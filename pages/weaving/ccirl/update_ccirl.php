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
weaving_require($conn, 'CanEdit', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
if (!ccirl_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.ccirl belum tersedia.']);

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) json_response(['success' => false, 'message' => 'ID tidak valid.']);
$sheet = ccirl_get_sheet($conn, (int)$id);
if (!$sheet) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);

$tanggal = ccirl_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$compressorNo = $sheet['Compressor_No'] ?? '';
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rawRows = $_POST['rows'] ?? [];
$itemMap = ccirl_item_map();
$allowedHours = ccirl_hours();

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

if (count($rowsToSave) === 0) json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
if (!sqlsrv_begin_transaction($conn)) json_response(['success' => false, 'message' => 'Gagal memulai transaksi update data.']);

$existingCells = ccirl_get_cells($conn, $tanggal, $compressorNo);

$deleteStmt = sqlsrv_query($conn, "DELETE FROM dbo.ccirl WHERE CAST(Tanggal AS DATE) = ? AND Compressor_No = ?", [$tanggal, $compressorNo]);
if ($deleteStmt === false) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menghapus data lama.']);
}
if ($deleteStmt) sqlsrv_free_stmt($deleteStmt);

$createdBy = $sheet['CreatBy'] ?? $_SESSION['UserName'];
$createdAt = $sheet['CreatAt'] ?? null;
$userName = $_SESSION['UserName'];
foreach ($rowsToSave as $row) {
    $key = $row['item_key'] . '|' . $row['jam'];
    $existingCell = $existingCells[$key] ?? null;

    $cellPetugas = ($existingCell && !empty($existingCell['petugas']))
        ? $existingCell['petugas']
        : ($petugas !== '' ? $petugas : null);

    $cellCreatedBy = ($existingCell && !empty($existingCell['creat_by']))
        ? $existingCell['creat_by']
        : $createdBy;

    $cellCreatedAt = ($existingCell && !empty($existingCell['creat_at']))
        ? $existingCell['creat_at']
        : $createdAt;

    $sql = "INSERT INTO dbo.ccirl
            (Tanggal, Compressor_No, Jam, Item_Key, Item_Check, Nilai, Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, [
        $tanggal, $compressorNo, $row['jam'], $row['item_key'], $row['item_check'],
        $row['nilai'], $cellPetugas, $keterangan !== '' ? $keterangan : null, $cellCreatedBy, $cellCreatedAt, $userName
    ]);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan perubahan CCIRL.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
}

sqlsrv_commit($conn);
json_response(['success' => true, 'message' => 'Data CCIRL berhasil diperbarui.']);

