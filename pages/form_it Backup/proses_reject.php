<?php
session_start();
// Pakai path absolut agar tidak gagal kalau working dir berubah
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta'); // WIB
// Pastikan koneksi tersedia
if (!isset($conn) || !is_resource($conn)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database tidak tersedia',
        'detail'  => isset($conn) ? gettype($conn) : 'conn not set'
    ]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
$alasan = $_POST['alasan'] ?? '';

// Alasan sekarang opsional; hanya pastikan ticket ada
if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket diperlukan']);
    exit;
}

if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

try {
    $isCCTV = stripos($ticket,'CCTV-') === 0;
    $isInternet = stripos($ticket,'INET-') === 0;
    $isEmail = stripos($ticket,'EMAIL-') === 0;
    
    if ($isCCTV) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_CCTV WHERE ticket = ?";
    } elseif ($isInternet) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
    } elseif ($isEmail) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
    } else {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Barang WHERE ticket = ?";
    }
    $checkStmt = sqlsrv_query($conn, $checkSql, [$ticket]);
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    if (!sqlsrv_has_rows($checkStmt)) {
        echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan']);
        exit;
    }
    
    // Get user info
    // Gunakan nama dari session (orang yang login dan pencet tombol Tolak)
    $userName = $_SESSION['UserFullName'] ?? $_SESSION['UserName'] ?? 'User';
    $rejectedAt = date('Y-m-d H:i:s');
    
    // Build update for appropriate table (attempt extended columns; fallback if missing)
    if ($isCCTV) {
        $updateSql = "UPDATE Form_Pengajuan_CCTV 
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } elseif ($isInternet) {
        $updateSql = "UPDATE Form_Pengajuan_Akses_Internet 
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } elseif ($isEmail) {
        $updateSql = "UPDATE Form_Pengajuan_Email_Account 
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } else {
        $updateSql = "UPDATE Form_Pengajuan_Barang 
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    }
    
    $updateParams = [ 'Ditolak', $_SESSION['UserId'], ($alasan === '' ? null : $alasan), $rejectedAt, $ticket ];
    $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

    // Fallback if table missing additional columns (e.g., CCTV not yet altered)
    if (!$updateStmt) {
        $errors = sqlsrv_errors();
        if ($isCCTV) {
            $fallbackSql = "UPDATE Form_Pengajuan_CCTV SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } elseif ($isInternet) {
            $fallbackSql = "UPDATE Form_Pengajuan_Akses_Internet SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } elseif ($isEmail) {
            $fallbackSql = "UPDATE Form_Pengajuan_Email_Account SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } else {
            // Check if fallback for Barang is needed (only if primary failed)
             $fallbackSql = "UPDATE Form_Pengajuan_Barang SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        }
        $fallbackStmt = sqlsrv_query($conn, $fallbackSql, [$_SESSION['UserId'], $ticket]);
        if (!$fallbackStmt) {
            echo json_encode(['success'=>false,'message'=>'Gagal mengupdate status (fallback): '.print_r(sqlsrv_errors(), true)]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Pengajuan ditolak (fallback, detail penolakan tidak tersimpan)',
            'rejected_by_name' => $userName,
            'rejection_date' => date('d-m-Y H:i', strtotime($rejectedAt)),
            'ticket_type' => $isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : 'IT')),
            'fallback' => true,
            'errors' => $errors
        ]);
        exit;
    }
    
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pengajuan berhasil ditolak',
        'rejected_by_name' => $userName,
        'rejection_date' => date('d-m-Y H:i', strtotime($rejectedAt)),
        'ticket_type' => $isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : 'IT')),
        'fallback' => false
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
