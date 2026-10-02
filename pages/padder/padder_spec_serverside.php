<?php
// padder_spec_serverside.php - Server-side processing untuk DataTables
require_once __DIR__ . '/../../koneksi.php';
session_start();

// Check permission
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = $_POST;

// Parameter dasar DataTables
$draw = $input['draw'] ?? 1;
$start = $input['start'] ?? 0;
$length = $input['length'] ?? 10;
$searchValue = $input['search_value'] ?? '';
$orderColumn = $input['order'][0]['column'] ?? 0;
$orderDir = $input['order'][0]['dir'] ?? 'asc';
$padderFilter = $input['padder_id'] ?? '';

// Debug logging (sementara untuk troubleshooting)
error_log("DataTables Request: " . print_r($input, true));

// Mapping kolom untuk ordering
$columnMap = [
    0 => 'ps.id',
    1 => 'p.padder_id', 
    2 => 'p.padder_name',
    3 => 'ps.spec_name',
    4 => 'ps.spec_value'
];
$orderBy = $columnMap[$orderColumn] ?? 'ps.id';

// Query dasar dengan JOIN
$sql = "SELECT 
            ps.id,
            p.padder_id,
            p.padder_name,
            ps.spec_name,
            ps.spec_value
        FROM dbo.pad_m_padder_spec ps
        INNER JOIN dbo.pad_m_padder p ON ps.padder_id = p.padder_id
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total 
             FROM dbo.pad_m_padder_spec ps
             INNER JOIN dbo.pad_m_padder p ON ps.padder_id = p.padder_id
             WHERE 1=1";
             
$params = [];
$countParams = [];

// Filter padder_id
if (!empty($padderFilter)) {
    $sql .= " AND ps.padder_id = ?";
    $countSql .= " AND ps.padder_id = ?";
    $params[] = $padderFilter;
    $countParams[] = $padderFilter;
}

// Filter pencarian
if (!empty($searchValue)) {
    $sql .= " AND (p.padder_id LIKE ? OR p.padder_name LIKE ? OR ps.spec_name LIKE ? OR ps.spec_value LIKE ?)";
    $countSql .= " AND (p.padder_id LIKE ? OR p.padder_name LIKE ? OR ps.spec_name LIKE ? OR ps.spec_value LIKE ?)";
    $searchParam = "%" . $searchValue . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

// Total records
$stmt = sqlsrv_query($conn, $countSql, $countParams);
$totalRecords = 0;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
} else {
    error_log("Count query failed: " . print_r(sqlsrv_errors(), true));
}
if ($stmt) sqlsrv_free_stmt($stmt);

// Filtered records count
$filteredRecords = $totalRecords;

// Ordering dan pagination
$sql .= " ORDER BY $orderBy $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = (int)$start;
$params[] = (int)$length;

// Debug query
error_log("SQL Query: " . $sql);
error_log("SQL Params: " . print_r($params, true));

// Eksekusi query utama
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'id' => $row['id'],
            'padder_id' => $row['padder_id'],
            'padder_name' => $row['padder_name'],
            'spec_name' => $row['spec_name'],
            'spec_value' => $row['spec_value']
        ];
    }
    sqlsrv_free_stmt($stmt);
} else {
    error_log("Main query failed: " . print_r(sqlsrv_errors(), true));
    $data = [];
}

// Response DataTables
$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($response);

// Debug output
error_log("Response: " . json_encode($response));
?>