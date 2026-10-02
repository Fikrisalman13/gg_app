<?php
session_start();
require_once '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName']) || !isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$ticket    = $_POST['ticket'] ?? '';
$newStatus = $_POST['new_status'] ?? '';
$ticket    = trim($ticket);
$newStatus = trim($newStatus);

if (empty($ticket) || empty($newStatus)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

// Validasi status yang diizinkan
$allowedStatus = ['unclosing', 'closed'];
if (!in_array($newStatus, $allowedStatus)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
    exit;
}

// Cek data tiket
$sql = "SELECT * FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    exit;
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$currentStatus = strtolower(trim($data['status_ticket'] ?? ''));

// Validasi transisi status
if ($newStatus === 'unclosing' && $currentStatus !== 'approved') {
    echo json_encode(['success' => false, 'message' => 'Status Unclosing hanya bisa dilakukan setelah Approved.']);
    exit;
}
if ($newStatus === 'closed' && $currentStatus !== 'unclosing') {
    echo json_encode(['success' => false, 'message' => 'Status Closed hanya bisa dilakukan setelah Unclosing.']);
    exit;
}

// Cek otorisasi: Admin, pemilik form, atau user yang memiliki TTD untuk ticket ini boleh memproses
$isAdmin = isset($_SESSION['GroupId']) && (int)$_SESSION['GroupId'] === 1;
$isOwner = trim((string)($data['created_by'] ?? '')) === trim((string)($_SESSION['NamaLengkap'] ?? ''));

// Cek apakah user memiliki TTD untuk ticket ini (approver/signer)
$hasTTD = false;
if (isset($_SESSION['UserId'])) {
    $sqlTTD = "SELECT TOP 1 UserId FROM Form_Umum_TTD WHERE Ticket = ? AND SignedByUserId = ?";
    $stmtTTD = sqlsrv_query($conn, $sqlTTD, [$ticket, $_SESSION['UserId']]);
    if ($stmtTTD && sqlsrv_has_rows($stmtTTD)) {
        $hasTTD = true;
    }
}

// Cek user role apakah bisa sign/approve
$canApprove = false;
if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT TOP 1 GroupRole FROM User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole && sqlsrv_has_rows($stmtRole)) {
        $canApprove = true;
    }
}

if (!$isAdmin && !$isOwner && !$hasTTD && !$canApprove) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki otorisasi untuk mengubah status ini.']);
    exit;
}

// Update status dengan timestamp yang sesuai
$userName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? '';

if ($newStatus === 'unclosing') {
    // Saat status berubah ke Unclosing, simpan timestamp dan user
    $sqlUp = "UPDATE Form_Umum_Buka_Tanggal_Closingan 
              SET status_ticket = ?, 
                  updated_at = GETDATE(), 
                  updated_by = ?,
                  unclosing_at = GETDATE(),
                  unclosing_by = ?
              WHERE ticket = ?";
    $params = [ucfirst($newStatus), $userName, $userName, $ticket];
} elseif ($newStatus === 'closed') {
    // Saat status berubah ke Closed, simpan timestamp dan user
    $sqlUp = "UPDATE Form_Umum_Buka_Tanggal_Closingan 
              SET status_ticket = ?, 
                  updated_at = GETDATE(), 
                  updated_by = ?,
                  closed_at = GETDATE(),
                  closed_by = ?
              WHERE ticket = ?";
    $params = [ucfirst($newStatus), $userName, $userName, $ticket];
} else {
    // Fallback untuk status lain (seharusnya tidak terjadi karena sudah divalidasi)
    $sqlUp = "UPDATE Form_Umum_Buka_Tanggal_Closingan 
              SET status_ticket = ?, 
                  updated_at = GETDATE(), 
                  updated_by = ? 
              WHERE ticket = ?";
    $params = [ucfirst($newStatus), $userName, $ticket];
}

$stmtUp = sqlsrv_query($conn, $sqlUp, $params);

if ($stmtUp === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate menjadi ' . ucfirst($newStatus) . '.']);
