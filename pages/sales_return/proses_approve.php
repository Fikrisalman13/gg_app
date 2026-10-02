<?php
session_start();
require '../../koneksi3.php'; // koneksi menggunakan PDO SQLSRV di $conn3

// Set timezone ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

// Approver yang sudah ditentukan
$approveBy = 'DAVID'; 
$approveDate = date('Y-m-d H:i:s'); // Sekarang akan menggunakan waktu WIB

// Ambil aksi (approve/unapprove)
$action = $_POST['action'] ?? '';
if (!in_array($action, ['approve', 'unapprove'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
    exit;
}

// Ambil data sonmbr dari POST (bisa 1 atau banyak)
$returnnos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['returnnos']) && is_array($_POST['returnnos'])) {
        $returnnos = array_filter($_POST['returnnos'], 'is_string');
    } elseif (isset($_POST['returnno'])) {
        $returnnos = [$_POST['returnno']];
    }
}

if (empty($returnnos)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data Sales Retur tidak ditemukan.']);
    exit;
}

try {
    $conn3->beginTransaction();

    if ($action === 'approve') {
        // Query untuk Approve
        $sql = "
            UPDATE inreturnhd
               SET fgstatus    = 'V',
                   approvedate = :approvedate,
                   approveby   = :approveby
             WHERE returnno      = :returnno
        ";
        $params = [
            ':approvedate' => $approveDate,
            ':approveby'   => $approveBy
        ];
    } else {
        // Query untuk Unapprove
        $sql = "
            UPDATE inreturnhd
               SET fgstatus    = 'O',
                   approvedate = NULL,
                   approveby   = NULL
             WHERE returnno      = :returnno
        ";
        $params = [];
    }

    $stmt = $conn3->prepare($sql);
    $success = 0;

    foreach ($returnnos as $sr) {
        $executeParams = $params;
        $executeParams[':returnno'] = $sr;
        $stmt->execute($executeParams);
        $success += $stmt->rowCount();
    }

    if ($success === 0) {
        throw new Exception('Tidak ada Sales Return yang berhasil diproses.');
    }

    $conn3->commit();
    
    $actionText = $action === 'approve' ? 'di-approve' : 'di-unapprove';
    echo json_encode([
        'status'    => 'success',
        'processed' => $success,
        'message'   => "$success Sales Return berhasil $actionText oleh $approveBy.",
        'action'    => $action,
        'timestamp' => $approveDate // Tambahkan timestamp untuk debugging
    ]);
} catch (Exception $e) {
    $conn3->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $conn3->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}