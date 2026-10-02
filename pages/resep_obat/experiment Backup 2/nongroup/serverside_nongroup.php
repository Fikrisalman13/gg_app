<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../../../../koneksi3.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]); exit; }

function checkPermissions($conn, $groupId, $menuId) {
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $p = $r;
  return $p;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
if (($permissions['CanView'] ?? 0) != 1) { echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]); exit; }

// DataTables params
$start   = (int)($_GET['start'] ?? 0);
$length  = (int)($_GET['length'] ?? 50);
$search  = trim($_GET['search']['value'] ?? '');
$orderBy = $_GET['order'][0]['column'] ?? 0;
$dir     = $_GET['order'][0]['dir'] ?? 'desc';

$cols = ['e.created_at','e.soi','e.no_cp','e.kode_warna','e.color_name','e.resep_prod_code','e.resep_prod_name','e.cus_color','e.experiment_seq','e.experiment_status','e.updated_at','e.updated_by'];
$orderByCol = $cols[$orderBy] ?? 'e.created_at';
if (!in_array($dir, ['asc', 'desc'])) $dir = 'desc';

// Filter params
$filterStatus = trim($_GET['filter_status'] ?? '');
$filterDateFrom = trim($_GET['filter_date_from'] ?? '');
$filterDateTo   = trim($_GET['filter_date_to'] ?? '');

// Base query with JOIN to get soi from group
$baseFrom = "dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id = e.group_id";
$conditions = [];
$params = [];

// Search
if ($search !== '') {
  $conditions[] = "(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.resep_prod_code LIKE ? OR e.resep_prod_name LIKE ? OR e.cus_color LIKE ? OR e.soi LIKE ? OR e.created_by LIKE ?)";
  $s = "%$search%";
  $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s]);
}

// Filter status — tentative: filter on experiment_status first, PostgreSQL fail override handled in PHP
if ($filterStatus !== '' && in_array($filterStatus, ['Draft', 'Process', 'Sukses', 'Gagal', 'Approved'])) {
  $conditions[] = "e.experiment_status = ?";
  $params[] = $filterStatus;
}

// Filter date
if ($filterDateFrom !== '') {
  $conditions[] = "e.created_at >= ?";
  $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
  $conditions[] = "e.created_at <= ?";
  $params[] = $filterDateTo . ' 23:59:59';
}

$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total
$countSql = "SELECT COUNT(*) AS cnt FROM $baseFrom $where";
$stmtCount = sqlsrv_query($conn, $countSql, $params);
$totalRecords = 0;
$filteredRecords = 0;
if ($stmtCount && $rc = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
  $filteredRecords = $totalRecords = (int)$rc['cnt'];
}
if ($stmtCount) sqlsrv_free_stmt($stmtCount);

// Count total (unfiltered) for recordsTotal
$countAllSql = "SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e";
$stmtAll = sqlsrv_query($conn, $countAllSql);
if ($stmtAll && $ra = sqlsrv_fetch_array($stmtAll, SQLSRV_FETCH_ASSOC)) {
  $totalRecords = (int)$ra['cnt'];
}
if ($stmtAll) sqlsrv_free_stmt($stmtAll);

// Fetch data
$sql = "SELECT e.id, e.created_at, e.soi, e.no_cp, g.no_cp AS group_no_cp, e.kode_warna, e.color_name, e.resep_prod_code, e.resep_prod_name, e.cus_color, e.experiment_seq, e.experiment_status, e.updated_at, e.updated_by
        FROM $baseFrom $where ORDER BY $orderByCol $dir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql, $params);

$experiments = [];
$noCpList = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $experiments[] = $r;
    $ncp = trim((string)($r['no_cp'] ?? ''));
    if ($ncp !== '') $noCpList[] = $ncp;
  }
  sqlsrv_free_stmt($stmt);
}

