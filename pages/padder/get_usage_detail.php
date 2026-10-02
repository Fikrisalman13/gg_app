<?php
// get_usage_detail.php - Ambil detail pemakaian
session_start();
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $sql = "SELECT 
                u.id,
                u.used_date,
                u.location,
                u.machine_name,
                u.remarks,
                u.padder_id,
                p.padder_name,
                p.status as padder_status
            FROM dbo.pad_t_usage u
            INNER JOIN dbo.pad_m_padder p ON u.padder_id = p.padder_id
            WHERE u.id = ?";
    
    $params = [$id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data']);
        exit;
    }

    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit;
    }

    // Format tanggal
    if ($data['used_date'] instanceof DateTime) {
        $data['used_date_formatted'] = $data['used_date']->format('d-m-Y');
    } else {
        $data['used_date_formatted'] = $data['used_date'];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>