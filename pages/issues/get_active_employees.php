<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT m.id_emp, m.nama_lengkap, m.nik,
                   j.jabatan, d.dept, b.bagian
            FROM dbo.m_emp m
            LEFT JOIN dbo.m_jab j ON m.id_jab = j.id_jab
            LEFT JOIN dbo.m_subbag sb ON m.id_subbag = sb.id_subbag
            LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
            LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
            WHERE ISNULL(m.aktif, 0) = 1
            ORDER BY m.nama_lengkap";

    $stmt = sqlsrv_query($conn, $sql);
    $employees = [];

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $jabatan = $row['jabatan'] ?? 'N/A';
            $dept = $row['dept'] ?? '';
            $bagian = $row['bagian'] ?? '';
            
            // Build display text
            $displayParts = [$row['nama_lengkap']];
            if ($dept) {
                $displayParts[] = $dept;
            }
            
            $employees[] = [
                'id' => $row['id_emp'],
                'text' => implode(' - ', $displayParts),
                'nama_lengkap' => $row['nama_lengkap'],
                'nik' => $row['nik'],
                'jabatan' => $jabatan,
                'dept' => $dept,
                'bagian' => $bagian
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    echo json_encode(['success' => true, 'employees' => $employees]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

sqlsrv_close($conn);
?>
