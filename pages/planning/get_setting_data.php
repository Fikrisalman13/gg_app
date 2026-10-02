<?php
session_start();
include '../../koneksi.php';

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Columns for Ordering
$columns = [
    0 => 'username',
    1 => 'rtg_name',
    2 => 'created_by',
    3 => 'created_at',
];

$orderColIndex = $_POST['order'][0]['column'] ?? 3;
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';
$orderCol = $columns[$orderColIndex] ?? 'created_at';

// Base Query
$sqlBase = "FROM planning_setting";
$params = [];
$where = "";

if ($searchValue !== '') {
    $where = " WHERE username LIKE ? OR rtg_name LIKE ?";
    $params = ["%$searchValue%", "%$searchValue%"];
}

// Total records without filtering
$sqlTotal = "SELECT COUNT(*) as total FROM planning_setting";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalRecords = 0;
if ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}

// Total records with filtering
$sqlFilterTotal = "SELECT COUNT(*) as filterTotal $sqlBase $where";
$stmtFilterTotal = sqlsrv_query($conn, $sqlFilterTotal, $params);
$totalFiltered = 0;
if ($stmtFilterTotal && $row = sqlsrv_fetch_array($stmtFilterTotal, SQLSRV_FETCH_ASSOC)) {
    $totalFiltered = $row['filterTotal'];
}

// Fetch Data with Pagination setup
$sqlData = "
    SELECT 
        id, username, rtg_name, created_at, created_by 
    $sqlBase
    $where
    ORDER BY $orderCol $orderDir
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$params[] = (int)$start;
$params[] = (int)$length;

$stmtData = sqlsrv_query($conn, $sqlData, $params);

$data = [];
if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $dateStr = $row['created_at'] ? $row['created_at']->format('d/m/Y H:i') : '-';
        $byStr   = $row['created_by'] ?? 'Sistem';

        $btnHapus = "<button class='btn btn-xs btn-danger btn-delete' data-id='{$row['id']}' data-name='{$row['username']}' title='Hapus Akses'><i class='fas fa-trash-alt'></i></button>";

        $data[] = [
            'username'   => htmlspecialchars($row['username']),
            'rtg_name'   => '<span class="badge badge-info px-2 py-1"><i class="fas fa-route mr-1"></i> ' . htmlspecialchars($row['rtg_name']) . '</span>',
            'created_by' => htmlspecialchars($byStr),
            'created_at' => $dateStr,
            'actions'    => $btnHapus
        ];
    }
}

echo json_encode([
    'draw'            => intval($draw),
    'recordsTotal'    => intval($totalRecords),
    'recordsFiltered' => intval($totalFiltered),
    'data'            => $data
]);
?>
