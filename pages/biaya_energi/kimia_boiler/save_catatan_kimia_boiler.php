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
$catatan = trim($_POST['catatan'] ?? '');
if ($tanggal === '' || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

$cek = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.kimia_boiler_harian WHERE CAST(tanggal AS DATE)=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);
if (!$row || intval($row['total'] ?? 0) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data kimia untuk tanggal ini belum ada. Input data dulu.']);
    exit;
}

$sql = "UPDATE dbo.kimia_boiler_harian
        SET note=?, updateby=?, updateat=GETDATE()
        WHERE CAST(tanggal AS DATE)=?";
$params = [$catatan, $_SESSION['UserName'], $tanggal];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);

