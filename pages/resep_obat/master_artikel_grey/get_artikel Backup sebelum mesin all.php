<?php
// pages/resep_obat/master_artikel_grey/get_artikel.php
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: application/json');

// Columns mapping
$columns = [
    0 => 'id', // No (index)
    1 => 'kode_gray',
    2 => 'nama_artikel',
    3 => 'gramasi',
    4 => 'pickup',
    5 => 'padry'
];

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$searchValue = $_POST['search']['value'] ?? '';

// Order
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'ASC';
$orderBy = $columns[$orderColumnIndex] ?? 'id';

// Base Query
$sqlBase = "FROM dbo.master_artikel_grey";
$where = " WHERE 1=1";
$params = [];

if (!empty($searchValue)) {
    $where .= " AND (kode_gray LIKE ? OR nama_artikel LIKE ? OR padry LIKE ?)";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
}

// Total Records
$sqlTotal = "SELECT COUNT(*) as count " . $sqlBase;
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalRecords = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)['count'];

// Filtered Records
$sqlFiltered = "SELECT COUNT(*) as count " . $sqlBase . $where;
$stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
$totalFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)['count'];

// Data Query
$sqlData = "SELECT * " . $sqlBase . $where . " ORDER BY $orderBy $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $start;
$params[] = $length;

$stmtData = sqlsrv_query($conn, $sqlData, $params);
$data = [];

$no = $start + 1;
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
    // Format dates
    $created_at = $row['created_at'] ? $row['created_at']->format('Y-m-d H:i:s') : '-';
    $updated_at = $row['updated_at'] ? $row['updated_at']->format('Y-m-d H:i:s') : '-';
    
    $data[] = [
        "no" => $no++,
        "kode_gray" => $row['kode_gray'],
        "nama_artikel" => $row['nama_artikel'],
        "gramasi" => number_format($row['gramasi'], 2, ',', '.'),
        "pickup" => number_format($row['pickup'], 2, ',', '.'),
        "padry" => $row['padry'],
        "created_at" => $created_at,
        "created_by" => $row['created_by'],
        "updated_at" => $updated_at,
        "updated_by" => $row['updated_by'],
        "id" => $row['id'] // for actions
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
?>
