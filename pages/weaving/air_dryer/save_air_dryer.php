<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

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
if (!air_dryer_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.air_dryer belum tersedia. Jalankan create_table_air_dryer.sql terlebih dahulu.']);

$tanggal = trim($_POST['tanggal'] ?? '');
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rawRows = $_POST['rows'] ?? [];
$allowedHours = air_dryer_hours();

if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal wajib diisi.']);
if ($petugas === '') json_response(['success' => false, 'message' => 'Petugas wajib diisi.']);
if (!is_array($rawRows)) json_response(['success' => false, 'message' => 'Format data input tidak valid.']);

$fieldColumns = [
    'ad1_temp_in' => 'AirDryer1_Temp_In_C',
    'ad1_temp_out' => 'AirDryer1_Temp_Out_C',
    'ad1_press_in' => 'AirDryer1_Tekanan_In_Bar',
    'ad1_press_out' => 'AirDryer1_Tekanan_Out_Bar',
    'ad2_temp_in' => 'AirDryer2_Temp_In_C',
    'ad2_temp_out' => 'AirDryer2_Temp_Out_C',
    'ad2_press_in' => 'AirDryer2_Tekanan_In_Bar',
    'ad2_press_out' => 'AirDryer2_Tekanan_Out_Bar',
];
$fields = array_keys($fieldColumns);
$rowsToSave = [];
foreach ($rawRows as $row) {
    if (!is_array($row)) continue;
    $jam = trim((string)($row['jam'] ?? ''));
    if (!in_array($jam, $allowedHours, true)) continue;

    $normalized = ['jam' => $jam];
    $hasValue = false;
    foreach ($fields as $field) {
        $rawValue = $row[$field] ?? '';
        if (!air_dryer_is_valid_decimal_input($rawValue)) {
            json_response(['success' => false, 'message' => 'Baris jam ' . $jam . ' berisi nilai Air Dryer yang tidak sesuai format angka.']);
        }
        $value = air_dryer_normalize_decimal($rawValue);
        $normalized[$field] = $value;
        if ($value !== null) $hasValue = true;
    }
    if (!$hasValue) continue;
    $rowsToSave[] = $normalized;
}

if (count($rowsToSave) === 0) {
    if ($editExisting) {
        $ketVal = ($keterangan !== '') ? $keterangan : null;
        $updateKetSql = "UPDATE dbo.air_dryer SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE CAST(Tanggal AS DATE) = ?";
        $updateKetStmt = sqlsrv_query($conn, $updateKetSql, [$ketVal, $_SESSION['UserName'], $tanggal]);
        if ($updateKetStmt === false) {
            json_response(['success' => false, 'message' => 'Gagal memperbarui keterangan.']);
        }
        if ($updateKetStmt) sqlsrv_free_stmt($updateKetStmt);
        json_response(['success' => true, 'message' => 'Keterangan berhasil diperbarui.']);
    }
    json_response(['success' => false, 'message' => 'Isi minimal satu baris data pencatatan.']);
}
if (!sqlsrv_begin_transaction($conn)) json_response(['success' => false, 'message' => 'Gagal memulai transaksi simpan data.']);

$saved = 0;
$updated = 0;
$skipped = 0;
$userName = $_SESSION['UserName'];
foreach ($rowsToSave as $row) {
    $findSql = "SELECT TOP 1 Id,
                    AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                    AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                    AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                    AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar,
                    Petugas
                FROM dbo.air_dryer
                WHERE CAST(Tanggal AS DATE) = ?
                  AND CONVERT(VARCHAR(5), Jam_Pengecekan, 108) = ?";
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
                    if (!air_dryer_values_equal($newVal, $existingValue)) {
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
            ? air_dryer_merge_petugas_names($existingRowPetugas, $petugas)
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

        $updateSql = "UPDATE dbo.air_dryer SET " . implode(', ', $setParts) . " WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        if ($updateStmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal melengkapi data Air Dryer yang sudah ada.']);
        }
        if ($updateStmt) sqlsrv_free_stmt($updateStmt);
        $updated++;
        continue;
    }

    $sql = "INSERT INTO dbo.air_dryer
            (Tanggal, Jam_Pengecekan, AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
             AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
             AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
             AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar,
             Petugas, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $params = [
        $tanggal, $row['jam'], $row['ad1_temp_in'], $row['ad1_temp_out'],
        $row['ad1_press_in'], $row['ad1_press_out'], $row['ad2_temp_in'], $row['ad2_temp_out'],
        $row['ad2_press_in'], $row['ad2_press_out'], $petugas, $keterangan, $userName
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data Air Dryer.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $saved++;
}

if ($keterangan !== '') {
    $syncKetSql = "UPDATE dbo.air_dryer SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ?";
    $syncKetStmt = sqlsrv_query($conn, $syncKetSql, [$keterangan, $tanggal]);
    if ($syncKetStmt) sqlsrv_free_stmt($syncKetStmt);
}

sqlsrv_commit($conn);
$message = $saved . ' baris data berhasil disimpan.';
if ($updated > 0) $message .= ' ' . $updated . ' baris existing berhasil ' . ($editExisting ? 'diupdate.' : 'dilengkapi.');
if ($skipped > 0) $message .= ' ' . $skipped . ' baris dilewati karena sudah ada.';
json_response(['success' => true, 'message' => $message]);
