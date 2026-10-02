<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_cacat'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id_cacat = (int)$_POST['id_cacat'];

try {
    // Hapus cacat
    $stmt = sqlsrv_query($conn, "
        DELETE FROM cl_cutting_cacat
        WHERE id_cacat = ?
    ", [$id_cacat]);

    if (!$stmt) {
        throw new Exception('Gagal hapus cacat: ' . print_r(sqlsrv_errors(), true));
    }

    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
