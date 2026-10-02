<?php
// pages/resep_obat/save_config_pk.php
require_once '../../koneksi.php';

session_start();
$user = $_SESSION['UserName'] ?? 'SYSTEM';
$now = date('Y-m-d H:i:s');

header('Content-Type: application/json');

$pk = $_POST['panjang_kain'] ?? '';
$val = floatval($pk);

if ($val <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nilai tidak valid']);
    exit;
}

try {
    // Assuming single config row logic like limits
    // First, check if row exists
    $sqlCheck = "SELECT count(*) as cnt FROM dbo.resep_config";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    
    if ($row['cnt'] == 0) {
        $sql = "INSERT INTO dbo.resep_config (panjang_kain, updated_at, updated_by) VALUES (?, ?, ?)";
    } else {
        // Update the top 1 (or all, since usually config is singleton)
        // Using "UPDATE TOP (1)" isn't standard SQL Server t-sql for UPDATE without FROM usually, 
        // but just UPDATE resep_config is fine if we assume 1 row.
        // Or update by id if we knew it? Let's just update all rows (singleton pattern).
        $sql = "UPDATE dbo.resep_config SET panjang_kain = ?, updated_at = ?, updated_by = ?";
    }
    
    $params = [$val, $now, $user];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Konfigurasi disimpan']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
