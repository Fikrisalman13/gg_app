<?php
// ===================================================
// 1. INISIALISASI & VALIDASI PARAMETER
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$ticketId = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
$lastMsgId = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;
$currentUserId = $_SESSION['UserId'];
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');

if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Ticket ID']);
    exit;
}

// Check access (optional but recommended)
// ...

$response = ['success' => true, 'messages' => [], 'max_message_id' => $lastMsgId];

try {
    // 1. Fetch new messages
    $sql = "SELECT tm.*, e.nama_lengkap,
            (SELECT TOP 1 g.GroupName 
             FROM dbo.SMUserMs u 
             JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId 
             WHERE u.UserId = tm.sender_id) as sender_group
            FROM dbo.ticket_messages tm
            LEFT JOIN dbo.m_emp e ON tm.sender_id = e.id_emp
            WHERE tm.ticket_id = ? AND tm.message_id > ?
            ORDER BY tm.created_at ASC";
    $stmt = sqlsrv_query($conn, $sql, [$ticketId, $lastMsgId]);
    
    $messages = [];
    $newMaxId = $lastMsgId;
    $msgIds = [];

    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $messages[] = $row;
        $mid = intval($row['message_id']);
        if ($mid > $newMaxId) $newMaxId = $mid;
        $msgIds[] = $mid;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    if (!empty($messages)) {
        // 2. Fetch attachments
        $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
        $sqlAtt = "SELECT * FROM dbo.ticket_attachments WHERE ticket_id = ? AND message_id IN ($placeholders)";
        $paramsAtt = array_merge([$ticketId], $msgIds);
        $stmtAtt = sqlsrv_query($conn, $sqlAtt, $paramsAtt);
        
        $attachments = [];
        while ($stmtAtt && $rowA = sqlsrv_fetch_array($stmtAtt, SQLSRV_FETCH_ASSOC)) {
            $mid = intval($rowA['message_id']);
            if (!isset($attachments[$mid])) $attachments[$mid] = [];
            $attachments[$mid][] = $rowA;
        }
        if ($stmtAtt) sqlsrv_free_stmt($stmtAtt);

        // 3. Build HTML UNTUK BALON CHAT
        $htmlOutput = [];
        foreach ($messages as $msg) {
            $isMe = ($msg['sender_id'] == $currentUserId);
            $senderNamePlain = htmlspecialchars($msg['sender_name'] ?? $msg['nama_lengkap'] ?? 'User');
            $senderName = $senderNamePlain;
            
            // Check for Administrator
            $senderGroup = trim($msg['sender_group'] ?? '');
            if (strcasecmp($senderGroup, 'Administrator') === 0 && !$isMe) {
                $senderName = '<i class="fas fa-crown text-warning mr-1" title="Administrator"></i> ' . $senderName;
            }
            
            $time = $msg['created_at'] ? $msg['created_at']->format('d M H:i') : '';
            
            // Bubble classes
            $bubbleClasses = 'direct-chat-text';
            if ($isMe) {
                $bubbleClasses .= ' bubble-me theme-' . htmlspecialchars($themeColor);
            } else {
                $bubbleClasses .= ' bubble-other';
            }

            $mid = intval($msg['message_id']);
            $atts = $attachments[$mid] ?? [];
            $attHtml = '';
            if (!empty($atts)) {
                $attHtml .= '<div class="mt-2">';
                foreach ($atts as $att) {
                    $storedPath = $att['file_path'] ?? '';
                    $filePath = (strpos($storedPath, '/gg_app/') === 0) ? $storedPath : '/gg_app/' . ltrim($storedPath, '/');
                    $displayName = basename($filePath);
                    $ext = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp']);
                    
                    if ($isImage) {
                        $attHtml .= '<a href="'.htmlspecialchars($filePath).'" target="_blank" class="mr-2 mb-2 d-inline-block">';
                        $attHtml .= '<img src="'.htmlspecialchars($filePath).'" alt="'.htmlspecialchars($displayName).'" class="chat-attachment-thumb">';
                        $attHtml .= '</a>';
                    } else {
                        $attHtml .= '<a href="'.htmlspecialchars($filePath).'" target="_blank" class="chat-attachment-link mr-2 mb-2">';
                        $attHtml .= '<i class="fas fa-paperclip"></i> '.htmlspecialchars($displayName);
                        $attHtml .= '</a>';
                    }
                }
                $attHtml .= '</div>';
            }

            $floatName = $isMe ? 'right' : 'left';
            $floatTime = $isMe ? 'left' : 'right';
            $statusHtml = '';
            if ($isMe) {
                $statusHtml = '
                <div class="chat-status" data-message-id="'.$mid.'">
                    <span class="status-label sent">
                        <i class="fas fa-check"></i>
                        <span>Terkirim</span>
                    </span>
                </div>';
            }

            $html = '
            <div class="direct-chat-msg '.$rightClass.'">
                <div class="direct-chat-infos clearfix">
                    <span class="direct-chat-name float-'.$floatName.'">'.$senderName.'</span>
                    <span class="direct-chat-timestamp float-'.$floatTime.'">'.$time.'</span>
                </div>
                <img class="direct-chat-img" src="/gg_app/dist/img/user_default.png" alt="'.$senderNamePlain.'">
                <div class="'.$bubbleClasses.'">
                    '.$msg['message_html'].'
                    '.$attHtml.'
                </div>
                '.$statusHtml.'
            </div>';
            
            $htmlOutput[] = $html;
        }
        
        $response['html'] = implode('', $htmlOutput);
        $response['max_message_id'] = $newMaxId;

    }

    // 4. Fetch read statuses for current user's sent messages in this ticket
    $sqlReadStatus = "SELECT message_id, read_by, read_at
                      FROM dbo.ticket_messages
                      WHERE ticket_id = ?
                        AND sender_id = ?
                        AND read_by IS NOT NULL
                        AND read_by <> 0";
    $stmtReadStatus = sqlsrv_query($conn, $sqlReadStatus, [$ticketId, $currentUserId]);
    $readStatuses = [];
    while ($stmtReadStatus && ($rSt = sqlsrv_fetch_array($stmtReadStatus, SQLSRV_FETCH_ASSOC))) {
        $rTime = ($rSt['read_at'] instanceof DateTimeInterface) ? $rSt['read_at']->format('d M H:i') : 'Dibaca';
        $readStatuses[] = [
            'message_id' => intval($rSt['message_id']),
            'read_by' => intval($rSt['read_by']),
            'read_time' => $rTime
        ];
    }
    if ($stmtReadStatus) sqlsrv_free_stmt($stmtReadStatus);
    $response['read_statuses'] = $readStatuses;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
