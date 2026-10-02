<?php
// pages/resep_obat/resep_role_manager_api.php
// CRUD API for resep_obat_groups & resep_obat_group_members
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user   = $_SESSION['UserName'];
$action = $_REQUEST['action'] ?? '';
$now    = date('Y-m-d H:i:s');

function safeStr($v) { return trim((string)($v ?? '')); }
function sqlErr($fallback = 'DB Error') {
    $e = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (is_array($e) && !empty($e)) {
        return $e[0]['message'] ?? $fallback;
    }
    return $fallback;
}

// =============================================
// GET: List groups (server-side DataTables)
// =============================================
if ($action === 'list_groups') {
    $draw   = (int)($_POST['draw'] ?? 1);
    $start  = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $search = safeStr($_POST['search']['value'] ?? '');

    $where  = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (g.group_name LIKE ? OR g.role_type LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $cntStmt = sqlsrv_query($conn, "SELECT COUNT(1) AS total FROM dbo.resep_obat_groups g $where", $params);
    $total   = 0;
    if ($cntStmt && $r = sqlsrv_fetch_array($cntStmt, SQLSRV_FETCH_ASSOC)) {
        $total = (int)$r['total'];
    }

    $sql = "SELECT g.id, g.group_name, g.role_type, g.experiment_view_scope,
                   (SELECT COUNT(1) FROM dbo.resep_obat_group_members WHERE group_id=g.id) AS member_count,
                   g.created_by, g.update_at, g.update_by
            FROM dbo.resep_obat_groups g
            $where
            ORDER BY g.id DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $params[] = $start;
    $params[] = $length;
    $stmt = sqlsrv_query($conn, $sql, $params);
    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ua = $row['update_at'];
            $data[] = [
                'id'           => $row['id'],
                'group_name'   => $row['group_name'],
                'role_type'    => $row['role_type'],
                'experiment_view_scope' => $row['experiment_view_scope'] ?? 'ALL',
                'member_count' => (int)$row['member_count'],
                'update_by'    => $row['update_by'],
                'update_at'    => ($ua instanceof DateTime) ? $ua->format('d-M-Y H:i') : ($ua ?? '-'),
            ];
        }
    }
    echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data]);
    exit;
}

// =============================================
// POST: Add group
// =============================================
if ($action === 'add_group') {
    $name = safeStr($_POST['group_name'] ?? '');
    $role = safeStr($_POST['role_type']  ?? '');
    $scope = safeStr($_POST['experiment_view_scope'] ?? 'ALL');
    if (!in_array($scope, ['ALL','APPROVED_ONLY','PROCESS_ONLY'], true)) $scope = 'ALL';
    if ($name === '' || !in_array($role, ['LAB','PRODUCTION','BOTH','KABAG','PPC','QC'], true)) {
        echo json_encode(['success' => false, 'message' => 'Nama grup dan tipe role wajib diisi.']); exit;
    }
    // Check duplicate name
    $chk = sqlsrv_query($conn, "SELECT 1 FROM dbo.resep_obat_groups WHERE LOWER(group_name)=LOWER(?)", [$name]);
    if ($chk && sqlsrv_fetch($chk)) {
        echo json_encode(['success' => false, 'message' => 'Nama grup sudah ada.']); exit;
    }
    $stmt = sqlsrv_query($conn,
        "INSERT INTO dbo.resep_obat_groups (group_name, role_type, experiment_view_scope, created_at, created_by, update_at, update_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$name, $role, $scope, $now, $user, $now, $user]
    );
    if ($stmt === false) { echo json_encode(['success' => false, 'message' => sqlErr()]); exit; }
    echo json_encode(['success' => true, 'message' => 'Grup berhasil ditambahkan.']);
    exit;
}

// =============================================
// POST: Edit group
// =============================================
if ($action === 'edit_group') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = safeStr($_POST['group_name'] ?? '');
    $role = safeStr($_POST['role_type']  ?? '');
    $scope = safeStr($_POST['experiment_view_scope'] ?? 'ALL');
    if (!in_array($scope, ['ALL','APPROVED_ONLY','PROCESS_ONLY'], true)) $scope = 'ALL';
    if ($id <= 0 || $name === '' || !in_array($role, ['LAB','PRODUCTION','BOTH','KABAG','PPC','QC'], true)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']); exit;
    }
    $chk = sqlsrv_query($conn, "SELECT 1 FROM dbo.resep_obat_groups WHERE LOWER(group_name)=LOWER(?) AND id<>?", [$name, $id]);
    if ($chk && sqlsrv_fetch($chk)) {
        echo json_encode(['success' => false, 'message' => 'Nama grup sudah digunakan grup lain.']); exit;
    }
    $stmt = sqlsrv_query($conn,
        "UPDATE dbo.resep_obat_groups SET group_name=?, role_type=?, experiment_view_scope=?, update_at=?, update_by=? WHERE id=?",
        [$name, $role, $scope, $now, $user, $id]
    );
    if ($stmt === false) { echo json_encode(['success' => false, 'message' => sqlErr()]); exit; }
    echo json_encode(['success' => true, 'message' => 'Grup berhasil diperbarui.']);
    exit;
}

