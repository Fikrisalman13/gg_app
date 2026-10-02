<?php
// ===================================================
// 1. INISIALISASI & VALIDASI AKSES
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

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

    $sqlTicket = "SELECT t.creator_id, t.assigned_to FROM dbo.tickets t WHERE t.ticket_id = ?";
    $stmtTicket = sqlsrv_query($conn, $sqlTicket, [$ticketId]);
    $ticket = $stmtTicket ? sqlsrv_fetch_array($stmtTicket, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtTicket) sqlsrv_free_stmt($stmtTicket);
    if (!$ticket) {
        throw new Exception('Ticket tidak ditemukan');
    }

    $isIT = false;
    $sqlIT = "SELECT TOP 1 1 FROM dbo.m_emp e INNER JOIN dbo.m_bag b ON e.id_bag = b.id_bag
               WHERE e.id_emp = ? AND b.nama_bag = 'Information Technology'";
    $stmtIT = sqlsrv_query($conn, $sqlIT, [$userId]);
    if ($stmtIT && sqlsrv_fetch_array($stmtIT, SQLSRV_FETCH_ASSOC)) {
        $isIT = true;
    }
    if ($stmtIT) sqlsrv_free_stmt($stmtIT);

    $hasAccess = $isIT
        || $ticket['creator_id'] == $userId
        || (!empty($ticket['assigned_to']) && $ticket['assigned_to'] == $userId);

    if (!$hasAccess) {
        $sqlParticipant = "SELECT TOP 1 1 FROM dbo.ticket_messages WHERE ticket_id = ? AND sender_id = ?";
        $stmtPart = sqlsrv_query($conn, $sqlParticipant, [$ticketId, $userId]);
        if ($stmtPart && sqlsrv_fetch_array($stmtPart, SQLSRV_FETCH_ASSOC)) {
            $hasAccess = true;
        }
        if ($stmtPart) sqlsrv_free_stmt($stmtPart);
    }

    if (!$hasAccess) {
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
