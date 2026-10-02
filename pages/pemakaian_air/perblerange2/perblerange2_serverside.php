<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
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
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';

$where = '';
$params = [];

if ($startDate !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " CAST(Tanggal AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " CAST(Tanggal AS DATE) <= ?";
    $params[] = $endDate;
}

if ($search !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " (CONVERT(VARCHAR(10), Tanggal, 120) LIKE ? 
              OR CreatBy LIKE ? 
              OR Keterangan LIKE ?
              OR CONVERT(VARCHAR(50), (Meter_Ahir - Meter_Awal)) LIKE ?
              OR CONVERT(VARCHAR(50), Oprasional_Mesin) LIKE ?
              OR CONVERT(VARCHAR(50), Pemakaian_Rata2perjam) LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Total records (tanpa filter)
$totalRecords = 0;
$countAllSql = "SELECT COUNT(*) AS total FROM dbo.perblerange2_air";
$countAllStmt = sqlsrv_query($conn, $countAllSql);
if ($countAllStmt !== false && $row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}
if ($countAllStmt) {
    sqlsrv_free_stmt($countAllStmt);
}

// Total filtered
$totalFiltered = $totalRecords;
if ($where !== '') {
    $countFilteredSql = "SELECT COUNT(*) AS total FROM dbo.perblerange2_air $where";
    $countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
    if ($countFilteredStmt !== false && $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = $row['total'];
    } else {
        $totalFiltered = 0;
    }
    if ($countFilteredStmt) {
        sqlsrv_free_stmt($countFilteredStmt);
    }
}

// Data query with pagination
$sql = "SELECT Id, Tanggal, CreatBy, Meter_Awal, Meter_Ahir,
               (Meter_Ahir - Meter_Awal) AS Total_Pemakaian,
               Oprasional_Mesin, Pemakaian_Rata2perjam, Keterangan
        FROM dbo.perblerange2_air
        $where
        ORDER BY CAST(Tanggal AS DATE) DESC, Id DESC
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
    $tanggalRaw = $row['Tanggal'];
    $tanggal = $tanggalRaw;
    $tanggalFormatted = $tanggalRaw;
    if ($tanggalRaw instanceof DateTime) {
        $tanggal = $tanggalRaw->format('Y-m-d');
        $tanggalFormatted = $tanggalRaw->format('d-m-Y');
    }

    $keterangan = $row['Keterangan'] ?? '';
    $isOff = strtolower(trim((string)$keterangan)) === 'off';

    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggal ?? '',
        'tanggal_formatted' => $tanggalFormatted ?? '',
        'meter_awal' => $row['Meter_Awal'] ?? 0,
        'meter_akhir' => $row['Meter_Ahir'] ?? 0,
        'total_pemakaian' => $row['Total_Pemakaian'] ?? 0,
        'oprasional_mesin' => $isOff ? null : ($row['Oprasional_Mesin'] ?? 0),
        'pemakaian_rata_rata_jam' => $isOff ? null : ($row['Pemakaian_Rata2perjam'] ?? 0),
        'created_by' => $row['CreatBy'] ?? '',
        'keterangan' => $keterangan
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => intval($totalRecords),
    'recordsFiltered' => intval($totalFiltered),
    'data' => $data
]);






