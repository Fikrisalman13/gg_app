<?php
// org_units_action.php - Action handler for Organizational Units
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action == 'add') {
    $name = $_POST['UnitName'] ?? '';
    $desc = $_POST['Description'] ?? '';

    if (empty($name)) {
        $_SESSION['error'] = "Nama unit tidak boleh kosong!";
        header('Location: org_units.php');
        exit;
    }

    $sql = "INSERT INTO fin_org_units (UnitName, Description, IsActive, CreatedBy, UpdatedBy) VALUES (?, ?, 1, ?, ?)";
    $params = [$name, $desc, $_SESSION['UserId'], $_SESSION['UserId']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Unit berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambah unit: " . print_r(sqlsrv_errors(), true);
    }
    header('Location: org_units.php');
    exit;

} elseif ($action == 'edit') {
    $id = $_POST['UnitId'] ?? '';
    $name = $_POST['UnitName'] ?? '';
    $desc = $_POST['Description'] ?? '';
    $isActive = $_POST['IsActive'] ?? '1';

    if (empty($id) || empty($name)) {
        $_SESSION['error'] = "ID dan Nama unit wajib diisi!";
        header('Location: org_units.php');
        exit;
    }

    $sql = "UPDATE fin_org_units SET UnitName = ?, Description = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE UnitId = ?";
    $params = [$name, $desc, $isActive, $_SESSION['UserId'], $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Unit berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui unit: " . print_r(sqlsrv_errors(), true);
    }
    header('Location: org_units.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: org_units.php');
        exit;
    }

    // Check if unit is in use (optional but good practice)
    $checkSql = "SELECT TOP 1 RequestId FROM fin_requests WHERE UnitId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Unit tidak bisa dihapus karena sudah memiliki transaksi/request!";
        header('Location: org_units.php');
        exit;
    }

    $sql = "DELETE FROM fin_org_units WHERE UnitId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Unit berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus unit: " . print_r(sqlsrv_errors(), true);
    }
    header('Location: org_units.php');
    exit;
} else {
    header('Location: org_units.php');
    exit;
}
