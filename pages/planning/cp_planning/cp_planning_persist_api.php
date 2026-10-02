<?php
session_start();

if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
    ]);
    exit;
}

include '../../../koneksi.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!function_exists('cpPlannerPersistJsonExit')) {
    function cpPlannerPersistJsonExit(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('cpPlannerPersistSqlsrvErrorText')) {
    function cpPlannerPersistSqlsrvErrorText(string $fallback = 'Terjadi kesalahan database.'): string
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if (!is_array($errors) || empty($errors)) {
            return $fallback;
        }

        $parts = [];
        foreach ($errors as $err) {
            $msg = trim((string)($err['message'] ?? ''));
            if ($msg !== '') {
                $parts[] = $msg;
            }
        }

        if (empty($parts)) {
            return $fallback;
        }
        return implode(' | ', $parts);
    }
}

if (!function_exists('cpPlannerPersistNormalizeMachineId')) {
    function cpPlannerPersistNormalizeMachineId($value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('cpPlannerPersistGetTableColumns')) {
    function cpPlannerPersistGetTableColumns($conn, string $tableName): array
    {
        $cleanTable = trim($tableName);
        if ($cleanTable === '') {
            return [];
        }

        $tableOnly = $cleanTable;
        if (strpos($cleanTable, '.') !== false) {
            $parts = explode('.', $cleanTable);
            $tableOnly = trim((string)end($parts));
        }
        $tableOnly = trim($tableOnly, "[] \t\n\r\0\x0B");
        if ($tableOnly === '') {
            return [];
        }

        $stmtCols = sqlsrv_query(
            $conn,
            "SELECT LOWER(COLUMN_NAME) AS col_name
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?",
            [$tableOnly]
        );
        if ($stmtCols === false) {
            return [];
        }

        $cols = [];
        while ($rc = sqlsrv_fetch_array($stmtCols, SQLSRV_FETCH_ASSOC)) {
            $colName = trim((string)($rc['col_name'] ?? ''));
            if ($colName !== '') {
                $cols[$colName] = true;
            }
        }
        sqlsrv_free_stmt($stmtCols);
        return $cols;
    }
}

if (!function_exists('cpPlannerPersistGetTableColumnMeta')) {
    function cpPlannerPersistGetTableColumnMeta($conn, string $tableName): array
    {
        $cleanTable = trim($tableName);
        if ($cleanTable === '') {
            return [];
        }

        $tableOnly = $cleanTable;
        if (strpos($cleanTable, '.') !== false) {
            $parts = explode('.', $cleanTable);
            $tableOnly = trim((string)end($parts));
        }
        $tableOnly = trim($tableOnly, "[] \t\n\r\0\x0B");
        if ($tableOnly === '') {
            return [];
        }

        $stmtCols = sqlsrv_query(
            $conn,
            "SELECT
                LOWER(COLUMN_NAME) AS col_name,
                LOWER(DATA_TYPE) AS data_type,
                CAST(
                    COLUMNPROPERTY(
                        OBJECT_ID(QUOTENAME(TABLE_SCHEMA) + '.' + QUOTENAME(TABLE_NAME)),
                        COLUMN_NAME,
                        'IsIdentity'
                    ) AS INT
                ) AS is_identity,
                CAST(
                    COLUMNPROPERTY(
                        OBJECT_ID(QUOTENAME(TABLE_SCHEMA) + '.' + QUOTENAME(TABLE_NAME)),
                        COLUMN_NAME,
                        'IsComputed'
                    ) AS INT
                ) AS is_computed
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION ASC",
            [$tableOnly]
        );
        if ($stmtCols === false) {
            return [];
        }

        $meta = [];
        while ($rc = sqlsrv_fetch_array($stmtCols, SQLSRV_FETCH_ASSOC)) {
            $colName = trim((string)($rc['col_name'] ?? ''));
            if ($colName === '') {
                continue;
            }
            $meta[] = [
                'name' => $colName,
                'data_type' => strtolower(trim((string)($rc['data_type'] ?? ''))),
                'is_identity' => ((int)($rc['is_identity'] ?? 0) === 1),
                'is_computed' => ((int)($rc['is_computed'] ?? 0) === 1),
            ];
        }
        sqlsrv_free_stmt($stmtCols);

        return $meta;
    }
}

if (!function_exists('cpPlannerPersistResequenceByMachinePeriod')) {
    function cpPlannerPersistResequenceByMachinePeriod($conn, string $tableName, string $machineId, string $periodDate, ?array $tableCols = null): void
    {
        $cols = is_array($tableCols) ? $tableCols : cpPlannerPersistGetTableColumns($conn, $tableName);
        if (empty($cols) || !isset($cols['id']) || !isset($cols['seq_no'])) {
            return;
        }

        $stmtRows = sqlsrv_query(
            $conn,
            "SELECT id
             FROM {$tableName}
             WHERE machine_id = ? AND period_date = ?
             ORDER BY
                CASE WHEN seq_no IS NULL THEN 1 ELSE 0 END ASC,
                seq_no ASC,
                id ASC",
            [$machineId, $periodDate]
        );
        if ($stmtRows === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal mengambil urutan data.'));
        }

        $ids = [];
        while ($row = sqlsrv_fetch_array($stmtRows, SQLSRV_FETCH_ASSOC)) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        sqlsrv_free_stmt($stmtRows);

        if (empty($ids)) {
            return;
        }

        $seqNo = 1;
        foreach ($ids as $rowId) {
            $stmtUpdateSeq = sqlsrv_query(
                $conn,
                "UPDATE {$tableName} SET seq_no = ? WHERE id = ?",
                [$seqNo, $rowId]
            );
            if ($stmtUpdateSeq === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal merapikan urutan data.'));
            }
            sqlsrv_free_stmt($stmtUpdateSeq);
            $seqNo++;
        }
    }
}

if ($action === 'save_bakar_bulu') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $rowsRaw = $_POST['rows'] ?? '[]';
    $rows = json_decode((string)$rowsRaw, true);
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if (!is_array($rows)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Format data rows tidak valid.',
        ]);
    }

    $toNull = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    };

    $toDateSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d', $ts);
    };

    $toTimeSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text)) {
            return strlen($text) === 5 ? ($text . ':00') : $text;
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('H:i:s', $ts);
    };

    $toDecimal = static function ($value): ?float {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (float)$text;
    };

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi simpan.'),
        ]);
    }

    try {
        $stmtDelete = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.cpp_bakar_bulu WHERE machine_id = ? AND period_date = ?",
            [$machineId, $periodDate]
        );
        if ($stmtDelete === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menghapus data lama.'));
        }
        sqlsrv_free_stmt($stmtDelete);

        $insertSql = "
            INSERT INTO dbo.cpp_bakar_bulu (
                period_date,
                machine_id,
                seq_no,
                cp_no,
                tgl_cp,
                label_jual,
                cust_color,
                kode_lab,
                routing_name,
                qty,
                material_name,
                plan_machine,
                plan_date,
                plan_start,
                plan_end,
                plan_description,
                actual_date,
                actual_start,
                actual_end,
                actual_shift,
                actual_realisasi,
                posisi_hari_ini,
                next_routing,
                last_update,
                updated_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?
            )
        ";

        $savedCount = 0;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cpNo = $toNull($row['no_cp'] ?? null);
            if ($cpNo === null) {
                continue;
            }

            $seqNo = (int)($row['seq_no'] ?? ($idx + 1));
            if ($seqNo <= 0) {
                $seqNo = $idx + 1;
            }

            $params = [
                $periodDate,
                $machineId,
                $seqNo,
                $cpNo,
                $toDateSql($row['tgl_cp'] ?? null),
                $toNull($row['label'] ?? null),
                $toNull($row['cust_color'] ?? null),
                $toNull($row['kode_lab'] ?? null),
                $toNull($row['routing_name'] ?? null),
                $toDecimal($row['qty'] ?? null),
                $toNull($row['material'] ?? null),
                $toNull($row['plan_machine'] ?? null),
                $toDateSql($row['plan_date'] ?? null),
                $toTimeSql($row['plan_start'] ?? null),
                $toTimeSql($row['plan_end'] ?? null),
                $toNull($row['plan_description'] ?? null),
                $toDateSql($row['actual_date'] ?? null),
                $toTimeSql($row['actual_start'] ?? null),
                $toTimeSql($row['actual_end'] ?? null),
                $toNull($row['actual_shift'] ?? null),
                $toDecimal($row['actual_realisasi'] ?? null),
                $toNull($row['posisi_hari_ini'] ?? null),
                $toNull($row['next_routing'] ?? null),
                $currUser,
            ];

            $stmtInsert = sqlsrv_query($conn, $insertSql, $params);
            if ($stmtInsert === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyimpan data baris CP.'));
            }
            sqlsrv_free_stmt($stmtInsert);
            $savedCount++;
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit transaksi simpan.'));
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $savedCount . ' data bakar bulu berhasil disimpan permanen.',
            'saved_count' => $savedCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'save_paddry') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $rowsRaw = $_POST['rows'] ?? '[]';
    $rows = json_decode((string)$rowsRaw, true);
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if (!is_array($rows)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Format data rows tidak valid.',
        ]);
    }

    $toNull = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    };

    $toDateSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d', $ts);
    };

    $toDateTimeSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $text)) {
            return strlen($text) === 16 ? ($text . ':00') : $text;
        }
        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    };

    $toTimeSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text)) {
            return strlen($text) === 5 ? ($text . ':00') : $text;
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('H:i:s', $ts);
    };

    $toDecimal = static function ($value): ?float {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (float)$text;
    };

    $tableCols = [];
    $stmtCols = sqlsrv_query($conn, "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'cpp_paddry'
    ");
    if ($stmtCols !== false) {
        while ($rc = sqlsrv_fetch_array($stmtCols, SQLSRV_FETCH_ASSOC)) {
            $colName = strtolower(trim((string)($rc['COLUMN_NAME'] ?? '')));
            if ($colName !== '') {
                $tableCols[$colName] = true;
            }
        }
        sqlsrv_free_stmt($stmtCols);
    }
    if (empty($tableCols)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Tabel cpp_paddry belum tersedia atau tidak bisa dibaca.',
        ]);
    }
    $hasPlanDateCol = isset($tableCols['tgl']) || isset($tableCols['plan_date']);

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi simpan paddry.'),
        ]);
    }

    try {
        $stmtDelete = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.cpp_paddry WHERE machine_id = ? AND period_date = ?",
            [$machineId, $periodDate]
        );
        if ($stmtDelete === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menghapus data paddry lama.'));
        }
        sqlsrv_free_stmt($stmtDelete);

        $savedCount = 0;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cpNo = $toNull($row['no_cp'] ?? null);
            if ($cpNo === null) {
                continue;
            }

            $seqNo = (int)($row['seq_no'] ?? ($idx + 1));
            if ($seqNo <= 0) {
                $seqNo = $idx + 1;
            }

            $actualTimbangLabStart = $toTimeSql($row['actual_timbang_lab_start'] ?? ($row['actual_timbang_lalab_start'] ?? null));
            $actualTimbangLabFinish = $toTimeSql($row['actual_timbang_lab_finish'] ?? ($row['actual_timbang_lalab_finish'] ?? null));
            $actualTimbangLaStart = $toTimeSql($row['actual_timbang_la_start'] ?? null);
            $actualTimbangLaFinish = $toTimeSql($row['actual_timbang_la_finish'] ?? null);
            $actualLarutLabStart = $toTimeSql($row['actual_larut_lab_start'] ?? ($row['actual_larut_lalab_start'] ?? null));
            $actualLarutLabFinish = $toTimeSql($row['actual_larut_lab_finish'] ?? ($row['actual_larut_lalab_finish'] ?? null));
            $actualLarutLaStart = $toTimeSql($row['actual_larut_la_start'] ?? null);
            $actualLarutLaFinish = $toTimeSql($row['actual_larut_la_finish'] ?? null);
            $actualTimbangLalabStart = $toTimeSql($row['actual_timbang_lalab_start'] ?? ($row['actual_timbang_lab_start'] ?? null));
            $actualTimbangLalabFinish = $toTimeSql($row['actual_timbang_lalab_finish'] ?? ($row['actual_timbang_lab_finish'] ?? null));
            $actualLarutLalabStart = $toTimeSql($row['actual_larut_lalab_start'] ?? ($row['actual_larut_lab_start'] ?? null));
            $actualLarutLalabFinish = $toTimeSql($row['actual_larut_lalab_finish'] ?? ($row['actual_larut_lab_finish'] ?? null));
            $actualLarutPrdksStart = $toTimeSql($row['actual_larut_prdks_start'] ?? ($row['actual_larut_produksi_start'] ?? null));
            $actualLarutPrdksFinish = $toTimeSql($row['actual_larut_prdks_finish'] ?? ($row['actual_larut_produksi_finish'] ?? null));
            $actualToppingPaddryStart = $toTimeSql($row['actual_topping_paddry_start'] ?? ($row['actual_topping_start'] ?? null));
            $actualToppingPaddryFinish = $toTimeSql($row['actual_topping_paddry_finish'] ?? ($row['actual_topping_finish'] ?? null));
            // Guard: UI kadang mengirim plan_date kosong/format beda; default ke period_date supaya kolom Tgl tidak hilang setelah reload.
            $planDateSql = $toDateSql($row['plan_date'] ?? null) ?: $periodDate;
            $lokasiPaddryValue = $toNull($row['lokasi_paddry'] ?? null);
            if ($lokasiPaddryValue === null) {
                $lokasiPaddryValue = $toNull($row['routing_name'] ?? null);
            }
            $isBreakTime = false;
            $isBreakRaw = strtolower(trim((string)($row['is_break_time'] ?? '')));
            if ($isBreakRaw === '1' || $isBreakRaw === 'true' || $isBreakRaw === 'y' || $isBreakRaw === 'yes') {
                $isBreakTime = true;
            }
            $isLateMoveSource = false;
            $lateMoveSourceRaw = strtolower(trim((string)($row['late_move_source_flag'] ?? ($row['is_late_move_source'] ?? ''))));
            if ($lateMoveSourceRaw === '1' || $lateMoveSourceRaw === 'true' || $lateMoveSourceRaw === 'y' || $lateMoveSourceRaw === 'yes') {
                $isLateMoveSource = true;
            }

            $valueMap = [
                'period_date' => $periodDate,
                'machine_id' => $machineId,
                'seq_no' => $seqNo,
                'cp_no' => $cpNo,
                'is_break_time' => $isBreakTime ? 1 : 0,
                'break_time_name' => $toNull($row['break_time_name'] ?? ($isBreakTime ? $cpNo : null)),
                'tgl_cp' => $toDateSql($row['tgl_cp'] ?? null),
                'label' => $toNull($row['label'] ?? null),
                'label_jual' => $toNull($row['label'] ?? null),
                'cust_color' => $toNull($row['cust_color'] ?? null),
                'kode_lab' => $toNull($row['kode_lab'] ?? null),
                'routing_name' => $toNull($row['routing_name'] ?? null),
                'lokasi_paddry' => $lokasiPaddryValue,
                'material_name' => $toNull($row['material'] ?? null),
                'qty' => $toDecimal($row['qty'] ?? null),
                'posisi_hari_ini' => $toNull($row['posisi_hari_ini'] ?? null),
                'speed' => $toNull($row['speed'] ?? null),
                'temperatur' => $toNull($row['temperatur'] ?? ($row['temperature'] ?? null)),
                'resep_lipat' => $toNull($row['resp_lipat'] ?? null),
                'status_resep' => $toNull($row['status_resp'] ?? null),
                'vlot_resep' => $toNull($row['vlot_resp'] ?? null),
                'bon_resep' => $toNull($row['bon_resp'] ?? null),
                'ket' => $toNull($row['plan_description'] ?? null),
                'plan_description' => $toNull($row['plan_description'] ?? null),
                'tgl' => $planDateSql,
                'plan_date' => $planDateSql,
                'est_tmbng_plrtn_lalab' => $toTimeSql($row['est_tmbng_plrtm_lalab'] ?? null),
                'est_tmbng_plrtm_lalab' => $toTimeSql($row['est_tmbng_plrtm_lalab'] ?? null),
                'est_plrtn_prdks' => $toTimeSql($row['est_plrtm_prdks'] ?? null),
                'est_plrtm_prdks' => $toTimeSql($row['est_plrtm_prdks'] ?? null),
                'rencana_start' => $toTimeSql($row['plan_start'] ?? null),
                'rencana_finish' => $toTimeSql($row['plan_end'] ?? null),
                'plan_start' => $toTimeSql($row['plan_start'] ?? null),
                'plan_finish' => $toTimeSql($row['plan_end'] ?? null),
                'plan_end' => $toTimeSql($row['plan_end'] ?? null),
                'aktual_start' => $toTimeSql($row['actual_start'] ?? null),
                'aktual_finish' => $toTimeSql($row['actual_end'] ?? null),
                'actual_start' => $toTimeSql($row['actual_start'] ?? null),
                'actual_finish' => $toTimeSql($row['actual_end'] ?? null),
                'actual_end' => $toTimeSql($row['actual_end'] ?? null),
                'aktual_timbang_lab_start' => $actualTimbangLabStart,
                'aktual_timbang_lab_finish' => $actualTimbangLabFinish,
                'actual_timbang_lab_start' => $actualTimbangLabStart,
                'actual_timbang_lab_finish' => $actualTimbangLabFinish,
                'aktual_timbang_la_start' => $actualTimbangLaStart,
                'aktual_timbang_la_finish' => $actualTimbangLaFinish,
                'actual_timbang_la_start' => $actualTimbangLaStart,
                'actual_timbang_la_finish' => $actualTimbangLaFinish,
                'aktual_timbang_lalab_start' => $actualTimbangLalabStart,
                'aktual_timbang_lalab_finish' => $actualTimbangLalabFinish,
                'actual_timbang_lalab_start' => $actualTimbangLalabStart,
                'actual_timbang_lalab_finish' => $actualTimbangLalabFinish,
                'aktual_larut_lab_start' => $actualLarutLabStart,
                'aktual_larut_lab_finish' => $actualLarutLabFinish,
                'actual_larut_lab_start' => $actualLarutLabStart,
                'actual_larut_lab_finish' => $actualLarutLabFinish,
                'aktual_larut_la_start' => $actualLarutLaStart,
                'aktual_larut_la_finish' => $actualLarutLaFinish,
                'actual_larut_la_start' => $actualLarutLaStart,
                'actual_larut_la_finish' => $actualLarutLaFinish,
                'aktual_larut_lalab_start' => $actualLarutLalabStart,
                'aktual_larut_lalab_finish' => $actualLarutLalabFinish,
                'actual_larut_lalab_start' => $actualLarutLalabStart,
                'actual_larut_lalab_finish' => $actualLarutLalabFinish,
                'aktual_larut_prdks_start' => $actualLarutPrdksStart,
                'aktual_larut_prdks_finish' => $actualLarutPrdksFinish,
                'actual_larut_prdks_start' => $actualLarutPrdksStart,
                'actual_larut_prdks_finish' => $actualLarutPrdksFinish,
                'aktual_larut_produksi_start' => $actualLarutPrdksStart,
                'aktual_larut_produksi_finish' => $actualLarutPrdksFinish,
                'actual_larut_produksi_start' => $actualLarutPrdksStart,
                'actual_larut_produksi_finish' => $actualLarutPrdksFinish,
                'aktual_topping_paddry_start' => $actualToppingPaddryStart,
                'aktual_topping_paddry_finish' => $actualToppingPaddryFinish,
                'actual_topping_paddry_start' => $actualToppingPaddryStart,
                'actual_topping_paddry_finish' => $actualToppingPaddryFinish,
                'aktual_topping_start' => $actualToppingPaddryStart,
                'aktual_topping_finish' => $actualToppingPaddryFinish,
                'actual_topping_start' => $actualToppingPaddryStart,
                'actual_topping_finish' => $actualToppingPaddryFinish,
                'actual_realisasi' => $toDecimal($row['actual_realisasi'] ?? null),
                'aktual_qty' => $toDecimal($row['actual_realisasi'] ?? ($row['aktual_qty'] ?? null)),
                'vlot_aktual' => $toNull($row['actual_vlot'] ?? null),
                'actual_vlot' => $toNull($row['actual_vlot'] ?? null),
                'sisa_larut' => $toNull($row['sisa_saturator'] ?? null),
                'sisa_saturator' => $toNull($row['sisa_saturator'] ?? null),
                'sample_kain' => $toNull($row['sample'] ?? null),
                'sample' => $toNull($row['sample'] ?? null),
                'rko' => $toDateSql($row['rko'] ?? null),
                'next_routing' => $toNull($row['next_routing'] ?? null),
                'last_update' => date('Y-m-d H:i:s'),
                'update_by' => $currUser,
                'updated_by' => $currUser,
                'created_by' => $currUser,
                'modified_by' => $currUser,
                'modified_at' => date('Y-m-d H:i:s'),
                'is_late_move_source' => $isLateMoveSource ? 1 : 0,
                'late_move_at' => $toDateTimeSql($row['late_move_at'] ?? null),
                'late_move_target_period_date' => $toDateSql($row['late_move_target_period_date'] ?? null),
                'late_move_target_machine_id' => $toNull($row['late_move_target_machine_id'] ?? null),
            ];

            $insertCols = [];
            $insertQ = [];
            $insertParams = [];

            foreach ($valueMap as $col => $val) {
                if (!isset($tableCols[$col])) {
                    continue;
                }
                $insertCols[] = '[' . $col . ']';
                $insertQ[] = '?';
                $insertParams[] = $val;
            }

            if (empty($insertCols)) {
                continue;
            }

            $sqlInsert = "INSERT INTO dbo.cpp_paddry (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertQ) . ")";
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $insertParams);
            if ($stmtInsert === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyimpan data paddry.'));
            }
            sqlsrv_free_stmt($stmtInsert);
            $savedCount++;
        }

        // Pastikan kolom tanggal baru tidak kosong (mis. kolom baru ditambahkan dan ada baris yang terlanjur tersimpan NULL)
        if (isset($tableCols['tgl'])) {
            $stmtFix = sqlsrv_query(
                $conn,
                "UPDATE dbo.cpp_paddry SET tgl = COALESCE(tgl, period_date) WHERE machine_id = ? AND period_date = ?",
                [$machineId, $periodDate]
            );
            if ($stmtFix === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal memperbaiki kolom tgl paddry.'));
            }
            sqlsrv_free_stmt($stmtFix);
        }
        if (isset($tableCols['plan_date'])) {
            $stmtFix2 = sqlsrv_query(
                $conn,
                "UPDATE dbo.cpp_paddry SET plan_date = COALESCE(plan_date, period_date) WHERE machine_id = ? AND period_date = ?",
                [$machineId, $periodDate]
            );
            if ($stmtFix2 === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal memperbaiki kolom plan_date paddry.'));
            }
            sqlsrv_free_stmt($stmtFix2);
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit transaksi paddry.'));
        }

        $msg = $savedCount . ' data paddry berhasil disimpan permanen.';
        if (!$hasPlanDateCol) {
            $msg .= ' (Catatan: kolom tgl/plan_date tidak ada di tabel, jadi Tgl tidak tersimpan.)';
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $msg,
            'saved_count' => $savedCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'save_scouring') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $rowsRaw = $_POST['rows'] ?? '[]';
    $rows = json_decode((string)$rowsRaw, true);
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if (!is_array($rows)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Format data rows tidak valid.',
        ]);
    }

    $toNull = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    };

    $toDateSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d', $ts);
    };

    $toTimeSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text)) {
            return strlen($text) === 5 ? ($text . ':00') : $text;
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('H:i:s', $ts);
    };

    $toDecimal = static function ($value): ?float {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }

        // Handle pure numeric values first.
        $normalized = str_replace(',', '', $text);
        if (is_numeric($normalized)) {
            return (float)$normalized;
        }

        // Fallback: extract first numeric token from values with units (e.g. "131 m/m").
        if (preg_match('/-?\d+(?:[.,]\d+)?/', $text, $m)) {
            $candidate = (string)($m[0] ?? '');
            if ($candidate !== '') {
                if (strpos($candidate, ',') !== false && strpos($candidate, '.') === false) {
                    // Single comma as decimal separator.
                    $candidate = str_replace(',', '.', $candidate);
                } else {
                    // Commas as thousand separators.
                    $candidate = str_replace(',', '', $candidate);
                }
                if (is_numeric($candidate)) {
                    return (float)$candidate;
                }
            }
        }

        return null;
    };

    $toInt = static function ($value): ?int {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (int)round((float)$text);
    };

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi simpan scouring.'),
        ]);
    }

    try {
        $stmtDelete = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.cpp_scouring WHERE machine_id = ? AND period_date = ?",
            [$machineId, $periodDate]
        );
        if ($stmtDelete === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menghapus data scouring lama.'));
        }
        sqlsrv_free_stmt($stmtDelete);

        $insertSql = "
            INSERT INTO dbo.cpp_scouring (
                period_date,
                machine_id,
                seq_no,
                cp_no,
                tgl_cp,
                label_jual,
                cust_color,
                kode_lab,
                routing_name,
                qty,
                material_name,
                speed,
                grammature,
                plan_machine,
                plan_date,
                plan_paddry,
                plan_description,
                actual_date,
                actual_start,
                actual_end,
                actual_operator,
                actual_shift,
                down_time,
                actual_realisasi,
                posisi_hari_ini,
                next_routing,
                last_update,
                updated_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?
            )
        ";

        $savedCount = 0;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cpNo = $toNull($row['no_cp'] ?? null);
            if ($cpNo === null) {
                continue;
            }

            $seqNo = (int)($row['seq_no'] ?? ($idx + 1));
            if ($seqNo <= 0) {
                $seqNo = $idx + 1;
            }

            $params = [
                $periodDate,
                $machineId,
                $seqNo,
                $cpNo,
                $toDateSql($row['tgl_cp'] ?? null),
                $toNull($row['label'] ?? null),
                $toNull($row['cust_color'] ?? null),
                $toNull($row['kode_lab'] ?? null),
                $toNull($row['routing_name'] ?? null),
                $toDecimal($row['qty'] ?? null),
                $toNull($row['material'] ?? null),
                $toDecimal($row['speed'] ?? null),
                $toDecimal($row['grammature'] ?? null),
                $toNull($row['plan_machine'] ?? null),
                $toDateSql($row['plan_date'] ?? null),
                $toNull($row['plan_paddry'] ?? null),
                $toNull($row['plan_description'] ?? null),
                $toDateSql($row['actual_date'] ?? null),
                $toTimeSql($row['actual_start'] ?? null),
                $toTimeSql($row['actual_end'] ?? null),
                $toNull($row['actual_operator'] ?? null),
                $toNull($row['actual_shift'] ?? null),
                $toInt($row['down_time'] ?? null),
                $toDecimal($row['actual_realisasi'] ?? null),
                $toNull($row['posisi_hari_ini'] ?? null),
                $toNull($row['next_routing'] ?? null),
                $currUser,
            ];

            $stmtInsert = sqlsrv_query($conn, $insertSql, $params);
            if ($stmtInsert === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyimpan data scouring.'));
            }
            sqlsrv_free_stmt($stmtInsert);
            $savedCount++;
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit transaksi scouring.'));
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $savedCount . ' data scouring berhasil disimpan permanen.',
            'saved_count' => $savedCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'save_presett') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $rowsRaw = $_POST['rows'] ?? '[]';
    $rows = json_decode((string)$rowsRaw, true);
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if (!is_array($rows)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Format data rows tidak valid.',
        ]);
    }

    $toNull = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    };

    $toDateSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d', $ts);
    };

    $toTimeSql = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text)) {
            return strlen($text) === 5 ? ($text . ':00') : $text;
        }
        $ts = strtotime($text);
        return $ts === false ? null : date('H:i:s', $ts);
    };

    $toDecimal = static function ($value): ?float {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (float)$text;
    };

    $toInt = static function ($value): ?int {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (int)round((float)$text);
    };

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi simpan presett.'),
        ]);
    }

    try {
        $stmtDelete = sqlsrv_query(
            $conn,
            "DELETE FROM dbo.cpp_presett WHERE machine_id = ? AND period_date = ?",
            [$machineId, $periodDate]
        );
        if ($stmtDelete === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menghapus data presett lama.'));
        }
        sqlsrv_free_stmt($stmtDelete);

        $insertSql = "
            INSERT INTO dbo.cpp_presett (
                period_date,
                machine_id,
                seq_no,
                cp_no,
                tgl_cp,
                urut_plan,
                label_jual,
                cust_color,
                kode_lab,
                material_name,
                qty,
                ket,
                posisi_hari_ini,
                plan_date,
                plan_machine,
                actual_start,
                actual_end,
                actual_operator,
                actual_shift,
                status,
                down_time,
                keterangan,
                last_update,
                updated_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?
            )
        ";

        $savedCount = 0;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cpNo = $toNull($row['no_cp'] ?? null);
            if ($cpNo === null) {
                continue;
            }

            $seqNo = (int)($row['seq_no'] ?? ($idx + 1));
            if ($seqNo <= 0) {
                $seqNo = $idx + 1;
            }

            $params = [
                $periodDate,
                $machineId,
                $seqNo,
                $cpNo,
                $toDateSql($row['tgl_cp'] ?? null),
                $toNull($row['urut_plan'] ?? null),
                $toNull($row['label'] ?? null),
                $toNull($row['cust_color'] ?? null),
                $toNull($row['kode_lab'] ?? null),
                $toNull($row['material'] ?? null),
                $toDecimal($row['qty'] ?? null),
                $toNull($row['ket'] ?? null),
                $toNull($row['posisi_hari_ini'] ?? null),
                $toDateSql($row['plan_date'] ?? null),
                $toNull($row['plan_machine'] ?? null),
                $toTimeSql($row['actual_start'] ?? null),
                $toTimeSql($row['actual_end'] ?? null),
                $toNull($row['actual_operator'] ?? null),
                $toNull($row['actual_shift'] ?? null),
                $toNull($row['actual_wheel_no'] ?? ($row['status'] ?? null)),
                $toInt($row['down_time'] ?? null),
                $toNull($row['plan_description'] ?? ($row['keterangan'] ?? null)),
                $currUser,
            ];

            $stmtInsert = sqlsrv_query($conn, $insertSql, $params);
            if ($stmtInsert === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyimpan data presett.'));
            }
            sqlsrv_free_stmt($stmtInsert);
            $savedCount++;
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit transaksi presett.'));
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $savedCount . ' data presett berhasil disimpan permanen.',
            'saved_count' => $savedCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'update_posisi_hari_ini') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $layout = strtolower(trim((string)($_POST['layout'] ?? '')));
    $rowsRaw = $_POST['rows'] ?? '[]';
    $rows = json_decode((string)$rowsRaw, true);
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if (!is_array($rows)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Format rows tidak valid.',
        ]);
    }

    $tableMap = [
        'bakar_bulu' => 'dbo.cpp_bakar_bulu',
        'paddry' => 'dbo.cpp_paddry',
        'scouring' => 'dbo.cpp_scouring',
        'presett' => 'dbo.cpp_presett',
    ];
    if (!isset($tableMap[$layout])) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Layout tidak valid untuk update posisi.',
        ]);
    }

    $tableName = $tableMap[$layout];
    $tableCols = cpPlannerPersistGetTableColumns($conn, $tableName);
    if (empty($tableCols) || !isset($tableCols['id'])) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Tabel tujuan belum siap (kolom wajib tidak ditemukan).',
        ]);
    }

    $toNull = static function ($value): ?string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    };
    $toDecimal = static function ($value): ?float {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (!is_numeric($text)) {
            return null;
        }
        return (float)$text;
    };

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi update data.'),
        ]);
    }

    try {
        $updatedCount = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = (int)($row['row_id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $valueMap = [
                'posisi_hari_ini' => $toNull($row['posisi_hari_ini'] ?? null),
                'next_routing' => $toNull($row['next_routing'] ?? null),
                'kode_lab' => $toNull($row['kode_lab'] ?? null),
                'cust_color' => $toNull($row['cust_color'] ?? null),
                'material_name' => $toNull($row['material'] ?? null),
                'material' => $toNull($row['material'] ?? null),
                'vlot_resep' => $toNull($row['vlot_resp'] ?? null),
                'plan_description' => $toNull($row['plan_description'] ?? null),
                'ket' => $toNull($row['plan_description'] ?? null),
            ];
            $labelVal = $toNull($row['label'] ?? null);
            $valueMap['label'] = $labelVal;
            $valueMap['label_jual'] = $labelVal;

            $qtyLatest = $toDecimal($row['qty_latest'] ?? null);
            if ($qtyLatest !== null) {
                if ($layout === 'paddry') {
                    // Rule khusus paddry: qty source masuk ke Aktual Qty, jangan ubah Planning Qty.
                    $valueMap['actual_realisasi'] = $qtyLatest;
                    $valueMap['aktual_qty'] = $qtyLatest;
                } else {
                    $valueMap['qty'] = $qtyLatest;
                }
            }

            $actualQty = $toDecimal($row['actual_realisasi'] ?? null);
            if ($actualQty !== null) {
                $valueMap['actual_realisasi'] = $actualQty;
                $valueMap['aktual_qty'] = $actualQty;
            }

            $setParts = [];
            $params = [];
            foreach ($valueMap as $col => $val) {
                if (!isset($tableCols[$col])) {
                    continue;
                }
                $setParts[] = '[' . $col . '] = ?';
                $params[] = $val;
            }

            if (isset($tableCols['last_update'])) {
                $setParts[] = 'last_update = GETDATE()';
            }
            if (isset($tableCols['updated_by'])) {
                $setParts[] = 'updated_by = ?';
                $params[] = $currUser;
            } elseif (isset($tableCols['update_by'])) {
                $setParts[] = 'update_by = ?';
                $params[] = $currUser;
            }

            if (empty($setParts)) {
                continue;
            }

            $params[] = $rowId;
            $params[] = $machineId;
            $params[] = $periodDate;

            $stmtUpdate = sqlsrv_query(
                $conn,
                "UPDATE {$tableName}
                 SET " . implode(', ', $setParts) . "
                 WHERE id = ? AND machine_id = ? AND period_date = ?",
                $params
            );
            if ($stmtUpdate === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal mengupdate data.'));
            }

            $affected = sqlsrv_rows_affected($stmtUpdate);
            sqlsrv_free_stmt($stmtUpdate);
            if ($affected !== false && $affected > 0) {
                $updatedCount += (int)$affected;
            }
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit update data.'));
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $updatedCount . ' data berhasil diupdate permanen.',
            'updated_count' => $updatedCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'move_planning_rows') {
    $layout = strtolower(trim((string)($_POST['layout'] ?? '')));
    $sourceMachineId = cpPlannerPersistNormalizeMachineId($_POST['source_machine_id'] ?? '');
    $sourcePeriodDate = trim((string)($_POST['source_period_date'] ?? ''));
    $targetMachineId = cpPlannerPersistNormalizeMachineId($_POST['target_machine_id'] ?? '');
    $targetPeriodDate = trim((string)($_POST['target_period_date'] ?? ''));
    $rowIdsInput = $_POST['row_ids'] ?? [];
    $currUser = trim((string)($_SESSION['UserName'] ?? 'SYSTEM'));

    if (!is_array($rowIdsInput)) {
        $decoded = json_decode((string)$rowIdsInput, true);
        $rowIdsInput = is_array($decoded) ? $decoded : [];
    }

    $rowIds = [];
    foreach ($rowIdsInput as $rowIdRaw) {
        $rowId = (int)$rowIdRaw;
        if ($rowId > 0) {
            $rowIds[$rowId] = $rowId;
        }
    }
    $rowIds = array_values($rowIds);

    if ($sourceMachineId === '' || $targetMachineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine asal/tujuan tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourcePeriodDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetPeriodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Tanggal asal/tujuan tidak valid.',
        ]);
    }
    if ($sourceMachineId === $targetMachineId && $sourcePeriodDate === $targetPeriodDate) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Tujuan pindah harus berbeda dari asal.',
        ]);
    }
    if (empty($rowIds)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Data yang dipilih tidak valid.',
        ]);
    }

    $tableMap = [
        'bakar_bulu' => 'dbo.cpp_bakar_bulu',
        'paddry' => 'dbo.cpp_paddry',
        'scouring' => 'dbo.cpp_scouring',
        'presett' => 'dbo.cpp_presett',
    ];
    if (!isset($tableMap[$layout])) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Layout tidak valid untuk pindah data.',
        ]);
    }

    $tableName = $tableMap[$layout];
    $tableCols = cpPlannerPersistGetTableColumns($conn, $tableName);
    if (empty($tableCols) || !isset($tableCols['id']) || !isset($tableCols['machine_id']) || !isset($tableCols['period_date'])) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Struktur tabel tujuan belum siap.',
        ]);
    }

    if (!sqlsrv_begin_transaction($conn)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal membuka transaksi pindah data.'),
        ]);
    }

    try {
        $holders = implode(', ', array_fill(0, count($rowIds), '?'));
        $paramsSelect = array_merge($rowIds, [$sourceMachineId, $sourcePeriodDate]);
        $selectOrder = isset($tableCols['seq_no'])
            ? "CASE WHEN seq_no IS NULL THEN 1 ELSE 0 END ASC, seq_no ASC, id ASC"
            : "id ASC";
        $stmtSelect = sqlsrv_query(
            $conn,
            "SELECT id
             FROM {$tableName}
             WHERE id IN ({$holders})
               AND machine_id = ?
               AND period_date = ?
             ORDER BY {$selectOrder}",
            $paramsSelect
        );
        if ($stmtSelect === false) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal memvalidasi data yang dipilih.'));
        }

        $foundIds = [];
        while ($row = sqlsrv_fetch_array($stmtSelect, SQLSRV_FETCH_ASSOC)) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $foundIds[$id] = $id;
            }
        }
        sqlsrv_free_stmt($stmtSelect);

        if (empty($foundIds)) {
            throw new RuntimeException('Data terpilih tidak ditemukan pada machine/tanggal asal.');
        }

        $hasSeqNoCol = isset($tableCols['seq_no']);
        $nextTargetSeq = 1;
        if ($hasSeqNoCol) {
            $stmtMinSeq = sqlsrv_query(
                $conn,
                "SELECT MIN(CAST(seq_no AS INT)) AS min_seq
                 FROM {$tableName}
                 WHERE machine_id = ? AND period_date = ?",
                [$targetMachineId, $targetPeriodDate]
            );
            if ($stmtMinSeq === false) {
                throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal membaca urutan data tujuan.'));
            }
            $rowMinSeq = sqlsrv_fetch_array($stmtMinSeq, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtMinSeq);

            $foundCount = count($foundIds);
            $minSeqRaw = $rowMinSeq['min_seq'] ?? null;
            if ($minSeqRaw === null || $minSeqRaw === '') {
                $nextTargetSeq = 1;
            } else {
                $minSeq = (int)$minSeqRaw;
                // Sisipkan data pindahan di bagian atas urutan tujuan.
                $nextTargetSeq = $minSeq - max(1, $foundCount);
            }
        }

        $updateByCol = null;
        if (isset($tableCols['updated_by'])) {
            $updateByCol = 'updated_by';
        } elseif (isset($tableCols['update_by'])) {
            $updateByCol = 'update_by';
        }

        $tzJakarta = new DateTimeZone('Asia/Jakarta');
        $nowJakarta = new DateTime('now', $tzJakarta);
        $isLateCopyMode = false;
        if ($layout === 'paddry') {
            $sourceCutoff = DateTime::createFromFormat('Y-m-d', $sourcePeriodDate, $tzJakarta);
            if ($sourceCutoff instanceof DateTime) {
                // Cutoff selalu mengacu ke tanggal asal yang dipindahkan, pukul 18:00 WIB.
                $sourceCutoff->setTime(18, 0, 0);
                $isLateCopyMode = ($nowJakarta >= $sourceCutoff);
            }
        }
        $movedCount = 0;

        if ($isLateCopyMode) {
            $tableMeta = cpPlannerPersistGetTableColumnMeta($conn, $tableName);
            if (empty($tableMeta)) {
                throw new RuntimeException('Metadata kolom paddry tidak tersedia.');
            }

            $insertCols = [];
            $selectParts = [];
            $selectParamKinds = [];
            foreach ($tableMeta as $meta) {
                $col = (string)($meta['name'] ?? '');
                $dataType = (string)($meta['data_type'] ?? '');
                if ($col === '' || $col === 'id') {
                    continue;
                }
                if (!empty($meta['is_identity']) || !empty($meta['is_computed'])) {
                    continue;
                }
                if ($dataType === 'timestamp' || $dataType === 'rowversion') {
                    continue;
                }

                $insertCols[] = '[' . $col . ']';
                if ($col === 'machine_id') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'target_machine_id';
                } elseif ($col === 'period_date') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'target_period_date';
                } elseif ($col === 'seq_no') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'target_seq_no';
                } elseif ($col === 'tgl') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'target_tgl';
                } elseif ($col === 'plan_date') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'target_plan_date';
                } elseif ($col === 'is_late_move_source') {
                    $selectParts[] = '0';
                } elseif ($col === 'late_move_at' || $col === 'late_move_target_period_date' || $col === 'late_move_target_machine_id') {
                    $selectParts[] = 'NULL';
                } elseif ($col === 'last_update' || $col === 'modified_at') {
                    $selectParts[] = 'GETDATE()';
                } elseif ($col === 'updated_by' || $col === 'update_by' || $col === 'modified_by') {
                    $selectParts[] = '?';
                    $selectParamKinds[] = 'curr_user';
                } else {
                    $selectParts[] = '[' . $col . ']';
                }
            }

            if (empty($insertCols) || empty($selectParts)) {
                throw new RuntimeException('Kolom salin paddry tidak tersedia.');
            }

            $insertSql = "
                INSERT INTO {$tableName} (" . implode(', ', $insertCols) . ")
                SELECT " . implode(', ', $selectParts) . "
                FROM {$tableName}
                WHERE id = ?
                  AND machine_id = ?
                  AND period_date = ?
            ";

            $sourceSetParts = [];
            if (isset($tableCols['is_late_move_source'])) {
                $sourceSetParts[] = 'is_late_move_source = ?';
            }
            if (isset($tableCols['late_move_at'])) {
                $sourceSetParts[] = 'late_move_at = GETDATE()';
            }
            if (isset($tableCols['late_move_target_period_date'])) {
                $sourceSetParts[] = 'late_move_target_period_date = ?';
            }
            if (isset($tableCols['late_move_target_machine_id'])) {
                $sourceSetParts[] = 'late_move_target_machine_id = ?';
            }
            if (isset($tableCols['last_update'])) {
                $sourceSetParts[] = 'last_update = GETDATE()';
            }
            if ($updateByCol !== null) {
                $sourceSetParts[] = $updateByCol . ' = ?';
            }
            if (empty($sourceSetParts)) {
                throw new RuntimeException('Kolom penanda late move di source belum tersedia.');
            }

            $sourceUpdateSql = "
                UPDATE {$tableName}
                SET " . implode(', ', $sourceSetParts) . "
                WHERE id = ?
                  AND machine_id = ?
                  AND period_date = ?
            ";

            foreach ($foundIds as $rowId) {
                $paramsInsert = [];
                foreach ($selectParamKinds as $kind) {
                    if ($kind === 'target_machine_id') {
                        $paramsInsert[] = $targetMachineId;
                    } elseif ($kind === 'target_period_date') {
                        $paramsInsert[] = $targetPeriodDate;
                    } elseif ($kind === 'target_seq_no') {
                        $paramsInsert[] = $nextTargetSeq;
                    } elseif ($kind === 'target_tgl') {
                        $paramsInsert[] = $targetPeriodDate;
                    } elseif ($kind === 'target_plan_date') {
                        $paramsInsert[] = $targetPeriodDate;
                    } elseif ($kind === 'curr_user') {
                        $paramsInsert[] = $currUser;
                    }
                }
                $paramsInsert[] = $rowId;
                $paramsInsert[] = $sourceMachineId;
                $paramsInsert[] = $sourcePeriodDate;

                $stmtInsert = sqlsrv_query($conn, $insertSql, $paramsInsert);
                if ($stmtInsert === false) {
                    throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyalin data ke tanggal tujuan.'));
                }
                $inserted = sqlsrv_rows_affected($stmtInsert);
                sqlsrv_free_stmt($stmtInsert);
                if ($inserted === false) {
                    throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menyalin data ke tanggal tujuan.'));
                }
                $movedCount += ($inserted > 0) ? (int)$inserted : 1;
                if ($hasSeqNoCol) {
                    $nextTargetSeq++;
                }

                $paramsSourceUpdate = [];
                if (isset($tableCols['is_late_move_source'])) {
                    $paramsSourceUpdate[] = 1;
                }
                if (isset($tableCols['late_move_target_period_date'])) {
                    $paramsSourceUpdate[] = $targetPeriodDate;
                }
                if (isset($tableCols['late_move_target_machine_id'])) {
                    $paramsSourceUpdate[] = $targetMachineId;
                }
                if ($updateByCol !== null) {
                    $paramsSourceUpdate[] = $currUser;
                }
                $paramsSourceUpdate[] = $rowId;
                $paramsSourceUpdate[] = $sourceMachineId;
                $paramsSourceUpdate[] = $sourcePeriodDate;

                $stmtSourceUpdate = sqlsrv_query($conn, $sourceUpdateSql, $paramsSourceUpdate);
                if ($stmtSourceUpdate === false) {
                    throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal menandai data source hasil late move.'));
                }
                sqlsrv_free_stmt($stmtSourceUpdate);
            }

            cpPlannerPersistResequenceByMachinePeriod($conn, $tableName, $targetMachineId, $targetPeriodDate, $tableCols);
        } else {
            $setParts = [
                'machine_id = ?',
                'period_date = ?',
            ];
            if ($hasSeqNoCol) {
                $setParts[] = 'seq_no = ?';
            }
            if ($layout === 'paddry') {
                if (isset($tableCols['tgl'])) {
                    $setParts[] = 'tgl = ?';
                }
                if (isset($tableCols['plan_date'])) {
                    $setParts[] = 'plan_date = ?';
                }
            }
            if (isset($tableCols['last_update'])) {
                $setParts[] = 'last_update = GETDATE()';
            }
            if ($updateByCol !== null) {
                $setParts[] = $updateByCol . ' = ?';
            }
            if ($layout === 'paddry') {
                if (isset($tableCols['is_late_move_source'])) {
                    $setParts[] = 'is_late_move_source = 0';
                }
                if (isset($tableCols['late_move_at'])) {
                    $setParts[] = 'late_move_at = NULL';
                }
                if (isset($tableCols['late_move_target_period_date'])) {
                    $setParts[] = 'late_move_target_period_date = NULL';
                }
                if (isset($tableCols['late_move_target_machine_id'])) {
                    $setParts[] = 'late_move_target_machine_id = NULL';
                }
            }

            $updateSql = "
                UPDATE {$tableName}
                SET " . implode(', ', $setParts) . "
                WHERE id = ?
                  AND machine_id = ?
                  AND period_date = ?
            ";

            foreach ($foundIds as $rowId) {
                $paramsUpdate = [
                    $targetMachineId,
                    $targetPeriodDate,
                ];
                if ($hasSeqNoCol) {
                    $paramsUpdate[] = $nextTargetSeq;
                }
                if ($layout === 'paddry') {
                    if (isset($tableCols['tgl'])) {
                        $paramsUpdate[] = $targetPeriodDate;
                    }
                    if (isset($tableCols['plan_date'])) {
                        $paramsUpdate[] = $targetPeriodDate;
                    }
                }
                if ($updateByCol !== null) {
                    $paramsUpdate[] = $currUser;
                }
                $paramsUpdate[] = $rowId;
                $paramsUpdate[] = $sourceMachineId;
                $paramsUpdate[] = $sourcePeriodDate;

                $stmtUpdate = sqlsrv_query($conn, $updateSql, $paramsUpdate);
                if ($stmtUpdate === false) {
                    throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal memindahkan data.'));
                }
                $affected = sqlsrv_rows_affected($stmtUpdate);
                sqlsrv_free_stmt($stmtUpdate);
                if ($affected !== false && $affected > 0) {
                    $movedCount += (int)$affected;
                    if ($hasSeqNoCol) {
                        $nextTargetSeq++;
                    }
                }
            }

            cpPlannerPersistResequenceByMachinePeriod($conn, $tableName, $sourceMachineId, $sourcePeriodDate, $tableCols);
            cpPlannerPersistResequenceByMachinePeriod($conn, $tableName, $targetMachineId, $targetPeriodDate, $tableCols);
        }

        if (!sqlsrv_commit($conn)) {
            throw new RuntimeException(cpPlannerPersistSqlsrvErrorText('Gagal commit transaksi pindah data.'));
        }

        $notFoundCount = max(0, count($rowIds) - count($foundIds));
        $message = $movedCount . ' data berhasil dipindah.';
        if ($isLateCopyMode) {
            $message = $movedCount . ' data berhasil disalin ke tujuan karena proses pindah dilakukan >= 18:00. Data source tetap ada dan ditandai kuning.';
        }
        if ($notFoundCount > 0) {
            $message .= ' ' . $notFoundCount . ' data tidak ditemukan.';
        }

        cpPlannerPersistJsonExit([
            'success' => true,
            'message' => $message,
            'moved_count' => $movedCount,
            'not_found_count' => $notFoundCount,
        ]);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

