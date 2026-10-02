<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../../../../koneksi3.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
  echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
  exit;
}

function checkPermissions($conn, $groupId, $menuId)
{
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
    $p = $r;
  return $p;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
$isAdmin = (int) ($_SESSION['GroupId'] ?? 0) === 1;
if (($permissions['CanView'] ?? 0) != 1) {
  echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
  exit;
}

// DataTables params
$start = (int) ($_GET['start'] ?? 0);
$length = (int) ($_GET['length'] ?? 50);
$search = trim($_GET['search']['value'] ?? '');
$orderBy = $_GET['order'][0]['column'] ?? 0;
$dir = $_GET['order'][0]['dir'] ?? 'desc';

$cols = [null, 'e.created_at', 'e.soi', 'e.kode_warna', 'e.resep_prod_code', 'e.experiment_seq', 'e.experiment_status', null, 'e.updated_at', null];
$orderByCol = $cols[(int)$orderBy] ?? 'e.created_at';
if (!in_array($dir, ['asc', 'desc']))
  $dir = 'desc';

// Filter params
$filterStatus = trim($_GET['filter_status'] ?? '');
$filterDateFrom = trim($_GET['filter_date_from'] ?? '');
$filterDateTo = trim($_GET['filter_date_to'] ?? '');
$filterLabel = trim($_GET['filter_label'] ?? '');
$filterCreatedBy = trim($_GET['filter_created_by'] ?? '');
$searchStatusMap = ['draft'=>'Draft','approved'=>'Approved','process'=>'Process','prosess'=>'Process','pass'=>'Pass','fail'=>'Fail','sukses'=>'Sukses','gagal'=>'Gagal'];
$searchStatus = $searchStatusMap[strtolower($search)] ?? '';
if ($filterStatus === '' && $searchStatus !== '') {
  $filterStatus = $searchStatus;
  $search = '';
}

// Base query with JOIN to get soi from group
$baseFrom = "dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id = e.group_id";
$conditions = [];
$params = [];

// Apply visibility
resepExperimentApplyVisibility($conditions, $params, 'e', $conn);

// Search
if ($search !== '') {
  $conditions[] = "(e.no_cp LIKE ? OR e.kode_warna LIKE ? OR e.color_name LIKE ? OR e.resep_prod_code LIKE ? OR e.resep_prod_name LIKE ? OR e.cus_color LIKE ? OR e.soi LIKE ? OR e.created_by LIKE ?)";
  $s = "%$search%";
  $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s]);
}

// Dedicated filters remain independent from DataTables global search.
if ($filterLabel !== '') {
  $labelSearch = "%$filterLabel%";
  $conditions[] = "(e.cus_color LIKE ? OR e.resep_prod_code LIKE ? OR e.resep_prod_name LIKE ?)";
  $params = array_merge($params, [$labelSearch, $labelSearch, $labelSearch]);
}
if ($filterCreatedBy !== '') {
  $conditions[] = "e.created_by = ?";
  $params[] = $filterCreatedBy;
}

// Pass/Fail may be derived from koneksi3 while SQL Server still stores Process.
$effectiveStatusFilter = in_array($filterStatus, ['Pass', 'Fail'], true) ? $filterStatus : '';
if ($filterStatus !== '' && in_array($filterStatus, ['Draft', 'Process', 'Sukses', 'Gagal', 'Approved'], true)) {
  if ($filterStatus === 'Process') {
    $conditions[] = "e.experiment_status IN ('Process','Prosess')";
  } else {
    $conditions[] = "e.experiment_status = ?";
    $params[] = $filterStatus;
  }
} elseif ($effectiveStatusFilter !== '') {
  $conditions[] = "e.experiment_status IN ('Process','Prosess','Pass','Fail')";
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
  $filteredRecords = $totalRecords = (int) $rc['cnt'];
}
if ($stmtCount)
  sqlsrv_free_stmt($stmtCount);

// Count total (unfiltered) for recordsTotal
$baseConditions = [];
$baseParams = [];
resepExperimentApplyVisibility($baseConditions, $baseParams, 'e', $conn);
$whereBase = count($baseConditions) ? 'WHERE ' . implode(' AND ', $baseConditions) : '';

