<?php
// members_action.php - Backend for Master Members
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'import') {
    $userIds = $_POST['UserIds'] ?? [];
    if (!empty($userIds)) {
        foreach ($userIds as $id) {
            // Fetch user info for linking
            $sqlInfo = "SELECT u.UserId, e.nama_lengkap, e.email FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp WHERE u.UserId = ?";
            $stmtInfo = sqlsrv_query($conn, $sqlInfo, [$id]);
            $info = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);

            if ($info) {
                $insSql = "INSERT INTO fin_members (UserId, FullName, Email, IsManual, CreatedBy, UpdatedBy) VALUES (?, ?, ?, 0, ?, ?)";
                sqlsrv_query($conn, $insSql, [$id, $info['nama_lengkap'], $info['email'], $userId, $userId]);
            }
        }
        $_SESSION['success'] = count($userIds) . " anggota berhasil diimport dari karyawan.";
    }
} 
elseif ($action == 'add_manual') {
    $name = $_POST['FullName'] ?? '';
    $user = $_POST['Username'] ?? '';
    $email = $_POST['Email'] ?? '';

    $insSql = "INSERT INTO fin_members (FullName, Username, Email, IsManual, CreatedBy, UpdatedBy) VALUES (?, ?, ?, 1, ?, ?)";
    $res = sqlsrv_query($conn, $insSql, [$name, $user, $email, $userId, $userId]);

    if ($res) {
        $_SESSION['success'] = "Anggota manual berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan anggota manual.";
    }
}
elseif ($action == 'edit') {
    $memberId = $_POST['MemberId'] ?? '';
    $name = $_POST['FullName'] ?? '';
    $user = $_POST['Username'] ?? '';
    $email = $_POST['Email'] ?? '';
    $active = $_POST['IsActive'] ?? 1;

    $updSql = "UPDATE fin_members SET FullName = ?, Username = ?, Email = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE MemberId = ?";
    sqlsrv_query($conn, $updSql, [$name, $user, $email, $active, $userId, $memberId]);
    $_SESSION['success'] = "Data anggota berhasil diperbarui.";
}

header('Location: members.php');
exit;
?>
