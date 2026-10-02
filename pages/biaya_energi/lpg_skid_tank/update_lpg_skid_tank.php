<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location: lpg_skid_tank.php'); exit; }
$menuId=230; $permissions=getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) { $_SESSION['error']='Anda tidak memiliki hak mengubah data.'; header('Location: lpg_skid_tank.php'); exit; }
function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }

$id=intval($_POST['id'] ?? 0);
$tanggal=trim($_POST['tanggal'] ?? '');
$tankKode=trim($_POST['tank_kode'] ?? '');
if($id<=0||$tanggal===''||$tankKode===''){ $_SESSION['error']='Data tidak valid.'; header('Location: lpg_skid_tank.php'); exit; }

$params=[
  $tanggal, $tankKode, n($_POST['pemakaian_kg'] ?? '0'), n($_POST['harga_rp'] ?? '0'), trim($_POST['ket'] ?? ''), trim($_POST['note'] ?? ''), $_SESSION['UserName'], $id
];
$sql="UPDATE dbo.lpg_skid_tank_harian SET tanggal=?, tank_kode=?, pemakaian_kg=?, harga_rp=?, ket=?, note=?, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,$params);
if($stmt===false){ $_SESSION['error']='Gagal memperbarui data.'; header('Location: edit_lpg_skid_tank.php?id=' . urlencode((string)$id)); exit; }
if($stmt) sqlsrv_free_stmt($stmt);
$_SESSION['success']='Data berhasil diperbarui.'; header('Location: lpg_skid_tank.php'); exit;