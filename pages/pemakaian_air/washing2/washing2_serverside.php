<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak untuk melihat data.']);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(d.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(d.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($search !== '') {
    $where .= " AND (
      CONVERT(VARCHAR(10), d.tanggal, 120) LIKE ?
      OR CONVERT(VARCHAR(50), w.WattAwal) LIKE ?
      OR CONVERT(VARCHAR(50), w.WattAkhir) LIKE ?
      OR CONVERT(VARCHAR(50), s.SteamAwal) LIKE ?
      OR CONVERT(VARCHAR(50), s.SteamAkhir) LIKE ?
      OR CONVERT(VARCHAR(50), m.WaterAwal) LIKE ?
      OR CONVERT(VARCHAR(50), m.WaterAkhir) LIKE ?
      OR CONVERT(VARCHAR(50), m.TotalPemakaian) LIKE ?
      OR m.Keterangan LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "
FROM (
  SELECT Tanggal AS tanggal FROM dbo.washing2_watt_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_steam_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_water_meter
) d
LEFT JOIN dbo.washing2_watt_meter w ON w.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_steam_meter s ON s.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_water_meter m ON m.Tanggal = d.tanggal
";

$countAllSql = "SELECT COUNT(*) AS total " . $baseFrom;
$countAllStmt = sqlsrv_query($conn, $countAllSql);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = $r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredSql = "SELECT COUNT(*) AS total " . $baseFrom . " " . $where;
$countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = $r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT d.tanggal, w.WattAwal, w.WattAkhir, s.SteamAwal, s.SteamAkhir, m.WaterAwal, m.WaterAkhir, m.TotalPemakaian, m.Keterangan
        " . $baseFrom . " " . $where . "
        ORDER BY CAST(d.tanggal AS DATE) DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$dataParams = $params; $dataParams[] = $start; $dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Gagal mengambil data.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'];
    if ($tgl instanceof DateTime) { $t = $tgl->format('Y-m-d'); $tf = $tgl->format('d-m-Y'); }
    else { $t = (string)$tgl; $tf = $t; }
    $data[] = [
      'tanggal' => $t,
      'tanggal_formatted' => $tf,
      'watt_awal' => $row['WattAwal'] ?? null,
      'watt_akhir' => $row['WattAkhir'] ?? null,
      'steam_awal' => $row['SteamAwal'] ?? null,
      'steam_akhir' => $row['SteamAkhir'] ?? null,
      'water_awal' => $row['WaterAwal'] ?? null,
      'water_akhir' => $row['WaterAkhir'] ?? null,
      'total_pemakaian' => $row['TotalPemakaian'] ?? null,
      'keterangan' => $row['Keterangan'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>(int)$totalRecords,'recordsFiltered'=>(int)$totalFiltered,'data'=>$data]);
