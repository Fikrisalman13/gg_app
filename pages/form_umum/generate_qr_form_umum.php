<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

header('Cache-Control: no-store, must-revalidate');
header('Pragma: no-cache');

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'link';
if (!in_array($mode, ['link', 'ticket'], true)) {
    $mode = 'link';
}
if ($ticket === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Parameter ticket kosong']);
    exit;
}

$isIKS = stripos($ticket, 'IKS-') === 0;
$isIKP = stripos($ticket, 'IKP-') === 0;
$isIPC = stripos($ticket, 'IPC-') === 0;

function iksRequiresKadeptIT($ticket, $tglPengajuan)
{
    if (stripos($ticket, 'IKS-') !== 0) return false;
    if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
    $time = strtotime((string)$tglPengajuan);
    return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}

if (!$isIKS && !$isIKP && !$isIPC) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'QR hanya berlaku untuk tiket IKS / IKP / IPC']);
    exit;
}

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal']);
    exit;
}

$tableName = $isIPC ? 'Form_Umum_Izin_Pulang_Cepat' : 'Form_Umum_Izin_Keluar_Pabrik';
$sql = $isIPC
    ? "SELECT TOP 1 ticket, status_ticket FROM {$tableName} WHERE ticket = ?"
    : "SELECT TOP 1 ticket, status_ticket, tgl_pengajuan FROM {$tableName} WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if ($stmt === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Query database gagal']);
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$row) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Tiket tidak ditemukan']);
    exit;
}

$sqlTtd = "SELECT COUNT(*) AS cnt FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL AND SignaturePath != ''";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttdCount = 0;
if ($stmtTtd) {
    $rTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC);
    $ttdCount = (int)($rTtd['cnt'] ?? 0);
    sqlsrv_free_stmt($stmtTtd);
}

$required = $isIKP ? 4 : 3;
if ($isIKS && iksRequiresKadeptIT($ticket, $row['tgl_pengajuan'] ?? null)) {
    $required = 4;
}
$statusApproved = strtolower(trim((string)($row['status_ticket'] ?? ''))) === 'approved';
if (!$statusApproved && $ttdCount < $required) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'QR Code belum tersedia. TTD belum lengkap (' . $ttdCount . '/' . $required . ')',
        'ttd_count' => $ttdCount,
        'required' => $required
    ]);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$qrText = $mode === 'ticket'
    ? $ticket
    : $scheme . '://' . $host . '/gg_app/pages/form_umum/scan_action.php?ticket=' . urlencode($ticket);

if (!class_exists('\chillerlan\QRCode\QRCode')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Library QR Code tidak tersedia di vendor/']);
    exit;
}

try {
    $options = new \chillerlan\QRCode\QROptions;
    $options->outputInterface = \chillerlan\QRCode\Output\QRGdImagePNG::class;
    $options->scale = 8;
    $options->quietzoneSize = 2;
    $options->eccLevel = \chillerlan\QRCode\common\EccLevel::H;
    $options->outputBase64 = false;

    $pngData = (new \chillerlan\QRCode\QRCode($options))->render($qrText);
    if (empty($pngData)) {
        throw new RuntimeException('QR render menghasilkan data kosong');
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($pngData));
    echo $pngData;
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Gagal generate QR: ' . $e->getMessage()]);
    exit;
}

