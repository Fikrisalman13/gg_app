<?php
session_start();
header('Content-Type: application/json');
require_once '../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

$ticket = $_GET['ticket'] ?? '';
if (!$ticket || !isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'count' => 0, 'status' => 'Error']);
    exit;
}

// Deteksi jenis tiket Izin Keluar (IKS atau IKP)
$isIKS = stripos($ticket, 'IKS-') === 0;
$isIKP = stripos($ticket, 'IKP-') === 0;
$isIPC = stripos($ticket, 'IPC-') === 0;
$isIzinKeluar = $isIKS || $isIKP;
$isScanForm = $isIzinKeluar || $isIPC;

function iksRequiresKadeptIT($ticket, $tglPengajuan)
{
    if (stripos($ticket, 'IKS-') !== 0) return false;
    if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
    $time = strtotime((string)$tglPengajuan);
    return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}

function iksTimeToMinutes($timeValue)
{
    if ($timeValue instanceof DateTime) {
        return ((int) $timeValue->format('H')) * 60 + (int) $timeValue->format('i');
    }

    $timeValue = trim((string) $timeValue);
    if ($timeValue === '') {
        return null;
    }

    if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?/', $timeValue, $m)) {
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    return null;
}

function iksIsLateReturn($jamKembaliReal, $estimasiKembali)
{
    $actualMinutes = iksTimeToMinutes($jamKembaliReal);
    $estimateMinutes = iksTimeToMinutes($estimasiKembali);

    if ($actualMinutes === null || $estimateMinutes === null) {
        return false;
    }

    return $actualMinutes > $estimateMinutes;
}

