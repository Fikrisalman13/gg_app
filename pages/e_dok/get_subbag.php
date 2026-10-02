<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$bagId = $_GET['bag_id'] ?? '';

header('Content-Type: application/json');

try {
    if (empty($bagId)) {
        // Jika tidak ada bag_id, return semua subbag
        $sql = "SELECT id_subbag, subbag FROM dbo.m_subbag ORDER BY subbag";
        $stmt = sqlsrv_query($conn, $sql);
    } else {
        $sql = "SELECT id_subbag, subbag FROM dbo.m_subbag WHERE id_bag = ? ORDER BY subbag";
        $params = [$bagId];
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