<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
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
    if ($ticketId <= 0) throw new Exception('Ticket invalid');

    $userId = intval($_SESSION['UserId']);

    $sqlTicket = "SELECT t.ticket_id, t.ticket_no, t.subject, t.creator_name, t.creator_id, t.assigned_to, t.closed_at,
                         e.nama_lengkap as assigned_name,
                         (SELECT TOP 1 a.kode_asset_seq FROM dbo.ticket_assets ta 
                          LEFT JOIN dbo.m_asset a ON ta.id_asset = a.id_asset 
                          WHERE ta.ticket_id = t.ticket_id) as asset_code
                  FROM dbo.tickets t
                  LEFT JOIN dbo.m_emp e ON t.assigned_to = e.id_emp
                  WHERE t.ticket_id = ?";
    $stmtTicket = sqlsrv_query($conn, $sqlTicket, [$ticketId]);
    $ticket = $stmtTicket ? sqlsrv_fetch_array($stmtTicket, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtTicket) sqlsrv_free_stmt($stmtTicket);
    if (!$ticket) throw new Exception('Ticket tidak ditemukan');

    // ... (rest of the code) ...

    // ===================================================
    // 2. CEK PERAN DAN HAK AKSES USER
    // ===================================================
    $identity = ticket_chat_load_identity($conn, $userId);
    if (!ticket_chat_can_access($conn, $ticketId, $identity)) {
        http_response_code(403);
        throw new Exception('Tidak memiliki akses ke ticket ini');
    }

    // ===================================================
    // 3. AMBIL PESAN DAN DETIL LAMPIRAN
    // ===================================================
    $sqlMessages = "SELECT tm.message_id, tm.sender_id, tm.sender_name, tm.message_html, tm.created_at,
                           tm.read_by, tm.read_at, reader.nama_lengkap AS read_by_name,
                           (SELECT TOP 1 g.GroupName 
                            FROM dbo.SMUserMs u 
                            JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId 
                            WHERE u.UserId = tm.sender_id) as sender_group
                    FROM dbo.ticket_messages tm
                    LEFT JOIN dbo.SMUserMs u_reader ON tm.read_by = u_reader.UserId
                    LEFT JOIN dbo.m_emp reader ON u_reader.EmpId = reader.id_emp
                    WHERE tm.ticket_id = ?
                    ORDER BY tm.created_at ASC";
    $stmtMsg = sqlsrv_query($conn, $sqlMessages, [$ticketId]);
    $messages = [];
    $messageIds = [];
    while ($stmtMsg && ($row = sqlsrv_fetch_array($stmtMsg, SQLSRV_FETCH_ASSOC))) {
        $row['is_me'] = ($row['sender_id'] == $userId);
        $row['created_at_fmt'] = $row['created_at'] ? $row['created_at']->format('d M H:i') : '';
        $row['is_admin'] = (isset($row['sender_group']) && strcasecmp(trim($row['sender_group']), 'Administrator') === 0);
        $messages[] = $row;
        $messageIds[] = intval($row['message_id']);
    }
    if ($stmtMsg) sqlsrv_free_stmt($stmtMsg);

    $attachmentMap = [];
    if (!empty($messageIds)) {
        $ph = implode(',', array_fill(0, count($messageIds), '?'));
        $sqlAtt = "SELECT attachment_id, message_id, file_path, mime_type FROM dbo.ticket_attachments
                   WHERE ticket_id = ? AND message_id IN ($ph)
                   ORDER BY attachment_id ASC";
        $paramsAtt = array_merge([$ticketId], $messageIds);
        $stmtAtt = sqlsrv_query($conn, $sqlAtt, $paramsAtt);
        while ($stmtAtt && ($att = sqlsrv_fetch_array($stmtAtt, SQLSRV_FETCH_ASSOC))) {
            $mid = intval($att['message_id']);
            if (!isset($attachmentMap[$mid])) $attachmentMap[$mid] = [];
            $attachmentMap[$mid][] = $att;
        }
        if ($stmtAtt) sqlsrv_free_stmt($stmtAtt);
    }

    // ===================================================
    // 4. HITUNG STATUS BACA DAN BENTUK RESPON
    // ===================================================
    foreach ($messages as &$msg) {
        $mid = intval($msg['message_id']);
        $msg['attachments'] = $attachmentMap[$mid] ?? [];
        $readers = [];
        $readBy = isset($msg['read_by']) ? intval($msg['read_by']) : null;
        $readAt = $msg['read_at'] ?? null;
        if ($readBy && $readAt && $readBy !== intval($msg['sender_id'])) {
            $name = $msg['read_by_name'] ?? ('User ' . $readBy);
            $readers[] = [
                'user_id' => $readBy,
                'name' => $name,
                'read_at' => $readAt->format('d M H:i')
            ];
        }
        $msg['receipt'] = [
            'delivered' => true,
            'read' => !empty($readers),
            'readers' => $readers
        ];
        $msg['receipt_text'] = !empty($readers)
            ? 'Dibaca oleh ' . implode(', ', array_map(function($r){ return $r['name']; }, $readers))
            : 'Terkirim';
        $msg['read_at_fmt'] = $readAt ? $readAt->format('d M H:i') : '';
        $msg['read_at'] = $readAt ? $readAt->format(DateTime::ATOM) : null;
        $msg['read_by'] = $readBy;
    }
    unset($msg);

    $response['success'] = true;
    $response['ticket'] = [
        'ticket_id' => $ticket['ticket_id'],
        'ticket_no' => $ticket['ticket_no'],
        'subject' => $ticket['subject'],
        'creator_name' => $ticket['creator_name'] ?? '',
        'assigned_to' => $ticket['assigned_to'] ? intval($ticket['assigned_to']) : null,
        'assigned_name' => $ticket['assigned_name'] ?? null,
        'closed_at' => $ticket['closed_at'] ? $ticket['closed_at']->format('Y-m-d H:i:s') : null,
        'has_asset' => !empty($ticket['asset_code'])
    ];
    $response['messages'] = $messages;
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
