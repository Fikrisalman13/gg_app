<?php
session_start();
header('Content-Type: application/json');
require_once '../../koneksi.php';

$ticket = $_GET['ticket'] ?? '';
if (!$ticket || !isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'count' => 0, 'status' => 'Error']);
    exit;
}

try {
    $isCCTV = stripos($ticket, 'CCTV-') === 0;
    $isInternet = stripos($ticket, 'INET-') === 0;
    $isEmail = stripos($ticket, 'EMAIL-') === 0;
    $isGrantRevoke = stripos($ticket, 'GRT-') === 0;
    $isDB = stripos($ticket, 'DB-') === 0;
    $statusTicket = '';
    
    if ($isCCTV) {
        $sqlStatus = "SELECT status_ticket FROM Form_Pengajuan_CCTV WHERE ticket = ?";
    } elseif ($isInternet) {
        $sqlStatus = "SELECT status_ticket FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
    } elseif ($isEmail) {
        $sqlStatus = "SELECT status_ticket FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
    } elseif ($isGrantRevoke) {
        $sqlStatus = "SELECT status_ticket FROM Form_Grant_Revoke_Trustee WHERE ticket = ?";
    } elseif ($isDB) {
        $sqlStatus = "SELECT status_ticket FROM Form_Perubahan_Data_Database WHERE ticket = ?";
    } else {
        $sqlStatus = "SELECT status_ticket FROM Form_Pengajuan_Barang WHERE ticket = ?";
    }
    
    $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$ticket]);
    $isRejected = false;
    if ($stmtStatus && $rowS = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)) {
        $statusTicket = trim($rowS['status_ticket'] ?? '');
        if (strtolower($statusTicket) === 'ditolak') { $isRejected = true; }
    }
    if ($stmtStatus) sqlsrv_free_stmt($stmtStatus);

    if ($isRejected) {
        $ticketType = $isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : ($isGrantRevoke ? 'Grant/Revoke' : ($isDB? 'Database' : 'IT'))));
        $required = $isInternet ? 5 : ($isCCTV ? 4 : ($isEmail ? 5 : ($isGrantRevoke ? 4 : ($isDB ? 4 : 5))));
        echo json_encode(['success'=>true,'count'=>0,'status'=>'Ditolak','required'=>$required,'ticket_type'=>$ticketType]);
        exit;
    }

    // Hitung tanda tangan
    $sqlCount = "SELECT COUNT(*) AS ttd_count FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL AND SignaturePath != ''";
    $stmtCount = sqlsrv_query($conn, $sqlCount, [$ticket]);
    $ttdCount = 0;
    if ($stmtCount && $rowC = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
        $ttdCount = (int)$rowC['ttd_count'];
    }
    if ($stmtCount) sqlsrv_free_stmt($stmtCount);

    // Email usually has 5 signers (Pemohon, Atasan, Petugas IT, Kabag IT, Kadept IT). 
    // GrantRevoke usually has 4 signers (Pemohon, Petugas IT, Kabag IT, Kadept IT).
    $required = $isInternet ? 5 : ($isCCTV ? 4 : ($isEmail ? 5 : ($isGrantRevoke ? 4 : ($isDB ? 4 : 5))));
    // Build status string
    if ($ttdCount === 0) {
        $status = 'Menunggu Persetujuan';
    } elseif ($ttdCount >= $required) {
        $status = 'Selesai';
    } else {
        $status = $ttdCount . '/' . $required . ' Disetujui';
    }

    $ticketType = $isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : ($isGrantRevoke ? 'Grant/Revoke' : ($isDB? 'Database' : 'IT'))));
    
    echo json_encode([
        'success' => true,
        'count' => $ttdCount,
        'required' => $required,
        'status' => $status,
        'ticket_type' => $ticketType
    ]);
} catch (Exception $ex) {
    echo json_encode(['success'=>false,'count'=>0,'required'=>0,'status'=>'Error','message'=>$ex->getMessage()]);
}
exit;
