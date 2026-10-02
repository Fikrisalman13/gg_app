<?php
session_start();
header('Content-Type: application/json');

// ======================================================
// 1. VALIDASI SESSION LOGIN
// ======================================================
if (!isset($_SESSION['UserName'])) {
    echo json_encode([
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Session expired. Silakan login kembali.'
    ]);
    exit;
}

$loginGroupId = $_SESSION['GroupId'] ?? null;

// ======================================================
// 2. KONEKSI DATABASE
// ======================================================
include '../koneksi.php';

if (!$conn) {
    error_log("Database connection failed on hakaksesgroup_ajax");
    echo json_encode([
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Koneksi database gagal.'
    ]);
    exit;
}

// ======================================================
// 3. KONSTAN MENU ID
// ======================================================
define('MENU_GROUP_ACCESS', 4);

// ======================================================
// 4. FUNCTION: CEK PERMISSION
// ======================================================
function getGroupPermissions($conn, $groupId, $menuId)
{
    $sql = "
        SELECT CanView, CanAdd, CanEdit, CanDelete
        FROM dbo.SMGroupTrustee
        WHERE GroupId = ? AND MenuId = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    if ($stmt === false) {
        return ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    }

    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return $data ?: ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
}

// ======================================================
// 5. FUNCTION FORMATTER UI (HTML)
// ======================================================
function formatGroupName($val) {
    return htmlspecialchars($val);
}

function formatGroupDesc($val) {
    $val = htmlspecialchars($val);
    return "<span class=\"group-desc\" title=\"$val\">$val</span>";
}

function formatMenuName($menuName, $parentMenuName = null) {
    $formattedName = htmlspecialchars($menuName);
    
    if ($parentMenuName) {
        $parentName = htmlspecialchars($parentMenuName);
        return "<div class='menu-with-parent'>
                    <div class='parent-menu text-muted small'>
                        <i class='fas fa-folder mr-1'></i>$parentName
                    </div>
                    <div class='sub-menu'>
                        <i class='fas fa-level-down-alt mr-1'></i>$formattedName
                    </div>
                </div>";
    }
    
    return "<div class='main-menu'>
                <i class='fas fa-folder-open mr-1 text-warning'></i>$formattedName
            </div>";
}

function permissionIcon($val) {
    return '<i class="fas fa-' . ($val ? 'check-circle permission-yes' : 'times-circle permission-no') 
        . ' permission-icon"></i>';
}

function actionButtons($trusteeId, $groupName, $menuName, $perm)
{
    $btns = [];

    // Tombol Edit
    if ($perm['CanEdit']) {
        $btns[] = '<a href="edit_hakaksesgroup.php?id=' . intval($trusteeId) . '"
                    class="btn btn-warning btn-sm mr-1"
                    title="Edit Hak Akses">
                        <i class="fas fa-edit"></i>
                   </a>';
    }

    // Tombol Hapus
    if ($perm['CanDelete']) {
        $btns[] = '<button class="btn btn-danger btn-sm btn-delete-hakakses"                         
                         data-trustee-id="' . intval($trusteeId) . '"
                         data-group-name="' . htmlspecialchars($groupName) . '"
                         data-menu-name="' . htmlspecialchars($menuName) . '"
                         title="Hapus Hak Akses">
                         <i class="fas fa-trash"></i>
                    </button>';
    }

    return implode("", $btns);
}

// ======================================================
// 6. CEK HAK AKSES VIEW
// ======================================================
$permissions = getGroupPermissions($conn, $loginGroupId, MENU_GROUP_ACCESS);

if (!$permissions['CanView']) {
    echo json_encode([
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Anda tidak memiliki hak untuk melihat data ini.'
    ]);
    exit;
}

