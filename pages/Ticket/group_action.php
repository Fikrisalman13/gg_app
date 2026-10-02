<?php
// ===================================================
// 1. INISIALISASI REQUEST
// ===================================================
@session_start(); // Suppress warning if session already started
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success' => false, 'message' => ''];

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserId'])) {
    $resp['message'] = 'Unauthorized';
    echo json_encode($resp);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // ===================================================
    // 3. HANDLER SETIAP AKSI GRUP
    // ===================================================
    if ($action === 'add_group') {
        $name = trim($_POST['group_name'] ?? '');
        if ($name === '') throw new Exception('Nama grup tidak boleh kosong');
        
        $sql = "INSERT INTO dbo.ticket_tech_groups (group_name) VALUES (?)";
        $stmt = sqlsrv_query($conn, $sql, [$name]);
        if ($stmt === false) throw new Exception('Gagal membuat grup');
        
        $resp['success'] = true;
        $resp['message'] = 'Grup berhasil dibuat';
        
    } elseif ($action === 'edit_group') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['group_name'] ?? '');
        if ($id <= 0 || $name === '') throw new Exception('Data tidak valid');
        
        $sql = "UPDATE dbo.ticket_tech_groups SET group_name = ? WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$name, $id]);
        if ($stmt === false) throw new Exception('Gagal update grup');
        
        $resp['success'] = true;
        $resp['message'] = 'Grup berhasil diupdate';
        
    } elseif ($action === 'delete_group') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID tidak valid');
        
        $sql = "DELETE FROM dbo.ticket_tech_groups WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt === false) throw new Exception('Gagal menghapus grup');
        
        $resp['success'] = true;
        $resp['message'] = 'Grup berhasil dihapus';
        
    } elseif ($action === 'add_member') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $empId = intval($_POST['id_emp'] ?? 0);
        
        if ($groupId <= 0 || $empId <= 0) throw new Exception('Data tidak valid');
        
        // Ensure technician is not already assigned to any group
        $check = sqlsrv_query($conn, "SELECT TOP 1 tm.group_id, g.group_name FROM dbo.ticket_tech_members tm LEFT JOIN dbo.ticket_tech_groups g ON tm.group_id = g.id WHERE tm.id_emp = ?", [$empId]);
        if ($check && ($row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC))) {
            $existingGroupId = intval($row['group_id'] ?? 0);
            $existingGroupName = trim($row['group_name'] ?? 'lain');
            if ($existingGroupId === $groupId) {
                throw new Exception('Teknisi sudah ada di grup ini');
            }
            throw new Exception('Teknisi sudah terdaftar di grup ' . $existingGroupName);
        }
        if ($check) { sqlsrv_free_stmt($check); }

        $sql = "INSERT INTO dbo.ticket_tech_members (group_id, id_emp) VALUES (?, ?)";
        $stmt = sqlsrv_query($conn, $sql, [$groupId, $empId]);
        if ($stmt === false) throw new Exception('Gagal menambahkan anggota');
        
        $resp['success'] = true;
        $resp['message'] = 'Anggota berhasil ditambahkan';
        
    } elseif ($action === 'remove_member') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID tidak valid');
        
        $sql = "DELETE FROM dbo.ticket_tech_members WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt === false) throw new Exception('Gagal menghapus anggota');
        
        $resp['success'] = true;
        $resp['message'] = 'Anggota berhasil dihapus';
        
    } elseif ($action === 'get_members') {
        $groupId = intval($_POST['group_id'] ?? 0);
        
        $sql = "SELECT tm.id, tm.id_emp, e.nama_lengkap, j.jabatan 
                FROM dbo.ticket_tech_members tm
                JOIN dbo.m_emp e ON tm.id_emp = e.id_emp
                LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
                WHERE tm.group_id = ?
                ORDER BY e.nama_lengkap";
        $stmt = sqlsrv_query($conn, $sql, [$groupId]);
        $members = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $members[] = $row;
        }
        
        $resp['success'] = true;
        $resp['data'] = $members;
        
    } else {
        throw new Exception('Action tidak dikenal');
    }
} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
