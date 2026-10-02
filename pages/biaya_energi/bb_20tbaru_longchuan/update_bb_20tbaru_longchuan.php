<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location: bb_20tbaru_longchuan.php'); exit; }
$menuId=230; $permissions=getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) { $_SESSION['error']='Anda tidak memiliki hak mengubah data.'; header('Location: bb_20tbaru_longchuan.php'); exit; }
function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }
$id=intval($_POST['id'] ?? 0); $tanggal=trim($_POST['tanggal'] ?? '');
if($id<=0||$tanggal===''){ $_SESSION['error']='Data tidak valid.'; header('Location: bb_20tbaru_longchuan.php'); exit; }
$params=[
  $tanggal, n($_POST['pemakaian_kg'] ?? '0'), n($_POST['harga_rp_per_kg'] ?? '0'), n($_POST['total_biaya_boiler_rp'] ?? '0'),
  n($_POST['extractor_kg'] ?? '0'), n($_POST['cgrate_kg'] ?? '0'), n($_POST['fly_ash_kg'] ?? '0'), trim($_POST['ket'] ?? ''), trim($_POST['note'] ?? ''), $_SESSION['UserName'], $id
];
$sql="UPDATE dbo.bb_20tbaru_longchuan_harian SET tanggal=?, pemakaian_kg=?, harga_rp_per_kg=?, total_biaya_boiler_rp=?, extractor_kg=?, cgrate_kg=?, fly_ash_kg=?, ket=?, note=?, updateby=?, updateat=GETDATE() WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,$params);
if($stmt===false){ $_SESSION['error']='Gagal memperbarui data.'; header('Location: edit_bb_20tbaru_longchuan.php?id=' . urlencode((string)$id)); exit; }
if($stmt) sqlsrv_free_stmt($stmt);
$_SESSION['success']='Data berhasil diperbarui.'; header('Location: bb_20tbaru_longchuan.php'); exit;
