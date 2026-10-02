<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'success' => false,
    'message' => 'Unknown error'
];

if (!isset($_SESSION['UserName'])) {
    $response['message'] = "Silakan login terlebih dahulu";
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = $_POST['template_id'] ?? null;
    $namaLengkap = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
    
    try {
        if (empty($template_id)) {
            throw new Exception("ID Template tidak valid.");
        }
        
        // Hanya bisa hapus template milik sendiri
        $sql = "DELETE FROM dbo.issues_template 
                WHERE template_id = ? AND created_by = ?";
        $params = [$template_id, $namaLengkap];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Database error: " . print_r(sqlsrv_errors(), true));
        }
        
        $rowsAffected = sqlsrv_rows_affected($stmt);
        
        if ($rowsAffected > 0) {
            $response['success'] = true;
            $response['message'] = "Template berhasil dihapus.";
        } else {
            throw new Exception("Template tidak ditemukan atau Anda tidak memiliki akses untuk menghapusnya.");
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
sqlsrv_close($conn);
?>