$countAllSql = "SELECT COUNT(*) AS cnt FROM dbo.resep_obat_experiment e $whereBase";
$stmtAll = sqlsrv_query($conn, $countAllSql, $baseParams);
if ($stmtAll && $ra = sqlsrv_fetch_array($stmtAll, SQLSRV_FETCH_ASSOC)) {
  $totalRecords = (int) $ra['cnt'];
}
if ($stmtAll)
  sqlsrv_free_stmt($stmtAll);

// Fetch data
$hasQcKeputusan = false;
$stmtQcCol = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.resep_obat_experiment', 'qc_keputusan') AS len");
if ($stmtQcCol && $qcCol = sqlsrv_fetch_array($stmtQcCol, SQLSRV_FETCH_ASSOC)) {
  $hasQcKeputusan = $qcCol['len'] !== null;
}
if ($stmtQcCol)
  sqlsrv_free_stmt($stmtQcCol);
$qcKeputusanSelect = $hasQcKeputusan ? 'e.qc_keputusan' : 'NULL AS qc_keputusan';
$paginationSql = $effectiveStatusFilter === '' ? "OFFSET $start ROWS FETCH NEXT $length ROWS ONLY" : '';
$sql = "SELECT e.id, e.created_at, e.created_by, e.soi, e.no_cp, e.kode_warna, e.color_name, e.resep_prod_code, e.resep_prod_name, e.cus_color, e.experiment_seq, e.experiment_status, e.updated_at, e.updated_by, $qcKeputusanSelect
        FROM $baseFrom $where ORDER BY $orderByCol $dir $paginationSql";
$stmt = sqlsrv_query($conn, $sql, $params);

$experiments = [];
$noCpList = [];
if ($stmt) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $experiments[] = $r;
    $ncp = trim((string) ($r['no_cp'] ?? ''));
    if ($ncp !== '')
      $noCpList[] = $ncp;
  }
  sqlsrv_free_stmt($stmt);
}

// Query PostgreSQL ERP for fail status (batch)
$failMap = [];
$posisiMap = [];
$descriptionMap = [];
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
      latest AS (
        SELECT cp_no, productionhdid
        FROM latest_cp
        WHERE rn = 1
      ),
      last_filled AS (
        SELECT DISTINCT ON (l.cp_no)
          l.cp_no,
          r.rtgseq,
          r.rtgmsid
        FROM latest l
        JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid
        WHERE COALESCE(r.prdqty, 0) > 0
           OR r.prduomid IS NOT NULL
           OR COALESCE(r.prdstdqty, 0) > 0
        ORDER BY l.cp_no, r.rtgseq DESC NULLS LAST, r.productionrtgid DESC
      ),
      posisi AS (
        SELECT
          l.cp_no,
          CASE
            WHEN lf.rtgmsid = 692 THEN 'VERPACKING'
            WHEN COALESCE(next_m.rtgname, '') <> '' THEN next_m.rtgname
            WHEN COALESCE(first_m.rtgname, '') <> '' THEN first_m.rtgname
            ELSE 'PEMARTAIAN'
          END AS posisi_hari_ini
        FROM latest l
        LEFT JOIN last_filled lf ON lf.cp_no = l.cp_no
        LEFT JOIN pdproductionrtg next_r ON next_r.productionhdid = l.productionhdid AND next_r.rtgseq = lf.rtgseq + 1
        LEFT JOIN pdrtgms next_m ON next_m.rtgmsid = next_r.rtgmsid
        LEFT JOIN pdproductionrtg first_r ON first_r.productionhdid = l.productionhdid AND first_r.rtgmsid = 409
        LEFT JOIN pdrtgms first_m ON first_m.rtgmsid = first_r.rtgmsid
      ),
      acc_warna AS (
        SELECT
          l.cp_no,
          COALESCE(r.failmsid, 0) AS failmsid,
          COALESCE(r.fgresult, '') AS fgresult,
          COALESCE(f.failcode, '') AS failcode,
          COALESCE(f.faildesc, '') AS faildesc,
          COALESCE(r.resultdesc, '') AS resultdesc,
          ROW_NUMBER() OVER (
            PARTITION BY l.cp_no
            ORDER BY
              CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC,
              r.starttime DESC NULLS LAST,
              r.productionrtgid DESC
          ) AS rn
        FROM latest l
        JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = $accWarnaRRtgmsId
        LEFT JOIN pdfailms f ON r.failmsid = f.failmsid
      )
      SELECT a.cp_no, a.failmsid, a.fgresult, a.failcode, a.faildesc, a.resultdesc,
             COALESCE(p.posisi_hari_ini, '-') AS posisi_hari_ini
      FROM acc_warna a
      LEFT JOIN posisi p ON p.cp_no = a.cp_no
      WHERE rn = 1
    ";

    $pgStmt = $conn3->prepare($pgSql);
    $pgStmt->execute($pgParams);
    while ($pgRow = $pgStmt->fetch(PDO::FETCH_ASSOC)) {
      $cpUpper = strtoupper(trim($pgRow['cp_no']));
      $failcode = trim($pgRow['failcode'] ?? '');
      $faildesc = trim($pgRow['faildesc'] ?? '');
      $failmsid = (int) ($pgRow['failmsid'] ?? 0);
      $fgResult = strtoupper(trim((string) ($pgRow['fgresult'] ?? '')));
      $hasFailName = ($failcode !== '' || $faildesc !== '');

      if ($failmsid !== 0 && $hasFailName) {
        $failMap[$cpUpper] = 'Fail';
      } elseif ($fgResult === 'P' && $failmsid === 0 && !$hasFailName) {
        $failMap[$cpUpper] = 'Pass';
      }
      $posisiMap[$cpUpper] = trim((string) ($pgRow['posisi_hari_ini'] ?? '')) ?: '-';
      $descriptionMap[$cpUpper] = trim((string) ($pgRow['resultdesc'] ?? '')) ?: '-';
    }
  } catch (Throwable $e) {
    error_log('nongroup PG error: ' . $e->getMessage());
  }
}

