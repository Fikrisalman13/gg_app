<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: 21tonactom.php');
    exit;
}

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && $permissions['CanDelete'] != 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak menghapus data.';
    header('Location: 21tonactom.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$param = trim($_GET['param'] ?? '');
$map = [
    'air_boiler' => 'air_boiler_actom',
    'steam_boiler' => 'steam_boiler_actom',
    'air_analog' => 'air_analog_actom',
    'analog_steam' => 'analog_steam_actom',
];

if ($id <= 0 || !isset($map[$param])) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: 21tonactom.php');
    exit;
}

$table = $map[$param];
$stmt = sqlsrv_query($conn, "DELETE FROM dbo.$table WHERE id=?", [$id]);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal menghapus data.';
    header('Location: 21tonactom.php');
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil dihapus.';
header('Location: 21tonactom.php');
exit;
