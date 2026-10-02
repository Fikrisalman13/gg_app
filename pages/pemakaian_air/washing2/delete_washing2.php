<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location:/gg_app/login.php'); exit; }
$menuId=230; $permissions=getPermissions($conn,$_SESSION['GroupId'],$menuId);
if (!empty($permissions) && isset($permissions['CanDelete']) && $permissions['CanDelete'] != 1) { $_SESSION['error']='Anda tidak memiliki hak untuk menghapus data.'; header('Location:washing2.php'); exit; }

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal==='') { $_SESSION['error']='Tanggal tidak valid.'; header('Location:washing2.php'); exit; }

sqlsrv_query($conn, "DELETE FROM dbo.washing2_watt_meter WHERE Tanggal=?", [$tanggal]);
sqlsrv_query($conn, "DELETE FROM dbo.washing2_steam_meter WHERE Tanggal=?", [$tanggal]);
sqlsrv_query($conn, "DELETE FROM dbo.washing2_water_meter WHERE Tanggal=?", [$tanggal]);

$_SESSION['success']='Data berhasil dihapus.';
header('Location:washing2.php');
exit;
