<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserId'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu!'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Permintaan tidak valid!'
    ]);
    exit;
}

// Get report ID and foto name
$reportId = $_POST['id'] ?? null;
$fotoName = $_POST['foto'] ?? '';

if (empty($reportId)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID laporan tidak valid!'
    ]);
    exit;
}

try {
    // Start transaction
    sqlsrv_begin_transaction($conn);
    
    // Delete the report
    $sql = "DELETE FROM dbo.report_pemadaman WHERE id = ?";
    $params = [$reportId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Gagal menghapus laporan: " . print_r(sqlsrv_errors(), true));
    }
    
    // Delete the photo if exists
    if (!empty($fotoName)) {
        $filePath = __DIR__ . '/../../uploads/pemadaman/' . $fotoName;
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception("Gagal menghapus file foto");
            }
        }
    }
    
    // Commit transaction
    sqlsrv_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Laporan pemadaman berhasil dihapus!'
    ]);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (sqlsrv_errors()) {
        sqlsrv_rollback($conn);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>