<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
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

$where = '';
$params = [];

$where .= ($where === '' ? 'WHERE' : ' AND') . " (
    Meter_Awal IS NOT NULL
    OR Meter_Akhir IS NOT NULL
    OR Total_Pemakaian IS NOT NULL
    OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
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
        OR CONVERT(VARCHAR(50), Meter_Akhir) LIKE ?
        OR CONVERT(VARCHAR(50), Total_Pemakaian) LIKE ?
    )";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$totalRecords = 0;
$countAllSql = "SELECT COUNT(*) AS total FROM dbo.lab_air";
$countAllStmt = sqlsrv_query($conn, $countAllSql);
if ($countAllStmt !== false && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int)($row['total'] ?? 0);
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countFilteredSql = "SELECT COUNT(*) AS total FROM dbo.lab_air $where";
    $countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
    if ($countFilteredStmt !== false && ($row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = (int)($row['total'] ?? 0);
    } else {
        $totalFiltered = 0;
    }
    if ($countFilteredStmt) sqlsrv_free_stmt($countFilteredStmt);
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian, Keterangan, Catatan, CreatBy
        FROM dbo.lab_air
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
    } elseif (is_string($tanggalRaw) && $tanggalRaw !== '') {
        $ts = strtotime($tanggalRaw);
        if ($ts) {
            $tanggal = date('Y-m-d', $ts);
            $tanggalFormatted = date('d-m-Y', $ts);
        }
    }

    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggal ?? '',
        'tanggal_formatted' => $tanggalFormatted ?? '',
        'meter_awal' => $row['Meter_Awal'] ?? null,
        'meter_akhir' => $row['Meter_Akhir'] ?? null,
        'total_pemakaian' => $row['Total_Pemakaian'] ?? null,
        'keterangan' => $row['Keterangan'] ?? '',
        'catatan' => $row['Catatan'] ?? '',
        'created_by' => $row['CreatBy'] ?? ''
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => (int)$totalRecords,
    'recordsFiltered' => (int)$totalFiltered,
    'data' => $data
]);

