<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }

$tanggal = trim($_POST['tanggal'] ?? '');
$tankKode = trim($_POST['tank_kode'] ?? '');
$pemakaian = n($_POST['pemakaian_kg'] ?? '0');
$harga = n($_POST['harga_rp'] ?? '0');
$ket = trim($_POST['ket'] ?? '');
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : null;

if ($tanggal === '' || $tankKode === '') { echo json_encode(['success'=>false,'message'=>'Tanggal dan tank wajib diisi.']); exit; }

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.lpg_skid_tank_harian WHERE tanggal=? AND tank_kode=?", [$tanggal, $tankKode]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.lpg_skid_tank_harian SET pemakaian_kg=?, harga_rp=?, ket=?, note=COALESCE(?, note), updateby=?, updateat=GETDATE() WHERE id=?";
    $params = [$pemakaian,$harga,$ket,$note,$_SESSION['UserName'],$row['id']];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.lpg_skid_tank_harian (tanggal,tank_kode,pemakaian_kg,harga_rp,ket,note,creatby) VALUES (?,?,?,?,?,?,?)";
    $params = [$tanggal,$tankKode,$pemakaian,$harga,$ket,$note,$_SESSION['UserName']];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);