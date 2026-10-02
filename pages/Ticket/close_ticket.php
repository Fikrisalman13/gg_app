<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$resp = ['success' => false, 'message' => ''];

if (!isset($_SESSION['UserId'])) {
    $resp['message'] = 'Unauthorized';
    echo json_encode($resp);
    exit;
}

try {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    if ($ticket_id <= 0) throw new Exception('Invalid Ticket ID');

    // ===================================================
    // 2. UPDATE STATUS TICKET KE CLOSED
    // ===================================================
    // Get 'Selesai' status_id from ticket_statuses
    $selesaiStatusId = null;
    $stmtStatus = sqlsrv_query($conn, "SELECT status_id FROM dbo.ticket_statuses WHERE status_name = 'Selesai'");
    if ($stmtStatus && $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)) {
        $selesaiStatusId = $rowStatus['status_id'];
    }
    if ($stmtStatus) sqlsrv_free_stmt($stmtStatus);

    // Update closed_at, status_id, closed_by, updated_at, dan updated_by
    $sql = "UPDATE dbo.tickets 
            SET closed_at = GETDATE(), 
                closed_by = ?,
                status_id = ?,
                updated_at = GETDATE(),
                updated_by = ?
            WHERE ticket_id = ?";
    
    $stmt = sqlsrv_query($conn, $sql, [
        $_SESSION['UserId'], 
        $selesaiStatusId, 
        $_SESSION['UserId'], 
        $ticket_id
    ]);
    
    if ($stmt === false) {
        throw new Exception('Gagal menyelesaikan ticket');
    }
    // ===================================================
    // 3. KEMBALIKAN STATUS ASET KE 'USED'
    // ===================================================
    $stmtAssets = sqlsrv_query($conn, "SELECT id_asset FROM dbo.ticket_assets WHERE ticket_id = ?", [$ticket_id]);
    if ($stmtAssets) {
        $maintenance_note = $_POST['maintenance_note'] ?? '';
        while ($rowA = sqlsrv_fetch_array($stmtAssets, SQLSRV_FETCH_ASSOC)) {
            $aid = $rowA['id_asset'];
            
            // Update status asset
            sqlsrv_query($conn, "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?", 
                [$_SESSION['UserName'], $aid]);

            // Insert history if specific maintenance finish
            if (!empty($maintenance_note)) {
                $sqlHist = "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) 
                            VALUES (?, 'Maintenance', 'Used', ?, 'status asset', ?, GETDATE())";
                sqlsrv_query($conn, $sqlHist, [$aid, $maintenance_note, $_SESSION['UserName']]);
            }
        }
        sqlsrv_free_stmt($stmtAssets);
    }
    
    $resp['success'] = true;
    $resp['message'] = 'Ticket berhasil diselesaikan';
} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
