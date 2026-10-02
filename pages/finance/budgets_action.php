<?php
// budgets_action.php - CRUD handler for Budget Planning
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $unitId = $_POST['UnitId'] ?? '';
    $period = $_POST['Period'] ?? '';
    $amount = $_POST['TotalAllocated'] ?? 0;

    if (empty($unitId) || empty($period) || $amount <= 0) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: budgets.php');
        exit;
    }

    // Check if budget for this unit and period already exists
    $checkSql = "SELECT TOP 1 BudgetId FROM fin_budget_plans WHERE UnitId = ? AND Period = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$unitId, $period]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Budget untuk unit dan periode ini sudah ada!";
        header('Location: budgets.php');
        exit;
    }

    $sql = "INSERT INTO fin_budget_plans (UnitId, Period, TotalAllocated, Status, CreatedBy, UpdatedBy) VALUES (?, ?, ?, 'Active', ?, ?)";
    $params = [$unitId, $period, $amount, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Alokasi budget berhasil disimpan!";
    } else {
        $errors = sqlsrv_errors();
        $_SESSION['error'] = "Gagal menyimpan budget: " . ($errors[0]['message'] ?? 'Unknown Error');
    }
    header('Location: budgets.php');
    exit;

} elseif ($action == 'edit') {
    $budgetId = $_POST['BudgetId'] ?? '';
    $unitId = $_POST['UnitId'] ?? '';
    $period = $_POST['Period'] ?? '';
    $amount = $_POST['TotalAllocated'] ?? 0;
    $status = $_POST['Status'] ?? 'Active';

    if (empty($budgetId) || empty($unitId) || empty($period)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: budgets.php');
        exit;
    }

    $sql = "UPDATE fin_budget_plans SET UnitId = ?, Period = ?, TotalAllocated = ?, Status = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE BudgetId = ?";
    $params = [$unitId, $period, $amount, $status, $userId, $budgetId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Budget berhasil diperbarui!";
    } else {
        $errors = sqlsrv_errors();
        $_SESSION['error'] = "Gagal memperbarui budget: " . ($errors[0]['message'] ?? 'Unknown Error');
    }
    header('Location: budgets.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: budgets.php');
        exit;
    }

    // Security: check if used in requests
    $checkSql = "SELECT TOP 1 RequestId FROM fin_requests WHERE BudgetId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Budget tidak bisa dihapus karena sudah digunakan dalam pengajuan dana!";
        header('Location: budgets.php');
        exit;
    }

    $sql = "DELETE FROM fin_budget_plans WHERE BudgetId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Budget berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus budget.";
    }
    header('Location: budgets.php');
    exit;
} else {
    header('Location: budgets.php');
    exit;
}
