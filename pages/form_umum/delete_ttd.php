<?php
session_start();
header('Content-Type: application/json');
require_once '../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

$result   = ['success' => false, 'message' => 'Error'];
$ticket   = $_POST['ticket']    ?? '';
$roleCode = $_POST['role_code'] ?? '';
$userId   = $_SESSION['UserId'] ?? 0;

if (!$ticket || !$roleCode || !$userId) {
    $result['message'] = 'Missing parameters';
    echo json_encode($result);
    exit;
}

$roleMap = [
    'pemohon'        => 'Pemohon',
    'atasan_pemohon' => 'Atasan Pemohon',
    'personalia'     => 'Personalia',
    'danru_satpam'   => 'DanRu SATPAM',
    'hrd'            => 'HRD',
    'kabag_ics'      => 'Kabag ICS',
    'kadept'         => 'Kadept',
    'kadept_it'      => 'Kadept',
    'acc_audit'      => 'Kadept ACC',
    'kadept_acc'     => 'Kadept ACC',
    'direksi'        => 'Direksi',
];

$groupRole = $roleMap[$roleCode] ?? null;
if (!$groupRole) {
    $result['message'] = 'Invalid role';
    echo json_encode($result);
    exit;
}

try {
    $isIKP = stripos($ticket, 'IKP-') === 0;

    if ($isIKP) {
        // IKP: cek status dari tabel IKP
        $stmtTicket  = sqlsrv_query($conn,
            "SELECT status_ticket FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?", [$ticket]);
        $ticketData  = $stmtTicket ? sqlsrv_fetch_array($stmtTicket, SQLSRV_FETCH_ASSOC) : null;
        if ($stmtTicket) sqlsrv_free_stmt($stmtTicket);
        $currentStatus = strtolower(trim((string)($ticketData['status_ticket'] ?? '')));
        if ($currentStatus === 'approved') {
            $result['message'] = 'Tanda tangan tidak dapat dihapus setelah status Approved.';
            echo json_encode($result);
            exit;
        }
    } else {
        $stmtTicket = sqlsrv_query($conn, "SELECT status_ticket FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?", [$ticket]);
        $ticketData = $stmtTicket ? sqlsrv_fetch_array($stmtTicket, SQLSRV_FETCH_ASSOC) : null;
        if ($stmtTicket) sqlsrv_free_stmt($stmtTicket);
        $currentStatus = strtolower(trim((string)($ticketData['status_ticket'] ?? '')));
        if (in_array($currentStatus, ['unclosing', 'closed'], true)) {
            $result['message'] = 'Tanda tangan tidak dapat dihapus saat status sudah Unclosing atau Closed.';
            echo json_encode($result);
            exit;
        }
    }

    $isAdmin = ((int)($_SESSION['GroupId'] ?? 0) === 1);
    $signedByUserIds = $isAdmin ? [$userId, 0] : [$userId];
    $userPlaceholders = implode(',', array_fill(0, count($signedByUserIds), '?'));

    // Hanya hapus TTD milik sendiri; Kadept canonical dan legacy memakai satu slot.
    if ($groupRole === 'Kadept') {
        $sql  = "DELETE FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept', 'Kadept IT') AND SignedByUserId IN ($userPlaceholders)";
        $stmt = sqlsrv_query($conn, $sql, array_merge([$ticket], $signedByUserIds));
    } elseif ($groupRole === 'Kadept ACC') {
        $sql  = "DELETE FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept ACC', 'Acc Audit') AND SignedByUserId IN ($userPlaceholders)";
        $stmt = sqlsrv_query($conn, $sql, array_merge([$ticket], $signedByUserIds));
    } else {
        $sql  = "DELETE FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ? AND SignedByUserId IN ($userPlaceholders)";
        $stmt = sqlsrv_query($conn, $sql, array_merge([$ticket, $groupRole], $signedByUserIds));
    }

    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
        closinganSyncApprovalStatus($conn, $ticket);
        $result['success'] = true;
        $result['message'] = 'Tanda tangan berhasil dihapus';
    } else {
        $result['message'] = 'Database error: ' . print_r(sqlsrv_errors(), true);
    }
} catch (Exception $ex) {
    $result['message'] = 'Exception: ' . $ex->getMessage();
}

echo json_encode($result);
exit;
