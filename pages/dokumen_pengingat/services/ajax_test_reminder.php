<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/ReminderService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$jenis = $_POST['jenis'] ?? null;
$id    = isset($_POST['id']) ? (int)$_POST['id'] : null;

if (!$jenis || !$id) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit;
}

try {
    $service = new ReminderService($conn);
    $result  = $service->sendTestReminder($jenis, $id);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
