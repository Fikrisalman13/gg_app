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
if (!empty($permissions) && isset($permissions['CanDelete']) && $permissions['CanDelete'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menghapus catatan.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

$sql = "UPDATE dbo.air_analog_actom SET catatan=NULL, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$_SESSION['UserName'], $id]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus catatan.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Catatan dihapus.']);

