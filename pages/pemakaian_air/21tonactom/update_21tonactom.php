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
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak mengubah data.';
    header('Location: 21tonactom.php');
    exit;
}

function n($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,]/', '', $v);
    if ($v === '') return null;
    if (strpos($v, '.') !== false) {
        $a = str_replace(',', '', $v);
        if (is_numeric($a)) return (float) $a;
    }
    $f = str_replace('.', '', $v);
    $f = str_replace(',', '.', $f);
    return is_numeric($f) ? (float) $f : null;
}

$id = intval($_POST['id'] ?? 0);
$param = trim($_POST['param'] ?? '');
$tanggal = trim($_POST['tanggal'] ?? '');
$awal = n($_POST['awal'] ?? '');
$ahir = n($_POST['ahir'] ?? '');

$map = [
    'air_boiler' => 'air_boiler_actom',
    'steam_boiler' => 'steam_boiler_actom',
    'air_analog' => 'air_analog_actom',
    'analog_steam' => 'analog_steam_actom',
];

if ($id <= 0 || $tanggal === '' || $awal === null || $ahir === null || !isset($map[$param])) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: 21tonactom.php');
    exit;
}

$table = $map[$param];
$total = round($ahir - $awal, 2);
$rata = round($total / 24, 2);

$sql = "UPDATE dbo.$table
        SET tanggal=?, awal=?, ahir=?, total_pemakaian=?, pemakaianrata2perjam=?, updateby=?, updateat=GETDATE()
        WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $awal, $ahir, $total, $rata, $_SESSION['UserName'], $id]);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal memperbarui data.';
    header('Location: edit_21tonactom.php?id=' . urlencode((string)$id) . '&param=' . urlencode($param));
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil diperbarui.';
header('Location: 21tonactom.php');
exit;
