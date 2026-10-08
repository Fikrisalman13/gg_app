<?php
// pages/resep_obat/master_limit_warna/save_limit.php
session_start();
require_once '../../../../koneksi.php';

$response = ['status' => 'error', 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'add';
    $id = $_POST['id'] ?? null;
    $kode_warna = trim($_POST['kode_warna'] ?? '');
    
    // Helper function to parse limit values: "-" means unlimited (NULL), empty means 0, otherwise parse as float
    function parseLimitValue($value) {
        $value = trim($value);
        if ($value === '-') {
            return null; // Unlimited
        }
        if (empty($value)) {
            return 0;
        }
        // Remove thousand separator (dot) before parsing
        $value = str_replace('.', '', $value);
        return floatval($value);
    }
    
    // Parse limit values
    $max_cost = parseLimitValue($_POST['max_cost'] ?? '');
    $max_cf_disperse = parseLimitValue($_POST['max_cf_disperse'] ?? '');
    $max_cf_reactive = parseLimitValue($_POST['max_cf_reactive'] ?? '');
    $max_cf_total = parseLimitValue($_POST['max_cf_total'] ?? '');
    
    $username = $_SESSION['username'] ?? 'System'; // Adjust based on session key
    
    if (empty($kode_warna)) {
        echo json_encode(['status' => 'error', 'message' => 'Kode Warna Wajib Diisi!']);
        exit;
    }

    if ($mode === 'add') {
        // Check Duplicate
        $check = sqlsrv_query($conn, "SELECT COUNT(*) as cnt FROM resep_limit_color WHERE kode_warna = ?", [$kode_warna]);
        $row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        if ($row['cnt'] > 0) {
             echo json_encode(['status' => 'error', 'message' => 'Kode Warna sudah terdaftar! Gunakan Edit.']);
             exit;
        }

        $sql = "INSERT INTO resep_limit_color (kode_warna, max_cost, max_cf_disperse, max_cf_reactive, max_cf_total, created_by, updated_by, modifiedStr) 
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())"; // Using modifiedStr/updated_at depending on schema. Setup used updated_at.
        
        // Let's stick to Schema: created_at, created_by, updated_at, updated_by
        $sql = "INSERT INTO resep_limit_color (kode_warna, max_cost, max_cf_disperse, max_cf_reactive, max_cf_total, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                
        $params = [$kode_warna, $max_cost, $max_cf_disperse, $max_cf_reactive, $max_cf_total, $username];
    
    } else { // Edit
        $sql = "UPDATE resep_limit_color 
                SET max_cost = ?, max_cf_disperse = ?, max_cf_reactive = ?, max_cf_total = ?, updated_by = ?, updated_at = GETDATE()
                WHERE id = ?";
        $params = [$max_cost, $max_cf_disperse, $max_cf_reactive, $max_cf_total, $username, $id];
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $response = ['status' => 'success', 'message' => 'Data berhasil disimpan.'];
    } else {
        $errors = sqlsrv_errors();
        $response = ['status' => 'error', 'message' => 'Database Error: ' . ($errors[0]['message'] ?? 'Unknown')];
    }
}

echo json_encode($response);
?>
