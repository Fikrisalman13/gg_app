<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }

$tanggal = trim($_POST['tanggal'] ?? '');
$lvbp = n($_POST['lvbp_kwh'] ?? '0');
$vbp = n($_POST['vbp_kwh'] ?? '0');
$kvarh = n($_POST['kvarh'] ?? '0');
$cos = n($_POST['cos_phi'] ?? '0');
$faktor = n($_POST['faktor_kali'] ?? '6000');
$tarif = n($_POST['rp_per_kwh'] ?? '1155.417');
$pf = n($_POST['pf_standar'] ?? '0.95');
$kapasitas = n($_POST['kapasitas_kva'] ?? '5190');
$ket = trim($_POST['ket'] ?? '');
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : null;

if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.listrik_gardu_induk_harian WHERE tanggal=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) sqlsrv_free_stmt($cek);

if ($row) {
    $sql = "UPDATE dbo.listrik_gardu_induk_harian SET lvbp_kwh=?, vbp_kwh=?, kvarh=?, cos_phi=?, faktor_kali=?, rp_per_kwh=?, pf_standar=?, kapasitas_kva=?, ket=?, note=COALESCE(?, note), updateby=?, updateat=GETDATE() WHERE id=?";
    $params = [$lvbp,$vbp,$kvarh,$cos,$faktor,$tarif,$pf,$kapasitas,$ket,$note,$_SESSION['UserName'],$row['id']];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.listrik_gardu_induk_harian (tanggal,lvbp_kwh,vbp_kwh,kvarh,cos_phi,faktor_kali,rp_per_kwh,pf_standar,kapasitas_kva,ket,note,creatby) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $params = [$tanggal,$lvbp,$vbp,$kvarh,$cos,$faktor,$tarif,$pf,$kapasitas,$ket,$note,$_SESSION['UserName']];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);

