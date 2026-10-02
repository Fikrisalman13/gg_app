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
        OR CONVERT(VARCHAR(50), x.air_awal_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.air_akhir_m3) LIKE ?
        OR CONVERT(VARCHAR(50), x.steam_awal_ton) LIKE ?
        OR CONVERT(VARCHAR(50), x.steam_akhir_ton) LIKE ?
        OR x.air_ket LIKE ?
        OR x.steam_ket LIKE ?
        OR COALESCE(x.updateby, x.creatby, '') LIKE ?
    )";
    $sp = "%{$search}%";
    $params = array_merge($params, [$sp,$sp,$sp,$sp,$sp,$sp,$sp,$sp]);
}

$baseFrom = "FROM dbo.air_steam_20tonlama_harian x";

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom);
$totalRecords = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)$r['total'];
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total " . $baseFrom . " " . $where, $params);
$totalFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)$r['total'];
if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);

$sql = "SELECT x.id, x.tanggal, x.air_awal_m3, x.air_akhir_m3, x.air_total_pemakaian_m3, x.air_rata_rata_per_jam_m3, x.air_ket,
               x.steam_awal_ton, x.steam_akhir_ton, x.steam_total_pemakaian_ton, x.steam_rata_rata_per_jam_ton, x.steam_ket,
               CONVERT(VARCHAR(5), x.cut_off_jam, 108) AS cut_off_jam,
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
        'air_awal_m3' => $row['air_awal_m3'] ?? 0,
        'air_akhir_m3' => $row['air_akhir_m3'] ?? 0,
        'air_total_pemakaian_m3' => $row['air_total_pemakaian_m3'] ?? 0,
        'air_rata_rata_per_jam_m3' => $row['air_rata_rata_per_jam_m3'] ?? 0,
        'air_ket' => $row['air_ket'] ?? '',
        'steam_awal_ton' => $row['steam_awal_ton'] ?? 0,
        'steam_akhir_ton' => $row['steam_akhir_ton'] ?? 0,
        'steam_total_pemakaian_ton' => $row['steam_total_pemakaian_ton'] ?? 0,
        'steam_rata_rata_per_jam_ton' => $row['steam_rata_rata_per_jam_ton'] ?? 0,
        'steam_ket' => $row['steam_ket'] ?? '',
        'cut_off_jam' => $row['cut_off_jam'] ?? '09:00',
        'created_by' => $row['created_by'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['draw'=>$draw,'recordsTotal'=>$totalRecords,'recordsFiltered'=>$totalFiltered,'data'=>$data]);
