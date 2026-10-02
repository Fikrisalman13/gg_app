<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success' => false, 'message' => ''];

if (!isset($_SESSION['UserId']) || !$conn) {
    $resp['message'] = 'Silakan login terlebih dahulu';
    echo json_encode($resp);
    exit;
}

// Check admin permission (sementara dengan group Administrator)
$isAdmin = false;
$sqlAdmin = "SELECT b.GroupName FROM dbo.SMUserMs a LEFT JOIN dbo.SMUserGroup b ON a.GroupId = b.GroupId WHERE a.UserId = ?";
$stmtAdmin = sqlsrv_query($conn, $sqlAdmin, [$_SESSION['UserId']]);
if ($stmtAdmin && $rowAdmin = sqlsrv_fetch_array($stmtAdmin, SQLSRV_FETCH_ASSOC)) {
    if (strcasecmp(trim($rowAdmin['GroupName']), 'Administrator') === 0) {
        $isAdmin = true;
    }
}
if ($stmtAdmin) sqlsrv_free_stmt($stmtAdmin);

if (!$isAdmin) {
    $resp['message'] = 'Akses ditolak. Hanya Administrator yang dapat mengubah data ticket.';
    echo json_encode($resp);
    exit;
}

try {
    $ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $assetId = isset($_POST['asset_id']) ? intval($_POST['asset_id']) : 0; // 0 means no asset or unselected
    $assignTo = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0; // 0 means unassigned

    if ($ticketId <= 0) throw new Exception("Ticket ID tidak valid");
    if ($subject === '') throw new Exception("Subject tidak boleh kosong");

    sqlsrv_begin_transaction($conn);

    // Get ticket_no for history log
    $sqlTicket = "SELECT ticket_no FROM dbo.tickets WHERE ticket_id = ?";
    $stmtTicket = sqlsrv_query($conn, $sqlTicket, [$ticketId]);
    $ticketRow = $stmtTicket ? sqlsrv_fetch_array($stmtTicket, SQLSRV_FETCH_ASSOC) : null;
    $ticketNo = $ticketRow ? $ticketRow['ticket_no'] : '';
    if ($stmtTicket) sqlsrv_free_stmt($stmtTicket);

    // ===================================================
    // 2. UPDATE DATA DASAR TICKET
    // ===================================================
    $sqlUpd = "UPDATE dbo.tickets SET subject = ?, assigned_to = ?, updated_at = GETDATE(), updated_by = ? WHERE ticket_id = ?";
    $paramsUpd = [$subject, ($assignTo > 0 ? $assignTo : null), $_SESSION['UserId'], $ticketId];
    $stmtUpd = sqlsrv_query($conn, $sqlUpd, $paramsUpd);
    if ($stmtUpd === false) throw new Exception("Gagal update ticket: " . print_r(sqlsrv_errors(), true));

    // ===================================================
    // 3. SINKRONISASI RELASI ASET
    // ===================================================
    // Current Asset
    $sqlCurr = "SELECT id_asset FROM dbo.ticket_assets WHERE ticket_id = ?";
    $stmtCurr = sqlsrv_query($conn, $sqlCurr, [$ticketId]);
    $currRow = $stmtCurr ? sqlsrv_fetch_array($stmtCurr, SQLSRV_FETCH_ASSOC) : null;
    $currentAssetId = $currRow ? intval($currRow['id_asset']) : 0;
    if ($stmtCurr) sqlsrv_free_stmt($stmtCurr);

    /*
     Logic Change:
     - If assetId != currentAssetId:
       1. Revert currentAssetId to status 1 (Used)
       2. Delete from ticket_assets WHERE ticket_id
       3. If new assetId > 0:
          - Insert into ticket_assets
          - Update new assetId to status 6 (Maintenance)
    */

    if ($assetId !== $currentAssetId) {
        // Revert old asset
        if ($currentAssetId > 0) {
             $sqlRev = "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
             sqlsrv_query($conn, $sqlRev, [$_SESSION['UserName'], $currentAssetId]);
             
             // Add history log: Maintenance -> Used (asset removed from ticket)
             $historyNote = "Asset Dilepas Dari Ticket " . $ticketNo;
             $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) 
                         VALUES (?, 'Maintenance', 'Used', ?, 'status asset', ?, GETDATE())";
             sqlsrv_query($conn, $sqlHist, [$currentAssetId, $historyNote, $_SESSION['UserName']]);
             
             // Remove link
             $sqlDel = "DELETE FROM dbo.ticket_assets WHERE ticket_id = ?";
             sqlsrv_query($conn, $sqlDel, [$ticketId]);
        }

        // Apply new asset
        if ($assetId > 0) {
            // Link new asset
            $sqlIns = "INSERT INTO dbo.ticket_assets (ticket_id, id_asset, qty, created_at, created_by) VALUES (?, ?, 1, GETDATE(), ?)";
            $stmtIns = sqlsrv_query($conn, $sqlIns, [$ticketId, $assetId, $_SESSION['UserId']]);
            if ($stmtIns === false) throw new Exception("Gagal link asset baru");

            // Update status new asset
            $sqlSet = "UPDATE dbo.m_asset SET id_status = 6, upddate = GETDATE(), upduser = ? WHERE id_asset = ?";
            sqlsrv_query($conn, $sqlSet, [$_SESSION['UserName'], $assetId]);
            
            // Add history log: Used -> Maintenance (asset added to ticket)
            $historyNote = "Asset Sedang Terhubung Dengan Ticket " . $ticketNo;
            $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) 
                        VALUES (?, 'Used', 'Maintenance', ?, 'status asset', ?, GETDATE())";
            sqlsrv_query($conn, $sqlHist, [$assetId, $historyNote, $_SESSION['UserName']]);
        }
    }

    // ===================================================
    // 4. PENYESUAIAN STATUS TICKET
    // ===================================================
    // If Assign To changed from NULL to Value -> Status potentially becomes Process
    // If Assign To changed from Value to NULL -> Status potentially becomes Open
    // However, existing logic in ticket_serverside derives status dynamically if not Closed.
    // So we might not need to explicit update ticket_statuses unless we store status_id strictly.
    // Let's check save_ticket.php: it inserts status_id based on 'Open'.
    // Let's check standard logic: usually assigning moves to 'Process'.
    
    // We will update status_id to 'Proses' if assigned, 'Open' if unassigned, ONLY IF not closed.
    // Check if closed
    $sqlCheck = "SELECT closed_at FROM dbo.tickets WHERE ticket_id = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$ticketId]);
    $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    $isClosed = ($rowCheck && !empty($rowCheck['closed_at']));

    if (!$isClosed) {
        $targetStatusId = null;

        if ($assignTo <= 0) {
            // Hanya kembalikan ke status Open saat tiket tidak di-assign.
            $sSql = "SELECT TOP 1 status_id FROM dbo.ticket_statuses WHERE status_name = 'Open'";
            $sStmt = sqlsrv_query($conn, $sSql);
            if ($sStmt && $sRow = sqlsrv_fetch_array($sStmt, SQLSRV_FETCH_ASSOC)) {
                $targetStatusId = $sRow['status_id'];
            }
        }

        // Saat assign teknisi melalui Quick Edit, biarkan status tetap apa adanya.
        // Status akan berpindah ke 'Proses' setelah teknisi membalas via chat (lihat reply_ticket.php).

        if ($targetStatusId) {
            sqlsrv_query($conn, "UPDATE dbo.tickets SET status_id = ? WHERE ticket_id = ?", [$targetStatusId, $ticketId]);
        }
    }


    sqlsrv_commit($conn);
    $resp['success'] = true;
    $resp['message'] = 'Ticket berhasil diperbarui';

} catch (Exception $e) {
    if ($conn) @sqlsrv_rollback($conn);
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
