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
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah catatan.']);
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$masterId = intval($_POST['master_id'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');
if ($tanggal === '' || $catatan === '' || $masterId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tanggal, parameter, dan catatan wajib diisi.']);
    exit;
}

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.kimia_ipal_harian WHERE tanggal=? AND master_id=?", [$tanggal, $masterId]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Data kimia untuk tanggal dan parameter ini belum ada. Input data dulu.']);
    exit;
}

$sql = "UPDATE dbo.kimia_ipal_harian
        SET catatan=?, updateby=?, updateat=GETDATE()
        WHERE id=?";
$params = [$catatan, $_SESSION['UserName'], $row['id']];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);
