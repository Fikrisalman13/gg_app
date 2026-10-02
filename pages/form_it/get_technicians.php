<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Ambil daftar teknisi IT aktif (e.aktif = 1) beserta ticket tech group (Infra & Support, ERP & Software, dll)
    $sql = "SELECT DISTINCT e.id_emp, e.nama_lengkap, j.jabatan, g.group_name,
                   COALESCE(NULLIF(u.Theme, ''), 'primary') AS theme
            FROM dbo.m_emp e
            LEFT JOIN dbo.SMUserMs u ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
            LEFT JOIN dbo.ticket_tech_members tm ON e.id_emp = tm.id_emp
            LEFT JOIN dbo.ticket_tech_groups g ON tm.group_id = g.id
            WHERE (d.dept LIKE '%Information Technology%' OR d.dept = 'IT' OR u.GroupId = 1)
              AND e.aktif = 1
            ORDER BY e.nama_lengkap ASC";
    
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        throw new Exception('Gagal memuat daftar teknisi IT');
    }

    $technicians = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $technicians[] = [
            'id_emp' => (int)$row['id_emp'],
            'nama_lengkap' => $row['nama_lengkap'],
            'jabatan' => $row['jabatan'] ?? null,
            'group_name' => $row['group_name'] ?? null,
            'theme' => $row['theme']
        ];
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'data' => $technicians]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
