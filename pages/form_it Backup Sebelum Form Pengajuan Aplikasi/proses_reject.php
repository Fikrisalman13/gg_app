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
    $isPemindahanCctv = stripos($ticket, 'CCTV-P-') === 0;
    $isCCTV = !$isPemindahanCctv && stripos($ticket, 'CCTV-') === 0;
    $isInternet = stripos($ticket, 'INET-') === 0;
    $isEmail = stripos($ticket, 'EMAIL-') === 0;
    $isClosing = stripos($ticket, 'CLS-') === 0;
    $isApplication = stripos($ticket, 'APP-') === 0;
    $isGudangBaruErp = stripos($ticket, 'GDG-') === 0;
    
    if ($isPemindahanCctv) {
        $checkSql = "SELECT ticket, status_ticket FROM dbo.Form_Pemindahan_CCTV WHERE ticket = ?";
    } elseif ($isCCTV) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_CCTV WHERE ticket = ?";
    } elseif ($isInternet) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
    } elseif ($isEmail) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
    } elseif (stripos($ticket,'GRT-') === 0) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Grant_Revoke_Trustee WHERE ticket = ?";
    } elseif (stripos($ticket,'DB-') === 0) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Perubahan_Data_Database WHERE ticket = ?";
    } elseif ($isClosing) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Buka_Tanggal_Closingan WHERE ticket = ?";
    } elseif ($isApplication) {
        $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Aplikasi WHERE ticket = ?";
    } elseif ($isGudangBaruErp) {
        $checkSql = "SELECT ticket, status_ticket FROM dbo.Form_Penambahan_Gudang_Baru_ERP WHERE ticket = ?";
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
    if ($isPemindahanCctv) {
        $updateSql = "UPDATE dbo.Form_Pemindahan_CCTV
                      SET status_ticket = ?, updated_at = GETDATE(), updated_by = ?
                      WHERE ticket = ?";
    } elseif ($isCCTV) {
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
    } elseif (stripos($ticket,'GRT-') === 0) {
        $updateSql = "UPDATE Form_Grant_Revoke_Trustee
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } elseif (stripos($ticket,'DB-') === 0) {
        $updateSql = "UPDATE Form_Perubahan_Data_Database
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } elseif ($isClosing) {
        $updateSql = "UPDATE Form_Buka_Tanggal_Closingan
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    } elseif ($isGudangBaruErp) {
        $updateSql = "UPDATE dbo.Form_Penambahan_Gudang_Baru_ERP
                      SET status_ticket = ?, rejected_by = ?, rejection_reason = ?, rejection_date = ?
                      WHERE ticket = ?";
    } elseif ($isApplication) {
        $updateSql = "UPDATE Form_Pengajuan_Aplikasi
                      SET status_ticket = ?,
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?,
                          updated_at = GETDATE(),
                          updated_by = ?
                      WHERE ticket = ?";
    } else {
        $updateSql = "UPDATE Form_Pengajuan_Barang 
                      SET status_ticket = ?, 
                          rejected_by = ?,
                          rejection_reason = ?,
                          rejection_date = ?
                      WHERE ticket = ?";
    }
    
    if ($isApplication) {
        $updateParams = [
            'Ditolak',
            $_SESSION['UserId'],
            $alasan === '' ? null : $alasan,
            $rejectedAt,
            $_SESSION['UserId'],
            $ticket,
        ];
    } elseif ($isPemindahanCctv) {
        $updateParams = ['Ditolak', $userName, $ticket];
    } else {
        $updateParams = [
            'Ditolak',
            $_SESSION['UserId'],
            $alasan === '' ? null : $alasan,
            $rejectedAt,
            $ticket,
        ];
    }
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
        } elseif (stripos($ticket,'GRT-') === 0) {
            $fallbackSql = "UPDATE Form_Grant_Revoke_Trustee SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } elseif (stripos($ticket,'DB-') === 0) {
             $fallbackSql = "UPDATE Form_Perubahan_Data_Database SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } elseif ($isClosing) {
             $fallbackSql = "UPDATE Form_Buka_Tanggal_Closingan SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        } elseif ($isApplication) {
            $fallbackSql = "UPDATE Form_Pengajuan_Aplikasi
                            SET status_ticket = 'Ditolak',
                                updated_at = GETDATE(),
                                updated_by = ?
                            WHERE ticket = ?";
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
            'ticket_type' => $isApplication ? 'Pembuatan Aplikasi' : ($isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : 'IT'))),
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
        'ticket_type' => $isApplication ? 'Pembuatan Aplikasi' : ($isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : 'IT'))),
        'fallback' => false
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
