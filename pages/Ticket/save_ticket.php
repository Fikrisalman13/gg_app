<?php
// ===================================================
// 1. INISIALISASI DAN VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success'=>false, 'message'=>''];
if (!isset($_SESSION['UserId']) || !$conn) { $resp['message']='Silakan login'; echo json_encode($resp); exit; }

try {
    $subject = trim($_POST['subject'] ?? '');
    $priority = intval($_POST['priority'] ?? 2);
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $message_html = $_POST['message_html'] ?? '';
    if ($subject==='') throw new Exception('Subject wajib diisi');
    // Asset is optional: do not enforce

    sqlsrv_begin_transaction($conn);

    // ===================================================
    // 2. GENERATE NOMOR TICKET
    // ===================================================
    $today = date('Y-m-d');
    $sel = sqlsrv_query($conn, "SELECT last_seq FROM dbo.ticket_counters WHERE counter_date = ?", [ $today ]);
    $seq = 0; $row = $sel ? sqlsrv_fetch_array($sel, SQLSRV_FETCH_ASSOC) : null; if ($sel) sqlsrv_free_stmt($sel);
    if ($row) { $seq = intval($row['last_seq']) + 1; $upd = sqlsrv_query($conn, "UPDATE dbo.ticket_counters SET last_seq = ? WHERE counter_date = ?", [ $seq, $today ]); }
    else { $seq = 1; $ins = sqlsrv_query($conn, "INSERT INTO dbo.ticket_counters(counter_date,last_seq) VALUES(?,?)", [ $today, $seq ]); }
    $ticket_no = sprintf('TKT-%s-%04d', date('d-m-Y'), $seq);

    // ===================================================
    // 3. SNAPSHOT DATA PEMOHON
    // ===================================================
    $creator_jabatan = ''; $creator_dept = ''; $creator_bagian = '';
    $creator_name = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
    $sqlC = "SELECT j.jabatan, d.dept, b.bagian 
             FROM dbo.m_emp e
             LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
             LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
             LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
             LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
             WHERE e.nama_lengkap = ?";
    $stmtC = sqlsrv_query($conn, $sqlC, [ $creator_name ]);
    if ($stmtC && $rowC = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC)) {
        $creator_jabatan = $rowC['jabatan'] ?? '';
        $creator_dept = $rowC['dept'] ?? '';
        $creator_bagian = $rowC['bagian'] ?? '';
    }

    // ===================================================
    // 4. STATUS AWAL TICKET
    // ===================================================
    $openStatusId = 1;
    $sqlStatus = "SELECT TOP 1 status_id FROM dbo.ticket_statuses WHERE status_name = 'Open' ORDER BY status_id ASC";
    $stmtStatus = sqlsrv_query($conn, $sqlStatus);
    if ($stmtStatus && $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)) {
        $openStatusId = $rowStatus['status_id'];
    }

    // ===================================================
    // 5. SIMPAN TICKET UTAMA
    // ===================================================
    $insT = sqlsrv_query($conn, "INSERT INTO dbo.tickets (ticket_no, creator_id, creator_name, subject, priority, status_id, created_at, created_by, creator_jabatan, creator_dept, creator_bagian) VALUES (?,?,?,?,?,?,GETDATE(),?,?,?,?)",
      [ $ticket_no, $_SESSION['UserId'], $creator_name, $subject, $priority, $openStatusId, $_SESSION['UserId'], $creator_jabatan, $creator_dept, $creator_bagian ]);
    if ($insT === false) throw new Exception('Gagal membuat ticket: ' . print_r(sqlsrv_errors(), true));
    // Get new ticket_id
    $selId = sqlsrv_query($conn, "SELECT ticket_id FROM dbo.tickets WHERE ticket_no = ?", [ $ticket_no ]);
    $tidRow = $selId ? sqlsrv_fetch_array($selId, SQLSRV_FETCH_ASSOC) : null; if ($selId) sqlsrv_free_stmt($selId);
    if (!$tidRow) throw new Exception('Ticket tidak ditemukan sesudah insert');
    $ticket_id = intval($tidRow['ticket_id']);

    // ===================================================
    // 6. RELASI ASET (OPSIONAL)
    // ===================================================
    if ($asset_id > 0) {
        $insMap = sqlsrv_query($conn, "INSERT INTO dbo.ticket_assets (ticket_id, id_asset, qty, created_at, created_by) VALUES (?,?,1,GETDATE(),?)",
          [ $ticket_id, $asset_id, $_SESSION['UserId'] ]);
        
        // Update asset status to Maintenance (6)
        $updAsset = sqlsrv_query($conn, "UPDATE dbo.m_asset SET id_status = 6, upddate = GETDATE(), upduser = ? WHERE id_asset = ?", 
          [ $_SESSION['UserName'], $asset_id ]);
          
        // Add history log: Used -> Maintenance with ticket info
        $historyNote = "Asset Sedang Terhubung Dengan Ticket " . $ticket_no;
        $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) 
                    VALUES (?, 'Used', 'Maintenance', ?, 'status asset', ?, GETDATE())";
        sqlsrv_query($conn, $sqlHist, [$asset_id, $historyNote, $_SESSION['UserName']]);
    }

    // ===================================================
    // 7. SIMPAN PESAN PERTAMA
    // ===================================================
    $messageId = null;
    if ($message_html) {
      $sqlMsg = "INSERT INTO dbo.ticket_messages (ticket_id, sender_id, sender_name, message_html, created_at, created_by)
                 OUTPUT INSERTED.message_id AS message_id
                 VALUES (?,?,?,?,GETDATE(),?)";
      $stmtMsg = sqlsrv_query($conn, $sqlMsg,
        [ $ticket_id, $_SESSION['UserId'], ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']), $message_html, $_SESSION['UserId'] ]);
      if ($stmtMsg && $rowMsg = sqlsrv_fetch_array($stmtMsg, SQLSRV_FETCH_ASSOC)) {
        $messageId = intval($rowMsg['message_id'] ?? 0) ?: null;
      }
    }

    $ticketFolderSlug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticket_no);
    if ($ticketFolderSlug === '') { $ticketFolderSlug = 'ticket_' . $ticket_id; }

    // ===================================================
    // 8. UPLOAD LAMPIRAN
    // ===================================================
    if (!empty($_FILES['attachments']) && isset($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
      $baseDir = __DIR__ . '/../../uploads/tickets/';
      $basePublic = '/gg_app/uploads/tickets/';
      $ticketDir = $baseDir . $ticketFolderSlug . DIRECTORY_SEPARATOR;
      if (!is_dir($ticketDir)) { @mkdir($ticketDir, 0775, true); }
      $messageFolder = $messageId ? ('msg_' . $messageId) : 'initial';
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
          $dest = $targetDir . $base . '_' . $dup . $ext;
          $original = $base . '_' . $dup . $ext;
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

    sqlsrv_commit($conn);
    $resp['success']=true; $resp['ticket_id']=$ticket_id; $resp['ticket_no']=$ticket_no; $resp['message']='Ticket dibuat';
} catch (Exception $e) {
    if ($conn) @sqlsrv_rollback($conn);
    $resp['success']=false; $resp['message']=$e->getMessage();
}

echo json_encode($resp);
