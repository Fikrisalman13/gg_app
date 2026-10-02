<?php
// ===================================================
// 1. INISIALISASI HALAMAN DETAIL
// ===================================================
session_start();
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserId'])) { header('Location: /gg_app/login.php'); exit; }

// ===================================================
// 3. KONFIGURASI LAYOUT DAN DATA AWAL
// ===================================================
$includeTicketThemeCss = true;
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$noteModalPalette = [
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
$noteModalTheme = $noteModalPalette[$themeColor] ?? $noteModalPalette['primary'];
$noteModalHeaderHex = $noteModalTheme['base'];
$noteModalHeaderText = $noteModalTheme['text'];
$GLOBALS['ticketThemeOverride'] = $themeColor;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$id_ticket = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lookup ticket_id by ticket_no if requested
if ($id_ticket <= 0 && isset($_GET['no'])) {
    $ticketNo = trim($_GET['no']);
    if ($ticketNo !== '') {
        $sqlLookup = "SELECT ticket_id FROM dbo.tickets WHERE ticket_no = ?";
        $stmtLookup = sqlsrv_query($conn, $sqlLookup, [$ticketNo]);
        if ($stmtLookup && $rowLookup = sqlsrv_fetch_array($stmtLookup, SQLSRV_FETCH_ASSOC)) {
            $id_ticket = intval($rowLookup['ticket_id']);
        }
        if ($stmtLookup) sqlsrv_free_stmt($stmtLookup);
    }
}

// Check if user is Administrator
$isAdmin = false;
if (isset($_SESSION['UserName'])) {
    $sqlAdmin = "SELECT b.GroupName 
                 FROM dbo.SMUserMs a 
                 LEFT JOIN dbo.SMUserGroup b ON a.GroupId = b.GroupId 
                 WHERE a.UserName = ?";
    $stmtAdmin = sqlsrv_query($conn, $sqlAdmin, [ $_SESSION['UserName'] ]);
    if ($stmtAdmin && $rowAdmin = sqlsrv_fetch_array($stmtAdmin, SQLSRV_FETCH_ASSOC)) {
        if (strcasecmp(trim($rowAdmin['GroupName']), 'Administrator') === 0) {
            $isAdmin = true;
        }
    }
    if ($stmtAdmin) sqlsrv_free_stmt($stmtAdmin);
}



// Helper functions
/**
 * Mengembalikan badge sesuai level prioritas ticket.
 */
function getPriorityLabel($p) {
    if ($p == 1) return '<span class="badge bg-success">Low</span>';
    if ($p == 3) return '<span class="badge bg-danger">High</span>';
    return '<span class="badge bg-warning">Normal</span>';
}
/**
 * Mengubah nama status menjadi badge bootstrap yang konsisten.
 */
function getStatusLabelByName($name) {
    $n = trim((string)$name);
    if ($n === '') $n = 'Open';
    $cls = 'bg-secondary';
    if (strcasecmp($n,'Open')===0) $cls = 'bg-info';
    else if (strcasecmp($n,'Proses')===0 || strcasecmp($n,'In Progress')===0 || strcasecmp($n,'Process')===0) $cls = 'bg-warning text-dark';
    else if (strcasecmp($n,'Selesai')===0 || strcasecmp($n,'Done')===0 || strcasecmp($n,'Closed')===0 || strcasecmp($n,'Complete')===0) $cls = 'bg-success';
    else if (strcasecmp($n,'Cancel')===0 || strcasecmp($n,'Rejected')===0) $cls = 'bg-danger';
    return '<span class="badge '.$cls.'">'.htmlspecialchars($n).'</span>';
}

/**
 * Menentukan status akhir ticket berdasarkan field penting.
 */
function getTicketStatus($ticket) {
  if (!empty($ticket['closed_at'])) {
    return '<span class="badge bg-success">Selesai</span>';
  }

  $statusName = trim($ticket['ticket_status_name'] ?? '');
  if ($statusName !== '') {
    return getStatusLabelByName($statusName);
  }

  if (empty($ticket['assigned_to'])) {
    return '<span class="badge bg-info">Open</span>';
  }

  return '<span class="badge bg-warning text-dark">Proses</span>';
}

$ticket = null;
$messages = [];
$maxMessageId = 0;

if ($id_ticket > 0) {
    // Fetch Ticket Details
    $sqlT = "SELECT t.*, 
              e.nama_lengkap, j.jabatan, d.dept, b.bagian,
              ts.status_name as ticket_status_name,
              a.kode_asset_seq, k.nama_kategori, s.nama_status as asset_status,
              assign_emp.nama_lengkap AS assigned_emp_name,
              COALESCE(NULLIF(us_assign.Theme, ''), 'secondary') AS assigned_theme
             FROM dbo.tickets t
             LEFT JOIN dbo.m_emp e ON t.creator_id = e.id_emp
             LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
             LEFT JOIN dbo.m_bag b ON e.id_bag = b.id_bag
             LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
             LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
             LEFT JOIN dbo.ticket_assets ta ON t.ticket_id = ta.ticket_id
             LEFT JOIN dbo.m_asset a ON ta.id_asset = a.id_asset
             LEFT JOIN dbo.m_kategori k ON a.id_kategori = k.id_kategori
            LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
            LEFT JOIN dbo.m_emp assign_emp ON t.assigned_to = assign_emp.id_emp
            LEFT JOIN dbo.SMUserMs us_assign ON us_assign.EmpId = t.assigned_to
             WHERE t.ticket_id = ?";
    
    $stmtT = sqlsrv_query($conn, $sqlT, [$id_ticket]);
    if ($stmtT) {
        $ticket = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtT);
    }

    // Fetch Messages
    if ($ticket) {
        $sqlM = "SELECT tm.*, e.nama_lengkap, reader.nama_lengkap AS read_by_name,
                        (SELECT TOP 1 g.GroupName 
                         FROM dbo.SMUserMs u 
                         JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId 
                         WHERE u.UserId = tm.sender_id) as sender_group
                 FROM dbo.ticket_messages tm
                 LEFT JOIN dbo.m_emp e ON tm.sender_id = e.id_emp
                 LEFT JOIN dbo.m_emp reader ON tm.read_by = reader.id_emp
                 WHERE tm.ticket_id = ?
                 ORDER BY tm.created_at ASC";
        $stmtM = sqlsrv_query($conn, $sqlM, [$id_ticket]);
        $messageIds = [];
        $maxMessageId = 0;
        while ($stmtM && $row = sqlsrv_fetch_array($stmtM, SQLSRV_FETCH_ASSOC)) {
          $messages[] = $row;
          if (!empty($row['message_id'])) {
            $messageIds[] = intval($row['message_id']);
            if (intval($row['message_id']) > $maxMessageId) {
              $maxMessageId = intval($row['message_id']);
            }
          }
        }
        if ($stmtM) sqlsrv_free_stmt($stmtM);

        if (!empty($messageIds)) {
          $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
          $sqlA = "SELECT message_id, file_path, mime_type, uploaded_at FROM dbo.ticket_attachments WHERE ticket_id = ? AND message_id IN ($placeholders) ORDER BY attachment_id ASC";
          $paramsA = array_merge([ $id_ticket ], $messageIds);
          $stmtA = sqlsrv_query($conn, $sqlA, $paramsA);
          $attachmentMap = [];
          while ($stmtA && $rowA = sqlsrv_fetch_array($stmtA, SQLSRV_FETCH_ASSOC)) {
            $mid = intval($rowA['message_id']);
            if (!isset($attachmentMap[$mid])) $attachmentMap[$mid] = [];
            $attachmentMap[$mid][] = $rowA;
          }
          if ($stmtA) sqlsrv_free_stmt($stmtA);

          foreach ($messages as &$msgRef) {
            $mid = intval($msgRef['message_id'] ?? 0);
            $msgRef['attachments'] = $attachmentMap[$mid] ?? [];
          }
          unset($msgRef);
        } else {
          foreach ($messages as &$msgRef) {
            $msgRef['attachments'] = [];
          }
          unset($msgRef);
        }
    }
}

