<?php
// pages/ppc_guidance_v2/edit_otomatis/process_edit_bulk.php
session_start();
date_default_timezone_set('Asia/Jakarta');
set_time_limit(300); // 5 menit untuk batch besar
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

$rawIds = $_POST['resep_ids'] ?? [];
$resepIds = [];

if (is_array($rawIds)) {
    foreach ($rawIds as $id) {
        $cleanId = filter_var($id, FILTER_VALIDATE_INT);
        if ($cleanId && $cleanId > 0) {
            $resepIds[] = $cleanId;
        }
    }
} elseif (is_string($rawIds) && trim($rawIds) !== '') {
    foreach (explode(',', $rawIds) as $id) {
        $cleanId = filter_var(trim($id), FILTER_VALIDATE_INT);
        if ($cleanId && $cleanId > 0) {
            $resepIds[] = $cleanId;
        }
    }
}

$resepIds = array_values(array_unique($resepIds));

if (empty($resepIds)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Silakan pilih minimal 1 resep untuk diproses otomatis.']);
    exit;
}

require_once __DIR__ . '/auto_edit_logic.php';

$user = $_SESSION['UserName'] ?? 'system';
$total = count($resepIds);
$successCount = 0;
$failedCount = 0;
$results = [];

foreach ($resepIds as $id) {
    $res = processAutoEdit($conn, $conn3, $id, $user);
    if ($res['ok']) {
        $successCount++;
    } else {
        $failedCount++;
    }
    $results[] = [
        'resep_id' => $id,
        'ok'       => $res['ok'],
        'message'  => $res['message'],
        'no_cp'    => $res['no_cp'] ?? '-'
    ];
}

echo json_encode([
    'ok'            => $failedCount === 0 || $successCount > 0,
    'total'         => $total,
    'success_count' => $successCount,
    'failed_count'  => $failedCount,
    'results'       => $results
]);