// Format output
$rows = [];
foreach ($experiments as $e) {
  $ncp = strtoupper(trim((string) ($e['no_cp'] ?? '')));
  $expStatus = trim((string) ($e['experiment_status'] ?? 'Draft'));

  // Determine display status
  // ERP history only promotes Process rows to Pass/Fail.
  if (in_array($expStatus, ['Process', 'Prosess'], true) && isset($failMap[$ncp])) {
    $displayStatus = $failMap[$ncp];
  } else {
    $displayStatus = $expStatus;
  }

  if ($effectiveStatusFilter === '' || $displayStatus === $effectiveStatusFilter) {
    $rows[] = [
      'id' => (int) $e['id'],
      'waktu_input' => ($e['created_at'] instanceof DateTime) ? $e['created_at']->format('d/m/Y') : '-',
      'created_by' => $e['created_by'] ?? '-',
      'soi' => !empty($e['soi']) ? $e['soi'] : '-',
      'no_cp' => !empty($e['no_cp']) ? $e['no_cp'] : '-',
      'kode_warna' => $e['kode_warna'] ?? '-',
      'color_name' => $e['color_name'] ?? '-',
      'resep_prod_code' => $e['resep_prod_code'] ?? '-',
      'resep_prod_name' => $e['resep_prod_name'] ?? '-',
      'cus_color' => $e['cus_color'] ?? '-',
      'experiment_seq' => (int) ($e['experiment_seq'] ?? 1),
      'display_status' => $displayStatus,
      'updated_at' => ($e['updated_at'] instanceof DateTime) ? $e['updated_at']->format('d/m/Y') : '-',
      'updated_by' => $e['updated_by'] ?? '-',
      'posisi_hari_ini' => $posisiMap[$ncp] ?? '-',
      'deskripsi_produksi' => $descriptionMap[$ncp] ?? '-',
      'qc_keputusan' => trim((string) ($e['qc_keputusan'] ?? '')) ?: '-',
    ];
  }
}

if ($effectiveStatusFilter !== '') {
  $filteredRecords = count($rows);
  $rows = array_slice($rows, $start, $length);
}

echo json_encode([
  'draw' => (int) ($_GET['draw'] ?? 1),
  'recordsTotal' => $totalRecords,
  'recordsFiltered' => $filteredRecords,
  'data' => $rows,
]);