if ($action === 'delete_bakar_bulu_row' || $action === 'delete_paddry_row' || $action === 'delete_scouring_row' || $action === 'delete_presett_row') {
    $machineId = cpPlannerPersistNormalizeMachineId($_POST['machine_id'] ?? '');
    $periodDate = trim((string)($_POST['period_date'] ?? ''));
    $rowId = (int)($_POST['row_id'] ?? 0);

    if ($machineId === '') {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }
    if ($rowId <= 0) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Row ID tidak valid.',
        ]);
    }

    if ($action === 'delete_paddry_row') {
        $tableName = 'dbo.cpp_paddry';
    } elseif ($action === 'delete_scouring_row') {
        $tableName = 'dbo.cpp_scouring';
    } elseif ($action === 'delete_presett_row') {
        $tableName = 'dbo.cpp_presett';
    } else {
        $tableName = 'dbo.cpp_bakar_bulu';
    }
    $stmtDelete = sqlsrv_query(
        $conn,
        "DELETE FROM {$tableName} WHERE id = ? AND machine_id = ? AND period_date = ?",
        [$rowId, $machineId, $periodDate]
    );
    if ($stmtDelete === false) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => cpPlannerPersistSqlsrvErrorText('Gagal menghapus data.'),
        ]);
    }

    $affected = sqlsrv_rows_affected($stmtDelete);
    sqlsrv_free_stmt($stmtDelete);

    if ($affected === 0) {
        cpPlannerPersistJsonExit([
            'success' => false,
            'message' => 'Data tidak ditemukan atau sudah terhapus.',
        ]);
    }

    cpPlannerPersistJsonExit([
        'success' => true,
        'message' => 'Data berhasil dihapus permanen.',
    ]);
}

cpPlannerPersistJsonExit([
    'success' => false,
    'message' => 'Action tidak dikenali.',
]);
