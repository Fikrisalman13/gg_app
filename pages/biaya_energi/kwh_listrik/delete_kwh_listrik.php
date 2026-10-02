<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: kwh_listrik.php');
    exit;
}

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && (int)$permissions['CanDelete'] !== 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak menghapus data.';
    header('Location: kwh_listrik.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: kwh_listrik.php');
    exit;
}

if (!kwhl_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    $_SESSION['error'] = 'Tabel kwh_listrik belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}

$stmt = sqlsrv_query($conn, "DELETE FROM {$tableName} WHERE id=?", [$id]);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal menghapus data.';
    header('Location: kwh_listrik.php');
    exit;
}
sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil dihapus.';
header('Location: kwh_listrik.php');
exit;
