<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location: listrik_gardu_induk.php'); exit; }
$menuId=236; $permissions=getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) { $_SESSION['error']='Anda tidak memiliki hak mengubah data.'; header('Location: listrik_gardu_induk.php'); exit; }
function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }
$id=intval($_POST['id'] ?? 0); $tanggal=trim($_POST['tanggal'] ?? '');
if($id<=0||$tanggal===''){ $_SESSION['error']='Data tidak valid.'; header('Location: listrik_gardu_induk.php'); exit; }
$params=[
  $tanggal, n($_POST['lvbp_kwh'] ?? '0'), n($_POST['vbp_kwh'] ?? '0'), n($_POST['kvarh'] ?? '0'), n($_POST['cos_phi'] ?? '0'),
  n($_POST['faktor_kali'] ?? '6000'), n($_POST['rp_per_kwh'] ?? '1155.417'), n($_POST['pf_standar'] ?? '0.95'), n($_POST['kapasitas_kva'] ?? '5190'),
  trim($_POST['ket'] ?? ''), trim($_POST['note'] ?? ''), $_SESSION['UserName'], $id
];
$sql="UPDATE dbo.listrik_gardu_induk_harian SET tanggal=?, lvbp_kwh=?, vbp_kwh=?, kvarh=?, cos_phi=?, faktor_kali=?, rp_per_kwh=?, pf_standar=?, kapasitas_kva=?, ket=?, note=?, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,$params);
if($stmt===false){ $_SESSION['error']='Gagal memperbarui data.'; header('Location: edit_listrik_gardu_induk.php?id=' . urlencode((string)$id)); exit; }
if($stmt) sqlsrv_free_stmt($stmt);
$_SESSION['success']='Data berhasil diperbarui.'; header('Location: listrik_gardu_induk.php'); exit;

