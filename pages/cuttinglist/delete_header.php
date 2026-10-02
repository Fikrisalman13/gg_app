<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No IDs provided']);
    exit;
}

try {
    sqlsrv_begin_transaction($conn);
    $deleted_count = 0;

    foreach ($ids as $id_header) {
        $id_header = intval($id_header);

        // Check if already processed - cannot delete processed data
        $checkStmt = sqlsrv_query($conn, "
            SELECT processed FROM cl_cutting_header WHERE id_header = ?
        ", [$id_header]);

        if (!$checkStmt) {
            throw new Exception('Gagal cek status: ' . print_r(sqlsrv_errors(), true));
        }

        $header = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        if (!$header) {
            continue; // Skip jika tidak ditemukan
        }

        if ($header['processed'] == 1) {
            throw new Exception('Tidak bisa menghapus data yang sudah di-process');
        }

        // Get all piece IDs untuk header ini
        $piecesStmt = sqlsrv_query($conn, "
            SELECT id_piece FROM cl_cutting_piece WHERE id_header = ?
        ", [$id_header]);

        if (!$piecesStmt) {
            throw new Exception('Gagal ambil piece: ' . print_r(sqlsrv_errors(), true));
        }

        // Hapus semua cacat untuk piece-piece ini
        while ($piece = sqlsrv_fetch_array($piecesStmt, SQLSRV_FETCH_ASSOC)) {
            $id_piece = $piece['id_piece'];

            // Hapus dari cl_cutting_cacat
            $deleteCacatStmt = sqlsrv_query($conn, "
                DELETE FROM cl_cutting_cacat WHERE id_piece = ?
            ", [$id_piece]);

            if (!$deleteCacatStmt) {
                throw new Exception('Gagal hapus cacat: ' . print_r(sqlsrv_errors(), true));
            }

            // Hapus dari cl_cutting_process
            $deleteProcessStmt = sqlsrv_query($conn, "
                DELETE FROM cl_cutting_process WHERE id_piece = ?
            ", [$id_piece]);

            if (!$deleteProcessStmt) {
                throw new Exception('Gagal hapus process: ' . print_r(sqlsrv_errors(), true));
            }

            // Hapus dari cl_cutting_summary
            $deleteSummaryStmt = sqlsrv_query($conn, "
                DELETE FROM cl_cutting_summary WHERE id_piece = ?
            ", [$id_piece]);

            if (!$deleteSummaryStmt) {
                throw new Exception('Gagal hapus summary: ' . print_r(sqlsrv_errors(), true));
            }
        }

        // Hapus semua piece
        $deletePiecesStmt = sqlsrv_query($conn, "
            DELETE FROM cl_cutting_piece WHERE id_header = ?
        ", [$id_header]);

        if (!$deletePiecesStmt) {
            throw new Exception('Gagal hapus piece: ' . print_r(sqlsrv_errors(), true));
        }

        // Hapus header
        $deleteHeaderStmt = sqlsrv_query($conn, "
            DELETE FROM cl_cutting_header WHERE id_header = ?
        ", [$id_header]);

        if (!$deleteHeaderStmt) {
            throw new Exception('Gagal hapus header: ' . print_r(sqlsrv_errors(), true));
        }

        $deleted_count++;
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'ok', 'deleted' => $deleted_count], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
