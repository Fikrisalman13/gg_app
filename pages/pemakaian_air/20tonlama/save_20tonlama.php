<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){
    $v=trim((string)$v);
    if($v==='') return 0;
    $v=preg_replace('/[^0-9.,]/','',$v);
    if($v==='') return 0;
    if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; }
    $f=str_replace('.','',$v); $f=str_replace(',','.',$f);
    return is_numeric($f)?(float)$f:0;
}

$id = intval($_POST['id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$cutOff = trim($_POST['cut_off_jam'] ?? '09:00');
if ($tanggal === '') { echo json_encode(['success'=>false,'message'=>'Tanggal wajib diisi.']); exit; }
if (!preg_match('/^\d{2}:\d{2}$/', $cutOff)) $cutOff = '09:00';

$airAwal = n($_POST['air_awal_m3'] ?? '0');
$airAkhir = n($_POST['air_akhir_m3'] ?? '0');
$steamAwal = n($_POST['steam_awal_ton'] ?? '0');
$steamAkhir = n($_POST['steam_akhir_ton'] ?? '0');
$airKet = trim($_POST['air_ket'] ?? '');
$steamKet = trim($_POST['steam_ket'] ?? '');

$airTotal = $airAkhir - $airAwal;
$steamTotal = $steamAkhir - $steamAwal;
if ($airTotal < 0) { echo json_encode(['success'=>false,'message'=>'Air akhir tidak boleh lebih kecil dari air awal.']); exit; }
if ($steamTotal < 0) { echo json_encode(['success'=>false,'message'=>'Steam akhir tidak boleh lebih kecil dari steam awal.']); exit; }

$airRata = $airTotal / 24;
$steamRata = $steamTotal / 24;

if ($id > 0) {
    $sql = "UPDATE dbo.air_steam_20tonlama_harian
            SET air_awal_m3=?, air_akhir_m3=?, air_total_pemakaian_m3=?, air_rata_rata_per_jam_m3=?, air_ket=?,
                steam_awal_ton=?, steam_akhir_ton=?, steam_total_pemakaian_ton=?, steam_rata_rata_per_jam_ton=?, steam_ket=?,
                cut_off_jam=?, updateby=?, updateat=GETDATE()
            WHERE id=?";
    $params = [$airAwal,$airAkhir,$airTotal,$airRata,$airKet,$steamAwal,$steamAkhir,$steamTotal,$steamRata,$steamKet,$cutOff,$_SESSION['UserName'],$id];
    $msg = 'Data berhasil diperbarui.';
} else {
    $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_steam_20tonlama_harian WHERE tanggal=?", [$tanggal]);
    $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
    if ($cek) sqlsrv_free_stmt($cek);

    if ($row) {
        $sql = "UPDATE dbo.air_steam_20tonlama_harian
                SET air_awal_m3=?, air_akhir_m3=?, air_total_pemakaian_m3=?, air_rata_rata_per_jam_m3=?, air_ket=?,
                    steam_awal_ton=?, steam_akhir_ton=?, steam_total_pemakaian_ton=?, steam_rata_rata_per_jam_ton=?, steam_ket=?,
                    cut_off_jam=?, updateby=?, updateat=GETDATE()
                WHERE id=?";
        $params = [$airAwal,$airAkhir,$airTotal,$airRata,$airKet,$steamAwal,$steamAkhir,$steamTotal,$steamRata,$steamKet,$cutOff,$_SESSION['UserName'],$row['id']];
        $msg = 'Data berhasil diperbarui.';
    } else {
        $sql = "INSERT INTO dbo.air_steam_20tonlama_harian (
                    tanggal, air_awal_m3, air_akhir_m3, air_total_pemakaian_m3, air_rata_rata_per_jam_m3, air_ket,
                    steam_awal_ton, steam_akhir_ton, steam_total_pemakaian_ton, steam_rata_rata_per_jam_ton, steam_ket,
                    cut_off_jam, creatby
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = [$tanggal,$airAwal,$airAkhir,$airTotal,$airRata,$airKet,$steamAwal,$steamAkhir,$steamTotal,$steamRata,$steamKet,$cutOff,$_SESSION['UserName']];
        $msg = 'Data berhasil disimpan.';
    }
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data.']); exit; }
if ($stmt) sqlsrv_free_stmt($stmt);
echo json_encode(['success'=>true,'message'=>$msg]);
