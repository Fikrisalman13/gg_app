<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId=230; $permissions=getPermissions($conn,$_SESSION['GroupId'],$menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return null; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return null; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return $a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?$f:null; }
$tanggal = trim($_POST['tanggal'] ?? '');
$awal = n($_POST['steam_awal'] ?? '');
$akhir = n($_POST['steam_akhir'] ?? '');
$pemInput = n($_POST['steam_pemakaian'] ?? '');
if ($tanggal==='') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }
$pem = ($awal!==null && $akhir!==null) ? round((float)$akhir - (float)$awal, 2) : $pemInput;

$cek = sqlsrv_query($conn, "SELECT TOP 1 Id FROM dbo.washing2_steam_meter WHERE Tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);
if ($row) {
  $sql = "UPDATE dbo.washing2_steam_meter SET SteamAwal=?, SteamAkhir=?, SteamPemakaian=?, UpdateBy=?, UpdateAt=GETDATE() WHERE Tanggal=?";
  $params = [$awal,$akhir,$pem,$_SESSION['UserName'],$tanggal];
} else {
  $sql = "INSERT INTO dbo.washing2_steam_meter (Tanggal, SteamAwal, SteamAkhir, SteamPemakaian, CreatBy) VALUES (?,?,?,?,?)";
  $params = [$tanggal,$awal,$akhir,$pem,$_SESSION['UserName']];
}
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt===false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan STEAM.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>'Data STEAM berhasil disimpan.']);
