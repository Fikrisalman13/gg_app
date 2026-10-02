<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location: lpg_skid_tank.php'); exit; }
$menuId=230; $permissions=getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && $permissions['CanDelete'] != 1) { $_SESSION['error']='Anda tidak memiliki hak menghapus data.'; header('Location: lpg_skid_tank.php'); exit; }

$id=intval($_GET['id'] ?? 0);
if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: lpg_skid_tank.php'); exit; }

$stmt=sqlsrv_query($conn,"DELETE FROM dbo.lpg_skid_tank_harian WHERE id=?",[$id]);
if($stmt===false){ $_SESSION['error']='Gagal menghapus data.'; header('Location: lpg_skid_tank.php'); exit; }
if($stmt) sqlsrv_free_stmt($stmt);
$_SESSION['success']='Data berhasil dihapus.'; header('Location: lpg_skid_tank.php'); exit;