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
$pemakaian = n($_POST['pemakaian_kg'] ?? '0');
$harga = n($_POST['harga_rp_per_kg'] ?? '0');
$totalBoiler = n($_POST['total_biaya_boiler_rp'] ?? '0');
$extractor = n($_POST['extractor_kg'] ?? '0');
$cgrate = n($_POST['cgrate_kg'] ?? '0');
$flyAsh = n($_POST['fly_ash_kg'] ?? '0');
$ket = trim($_POST['ket'] ?? '');
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : null;

if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.bb_20tbaru_longchuan_harian WHERE tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.bb_20tbaru_longchuan_harian SET pemakaian_kg=?, harga_rp_per_kg=?, total_biaya_boiler_rp=?, extractor_kg=?, cgrate_kg=?, fly_ash_kg=?, ket=?, note=COALESCE(?, note), updateby=?, updateat=GETDATE() WHERE id=?";
    $params = [$pemakaian,$harga,$totalBoiler,$extractor,$cgrate,$flyAsh,$ket,$note,$_SESSION['UserName'],$row['id']];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.bb_20tbaru_longchuan_harian (tanggal,pemakaian_kg,harga_rp_per_kg,total_biaya_boiler_rp,extractor_kg,cgrate_kg,fly_ash_kg,ket,note,creatby) VALUES (?,?,?,?,?,?,?,?,?,?)";
    $params = [$tanggal,$pemakaian,$harga,$totalBoiler,$extractor,$cgrate,$flyAsh,$ket,$note,$_SESSION['UserName']];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
