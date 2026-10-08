<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$draw = $_POST['draw'] ?? 1;
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';
$orderColIdx = (int)($_POST['order'][0]['column'] ?? 0);
$orderDir = strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$columns = ['id','kode_mesin','nama_mesin','infra_red','status'];
$orderBy = $columns[$orderColIdx] ?? 'kode_mesin';

$where = " WHERE 1=1";
$params = [];
if ($search !== '') {
  $where .= " AND (kode_mesin LIKE ? OR nama_mesin LIKE ?)";
  $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%';
}

$totalSql = "SELECT COUNT(*) AS c FROM dbo.master_mesin_lab";
$totalRes = sqlsrv_query($conn, $totalSql);
$totalRow = sqlsrv_fetch_array($totalRes, SQLSRV_FETCH_ASSOC);
$recordsTotal = (int)($totalRow['c'] ?? 0);

$filteredSql = "SELECT COUNT(*) AS c FROM dbo.master_mesin_lab $where";
$filteredStmt = sqlsrv_query($conn, $filteredSql, $params);
$filteredRow = $filteredStmt ? sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC) : null;
$recordsFiltered = (int)($filteredRow['c'] ?? 0);

$sql = "SELECT id, kode_mesin, nama_mesin, status, infra_red, tekanan_padder, wpu, speed, fan1, fan2, temp_chamber_1, temp_chamber_2 FROM dbo.master_mesin_lab $where ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $data[] = [
    'id' => (int)$r['id'],
    'kode_mesin' => $r['kode_mesin'],
    'nama_mesin' => $r['nama_mesin'],
    'status' => $r['status'],
    'infra_red' => $r['infra_red'],
    'tekanan_padder' => $r['tekanan_padder'],
    'wpu' => $r['wpu'],
    'speed' => $r['speed'],
    'fan1' => $r['fan1'],
    'fan2' => $r['fan2'],
    'temp_chamber_1' => $r['temp_chamber_1'],
    'temp_chamber_2' => $r['temp_chamber_2'],
  ];
}
echo json_encode([
  'draw' => (int)$draw,
  'recordsTotal' => $recordsTotal,
  'recordsFiltered' => $recordsFiltered,
  'data' => $data,
]);
