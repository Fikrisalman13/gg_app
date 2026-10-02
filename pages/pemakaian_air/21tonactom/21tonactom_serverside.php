<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Anda tidak memiliki hak untuk melihat data.'
    ]);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$parameter = trim($_POST['parameter'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') {
    $where .= " AND CAST(x.tanggal AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where .= " AND CAST(x.tanggal AS DATE) <= ?";
    $params[] = $endDate;
}
if ($parameter !== '') {
    $where .= " AND x.parameter = ?";
    $params[] = $parameter;
}
if ($search !== '') {
    $where .= " AND (
      CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ?
      OR x.parameter LIKE ?
      OR CONVERT(VARCHAR(50), x.awal) LIKE ?
      OR CONVERT(VARCHAR(50), x.akhir) LIKE ?
      OR CONVERT(VARCHAR(50), x.total_pemakaian) LIKE ?
      OR CONVERT(VARCHAR(50), x.pemakaian_rata) LIKE ?
      OR x.created_by LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp, $sp, $sp, $sp]);
}

$baseFrom = "
FROM (
  SELECT
    w.id AS id,
    w.Tanggal AS tanggal,
    'Air Boiler 21 Ton Actom' AS parameter,
    'air_boiler' AS param_key,
    w.awal AS awal,
    w.ahir AS akhir,
    w.total_pemakaian AS total_pemakaian,
    w.pemakaianrata2perjam AS pemakaian_rata,
    COALESCE(w.updateby, w.creatby, '') AS created_by
  FROM dbo.air_boiler_actom w

  UNION ALL

  SELECT
    s.id AS id,
    s.Tanggal AS tanggal,
    'Steam Boiler 21 Ton Actom' AS parameter,
    'steam_boiler' AS param_key,
    s.awal AS awal,
    s.ahir AS akhir,
    s.total_pemakaian AS total_pemakaian,
    s.pemakaianrata2perjam AS pemakaian_rata,
    COALESCE(s.updateby, s.creatby, '') AS created_by
  FROM dbo.steam_boiler_actom s

  UNION ALL

  SELECT
    m.id AS id,
    m.Tanggal AS tanggal,
    'Air Analog 21 Ton Actom' AS parameter,
    'air_analog' AS param_key,
    m.awal AS awal,
    m.ahir AS akhir,
    m.total_pemakaian AS total_pemakaian,
    m.pemakaianrata2perjam AS pemakaian_rata,
    COALESCE(m.updateby, m.creatby, '') AS created_by
  FROM dbo.air_analog_actom m

  UNION ALL

  SELECT
    a.id AS id,
    a.Tanggal AS tanggal,
    'Analog Steam 21 Ton Actom' AS parameter,
    'analog_steam' AS param_key,
    a.awal AS awal,
    a.ahir AS akhir,
    a.total_pemakaian AS total_pemakaian,
    a.pemakaianrata2perjam AS pemakaian_rata,
    COALESCE(a.updateby, a.creatby, '') AS created_by
  FROM dbo.analog_steam_actom a
) x
";

$countAllSql = "SELECT COUNT(*) AS total " . $baseFrom;
$countAllStmt = sqlsrv_query($conn, $countAllSql);
$totalRecords = 0;
if ($countAllStmt && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int) $row['total'];
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredSql = "SELECT COUNT(*) AS total " . $baseFrom . " " . $where;
$countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) {
    $totalFiltered = (int) $row['total'];
}
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "
SELECT x.id, x.tanggal, x.parameter, x.param_key, x.awal, x.akhir, x.total_pemakaian, x.pemakaian_rata, x.created_by
" . $baseFrom . "
" . $where . "
ORDER BY CAST(x.tanggal AS DATE) DESC,
         CASE x.parameter
           WHEN 'Air Boiler 21 Ton Actom' THEN 1
           WHEN 'Steam Boiler 21 Ton Actom' THEN 2
           WHEN 'Air Analog 21 Ton Actom' THEN 3
           WHEN 'Analog Steam 21 Ton Actom' THEN 4
           ELSE 5
         END ASC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Gagal mengambil data.'
    ]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal'];
    if ($tgl instanceof DateTime) {
        $tanggalRaw = $tgl->format('Y-m-d');
        $tanggalFmt = $tgl->format('d-m-Y');
    } else {
        $tanggalRaw = (string) $tgl;
        $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : '';
    }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'parameter' => $row['parameter'] ?? '',
        'param_key' => $row['param_key'] ?? '',
        'awal' => $row['awal'] ?? null,
        'akhir' => $row['akhir'] ?? null,
        'total_pemakaian' => $row['total_pemakaian'] ?? null,
        'pemakaian_rata' => $row['pemakaian_rata'] ?? null,
        'created_by' => $row['created_by'] ?? '',
        'aksi' => '-'
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
