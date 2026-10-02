<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: kimia_weaving.php');
    exit;
}

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak mengubah data.';
    header('Location: kimia_weaving.php');
    exit;
}

function n($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9.,]/', '', $v);
    if ($v === '') return null;
    if (strpos($v, '.') !== false) {
        $a = str_replace(',', '', $v);
        if (is_numeric($a)) return (float)$a;
    }
    $f = str_replace('.', '', $v);
    $f = str_replace(',', '.', $f);
    return is_numeric($f) ? (float)$f : null;
}

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$pakai = n($_POST['pakai_kg'] ?? '');
$harga = n($_POST['harga_rp'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');

if ($id <= 0 || $tanggal === '' || $pakai === null || $harga === null) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: kimia_weaving.php');
    exit;
}

$sql = "UPDATE dbo.kimia_weaving_harian
        SET tanggal=?, pakai_kg=?, harga_rp=?, catatan=?, updateby=?, updateat=GETDATE()
        WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $pakai, $harga, $catatan, $_SESSION['UserName'], $id]);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal memperbarui data.';
    header('Location: edit_kimia_weaving.php?id=' . urlencode((string)$id));
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil diperbarui.';
header('Location: kimia_weaving.php');
exit;


