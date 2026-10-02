<?php
// group_members_action.php - Backend for member assignments
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$groupId = $_POST['GroupId'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $memberId = $_POST['MemberId'] ?? '';
    
    // Check if already in group
    $checkSql = "SELECT GroupMemberId FROM fin_group_members WHERE GroupId = ? AND MemberId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$groupId, $memberId]);
    
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Anggota sudah ada dalam group ini.";
    } else {
        $sql = "INSERT INTO fin_group_members (GroupId, MemberId, CreatedBy, UpdatedBy) VALUES (?, ?, ?, ?)";
        $params = [$groupId, $memberId, $userId, $userId];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $_SESSION['success'] = "Anggota berhasil ditambahkan.";
        } else {
            $_SESSION['error'] = "Gagal menambahkan anggota. Pastikan migration sudah dijalankan.";
        }
    }
} 
elseif ($action == 'delete') {
    $groupMemberId = $_POST['GroupMemberId'] ?? $_POST['MemberId'] ?? '';
    
    $sql = "DELETE FROM fin_group_members WHERE GroupMemberId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupMemberId]);

    if ($stmt) {
        $_SESSION['success'] = "Anggota berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus anggota.";
    }
}

header('Location: group_members.php?id=' . $groupId);
exit;
?>
