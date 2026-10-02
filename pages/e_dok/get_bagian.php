<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$deptId = $_GET['dept_id'] ?? '';

header('Content-Type: application/json');

try {
    if (empty($deptId)) {
        // Jika tidak ada dept_id, return semua bagian
        $sql = "SELECT id_bag, bagian FROM dbo.m_bag ORDER BY bagian";
        $stmt = sqlsrv_query($conn, $sql);
    } else {
        $sql = "SELECT id_bag, bagian FROM dbo.m_bag WHERE id_dept = ? ORDER BY bagian";
        $params = [$deptId];
        $stmt = sqlsrv_query($conn, $sql, $params);
    }

    $data = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>