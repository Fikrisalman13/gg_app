<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$q = $_GET['q'] ?? $_POST['q'] ?? '';
$onlyActive = $_GET['only_active'] ?? $_POST['only_active'] ?? '1';
$params = [];
$sql = "SELECT id, kode_mesin, nama_mesin, status, infra_red, tekanan_padder, wpu, speed, fan1, fan2, temp_chamber_1, temp_chamber_2, temp_chamber_1_time, temp_chamber_2_time FROM dbo.master_mesin_lab";
if ($onlyActive === '1') $sql .= " WHERE status='Active'";
if ($q !== '') {
  $sql .= $onlyActive === '1' ? " AND (kode_mesin LIKE ? OR nama_mesin LIKE ?)" : " WHERE kode_mesin LIKE ? OR nama_mesin LIKE ?";
  $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%';
}
$sql .= " ORDER BY kode_mesin ASC";
$stmt = sqlsrv_query($conn, $sql, $params);
$results = [];
function fmtNum($v) { return $v !== null && $v !== '' ? (float)$v : null; }
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $results[] = [
    'id' => $r['kode_mesin'],
    'text' => $r['kode_mesin'].' - '.$r['nama_mesin'],
    'machine_data' => [
      'id' => (int)$r['id'],
      'kode_mesin' => $r['kode_mesin'],
      'nama_mesin' => $r['nama_mesin'],
      'status' => $r['status'],
      'infra_red' => fmtNum($r['infra_red']),
      'tekanan_padder' => fmtNum($r['tekanan_padder']),
      'wpu' => fmtNum($r['wpu']),
      'speed' => fmtNum($r['speed']),
      'fan1' => fmtNum($r['fan1']),
      'fan2' => fmtNum($r['fan2']),
      'temp_chamber_1' => fmtNum($r['temp_chamber_1']),
      'temp_chamber_2' => fmtNum($r['temp_chamber_2']),
      'temp_chamber_1_time' => fmtNum($r['temp_chamber_1_time']),
      'temp_chamber_2_time' => fmtNum($r['temp_chamber_2_time']),
    ]
  ];
}
echo json_encode(['results'=>$results], JSON_NUMERIC_CHECK);
