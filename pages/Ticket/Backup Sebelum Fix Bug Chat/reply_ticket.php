<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success'=>false, 'message'=>''];
if (!isset($_SESSION['UserId']) || !$conn) { 
    $resp['message']='Silakan login'; 
    echo json_encode($resp); 
    exit; 
}

try {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $message_html = $_POST['message'] ?? '';

    if ($ticket_id <= 0) throw new Exception('Invalid Ticket ID');
    if (trim($message_html) === '') throw new Exception('Pesan tidak boleh kosong');

    $ticketNo = '';
    $ticketStmt = sqlsrv_query($conn, "SELECT ticket_no FROM dbo.tickets WHERE ticket_id = ?", [ $ticket_id ]);
    if ($ticketStmt && ($ticketRow = sqlsrv_fetch_array($ticketStmt, SQLSRV_FETCH_ASSOC))) {
        $ticketNo = $ticketRow['ticket_no'] ?? '';
    }
    if ($ticketStmt) { sqlsrv_free_stmt($ticketStmt); }
    if ($ticketNo === '') { throw new Exception('Ticket tidak ditemukan'); }

    sqlsrv_begin_transaction($conn);

    // ===================================================
    // 2. SIMPAN PESAN BALASAN
    // ===================================================
    $sql = "INSERT INTO dbo.ticket_messages (ticket_id, sender_id, sender_name, message_html, created_at, created_by) 
            OUTPUT INSERTED.message_id AS message_id
            VALUES (?, ?, ?, ?, GETDATE(), ?)";
    $params = [
        $ticket_id, 
        $_SESSION['UserId'], 
        ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']), 
        $message_html, 
        $_SESSION['UserId']
    ];
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) throw new Exception('Gagal mengirim pesan');
    $messageId = null;
    if ($rowMsg = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $messageId = intval($rowMsg['message_id'] ?? 0) ?: null;
    }

    $ticketFolderSlug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticketNo);
    if ($ticketFolderSlug === '') { $ticketFolderSlug = 'ticket_' . $ticket_id; }

    // ===================================================
    // 3. PROSES LAMPIRAN BALASAN
    // ===================================================
    if (!empty($_FILES['attachments']) && isset($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        $baseDir = __DIR__ . '/../../uploads/tickets/';
        $basePublic = '/gg_app/uploads/tickets/';
        $ticketDir = $baseDir . $ticketFolderSlug . DIRECTORY_SEPARATOR;
        if (!is_dir($ticketDir)) { @mkdir($ticketDir, 0775, true); }
        $messageFolder = $messageId ? ('msg_' . $messageId) : ('msg_' . time());
        $targetDir = $ticketDir . $messageFolder . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
        $publicDir = $basePublic . $ticketFolderSlug . '/' . $messageFolder . '/';

        $names = $_FILES['attachments']['name'];
        $tmps  = $_FILES['attachments']['tmp_name'];
        $sizes = $_FILES['attachments']['size'];
        $types = $_FILES['attachments']['type'];
        $errors= $_FILES['attachments']['error'];
        for ($i=0; $i<count($names); $i++) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($tmps[$i])) continue;
            $original = basename($names[$i]);
            if ($original === '') { $original = 'attachment_' . ($i+1); }
            $dest = $targetDir . $original;
            $dup = 1;
            while (file_exists($dest)) {
                $info = pathinfo($original);
                $base = $info['filename'] ?? 'file';
                $ext  = isset($info['extension']) ? ('.' . $info['extension']) : '';
                $newName = $base . '_' . $dup . $ext;
                $dest = $targetDir . $newName;
                $original = $newName;
                $dup++;
            }
            if (@move_uploaded_file($tmps[$i], $dest)) {
                $relative = $publicDir . $original;
                $attSql = "INSERT INTO dbo.ticket_attachments (ticket_id, message_id, file_path, mime_type, size, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,GETDATE())";
                $attParams = [ $ticket_id, $messageId, $relative, $types[$i] ?? null, intval($sizes[$i] ?? 0), $_SESSION['UserId'] ];
                sqlsrv_query($conn, $attSql, $attParams);
            }
        }
    }

    // ===================================================
    // 4. UPDATE STATUS OTOMATIS JIKA TEKNISI MEMBALAS
    // ===================================================
    $debugInfo = [];
    try {
        // Get ticket's assigned_to
        $stT = sqlsrv_query($conn, "SELECT assigned_to FROM dbo.tickets WHERE ticket_id = ?", [ $ticket_id ]);
        $assignedId = null;
        if ($stT) { 
            $rowT = sqlsrv_fetch_array($stT, SQLSRV_FETCH_ASSOC); 
            $assignedId = $rowT['assigned_to'] ?? null;
            $debugInfo['assigned_to_id'] = $assignedId;
            sqlsrv_free_stmt($stT); 
        }
        
        // Get assigned technician's name
        $assignedName = null;
        if (!empty($assignedId)) {
            $stEmp = sqlsrv_query($conn, "SELECT nama_lengkap FROM dbo.m_emp WHERE id_emp = ?", [ intval($assignedId) ]);
            if ($stEmp) { 
                $rowEmp = sqlsrv_fetch_array($stEmp, SQLSRV_FETCH_ASSOC); 
                $assignedName = $rowEmp['nama_lengkap'] ?? null;
                $debugInfo['assigned_name'] = $assignedName;
                sqlsrv_free_stmt($stEmp); 
            }
        }
        
        // Find 'Proses' status from ticket_statuses (NOT m_status which is for assets)
        $statusProsesId = null;
        $stS = sqlsrv_query($conn, "SELECT TOP 1 status_id, status_name FROM dbo.ticket_statuses WHERE status_name = 'Proses' ORDER BY status_id ASC");
        if ($stS) { 
            $rowS = sqlsrv_fetch_array($stS, SQLSRV_FETCH_ASSOC); 
            $statusProsesId = $rowS['status_id'] ?? null;
            $debugInfo['proses_status_id'] = $statusProsesId;
            $debugInfo['proses_status_name'] = $rowS['status_name'] ?? null;
            sqlsrv_free_stmt($stS); 
        }

        // Check if sender matches assigned technician
        $senderName = trim($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);
        $assignedName = $assignedName ? trim($assignedName) : null;
        $debugInfo['sender_name'] = $senderName;
        
        $shouldUpdate = false;
        if ($assignedName && strcasecmp($senderName, $assignedName) === 0) {
            $shouldUpdate = true;
            $debugInfo['match_type'] = 'exact_name';
        } else if (!empty($assignedId)) {
            // Fallback: check if sender is IT staff
            $stIT = sqlsrv_query($conn, "SELECT TOP 1 1 FROM dbo.m_emp WHERE nama_lengkap = ? AND id_bagian IN (SELECT id_bagian FROM dbo.m_bag WHERE nama_bag = 'Information Technology')", [ $senderName ]);
            if ($stIT && sqlsrv_fetch_array($stIT, SQLSRV_FETCH_ASSOC)) { 
                $shouldUpdate = true;
                $debugInfo['match_type'] = 'it_staff';
                sqlsrv_free_stmt($stIT);
            }
        }
        
        $debugInfo['should_update'] = $shouldUpdate;
        
        // Execute update
        if ($shouldUpdate && $statusProsesId) {
            $upd = sqlsrv_query($conn, "UPDATE dbo.tickets SET status_id = ?, updated_at = GETDATE() WHERE ticket_id = ?", [ intval($statusProsesId), $ticket_id ]);
            $debugInfo['update_success'] = ($upd !== false);
            if ($upd === false) {
                $debugInfo['sql_error'] = sqlsrv_errors();
            }
        }
    } catch(Exception $e2) { 
        $debugInfo['exception'] = $e2->getMessage();
    }

    sqlsrv_commit($conn);
    $resp['success'] = true;
    $resp['message'] = 'Pesan terkirim';
    $resp['debug'] = $debugInfo;
} catch (Exception $e) {
    if ($conn) { @sqlsrv_rollback($conn); }
    $resp['success'] = false;
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
