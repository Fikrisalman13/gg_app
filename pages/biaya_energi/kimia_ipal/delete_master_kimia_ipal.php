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
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menghapus master.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID master tidak valid.']);
    exit;
}

$cek = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.kimia_ipal_harian WHERE master_id=?", [$id]);
$used = 0;
if ($cek && ($r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC))) {
    $used = intval($r['total'] ?? 0);
}
if ($cek) sqlsrv_free_stmt($cek);

if ($used > 0) {
    echo json_encode(['success' => false, 'message' => 'Master tidak bisa dihapus karena sudah dipakai transaksi.']);
    exit;
}

$stmt = sqlsrv_query($conn, "DELETE FROM dbo.kimia_ipal_master WHERE id=?", [$id]);
if ($stmt === false) {
    $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $detail = (!empty($err) && isset($err[0]['message'])) ? (' ' . $err[0]['message']) : '';
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus master.' . $detail]);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Master berhasil dihapus.']);

