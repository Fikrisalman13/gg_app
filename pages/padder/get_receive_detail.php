<?php
// get_receive_detail.php
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$id = $_POST['id'];

try {
    // Ambil data penerimaan dengan status pembelian
    $sql = "SELECT r.id, r.padder_id, r.receive_date, r.grn_number, r.remarks,
                   p.padder_name,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM dbo.pad_t_purchase pur 
                       WHERE pur.padder_id = r.padder_id
                   ) THEN 1 ELSE 0 END as has_purchase
            FROM dbo.pad_t_receive r
            LEFT JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
            WHERE r.id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    
    if ($stmt === false || !$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit;
    }
    
    // Format tanggal
    if ($row['receive_date']) {
        $row['receive_date_formatted'] = $row['receive_date']->format('d/m/Y');
    }
    
    // Ambil foto
    $photoSql = "SELECT file_path, file_type, uploaded_at 
                FROM dbo.pad_t_receive_files 
                WHERE receive_id = ? 
                ORDER BY uploaded_at";
    $photoStmt = sqlsrv_query($conn, $photoSql, [$id]);
    
    $photos = [];
    if ($photoStmt !== false) {
        while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
            $photos[] = $photo;
        }
        sqlsrv_free_stmt($photoStmt);
    }
    
    $row['photos'] = $photos;
    
    echo json_encode(['success' => true, 'data' => $row]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>