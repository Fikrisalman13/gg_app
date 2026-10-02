<?php
// get_scrap_detail.php - Get Detail Scrap untuk Modal
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Check permission
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $scrapId = $_POST['id'] ?? 0;
    if (!$scrapId) {
        throw new Exception("ID Scrap tidak valid!");
    }

    // Get scrap detail
    $sql = "SELECT 
                s.*,
                p.padder_name,
                p.status as padder_status,
                FORMAT(s.scrap_date, 'dd/MM/yyyy') as scrap_date_formatted
            FROM dbo.pad_t_scrap s
            INNER JOIN dbo.pad_m_padder p ON s.padder_id = p.padder_id
            WHERE s.id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [$scrapId]);
    
    if (!$stmt || !$scrapData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        throw new Exception("Data scrap tidak ditemukan!");
    }
    
    sqlsrv_free_stmt($stmt);

    // Get scrap photos
    $photoSql = "SELECT 
                    id, file_path, file_type, uploaded_at,
                    FORMAT(uploaded_at, 'dd/MM/yyyy HH:mm') as uploaded_at_formatted
                 FROM dbo.pad_t_scrap_files 
                 WHERE scrap_id = ?
                 ORDER BY uploaded_at";
    
    $photoStmt = sqlsrv_query($conn, $photoSql, [$scrapId]);
    $photos = [];
    
    while ($photoStmt && $photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
        $photos[] = $photo;
    }
    
    if ($photoStmt) sqlsrv_free_stmt($photoStmt);

    // Prepare response
    $response = [
        'success' => true,
        'data' => array_merge($scrapData, ['photos' => $photos])
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_scrap_detail.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>