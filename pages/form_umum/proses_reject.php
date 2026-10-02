<?php
session_start();
require_once '../../koneksi.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || !is_resource($conn)) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
$alasan = $_POST['alasan'] ?? '';

if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket diperlukan']);
    exit;
}

if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

try {
    $isIKS        = stripos($ticket, 'IKS-') === 0;
    $isIKP_legacy = stripos($ticket, 'IKP-') === 0;
    $isIPC        = stripos($ticket, 'IPC-') === 0;

    if ($isIKS || $isIKP_legacy || $isIPC) {
        // ── IKP/IKS/IPC: Verifikasi GroupRole penolak ─────────────────
        //   IKS/IPC   → hanya 'Atasan Pemohon' atau 'HRD'
        //   IKP legacy → 'Atasan Pemohon' atau 'Personalia'
        $allowedRoles = ($isIKS || $isIPC)
            ? ['Atasan Pemohon', 'HRD']
            : ['Atasan Pemohon', 'Personalia'];

        $userRole = null;
        $placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
        $roleSql  = "SELECT TOP 1 GroupRole FROM User_TTD_Template_Umum
                     WHERE UserId = ? AND GroupRole IN ($placeholders) AND IsActive = 1";
        $roleParams = array_merge([$_SESSION['UserId']], $allowedRoles);
        $roleStmt = sqlsrv_query($conn, $roleSql, $roleParams);
        if ($roleStmt && sqlsrv_has_rows($roleStmt)) {
            $roleRow  = sqlsrv_fetch_array($roleStmt, SQLSRV_FETCH_ASSOC);
            $userRole = $roleRow['GroupRole'] ?? null;
        }

        if (!$userRole) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak berwenang menolak dokumen ini']);
            exit;
        }

        $tableName = $isIPC ? 'Form_Umum_Izin_Pulang_Cepat' : 'Form_Umum_Izin_Keluar_Pabrik';
        $checkSql  = "SELECT ticket, status_ticket FROM {$tableName} WHERE ticket = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$ticket]);

        if (!$checkStmt) {
            echo json_encode(['success' => false, 'message' => 'Query error: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        if (!sqlsrv_has_rows($checkStmt)) {
            echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan']);
            exit;
        }

        $userName   = $_SESSION['NamaLengkap'] ?? $_SESSION['UserFullName'] ?? $_SESSION['UserName'] ?? 'User';
        $rejectedAt = date('Y-m-d H:i:s');

        $updateSql = "UPDATE {$tableName}
                      SET status_ticket    = 'Ditolak',
                          rejected_by      = ?,
                          rejection_reason = ?,
                          rejection_date   = GETDATE()
                      WHERE ticket = ?";

        $updateParams = [$userName, ($alasan === '' ? null : $alasan), $ticket];
        $updateStmt   = sqlsrv_query($conn, $updateSql, $updateParams);

        if (!$updateStmt) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        echo json_encode([
            'success'          => true,
            'message'          => 'Pengajuan berhasil ditolak',
            'rejected_by_name' => $userName,
            'rejection_date'   => date('d-m-Y H:i', strtotime($rejectedAt)),
        ]);
        exit;
    }

    // ── Existing Closingan logic ─────────────────────────────────────────
    $checkSql  = "SELECT ticket, status_ticket FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$ticket]);

    if (!$checkStmt) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    if (!sqlsrv_has_rows($checkStmt)) {
        echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan']);
        exit;
    }

    $userName   = $_SESSION['UserFullName'] ?? $_SESSION['UserName'] ?? 'User';
    $rejectedAt = date('Y-m-d H:i:s');

    $updateSql = "UPDATE Form_Umum_Buka_Tanggal_Closingan
                  SET status_ticket    = ?,
                      rejected_by      = ?,
                      rejection_reason = ?,
                      rejection_date   = ?
                  WHERE ticket = ?";

    $updateParams = ['Ditolak', $_SESSION['UserId'], ($alasan === '' ? null : $alasan), $rejectedAt, $ticket];
    $updateStmt   = sqlsrv_query($conn, $updateSql, $updateParams);

    // Fallback jika kolom rejection belum ada
    if (!$updateStmt) {
        $fallbackSql  = "UPDATE Form_Umum_Buka_Tanggal_Closingan SET status_ticket = 'Ditolak', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?";
        $fallbackStmt = sqlsrv_query($conn, $fallbackSql, [$_SESSION['UserId'], $ticket]);
        if (!$fallbackStmt) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        echo json_encode([
            'success'           => true,
            'message'           => 'Pengajuan ditolak',
            'rejected_by_name'  => $userName,
            'rejection_date'    => date('d-m-Y H:i', strtotime($rejectedAt)),
            'fallback'          => true
        ]);
        exit;
    }

    echo json_encode([
        'success'           => true,
        'message'           => 'Pengajuan berhasil ditolak',
        'rejected_by_name'  => $userName,
        'rejection_date'    => date('d-m-Y H:i', strtotime($rejectedAt)),
        'fallback'          => false
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