// Prefill for Create Mode
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/summernote-lite.min.css">
<style>
    .direct-chat-messages { height: 400px; }
    .ticket-detail-list .list-group-item { padding: 0.5rem 1rem; border: none; border-bottom: 1px solid #f0f0f0; }
    .ticket-detail-list .list-group-item:last-child { border-bottom: none; }
    .ticket-label { font-weight: 600; color: #555; font-size: 0.9rem; }
    .ticket-value { font-size: 0.95rem; }
  .chat-attachment-link { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border: 1px solid transparent; border-radius: 999px; font-size: 0.85rem; text-decoration: none; }
  .direct-chat-msg.right .chat-attachment-link { color: #fff; border-color: rgba(255,255,255,0.7); }
  .direct-chat-msg:not(.right) .chat-attachment-link { color: #0d6efd; border-color: rgba(13,110,253,0.5); }
  .chat-attachment-thumb { border: 2px solid rgba(255,255,255,0.65); border-radius: 6px; max-width: 200px; max-height: 150px; }
  .direct-chat-msg:not(.right) .chat-attachment-thumb { border-color: rgba(13,110,253,0.35); }
  
  /* Compact Bubble Style */
  .direct-chat-text { padding: 6px 12px !important; line-height: 1.3 !important; margin-bottom: 4px !important; }
  .direct-chat-text p { margin: 0 !important; padding: 0 !important; }
  .direct-chat-text > *:first-child { margin-top: 0 !important; }
  .direct-chat-text > *:last-child { margin-bottom: 0 !important; }
  .direct-chat-infos { margin-bottom: 2px !important; }
  .chat-status { display:flex; align-items:center; gap:0.35rem; font-size:0.8rem; color:#6c757d; margin-top:4px; }
  .direct-chat-msg.right .chat-status { justify-content:flex-end; }
  .chat-status .status-label { display:inline-flex; align-items:center; gap:0.25rem; }
  .chat-status .status-label.sent { color:#adb5bd; }
  .chat-status .status-label.read { color:#0d6efd; }

  

/* Outgoing bubble base color (will be combined with theme class) */
  .direct-chat-text.bubble-me { color:#fff; }
  .direct-chat-text.bubble-other { color:#212529; background:#f1f3f5; }

  /* Theme classes - match bootstrap palette */
  .direct-chat-text.bubble-me.theme-primary { background:#0d6efd; }
  .direct-chat-text.bubble-me.theme-secondary { background:#6c757d; }
  .direct-chat-text.bubble-me.theme-success { background:#28a745; }
  .direct-chat-text.bubble-me.theme-danger { background:#dc3545; }
  .direct-chat-text.bubble-me.theme-warning { background:#ffc107; color:#212529; }
  .direct-chat-text.bubble-me.theme-info { background:#0dcaf0; }
  .direct-chat-text.bubble-me.theme-light { background:#f8f9fa; color:#212529; }
  .direct-chat-text.bubble-me.theme-dark { background:#212529; }

  /* Attachments visibility tweaks */
  .chat-attachment-link { background: rgba(0,0,0,0.03); padding:0.2rem 0.5rem; border-radius:6px; }
  .direct-chat-msg.right .chat-attachment-link { background: rgba(255,255,255,0.12); color:#fff; }
    .chat-attachment-thumb { box-shadow: 0 0 0 2px rgba(0,0,0,0.04); border-radius:6px; }
    .direct-chat-text .chat-inline-img-link { display:block !important; margin-bottom:0.5rem !important; }
    .direct-chat-text img,
    .note-editor .note-editable img {
      max-width: 220px !important;
      width: auto !important;
      height: auto;
      border-radius: 6px;
      display: block;
      margin-top: 0.35rem;
      background: rgba(0,0,0,0.03);
      box-shadow: 0 0 0 2px rgba(0,0,0,0.04);
      cursor: zoom-in;
      transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .direct-chat-text .chat-inline-img-link img { display:block; width:auto !important; }
    .direct-chat-text img:hover,
    .note-editor .note-editable img:hover {
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transform: translateY(-1px);
    }
  
  /* Fix Header Icon Clickability */
  .main-header { z-index: 1100 !important; position: relative; }
  /* Fix Modal z-index to prevent header overlap */
  .modal { z-index: 9999 !important; }
  .modal-backdrop { z-index: 9998 !important; }
  /* Summernote Modal Fix */
  .note-modal-backdrop { z-index: 10000 !important; }
  .note-modal { z-index: 10001 !important; top: 50px !important; }
    .note-modal-content {
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 20px 45px rgba(15,28,45,0.25);
      border: none;
    }
    .note-modal-header {
      background: <?php echo $noteModalHeaderHex; ?>;
      color: <?php echo $noteModalHeaderText; ?>;
      border: none;
      padding: 0.85rem 1.25rem;
    }
    .note-modal-header .note-modal-title { color: inherit; font-weight: 600; }
    .note-modal-header .close,
    .note-modal-header .note-icon-close {
      color: <?php echo $noteModalHeaderText; ?>;
      opacity: 0.75;
    }
    .note-modal-header .close:hover,
    .note-modal-header .note-icon-close:hover { opacity: 1; }
</style>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Ticket <?php echo $ticket ? '#'.htmlspecialchars($ticket['ticket_no']??'') : 'Detail'; ?></h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/pages/ticket/list.php">Tickets</a></li><li class="breadcrumb-item active">Detail</li></ol></div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      
      <?php if ($ticket): ?>
      <div class="row">
        <!-- LEFT: Ticket Details -->
        <div class="col-md-4">
          <div class="card shadow-sm">
            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
              <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i> Ticket Detail</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group ticket-detail-list">
                        <li class="list-group-item">
                            <div class="ticket-label">Status Ticket</div>
                            <div class="ticket-value"><?php echo getTicketStatus($ticket); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Priority</div>
                            <div class="ticket-value"><?php echo getPriorityLabel($ticket['priority']); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Created</div>
                            <div class="ticket-value"><?php echo $ticket['created_at'] ? $ticket['created_at']->format('d-m-Y H:i') : '-'; ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Nama Pemohon</div>
                            <div class="ticket-value"><?php echo htmlspecialchars($ticket['creator_name'] ?: ($ticket['nama_lengkap'] ?? '-')); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Jabatan</div>
                            <div class="ticket-value"><?php echo htmlspecialchars($ticket['creator_jabatan'] ?: ($ticket['jabatan'] ?? '-')); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Departemen</div>
                            <div class="ticket-value"><?php echo htmlspecialchars($ticket['creator_dept'] ?: ($ticket['dept'] ?? '-')); ?></div>
                        </li>
                       
                        <li class="list-group-item">
                            <div class="ticket-label">Bagian</div>
                            <div class="ticket-value"><?php echo htmlspecialchars($ticket['creator_bagian'] ?: ($ticket['bagian'] ?? '-')); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Asset</div>
                            <div class="ticket-value">
                                <?php 
                                    if ($ticket['kode_asset_seq']) {
                                        echo '<strong>'.htmlspecialchars($ticket['kode_asset_seq']).'</strong><br>';
                                        echo '<small class="text-muted">'.htmlspecialchars($ticket['nama_kategori']??'').'</small>';
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Subject</div>
                            <div class="ticket-value"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                        </li>
                        <li class="list-group-item">
                            <div class="ticket-label">Assigned To</div>
                            <div class="ticket-value" id="assignedTechDisplay">
                                <?php 
                                  if ($ticket['assigned_to']) {
                                    $assignedName = $ticket['assigned_emp_name'] ?? '-';
                                    $assignedTheme = ticket_normalize_theme($ticket['assigned_theme'] ?? 'secondary');
                                    $assignedBadgeClass = 'bg-' . $assignedTheme;
                                    echo '<span class="badge '.htmlspecialchars($assignedBadgeClass, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($assignedName).'</span>';
                                  } else {
                                    echo '<span class="badge bg-warning">Belum di-assign</span>';
                                  }
                                ?>
                            </div>
                            <?php if ($isAdmin && empty($ticket['closed_at'])): ?>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" id="btnAssignTech">
                                    <i class="fas fa-user-plus"></i> <?php echo $ticket['assigned_to'] ? 'Reassign' : 'Assign'; ?> Teknisi
                                </button>
                            </div>
                            <?php endif; ?>
                        </li>

                        <?php if ($ticket['assigned_to']): ?>
                            <?php
                            // Check if user is assigned technician or Administrator
                            $canComplete = false;
                            if ($isAdmin) {
                                $canComplete = true;
                            } else {
                                // Check if current user is the assigned technician
                                $sqlCheckAssigned = "SELECT EmpId FROM dbo.SMUserMs WHERE UserName = ?";
                                $stmtCheckAssigned = sqlsrv_query($conn, $sqlCheckAssigned, [$_SESSION['UserName']]);
                                if ($stmtCheckAssigned && $rowCheckAssigned = sqlsrv_fetch_array($stmtCheckAssigned, SQLSRV_FETCH_ASSOC)) {
                                    $userEmpId = intval($rowCheckAssigned['EmpId'] ?? 0);
                                    if ($userEmpId > 0 && $userEmpId == intval($ticket['assigned_to'])) {
                                        $canComplete = true;
                                    }
                                }
                                if ($stmtCheckAssigned) sqlsrv_free_stmt($stmtCheckAssigned);
                            }
                            ?>
                            <?php if ($canComplete): ?>
                            <li class="list-group-item">
                            <div class="ticket-label">Status Penyelesaian</div>
                            <div class="ticket-value" id="completionStatus">
                                <?php if ($ticket['closed_at']): ?>
                                    <span class="badge bg-success">Selesai</span><br>
                                    <small class="text-muted"><?php echo $ticket['closed_at']->format('d-m-Y H:i'); ?></small>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success w-100" id="btnMarkComplete">
                                        <i class="fas fa-check"></i> Tandai Selesai
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- RIGHT: Chat Room -->
        <div class="col-md-8">
            <div class="card card-<?php echo $themeColor; ?> card-outline direct-chat direct-chat-<?php echo $themeColor; ?> shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-comments mr-2"></i> Chat Room</h3>
                </div>
                <div class="card-body">
                    <div class="direct-chat-messages" id="chatContainer">
                        <?php foreach($messages as $msg): 
                          $isMe = ($msg['sender_id'] == $_SESSION['UserId']);
                          $senderNamePlain = htmlspecialchars($msg['sender_name'] ?? 'User');
                          $senderNameDisplay = $senderNamePlain;
                          
                          // Check for Administrator
                          $senderGroup = trim($msg['sender_group'] ?? '');
                          if (strcasecmp($senderGroup, 'Administrator') === 0 && !$isMe) {
                              $senderNameDisplay = '<i class="fas fa-crown text-warning mr-1" title="Administrator"></i> ' . $senderNameDisplay;
                          }
                          
                          $time = $msg['created_at'] ? $msg['created_at']->format('d M H:i') : '';
                          $statusIcon = 'fa-check';
                          $statusClass = 'sent';
                          $statusText = 'Terkirim';
                          $readTime = '';
                          $readById = intval($msg['read_by'] ?? 0);
                          if ($readById && $readById !== intval($msg['sender_id'])) {
                            $statusIcon = 'fa-check-double';
                            $statusClass = 'read';
                            $readTime = ($msg['read_at'] instanceof DateTimeInterface) ? $msg['read_at']->format('d M H:i') : '';
                            $statusText = $readTime ? $readTime : 'Dibaca';
                          }
                        ?>
                        <div class="direct-chat-msg <?php echo $isMe ? 'right' : ''; ?>">
                            <div class="direct-chat-infos clearfix">
                                <span class="direct-chat-name float-<?php echo $isMe ? 'right' : 'left'; ?>"><?php echo $senderNameDisplay; ?></span>
                                <span class="direct-chat-timestamp float-<?php echo $isMe ? 'left' : 'right'; ?>"><?php echo $time; ?></span>
                            </div>
                            <img class="direct-chat-img" src="/gg_app/dist/img/user_default.png" alt="<?php echo $senderNamePlain; ?>" title="<?php echo $senderNamePlain; ?>">
                           
 <?php
                              // Determine bubble classes: outgoing messages get theme class
                              $bubbleClasses = 'direct-chat-text';
                              if ($isMe) {
                                $bubbleClasses .= ' bubble-me theme-'.htmlspecialchars($themeColor);
                              } else {
                                $bubbleClasses .= ' bubble-other';
                              }
                            ?>
							<div class="<?php echo $bubbleClasses; ?>">
                                <?php echo $msg['message_html'];  // Already HTML content ?>
                              <?php if (!empty($msg['attachments'])): ?>
                              <div class="mt-2">
                                <?php foreach($msg['attachments'] as $att): 
                                  $storedPath = $att['file_path'] ?? '';
                                  $filePath = (strpos($storedPath, '/gg_app/') === 0)
                                    ? $storedPath
                                    : '/gg_app/' . ltrim($storedPath, '/');
                                  $displayName = basename($filePath);
                                  $ext = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
                                  $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp']);
                                ?>
                                  <?php if ($isImage): ?>
                                    <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" class="mr-2 mb-2 d-inline-block">
                                      <img src="<?php echo htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="chat-attachment-thumb">
                                    </a>
                                  <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" class="chat-attachment-link mr-2 mb-2">
                                      <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($displayName); ?>
                                    </a>
                                  <?php endif; ?>
                                <?php endforeach; ?>
                              </div>
                              <?php endif; ?>
                            </div>
                            <?php if ($isMe): ?>
                            <div class="chat-status">
                              <span class="status-label <?php echo $statusClass; ?>">
                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                <?php if ($statusText): ?><span><?php echo htmlspecialchars($statusText); ?></span><?php endif; ?>
                              </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer">
                  <?php if (!empty($ticket['closed_at'])): ?>
                    <div class="alert alert-secondary text-center mb-0">
                      <i class="fas fa-lock mr-2"></i> Ticket sudah selesai percakapan berakhir.
                    </div>
                  <?php else: ?>
                  <form id="replyForm" enctype="multipart/form-data">
                    <textarea id="replyEditor" name="message" class="form-control"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div>
                        <label for="replyAttachments" class="btn btn-sm btn-outline-secondary mb-0">
                          <i class="fas fa-paperclip"></i> Attach Files
                        </label>
                        <input type="file" id="replyAttachments" name="attachments[]" multiple style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                        <small id="replyFilePreview" class="text-muted ml-2"></small>
                      </div>
                      <button type="button" id="btnSendReply" class="btn btn-theme">
                        <i class="fas fa-paper-plane"></i> Send
                      </button>
                    </div>
                  </form>
                  <?php endif; ?>
                </div>
            </div>
        </div>
      </div>

      <!-- Modal Assign Technician -->
      <div class="modal fade" id="modalAssignTech" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-<?php echo $themeColor; ?> text-white">
              <h5 class="modal-title">Assign Teknisi</h5>
              <button type="button" class="close" id="btnCloseModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for="selectTechnician">Pilih Teknisi IT:</label>
                <select class="form-select" id="selectTechnician">
                  <option value="">-- Pilih Teknisi --</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" id="btnCancelAssign" data-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-primary" id="btnConfirmAssign">Assign</button>
            </div>
          </div>
        </div>
      </div>

      <?php else: ?>
        <div class="alert alert-danger">Ticket not found or invalid ID.</div>
      <?php endif; ?>

    </div>
  </section>
</div>

<script>
  window.currentTicketId = <?php echo intval($id_ticket); ?>;
  window.hasAsset = <?php echo ($ticket && !empty($ticket['kode_asset_seq'])) ? 'true' : 'false'; ?>;
</script>
<?php
// Check if user has seen the Universal Zoom tutorial
$showUniversalZoomTutorial = true;
$tutorialFile = __DIR__ . '/../../config/tutorial_data.json';
if (file_exists($tutorialFile)) {
    $tutorialData = json_decode(file_get_contents($tutorialFile), true);
    if (isset($tutorialData['universal_zoom']) && is_array($tutorialData['universal_zoom'])) {
        if (in_array($_SESSION['UserName'], $tutorialData['universal_zoom'])) {
            $showUniversalZoomTutorial = false;
        }
    }
}
?>
<script>
    window.needUniversalZoomTutorial = <?php echo $showUniversalZoomTutorial ? 'true' : 'false'; ?>;
</script>
<script src="/gg_app/plugins/js/universal_zoom_tutorial.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/js/summernote-lite.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<!-- ===================================================
  9. JAVASCRIPT DETAIL TICKET
======================================================= -->
<script>
$(function(){
  var ticketId = <?php echo intval($id_ticket); ?>;
  var lastMessageId = <?php echo intval($maxMessageId); ?>;
  var chatImageModalInstance = null;
  var chatImageModalImg = null;
  
  
  // ZOOM & PAN VARIABLES
  var currentZoom = 1;
  var isDragging = false;
  var startX = 0, startY = 0;
  var translateX = 0, translateY = 0;
  var lastTranslateX = 0, lastTranslateY = 0;

  function updateZoom() {
      if (!chatImageModalImg) return;
      if (currentZoom < 1) currentZoom = 1;
      if (currentZoom > 5) currentZoom = 5;
      
      // Apply transform with translation and scale
      chatImageModalImg.style.transform = 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
      chatImageModalImg.style.transformOrigin = 'center center';
      
      // Update cursor based on state
      if (currentZoom > 1) {
          chatImageModalImg.style.cursor = isDragging ? 'grabbing' : 'grab';
      } else {
          chatImageModalImg.style.cursor = 'zoom-in';
          // Reset positioning if zoom is back to 1
          translateX = 0;
          translateY = 0;
          lastTranslateX = 0;
          lastTranslateY = 0;
      }
      
      // When dragging, we disable transition for immediate response
      chatImageModalImg.style.transition = isDragging ? 'none' : 'transform 0.1s ease-out';
  }

  function handleWheelZoom(e) {
      var modalEl = document.getElementById('ticketChatImageModal');
      if (!modalEl || !modalEl.classList.contains('show') || modalEl.style.display === 'none') {
          return;
      }
      e.preventDefault();
      
      if (e.deltaY < 0) {
          currentZoom += 0.25;
      } else {
          currentZoom -= 0.25;
      }
      // Re-clamp if zoomed out to 1, reset position
      if (currentZoom <= 1) {
          currentZoom = 1;
          translateX = 0;
          translateY = 0;
          lastTranslateX = 0;
          lastTranslateY = 0;
      }
      updateZoom();
  }
  
  // Panning Event Handlers
  function onMouseDown(e) {
      if (currentZoom <= 1) return;
      isDragging = true;
      startX = e.clientX;
      startY = e.clientY;
      chatImageModalImg.style.cursor = 'grabbing';
      chatImageModalImg.style.transition = 'none';
      e.preventDefault();
  }
  
  function onMouseMove(e) {
      if (!isDragging || currentZoom <= 1) return;
      e.preventDefault();
      var deltaX = e.clientX - startX;
      var deltaY = e.clientY - startY;
      
      translateX = lastTranslateX + deltaX;
      translateY = lastTranslateY + deltaY;
      
      updateZoom();
  }
  
  function onMouseUp(e) {
      if (!isDragging) return;
      isDragging = false;
      lastTranslateX = translateX;
      lastTranslateY = translateY;
      if (currentZoom > 1) {
        chatImageModalImg.style.cursor = 'grab';
      }
      updateZoom();
  }

  // Membuat instance modal preview gambar bila belum tersedia
  function ensureChatImageModal() {
    if (chatImageModalInstance && chatImageModalImg) { return chatImageModalInstance; }
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) { return null; }
    var modalEl = document.getElementById('ticketChatImageModal');
    if (!modalEl) { return null; }
    chatImageModalInstance = new bootstrap.Modal(modalEl);
    chatImageModalImg = modalEl.querySelector('#ticketChatImageModalImg');
    
    // Attach Zoom & Pan Listeners using jQuery
    $(modalEl).off('shown.bs.modal').on('shown.bs.modal', function () {
        currentZoom = 1;
        translateX = 0; 
        translateY = 0;
        lastTranslateX = 0; 
        lastTranslateY = 0;
        
        if (chatImageModalImg) {
             chatImageModalImg.style.transform = '';
             chatImageModalImg.style.transition = '';
             chatImageModalImg.style.cursor = 'zoom-in';
             
             // Add Mouse Events for Panning
             chatImageModalImg.addEventListener('mousedown', onMouseDown);
             window.addEventListener('mousemove', onMouseMove);
             window.addEventListener('mouseup', onMouseUp);
        }
        
        // Add Wheel Event
        window.removeEventListener('wheel', handleWheelZoom);
        window.addEventListener('wheel', handleWheelZoom, { passive: false });
    });

    $(modalEl).off('hidden.bs.modal').on('hidden.bs.modal', function () {
        window.removeEventListener('wheel', handleWheelZoom);
        if (chatImageModalImg) {
            chatImageModalImg.removeEventListener('mousedown', onMouseDown);
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
            
            chatImageModalImg.style.transform = '';
            // chatImageModalImg.style.border = ''; // Clean up any visual debug if left
        }
        currentZoom = 1;
        isDragging = false;
    });

    return chatImageModalInstance;
  }

  // Membuka gambar chat ke modal bootstrap atau tab baru
  function openChatImage(src) {
    if (!src) { return; }
    var modal = ensureChatImageModal();
    if (modal && chatImageModalImg) {
      chatImageModalImg.src = src;
      modal.show();
    } else {
      window.open(src, '_blank');
    }
  }

  // Membersihkan HTML Summernote agar tidak menyimpan tag kosong
  function tidySummernoteHtml(html) {
    if (!html) { return ''; }
    html = html.replace(/(<p>(?:&nbsp;|\s|<br\s*\/?>)*<\/p>\s*)+$/gi, '');
    html = html.replace(/(?:<br\s*\/?>\s*)+$/gi, '');
    return html.trim();
  }
  // Memperkaya gambar chat dengan lazy-load & preview modal
  function enhanceChatImages($scope) {
    var $target = ($scope && $scope.length) ? $scope : $('#chatContainer');
    var $images = $target.find('.direct-chat-text img');
    if ($target.is('.direct-chat-text img')) {
      $images = $images.add($target);
    }
    $images.each(function(){
      var $img = $(this);
      var src = $img.attr('src');
      if (!src) { return; }
      $img.attr({ loading: 'lazy', decoding: 'async' });

      var $link = $img.parent('a.chat-inline-img-link');
      if (!$link.length) {
        $img.wrap('<a class="chat-inline-img-link" href="'+src+'" target="_blank" rel="noopener noreferrer"></a>');
        $link = $img.parent('a.chat-inline-img-link');
      } else {
        $link.attr('href', src);
      }
      $link.off('click.chatImage').on('click.chatImage', function(e){
        e.preventDefault();
        e.stopPropagation();
        var imgUrl = $img.attr('src');
        
        // Check tutorial first (Gamified Unlock)
        if (window.UniversalZoomTutorial && typeof window.UniversalZoomTutorial.checkAndShow === 'function') {
            window.UniversalZoomTutorial.checkAndShow(function() {
                openChatImage(imgUrl);
            });
        } else {
            openChatImage(imgUrl);
        }
      });

      if (src.indexOf('data:') === 0 && !$img.data('blobReady') && !$img.data('blobBusy') && window.fetch) {
        $img.data('blobBusy', true);
        var original = src;
        fetch(original)
          .then(function(resp){ return resp.blob(); })
          .then(function(blob){
            var blobUrl = URL.createObjectURL(blob);
            var prevUrl = $img.data('blobUrl');
            if (prevUrl) { URL.revokeObjectURL(prevUrl); }
            $img.attr('src', blobUrl);
            $img.data('blobUrl', blobUrl);
            $img.data('blobReady', true);
            $img.data('blobBusy', false);
            var $parent = $img.parent('a.chat-inline-img-link');
            if ($parent.length) { $parent.attr('href', blobUrl); }
          })
          .catch(function(){
            $img.data('blobBusy', false);
          });
      }
    });
  }
  
  // Set global variable for chat bubble to exclude notifications for this ticket
  window.currentTicketId = ticketId;
  
    if (ticketId > 0) {
      $('#replyEditor').summernote({ placeholder:'Tulis balasan...', tabsize:2, height:100, toolbar: [
        ['style', ['bold', 'italic', 'underline', 'clear']],
        ['font', ['strikethrough']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link', 'picture']]
      ]});
      var $replyBtn = $('#btnSendReply');
      var replyBtnHtml = $replyBtn.html();
      var replyBusy = false;

      // Polling for new messages
      setInterval(function(){
        if(ticketId > 0){
          $.ajax({
            url: 'get_chat_updates.php',
            method: 'GET',
            data: { ticket_id: ticketId, last_message_id: lastMessageId },
            dataType: 'json'
          }).done(function(r){
            if(r && r.success && r.html){
              var $newContent = $(r.html);
              $('#chatContainer').append($newContent);
              enhanceChatImages($newContent);
              lastMessageId = r.max_message_id;
              var chatContainer = document.getElementById('chatContainer');
              if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
            }
          });
        }
      }, 3000);

      $('#replyAttachments').on('change', function(){
      var names = [].map.call(this.files, function(f){ return f.name; });
      $('#replyFilePreview').text(names.length ? names.length + ' file(s): ' + names.join(', ') : '');
      });
      
      // Scroll to bottom of chat
      var chatContainer = document.getElementById('chatContainer');
      if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
      enhanceChatImages();
      if (lastMessageId > 0 && ticketId > 0) {
      $.post('chat_mark_read.php', { ticket_id: ticketId, last_message_id: lastMessageId });
      }

      $replyBtn.on('click', function(){
        if (replyBusy) { return; }
        var msg = $('#replyEditor').summernote('code');
        if ($('#replyEditor').summernote('isEmpty')) {
        Swal.fire('Warning', 'Pesan tidak boleh kosong', 'warning');
        return;
        }
        var cleanedMsg = tidySummernoteHtml(msg);
        replyBusy = true;
        $replyBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Mengirim...');

        var formData = new FormData();
        formData.append('ticket_id', <?php echo $id_ticket; ?>);
        formData.append('message', cleanedMsg);
        var files = $('#replyAttachments')[0].files;
        for (var i=0; i<files.length; i++) {
        formData.append('attachments[]', files[i]);
        }
          
        $.ajax({
        url: 'reply_ticket.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
        }).done(function(r){
          if(r && r.success) {
            // Debug logging
            if(r.debug) {
              console.log('=== TICKET STATUS UPDATE DEBUG ===');
              console.log('Assigned To ID:', r.debug.assigned_to_id);
              console.log('Assigned Name:', r.debug.assigned_name);
              console.log('Sender Name:', r.debug.sender_name);
              console.log('Proses Status ID:', r.debug.proses_status_id);
              console.log('Proses Status Name:', r.debug.proses_status_name);
              console.log('Should Update:', r.debug.should_update);
              console.log('Match Type:', r.debug.match_type);
              console.log('Update Success:', r.debug.update_success);
              if(r.debug.sql_error) console.error('SQL Error:', r.debug.sql_error);
              if(r.debug.exception) console.error('Exception:', r.debug.exception);
            }
            $('#replyAttachments').val('');
            $('#replyFilePreview').text('');
            location.reload(); // Simple reload to show new message
          } else {
            Swal.fire('Error', r.message || 'Gagal mengirim pesan', 'error');
          }
          }).fail(function(){
          Swal.fire('Error', 'Koneksi bermasalah', 'error');
          }).always(function(){
          replyBusy = false;
          $replyBtn.prop('disabled', false).html(replyBtnHtml);
          });
      });

      // Assign Technician functionality (only if button is present)
      var assignModalEl = document.getElementById('modalAssignTech');
      var $btnAssignTech = $('#btnAssignTech');
      if ($btnAssignTech.length && assignModalEl) {
        var modalAssign = new bootstrap.Modal(assignModalEl);

        $btnAssignTech.on('click', function(){
          $.ajax({
            url: 'get_technicians.php',
            method: 'GET',
            dataType: 'json'
          }).done(function(r){
            if(r && r.success && r.data) {
              var $select = $('#selectTechnician');
              $select.find('option:not(:first)').remove();
              r.data.forEach(function(tech){
                var displayText = tech.nama_lengkap;
                if (tech.group_name) {
                  displayText += ' - ' + tech.group_name;
                }
                $select.append('<option value="'+tech.id_emp+'">'+displayText+'</option>');
              });
              modalAssign.show();
            } else {
              Swal.fire('Error', 'Gagal memuat daftar teknisi', 'error');
            }
          }).fail(function(){
            Swal.fire('Error', 'Koneksi bermasalah', 'error');
          });
        });

        $('#btnCloseModal, #btnCancelAssign').on('click', function(){
          modalAssign.hide();
        });

        $('#btnConfirmAssign').on('click', function(){
          var techId = $('#selectTechnician').val();
          if(!techId) {
            Swal.fire('Warning', 'Pilih teknisi terlebih dahulu', 'warning');
            return;
          }

          $.ajax({
            url: 'assign_technician.php',
            method: 'POST',
            data: { ticket_id: <?php echo $id_ticket; ?>, assigned_to: techId },
            dataType: 'json'
          }).done(function(r){
            if(r && r.success) {
              Swal.fire('Success', 'Teknisi berhasil di-assign', 'success');
              modalAssign.hide();
              var badgeTheme = r.technician_theme ? ('bg-'+r.technician_theme) : 'bg-success';
              $('#assignedTechDisplay').html('<span class="badge '+badgeTheme+'">'+r.technician_name+'</span>');
              $btnAssignTech.html('<i class="fas fa-user-plus"></i> Reassign Teknisi');
            } else {
              Swal.fire('Error', r.message || 'Gagal assign teknisi', 'error');
            }
          }).fail(function(){
            Swal.fire('Error', 'Koneksi bermasalah', 'error');
          });
        });
      }

      // Mark as Complete Handler
      // Mark as Complete Handler
      $('#btnMarkComplete').on('click', function(){
        if (window.hasAsset) {
            // Logic if asset exists: Show prompt for maintenance remarks
            Swal.fire({
                title: 'Selesaikan Ticket & Update Asset',
                html: `
                    <p>Ticket ini terhubung dengan asset. Masukkan keterangan maintenance untuk riwayat asset.</p>
                    <textarea id="maintenanceNote" class="form-control" rows="3" placeholder="Contoh: Ganti SSD, Install Ulang, dll..."></textarea>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Selesai & Simpan',
                cancelButtonText: 'Batal',
                preConfirm: () => {
                    const note = Swal.getPopup().querySelector('#maintenanceNote').value;
                    if (!note) {
                        Swal.showValidationMessage('Keterangan maintenance wajib diisi');
                    }
                    return { note: note };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const note = result.value.note;
                    $.ajax({
                        url: 'close_ticket.php',
                        method: 'POST',
                        data: { ticket_id: <?php echo $id_ticket; ?>, maintenance_note: note },
                        dataType: 'json'
                    }).done(function(r){
                        if(r && r.success) {
                            Swal.fire('Success', 'Ticket selesai dan data asset diupdate', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', r.message || 'Gagal menyelesaikan ticket', 'error');
                        }
                    }).fail(function(){
                        Swal.fire('Error', 'Koneksi bermasalah', 'error');
                    });
                }
            });
        } else {
            // Original logic for non-asset tickets
            Swal.fire({
            title: 'Selesaikan Ticket?',
            text: 'Ticket akan ditandai sebagai selesai.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Selesai',
            cancelButtonText: 'Batal'
            }).then((res) => {
            if(res.isConfirmed) {
                $.ajax({
                url: 'close_ticket.php',
                method: 'POST',
                data: { ticket_id: <?php echo $id_ticket; ?> },
                dataType: 'json'
                }).done(function(r){
                if(r && r.success) {
                    Swal.fire('Success', 'Ticket selesai', 'success').then(() => {
                    location.reload();
                    });
                } else {
                    Swal.fire('Error', r.message || 'Gagal menyelesaikan ticket', 'error');
                }
                }).fail(function(){
                Swal.fire('Error', 'Koneksi bermasalah', 'error');
                });
            }
            });
        }
      });
    }
});
</script>
