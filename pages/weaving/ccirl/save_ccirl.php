<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

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
if (!ccirl_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.ccirl belum tersedia. Jalankan create_table_ccirl.sql terlebih dahulu.']);

$tanggal = trim($_POST['tanggal'] ?? '');
$compressorNo = trim($_POST['compressor_no'] ?? '');
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rawRows = $_POST['rows'] ?? [];
$itemMap = ccirl_item_map();
$allowedHours = ccirl_hours();

if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal wajib diisi.']);
if (!in_array($compressorNo, ccirl_no_options(), true)) json_response(['success' => false, 'message' => 'Compressor No tidak valid.']);
if ($petugas === '') json_response(['success' => false, 'message' => 'Petugas wajib diisi.']);
if (!is_array($rawRows)) json_response(['success' => false, 'message' => 'Format data input tidak valid.']);

$rowsToSave = [];
foreach ($rawRows as $itemKey => $hourValues) {
    if (!isset($itemMap[$itemKey]) || !is_array($hourValues)) continue;
    foreach ($hourValues as $hour => $value) {
        $hour = trim((string)$hour);
        $value = trim((string)$value);
        if ($value === '' || !in_array($hour, $allowedHours, true)) continue;
        $rowsToSave[] = [
            'item_key' => $itemKey,
            'item_check' => strip_tags($itemMap[$itemKey]['label']),
            'jam' => $hour,
            'nilai' => $value,
        ];
    }
}

if (count($rowsToSave) === 0) {
    if ($editExisting) {
        $ketVal = ($keterangan !== '') ? $keterangan : null;
        $updateKetSql = "UPDATE dbo.ccirl SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE CAST(Tanggal AS DATE) = ? AND Compressor_No = ?";
        $updateKetStmt = sqlsrv_query($conn, $updateKetSql, [$ketVal, $_SESSION['UserName'], $tanggal, $compressorNo]);
        if ($updateKetStmt === false) {
            json_response(['success' => false, 'message' => 'Gagal memperbarui keterangan.']);
        }
        if ($updateKetStmt) sqlsrv_free_stmt($updateKetStmt);
        json_response(['success' => true, 'message' => 'Keterangan berhasil diperbarui.']);
    }
    json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
}
if (!sqlsrv_begin_transaction($conn)) json_response(['success' => false, 'message' => 'Gagal memulai transaksi simpan data.']);

$saved = 0;
$updated = 0;
$skipped = 0;
$userName = $_SESSION['UserName'];

foreach ($rowsToSave as $row) {
    $findSql = "SELECT TOP 1 Id, Nilai, Petugas
                FROM dbo.ccirl
                WHERE CAST(Tanggal AS DATE) = ?
                  AND Compressor_No = ?
                  AND Item_Key = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?";
    $findStmt = sqlsrv_query($conn, $findSql, [$tanggal, $compressorNo, $row['item_key'], $row['jam']]);
    if ($findStmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal mengecek data existing.']);
    }
    $existing = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
    if ($findStmt) sqlsrv_free_stmt($findStmt);

    if ($existing && !empty($existing['Id'])) {
        if ($editExisting) {
            if (ccirl_values_equal($row['nilai'], $existing['Nilai'] ?? '')) {
                $skipped++;
                continue;
            }
        } else {
            if (($existing['Nilai'] ?? '') !== '') {
                $skipped++;
                continue;
            }
        }

        // Preserve existing cell Petugas if already set; only use modal's $petugas if existing cell had no Petugas
        $existingRowPetugas = trim((string)($existing['Petugas'] ?? ''));
        $finalRowPetugas = ($existingRowPetugas !== '') ? $existingRowPetugas : $petugas;

        $updateSql = "UPDATE dbo.ccirl
                      SET Nilai = ?, Petugas = ?, Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE()
                      WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, [$row['nilai'], $finalRowPetugas, $keterangan, $userName, (int)$existing['Id']]);
        if ($updateStmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal melengkapi data CCIRL yang sudah ada.']);
        }
        if ($updateStmt) sqlsrv_free_stmt($updateStmt);
        $updated++;
        continue;
    }

    $sql = "INSERT INTO dbo.ccirl
            (Tanggal, Compressor_No, Jam, Item_Key, Item_Check, Nilai, Petugas, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, [
        $tanggal, $compressorNo, $row['jam'], $row['item_key'], $row['item_check'],
        $row['nilai'], $petugas, $keterangan, $userName
    ]);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data CCIRL.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $saved++;
}

if ($keterangan !== '') {
    $syncKetSql = "UPDATE dbo.ccirl SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ? AND Compressor_No = ?";
    $syncKetStmt = sqlsrv_query($conn, $syncKetSql, [$keterangan, $tanggal, $compressorNo]);
    if ($syncKetStmt) sqlsrv_free_stmt($syncKetStmt);
}

sqlsrv_commit($conn);
$message = $saved . ' data berhasil disimpan.';
if ($updated > 0) $message .= ' ' . $updated . ' data existing berhasil ' . ($editExisting ? 'diupdate.' : 'dilengkapi.');
if ($skipped > 0) $message .= ' ' . $skipped . ' data dilewati karena sudah ada.';
json_response(['success' => true, 'message' => $message]);

