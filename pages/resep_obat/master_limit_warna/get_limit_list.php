<?php
// pages/resep_obat/master_limit_warna/get_limit_list.php
session_start();
// ob_start(); // Prevent extra output
require_once '../../../koneksi.php';

// DataTables Parameters
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$draw = $_GET['draw'] ?? 1;
$search = $_GET['search']['value'] ?? '';
$order_col_idx = $_GET['order'][0]['column'] ?? 0;
$order_dir = $_GET['order'][0]['dir'] ?? 'desc';

// Columns mapping for Ordering
// Index must match the columns in JS
// 0: kode_warna, 1: max_cost, 2: max_cf_disperse, 3: max_cf_reactive, 4: max_cf_total, 5: updated_at, 6: updated_by, 7: id
$columns = [
    0 => 'kode_warna',
    1 => 'max_cost',
    2 => 'max_cf_disperse',
    3 => 'max_cf_reactive',
    4 => 'max_cf_total',
    5 => 'updated_at',
    6 => 'updated_by',
    7 => 'id'
];

$order_col = $columns[$order_col_idx] ?? 'updated_at';

// Base Query
$sqlTotal = "SELECT COUNT(*) as total FROM resep_limit_color";
$stmt = sqlsrv_query($conn, $sqlTotal);
$totalRecords = 0;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}

// Filtered Query
$sql = "SELECT * FROM resep_limit_color WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $sql .= " AND (kode_warna LIKE ? OR updated_by LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term];
}

// Get Total Filtered
$sqlCount = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
$stmt = sqlsrv_query($conn, $sqlCount, $params);
$totalFiltered = 0;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalFiltered = $row['total'];
}

// Order & Pagination
// SQL Server >= 2012 uses OFFSET FETCH
$sql .= " ORDER BY $order_col $order_dir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = intval($start);
$params[] = intval($length);

$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Formatting Updated At
        if ($row['updated_at'] instanceof DateTime) {
            $row['updated_at'] = $row['updated_at']->format('Y-m-d H:i');
        } elseif (!empty($row['updated_at'])) {
            $row['updated_at'] = date('Y-m-d H:i', strtotime($row['updated_at']));
        } else {
            // Fallback to Created At if Updated At is null
             if ($row['created_at'] instanceof DateTime) {
                $row['updated_at'] = $row['created_at']->format('Y-m-d H:i') . ' (New)';
            }
        }

        $data[] = $row;
    }
} else {
    // Debug SQL error if needed, but in JSON response usually just empty
    // die(print_r(sqlsrv_errors(), true));
}

$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
];

// ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>
