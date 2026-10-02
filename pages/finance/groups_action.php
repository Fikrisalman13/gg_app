<?php
// groups_action.php - Backend for Finance Group CRUD
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $name = $_POST['GroupName'] ?? '';
    $desc = $_POST['Description'] ?? '';

    $sql = "INSERT INTO fin_groups (GroupName, Description, IsActive, CreatedBy, UpdatedBy, CreatedAt, UpdatedAt) VALUES (?, ?, 1, ?, ?, GETDATE(), GETDATE())";
    $params = [$name, $desc, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Group berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan group.";
    }
} 
elseif ($action == 'edit') {
    $groupId = $_POST['GroupId'] ?? '';
    $name = $_POST['GroupName'] ?? '';
    $desc = $_POST['Description'] ?? '';
    $isActive = $_POST['IsActive'] ?? 1;

    $sql = "UPDATE fin_groups SET GroupName = ?, Description = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE GroupId = ?";
    $params = [$name, $desc, $isActive, $userId, $groupId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Group berhasil diperbarui.";
    } else {
        $errs = sqlsrv_errors();
        $_SESSION['error'] = "Gagal memperbarui group. Error: " . print_r($errs, true);
    }
}

header('Location: groups.php');
exit;
?>
