<?php
session_start();
include '../../koneksi.php';

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

$columns = [0 => 'username', 1 => 'group_name', 2 => 'created_by', 3 => 'created_at'];
$orderCol = $columns[$_POST['order'][0]['column'] ?? 3] ?? 'created_at';
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';

$sqlBase = "
FROM planning_user_group p
LEFT JOIN SMUserMs u ON p.username = u.UserName
LEFT JOIN m_emp m ON u.EmpId = m.id_emp
";
$params = [];
$where = " WHERE p.username != 'System' "; // dummy true

if ($searchValue !== '') {
    $where .= " AND (p.username LIKE ? OR p.group_name LIKE ? OR m.nama_lengkap LIKE ?)";
    $params = ["%$searchValue%", "%$searchValue%", "%$searchValue%"];
}

$stmtTot = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM planning_user_group");
$totRec = ($stmtTot && $row = sqlsrv_fetch_array($stmtTot)) ? $row['t'] : 0;

$stmtFilt = sqlsrv_query($conn, "SELECT COUNT(*) as t $sqlBase $where", $params);
$totFilt = ($stmtFilt && $row = sqlsrv_fetch_array($stmtFilt)) ? $row['t'] : 0;

$sqlData = "SELECT p.id, p.username, p.group_name, p.created_at, p.created_by, m.nama_lengkap $sqlBase $where ORDER BY $orderCol $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = (int)$start;
$params[] = (int)$length;
$stmtData = sqlsrv_query($conn, $sqlData, $params);

$data = [];
if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $fullName = $row['nama_lengkap'] ?? $row['username'];
        
        $btnEdit = "<button class='btn btn-xs btn-info btn-edit-user' 
                        data-id='{$row['id']}' 
                        data-username='".htmlspecialchars($row['username'])."' 
                        data-fullname='".htmlspecialchars($fullName)."'
                        data-group='".htmlspecialchars($row['group_name'])."' 
                        title='Ubah Grup Pelaksana'><i class='fas fa-edit'></i></button>";
                        
        $btnDel  = "<button class='btn btn-xs btn-danger btn-delete-user' 
                        data-id='{$row['id']}' 
                        data-name='".htmlspecialchars($fullName)."' 
                        title='Putus Akses Grup'><i class='fas fa-trash-alt'></i></button>";

        $data[] = [
            'username'   => '<span class="font-weight-bold" style="color: #1e293b;">' . htmlspecialchars($fullName) . '</span><br><small class="text-muted">Username: ' . htmlspecialchars($row['username']) . '</small>',
            'group_name' => '<span class="badge badge-info px-2 py-1"><i class="fas fa-layer-group mr-1"></i> ' . htmlspecialchars($row['group_name']) . '</span>',
            'created_by' => htmlspecialchars($row['created_by'] ?? 'Sistem'),
            'created_at' => $row['created_at'] ? $row['created_at']->format('d/m/Y H:i') : '-',
            'actions'    => "<div class='text-nowrap'>$btnEdit $btnDel</div>"
        ];
    }
}
echo json_encode(['draw' => intval($draw), 'recordsTotal' => intval($totRec), 'recordsFiltered' => intval($totFilt), 'data' => $data]);
?>
