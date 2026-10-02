<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak melihat data.']);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) >= ?"; $params[] = $startDate; }
if ($endDate !== '') { $where .= " AND CAST(x.tanggal AS DATE) <= ?"; $params[] = $endDate; }
if ($search !== '') {
    $where .= " AND (
        CONVERT(VARCHAR(10), x.tanggal, 120) LIKE ?
        OR CONVERT(VARCHAR(50), x.kwh_pln) LIKE ?
        OR CONVERT(VARCHAR(50), x.kwh_utility_amp_acb) LIKE ?
        OR CONVERT(VARCHAR(50), x.df_amp_acb) LIKE ?
        OR CONVERT(VARCHAR(50), x.weaving1_amp_acb) LIKE ?
        OR CONVERT(VARCHAR(50), x.weaving2_amp_acb) LIKE ?
        OR x.ket LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "FROM dbo.listrik_perbagian_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal, x.kwh_pln,
               x.kwh_utility_amp_acb, x.kwh_utility_kwh_hari,
               x.df_amp_acb, x.df_kwh_hari,
               x.weaving1_amp_acb, x.weaving1_kwh_hari,
               x.weaving2_amp_acb, x.weaving2_kwh_hari,
               x.jumlah_kwh_hari, x.jumlah_ampere, x.kwh_per_jam, x.efisiensi_persen,
               x.ket, COALESCE(x.updateby, x.creatby, '') AS created_by
        " . $baseFrom . " " . $where . "
        ORDER BY CAST(x.tanggal AS DATE) DESC, x.id DESC
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
    if ($tgl instanceof DateTime) { $tanggalRaw = $tgl->format('Y-m-d'); $tanggalFmt = $tgl->format('d-m-Y'); }
    else { $tanggalRaw = (string)$tgl; $tanggalFmt = $tanggalRaw !== '' ? date('d-m-Y', strtotime($tanggalRaw)) : ''; }

    $data[] = [
        'id' => $row['id'] ?? null,
        'tanggal' => $tanggalRaw,
        'tanggal_formatted' => $tanggalFmt,
        'kwh_pln' => $row['kwh_pln'] ?? 0,
        'kwh_utility_amp_acb' => $row['kwh_utility_amp_acb'] ?? 0,
        'kwh_utility_kwh_hari' => $row['kwh_utility_kwh_hari'] ?? 0,
        'df_amp_acb' => $row['df_amp_acb'] ?? 0,
        'df_kwh_hari' => $row['df_kwh_hari'] ?? 0,
        'weaving1_amp_acb' => $row['weaving1_amp_acb'] ?? 0,
        'weaving1_kwh_hari' => $row['weaving1_kwh_hari'] ?? 0,
        'weaving2_amp_acb' => $row['weaving2_amp_acb'] ?? 0,
        'weaving2_kwh_hari' => $row['weaving2_kwh_hari'] ?? 0,
        'jumlah_kwh_hari' => $row['jumlah_kwh_hari'] ?? 0,
        'jumlah_ampere' => $row['jumlah_ampere'] ?? 0,
        'kwh_per_jam' => $row['kwh_per_jam'] ?? 0,
        'efisiensi_persen' => $row['efisiensi_persen'] ?? 0,
        'ket' => $row['ket'] ?? '',
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
