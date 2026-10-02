<?php
// get_log_detail.php - Get Detail Log untuk Modal
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Check permission
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $logId = $_POST['id'] ?? 0;
    if (!$logId) {
        throw new Exception("ID Log tidak valid!");
    }

    // Get log detail
    $sql = "SELECT 
                l.*,
                p.padder_name,
                p.status as current_status,
                FORMAT(l.changed_at, 'dd/MM/yyyy HH:mm:ss') as changed_at_formatted
            FROM dbo.pad_status_log l
            INNER JOIN dbo.pad_m_padder p ON l.padder_id = p.padder_id
            WHERE l.id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [$logId]);
    
    if (!$stmt || !$logData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        throw new Exception("Data log tidak ditemukan!");
    }
    
    sqlsrv_free_stmt($stmt);

    // Prepare response
    $response = [
        'success' => true,
        'data' => $logData
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_log_detail.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>