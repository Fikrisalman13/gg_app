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
$sonmbrs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sonmbrs']) && is_array($_POST['sonmbrs'])) {
        $sonmbrs = array_filter($_POST['sonmbrs'], 'is_string');
    } elseif (isset($_POST['sonmbr'])) {
        $sonmbrs = [$_POST['sonmbr']];
    }
}

if (empty($sonmbrs)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data Sales Order tidak ditemukan.']);
    exit;
}

try {
    $conn3->beginTransaction();

    if ($action === 'approve') {
        // Query untuk Approve
        $sql = "
            UPDATE insohd
               SET fgstatus    = 'V',
                   approvedate = :approvedate,
                   approveby   = :approveby
             WHERE sonmbr      = :sonmbr
        ";
        $params = [
            ':approvedate' => $approveDate,
            ':approveby'   => $approveBy
        ];
    } else {
        // Query untuk Unapprove
        $sql = "
            UPDATE insohd
               SET fgstatus    = 'O',
                   approvedate = NULL,
                   approveby   = NULL
             WHERE sonmbr      = :sonmbr
        ";
        $params = [];
    }

    $stmt = $conn3->prepare($sql);
    $success = 0;

    foreach ($sonmbrs as $so) {
        $executeParams = $params;
        $executeParams[':sonmbr'] = $so;
        $stmt->execute($executeParams);
        $success += $stmt->rowCount();
    }

    if ($success === 0) {
        throw new Exception('Tidak ada Sales Order yang berhasil diproses.');
    }

    $conn3->commit();
    
    $actionText = $action === 'approve' ? 'di-approve' : 'di-unapprove';
    echo json_encode([
        'status'    => 'success',
        'processed' => $success,
        'message'   => "$success Sales Order berhasil $actionText oleh $approveBy.",
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