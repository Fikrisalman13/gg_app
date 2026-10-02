<?php
// categories_action.php - Action handler for Expense Categories
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action == 'add') {
    $name = $_POST['CategoryName'] ?? '';
    if (empty($name)) {
        $_SESSION['error'] = "Nama kategori tidak boleh kosong!";
        header('Location: categories.php');
        exit;
    }

    $sql = "INSERT INTO fin_categories (CategoryName, IsActive, CreatedBy, UpdatedBy) VALUES (?, 1, ?, ?)";
    $stmt = sqlsrv_query($conn, $sql, [$name, $_SESSION['UserId'], $_SESSION['UserId']]);

    if ($stmt) {
        $_SESSION['success'] = "Kategori berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambah kategori.";
    }
    header('Location: categories.php');
    exit;

} elseif ($action == 'edit') {
    $id = $_POST['CategoryId'] ?? '';
    $name = $_POST['CategoryName'] ?? '';
    $isActive = $_POST['IsActive'] ?? '1';

    if (empty($id) || empty($name)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: categories.php');
        exit;
    }

    $sql = "UPDATE fin_categories SET CategoryName = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE CategoryId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$name, $isActive, $_SESSION['UserId'], $id]);

    if ($stmt) {
        $_SESSION['success'] = "Kategori berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui kategori.";
    }
    header('Location: categories.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: categories.php');
        exit;
    }

    // Check usage
    $checkSql = "SELECT TOP 1 RequestId FROM fin_requests WHERE CategoryId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Kategori masih digunakan dalam transaksi!";
        header('Location: categories.php');
        exit;
    }

    $sql = "DELETE FROM fin_categories WHERE CategoryId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Kategori berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus kategori.";
    }
    header('Location: categories.php');
    exit;
} else {
    header('Location: categories.php');
    exit;
}
