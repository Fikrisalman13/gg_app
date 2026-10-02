<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

function merge_petugas_names($existing, $new)
{
    $names = [];
    foreach (explode(',', (string)$existing) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    foreach (explode(',', (string)$new) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    return implode(', ', array_values($names));
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
weaving_require($conn, 'CanAdd', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);

$editExisting = isset($_POST['edit_existing']) && (string)$_POST['edit_existing'] === '1';
if ($editExisting) {
    weaving_require($conn, 'CanEdit', true);
}
if (!temp_compressor_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor belum tersedia. Jalankan create_table_temp_compressor.sql terlebih dahulu.']);

$tanggal = trim($_POST['tanggal'] ?? '');
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rawRows = $_POST['rows'] ?? [];
$allowedHours = temp_compressor_hours();

if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal wajib diisi.']);
if ($petugas === '') json_response(['success' => false, 'message' => 'Petugas wajib diisi.']);
if (!is_array($rawRows)) json_response(['success' => false, 'message' => 'Format data input tidak valid.']);

$rowsToSave = [];
$fieldColumns = [
    'c1_in'       => 'Compressor1_In_C',
    'c1_out'      => 'Compressor1_Out_C',
    'c2_in'       => 'Compressor2_In_C',
    'c2_out'      => 'Compressor2_Out_C',
    'amper'       => 'Amper',
    'pressure_p1' => 'PressureBar_P1',
    'pressure_p2' => 'PressureBar_P2',
    'temp_t1'     => 'Temperature_T1',
    'temp_t2'     => 'Temperature_T2',
    'temp_t3'     => 'Temperature_T3',
    'dryer_c'     => 'Dryer_C',
    'tekanan_in'  => 'TekananAir_In',
    'tekanan_out' => 'TekananAir_Out',
];
foreach ($rawRows as $row) {
    if (!is_array($row)) continue;
    $jam = trim((string)($row['jam'] ?? ''));
    if (!in_array($jam, $allowedHours, true)) continue;

    foreach ($fieldColumns as $field => $column) {
        if (!temp_compressor_is_valid_decimal_input($row[$field] ?? '')) {
            json_response(['success' => false, 'message' => 'Baris jam ' . temp_compressor_display_hour($jam) . ' berisi nilai yang tidak sesuai format angka.']);
        }
    }

    $c1In       = temp_compressor_normalize_decimal($row['c1_in'] ?? '');
    $c1Out      = temp_compressor_normalize_decimal($row['c1_out'] ?? '');
    $c2In       = temp_compressor_normalize_decimal($row['c2_in'] ?? '');
    $c2Out      = temp_compressor_normalize_decimal($row['c2_out'] ?? '');
    $amper      = temp_compressor_normalize_decimal($row['amper'] ?? '');
    $pressP1    = temp_compressor_normalize_decimal($row['pressure_p1'] ?? '');
    $pressP2    = temp_compressor_normalize_decimal($row['pressure_p2'] ?? '');
    $tempT1     = temp_compressor_normalize_decimal($row['temp_t1'] ?? '');
    $tempT2     = temp_compressor_normalize_decimal($row['temp_t2'] ?? '');
    $tempT3     = temp_compressor_normalize_decimal($row['temp_t3'] ?? '');
    $dryerC     = temp_compressor_normalize_decimal($row['dryer_c'] ?? '');
    $tekananIn  = temp_compressor_normalize_decimal($row['tekanan_in'] ?? '');
    $tekananOut = temp_compressor_normalize_decimal($row['tekanan_out'] ?? '');

    $hasValue = $c1In !== null || $c1Out !== null || $c2In !== null || $c2Out !== null
        || $amper !== null || $pressP1 !== null || $pressP2 !== null
        || $tempT1 !== null || $tempT2 !== null || $tempT3 !== null
        || $dryerC !== null || $tekananIn !== null || $tekananOut !== null;
    if (!$hasValue) continue;
    $rowsToSave[] = [
        'jam'         => $jam,
        'c1_in'       => $c1In,
        'c1_out'      => $c1Out,
        'c2_in'       => $c2In,
        'c2_out'      => $c2Out,
        'amper'       => $amper,
        'pressure_p1' => $pressP1,
        'pressure_p2' => $pressP2,
        'temp_t1'     => $tempT1,
        'temp_t2'     => $tempT2,
        'temp_t3'     => $tempT3,
        'dryer_c'     => $dryerC,
        'tekanan_in'  => $tekananIn,
        'tekanan_out' => $tekananOut,
    ];
}

if (count($rowsToSave) === 0) {
    if ($editExisting) {
        $ketVal = ($keterangan !== '') ? $keterangan : null;
        $updateKetSql = "UPDATE dbo.temp_compressor SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE CAST(Tanggal AS DATE) = ?";
        $updateKetStmt = sqlsrv_query($conn, $updateKetSql, [$ketVal, $_SESSION['UserName'], $tanggal]);
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
    $findSql = "SELECT TOP 1 Id, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
                       Amper, PressureBar_P1, PressureBar_P2,
                       Temperature_T1, Temperature_T2, Temperature_T3,
                       Dryer_C, TekananAir_In, TekananAir_Out, Petugas
                FROM dbo.temp_compressor
                WHERE CAST(Tanggal AS DATE) = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?";
    $findStmt = sqlsrv_query($conn, $findSql, [$tanggal, $row['jam']]);
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
                    if (!temp_compressor_values_equal($newVal, $existingValue)) {
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

        // Merge existing Petugas with current $petugas when row is updated/supplemented
        $existingRowPetugas = trim((string)($existing['Petugas'] ?? ''));
        $finalRowPetugas = ($existingRowPetugas !== '')
            ? temp_compressor_merge_petugas_names($existingRowPetugas, $petugas)
            : $petugas;
        $setParts[] = 'Petugas = ?';
        $updateParams[] = $finalRowPetugas;

        if ($keterangan !== '') {
            $setParts[] = 'Keterangan = ?';
            $updateParams[] = $keterangan;
        }
        $setParts[] = 'UpdateBy = ?';
        $updateParams[] = $userName;
        $setParts[] = 'UpdateAt = GETDATE()';
        $updateParams[] = (int)$existing['Id'];

        $updateSql = "UPDATE dbo.temp_compressor SET " . implode(', ', $setParts) . " WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        if ($updateStmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal melengkapi data Check Sheet Kompressor Sullair yang sudah ada.']);
        }
        if ($updateStmt) sqlsrv_free_stmt($updateStmt);
        $updated++;
        continue;
    }

    $sql = "INSERT INTO dbo.temp_compressor
            (Tanggal, Jam, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
             Amper, PressureBar_P1, PressureBar_P2,
             Temperature_T1, Temperature_T2, Temperature_T3,
             Dryer_C, TekananAir_In, TekananAir_Out,
             Petugas, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, [
        $tanggal, $row['jam'],
        $row['c1_in'], $row['c1_out'], $row['c2_in'], $row['c2_out'],
        $row['amper'], $row['pressure_p1'], $row['pressure_p2'],
        $row['temp_t1'], $row['temp_t2'], $row['temp_t3'],
        $row['dryer_c'], $row['tekanan_in'], $row['tekanan_out'],
        $petugas, $keterangan, $userName
    ]);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data Check Sheet Kompressor Sullair.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $saved++;
}

if ($keterangan !== '') {
    $syncKetSql = "UPDATE dbo.temp_compressor SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ?";
    $syncKetStmt = sqlsrv_query($conn, $syncKetSql, [$keterangan, $tanggal]);
    if ($syncKetStmt) sqlsrv_free_stmt($syncKetStmt);
}

sqlsrv_commit($conn);
$message = $saved . ' baris data berhasil disimpan.';
if ($updated > 0) $message .= ' ' . $updated . ' baris existing berhasil ' . ($editExisting ? 'diupdate.' : 'dilengkapi.');
if ($skipped > 0) $message .= ' ' . $skipped . ' baris dilewati karena sudah ada.';
json_response(['success' => true, 'message' => $message]);
