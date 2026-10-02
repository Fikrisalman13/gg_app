<?php
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

function qrPrinterRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    qrPrinterRespond(401, ['ok' => false, 'message' => 'Unauthorized']);
}

$userId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($_SESSION['UserId'] ?? $_SESSION['UserName']));
$dir = dirname(__DIR__, 2) . '/config/qr_printer_settings';
$file = $dir . '/user_' . $userId . '.json';
$defaults = ['print_mode' => 'qr', 'transport' => 'windows', 'printer_name' => 'EPSON TM-U220 Receipt', 'ip' => '', 'port' => 9100];

if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    qrPrinterRespond(500, ['ok' => false, 'message' => 'Config folder unavailable']);
}

$settings = $defaults;
if (is_file($file)) {
    $saved = json_decode((string)file_get_contents($file), true);
    if (is_array($saved)) {
        $settings = array_merge($settings, $saved);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    $printMode = ($input['print_mode'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';
    $transport = ($input['transport'] ?? 'windows') === 'lan' ? 'lan' : 'windows';
    $printerName = trim((string)($input['printer_name'] ?? ''));
    $ip = trim((string)($input['ip'] ?? ''));
    $port = filter_var($input['port'] ?? 9100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);

    if ($transport === 'windows' && $printerName === '') {
        qrPrinterRespond(422, ['ok' => false, 'message' => 'Nama printer wajib diisi']);
    }
    if ($transport === 'lan' && (!filter_var($ip, FILTER_VALIDATE_IP) || !$port)) {
        qrPrinterRespond(422, ['ok' => false, 'message' => 'IP atau port printer tidak valid']);
    }

    $settings = ['print_mode' => $printMode, 'transport' => $transport, 'printer_name' => $printerName, 'ip' => $ip, 'port' => $port ?: 9100];
    if (file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        qrPrinterRespond(500, ['ok' => false, 'message' => 'Gagal menyimpan setting']);
    }
}

qrPrinterRespond(200, ['ok' => true, 'settings' => $settings]);
