<?php
// delete_scrap.php - Hapus Data Scrap
session_start();

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

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 118);
if ($permissions['CanDelete'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data scrap.";
    header('Location: scrap.php');
    exit;
}

// Get scrap ID from URL
$scrapId = $_GET['id'] ?? 0;
if (!$scrapId) {
    $_SESSION['error'] = "ID Scrap tidak valid!";
    header('Location: scrap.php');
    exit;
}

try {
    // Begin transaction
    sqlsrv_begin_transaction($conn);

    // Get scrap data first to get padder_id
    $getScrapSql = "SELECT padder_id FROM dbo.pad_t_scrap WHERE id = ?";
    $getStmt = sqlsrv_query($conn, $getScrapSql, [$scrapId]);
    
    if (!$getStmt || !sqlsrv_fetch($getStmt)) {
        throw new Exception("Data scrap tidak ditemukan!");
    }
    
    $padderId = sqlsrv_get_field($getStmt, 0);
    sqlsrv_free_stmt($getStmt);

    // Delete scrap photos first
    $deletePhotosSql = "DELETE FROM dbo.pad_t_scrap_files WHERE scrap_id = ?";
    $photoStmt = sqlsrv_query($conn, $deletePhotosSql, [$scrapId]);
    if (!$photoStmt) {
        throw new Exception("Gagal menghapus foto scrap!");
    }

    // Delete scrap record
    $deleteScrapSql = "DELETE FROM dbo.pad_t_scrap WHERE id = ?";
    $scrapStmt = sqlsrv_query($conn, $deleteScrapSql, [$scrapId]);
    if (!$scrapStmt) {
        throw new Exception("Gagal menghapus data scrap!");
    }

    // Update padder status back to previous state (READY)
    $updatePadderSql = "UPDATE dbo.pad_m_padder 
                       SET status = 'READY', updated_at = GETDATE(), updated_by = ?
                       WHERE padder_id = ?";
    $updateParams = [$_SESSION['UserName'], $padderId];
    $updateStmt = sqlsrv_query($conn, $updatePadderSql, $updateParams);
    if (!$updateStmt) {
        throw new Exception("Gagal update status padder!");
    }

    // Add status log
    $logSql = "INSERT INTO dbo.pad_status_log 
              (padder_id, status, changed_by, remarks) 
              VALUES (?, 'READY', ?, 'Dibatalkan scrap - data dihapus')";
    $logParams = [$padderId, $_SESSION['UserName']];
    $logStmt = sqlsrv_query($conn, $logSql, $logParams);

    // Commit transaction
    sqlsrv_commit($conn);

    $_SESSION['success'] = "Data scrap berhasil dihapus! Padder kembali berstatus READY.";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

header('Location: scrap.php');
exit;
?>