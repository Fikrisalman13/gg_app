<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/kwh_listrik_common.php');

header('Content-Type: application/json');

if (!isset($_POST['tanggal'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak dikirim.']);
    exit;
}

$tanggal = kwhl_normalize_date($_POST['tanggal']);
if ($tanggal === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$tableName = kwhl_table_full_name($conn);
if (!$tableName) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tabel KWH Listrik tidak ditemukan.']);
    exit;
}

try {
    $sql = "SELECT TOP 1 kwh_hari_ini_21t FROM " . $tableName . " WHERE CAST(tanggal AS DATE) = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tanggal]);
    
    if ($stmt === false) {
        throw new Exception("Query error: " . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    if ($row && $row['kwh_hari_ini_21t'] !== null) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => ['kwh_hari_ini_21t' => (float)$row['kwh_hari_ini_21t']]
        ]);
    } else {
        http_response_code(200);
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
