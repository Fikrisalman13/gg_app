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
weaving_require($conn, 'CanDelete', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
if (!ccirl_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.ccirl belum tersedia.']);

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) json_response(['success' => false, 'message' => 'ID tidak valid.']);
$sheet = ccirl_get_sheet($conn, (int)$id);
if (!$sheet) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);

$tanggal = ccirl_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$stmt = sqlsrv_query($conn, "DELETE FROM dbo.ccirl WHERE CAST(Tanggal AS DATE) = ? AND Compressor_No = ?", [$tanggal, $sheet['Compressor_No']]);
if ($stmt === false) json_response(['success' => false, 'message' => 'Gagal menghapus data CCIRL.']);
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['success' => true, 'message' => 'Data CCIRL berhasil dihapus.']);
