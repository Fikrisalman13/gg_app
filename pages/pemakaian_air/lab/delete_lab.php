<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && (int)$permissions['CanDelete'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: lab.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: lab.php');
    exit;
}

$sql = "DELETE FROM dbo.lab_air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [(int)$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal menghapus data.";
    header('Location: lab.php');
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$_SESSION['success'] = "Data berhasil dihapus.";
header('Location: lab.php');
exit;

