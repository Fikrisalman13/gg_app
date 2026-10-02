<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ac_weaving_helper.php');

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
    $mesinParam = trim($_GET['mesin'] ?? '');
    if (!ac_weaving_table_exists($conn)) {
        $_SESSION['error'] = 'Tabel belum tersedia.';
        header('Location: ac_weaving.php');
        exit;
    }
    $sheet = null;
    if ($id !== '' && ctype_digit((string)$id)) {
        $sheet = ac_weaving_resolve_sheet($conn, (int)$id);
    }
    if (!$sheet && $tanggalParam !== '' && $mesinParam !== '') {
        $sheet = ac_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $mesinParam);
    }
    if (!$sheet) {
        $_SESSION['error'] = 'Data tidak ditemukan.';
        header('Location: ac_weaving.php');
        exit;
    }
    $tanggal = ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
    $sql = "DELETE FROM dbo.ac_weaving WHERE CAST(Tanggal AS DATE) = ? AND Mesin = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal, $sheet['Mesin']]);
    if ($stmt) sqlsrv_free_stmt($stmt);
    $_SESSION['success'] = 'Data AC Weaving berhasil dihapus.';
    header('Location: ac_weaving.php');
    exit;
}

if (!ac_weaving_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.ac_weaving belum tersedia.']);
}

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');
$mesinParam = trim($_POST['mesin'] ?? '');

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = ac_weaving_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $mesinParam !== '') {
    $sheet = ac_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $mesinParam);
}
if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$sql = "DELETE FROM dbo.ac_weaving
        WHERE CAST(Tanggal AS DATE) = ?
          AND Mesin = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $sheet['Mesin']]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal menghapus data AC Weaving.']);
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['success' => true, 'message' => 'Data AC Weaving berhasil dihapus.']);
