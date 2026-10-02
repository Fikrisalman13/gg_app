<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230;
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

$cek = sqlsrv_query($conn, "SELECT TOP 1 Id FROM dbo.washing2_water_meter WHERE Tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
  $sql = "UPDATE dbo.washing2_water_meter SET Catatan=?, UpdateBy=?, UpdateAt=GETDATE() WHERE Tanggal=?";
  $params = [$catatan, $_SESSION['UserName'], $tanggal];
} else {
  $sql = "INSERT INTO dbo.washing2_water_meter (Tanggal, Catatan, CreatBy) VALUES (?, ?, ?)";
  $params = [$tanggal, $catatan, $_SESSION['UserName']];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);
