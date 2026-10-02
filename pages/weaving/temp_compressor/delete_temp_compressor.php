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

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanDelete', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = $_GET['id'] ?? '';
    $tanggalParam = trim($_GET['tanggal'] ?? '');
    if (!temp_compressor_table_exists($conn)) {
        $_SESSION['error'] = 'Tabel belum tersedia.';
        header('Location: temp_compressor.php');
        exit;
    }
    $sheet = null;
    if ($id !== '' && ctype_digit((string)$id)) {
        $sheet = temp_compressor_resolve_sheet($conn, (int)$id);
    }
    if (!$sheet && $tanggalParam !== '') {
        $sheet = temp_compressor_resolve_sheet_by_date($conn, $tanggalParam);
    }
    if (!$sheet) {
        $_SESSION['error'] = 'Data tidak ditemukan.';
        header('Location: temp_compressor.php');
        exit;
    }
    $tanggal = temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
    $sql = "DELETE FROM dbo.temp_compressor WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt) sqlsrv_free_stmt($stmt);
    $_SESSION['success'] = 'Data Check Sheet Kompressor Sullair tanggal ' . temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y') . ' berhasil dihapus.';
    header('Location: temp_compressor.php');
    exit;
}

if (!temp_compressor_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.temp_compressor belum tersedia.']);
}

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '') {
    $sheet = temp_compressor_resolve_sheet_by_date($conn, $tanggalParam);
}
if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$sql = "DELETE FROM dbo.temp_compressor WHERE CAST(Tanggal AS DATE) = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal menghapus data Check Sheet Kompressor Sullair.']);
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response([
    'success' => true,
    'message' => 'Data Check Sheet Kompressor Sullair tanggal ' . temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y') . ' berhasil dihapus.'
]);
