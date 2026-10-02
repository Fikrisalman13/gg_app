<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');
header('Content-Type: application/json');

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    echo json_encode([
        'draw' => (int)($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Anda tidak memiliki hak melihat data.',
    ]);
    exit;
}

$draw = (int)($_POST['draw'] ?? 1);
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
if ($length <= 0) {
    $length = 10;
}

$search = trim((string)($_POST['search']['value'] ?? ''));
$startDate = kwhl_normalize_date($_POST['start_date'] ?? '');
$endDate = kwhl_normalize_date($_POST['end_date'] ?? '');

if (!kwhl_table_exists($conn)) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia.',
    ]);
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Tabel kwh_listrik belum tersedia.',
    ]);
    exit;
}

$tableCols = kwhl_get_table_columns($conn);
$where = "WHERE 1=1";
$params = [];
if ($startDate !== '') {
    $where .= " AND CAST(tanggal AS DATE) >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where .= " AND CAST(tanggal AS DATE) <= ?";
    $params[] = $endDate;
}
if ($search !== '') {
    $sp = '%' . $search . '%';
    $searchParts = ["CONVERT(VARCHAR(10), tanggal, 120) LIKE ?"];
    $searchParams = [$sp];

    if (isset($tableCols['ket'])) {
        $searchParts[] = "COALESCE(CAST([ket] AS NVARCHAR(MAX)), '') LIKE ?";
        $searchParams[] = $sp;
    }
    if (isset($tableCols['updateby'])) {
        $searchParts[] = "COALESCE(CAST([updateby] AS NVARCHAR(MAX)), '') LIKE ?";
        $searchParams[] = $sp;
    }
    if (isset($tableCols['creatby'])) {
        $searchParts[] = "COALESCE(CAST([creatby] AS NVARCHAR(MAX)), '') LIKE ?";
        $searchParams[] = $sp;
    }
    if (isset($tableCols['created_by'])) {
        $searchParts[] = "COALESCE(CAST([created_by] AS NVARCHAR(MAX)), '') LIKE ?";
        $searchParams[] = $sp;
    }

    $where .= " AND (" . implode(' OR ', $searchParts) . ")";
    foreach ($searchParams as $p) {
        $params[] = $p;
    }
}

$countAllStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM {$tableName}");
$recordsTotal = 0;
if ($countAllStmt && ($r = sqlsrv_fetch_array($countAllStmt, SQLSRV_FETCH_ASSOC))) {
    $recordsTotal = (int)($r['total'] ?? 0);
}
if ($countAllStmt) {
    sqlsrv_free_stmt($countAllStmt);
}

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM {$tableName} {$where}", $params);
$recordsFiltered = 0;
if ($countFilteredStmt && ($r = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC))) {
    $recordsFiltered = (int)($r['total'] ?? 0);
}
if ($countFilteredStmt) {
    sqlsrv_free_stmt($countFilteredStmt);
}

$sql = "SELECT * FROM {$tableName} {$where}
        ORDER BY CAST(tanggal AS DATE) DESC, id DESC
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
        'error' => 'Gagal mengambil data.',
    ]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $mapped = kwhl_row_from_db($row);
    $mapped['tanggal_formatted'] = $mapped['tanggal'] ? date('d-m-Y', strtotime($mapped['tanggal'])) : '';
    $data[] = $mapped;
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
