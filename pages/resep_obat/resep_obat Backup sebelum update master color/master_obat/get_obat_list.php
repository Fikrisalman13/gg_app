<?php
// pages/master_obat/get_obat_list.php
session_start();
date_default_timezone_set('Asia/Jakarta');
ob_start(); // Start buffering to catch any stray whitespaces/warnings
require_once __DIR__ . '/../../../koneksi.php';

// DataTables Parameters
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$draw = $_GET['draw'] ?? 1;
$search = $_GET['search']['value'] ?? '';
$order_col_idx = $_GET['order'][0]['column'] ?? 0;
$order_dir = $_GET['order'][0]['dir'] ?? 'asc';

$columns = ['kode_obat', 'codeprod_proint', 'nama_obat', 'group_obat', 'uom', 'id'];

// Default ordering if not specified by DataTables
if (empty($_GET['order'])) {
    $order_col = 'id'; // Default to ID
    $order_dir = 'desc'; // Default to DESC
} else {
    $order_col_idx = $_GET['order'][0]['column'] ?? 0;
    $order_dir = $_GET['order'][0]['dir'] ?? 'desc'; // Default to desc if missing
    $order_col = $columns[$order_col_idx] ?? 'id';
}

// Base Query
$sql = "SELECT COUNT(*) as total FROM dbo.resep_master_obat";
$stmt = sqlsrv_query($conn, $sql);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$totalRecords = $row['total'];

// Filtered Query
$sql = "SELECT * FROM dbo.resep_master_obat WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $sql .= " AND (kode_obat LIKE ? OR nama_obat LIKE ? OR codeprod_proint LIKE ? OR group_obat LIKE ? OR uom LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
}

// Get Total Filtered
$stmt = sqlsrv_query($conn, str_replace("SELECT *", "SELECT COUNT(*) as total", $sql), $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$totalFiltered = $row['total'];

// Pagination & Order
$start = intval($start);
$length = intval($length);

$sql .= " ORDER BY $order_col $order_dir";

// Handle Pagination (SQL Server 2012+)
if ($length != -1) {
    $sql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $params[] = $start;
    $params[] = $length;
} else {
    // If length is -1 (All), we still need OFFSET if we want to skip (though usually Start is 0 for All)
    // But usually -1 means "All from start".
    // If we want to support OFFSET with All, it's tricky without FETCH.
    // Simpler: Just don't apply paging limit if -1, but apply offset if needed?
    // DataTables 'All' usually sends start=0, length=-1.
    // So just string concatenation for OFFSET if needed?
    // Actually valid SQL: ORDER BY ... OFFSET 0 ROWS. (Without FETCH is valid).
    // Let's just Apply OFFSET if start > 0.
    if ($start > 0) {
        $sql .= " OFFSET ? ROWS";
        $params[] = $start;
    }
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    ob_end_clean(); // Clean any previous output
    echo json_encode(['error' => print_r(sqlsrv_errors(), true)]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = $row;
}

$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
];

// Clear buffer and output JSON
ob_end_clean();
echo json_encode($response);
exit;
?>
