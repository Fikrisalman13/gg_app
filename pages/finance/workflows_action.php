<?php
// workflows_action.php - Action handler for Workflow Steps
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';
$ruleId = $_REQUEST['RuleId'] ?? '';
$userId = $_SESSION['UserId'];

if ($action == 'add') {
    $name = $_POST['StepName'] ?? '';
    $order = $_POST['StepOrder'] ?? '';
    $groupId = $_POST['RequiredGroupId'] ?? '';
    $type = $_POST['ActionType'] ?? 'Approval';

    if (empty($name) || empty($order) || empty($groupId) || empty($ruleId)) {
        $_SESSION['error'] = "Data wajib diisi semua!";
        header('Location: workflows.php?RuleId=' . $ruleId);
        exit;
    }

    $sql = "INSERT INTO fin_workflow_steps (RuleId, StepName, StepOrder, RequiredGroupId, ActionType, CreatedBy, UpdatedBy) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $params = [$ruleId, $name, $order, $groupId, $type, $userId, $userId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Tahapan berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambah tahapan.";
    }
    header('Location: workflows.php?RuleId=' . $ruleId);
    exit;

} elseif ($action == 'edit') {
    $id = $_POST['StepId'] ?? '';
    $name = $_POST['StepName'] ?? '';
    $order = $_POST['StepOrder'] ?? '';
    $groupId = $_POST['RequiredGroupId'] ?? '';
    $type = $_POST['ActionType'] ?? 'Approval';

    if (empty($id) || empty($name) || empty($order)) {
        $_SESSION['error'] = "Data tidak lengkap!";
        header('Location: workflows.php?RuleId=' . $ruleId);
        exit;
    }

    $sql = "UPDATE fin_workflow_steps SET StepName = ?, StepOrder = ?, RequiredGroupId = ?, ActionType = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE StepId = ?";
    $params = [$name, $order, $groupId, $type, $userId, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success'] = "Tahapan berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui tahapan.";
    }
    header('Location: workflows.php?RuleId=' . $ruleId);
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: workflows.php?RuleId=' . $ruleId);
        exit;
    }

    // Check if any request is currently at this step
    $checkSql = "SELECT TOP 1 RequestId FROM fin_requests WHERE CurrentStepId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        $_SESSION['error'] = "Tahapan tidak bisa dihapus karena ada pengajuan aktif yang tertahan di sini!";
        header('Location: workflows.php?RuleId=' . $ruleId);
        exit;
    }

    $sql = "DELETE FROM fin_workflow_steps WHERE StepId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt) {
        $_SESSION['success'] = "Tahapan berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus tahapan.";
    }
    header('Location: workflows.php?RuleId=' . $ruleId);
    exit;
} else {
    header('Location: rules.php');
    exit;
}
