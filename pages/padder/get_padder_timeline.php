<?php
// get_padder_timeline.php - Get Timeline untuk Padder Tertentu
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Check permission
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $padderId = $_POST['padder_id'] ?? '';
    if (!$padderId) {
        throw new Exception("Padder ID tidak valid!");
    }

    // Get timeline for this padder
    $sql = "SELECT 
                status,
                changed_at,
                FORMAT(changed_at, 'dd/MM/yyyy HH:mm') as changed_at_formatted,
                changed_by,
                remarks
            FROM dbo.pad_status_log
            WHERE padder_id = ?
            ORDER BY changed_at DESC";
    
    $stmt = sqlsrv_query($conn, $sql, [$padderId]);
    
    $timeline = [];
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $timeline[] = $row;
    }
    
    if ($stmt) sqlsrv_free_stmt($stmt);

    // Prepare response
    $response = [
        'success' => true,
        'data' => $timeline
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_padder_timeline.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>