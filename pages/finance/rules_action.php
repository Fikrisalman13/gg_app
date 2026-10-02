<?php
// rules_action.php - Action handler for Approval Rules
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $name = $_POST['RuleName'] ?? '';
    $min = $_POST['MinAmount'] ?? 0;
    $max = $_POST['MaxAmount'] ?? 999999999999;
    $type = $_POST['ConditionType'] ?? 'Nominal';

    if (empty($name)) {
        $_SESSION['error'] = "Nama rule wajib diisi!";
        header('Location: rules.php');
        exit;
    }

    // Validation: Correct Range
    if ($min > $max) {
        $_SESSION['error'] = "Min Amount tidak boleh lebih besar dari Max Amount!";
        header('Location: rules.php');
        exit;
    }

    // Validation: Check Overlap
    $checkSql = "SELECT Top 1 RuleName FROM fin_approval_rules 
                 WHERE IsActive = 1 
                 AND ConditionType = ?
                 AND ((MinAmount <= ? AND MaxAmount >= ?) OR (MinAmount <= ? AND MaxAmount >= ?) OR (MinAmount >= ? AND MaxAmount <= ?))";
    // Overlap logic: (StartA <= EndB) and (EndA >= StartB) is the general overlap formula.
    // Simplified SQL check: MinAmount <= NewMax AND MaxAmount >= NewMin
    $overlapSql = "SELECT Top 1 RuleName FROM fin_approval_rules 
                   WHERE IsActive = 1 
                   AND ConditionType = ?
                   AND (MinAmount <= ? AND MaxAmount >= ?)";
    
    $overlapStmt = sqlsrv_query($conn, $overlapSql, [$type, $max, $min]);
    if ($overlapStmt && sqlsrv_has_rows($overlapStmt)) {
        $row = sqlsrv_fetch_array($overlapStmt, SQLSRV_FETCH_ASSOC);
        $_SESSION['error'] = "Range nominal bertabrakan dengan rule: " . $row['RuleName'];
        header('Location: rules.php');
        exit;
    }

    $sql = "INSERT INTO fin_approval_rules (RuleName, MinAmount, MaxAmount, ConditionType, IsActive, CreatedBy, UpdatedBy) VALUES (?, ?, ?, ?, 1, ?, ?)";
    $params = [$name, $min, $max, $type, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Rule berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambah rule.";
    }
    header('Location: rules.php');
    exit;

} elseif ($action == 'edit') {
    $id = $_POST['RuleId'] ?? '';
    $name = $_POST['RuleName'] ?? '';
    $min = $_POST['MinAmount'] ?? 0;
    $max = $_POST['MaxAmount'] ?? 0;
    $type = $_POST['ConditionType'] ?? 'Nominal';
    $isActive = $_POST['IsActive'] ?? '1';

    if (empty($id) || empty($name)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: rules.php');
        exit;
    }

    // Validation: Correct Range
    if ($min > $max) {
        $_SESSION['error'] = "Min Amount tidak boleh lebih besar dari Max Amount!";
        header('Location: rules.php');
        exit;
    }

    // Validation: Check Overlap (Exclude self)
    $overlapSql = "SELECT Top 1 RuleName FROM fin_approval_rules 
                   WHERE IsActive = 1 
                   AND ConditionType = ?
                   AND RuleId != ?
                   AND (MinAmount <= ? AND MaxAmount >= ?)";
    
    $overlapStmt = sqlsrv_query($conn, $overlapSql, [$type, $id, $max, $min]);
    if ($overlapStmt && sqlsrv_has_rows($overlapStmt)) {
        $row = sqlsrv_fetch_array($overlapStmt, SQLSRV_FETCH_ASSOC);
        $_SESSION['error'] = "Range nominal bertabrakan dengan rule: " . $row['RuleName'];
        header('Location: rules.php');
        exit;
    }

    $sql = "UPDATE fin_approval_rules SET RuleName = ?, MinAmount = ?, MaxAmount = ?, ConditionType = ?, IsActive = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE RuleId = ?";
    $params = [$name, $min, $max, $type, $isActive, $userId, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Rule berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui rule.";
    }
    header('Location: rules.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: rules.php');
        exit;
    }

    // Check if any workflow steps are attached
    $checkSql = "SELECT TOP 1 StepId FROM fin_workflow_steps WHERE RuleId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Rule tidak bisa dihapus karena memiliki tahapan (steps) yang aktif!";
        header('Location: rules.php');
        exit;
    }

    $sql = "DELETE FROM fin_approval_rules WHERE RuleId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Rule berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus rule.";
    }
    header('Location: rules.php');
    exit;
} else {
    header('Location: rules.php');
    exit;
}
