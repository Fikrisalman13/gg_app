<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$compressorNo = trim($_POST['compressor_no'] ?? '');

$permissions = weaving_permissions($conn);
if (!weaving_can($permissions, 'CanView')) {
    json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Anda tidak memiliki hak melihat data.']);
}

if (!ccirl_table_exists($conn)) {
    json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Tabel dbo.ccirl belum tersedia.']);
}

$whereParts = [];
$params = [];
if ($startDate !== '') {
    $whereParts[] = 'CAST(Tanggal AS DATE) >= ?';
    $params[] = $startDate;
}
if ($endDate !== '') {
    $whereParts[] = 'CAST(Tanggal AS DATE) <= ?';
    $params[] = $endDate;
}
if ($compressorNo !== '') {
    $whereParts[] = 'Compressor_No = ?';
    $params[] = $compressorNo;
}
if ($search !== '') {
    $whereParts[] = "(CONVERT(VARCHAR(10), Tanggal, 120) LIKE ? OR Compressor_No LIKE ? OR Petugas LIKE ? OR CreatBy LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}
$where = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$groupSql = "FROM dbo.ccirl $where GROUP BY CAST(Tanggal AS DATE), Compressor_No";

$totalRecords = 0;
$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Compressor_No FROM dbo.ccirl GROUP BY CAST(Tanggal AS DATE), Compressor_No) x");
if ($countAllStmt && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) $totalRecords = (int)($row['total'] ?? 0);
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Compressor_No $groupSql) x", $params);
    if ($countStmt && ($row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) $totalFiltered = (int)($row['total'] ?? 0);
    else $totalFiltered = 0;
    if ($countStmt) sqlsrv_free_stmt($countStmt);
}

$sql = "SELECT MIN(Id) AS Id, CAST(Tanggal AS DATE) AS Tanggal, Compressor_No,
            MAX(Petugas) AS Petugas, MAX(CreatBy) AS CreatBy, MAX(CreatAt) AS CreatAt
        $groupSql
        ORDER BY CAST(Tanggal AS DATE) DESC, Compressor_No ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;
$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Gagal mengambil data CCIRL.']);

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggalKey = ccirl_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
    $cells = ccirl_get_cells($conn, $tanggalKey, $row['Compressor_No'] ?? '');
    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggalKey,
        'tanggal_formatted' => ccirl_fmt_date($row['Tanggal'] ?? null, 'd/m/Y'),
        'compressor_no' => $row['Compressor_No'] ?? '',
        'petugas' => ccirl_petugas_for_sheet($cells),
        'created_by' => $row['CreatBy'] ?? '',
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $totalFiltered, 'data' => $data]);
