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
$sqlTotal = "SELECT COUNT(DISTINCT group_name) as total FROM planning_trustee_group";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalRecords = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$searchQuery = "";
if ($searchValue != '') {
    $searchQuery = " WHERE group_name LIKE '%$searchValue%' ";
}

$sqlData = "
SELECT group_name, COUNT(*) as rtg_count, MAX(created_by) as created_by, MAX(created_at) as created_at
FROM planning_trustee_group
$searchQuery
GROUP BY group_name
ORDER BY group_name
OFFSET $start ROWS FETCH NEXT $length ROWS ONLY
";

$stmtData = sqlsrv_query($conn, $sqlData);
$data = [];
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
    $groupName = $row['group_name'];
    
    // Fetch individual routings for this group to pass to JS
    $sqlRtg = "SELECT id, rtgcode, rtgname, rtgmsid FROM planning_trustee_group WHERE group_name = ?";
    $stmtRtg = sqlsrv_query($conn, $sqlRtg, [$groupName]);
    $routings = [];
    while($r = sqlsrv_fetch_array($stmtRtg, SQLSRV_FETCH_ASSOC)) {
        $routings[] = $r;
    }
    
    $jsonRoutings = htmlspecialchars(json_encode($routings), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        "group_name" => $row['group_name'],
        "rtg_count" => '<span class="badge badge-info cursor-pointer btn-view-group" data-group="'.$groupName.'" data-routings=\''.$jsonRoutings.'\' style="font-size: 0.9rem; padding: 5px 12px; border-radius: 50px;">'.$row['rtg_count'].' Routing</span>',
        "created_by" => $row['created_by'],
        "created_at" => $row['created_at'] ? $row['created_at']->format('d M Y H:i') : '-',
        "actions" => '
            <div class="btn-group">
                <button class="btn btn-sm btn-info btn-edit-group" data-group="'.$groupName.'" data-routings=\''.$jsonRoutings.'\'><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger btn-delete-fullgroup" data-group="'.$groupName.'"><i class="fas fa-trash"></i></button>
            </div>'
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalRecords),
    "data" => $data
]);
