<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

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
$weavingFilter = intval($_POST['weaving'] ?? 0);
$compressorFilter = intval($_POST['compressor_no'] ?? 0);

$permissions = weaving_permissions($conn);
if (!weaving_can($permissions, 'CanView')) {
    json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Anda tidak memiliki hak melihat data.'
    ]);
}

if (!temp_compressor_v2_table_exists($conn)) {
    json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Tabel dbo.temp_compressor_v2 belum tersedia.'
    ]);
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
if (in_array($weavingFilter, [1, 2], true)) {
    $whereParts[] = 'Weaving = ?';
    $params[] = $weavingFilter;
}
if (in_array($compressorFilter, [1, 2, 3], true)) {
    $whereParts[] = 'Compressor_No = ?';
    $params[] = $compressorFilter;
}
if ($search !== '') {
    $whereParts[] = "(CONVERT(VARCHAR(10), Tanggal, 120) LIKE ? OR Pelaksana LIKE ? OR CreatBy LIKE ? OR UpdateBy LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$where = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$groupSql = "FROM dbo.temp_compressor_v2 $where GROUP BY CAST(Tanggal AS DATE), Weaving, Compressor_No";

$totalRecords = 0;
$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Weaving, Compressor_No FROM dbo.temp_compressor_v2 GROUP BY CAST(Tanggal AS DATE), Weaving, Compressor_No) x");
if ($countAllStmt && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int)($row['total'] ?? 0);
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Weaving, Compressor_No $groupSql) x", $params);
    if ($countStmt && ($row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = (int)($row['total'] ?? 0);
    } else {
        $totalFiltered = 0;
    }
    if ($countStmt) sqlsrv_free_stmt($countStmt);
}

$sql = "SELECT MIN(Id) AS Id,
            CAST(Tanggal AS DATE) AS Tanggal,
            Weaving,
            Compressor_No,
            MAX(CreatBy) AS CreatBy,
            MAX(CreatAt) AS CreatAt,
            MAX(UpdateBy) AS UpdateBy,
            MAX(UpdateAt) AS UpdateAt
        $groupSql
        ORDER BY CAST(Tanggal AS DATE) DESC, Weaving ASC, Compressor_No ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;

$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Gagal mengambil data Check Sheet Kompressor Sullair V2.']);
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggalKey = temp_compressor_v2_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
    $w = (int)($row['Weaving'] ?? 0);
    $c = (int)($row['Compressor_No'] ?? 0);
    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggalKey,
        'tanggal_formatted' => temp_compressor_v2_fmt_date($row['Tanggal'] ?? null, 'd/m/Y'),
        'weaving' => $w,
        'weaving_label' => 'Weaving ' . $w,
        'compressor_no' => $c,
        'compressor_label' => 'Compressor ' . $c,
        'pelaksana' => temp_compressor_v2_pelaksana_for_sheet_query($conn, $tanggalKey, $w, $c),
        'created_by' => $row['CreatBy'] ?? '',
        'updated_by' => !empty($row['UpdateBy']) ? $row['UpdateBy'] : '-',
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
