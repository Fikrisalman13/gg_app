<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId jetdyeing di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah catatan.']);
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
if ($tanggal === '' || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

$creatBy = $_SESSION['UserName'];
// Prevent truncation if DB column length is limited (umumnya NVARCHAR(20))
if ($creatBy !== null) {
    $creatBy = substr((string)$creatBy, 0, 20);
}

$sql = "INSERT INTO dbo.catatan_jetdyeing (Tanggal, Catatan, Creatby) VALUES (?, ?, ?)";
$params = [$tanggal, $catatan, $creatBy];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $err = sqlsrv_errors();
    $msg = 'Gagal menyimpan catatan.';
    if (!empty($err)) {
        $msg .= ' SQL: ' . $err[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);



