<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/chat_access.php';

$result = ['success' => false, 'conversations' => [], 'server_time' => date('Y-m-d H:i:s')];
if (!isset($_SESSION['UserId']) || !$conn) {
    $result['message'] = 'Unauthorized';
    echo json_encode($result);
    exit;
}

try {
    $userId = intval($_SESSION['UserId']);
    $lastCheckRaw = $_POST['last_check'] ?? '';
    $lastCheck = date_create($lastCheckRaw ?: '');
    if (!$lastCheck) { $lastCheck = new DateTime('-1 day'); }
    $lastCheckSql = $lastCheck->format('Y-m-d H:i:s');
    $lastSeenId = intval($_POST['last_seen_id'] ?? 0);
    if ($lastSeenId < 0) { $lastSeenId = 0; }

    // ===================================================
    // 2. TENTUKAN VISIBILITAS PENGGUNA
    // ===================================================
    $identity = ticket_chat_load_identity($conn, $userId);
    $visibilityRule = ticket_chat_visibility($identity);
    $visibility = $visibilityRule['sql'];
    $visibilityParams = $visibilityRule['params'];

    // ===================================================
    // 3. SIAPKAN PARAMETER PAGING & FILTER UNREAD
    // ===================================================
    $limit = intval($_POST['limit'] ?? 15);
    if ($limit <= 0 || $limit > 50) { $limit = 15; }

    $unreadFilter = $lastSeenId > 0 ? 'tmUnread.message_id > ?' : 'tmUnread.created_at > ?';
    $unreadParam = $lastSeenId > 0 ? $lastSeenId : $lastCheckSql;

    // ===================================================
    // 4. AMBIL DAFTAR KONVERSASI BESERTA UNREAD COUNT
    // ===================================================
    $sql = "SELECT TOP $limit v.ticket_id, v.ticket_no, v.subject, v.creator_name, v.closed_at,
                   lastMsg.sender_name AS last_sender,
                   lastMsg.message_html AS last_message,
                   lastMsg.created_at AS last_created,
                   v.created_at AS ticket_created,
                   COALESCE(unreadCnt.unread_count, 0) AS unread_count
            FROM (
                SELECT t.ticket_id, t.ticket_no, t.subject, t.creator_name, t.created_at, t.closed_at
                FROM dbo.tickets t
                WHERE $visibility AND (t.closed_at IS NULL)
            ) v
            OUTER APPLY (
                SELECT TOP 1 tm2.sender_name, tm2.message_html, tm2.created_at
                FROM dbo.ticket_messages tm2
                WHERE tm2.ticket_id = v.ticket_id
                ORDER BY tm2.created_at DESC
            ) lastMsg
            OUTER APPLY (
                SELECT COUNT(*) AS unread_count
                FROM dbo.ticket_messages tmUnread
                WHERE tmUnread.ticket_id = v.ticket_id
                  AND tmUnread.sender_id <> ?
                  AND $unreadFilter
                  AND (tmUnread.read_by IS NULL OR tmUnread.read_by = 0)
            ) unreadCnt
            ORDER BY COALESCE(lastMsg.created_at, v.created_at) DESC";

    $params = array_merge($visibilityParams, [$userId, $unreadParam]);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));
    }
    $conversations = [];
    while ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $preview = preg_replace('/<\s*(?:br|\/p|\/div|\/li)\s*\/?>/i', ' ', (string)($row['last_message'] ?? '')) ?? '';
        $preview = html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $preview = preg_replace('/\s+/u', ' ', trim(strip_tags($preview))) ?? '';
        if ($preview === '' && $row['subject']) { $preview = $row['subject']; }
        if (strlen($preview) > 120) { $preview = substr($preview, 0, 117).'...'; }
        $conversations[] = [
            'ticket_id' => intval($row['ticket_id']),
            'ticket_no' => $row['ticket_no'] ?? '',
            'subject' => $row['subject'] ?? '',
            'creator_name' => $row['creator_name'] ?? '',
            'last_sender' => $row['last_sender'] ?? '',
            'last_time' => $row['last_created'] ? $row['last_created']->format('d M H:i') : '',
            'preview' => $preview,
            'unread_count' => intval($row['unread_count'] ?? 0),
            'closed_at' => $row['closed_at'] ? $row['closed_at']->format('Y-m-d H:i:s') : null
        ];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    $result['success'] = true;
    $result['conversations'] = $conversations;
    $result['server_time'] = date('Y-m-d H:i:s');
} catch (Exception $e) {
    $result['message'] = $e->getMessage();
}

echo json_encode($result);
