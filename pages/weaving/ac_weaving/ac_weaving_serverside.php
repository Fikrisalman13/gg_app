<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ac_weaving_helper.php');

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
$mesin = trim($_POST['mesin'] ?? '');

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

if (!ac_weaving_table_exists($conn)) {
    json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Tabel dbo.ac_weaving belum tersedia. Jalankan migration create_table_ac_weaving.sql terlebih dahulu.'
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
if ($mesin !== '') {
    $whereParts[] = 'Mesin = ?';
    $params[] = $mesin;
}
if ($search !== '') {
    $whereParts[] = "(Mesin LIKE ? OR CONVERT(VARCHAR(10), Tanggal, 120) LIKE ? OR Petugas LIKE ? OR [Shift] LIKE ? OR CreatBy LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$where = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$groupSql = "FROM dbo.ac_weaving $where GROUP BY CAST(Tanggal AS DATE), Mesin";

$totalRecords = 0;
$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Mesin FROM dbo.ac_weaving GROUP BY CAST(Tanggal AS DATE), Mesin) x");
if ($countAllStmt && ($row = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRecords = (int)($row['total'] ?? 0);
}
if ($countAllStmt) sqlsrv_free_stmt($countAllStmt);

$totalFiltered = $totalRecords;
if ($where !== '') {
    $countStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM (SELECT CAST(Tanggal AS DATE) AS Tanggal, Mesin $groupSql) x", $params);
    if ($countStmt && ($row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
        $totalFiltered = (int)($row['total'] ?? 0);
    } else {
        $totalFiltered = 0;
    }
    if ($countStmt) sqlsrv_free_stmt($countStmt);
}

$sql = "SELECT MIN(Id) AS Id,
            CAST(Tanggal AS DATE) AS Tanggal,
            Mesin,
            MAX(Petugas) AS Petugas,
            MAX([Shift]) AS ShiftName,
            MAX(CreatBy) AS CreatBy,
            MAX(CreatAt) AS CreatAt
        $groupSql
        ORDER BY CAST(Tanggal AS DATE) DESC, Mesin ASC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $params;
$dataParams[] = $start;
$dataParams[] = $length;

$stmt = sqlsrv_query($conn, $sql, $dataParams);
if ($stmt === false) {
    json_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Gagal mengambil data AC Weaving.']);
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggalKey = ac_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
    $mesinKey = $row['Mesin'] ?? '';
    $shiftsText = ac_weaving_shifts_for_sheet_query($conn, $tanggalKey, $mesinKey);
    $data[] = [
        'id' => $row['Id'] ?? 0,
        'mesin' => $mesinKey,
        'tanggal' => $tanggalKey,
        'tanggal_formatted' => ac_weaving_fmt_date($row['Tanggal'] ?? null, 'd/m/Y'),
        'petugas' => ac_weaving_petugas_for_sheet_query($conn, $tanggalKey, $mesinKey),
        'shift' => $shiftsText !== '' ? $shiftsText : ($row['ShiftName'] ?? ''),
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
