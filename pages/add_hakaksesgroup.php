<?php
session_start();
include '../koneksi.php';

header('Content-Type: application/json');

// Validasi login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

// Validasi permission
function checkGroupAccessPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    
    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    
    return $permissions;
}

$permissions = checkGroupAccessPermissions($conn, $_SESSION['GroupId'], 4); // GROUP_ACCESS_MENU_ID = 4

if ($permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah data!']);
    exit;
}

// Validasi input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan!']);
    exit;
}

$groupId = $_POST['groupId'] ?? '';
$menuId = $_POST['menuId'] ?? '';
$canView = isset($_POST['canView']) ? 1 : 0;
$canAdd = isset($_POST['canAdd']) ? 1 : 0;
$canEdit = isset($_POST['canEdit']) ? 1 : 0;
$canDelete = isset($_POST['canDelete']) ? 1 : 0;

// Validasi data
if (empty($groupId) || empty($menuId)) {
    echo json_encode(['success' => false, 'message' => 'Group dan Menu harus dipilih!']);
    exit;
}

try {
    // Cek apakah data sudah ada
    $checkSql = "SELECT TrusteeId FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$groupId, $menuId]);
    
    if ($checkStmt && sqlsrv_fetch($checkStmt)) {
        echo json_encode(['success' => false, 'message' => 'Hak akses untuk Group dan Menu ini sudah ada!']);
        exit;
    }
    
    // Insert data baru
    $insertSql = "
        INSERT INTO dbo.SMGroupTrustee 
        (GroupId, MenuId, CanView, CanAdd, CanEdit, CanDelete)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    
    $params = [$groupId, $menuId, $canView, $canAdd, $canEdit, $canDelete, $_SESSION['UserName']];
    $insertStmt = sqlsrv_query($conn, $insertSql, $params);
    
    if ($insertStmt) {
        echo json_encode(['success' => true, 'message' => 'Hak akses berhasil ditambahkan!']);
    } else {
        $errors = sqlsrv_errors();
        $errorMessage = 'Gagal menambahkan hak akses.';
        if ($errors) {
            $errorMessage = $errors[0]['message'];
        }
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>