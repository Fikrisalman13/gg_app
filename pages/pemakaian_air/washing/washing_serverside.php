<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230; // TODO: ganti dengan MenuId washing di database
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

$where .= ($where === '' ? 'WHERE' : ' AND') . " (
    Meter_Awal IS NOT NULL
    OR Meter_Ahir IS NOT NULL
    OR Total_Pemakaian IS NOT NULL
    OR Operasional_Mesin IS NOT NULL
    OR Pemakaian_rata2perjam IS NOT NULL
 )";

if ($startDate !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " CAST(Tanggal AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " CAST(Tanggal AS DATE) <= ?";
    $params[] = $endDate;
}

if ($search !== '') {
    $where .= ($where === '' ? 'WHERE' : ' AND') . " (
        CONVERT(VARCHAR(10), Tanggal, 120) LIKE ?
        OR CreatBy LIKE ?
        OR Keterangan LIKE ?
        OR Catatan LIKE ?
        OR CONVERT(VARCHAR(50), Meter_Awal) LIKE ?
        OR CONVERT(VARCHAR(50), Meter_Ahir) LIKE ?
        OR CONVERT(VARCHAR(50), Total_Pemakaian) LIKE ?
        OR CONVERT(VARCHAR(50), Operasional_Mesin) LIKE ?
        OR CONVERT(VARCHAR(50), Pemakaian_rata2perjam) LIKE ?
    )";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$totalRecords = 0;
$countAllSql = "SELECT COUNT(*) AS total FROM dbo.washing_air";
$countAllStmt = sqlsrv_query($conn, $countAllSql);
if ($countAllStmt !== false && $row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countFilteredSql = "SELECT COUNT(*) AS total FROM dbo.washing_air $where";
    $countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
    if ($countFilteredStmt !== false && $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
        $totalFiltered = $row['total'];
    } else {
        $totalFiltered = 0;
    }
    if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);
}

$sql = "SELECT Id, Tanggal, CreatBy, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan, Catatan
        FROM dbo.washing_air
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
    $tanggalRaw = $row['Tanggal'] ?? null;
    $tanggal = $tanggalRaw;
    $tanggalFormatted = $tanggalRaw;
    if ($tanggalRaw instanceof DateTime) {
        $tanggal = $tanggalRaw->format('Y-m-d');
        $tanggalFormatted = $tanggalRaw->format('d-m-Y');
    }

    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggal ?? '',
        'tanggal_formatted' => $tanggalFormatted ?? '',
        'meter_awal' => $row['Meter_Awal'] ?? null,
        'meter_akhir' => $row['Meter_Ahir'] ?? null,
        'total_pemakaian' => $row['Total_Pemakaian'] ?? null,
        'operasional_mesin' => $row['Operasional_Mesin'] ?? null,
        'pemakaian_rata2perjam' => $row['Pemakaian_rata2perjam'] ?? null,
        'keterangan' => $row['Keterangan'] ?? '',
        'catatan' => $row['Catatan'] ?? '',
        'created_by' => $row['CreatBy'] ?? ''
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => intval($totalRecords),
    'recordsFiltered' => intval($totalFiltered),
    'data' => $data
]);
