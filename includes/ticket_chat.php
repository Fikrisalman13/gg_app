<?php
if (empty($_SESSION['UserId'])) {
    return;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/pages/ticket/theme_helper.php';
$rawTheme = $_SESSION['Theme'] ?? 'primary';
$chatThemeColor = ticket_normalize_theme($rawTheme);
// Provide consistent hex + contrast values for every technician theme option.
$chatThemePalette = [
    'primary' => ['base' => '#0d6efd', 'text' => '#fff'],
    'secondary' => ['base' => '#6c757d', 'text' => '#fff'],
    'success' => ['base' => '#198754', 'text' => '#fff'],
    'danger' => ['base' => '#dc3545', 'text' => '#fff'],
    'warning' => ['base' => '#ffc107', 'text' => '#212529'],
    'info' => ['base' => '#0dcaf0', 'text' => '#0b2f44'],
    'light' => ['base' => '#f8f9fa', 'text' => '#212529'],
    'dark' => ['base' => '#212529', 'text' => '#fff'],
    'indigo' => ['base' => '#6610f2', 'text' => '#fff'],
    'navy' => ['base' => '#001f3f', 'text' => '#fff'],
    'purple' => ['base' => '#6f42c1', 'text' => '#fff'],
    'pink' => ['base' => '#e83e8c', 'text' => '#fff'],
    'teal' => ['base' => '#20c997', 'text' => '#fff'],
    'orange' => ['base' => '#fd7e14', 'text' => '#212529'],
    'olive' => ['base' => '#3d9970', 'text' => '#fff'],
    'lime' => ['base' => '#01ff70', 'text' => '#0b2e13'],
    'fuchsia' => ['base' => '#f012be', 'text' => '#fff'],
    'maroon' => ['base' => '#85144b', 'text' => '#fff']
];
$chatThemeDefaults = $chatThemePalette['primary'];
$chatThemeData = $chatThemePalette[$chatThemeColor] ?? $chatThemeDefaults;
$chatThemeHex = $chatThemeData['base'];
$chatThemeText = $chatThemeData['text'];

// Get user's GroupName for role checking
$userGroupName = '';
$userEmpId = 0;
if (isset($_SESSION['GroupId'])) {
    $sqlGroup = "SELECT GroupName FROM dbo.SMUserGroup WHERE GroupId = ?";
    $stmtGroup = sqlsrv_query($conn, $sqlGroup, [$_SESSION['GroupId']]);
    if ($stmtGroup && $rowGroup = sqlsrv_fetch_array($stmtGroup, SQLSRV_FETCH_ASSOC)) {
        $userGroupName = trim($rowGroup['GroupName'] ?? '');
    }
}
// Get user's EmpId for assigned technician check
if (isset($_SESSION['UserName'])) {
    $sqlEmp = "SELECT EmpId FROM dbo.SMUserMs WHERE UserName = ?";
    $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$_SESSION['UserName']]);
    if ($stmtEmp && $rowEmp = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
        $userEmpId = intval($rowEmp['EmpId'] ?? 0);
    }
}

