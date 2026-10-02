<?php
// delete_padder_spec.php - Hapus Spesifikasi Padder
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

// helper: ambil permission user untuk menu Padder Spec
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

// Ambil permission (sesuaikan MenuId dengan menu Padder Spec di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 111);
if ($permissions['CanDelete'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus spesifikasi padder.";
    header('Location: m_padder_spec.php');
    exit;
}

// ====== Process Delete ======
$specId = $_GET['id'] ?? '';

if (empty($specId)) {
    $_SESSION['error'] = "ID Spesifikasi tidak valid!";
    header('Location: m_padder_spec.php');
    exit;
}

// Check if spec exists
$checkSql = "SELECT ps.id, p.padder_id, p.padder_name, ps.spec_name 
             FROM dbo.pad_m_padder_spec ps
             INNER JOIN dbo.pad_m_padder p ON ps.padder_id = p.padder_id
             WHERE ps.id = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, [$specId]);

if (!$checkStmt || !$specData = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
    $_SESSION['error'] = "Data spesifikasi tidak ditemukan!";
    header('Location: m_padder_spec.php');
    exit;
}
sqlsrv_free_stmt($checkStmt);

// Perform delete
$sql = "DELETE FROM dbo.pad_m_padder_spec WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, [$specId]);

if ($stmt !== false) {
    $_SESSION['success'] = "Spesifikasi '" . $specData['spec_name'] . "' untuk padder " . $specData['padder_id'] . " berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus spesifikasi padder: " . print_r(sqlsrv_errors(), true);
}

if ($stmt) sqlsrv_free_stmt($stmt);

// Redirect back to master page
header('Location: m_padder_spec.php');
exit;
?>