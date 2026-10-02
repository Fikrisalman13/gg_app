<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_piece'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id_piece = (int)$_POST['id_piece'];

try {
    sqlsrv_begin_transaction($conn);

    // Hapus cacat terkait piece dulu
    $stmtCacat = sqlsrv_query($conn, "
        DELETE FROM cl_cutting_cacat
        WHERE id_piece = ?
    ", [$id_piece]);

    if (!$stmtCacat) {
        throw new Exception('Gagal hapus cacat: ' . print_r(sqlsrv_errors(), true));
    }

    // Hapus piece
    $stmtPiece = sqlsrv_query($conn, "
        DELETE FROM cl_cutting_piece
        WHERE id_piece = ?
    ", [$id_piece]);

    if (!$stmtPiece) {
        throw new Exception('Gagal hapus piece: ' . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