$isLightTheme = ($chatThemeColor === 'light');
$chatActionColor = $isLightTheme ? '#212529' : $chatThemeHex;
$chatTextClass = $isLightTheme ? 'text-dark' : 'text-' . $chatThemeColor;
$buttonShouldMatchDetail = $isLightTheme || $chatThemeColor === 'navy';
$chatButtonClass = $buttonShouldMatchDetail ? 'ticket-chat-open-detail' : 'btn-' . $chatThemeColor;
?>
<script src="/gg_app/plugins/js/asset_ticket.js?v=2"></script>
<style>
:root {
    --ticket-chat-accent: <?php echo $chatThemeHex; ?>;
    --ticket-chat-contrast: <?php echo $chatThemeText; ?>;
    --ticket-chat-action: <?php echo $chatActionColor; ?>;
}
.ticket-chat-body {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.ticket-chat-body > div { flex: 1 1 auto; min-height: 0; }
.ticket-chat-bubble {
    position: fixed;
    right: 24px;
    bottom: 70px;
    left: auto;
    top: auto;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: var(--ticket-chat-accent, <?php echo $chatThemeHex; ?>);
    color: var(--ticket-chat-contrast, <?php echo $chatThemeText; ?>);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 18px rgba(0,0,0,.25);
    cursor: pointer;
    z-index: 9999;
    transition: transform .2s ease, opacity .2s ease;
    overflow: visible;
    cursor: grab;
}
.ticket-chat-bubble.dragging { cursor: grabbing; }
.ticket-chat-bubble.d-none { display: none !important; }
.ticket-chat-bubble:hover { transform: scale(1.05); }
.ticket-chat-bubble .ticket-chat-count {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #dc3545;
    border-radius: 999px;
    padding: 2px 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
}
.ticket-chat-bubble .ticket-chat-close-icon {
    position: absolute;
    top: -8px;
    left: -8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: none;
    background: rgba(0,0,0,0.4);
    color: #fff;
    font-size: 12px;
    line-height: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    z-index: 1;
}
.ticket-chat-bubble .ticket-chat-close-icon:hover {
    background: rgba(0,0,0,0.6);
}
.ticket-chat-toast {
    position: fixed;
    right: 100px;
    bottom: 140px;
    left: auto;
    top: auto;
    background: #fff;
    border-left: 4px solid var(--ticket-chat-accent, <?php echo $chatThemeHex; ?>);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    padding: 0.75rem 1rem;
    min-width: 240px;
    border-radius: 6px;
    z-index: 1055;
    display: none;
}
.ticket-chat-toast.show { display: block; }
.ticket-chat-toast small { color: #6c757d; }
.ticket-chat-panel {
    position: fixed;
    right: 90px;
    bottom: 30px;
    left: auto;
    top: auto;
    width: 360px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 18px 35px rgba(15,28,45,0.25);
    z-index: 9998;
    display: flex;
    flex-direction: column;
    max-height: 530px;
    overflow: hidden;
}
.ticket-chat-panel.d-none { display: none; }
.ticket-chat-header,
.ticket-chat-thread-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f0f0f0;
    background: #f8f9fa;
    gap: 0.5rem;
}
.ticket-chat-list { flex: 1 1 auto; overflow-y: auto; min-height: 0; }
.ticket-chat-list-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
}
.ticket-chat-list-item:hover { background: #f5f8ff; }
.ticket-chat-list-item.unread .ticket-title { font-weight: 700; }
.ticket-chat-list-item .ticket-chat-list-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.ticket-chat-list-item .ticket-chat-list-info strong {
    font-size: 0.92rem;
    color: #1f2d3d;
}
.ticket-chat-list-item .ticket-chat-list-info small {
    color: #6c757d;
}
.ticket-chat-thread-wrapper { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
.ticket-chat-thread { flex: 1 1 auto; overflow-y: auto; padding: 0.75rem 1rem; background: #f9fbff; min-height: 0; }
.ticket-chat-thread-message { margin-bottom: 0.5rem; }
.ticket-chat-thread-message.me { text-align: right; }
.ticket-chat-thread-message .bubble {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 14px;
    background: #e9ecef;
    max-width: 92%;
    line-height: 1.35;
    vertical-align: middle;
    overflow-wrap: break-word;
    word-wrap: break-word;
    overflow: hidden !important;
    word-break: break-word !important;
}
.ticket-chat-thread-message .bubble p { margin: 0 !important; padding: 0 !important; }
.ticket-chat-thread-message .bubble *:last-child { margin-bottom: 0 !important; }
.ticket-chat-thread-message .bubble table {
    max-width: 100% !important;
    width: 100% !important;
    table-layout: auto !important;
    white-space: normal !important;
}
.ticket-chat-thread-message .bubble tr {
    height: auto !important;
    line-height: normal !important;
}
.ticket-chat-thread-message .bubble td,
.ticket-chat-thread-message .bubble th {
    max-width: 100% !important;
    min-width: 0 !important;
    width: auto !important;
    white-space: normal !important;
    word-break: break-word !important;
}
.ticket-chat-thread-message.me .bubble { 
    background: var(--ticket-chat-accent, <?php echo $chatThemeHex; ?>); 
    color: var(--ticket-chat-contrast, <?php echo $chatThemeText; ?>); 
    text-align: left; 
    border: <?php echo $isLightTheme ? '1px solid #212529' : 'none'; ?>;
}
.ticket-chat-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    flex-wrap: nowrap;
    white-space: nowrap;
}
.ticket-chat-thread-message.me .ticket-chat-meta {
    justify-content: flex-end;
}
.ticket-chat-status {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    margin-left: 0.5rem;
    color: #adb5bd;
}
.ticket-chat-status.read { color: var(--ticket-chat-action, <?php echo $chatActionColor; ?>); }
.ticket-chat-status i { font-size: 0.8rem; }
.ticket-chat-thread .attachments a { display: inline-flex; align-items: center; font-size: 0.8rem; margin-top: 0.25rem; color: var(--ticket-chat-action, <?php echo $chatActionColor; ?>); text-decoration: underline; }
.ticket-chat-thread-message.me .bubble a { color: inherit !important; text-decoration: underline; font-weight: 500; }
.ticket-chat-thread-message.me .bubble a:hover { opacity: 0.85; }

.ticket-chat-thread .bubble img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    display: block;
    margin-top: 0.35rem;
    background: rgba(0,0,0,0.03);
    box-shadow: 0 0 0 2px rgba(0,0,0,0.04);
    cursor: zoom-in;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.ticket-chat-thread .bubble img:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    transform: translateY(-1px);
}
.chat-inline-img-link { display: inline-block; }
.chat-image-modal .modal-dialog {
    max-width: 95vw;
}
.chat-image-modal .modal-content {
    background: transparent;
    border: none;
    box-shadow: none;
}
.chat-image-modal .modal-body {
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
}
.chat-image-modal .chat-image-stage {
    position: relative;
    display: inline-block;
    line-height: 0;
}
.chat-image-modal img {
    border-radius: 12px;
    box-shadow: 0 18px 35px rgba(0,0,0,0.35);
    display: block;
    max-width: 90vw;
    max-height: 85vh;
    width: auto;
    height: auto;
}
.chat-image-modal .chat-image-close {
    position: absolute;
    right: 8px;
    top: 8px;
    font-size: 1.75rem;
    color: #fff;
    text-shadow: 0 2px 6px rgba(0,0,0,0.4);
    opacity: 0.9;
    z-index: 2;
}
.chat-image-modal .chat-image-close:hover { opacity: 1; }


.ticket-chat-reply { padding: 0.75rem 1rem; border-top: 1px solid #f0f0f0; background: #fff; }
.ticket-chat-reply textarea { width: 100%; border: 1px solid #ced4da; border-radius: 10px; padding: 0.5rem 0.65rem; resize: none; min-height: 60px; }
.ticket-chat-reply .actions { margin-top: 0.4rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.ticket-chat-thread-title {
    display: flex;
    flex-direction: column;
    font-size: 0.9rem;
    white-space: normal;
    flex: 1;
}
.ticket-chat-thread-title strong { font-size: 0.95rem; }
.ticket-chat-thread-title small { color: #6c757d; }
.ticket-chat-thread-title #ticketChatThreadAssigned {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.15rem;
    font-size: 0.8rem;
    color: #28a745;
}
.ticket-chat-thread-title #ticketChatThreadAssigned i {
    font-size: 0.75rem;
}
.ticket-chat-back { cursor: pointer; }
.ticket-chat-list-item .text-end small {
    white-space: nowrap;
    display: inline-block;
}
.ticket-chat-open-detail {
    border: 1px solid var(--ticket-chat-action, <?php echo $chatActionColor; ?>);
    color: var(--ticket-chat-action, <?php echo $chatActionColor; ?>);
    background: transparent;
}
.ticket-chat-open-detail:hover,
.ticket-chat-open-detail:focus {
    background: var(--ticket-chat-action, <?php echo $chatActionColor; ?>);
    color: <?php echo $isLightTheme ? '#fff' : 'var(--ticket-chat-contrast, '.$chatThemeText.')'; ?>;
}
</style>
<div id="ticketChatBubble" class="ticket-chat-bubble d-none" title="Pesan ticket baru">
    <button type="button" class="ticket-chat-close-icon" title="Sembunyikan bubble">&times;</button>
    <i class="fas fa-comments"></i>
    <span class="ticket-chat-count">0</span>
</div>
<div id="ticketChatToast" class="ticket-chat-toast"></div>
<div id="ticketChatPanel" class="ticket-chat-panel d-none">
    <div class="ticket-chat-header">
        <strong>Ticket Chats</strong>
        <div>
            <button type="button" class="btn btn-sm btn-link <?php echo $chatTextClass; ?> text-decoration-none ticket-chat-refresh"><i class="fas fa-sync"></i></button>
            <button type="button" class="btn btn-sm btn-link <?php echo $chatTextClass; ?> text-decoration-none ticket-chat-close"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="ticket-chat-body">
        <div id="ticketChatList" class="ticket-chat-list"></div>
        <div id="ticketChatThreadWrapper" class="ticket-chat-thread-wrapper d-none">
            <div class="ticket-chat-thread-header">
                <div class="d-flex align-items-start gap-2 w-100">
                    <span class="ticket-chat-back <?php echo $chatTextClass; ?>"><i class="fas fa-arrow-left"></i></span>
                    <div class="ticket-chat-thread-title">
                        <strong id="ticketChatThreadSubject">Ticket</strong>
                        <small id="ticketChatThreadMeta">-</small>
                        <small id="ticketChatThreadAssigned" class="text-muted" style="display:none;">
                            <i class="fas fa-user-tie"></i> <span id="ticketChatAssignedName"></span>
                        </small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm ticket-chat-open-detail">Detail</button>
                    <button type="button" class="btn btn-sm btn-success ticket-chat-complete d-none">Selesai</button>
                </div>
            </div>
            <div id="ticketChatThread" class="ticket-chat-thread"></div>
            <div class="ticket-chat-reply">
                <div id="ticketChatReplyForm">
                    <textarea id="ticketChatReplyInput" rows="2" placeholder="Tulis pesan..."></textarea>
                    <div class="actions">
                        <label class="btn btn-sm btn-outline-secondary mb-0">
                            <i class="fas fa-paperclip"></i>
                            <input type="file" id="ticketChatReplyAttachment" multiple style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                        </label>
                    <button type="button" class="btn btn-sm <?php echo $chatButtonClass; ?>" id="ticketChatReplySend">Kirim</button>
                    </div>
                    <small id="ticketChatAttachmentNames" class="text-muted"></small>
                </div>
                <div id="ticketChatClosedMessage" class="text-center text-muted p-2 d-none">
                    <em>Ticket sudah selesai percakapan berakhir.</em>
                </div>
            </div>
        </div>
    </div>
</div>
    <style>
        <?php
        // 5. DEFINE THEME COLORS FOR MODAL BACKGROUND
        $modalThemeColor = $_SESSION['Theme'] ?? 'primary';
        $themePalette = [
            'primary'   => '#0d6efd',
            'secondary' => '#6c757d',
            'success'   => '#198754',
            'danger'    => '#dc3545',
            'warning'   => '#ffc107',
            'info'      => '#0dcaf0',
            'light'     => '#f8f9fa',
            'dark'      => '#212529',
            'indigo'    => '#6610f2',
            'navy'      => '#001f3f',
            'purple'    => '#6f42c1',
            'pink'      => '#e83e8c',
            'teal'      => '#20c997',
            'orange'    => '#fd7e14',
            'olive'     => '#3d9970',
            'lime'      => '#01ff70',
            'fuchsia'   => '#f012be',
            'maroon'    => '#85144b',
            'blue'      => '#007bff',
            'red'       => '#dc3545',
            'green'     => '#28a745',
            'yellow'    => '#ffc107',
            'cyan'      => '#17a2b8',
            'white'     => '#ffffff',
            'gray'      => '#6c757d',
            'gray-dark' => '#343a40'
        ];
        
        $baseHex = $themePalette[$modalThemeColor] ?? $themePalette['primary'];

        // Helper to convert hex to rgb
        if (!function_exists('ticket_chat_hex2rgb')) {
            function ticket_chat_hex2rgb($hex) {
                $hex = str_replace("#", "", $hex);
                if(strlen($hex) == 3) {
                    $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                    $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                    $b = hexdec(substr($hex,2,1).substr($hex,2,1));
                } else {
                    $r = hexdec(substr($hex,0,2));
                    $g = hexdec(substr($hex,2,2));
                    $b = hexdec(substr($hex,4,2));
                }
                return "$r, $g, $b";
            }
        }
        
        $rgbStr = ticket_chat_hex2rgb($baseHex);
        
        // PREMIUM DARK GRADIENT REFINED: 
        // 1. Remove harsh vignette (black edges).
        // 2. Use a sophisticated dark grey base blended with theme color.
        // 3. Linear gradient for smooth lighting across the modal.
        ?>
        .chat-image-modal .modal-body {
            position: relative;
            padding: 0;
            overflow: hidden; 
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            border: 2px solid <?php echo $baseHex; ?>;
            border-radius: 8px;
        }
        .chat-image-close {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1050; /* Above image */
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: background 0.2s;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        .chat-image-close:hover {
            background: rgba(255, 255, 255, 0.4);
            color: #fff;
        }
    </style>
    <div class="modal fade chat-image-modal" id="ticketChatImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:transparent; border:none; box-shadow:none;">
                <div class="modal-body position-relative" style="border-radius: 8px;">
                    <button type="button" class="close chat-image-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                    <div class="chat-image-stage" style="width:100%; height:100%; display:flex; justify-content:center; align-items:center;">
                        <img src="" alt="Preview" id="ticketChatImageModalImg" class="img-fluid" style="max-height: 80vh; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
window.ticketChatConfig = {
    userId: <?php echo intval($_SESSION['UserId'] ?? 0); ?>,
    theme: '<?php echo htmlspecialchars($chatThemeColor, ENT_QUOTES); ?>',
    themeHex: '<?php echo $chatThemeHex; ?>',
    themeContrast: '<?php echo $chatThemeText; ?>',
    userRole: '<?php echo htmlspecialchars($userGroupName, ENT_QUOTES); ?>',
    userEmpId: <?php echo intval($userEmpId); ?>
};
</script>

<?php
// Check if user has seen the Universal Zoom tutorial
$showUniversalZoomTutorial = true;
$tutorialFile = __DIR__ . '/../config/tutorial_data.json';
if (file_exists($tutorialFile)) {
    $tutorialData = json_decode(file_get_contents($tutorialFile), true);
    if (isset($tutorialData['universal_zoom']) && is_array($tutorialData['universal_zoom'])) {
        if (!empty($_SESSION['UserName']) && in_array($_SESSION['UserName'], $tutorialData['universal_zoom'])) {
            $showUniversalZoomTutorial = false;
        }
    }
}
?>
<script>
    window.needUniversalZoomTutorial = <?php echo $showUniversalZoomTutorial ? 'true' : 'false'; ?>;
</script>
<script src="/gg_app/plugins/js/universal_zoom_tutorial.js"></script>
<script src="/gg_app/plugins/js/ticket_chat_bubble.js?v=26"></script>
