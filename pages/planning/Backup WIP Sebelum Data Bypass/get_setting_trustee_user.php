<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    exit;
}

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Total Count
$sqlTotal = "SELECT COUNT(*) as total FROM planning_trustee_user_group";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalRecords = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$searchQuery = "";
if ($searchValue != '') {
    $searchQuery = " WHERE username LIKE '%$searchValue%' OR group_name LIKE '%$searchValue%' ";
}

$sqlData = "
SELECT id, username, group_name, created_by, created_at
FROM planning_trustee_user_group
$searchQuery
ORDER BY created_at DESC
OFFSET $start ROWS FETCH NEXT $length ROWS ONLY
";

$stmtData = sqlsrv_query($conn, $sqlData);
$data = [];
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        "username" => $row['username'],
        "group_name" => $row['group_name'],
        "created_by" => $row['created_by'],
        "created_at" => $row['created_at'] ? $row['created_at']->format('d M Y H:i') : '-',
        "actions" => '
            <div class="btn-group">
                <button class="btn btn-sm btn-info btn-edit-user" data-id="'.$row['id'].'" data-fullname="'.$row['username'].'" data-group="'.$row['group_name'].'"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger btn-delete-user" data-id="'.$row['id'].'" data-name="'.$row['username'].'"><i class="fas fa-trash"></i></button>
            </div>'
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalRecords),
    "data" => $data
]);
