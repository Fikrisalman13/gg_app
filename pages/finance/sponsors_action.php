<?php
// sponsors_action.php - Action handler for Sponsor Management
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $name = $_POST['SponsorName'] ?? '';
    $desc = $_POST['Description'] ?? '';

    if (empty($name)) {
        $_SESSION['error'] = "Nama sponsor wajib diisi!";
        header('Location: sponsors.php');
        exit;
    }

    $sql = "INSERT INTO fin_sponsors (SponsorName, Description, IsActive, CreatedBy, UpdatedBy) VALUES (?, ?, 1, ?, ?)";
    $params = [$name, $desc, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Sponsor berhasil ditambahkan!";
    } else {
        $errors = sqlsrv_errors();
        $_SESSION['error'] = "Gagal menambah sponsor: " . ($errors[0]['message'] ?? 'Unknown Error');
    }
    header('Location: sponsors.php');
    exit;

} elseif ($action == 'edit') {
    $id = $_POST['SponsorId'] ?? '';
    $name = $_POST['SponsorName'] ?? '';
    $desc = $_POST['Description'] ?? '';
    $isActive = $_POST['IsActive'] ?? '1';

    if (empty($id) || empty($name)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: sponsors.php');
        exit;
    }

    $sql = "UPDATE fin_sponsors SET SponsorName = ?, Description = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE SponsorId = ?";
    $params = [$name, $desc, $isActive, $userId, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Sponsor berhasil diperbarui!";
    } else {
        $errors = sqlsrv_errors();
        $_SESSION['error'] = "Gagal memperbarui sponsor: " . ($errors[0]['message'] ?? 'Unknown Error');
    }
    header('Location: sponsors.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: sponsors.php');
        exit;
    }

    // Security check: Check if sponsor is used in any accounts
    $checkSql = "SELECT TOP 1 AccountId FROM fin_cash_accounts WHERE SponsorId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Sponsor tidak bisa dihapus karena terikat pada Akun Kas/Bank!";
        header('Location: sponsors.php');
        exit;
    }

    $sql = "DELETE FROM fin_sponsors WHERE SponsorId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Sponsor berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus sponsor.";
    }
    header('Location: sponsors.php');
    exit;
} else {
    header('Location: sponsors.php');
    exit;
}
