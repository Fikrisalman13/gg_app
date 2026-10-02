<?php
// complete_maintenance.php - Selesaikan Maintenance dan kembalikan status ke IN_USE
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Check permission
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 116);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data maintenance.";
    header('Location: maintenance.php');
    exit;
}

$maintenanceId = $_GET['id'] ?? '';
if (empty($maintenanceId)) {
    $_SESSION['error'] = "ID Maintenance tidak valid!";
    header('Location: maintenance.php');
    exit;
}

try {
    // Mulai transaction
    sqlsrv_begin_transaction($conn);
    
    // 1. Ambil data maintenance dan padder_id
    $sqlGetData = "SELECT m.padder_id, p.padder_name 
                   FROM pad_t_maintenance m 
                   JOIN pad_m_padder p ON m.padder_id = p.padder_id 
                   WHERE m.id = ?";
    $stmtGetData = sqlsrv_query($conn, $sqlGetData, [$maintenanceId]);
    
    if (!$stmtGetData || !$row = sqlsrv_fetch_array($stmtGetData, SQLSRV_FETCH_ASSOC)) {
        throw new Exception("Data maintenance tidak ditemukan!");
    }
    
    $padderId = $row['padder_id'];
    $padderName = $row['padder_name'];
    
    // 2. Update status padder ke IN_USE
    $sqlUpdatePadder = "UPDATE pad_m_padder 
                        SET status = 'IN_USE', 
                            updated_at = GETDATE(), 
                            updated_by = ? 
                        WHERE padder_id = ?";
    $stmtUpdatePadder = sqlsrv_query($conn, $sqlUpdatePadder, [$_SESSION['UserName'], $padderId]);
    
    if (!$stmtUpdatePadder) {
        throw new Exception("Gagal mengupdate status padder!");
    }
    
    // 3. Insert ke status log
    $sqlInsertLog = "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) 
                     VALUES (?, 'IN_USE', ?, 'Maintenance selesai - kembali ke penggunaan')";
    $stmtInsertLog = sqlsrv_query($conn, $sqlInsertLog, [$padderId, $_SESSION['UserName']]);
    
    if (!$stmtInsertLog) {
        throw new Exception("Gagal mencatat riwayat status!");
    }
    
    // Commit transaction
    sqlsrv_commit($conn);
    
    $_SESSION['success'] = "Maintenance berhasil diselesaikan! Padder " . $padderName . " kembali ke status IN_USE.";
    
} catch (Exception $e) {
    // Rollback transaction jika ada error
    sqlsrv_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

// Redirect kembali ke halaman maintenance
header('Location: maintenance.php');
exit;
?>