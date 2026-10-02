<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

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
$dryerNo = trim($_POST['dryer_no'] ?? '');
$ctNo = trim($_POST['ct_no'] ?? '');

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

if (!dryer_weaving_table_exists($conn)) {
    json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Tabel dbo.dryer_weaving belum tersedia. Jalankan create_table_dryer_weaving.sql terlebih dahulu.'
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
if ($dryerNo !== '') {
    $whereParts[] = 'Dryer_No = ?';
    $params[] = $dryerNo;
}
if ($ctNo !== '') {
    $whereParts[] = 'Ct_No = ?';
    $params[] = $ctNo;
}
if ($search !== '') {
    $whereParts[] = "(Dryer_No LIKE ? OR Ct_No LIKE ? OR CONVERT(VARCHAR(10), Tanggal, 120) LIKE ? OR Petugas LIKE ? OR [Shift] LIKE ? OR CreatBy LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$where = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$groupSql = "FROM dbo.dryer_weaving $where GROUP BY CAST(Tanggal AS DATE), Dryer_No, Ct_No";

$totalRecords = 0;
$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Dryer_No, Ct_No FROM dbo.dryer_weaving GROUP BY CAST(Tanggal AS DATE), Dryer_No, Ct_No) x");
if ($countAllStmt && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int)($row['total'] ?? 0);
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Dryer_No, Ct_No $groupSql) x", $params);
    if ($countStmt && ($row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = (int)($row['total'] ?? 0);
    } else {
        $totalFiltered = 0;
    }
    if ($countStmt) sqlsrv_free_stmt($countStmt);
}

$sql = "SELECT MIN(Id) AS Id,
            CAST(Tanggal AS DATE) AS Tanggal,
            Dryer_No,
            Ct_No,
            MAX(Petugas) AS Petugas,
            MAX([Shift]) AS ShiftName,
            MAX(CreatBy) AS CreatBy,
            MAX(CreatAt) AS CreatAt
        $groupSql
        ORDER BY CAST(Tanggal AS DATE) DESC, Dryer_No ASC, Ct_No ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;

$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Gagal mengambil data Dryer Weaving.']);
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggalKey = dryer_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
    $data[] = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $tanggalKey,
        'tanggal_formatted' => dryer_weaving_fmt_date($row['Tanggal'] ?? null, 'd/m/Y'),
        'dryer_no' => $row['Dryer_No'] ?? '',
        'ct_no' => $row['Ct_No'] ?? '',
        'petugas' => dryer_weaving_petugas_for_sheet_query($conn, $tanggalKey, $row['Dryer_No'] ?? '', $row['Ct_No'] ?? ''),
        'shift' => dryer_weaving_shifts_for_sheet_query($conn, $tanggalKey, $row['Dryer_No'] ?? '', $row['Ct_No'] ?? '') ?: ($row['ShiftName'] ?? ''),
        'created_by' => $row['CreatBy'] ?? '',
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
