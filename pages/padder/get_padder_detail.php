<?php
// get_padder_detail.php - Ambil detail padder untuk form pemakaian
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Ambil padder_id
$padder_id = $_POST['padder_id'] ?? '';

if (empty($padder_id)) {
    echo json_encode(['success' => false, 'message' => 'Padder ID tidak valid']);
    exit;
}

try {
    // Query untuk mengambil data padder utama
    $sql = "SELECT 
                padder_id,
                padder_name,
                status,
                remarks,
                created_at,
                updated_at
            FROM dbo.pad_m_padder 
            WHERE padder_id = ?";
    
    $params = [$padder_id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Error: " . print_r($errors, true));
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data padder']);
        exit;
    }

    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Padder tidak ditemukan']);
        exit;
    }

    // Format tanggal
    if ($data['updated_at'] instanceof DateTime) {
        $data['updated_at_formatted'] = $data['updated_at']->format('d-m-Y H:i');
    } else {
        $data['updated_at_formatted'] = '-';
    }

    if ($data['created_at'] instanceof DateTime) {
        $data['created_at_formatted'] = $data['created_at']->format('d-m-Y H:i');
    } else {
        $data['created_at_formatted'] = '-';
    }

    // Ambil spesifikasi padder
    $specSql = "SELECT 
                    spec_name, 
                    spec_value 
                FROM dbo.pad_m_padder_spec 
                WHERE padder_id = ? 
                ORDER BY id";
    
    $specStmt = sqlsrv_query($conn, $specSql, [$padder_id]);
    $specifications = [];
    
    if ($specStmt !== false) {
        while ($spec = sqlsrv_fetch_array($specStmt, SQLSRV_FETCH_ASSOC)) {
            $specifications[] = [
                'spec_name' => $spec['spec_name'] ?? '',
                'spec_value' => $spec['spec_value'] ?? ''
            ];
        }
        sqlsrv_free_stmt($specStmt);
    }

    // Siapkan response data
    $response_data = [
        'padder_id' => $data['padder_id'] ?? '',
        'padder_name' => $data['padder_name'] ?? '',
        'status' => $data['status'] ?? '',
        'remarks' => $data['remarks'] ?? '',
        'updated_at_formatted' => $data['updated_at_formatted'] ?? '-',
        'created_at_formatted' => $data['created_at_formatted'] ?? '-',
        'specifications' => $specifications
    ];

    echo json_encode([
        'success' => true, 
        'data' => $response_data
    ]);

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

// Tutup statement
if (isset($stmt) && $stmt) {
    sqlsrv_free_stmt($stmt);
}
?>