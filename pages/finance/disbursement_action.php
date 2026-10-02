<?php
// disbursement_action.php - Financial transaction handler (Ledger & Payment)
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Login diperlukan.']);
    exit;
}

$requestId = $_POST['RequestId'] ?? '';
$accountId = $_POST['AccountId'] ?? '';
$userId = $_SESSION['UserId'];

if (empty($requestId) || empty($accountId)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
    exit;
}

// --- STEP 1: Fetch Request Amount & Account Balance ---
$reqSql = "SELECT r.Amount, r.Title, a.AccountName, a.CurrentBalance 
           FROM fin_requests r
           CROSS JOIN fin_cash_accounts a
           WHERE r.RequestId = ? AND a.AccountId = ?";
$stmt = sqlsrv_query($conn, $reqSql, [$requestId, $accountId]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
    exit;
}

$amount = $data['Amount'];
$currentBalance = $data['CurrentBalance'];

if ($currentBalance < $amount) {
    echo json_encode(['status' => 'error', 'message' => 'Saldo akun tidak mencukupi untuk pembayaran ini.']);
    exit;
}

// --- STEP 2: Database Transaction ---
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal memulai transaksi DB.']);
    exit;
}

try {
    // 1. Update Request Status
    $sql1 = "UPDATE fin_requests SET Status = 'Paid', UpdatedBy = ?, UpdatedAt = GETDATE() WHERE RequestId = ?";
    $stmt1 = sqlsrv_query($conn, $sql1, [$userId, $requestId]);

    // 2. Create Ledger Record
    $sql2 = "INSERT INTO fin_ledger (AccountId, RequestId, Type, Amount, Description, CreatedBy, UpdatedBy) 
             VALUES (?, ?, 'OUT', ?, ?, ?, ?)";
    $ledgerDesc = "Pembayaran Pengajuan #" . $requestId . ": " . $data['Title'];
    $stmt2 = sqlsrv_query($conn, $sql2, [$accountId, $requestId, $amount, $ledgerDesc, $userId, $userId]);

    // 3. Update Account Balance
    $sql3 = "UPDATE fin_cash_accounts SET CurrentBalance = CurrentBalance - ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE AccountId = ?";
    $stmt3 = sqlsrv_query($conn, $sql3, [$amount, $userId, $accountId]);

    // 4. Log Request History
    $sql4 = "INSERT INTO fin_request_history (RequestId, ActorUserId, Action, Note, CreatedBy, UpdatedBy) 
             VALUES (?, ?, 'Paid', ?, ?, ?)";
    $histNote = "Cairkan dana melalui Rekening: " . $data['AccountName'];
    $stmt4 = sqlsrv_query($conn, $sql4, [$requestId, $userId, $histNote, $userId, $userId]);

    if ($stmt1 && $stmt2 && $stmt3 && $stmt4) {
        sqlsrv_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Pembayaran berhasil diproses dan dicatat dalam buku besar.']);
    } else {
        sqlsrv_rollback($conn);
        $errors = print_r(sqlsrv_errors(), true);
        echo json_encode(['status' => 'error', 'message' => 'Gagal memproses transaksi: ' . $errors]);
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
