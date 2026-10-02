<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success' => false, 'data' => []];

if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode($resp);
    exit;
}

try {
    // ===================================================
    // 2. PENGAMBILAN DATA TEKNISI IT
    // ===================================================
    $availableOnly = isset($_GET['available_only']) && intval($_GET['available_only']) === 1;

    $sql = "SELECT e.id_emp, e.nama_lengkap, j.jabatan, 
                                         g.group_name
                    FROM dbo.m_emp e
                    LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
                    LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
                    LEFT JOIN dbo.ticket_tech_members tm ON e.id_emp = tm.id_emp
                    LEFT JOIN dbo.ticket_tech_groups g ON tm.group_id = g.id
                    WHERE d.dept = 'Information Technology'
                        AND e.aktif = 1";

    if ($availableOnly) {
            $sql .= " AND tm.id_emp IS NULL";
    }

    $sql .= " ORDER BY e.nama_lengkap";
    
    $stmt = sqlsrv_query($conn, $sql);
    $data = [];
    
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'id_emp' => $row['id_emp'],
            'nama_lengkap' => $row['nama_lengkap'],
            'jabatan' => $row['jabatan'] ?? null,
            'group_name' => $row['group_name'] ?? null
        ];
    }
    
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    $resp['success'] = true;
    $resp['data'] = $data;
} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
