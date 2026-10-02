<?php
// purchase_serverside.php - Server-side processing untuk DataTables
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
$padderFilter = $input['padder_id'] ?? '';
$vendorFilter = $input['vendor_id'] ?? '';
$startDate = $input['start_date'] ?? '';
$endDate = $input['end_date'] ?? '';
$orderColumn = $input['order'][0]['column'] ?? 0;
$orderDir = $input['order'][0]['dir'] ?? 'asc';

// Mapping kolom untuk ordering
$columnMap = [
    0 => 'p.id',
    1 => 'p.purchase_date', 
    2 => 'p.padder_id',
    3 => 'pad.padder_name',
    4 => 'p.po_number',
    5 => 'v.vendor_name',
    6 => 'p.price',
    7 => 'p.remarks'
];
$orderBy = $columnMap[$orderColumn] ?? 'p.purchase_date DESC';

// Query dasar dengan JOIN
$sql = "SELECT 
            p.id,
            p.purchase_date,
            p.padder_id,
            pad.padder_name,
            p.po_number,
            v.vendor_name,
            p.price,
            p.remarks
        FROM dbo.pad_t_purchase p
        INNER JOIN dbo.pad_m_padder pad ON p.padder_id = pad.padder_id
        LEFT JOIN dbo.pad_m_vendor v ON p.vendor_id = v.vendor_id
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total 
             FROM dbo.pad_t_purchase p
             INNER JOIN dbo.pad_m_padder pad ON p.padder_id = pad.padder_id
             LEFT JOIN dbo.pad_m_vendor v ON p.vendor_id = v.vendor_id
             WHERE 1=1";
             
$params = [];
$countParams = [];

// Filter padder_id
if (!empty($padderFilter)) {
    $sql .= " AND p.padder_id = ?";
    $countSql .= " AND p.padder_id = ?";
    $params[] = $padderFilter;
    $countParams[] = $padderFilter;
}

// Filter vendor_id
if (!empty($vendorFilter)) {
    $sql .= " AND p.vendor_id = ?";
    $countSql .= " AND p.vendor_id = ?";
    $params[] = $vendorFilter;
    $countParams[] = $vendorFilter;
}

// Filter tanggal
if (!empty($startDate)) {
    $sql .= " AND p.purchase_date >= ?";
    $countSql .= " AND p.purchase_date >= ?";
    $params[] = $startDate;
    $countParams[] = $startDate;
}

if (!empty($endDate)) {
    $sql .= " AND p.purchase_date <= ?";
    $countSql .= " AND p.purchase_date <= ?";
    $params[] = $endDate;
    $countParams[] = $endDate;
}

// Filter pencarian
if (!empty($searchValue)) {
    $sql .= " AND (p.padder_id LIKE ? OR pad.padder_name LIKE ? OR p.po_number LIKE ? OR v.vendor_name LIKE ? OR p.remarks LIKE ?)";
    $countSql .= " AND (p.padder_id LIKE ? OR pad.padder_name LIKE ? OR p.po_number LIKE ? OR v.vendor_name LIKE ? OR p.remarks LIKE ?)";
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
$sql .= " ORDER BY $orderBy OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = (int)$start;
$params[] = (int)$length;

// Eksekusi query utama
$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format purchase_date
        $purchaseDate = '';
        if ($row['purchase_date'] instanceof DateTime) {
            $purchaseDate = $row['purchase_date']->format('Y-m-d');
        } elseif (is_string($row['purchase_date'])) {
            $purchaseDate = $row['purchase_date'];
        }
        
        $data[] = [
            'id' => $row['id'],
            'purchase_date' => $purchaseDate,
            'padder_id' => $row['padder_id'],
            'padder_name' => $row['padder_name'],
            'po_number' => $row['po_number'],
            'vendor_name' => $row['vendor_name'],
            'price' => $row['price'],
            'remarks' => $row['remarks']
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