// Query PostgreSQL ERP for fail status (batch)
$failMap = [];
if (!empty($noCpList)) {
  try {
    $accWarnaRRtgmsId = 589;

    // Build IN clause
    $placeholders = [];
    $pgParams = [];
    foreach (array_unique($noCpList) as $idx => $ncp) {
      $k = ':cp_' . $idx;
      $placeholders[] = $k;
      $pgParams[$k] = $ncp;
    }
    $ph = implode(',', $placeholders);

    $pgSql = "
      WITH latest_cp AS (
        SELECT
          TRIM(CAST(prdnmbr AS TEXT)) AS cp_no,
          productionhdid,
          ROW_NUMBER() OVER (PARTITION BY TRIM(CAST(prdnmbr AS TEXT)) ORDER BY prddate DESC NULLS LAST, productionhdid DESC) AS rn
        FROM pdproductionhd
        WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN (" . $ph . ")
      ),
      fail_check AS (
        SELECT
          l.cp_no,
          COALESCE(f.failcode, '') AS failcode,
          COALESCE(f.faildesc, '') AS faildesc,
          CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 1 ELSE 0 END AS has_fail
        FROM latest_cp l
        JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = $accWarnaRRtgmsId
        LEFT JOIN pdfailms f ON r.failmsid = f.failmsid
        WHERE l.rn = 1
        ORDER BY r.starttime DESC NULLS LAST, r.productionrtgid DESC
      )
      SELECT cp_no, failcode, faildesc, has_fail
      FROM fail_check
    ";

    $pgStmt = $conn3->prepare($pgSql);
    $pgStmt->execute($pgParams);
    while ($pgRow = $pgStmt->fetch(PDO::FETCH_ASSOC)) {
      $cpUpper = strtoupper(trim($pgRow['cp_no']));
      $failcode = trim($pgRow['failcode'] ?? '');
      $faildesc = trim($pgRow['faildesc'] ?? '');
      $hasFail = (int)($pgRow['has_fail'] ?? 0);

      if ($hasFail && ($failcode !== '' || $faildesc !== '')) {
        $failMap[$cpUpper] = 'Fail';
      } else {
        $failMap[$cpUpper] = 'Pass';
      }
    }
  } catch (Throwable $e) {
    error_log('nongroup PG error: ' . $e->getMessage());
  }
}

// Format output
$rows = [];
foreach ($experiments as $e) {
  $ncp = strtoupper(trim((string)($e['no_cp'] ?? '')));
  $expStatus = trim((string)($e['experiment_status'] ?? 'Draft'));

  // Determine display status
  // ERP (koneksi3) is most accurate — always wins if data exists
  if (isset($failMap[$ncp])) {
    $displayStatus = $failMap[$ncp]; // 'Fail' or 'Pass'
  } else {
    // No ERP data — fallback to user-set experiment_status
    $displayStatus = $expStatus;
  }

  $rows[] = [
    'id'               => (int)$e['id'],
    'waktu_input'      => ($e['created_at'] instanceof DateTime) ? $e['created_at']->format('d/m/Y H:i') : '-',
    'soi'              => !empty($e['soi']) ? $e['soi'] : '-',
    'no_cp'            => !empty($e['no_cp']) ? $e['no_cp'] : (!empty($e['group_no_cp']) ? $e['group_no_cp'] : '-'),
    'kode_warna'       => $e['kode_warna'] ?? '-',
    'color_name'       => $e['color_name'] ?? '-',
    'resep_prod_code'  => $e['resep_prod_code'] ?? '-',
    'resep_prod_name'  => $e['resep_prod_name'] ?? '-',
    'cus_color'        => $e['cus_color'] ?? '-',
    'experiment_seq'   => (int)($e['experiment_seq'] ?? 1),
    'display_status'   => $displayStatus,
    'updated_at'       => ($e['updated_at'] instanceof DateTime) ? $e['updated_at']->format('d/m/Y H:i') : '-',
    'updated_by'       => $e['updated_by'] ?? '-',
  ];
}

echo json_encode([
  'draw'            => (int)($_GET['draw'] ?? 1),
  'recordsTotal'    => $totalRecords,
  'recordsFiltered' => $filteredRecords,
  'data'            => $rows,
]);
