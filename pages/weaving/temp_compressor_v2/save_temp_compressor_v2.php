<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
weaving_require($conn, 'CanAdd', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);

$editExisting = isset($_POST['edit_existing']) && (string)$_POST['edit_existing'] === '1';
if ($editExisting) {
    weaving_require($conn, 'CanEdit', true);
}
if (!temp_compressor_v2_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor_v2 belum tersedia. Jalankan create_table_temp_compressor_v2.sql terlebih dahulu.']);
}

$tanggal = trim($_POST['tanggal'] ?? '');
$weaving = intval($_POST['weaving'] ?? 0);
$compressorNo = intval($_POST['compressor_no'] ?? 0);
$pelaksana = trim($_POST['pelaksana_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rawRows = $_POST['rows'] ?? [];
$allowedHours = temp_compressor_v2_hours();

if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal wajib diisi.']);
if (!in_array($weaving, [1, 2], true)) json_response(['success' => false, 'message' => 'Pilihan Weaving (1 / 2) wajib dipilih.']);
if (!in_array($compressorNo, [1, 2, 3], true)) json_response(['success' => false, 'message' => 'Pilihan Compressor No (1 / 2 / 3) wajib dipilih.']);
if ($pelaksana === '') json_response(['success' => false, 'message' => 'Pelaksana wajib diisi.']);
if (!is_array($rawRows)) json_response(['success' => false, 'message' => 'Format data input tidak valid.']);

$fieldColumns = [
    'pressure_p1' => 'PressureBar_P1',
    'pressure_p2' => 'PressureBar_P2',
    'temp_t1'     => 'Temperature_T1',
    'temp_t2'     => 'Temperature_T2',
    'temp_t3'     => 'Temperature_T3',
    'dryer_c'     => 'Dryer_C',
    'arus_a'      => 'ArusListrik_A',
    'press_in'    => 'AirCooling_PressIn',
    'press_out'   => 'AirCooling_PressOut',
    'temp_in'     => 'AirCooling_TempIn',
    'temp_out'    => 'AirCooling_TempOut',
];

$rowsToSave = [];
foreach ($rawRows as $row) {
    if (!is_array($row)) continue;
    $jam = trim((string)($row['jam'] ?? ''));
    if (!in_array($jam, $allowedHours, true)) continue;

    foreach ($fieldColumns as $field => $column) {
        if (!temp_compressor_v2_is_valid_decimal_input($row[$field] ?? '')) {
            json_response(['success' => false, 'message' => 'Baris jam ' . temp_compressor_v2_display_hour($jam) . ' berisi nilai yang tidak sesuai format angka.']);
        }
    }

    $pressP1  = temp_compressor_v2_normalize_decimal($row['pressure_p1'] ?? '');
    $pressP2  = temp_compressor_v2_normalize_decimal($row['pressure_p2'] ?? '');
    $tempT1   = temp_compressor_v2_normalize_decimal($row['temp_t1'] ?? '');
    $tempT2   = temp_compressor_v2_normalize_decimal($row['temp_t2'] ?? '');
    $tempT3   = temp_compressor_v2_normalize_decimal($row['temp_t3'] ?? '');
    $dryerC   = temp_compressor_v2_normalize_decimal($row['dryer_c'] ?? '');
    $arusA    = temp_compressor_v2_normalize_decimal($row['arus_a'] ?? '');
    $pressIn  = temp_compressor_v2_normalize_decimal($row['press_in'] ?? '');
    $pressOut = temp_compressor_v2_normalize_decimal($row['press_out'] ?? '');
    $tempIn   = temp_compressor_v2_normalize_decimal($row['temp_in'] ?? '');
    $tempOut  = temp_compressor_v2_normalize_decimal($row['temp_out'] ?? '');

    $hasValue = $pressP1 !== null || $pressP2 !== null
        || $tempT1 !== null || $tempT2 !== null || $tempT3 !== null
        || $dryerC !== null || $arusA !== null
        || $pressIn !== null || $pressOut !== null
        || $tempIn !== null || $tempOut !== null;
    if (!$hasValue) continue;

    $rowsToSave[] = [
        'jam'         => $jam,
        'pressure_p1' => $pressP1,
        'pressure_p2' => $pressP2,
        'temp_t1'     => $tempT1,
        'temp_t2'     => $tempT2,
        'temp_t3'     => $tempT3,
        'dryer_c'     => $dryerC,
        'arus_a'      => $arusA,
        'press_in'    => $pressIn,
        'press_out'   => $pressOut,
        'temp_in'     => $tempIn,
        'temp_out'    => $tempOut,
    ];
}

if (count($rowsToSave) === 0) {
    if ($editExisting) {
        $ketVal = ($keterangan !== '') ? $keterangan : null;
        $updateKetSql = "UPDATE dbo.temp_compressor_v2 SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE CAST(Tanggal AS DATE) = ? AND Weaving = ? AND Compressor_No = ?";
        $updateKetStmt = sqlsrv_query($conn, $updateKetSql, [$ketVal, $_SESSION['UserName'], $tanggal, $weaving, $compressorNo]);
        if ($updateKetStmt === false) {
            json_response(['success' => false, 'message' => 'Gagal memperbarui keterangan.']);
        }
        if ($updateKetStmt) sqlsrv_free_stmt($updateKetStmt);
        json_response(['success' => true, 'message' => 'Keterangan berhasil diperbarui.']);
    }
    json_response(['success' => false, 'message' => 'Isi minimal satu baris data pengecekan.']);
}

if (!sqlsrv_begin_transaction($conn)) json_response(['success' => false, 'message' => 'Gagal memulai transaksi simpan data.']);

$saved = 0;
$updated = 0;
$skipped = 0;
$userName = $_SESSION['UserName'];

foreach ($rowsToSave as $row) {
    $findSql = "SELECT TOP 1 Id,
                       PressureBar_P1, PressureBar_P2,
                       Temperature_T1, Temperature_T2, Temperature_T3,
                       Dryer_C, ArusListrik_A,
                       AirCooling_PressIn, AirCooling_PressOut,
                       AirCooling_TempIn, AirCooling_TempOut, Pelaksana
                FROM dbo.temp_compressor_v2
                WHERE CAST(Tanggal AS DATE) = ?
                  AND Weaving = ?
                  AND Compressor_No = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?";
    $findStmt = sqlsrv_query($conn, $findSql, [$tanggal, $weaving, $compressorNo, $row['jam']]);
    if ($findStmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal mengecek data existing.']);
    }
    $existing = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
    if ($findStmt) sqlsrv_free_stmt($findStmt);

    if ($existing && !empty($existing['Id'])) {
        $setParts = [];
        $updateParams = [];
        $hasFieldChange = false;

        foreach ($fieldColumns as $field => $column) {
            $existingValue = $existing[$column] ?? null;
            $newVal = $row[$field];
            if ($newVal !== null) {
                if ($editExisting) {
                    if (!temp_compressor_v2_values_equal($newVal, $existingValue)) {
                        $setParts[] = $column . ' = ?';
                        $updateParams[] = $newVal;
                        $hasFieldChange = true;
                    }
                } else if ($existingValue === null || $existingValue === '') {
                    $setParts[] = $column . ' = ?';
                    $updateParams[] = $newVal;
                    $hasFieldChange = true;
                }
            }
        }

        if (!$hasFieldChange) {
            $skipped++;
            continue;
        }

        $existingRowPelaksana = trim((string)($existing['Pelaksana'] ?? ''));
        $finalRowPelaksana = ($existingRowPelaksana !== '')
            ? temp_compressor_v2_merge_pelaksana_names($existingRowPelaksana, $pelaksana)
            : $pelaksana;
        $setParts[] = 'Pelaksana = ?';
        $updateParams[] = $finalRowPelaksana;

        if ($keterangan !== '') {
            $setParts[] = 'Keterangan = ?';
            $updateParams[] = $keterangan;
        }
        $setParts[] = 'UpdateBy = ?';
        $updateParams[] = $userName;
        $setParts[] = 'UpdateAt = GETDATE()';
        $updateParams[] = (int)$existing['Id'];

        $updateSql = "UPDATE dbo.temp_compressor_v2 SET " . implode(', ', $setParts) . " WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        if ($updateStmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal melengkapi data check sheet kompressor sullair yang sudah ada.']);
        }
        if ($updateStmt) sqlsrv_free_stmt($updateStmt);
        $updated++;
        continue;
    }

    $sql = "INSERT INTO dbo.temp_compressor_v2
            (Tanggal, Weaving, Compressor_No, Jam,
             PressureBar_P1, PressureBar_P2,
             Temperature_T1, Temperature_T2, Temperature_T3,
             Dryer_C, ArusListrik_A,
             AirCooling_PressIn, AirCooling_PressOut,
             AirCooling_TempIn, AirCooling_TempOut,
             Pelaksana, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, [
        $tanggal, $weaving, $compressorNo, $row['jam'],
        $row['pressure_p1'], $row['pressure_p2'],
        $row['temp_t1'], $row['temp_t2'], $row['temp_t3'],
        $row['dryer_c'], $row['arus_a'],
        $row['press_in'], $row['press_out'],
        $row['temp_in'], $row['temp_out'],
        $pelaksana, $keterangan, $userName
    ]);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data check sheet kompressor sullair.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $saved++;
}

if ($keterangan !== '') {
    $syncKetSql = "UPDATE dbo.temp_compressor_v2 SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ? AND Weaving = ? AND Compressor_No = ?";
    $syncKetStmt = sqlsrv_query($conn, $syncKetSql, [$keterangan, $tanggal, $weaving, $compressorNo]);
    if ($syncKetStmt) sqlsrv_free_stmt($syncKetStmt);
}

sqlsrv_commit($conn);
$message = $saved . ' baris data berhasil disimpan.';
if ($updated > 0) $message .= ' ' . $updated . ' baris existing berhasil ' . ($editExisting ? 'diupdate.' : 'dilengkapi.');
if ($skipped > 0) $message .= ' ' . $skipped . ' baris dilewati karena sudah ada.';
json_response(['success' => true, 'message' => $message]);
