<?php
// vendor_serverside.php - Server-side processing untuk DataTables
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
$vendorFilter = $input['search_vendor'] ?? '';
$orderColumn = $input['order'][0]['column'] ?? 0;
$orderDir = $input['order'][0]['dir'] ?? 'asc';

// Mapping kolom untuk ordering
$columnMap = [
    0 => 'vendor_id',
    1 => 'vendor_name', 
    2 => 'address',
    3 => 'contact_person',
    4 => 'phone',
    5 => 'email',
    6 => 'created_at'
];
$orderBy = $columnMap[$orderColumn] ?? 'vendor_id';

// Query dasar
$sql = "SELECT 
            vendor_id,
            vendor_name,
            address,
            contact_person,
            phone,
            email,
            created_at
        FROM dbo.pad_m_vendor 
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total FROM dbo.pad_m_vendor WHERE 1=1";
$params = [];
$countParams = [];

// Filter pencarian vendor
if (!empty($vendorFilter)) {
    $sql .= " AND (vendor_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $countSql .= " AND (vendor_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $vendorParam = "%" . $vendorFilter . "%";
    $params[] = $vendorParam;
    $params[] = $vendorParam;
    $params[] = $vendorParam;
    $params[] = $vendorParam;
    $countParams[] = $vendorParam;
    $countParams[] = $vendorParam;
    $countParams[] = $vendorParam;
    $countParams[] = $vendorParam;
}

// Filter pencarian global DataTables
if (!empty($searchValue)) {
    $sql .= " AND (vendor_name LIKE ? OR address LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $countSql .= " AND (vendor_name LIKE ? OR address LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchParam = "%" . $searchValue . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $countParams[] = $searchParam;
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

// Eksekusi query utama
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format created_at
        $createdAt = '';
        if ($row['created_at'] instanceof DateTime) {
            $createdAt = $row['created_at']->format('Y-m-d H:i:s');
        } elseif (is_string($row['created_at'])) {
            $createdAt = $row['created_at'];
        }
        
        $data[] = [
            'vendor_id' => $row['vendor_id'],
            'vendor_name' => $row['vendor_name'],
            'address' => $row['address'],
            'contact_person' => $row['contact_person'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'created_at' => $createdAt
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
?>