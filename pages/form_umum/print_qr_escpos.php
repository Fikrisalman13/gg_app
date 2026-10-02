<?php
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeRequestId(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return uniqid('qrp_', true);
    }
}

function logQrPrintFailure(string $requestId, string $message, array $context = []): void
{
    $dir = dirname(__DIR__, 2) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'severity' => 'ERROR',
        'request_id' => $requestId,
        'module' => 'form_umum',
        'action' => 'print_qr_escpos',
        'user' => isset($_SESSION['UserName']) ? preg_replace('/[^A-Za-z0-9_.@-]/', '_', (string)$_SESSION['UserName']) : null,
        'message' => $message,
        'source_file' => basename(__FILE__),
        'source_line' => 0,
        'context' => $context,
    ];

    @file_put_contents(
        $dir . '/error-' . date('Y-m-d') . '.log',
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

if (!isset($_SESSION['UserName'])) {
    respondJson(401, ['ok' => false, 'message' => 'Unauthorized']);
}

$ticket = trim((string)($_POST['ticket'] ?? ''));
$mode = ($_POST['mode'] ?? 'link') === 'ticket' ? 'ticket' : 'link';
if (!preg_match('/^(IKS|IKP|IPC)-[A-Za-z0-9-]+$/', $ticket)) {
    respondJson(422, ['ok' => false, 'message' => 'Ticket tidak valid']);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

$userId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($_SESSION['UserId'] ?? $_SESSION['UserName']));
$settingsFile = dirname(__DIR__, 2) . '/config/qr_printer_settings/user_' . $userId . '.json';
$settings = ['transport' => 'windows', 'printer_name' => 'EPSON TM-U220 Receipt', 'ip' => '', 'port' => 9100];
if (is_file($settingsFile)) {
    $saved = json_decode((string)file_get_contents($settingsFile), true);
    if (is_array($saved)) {
        $settings = array_merge($settings, $saved);
    }
}

try {
    $connector = $settings['transport'] === 'lan'
        ? new NetworkPrintConnector($settings['ip'], (int)$settings['port'])
        : new WindowsPrintConnector($settings['printer_name']);

    $printer = new Printer($connector);
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setEmphasis(true);
    $printer->text("PT SURYA USAHA MANDIRI\n");
    $printer->text($ticket . "\n");
    $printer->setEmphasis(false);
    $printer->text("BARCODE TICKET\n");
    $printer->feed();

    // ponytail: TM-U220 trial uses narrow legacy Code39. Upgrade to per-printer profiles if more printer models need different commands.
    $connector->write("\x1d\x48\x02"); // HRI text below barcode
    $connector->write("\x1d\x68\x50"); // barcode height 80 dots
    $connector->write("\x1d\x77\x01"); // narrow module width for 76mm impact paper
    $connector->write("\x1d\x6b\x04" . $ticket . "\x00"); // legacy Code39 command

    $printer->feed(2);
    $printer->text("Scan barcode / input ticket manual\n");
    $printer->feed(3);
    $printer->cut();
    $printer->close();

    respondJson(200, ['ok' => true, 'message' => 'Barcode ticket terkirim ke printer']);
} catch (Throwable $e) {
    $requestId = safeRequestId();
    logQrPrintFailure($requestId, 'Printer gagal diakses', [
        'ticket_prefix' => substr($ticket, 0, 3),
        'mode' => $mode,
        'transport' => $settings['transport'] ?? 'unknown',
        'exception_class' => get_class($e),
        'exception_message' => substr(preg_replace('/[\r\n]+/', ' ', $e->getMessage()), 0, 160),
    ]);
    respondJson(500, ['ok' => false, 'message' => 'Printer gagal diakses', 'request_id' => $requestId]);
}

