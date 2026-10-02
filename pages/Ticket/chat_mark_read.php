<?php
// ===================================================
// 1. INISIALISASI & VALIDASI AKSES
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/chat_access.php';

$response = ['success' => false];
if (!isset($_SESSION['UserId']) || !$conn) {
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

try {
    $ticketId = intval($_POST['ticket_id'] ?? 0);
    $lastMessageId = intval($_POST['last_message_id'] ?? 0);
    if ($ticketId <= 0) {
        throw new Exception('Ticket invalid');
    }
    if ($lastMessageId <= 0) {
        $response['success'] = true;
        $response['message'] = 'No messages to mark';
        echo json_encode($response);
        exit;
    }

    $userId = intval($_SESSION['UserId']);

    $identity = ticket_chat_load_identity($conn, $userId);
    if (!ticket_chat_can_access($conn, $ticketId, $identity)) {
        http_response_code(403);
        throw new Exception('Tidak memiliki akses ke ticket ini');
    }

    // ===================================================
    // 2. TENTUKAN RENTANG PESAN YANG PERLU DITANDAI
    // ===================================================
    $sqlMax = "SELECT MAX(message_id) AS max_id
               FROM dbo.ticket_messages
               WHERE ticket_id = ?
                 AND sender_id <> ?
                 AND message_id <= ?
                 AND read_by IS NULL";
    $stmtMax = sqlsrv_query($conn, $sqlMax, [$ticketId, $userId, $lastMessageId]);
    if ($stmtMax === false) {
        throw new Exception('Gagal menyiapkan data baca');
    }
    $maxRow = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC);
    if ($stmtMax) sqlsrv_free_stmt($stmtMax);
    $upToId = intval($maxRow['max_id'] ?? 0);

    // ===================================================
    // 3. PERBARUI STATUS BACA PESAN
    // ===================================================
    if ($upToId > 0) {
        $sqlUpdate = "UPDATE dbo.ticket_messages
                      SET read_by = ?, read_at = GETDATE()
                      WHERE ticket_id = ?
                        AND sender_id <> ?
                        AND message_id <= ?
                        AND read_by IS NULL";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$userId, $ticketId, $userId, $lastMessageId]);
        if ($stmtUpdate === false) {
            throw new Exception('Gagal memperbarui status baca');
        }
    }

    $response['success'] = true;
    $response['last_message_id'] = $upToId ?: $lastMessageId;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
