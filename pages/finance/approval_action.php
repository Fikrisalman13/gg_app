<?php
// approval_action.php - State Machine logic for Advance/Reject
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$id = $_POST['RequestId'] ?? '';
$action = $_POST['Action'] ?? '';
$note = $_POST['Note'] ?? '';
$userId = $_SESSION['UserId'];

if (empty($id) || empty($action)) {
    $_SESSION['error'] = "Data tidak lengkap!";
    header('Location: pending_approvals.php');
    exit;
}

// --- STEP 1: Fetch Request & Rule Details (including RequiredGroupId for authorization) ---
$reqSql = "SELECT r.*, s.StepOrder, s.RuleId as RuleIdFromStep, s.RequiredGroupId
           FROM fin_requests r
           JOIN fin_workflow_steps s ON r.CurrentStepId = s.StepId
           WHERE r.RequestId = ?";
$stmt = sqlsrv_query($conn, $reqSql, [$id]);
$request = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$request) {
    $_SESSION['error'] = "Pengajuan tidak ditemukan!";
    header('Location: pending_approvals.php');
    exit;
}

// --- STEP 1.5: Verify Authorization (Membership in Finance Group) ---
$authSql = "SELECT gm.GroupMemberId 
            FROM fin_group_members gm 
            WHERE gm.GroupId = ? AND gm.MemberId = ?";
$authStmt = sqlsrv_query($conn, $authSql, [$request['RequiredGroupId'], $userId]);
if (!$authStmt || !sqlsrv_has_rows($authStmt)) {
    $_SESSION['error'] = "Anda tidak memiliki otoritas untuk memproses tahap ini!";
    header('Location: pending_approvals.php');
    exit;
}

$currentStepId = $request['CurrentStepId'];
$currentRuleId = $request['RuleId'];
$currentOrder = $request['StepOrder'];

if ($action == 'Approve') {
    // --- STEP 2: Find Next Step in Sequence ---
    $nextSql = "SELECT TOP 1 StepId FROM fin_workflow_steps 
                WHERE RuleId = ? AND StepOrder > ? 
                ORDER BY StepOrder ASC";
    $nextStmt = sqlsrv_query($conn, $nextSql, [$currentRuleId, $currentOrder]);
    
    $nextStepId = null;
    if ($nextStmt && $row = sqlsrv_fetch_array($nextStmt, SQLSRV_FETCH_ASSOC)) {
        $nextStepId = $row['StepId'];
    }

    if ($nextStepId) {
        // Advance to next step
        $updateSql = "UPDATE fin_requests SET CurrentStepId = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE RequestId = ?";
        sqlsrv_query($conn, $updateSql, [$nextStepId, $userId, $id]);
        $_SESSION['success'] = "Pengajuan disetujui dan diteruskan ke tahapan berikutnya.";
    } else {
        // No more steps -> FULLY APPROVED
        $updateSql = "UPDATE fin_requests SET Status = 'Approved', UpdatedBy = ?, UpdatedAt = GETDATE() WHERE RequestId = ?";
        sqlsrv_query($conn, $updateSql, [$userId, $id]);
        $_SESSION['success'] = "Pengajuan telah SELESAI disetujui (Fully Approved). Siap untuk pembayaran.";
    }

    // Log History
    $histSql = "INSERT INTO fin_request_history (RequestId, StepId, ActorUserId, Action, Note, CreatedBy, UpdatedBy) 
                VALUES (?, ?, ?, 'Approve', ?, ?, ?)";
    sqlsrv_query($conn, $histSql, [$id, $currentStepId, $userId, $note, $userId, $userId]);

} elseif ($action == 'Reject') {
    // Set status to Rejected
    $updateSql = "UPDATE fin_requests SET Status = 'Rejected', UpdatedBy = ?, UpdatedAt = GETDATE() WHERE RequestId = ?";
    sqlsrv_query($conn, $updateSql, [$userId, $id]);

    // Log History
    $histSql = "INSERT INTO fin_request_history (RequestId, StepId, ActorUserId, Action, Note, CreatedBy, UpdatedBy) 
                VALUES (?, ?, ?, 'Reject', ?, ?, ?)";
    sqlsrv_query($conn, $histSql, [$id, $currentStepId, $userId, $note, $userId, $userId]);

    $_SESSION['success'] = "Pengajuan telah ditolak.";
}

header('Location: pending_approvals.php');
exit;
