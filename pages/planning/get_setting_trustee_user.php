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
$sqlTotal = "SELECT COUNT(DISTINCT username) as total FROM planning_trustee_user_group";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalRecords = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)['total'];

// Filtered Count
$recordsFiltered = $totalRecords;
$searchQuery = "";
if ($searchValue != '') {
    $searchQuery = " 
        WHERE t1.username LIKE '%$searchValue%' 
        OR t1.group_name LIKE '%$searchValue%' 
        OR m.nama_lengkap LIKE '%$searchValue%' 
    ";
    $sqlFilter = "
        SELECT COUNT(DISTINCT t1.username) as total 
        FROM planning_trustee_user_group t1
        LEFT JOIN SMUserMs u ON t1.username = u.UserName
        LEFT JOIN m_emp m ON u.EmpId = m.id_emp
        $searchQuery";
    $stmtFilter = sqlsrv_query($conn, $sqlFilter);
    $recordsFiltered = sqlsrv_fetch_array($stmtFilter, SQLSRV_FETCH_ASSOC)['total'];
}

$sqlData = "
SELECT 
    t1.username, 
    MAX(m.nama_lengkap) as full_name,
    STUFF((
        SELECT '|' + CAST(id AS VARCHAR(10)) + ':' + group_name
        FROM planning_trustee_user_group t2
        WHERE t2.username = t1.username
        FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '') as group_info,
    MAX(t1.created_by) as created_by,
    MAX(t1.created_at) as latest_created_at
FROM planning_trustee_user_group t1
LEFT JOIN SMUserMs u ON t1.username = u.UserName
LEFT JOIN m_emp m ON u.EmpId = m.id_emp
$searchQuery
GROUP BY t1.username
ORDER BY latest_created_at DESC
OFFSET $start ROWS FETCH NEXT $length ROWS ONLY
";

$stmtData = sqlsrv_query($conn, $sqlData);
$data = [];
if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $group_badges = '';
        if (!empty($row['group_info'])) {
            $groups = explode('|', $row['group_info']);
            foreach ($groups as $g) {
                $parts = explode(':', $g);
                $id = $parts[0];
                $name = $parts[1] ?? 'Unknown';
                $group_badges .= '
                    <span class="badge badge-primary p-2 mr-1 mb-1" style="font-size: 0.85rem; border-radius: 6px;">
                        '.$name.'
                        <i class="fas fa-times-circle ml-1 text-white-50 btn-delete-user" style="cursor:pointer;" data-id="'.$id.'" data-name="'.$row['username'].'" title="Hapus Akses"></i>
                    </span>';
            }
        }

        $displayName = '<div><div class="font-weight-bold text-dark">'.($row['full_name'] ?? 'Tidak Diketahui').'</div><small class="text-danger">'.$row['username'].'</small></div>';

        $data[] = [
            "username" => $displayName,
            "group_name" => $group_badges,
            "created_by" => $row['created_by'],
            "created_at" => $row['latest_created_at'] ? $row['latest_created_at']->format('d M Y H:i') : '-',
            "actions" => '
                <div class="btn-group">
                    <button class="btn btn-sm btn-danger btn-delete-full-user" data-username="'.$row['username'].'" title="Hapus Semua Akses"><i class="fas fa-user-slash"></i></button>
                </div>'
        ];
    }
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
]);
