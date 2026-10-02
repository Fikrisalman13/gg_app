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

// Ambil data ponmbr dari POST (bisa 1 atau banyak)
$ponmbrs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ponmbrs']) && is_array($_POST['ponmbrs'])) {
        $ponmbrs = array_filter($_POST['ponmbrs'], 'is_string');
    } elseif (isset($_POST['ponmbr'])) {
        $ponmbrs = [$_POST['ponmbr']];
    }
}

if (empty($ponmbrs)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data Purchase Order tidak ditemukan.']);
    exit;
}

try {
    $conn3->beginTransaction();

    if ($action === 'approve') {
        // Query untuk Approve
        $sql = "
            UPDATE prpohd
               SET fgstatus    = 'V',
                   approvedate = :approvedate,
                   approveby   = :approveby
             WHERE ponmbr      = :ponmbr
        ";
        $params = [
            ':approvedate' => $approveDate,
            ':approveby'   => $approveBy
        ];
    } else {
        // Query untuk Unapprove
        $sql = "
            UPDATE prpohd
               SET fgstatus    = 'O',
                   approvedate = NULL,
                   approveby   = NULL
             WHERE ponmbr      = :ponmbr
        ";
        $params = [];
    }

    $stmt = $conn3->prepare($sql);
    $success = 0;

    foreach ($ponmbrs as $po) {
        $executeParams = $params;
        $executeParams[':ponmbr'] = $po;
        $stmt->execute($executeParams);
        $success += $stmt->rowCount();
    }

    if ($success === 0) {
        throw new Exception('Tidak ada Purchase Order yang berhasil diproses.');
    }

    $conn3->commit();
    
    $actionText = $action === 'approve' ? 'di-approve' : 'di-unapprove';
    echo json_encode([
        'status'    => 'success',
        'processed' => $success,
        'message'   => "$success Purchase Order berhasil $actionText oleh $approveBy.",
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