try {
    if ($isScanForm) {
        $required = $isIKP ? 4 : 3;

        if ($isIPC) {
            $sqlStatus = "SELECT status_ticket, created_by, jam_keluar_real, status_keluar, tanggal FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
            $ticketTypeName = 'Izin Pulang Cepat';
        } else {
            $sqlStatus = "SELECT status_ticket, created_by, jam_keluar_real, jam_kembali_real, estimasi_kembali, tgl_keluar, tgl_pengajuan FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
            $ticketTypeName = 'Izin Keluar Pabrik';
        }
        $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$ticket]);

        if (!$stmtStatus) {
            echo json_encode(['success' => false, 'count' => 0, 'required' => $required, 'status' => 'Error', 'message' => 'Query gagal']);
            exit;
        }

        $rowS = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtStatus);

        if (!$rowS) {
            echo json_encode(['success' => false, 'count' => 0, 'required' => $required, 'status' => 'Error', 'message' => 'Tiket tidak ditemukan']);
            exit;
        }

        if ($isIKS && iksRequiresKadeptIT($ticket, $rowS['tgl_pengajuan'] ?? null)) {
            $required = 4;
        }

        $statusTicket = trim($rowS['status_ticket'] ?? '');
        $isRejected   = strtolower($statusTicket) === 'ditolak';

        if ($isRejected) {
            echo json_encode([
                'success'     => true,
                'count'       => 0,
                'required'    => $required,
                'status'      => 'Ditolak',
                'can_process' => false,
                'ticket_type' => $ticketTypeName
            ]);
            exit;
        }

        // Hitung TTD dari Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL
        $sqlTTD = "SELECT COUNT(*) AS ttd_count FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL";
        $stmtTTD = sqlsrv_query($conn, $sqlTTD, [$ticket]);
        $ttdCount = 0;
        if ($stmtTTD && $rowTTD = sqlsrv_fetch_array($stmtTTD, SQLSRV_FETCH_ASSOC)) {
            $ttdCount = (int)$rowTTD['ttd_count'];
        }
        if ($stmtTTD) sqlsrv_free_stmt($stmtTTD);

        // Tentukan status berdasarkan count vs required
        // If not all signatures: show TTD progress
        // If all signatures complete: show attendance status based on scans
        if ($ttdCount === 0) {
            $status = 'Menunggu Persetujuan';
        } elseif ($ttdCount < $required) {
            $status = $ttdCount . '/' . $required . ' Disetujui';
        } else {
            if ($isIPC) {
                $jamKeluarReal = $rowS['jam_keluar_real'] ?? null;
                if (!empty($jamKeluarReal)) {
                    $status = 'Sudah Keluar';
                } else {
                    $status = 'Approved';
                }
            } else {
                // All signatures complete - check if exit scan exists
                $jamKeluarReal = $rowS['jam_keluar_real'] ?? null;
                $jamKembaliReal = $rowS['jam_kembali_real'] ?? null;
                $estimasiKembali = $rowS['estimasi_kembali'] ?? null;
                $tglKeluar = $rowS['tgl_keluar'] ?? null;
                
                // Only show attendance status if exit scan exists
                if (!empty($jamKeluarReal) && empty($jamKembaliReal)) {
                    // Has exit scan, no return scan yet
                    $status = 'Sedang Keluar';
                } elseif (!empty($jamKeluarReal) && !empty($jamKembaliReal)) {
                    // Has both scans - compare time-only values. Do not parse
                    // "HH:MM" as DateTime because PHP would attach today's date.
                    if (iksIsLateReturn($jamKembaliReal, $estimasiKembali)) {
                        $status = 'Terlambat Kembali';
                    } else {
                        $status = 'Sudah Kembali';
                    }
                } else {
                    // No exit scan yet - show Approved
                    $status = 'Approved';
                }
            }
        }

        echo json_encode([
            'success'     => true,
            'count'       => $ttdCount,
            'required'    => $required,
            'status'      => $status,
            'can_process' => false,
            'ticket_type' => $ticketTypeName
        ]);



    } else {
        // â”€â”€ Branch Closingan (existing logic, tidak diubah) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // Semua tiket Form Umum menggunakan prefix UCLS- (Umum Closing)
        // atau CLS- tergantung proses_simpan; kita cek di Form_Umum_Buka_Tanggal_Closingan
        $sqlStatus = "SELECT status_ticket, jenis_pengajuan, is_revisi_harga, request_gudang, request_transaksi, gudang_transaksi, created_by FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
        $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$ticket]);

        $isRejected   = false;
        $statusTicket = '';
        $ticketData = [];

        if ($stmtStatus && $rowS = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)) {
            $ticketData = $rowS;
            $statusTicket = trim($rowS['status_ticket'] ?? '');
            if (strtolower($statusTicket) === 'ditolak') $isRejected = true;
        }
        if ($stmtStatus) sqlsrv_free_stmt($stmtStatus);

        $sync = closinganSyncApprovalStatus($conn, $ticket, $ticketData);
        $statusTicket = $sync['status'];
        $required = $sync['required'];

        if ($isRejected) {
            echo json_encode([
                'success'     => true,
                'count'       => 0,
                'status'      => 'Ditolak',
                'required'    => $required,
                'ticket_type' => 'Buka Tanggal Closingan'
            ]);
            exit;
        }

        $ttdCount = $sync['count'];

        if ($ttdCount === 0) {
            $status = 'Menunggu Persetujuan';
        } elseif ($ttdCount >= $required) {
            $status = 'Approved';
        } else {
            $status = $ttdCount . '/' . $required . ' Disetujui';
        }

        $currentStatus = strtolower(trim((string)$statusTicket));
        if ($currentStatus === 'unclosing') {
            $status = 'Unclosing';
        } elseif ($currentStatus === 'closed') {
            $status = 'Closed';
        }

        $isAdmin = isset($_SESSION['GroupId']) && (int)$_SESSION['GroupId'] === 1;
        $isOwner = trim((string)($ticketData['created_by'] ?? '')) === trim((string)($_SESSION['NamaLengkap'] ?? ''));

        // Logika tombol Unclosing (Approved â†’ Unclosing)
        $canUnclosing = false;
        if ($isAdmin) {
            $canUnclosing = true;
        } elseif (!$isOwner) {
            // Ambil user roles
            $userRoles = [];
            $sqlRoles = "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
            $stmtRoles = sqlsrv_query($conn, $sqlRoles, [$_SESSION['UserId']]);
            if ($stmtRoles) {
                while ($rowR = sqlsrv_fetch_array($stmtRoles, SQLSRV_FETCH_ASSOC)) {
                    $userRoles[] = trim($rowR['GroupRole']);
                }
                sqlsrv_free_stmt($stmtRoles);
            }
            
            $requestFlags = closinganRequestFlags($ticketData);
            $hasGudang = $requestFlags['has_gudang'];
            $hasTransaksi = $requestFlags['has_transaksi'];
            
            if ($hasGudang && in_array('Kadept ACC', $userRoles)) {
                $canUnclosing = true;
            }
            if ($hasTransaksi && in_array('Kabag ICS', $userRoles)) {
                $canUnclosing = true;
            }
        }
        
        // Logika tombol Closed (Unclosing â†’ Closed)
        $canClosed = $isAdmin || $isOwner;
        
        // can_process tergantung status saat ini
        $canProcess = false;
        if ($currentStatus === 'approved') {
            $canProcess = $canUnclosing;
        } elseif ($currentStatus === 'unclosing') {
            $canProcess = $canClosed;
        }

        echo json_encode([
            'success'     => true,
            'count'       => $ttdCount,
            'required'    => $required,
            'status'      => $status,
            'can_process' => $canProcess,
            'ticket_type' => 'Buka Tanggal Closingan'
        ]);
    }

} catch (Exception $ex) {
    echo json_encode(['success' => false, 'count' => 0, 'required' => 0, 'status' => 'Error', 'message' => $ex->getMessage()]);
}
exit;


