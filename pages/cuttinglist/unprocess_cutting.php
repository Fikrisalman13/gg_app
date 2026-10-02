<?php
require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No IDs selected']);
    exit;
}

try {
    sqlsrv_begin_transaction($conn);
    $unprocessed_count = 0;
    
    foreach ($ids as $id_header) {
        $id_header = intval($id_header);

        // Delete cutting process data
        $deleteProcessStmt = sqlsrv_query($conn, "
            DELETE FROM cl_cutting_process
            WHERE id_piece IN (
                SELECT id_piece FROM cl_cutting_piece
                WHERE id_header = ?
            )
        ", [$id_header]);

        if ($deleteProcessStmt === false) {
            throw new Exception('Delete process gagal: ' . json_encode(sqlsrv_errors()));
        }

        // Delete cutting summary data
        $deleteSummaryStmt = sqlsrv_query($conn, "
            DELETE FROM cl_cutting_summary
            WHERE id_piece IN (
                SELECT id_piece FROM cl_cutting_piece
                WHERE id_header = ?
            )
        ", [$id_header]);

        if ($deleteSummaryStmt === false) {
            throw new Exception('Delete summary gagal: ' . json_encode(sqlsrv_errors()));
        }

        // Update header processed status
        $updateStmt = sqlsrv_query($conn, "
            UPDATE cl_cutting_header
            SET processed = 0, updated_by = ?, updated_date = GETDATE()
            WHERE id_header = ?
        ", [$_SESSION['UserName'] ?? 'SYSTEM', $id_header]);

        if ($updateStmt === false) {
            throw new Exception('Update header gagal: ' . json_encode(sqlsrv_errors()));
        }

        $unprocessed_count++;
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'ok', 'processed' => $unprocessed_count], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
