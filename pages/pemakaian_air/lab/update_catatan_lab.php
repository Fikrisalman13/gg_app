<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk mengubah catatan.']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$catatan = trim($_POST['catatan'] ?? '');
if ($id <= 0 || $catatan === '') {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit;
}

$sql = "UPDATE dbo.lab_air
        SET Catatan = ?, UpdateBy = ?, UpdateAt = GETDATE()
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$catatan, $_SESSION['UserName'], $id]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui catatan.']);
    exit;
}

$affected = sqlsrv_rows_affected($stmt);
if ($stmt) sqlsrv_free_stmt($stmt);
if ($affected === 0) {
    echo json_encode(['success' => false, 'message' => 'Catatan tidak ditemukan.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Catatan berhasil diperbarui.']);

