<?php
session_start();
require '../../koneksi3.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$action = $_POST['action'] ?? '';
if ($action !== 'approve') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
    exit;
}

$invcnmbrs = [];
if (isset($_POST['invcnmbrs'])) {
    if (is_array($_POST['invcnmbrs'])) {
        $invcnmbrs = array_values(array_filter($_POST['invcnmbrs'], 'is_string'));
    } elseif (is_string($_POST['invcnmbrs']) && $_POST['invcnmbrs'] !== '') {
        $invcnmbrs = [$_POST['invcnmbrs']];
    }
}

if (empty($invcnmbrs)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data invoice tidak ditemukan.']);
    exit;
}

try {
    $conn3->beginTransaction();

    $stmt = $conn3->prepare("UPDATE ininvchd SET fgstatus = 'V' WHERE invcnmbr = :invcnmbr AND fgstatus = 'O'");
    $success = 0;

    foreach ($invcnmbrs as $invcnmbr) {
        $stmt->execute([':invcnmbr' => $invcnmbr]);
        $success += $stmt->rowCount();
    }

    if ($success === 0) {
        throw new Exception('Tidak ada invoice yang berhasil di-approve.');
    }

    $conn3->commit();

    echo json_encode([
        'status' => 'success',
        'processed' => $success,
        'message' => $success . ' invoice berhasil di-approve.'
    ]);
} catch (Throwable $e) {
    if ($conn3->inTransaction()) {
        $conn3->rollBack();
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

