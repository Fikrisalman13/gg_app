<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.']); exit; }
$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) { echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak menambah data.']); exit; }

function n($v){ $v=trim((string)$v); if($v==='') return 0; $v=preg_replace('/[^0-9.,-]/','',$v); if($v==='') return 0; if(strpos($v,'.')!==false){ $a=str_replace(',','',$v); if(is_numeric($a)) return (float)$a; } $f=str_replace('.','',$v); $f=str_replace(',','.',$f); return is_numeric($f)?(float)$f:0; }

$tanggal = trim($_POST['tanggal'] ?? '');
$itemsJson = $_POST['items'] ?? '';
if ($tanggal === '' || trim((string)$itemsJson) === '') {
    echo json_encode(['success'=>false,'message'=>'Tanggal dan item wajib diisi.']);
    exit;
}

$items = json_decode($itemsJson, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['success'=>false,'message'=>'Format item tidak valid.']);
    exit;
}

$affected = 0;
foreach ($items as $it) {
    $tankKode = trim((string)($it['tank_kode'] ?? ''));
    if ($tankKode === '') continue;

    $pemakaian = n($it['pemakaian_kg'] ?? 0);
    $harga = n($it['harga_rp'] ?? 0);
    $ket = trim((string)($it['ket'] ?? ''));

    $cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.lpg_skid_tank_harian WHERE tanggal=? AND tank_kode=?", [$tanggal, $tankKode]);
    $row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
    if ($cek) sqlsrv_free_stmt($cek);

    if ($row) {
        $sql = "UPDATE dbo.lpg_skid_tank_harian SET pemakaian_kg=?, harga_rp=?, ket=?, updateby=?, updateat=GETDATE() WHERE id=?";
        $params = [$pemakaian, $harga, $ket, $_SESSION['UserName'], $row['id']];
    } else {
        $sql = "INSERT INTO dbo.lpg_skid_tank_harian (tanggal,tank_kode,pemakaian_kg,harga_rp,ket,creatby) VALUES (?,?,?,?,?,?)";
        $params = [$tanggal, $tankKode, $pemakaian, $harga, $ket, $_SESSION['UserName']];
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan data bulk.']);
        exit;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $affected++;
}

echo json_encode(['success'=>true,'message'=>'Berhasil simpan ' . $affected . ' baris.']);