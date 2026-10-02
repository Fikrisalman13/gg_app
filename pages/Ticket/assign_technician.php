<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

$resp = ['success' => false, 'message' => ''];

if (!isset($_SESSION['UserId']) || !$conn) {
    $resp['message'] = 'Silakan login';
    echo json_encode($resp);
    exit;
}

try {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    
    if ($ticket_id <= 0) throw new Exception('Invalid Ticket ID');
    if ($assigned_to <= 0) throw new Exception('Invalid Technician ID');
    
    // ===================================================
    // 2. UPDATE INFORMASI PENUGASAN TICKET
    // ===================================================
    $sql = "UPDATE dbo.tickets 
            SET assigned_to = ?, 
                assigned_at = GETDATE(), 
                assigned_by = ?,
                updated_at = GETDATE(),
                updated_by = ?
            WHERE ticket_id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [$assigned_to, $_SESSION['UserId'], $_SESSION['UserId'], $ticket_id]);
    
    if ($stmt === false) {
        throw new Exception('Gagal assign teknisi');
    }
    
    // ===================================================
    // 3. AMBIL NAMA TEKNISI (RESPON)
    // ===================================================
    $sqlTech = "SELECT e.nama_lengkap, COALESCE(NULLIF(u.Theme, ''), 'secondary') AS theme
                FROM dbo.m_emp e
                LEFT JOIN dbo.SMUserMs u ON u.EmpId = e.id_emp
                WHERE e.id_emp = ?";
    $stmtTech = sqlsrv_query($conn, $sqlTech, [$assigned_to]);
    $techName = '';
    $techTheme = 'secondary';
    if ($stmtTech && $row = sqlsrv_fetch_array($stmtTech, SQLSRV_FETCH_ASSOC)) {
        $techName = $row['nama_lengkap'];
        $techTheme = ticket_normalize_theme($row['theme'] ?? 'secondary');
    }
    
    $resp['success'] = true;
    $resp['message'] = 'Teknisi berhasil di-assign';
    $resp['technician_name'] = $techName;
    $resp['technician_theme'] = $techTheme;
} catch (Exception $e) {
    $resp['success'] = false;
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
