<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki hak melihat data.']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

$stmt = sqlsrv_query($conn, "SELECT x.*, CONVERT(VARCHAR(5), x.cut_off_jam, 108) AS cut_off_jam_fmt FROM dbo.air_steam_20tlonchuan_harian x WHERE x.id=?", [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);
if (!$row) { echo json_encode(['success'=>false,'message'=>'Data tidak ditemukan.']); exit; }

$tgl = $row['tanggal'];
if ($tgl instanceof DateTime) $tanggal = $tgl->format('Y-m-d');
else $tanggal = $tgl ? date('Y-m-d', strtotime((string)$tgl)) : '';

echo json_encode(['success'=>true,'data'=>[
    'id' => $row['id'],
    'tanggal' => $tanggal,
    'air_awal_ton' => $row['air_awal_ton'] ?? 0,
    'air_akhir_ton' => $row['air_akhir_ton'] ?? 0,
    'air_total_pemakaian_ton' => $row['air_total_pemakaian_ton'] ?? 0,
    'air_rata_rata_per_jam_ton' => $row['air_rata_rata_per_jam_ton'] ?? 0,
    'air_ket' => $row['air_ket'] ?? '',
    'steam_awal_ton' => $row['steam_awal_ton'] ?? 0,
    'steam_akhir_ton' => $row['steam_akhir_ton'] ?? 0,
    'steam_total_pemakaian_ton' => $row['steam_total_pemakaian_ton'] ?? 0,
    'steam_rata_rata_per_jam_ton' => $row['steam_rata_rata_per_jam_ton'] ?? 0,
    'steam_ket' => $row['steam_ket'] ?? '',
    'cut_off_jam' => $row['cut_off_jam_fmt'] ?? '09:00'
]]);
