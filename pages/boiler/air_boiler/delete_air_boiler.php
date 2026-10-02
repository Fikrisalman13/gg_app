<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && (int)$permissions['CanDelete'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menghapus data.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

$stmt = sqlsrv_query($conn, "DELETE FROM dbo.air_boiler_alstom_harian WHERE id=?", [$id]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus.']);
