<?php
// requests_action.php - Rule-matching engine and request handler
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
    $categoryId = $_POST['CategoryId'] ?? '';
    $title = $_POST['Title'] ?? '';
    $amount = $_POST['Amount'] ?? 0;
    $desc = $_POST['Description'] ?? '';

    if (empty($unitId) || empty($categoryId) || empty($title) || empty($amount)) {
        $_SESSION['error'] = "Mohon lengkapi semua data wajib!";
        header('Location: requests.php');
        exit;
    }

    // --- STEP 1: Find Matching Approval Rule ---
    $ruleSql = "SELECT TOP 1 RuleId FROM fin_approval_rules 
                WHERE IsActive = 1 AND ? >= MinAmount AND ? <= MaxAmount 
                ORDER BY MinAmount DESC"; // Match the most specific (highest range) rule
    $ruleStmt = sqlsrv_query($conn, $ruleSql, [$amount, $amount]);
    
    $ruleId = null;
    if ($ruleStmt && $row = sqlsrv_fetch_array($ruleStmt, SQLSRV_FETCH_ASSOC)) {
        $ruleId = $row['RuleId'];
    }

    if (!$ruleId) {
        $_SESSION['error'] = "Tidak ditemukan aturan approval untuk nominal Rp " . number_format($amount, 0);
        header('Location: requests.php');
        exit;
    }

    // --- STEP 2: Find First Workflow Step for this Rule ---
    $stepSql = "SELECT TOP 1 StepId FROM fin_workflow_steps 
                WHERE RuleId = ? ORDER BY StepOrder ASC";
    $stepStmt = sqlsrv_query($conn, $stepSql, [$ruleId]);
    
    $firstStepId = null;
    if ($stepStmt && $row = sqlsrv_fetch_array($stepStmt, SQLSRV_FETCH_ASSOC)) {
        $firstStepId = $row['StepId'];
    }

    // If no steps defined, we might auto-approve if rule exists? 
    // For now, let's require at least one step.
    if (!$firstStepId) {
        $_SESSION['error'] = "Aturan ditemukan, tetapi belum ada tahapan approval yang dikonfigurasi.";
        header('Location: requests.php');
        exit;
    }

    // --- STEP 3: Budget Validation (Optional) ---
    $budgetId = !empty($_POST['BudgetId']) ? $_POST['BudgetId'] : null;
    if ($budgetId) {
        // Get Total Allocated
        $bSql = "SELECT TotalAllocated, Period FROM fin_budget_plans WHERE BudgetId = ?";
        $bStmt = sqlsrv_query($conn, $bSql, [$budgetId]);
        $budgetInfo = sqlsrv_fetch_array($bStmt, SQLSRV_FETCH_ASSOC);
        
        if ($budgetInfo) {
            $totalAllocated = $budgetInfo['TotalAllocated'];
            
            // Get Total Spent (Pending, Approved, Paid - everything not Rejected)
            $spentSql = "SELECT SUM(Amount) as TotalSpent FROM fin_requests WHERE BudgetId = ? AND Status != 'Rejected'";
            $spentStmt = sqlsrv_query($conn, $spentSql, [$budgetId]);
            $spentRow = sqlsrv_fetch_array($spentStmt, SQLSRV_FETCH_ASSOC);
            $totalSpent = $spentRow['TotalSpent'] ?? 0;
            
            $remaining = $totalAllocated - $totalSpent;
            
            if ($amount > $remaining) {
                $_SESSION['error'] = "Budget tidak mencukupi! Sisa budget periode " . $budgetInfo['Period'] . " adalah Rp " . number_format($remaining, 0, ',', '.') . ". (Pengajuan Anda: Rp " . number_format($amount, 0, ',', '.') . ")";
                header('Location: requests.php');
                exit;
            }
        }
    }

    // --- STEP 4: Insert Request ---
    $insertSql = "INSERT INTO fin_requests (UnitId, CategoryId, BudgetId, RuleId, Title, Amount, CurrentStepId, Status, CreatedBy, UpdatedBy) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?);
                  SELECT SCOPE_IDENTITY() AS RequestId;";
    $params = [$unitId, $categoryId, $budgetId, $ruleId, $title, $amount, $firstStepId, $userId, $userId];
    $stmt = sqlsrv_query($conn, $insertSql, $params);

    if ($stmt) {
        // Skip result for SCOPE_IDENTITY if needed
        sqlsrv_next_result($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $requestId = $row['RequestId'];

        // --- STEP 4: Log History ---
        $histSql = "INSERT INTO fin_request_history (RequestId, StepId, ActorUserId, Action, Note, CreatedBy, UpdatedBy) 
                    VALUES (?, ?, ?, 'Submit', ?, ?, ?)";
        $histParams = [$requestId, $firstStepId, $userId, $desc, $userId, $userId];
        sqlsrv_query($conn, $histSql, $histParams);

        $_SESSION['success'] = "Pengajuan berhasil dikirim! Status: Menunggu Approval.";
    } else {
        $_SESSION['error'] = "Gagal membuat pengajuan: " . print_r(sqlsrv_errors(), true);
    }
    
    header('Location: requests.php');
    exit;

} elseif ($action == 'delete') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $_SESSION['error'] = "ID tidak ditemukan!";
        header('Location: requests.php');
        exit;
    }

    // Security check: Only Draft, Rejected, or Pending can be deleted
    $checkSql = "SELECT Status FROM fin_requests WHERE RequestId = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$id]);
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

    // Allow deleting Pending requests as well (cancelling the request)
    if ($row && ($row['Status'] == 'Draft' || $row['Status'] == 'Rejected' || $row['Status'] == 'Pending')) {
        // Delete history first
        sqlsrv_query($conn, "DELETE FROM fin_request_history WHERE RequestId = ?", [$id]);
        
        // Delete request
        $sql = "DELETE FROM fin_requests WHERE RequestId = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);

        if ($stmt) {
            $_SESSION['success'] = "Pengajuan berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus pengajuan.";
        }
    } else {
        $_SESSION['error'] = "Pengajuan dengan status Approved/Paid tidak bisa dihapus.";
    }
    
    header('Location: requests.php');
    exit;

} else {
    header('Location: requests.php');
    exit;
}
