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
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak mengubah catatan.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');
if ($id <= 0 || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit;
}

$sql = "UPDATE dbo.air_analog_actom SET catatan=?, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$catatan, $_SESSION['UserName'], $id]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui catatan.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Catatan diperbarui.']);