// =============================================
// POST: Delete group
// =============================================
if ($action === 'delete_group') {
    $id = (int)($_POST['id'] ?? 0);
    // Check members
    $chk = sqlsrv_query($conn, "SELECT COUNT(1) AS c FROM dbo.resep_obat_group_members WHERE group_id=?", [$id]);
    if ($chk && ($r = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC)) && (int)$r['c'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Hapus semua anggota terlebih dahulu.']); exit;
    }
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_groups WHERE id=?", [$id]);
    if ($stmt === false) { echo json_encode(['success' => false, 'message' => sqlErr()]); exit; }
    echo json_encode(['success' => true, 'message' => 'Grup berhasil dihapus.']);
    exit;
}

// =============================================
// GET: List members of a group
// =============================================
if ($action === 'list_members') {
    $group_id = (int)($_GET['group_id'] ?? 0);
    $stmt = sqlsrv_query($conn,
        "SELECT m.id, m.username, m.created_by, m.created_at,
                COALESCE(e.nama_lengkap, '') AS nama_lengkap
         FROM dbo.resep_obat_group_members m
         LEFT JOIN dbo.SMUserMs u ON m.username = u.UserName
         LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
         WHERE m.group_id = ?
         ORDER BY m.username ASC",
        [$group_id]
    );
    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ca = $row['created_at'];
            $data[] = [
                'id'           => $row['id'],
                'username'     => $row['username'],
                'nama_lengkap' => $row['nama_lengkap'],
                'created_by'   => $row['created_by'],
                'created_at'   => ($ca instanceof DateTime) ? $ca->format('d-M-Y H:i') : ($ca ?? '-'),
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// =============================================
// GET: Search users from SMUserMs (for Select2)
// =============================================
if ($action === 'search_users') {
    $q = safeStr($_GET['q'] ?? '');
    $sql = "SELECT TOP 50 u.UserName,
                COALESCE(e.nama_lengkap, u.UserName) AS display_name
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            WHERE u.UserName LIKE ? OR e.nama_lengkap LIKE ?
            ORDER BY u.UserName ASC";
    $stmt = sqlsrv_query($conn, $sql, ["%$q%", "%$q%"]);
    $results = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = [
                'id'   => $row['UserName'],
                'text' => $row['display_name'] . ' (' . $row['UserName'] . ')',
            ];
        }
    }
    echo json_encode(['results' => $results]);
    exit;
}

// =============================================
// POST: Add member to group
// =============================================
if ($action === 'add_member') {
    $group_id = (int)($_POST['group_id'] ?? 0);
    $username = safeStr($_POST['username'] ?? '');
    if ($group_id <= 0 || $username === '') {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']); exit;
    }
    // Check if user already in any group
    $chk = sqlsrv_query($conn,
        "SELECT g.group_name FROM dbo.resep_obat_group_members m
         INNER JOIN dbo.resep_obat_groups g ON m.group_id=g.id
         WHERE m.username=?",
        [$username]
    );
    if ($chk && ($r = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'message' => "User '$username' sudah terdaftar di grup: " . $r['group_name']]); exit;
    }
    $stmt = sqlsrv_query($conn,
        "INSERT INTO dbo.resep_obat_group_members (group_id, username, created_at, created_by, update_at, update_by)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$group_id, $username, $now, $user, $now, $user]
    );
    if ($stmt === false) { echo json_encode(['success' => false, 'message' => sqlErr()]); exit; }
    echo json_encode(['success' => true, 'message' => "User '$username' berhasil ditambahkan."]);
    exit;
}

// =============================================
// POST: Remove member
// =============================================
if ($action === 'remove_member') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_group_members WHERE id=?", [$id]);
    if ($stmt === false) { echo json_encode(['success' => false, 'message' => sqlErr()]); exit; }
    echo json_encode(['success' => true, 'message' => 'Anggota berhasil dihapus.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
