<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

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
    $isIT = false;
    $sqlIT = "SELECT TOP 1 1 FROM dbo.m_emp e INNER JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE e.id_emp = ? AND b.nama_bag = 'Information Technology'";
    $stmtIT = sqlsrv_query($conn, $sqlIT, [$userId]);
    if ($stmtIT && sqlsrv_fetch_array($stmtIT, SQLSRV_FETCH_ASSOC)) { $isIT = true; }
    if ($stmtIT) sqlsrv_free_stmt($stmtIT);

    $visibilityClauses = [];
    $visibilityParams = [];
    if (!$isIT) {
        $visibilityClauses[] = 't.creator_id = ?';
        $visibilityParams[] = $userId;
        $visibilityClauses[] = 't.assigned_to = ?';
        $visibilityParams[] = $userId;
        $visibilityClauses[] = 'EXISTS (SELECT 1 FROM dbo.ticket_messages tm_part WHERE tm_part.ticket_id = t.ticket_id AND tm_part.sender_id = ?)';
        $visibilityParams[] = $userId;
    }
    $visibility = $isIT ? '1=1' : '(' . implode(' OR ', $visibilityClauses) . ')';

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
        $preview = strip_tags($row['last_message'] ?? '');
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
