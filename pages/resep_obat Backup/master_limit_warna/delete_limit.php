<?php
// pages/resep_obat/master_limit_warna/delete_limit.php
require_once '../../../koneksi.php';

$response = ['status' => 'error', 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        $sql = "DELETE FROM resep_limit_color WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        
        if ($stmt) {
            $response = ['status' => 'success', 'message' => 'Data berhasil dihapus.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Gagal menghapus data.'];
        }
    }
}
echo json_encode($response);
?>
