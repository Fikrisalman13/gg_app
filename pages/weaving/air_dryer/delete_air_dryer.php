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

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanDelete', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = $_GET['id'] ?? '';
    if ($id === '' || !ctype_digit((string)$id) || !air_dryer_table_exists($conn)) {
        $_SESSION['error'] = 'ID tidak valid.';
        header('Location: air_dryer.php');
        exit;
    }
    $sheet = air_dryer_resolve_sheet($conn, (int)$id);
    if (!$sheet) {
        $_SESSION['error'] = 'Data tidak ditemukan.';
        header('Location: air_dryer.php');
        exit;
    }
    $tanggal = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
    $sql = "DELETE FROM dbo.air_dryer WHERE CAST(Tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    if ($stmt) sqlsrv_free_stmt($stmt);
    $_SESSION['success'] = 'Data Air Dryer tanggal ' . air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y') . ' berhasil dihapus.';
    header('Location: air_dryer.php');
    exit;
}

if (!air_dryer_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.air_dryer belum tersedia.']);
}

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    json_response(['success' => false, 'message' => 'ID tidak valid.']);
}

$sheet = air_dryer_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$sql = "DELETE FROM dbo.air_dryer WHERE CAST(Tanggal AS DATE) = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal menghapus data Air Dryer.']);
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response([
    'success' => true,
    'message' => 'Data Air Dryer tanggal ' . air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y') . ' berhasil dihapus.'
]);
