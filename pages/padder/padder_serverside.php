<?php
// padder_serverside.php - Server-side processing untuk DataTables
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

// Mapping kolom untuk ordering
$columnMap = [
    0 => 'padder_id',
    1 => 'padder_name', 
    2 => 'status',
    3 => 'remarks',
    4 => 'created_at',
    5 => 'created_by'
];
$orderBy = $columnMap[$orderColumn] ?? 'padder_id';

// Query dasar
$sql = "SELECT 
            padder_id,
            padder_name,
            status,
            remarks,
            created_at,
            created_by
        FROM dbo.pad_m_padder 
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total FROM dbo.pad_m_padder WHERE 1=1";
$params = [];
$countParams = [];

// Filter pencarian
if (!empty($searchValue)) {
    $sql .= " AND (padder_id LIKE ? OR padder_name LIKE ? OR status LIKE ? OR remarks LIKE ?)";
    $countSql .= " AND (padder_id LIKE ? OR padder_name LIKE ? OR status LIKE ? OR remarks LIKE ?)";
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
}
if ($stmt) sqlsrv_free_stmt($stmt);

// Filtered records count
$filteredRecords = $totalRecords;

// Ordering dan pagination
$sql .= " ORDER BY $orderBy $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = (int)$start;
$params[] = (int)$length;

// Eksekusi query utama
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format created_at
        $createdAt = '-';
        if ($row['created_at'] instanceof DateTime) {
            $createdAt = $row['created_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['created_at'])) {
            $createdAt = $row['created_at'];
        }
        
        $data[] = [
            'padder_id' => $row['padder_id'],
            'padder_name' => $row['padder_name'],
            'status' => $row['status'],
            'remarks' => $row['remarks'] ?? '-',
            'created_at' => $createdAt,
            'created_by' => $row['created_by'] ?? '-',
            'aksi' => $row['padder_id'] // ID untuk action buttons
        ];
    }
    sqlsrv_free_stmt($stmt);
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
?>