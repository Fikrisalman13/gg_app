<?php
// pages/ppc_guidance_v2/edit_otomatis/process_edit.php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

$resepId = filter_input(INPUT_POST, 'resep_id', FILTER_VALIDATE_INT);
if (!$resepId || $resepId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID resep tidak valid.']);
    exit;
}

require_once __DIR__ . '/auto_edit_logic.php';

$user = $_SESSION['UserName'] ?? 'system';
$result = processAutoEdit($conn, $conn3, $resepId, $user);

if (!$result['ok']) {
    http_response_code(422);
}

echo json_encode($result);
