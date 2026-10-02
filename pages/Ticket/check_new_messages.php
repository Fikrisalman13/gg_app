<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/chat_access.php';

$response = ['success' => false, 'count' => 0, 'latest' => [], 'server_time' => date('Y-m-d H:i:s')];

if (!isset($_SESSION['UserId']) || !$conn) {
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit;
}

try {
    $userId = intval($_SESSION['UserId']);
    $lastCheckRaw = $_POST['last_check'] ?? '';
    $lastCheckDt = null;
    if ($lastCheckRaw) {
        $lastCheckDt = date_create($lastCheckRaw);
    }
    if (!$lastCheckDt) {
        $lastCheckDt = new DateTime('-1 hour');
    }
    $lastCheckSql = $lastCheckDt->format('Y-m-d H:i:s');
    $lastSeenId = intval($_POST['last_seen_id'] ?? 0);
    if ($lastSeenId < 0) { $lastSeenId = 0; }
    
    $excludeTicketId = intval($_POST['exclude_ticket_id'] ?? 0);

    // ===================================================
    // 2. TENTUKAN VISIBILITAS USER
    // ===================================================
    $identity = ticket_chat_load_identity($conn, $userId);
    $visibilityRule = ticket_chat_visibility($identity);
    $visibility = $visibilityRule['sql'];
    $visibilityParams = $visibilityRule['params'];

    // ===================================================
    // 3. BENTUK FILTER BERDASARKAN LAST CHECK
    // ===================================================
    $useSeenFilter = $lastSeenId > 0;
    $filterClause = $useSeenFilter ? 'tm.message_id > ?' : 'tm.created_at > ?';
    $filterParam = $useSeenFilter ? $lastSeenId : $lastCheckSql;

    $baseParams = [$filterParam, $userId];
    $baseParams = array_merge($baseParams, $visibilityParams);
    
    $excludeClause = ($excludeTicketId > 0) ? " AND tm.ticket_id <> ? " : "";
    if ($excludeTicketId > 0) {
        $baseParams[] = $excludeTicketId;
    }

    // ===================================================
    // 4. HITUNG JUMLAH PESAN BARU
    // ===================================================
    $sqlCount = "SELECT COUNT(*) AS cnt
                 FROM dbo.ticket_messages tm
                 INNER JOIN dbo.tickets t ON t.ticket_id = tm.ticket_id
                 WHERE $filterClause AND tm.sender_id <> ? $excludeClause AND $visibility
                 AND t.closed_at IS NULL
                 AND (tm.read_by IS NULL OR tm.read_by = 0)";
    $stmtCount = sqlsrv_query($conn, $sqlCount, $baseParams);
    $rowCount = $stmtCount ? sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtCount) sqlsrv_free_stmt($stmtCount);
    $totalNew = intval($rowCount['cnt'] ?? 0);

    // ===================================================
    // 5. AMBIL PREVIEW PESAN BARU TERBARU
    // ===================================================
    $sqlLatest = "SELECT TOP 5 tm.message_id, tm.ticket_id, t.ticket_no, tm.sender_name, tm.message_html, tm.created_at
                  FROM dbo.ticket_messages tm
                  INNER JOIN dbo.tickets t ON t.ticket_id = tm.ticket_id
                  WHERE $filterClause AND tm.sender_id <> ? $excludeClause AND $visibility
                  AND t.closed_at IS NULL
                  AND (tm.read_by IS NULL OR tm.read_by = 0)
                  ORDER BY tm.created_at DESC";
    $stmtLatest = sqlsrv_query($conn, $sqlLatest, $baseParams);
    $latest = [];
    $maxMessageId = $lastSeenId;
    while ($stmtLatest && ($row = sqlsrv_fetch_array($stmtLatest, SQLSRV_FETCH_ASSOC))) {
        $preview = preg_replace('/<\s*(?:br|\/p|\/div|\/li)\s*\/?>/i', ' ', (string)($row['message_html'] ?? '')) ?? '';
        $preview = html_entity_decode($preview, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $preview = preg_replace('/\s+/u', ' ', trim(strip_tags($preview))) ?? '';
        if (strlen($preview) > 120) {
            $preview = substr($preview, 0, 117) . '...';
        }
        $latest[] = [
            'message_id' => intval($row['message_id'] ?? 0),
            'ticket_id' => intval($row['ticket_id']),
            'ticket_no' => $row['ticket_no'] ?? '',
            'sender' => $row['sender_name'] ?? 'User',
            'time' => $row['created_at'] ? $row['created_at']->format('d-m H:i') : '',
            'preview' => $preview
        ];
        if (!empty($row['message_id'])) {
            $maxMessageId = max($maxMessageId, intval($row['message_id']));
        }
    }
    if ($stmtLatest) sqlsrv_free_stmt($stmtLatest);

    $response['success'] = true;
    $response['count'] = $totalNew;
    $response['latest'] = $latest;
    $response['server_time'] = date('Y-m-d H:i:s');
    $response['max_message_id'] = $maxMessageId;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
