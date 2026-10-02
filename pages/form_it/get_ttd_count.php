<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../koneksi.php';
require_once __DIR__ . '/includes/pengajuan_aplikasi_logging.php';

$requestId = pengajuanAplikasiRequestId();
$ticket = trim((string) ($_GET['ticket'] ?? ''));
if ($ticket === '' || !isset($conn) || $conn === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'count' => 0,
        'required' => 0,
        'status' => 'Error',
        'request_id' => $requestId,
    ]);
    exit;
}

try {
    $ticketDefinitions = [
        'CCTV-P-' => [
            'table' => 'Form_Pemindahan_CCTV',
            'type' => 'Pemindahan Kamera CCTV',
            'required' => 5,
        ],
        'CCTV-' => ['table' => 'Form_Pengajuan_CCTV', 'type' => 'CCTV', 'required' => 5],
        'INET-' => ['table' => 'Form_Pengajuan_Akses_Internet', 'type' => 'Internet', 'required' => 5],
        'EMAIL-' => ['table' => 'Form_Pengajuan_Email_Account', 'type' => 'Email', 'required' => 5],
        'GRT-' => ['table' => 'Form_Grant_Revoke_Trustee', 'type' => 'Grant/Revoke', 'required' => 4],
        'DB-' => ['table' => 'Form_Perubahan_Data_Database', 'type' => 'Database', 'required' => 4],
        'CLS-' => ['table' => 'Form_Buka_Tanggal_Closingan', 'type' => 'Buka Tanggal Closingan', 'required' => 4],
        'APP-' => ['table' => 'Form_Pengajuan_Aplikasi', 'type' => 'Pembuatan Aplikasi', 'required' => 5],
        'GDG-' => [
            'table' => 'Form_Penambahan_Gudang_Baru_ERP',
            'type' => 'Penambahan Gudang Baru ERP',
            'required' => 4,
        ],
    ];
    $ticketDefinition = [
        'table' => 'Form_Pengajuan_Barang',
        'type' => 'IT',
        'required' => 5,
    ];
    foreach ($ticketDefinitions as $prefix => $definition) {
        if (stripos($ticket, $prefix) === 0) {
            $ticketDefinition = $definition;
            break;
        }
    }

    $statusSql = 'SELECT status_ticket FROM ' . $ticketDefinition['table'] . ' WHERE ticket = ?';
    $statusStatement = sqlsrv_query($conn, $statusSql, [$ticket]);
    if ($statusStatement === false) {
        throw new RuntimeException('Status query failed');
    }
    $statusRow = sqlsrv_fetch_array($statusStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($statusStatement);
    $storedStatus = strtolower(trim((string) ($statusRow['status_ticket'] ?? '')));

    if ($storedStatus === 'ditolak') {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'required' => $ticketDefinition['required'],
            'status' => 'Ditolak',
            'ticket_type' => $ticketDefinition['type'],
        ]);
        exit;
    }

    $countSql = "SELECT COUNT(*) AS ttd_count
                 FROM Form_Pengajuan_Barang_TTD
                 WHERE Ticket = ?
                   AND SignaturePath IS NOT NULL
                   AND SignaturePath != ''";
    $countStatement = sqlsrv_query($conn, $countSql, [$ticket]);
    if ($countStatement === false) {
        throw new RuntimeException('Signature count query failed');
    }
    $countRow = sqlsrv_fetch_array($countStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($countStatement);
    $signatureCount = (int) ($countRow['ttd_count'] ?? 0);
    $requiredCount = $ticketDefinition['required'];

    if ($signatureCount === 0) {
        $displayStatus = 'Menunggu Persetujuan';
    } elseif ($signatureCount >= $requiredCount) {
        $displayStatus = 'Approved';
    } else {
        $displayStatus = $signatureCount . '/' . $requiredCount . ' Disetujui';
    }

    echo json_encode([
        'success' => true,
        'count' => $signatureCount,
        'required' => $requiredCount,
        'status' => $displayStatus,
        'ticket_type' => $ticketDefinition['type'],
    ]);
} catch (Throwable $exception) {
    pengajuanAplikasiLogError($requestId, 'signature_status', 'Status tanda tangan gagal diproses.');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'count' => 0,
        'required' => 0,
        'status' => 'Error',
        'message' => 'Status gagal diproses.',
        'request_id' => $requestId,
    ]);
}
exit;
