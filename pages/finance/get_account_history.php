<?php
// get_account_history.php - Fetch ledger records for a specific account
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$accountId = $_GET['id'] ?? '';

if (empty($accountId)) {
    echo json_encode(['status' => 'error', 'message' => 'AccountId is required']);
    exit;
}

$sql = "SELECT l.*, u.UserName, r.Title as RequestTitle 
        FROM fin_ledger l
        LEFT JOIN dbo.SMUserMs u ON l.CreatedBy = u.UserId
        LEFT JOIN fin_requests r ON l.RequestId = r.RequestId
        WHERE l.AccountId = ?
        ORDER BY l.TransTimestamp DESC";
$stmt = sqlsrv_query($conn, $sql, [$accountId]);
$history = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format date and amount for frontend
        $row['FormattedDate'] = $row['TransTimestamp']->format('d/m/Y H:i');
        $row['FormattedAmount'] = number_format($row['Amount'], 0, ',', '.');
        $history[] = $row;
    }
}

echo json_encode(['status' => 'success', 'data' => $history]);
