<?php
session_start();
include '../../koneksi.php';

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Karena kita menggunakan GROUP BY, maka kolom filter dan ordering berfokus pada group_name
$orderColIndex = $_POST['order'][0]['column'] ?? 0;
// Kolom index: 0=group_name, 1=count, 2=created_by, 3=created_at, 4=aksi
$columnsMap = [0 => 'group_name', 2 => 'created_by', 3 => 'created_at'];
$orderCol = $columnsMap[$orderColIndex] ?? 'group_name';
if ($orderCol === 'created_at') {
    $orderCol = 'MAX(created_at)';
} elseif ($orderCol === 'created_by') {
    $orderCol = 'MAX(created_by)';
}

$orderDir = $_POST['order'][0]['dir'] ?? 'asc';

$sqlBase = "FROM planning_group_rtg";
$where = "";
$params = [];

if ($searchValue !== '') {
    $where = " WHERE group_name LIKE ? ";
    $params = ["%$searchValue%"];
}

// Total records 
$stmtTot = sqlsrv_query($conn, "SELECT COUNT(DISTINCT group_name) as t FROM planning_group_rtg");
$totRec = ($stmtTot && $row = sqlsrv_fetch_array($stmtTot)) ? $row['t'] : 0;

// Total Filtered
$stmtFilt = sqlsrv_query($conn, "SELECT COUNT(DISTINCT group_name) as t $sqlBase $where", $params);
$totFilt = ($stmtFilt && $row = sqlsrv_fetch_array($stmtFilt)) ? $row['t'] : 0;

// Fetch Groups with Pagination
$sqlGroups = "
    SELECT group_name, COUNT(id) as jml_routing, MAX(created_at) as created_at, MAX(created_by) as created_by 
    $sqlBase 
    $where 
    GROUP BY group_name
    ORDER BY $orderCol $orderDir
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$params[] = (int)$start;
$params[] = (int)$length;
$stmtGroups = sqlsrv_query($conn, $sqlGroups, $params);

$groupsList = [];
$groupNames = [];
if ($stmtGroups) {
    while ($row = sqlsrv_fetch_array($stmtGroups, SQLSRV_FETCH_ASSOC)) {
        $g = $row['group_name'];
        $groupNames[] = $g;
        $groupsList[$g] = [
            'group_name'  => $g,
            'jml_routing' => $row['jml_routing'],
            'created_by'  => $row['created_by'],
            'created_at'  => $row['created_at'] ? $row['created_at']->format('d/m/Y H:i') : '-',
            'routings'    => []
        ];
    }
}

// Fetch Routings only for the loaded groups
if (count($groupNames) > 0) {
    $inPlaceholders = implode(',', array_fill(0, count($groupNames), '?'));
    $sqlRoutings = "SELECT id, group_name, rtg_name FROM planning_group_rtg WHERE group_name IN ($inPlaceholders)";
    $stmtRtg = sqlsrv_query($conn, $sqlRoutings, $groupNames);
    if ($stmtRtg) {
        while ($r = sqlsrv_fetch_array($stmtRtg, SQLSRV_FETCH_ASSOC)) {
            $groupsList[$r['group_name']]['routings'][] = [
                'id' => $r['id'],
                'name' => htmlspecialchars($r['rtg_name'])
            ];
        }
    }
}

$data = [];
foreach ($groupsList as $g) {
    $jsonRoutings = htmlspecialchars(json_encode($g['routings']), ENT_QUOTES, 'UTF-8');
    
    $btnLihat = "<button class='btn btn-xs btn-primary btn-view-group' data-group='".htmlspecialchars($g['group_name'])."' data-routings='".$jsonRoutings."'><i class='fas fa-eye mr-1'></i> Lihat ({$g['jml_routing']} Data)</button>";
    $btnEditGroup = "<button class='btn btn-xs btn-info btn-edit-group' data-group='".htmlspecialchars($g['group_name'])."' data-routings='".$jsonRoutings."' title='Ubah Grup & Anggota'><i class='fas fa-edit'></i></button>";
    $btnHapusGroup = "<button class='btn btn-xs btn-danger btn-delete-fullgroup' data-group='".htmlspecialchars($g['group_name'])."' title='Hapus Seluruh Grup'><i class='fas fa-trash-alt'></i></button>";

    $data[] = [
        'group_name' => htmlspecialchars($g['group_name']),
        'rtg_name'   => $btnLihat,
        'created_by' => htmlspecialchars($g['created_by'] ?? 'Sistem'),
        'created_at' => $g['created_at'],
        'actions'    => "<div class='text-nowrap'>$btnEditGroup $btnHapusGroup</div>"
    ];
}

echo json_encode(['draw' => intval($draw), 'recordsTotal' => intval($totRec), 'recordsFiltered' => intval($totFilt), 'data' => $data]);
?>