try {

    // ======================================================
    // 7. PARAMETER DATATABLES
    // ======================================================
    $draw   = intval($_GET['draw'] ?? 1);
    $start  = intval($_GET['start'] ?? 0);
    $length = intval($_GET['length'] ?? 10);

    $searchValue = trim($_GET['search']['value'] ?? '');

    $orderColIndex = intval($_GET['order'][0]['column'] ?? 1);
    $orderDir = strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    // Mapping kolom
    $columns = [
        0 => 'a.TrusteeId',
        1 => 'b.GroupName',
        2 => 'b.GroupDesc',
        3 => 'c.MenuName',
        4 => 'a.CanView',
        5 => 'a.CanAdd',
        6 => 'a.CanEdit',
        7 => 'a.CanDelete'
    ];

    $orderBy = isset($columns[$orderColIndex]) 
                ? "ORDER BY {$columns[$orderColIndex]} $orderDir"
                : "ORDER BY b.GroupName ASC, parent.MenuName ASC, c.MenuName ASC";

    // ======================================================
    // 8. BASE QUERY (TERMASUK SUBMENU)
    // ======================================================
    $baseQuery = "
        FROM dbo.SMGroupTrustee a
        LEFT JOIN dbo.SMUserGroup b ON a.GroupId = b.GroupId
        LEFT JOIN dbo.SMMenu c ON a.MenuId = c.MenuId
        LEFT JOIN dbo.SMMenu parent ON c.ParentMenuId = parent.MenuId
    ";

    // WHERE clause
    $where = "";
    $params = [];

    if ($searchValue !== "") {
        $where = "WHERE (b.GroupName LIKE ? OR b.GroupDesc LIKE ? OR c.MenuName LIKE ? OR parent.MenuName LIKE ?)";
        $searchParam = "%" . $searchValue . "%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }

    // ======================================================
    // 9. HITUNG TOTAL DATA
    // ======================================================
    $sqlTotal = "SELECT COUNT(*) AS total $baseQuery";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal);

    $recordsTotal = ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC))
                    ? $row['total']
                    : 0;

    sqlsrv_free_stmt($stmtTotal);

    // ======================================================
    // 10. HITUNG DATA SESUAI FILTER
    // ======================================================
    $sqlFiltered = "SELECT COUNT(*) AS total $baseQuery $where";
    $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);

    $recordsFiltered = ($stmtFiltered && $row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC))
                        ? $row['total']
                        : 0;

    sqlsrv_free_stmt($stmtFiltered);

    // ======================================================
    // 11. QUERY DATA UTAMA (DENGAN INFORMASI PARENT MENU)
    // ======================================================
    $sqlMain = "
        SELECT 
            a.TrusteeId,
            b.GroupName,
            b.GroupDesc,
            c.MenuName,
            c.ParentMenuId,
            parent.MenuName AS ParentMenuName,
            a.CanView,
            a.CanAdd,
            a.CanEdit,
            a.CanDelete
        $baseQuery
        $where
        $orderBy
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
    ";

    $mainParams = array_merge($params, [$start, $length]);

    $stmt = sqlsrv_query($conn, $sqlMain, $mainParams);

    if ($stmt === false) {
        throw new Exception("Query gagal: " . print_r(sqlsrv_errors(), true));
    }

    $rows = [];

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);

    // ======================================================
    // 12. FORMAT DATA UNTUK DATATABLES
    // ======================================================
    $data = [];

    foreach ($rows as $i => $r) {
        $parentMenuName = !empty($r['ParentMenuId']) ? $r['ParentMenuName'] : null;
        
        $data[] = [
            $start + $i + 1,
            formatGroupName($r['GroupName']),
            formatGroupDesc($r['GroupDesc']),
            formatMenuName($r['MenuName'], $parentMenuName),
            permissionIcon($r['CanView']),
            permissionIcon($r['CanAdd']),
            permissionIcon($r['CanEdit']),
            permissionIcon($r['CanDelete']),
            actionButtons($r['TrusteeId'], $r['GroupName'], $r['MenuName'], $permissions)
        ];
    }

    // ======================================================
    // 13. KIRIM JSON RESPONSE
    // ======================================================
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);

} catch (Exception $e) {

    error_log("Error hakaksesgroup_ajax.php: " . $e->getMessage());

    echo json_encode([
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Kesalahan server: ' . $e->getMessage()
    ]);
}

exit;