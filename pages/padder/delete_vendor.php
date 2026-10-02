<?php
// delete_vendor.php - Hapus Data Vendor
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

// helper: ambil permission user untuk menu Vendor
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

// Ambil permission (sesuaikan MenuId dengan menu Vendor di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 59);
if ($permissions['CanDelete'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data vendor.";
    header('Location: m_vendor.php');
    exit;
}

// ====== Process Delete ======
$vendorId = $_GET['id'] ?? '';

if (empty($vendorId)) {
    $_SESSION['error'] = "Vendor ID tidak valid!";
    header('Location: m_vendor.php');
    exit;
}

// Check if vendor exists
$checkSql = "SELECT vendor_id, vendor_name FROM dbo.pad_m_vendor WHERE vendor_id = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, [$vendorId]);

if (!$checkStmt || !$vendorData = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
    $_SESSION['error'] = "Data vendor tidak ditemukan!";
    header('Location: m_vendor.php');
    exit;
}
sqlsrv_free_stmt($checkStmt);

// Perform delete
$sql = "DELETE FROM dbo.pad_m_vendor WHERE vendor_id = ?";
$stmt = sqlsrv_query($conn, $sql, [$vendorId]);

if ($stmt !== false) {
    $_SESSION['success'] = "Vendor '" . $vendorData['vendor_name'] . "' berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus data vendor: " . print_r(sqlsrv_errors(), true);
}

if ($stmt) sqlsrv_free_stmt($stmt);

// Redirect back to master page
header('Location: m_vendor.php');
exit;
?>