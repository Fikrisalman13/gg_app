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
        OR CONVERT(VARCHAR(50), x.meter_awal_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.meter_akhir_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.total_pemakaian_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.meter_awal_debit_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.meter_akhir_debit_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.total_pemakaian_debit_m3) LIKE ?
        OR x.ket_mc_yang_jalan LIKE ?
        OR x.ket LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "FROM dbo.perblerange1_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal, x.meter_awal_m3, x.meter_akhir_m3, x.total_pemakaian_m3,
               x.meter_awal_debit_m3, x.meter_akhir_debit_m3, x.total_pemakaian_debit_m3,
               x.operasional_mc_pbr1_jam, x.pemakaian_rata_per_jam_m3,
               x.jumlah_debit_m3, x.operasional_mc_pbr1_debit_jam, x.pemakaian_rata_per_jam_debit_m3,
               x.ket_mc_yang_jalan, x.ket,
               COALESCE(x.updateby, x.creatby, '') AS created_by
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
        'meter_awal_m3' => $row['meter_awal_m3'] ?? 0,
        'meter_akhir_m3' => $row['meter_akhir_m3'] ?? 0,
        'total_pemakaian_m3' => $row['total_pemakaian_m3'] ?? 0,
        'meter_awal_debit_m3' => $row['meter_awal_debit_m3'] ?? 0,
        'meter_akhir_debit_m3' => $row['meter_akhir_debit_m3'] ?? 0,
        'total_pemakaian_debit_m3' => $row['total_pemakaian_debit_m3'] ?? 0,
        'operasional_mc_pbr1_jam' => $row['operasional_mc_pbr1_jam'] ?? 0,
        'pemakaian_rata_per_jam_m3' => $row['pemakaian_rata_per_jam_m3'] ?? 0,
        'jumlah_debit_m3' => $row['jumlah_debit_m3'] ?? 0,
        'operasional_mc_pbr1_debit_jam' => $row['operasional_mc_pbr1_debit_jam'] ?? 0,
        'pemakaian_rata_per_jam_debit_m3' => $row['pemakaian_rata_per_jam_debit_m3'] ?? 0,
        'ket_mc_yang_jalan' => $row['ket_mc_yang_jalan'] ?? '',
        'ket' => $row['ket'] ?? '',
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
