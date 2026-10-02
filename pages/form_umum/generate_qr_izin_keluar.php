<?php
/**
 * generate_qr_izin_keluar.php
 *
 * Endpoint untuk generate QR Code (PNG) untuk tiket Izin Keluar Pabrik (IKS / IKP)
 * yang sudah di-approve (semua TTD sudah lengkap).
 *
 * URL yang di-encode di QR: {scheme}://{host}/gg_app/pages/form_umum/scan_action.php?ticket=XXX
 * Sama dengan QR yang di-generate di detail_izin_keluar_pabrik.php dan
 * generate_pdf_izin_keluar_pabrik.php, sehingga scanner Satpam bisa pakai link
 * yang konsisten.
 *
 * Cara pakai:
 *   <img src="generate_qr_izin_keluar.php?ticket=IKP-20250605-001">
 *
 * Response:
 *   200 - image/png
 *   400 - parameter ticket kosong
 *   403 - tiket tidak ditemukan / belum approve
 *   500 - library QR tidak tersedia / error generate
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php';

header('Cache-Control: no-store, must-revalidate');
header('Pragma: no-cache');

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (stripos($ticket, 'IPC-') === 0) {
    require __DIR__ . '/generate_qr_form_umum.php';
    exit;
}
if ($ticket === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Parameter ticket kosong']);
    exit;
}

// Hanya berlaku untuk tiket izin keluar
if (stripos($ticket, 'IKS-') !== 0 && stripos($ticket, 'IKP-') !== 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'QR hanya berlaku untuk tiket IKS / IKP']);
    exit;
}

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal']);
    exit;
}

// ── Ambil data tiket ────────────────────────────────────────────────────────
$sql = "SELECT TOP 1 ticket, status_ticket FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
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

// ── Validasi status: harus approved (TTD lengkap) ───────────────────────────
// Hitung jumlah TTD yang sudah terisi
$sqlTtd = "SELECT COUNT(*) AS cnt FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL AND SignaturePath != ''";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttdCount = 0;
if ($stmtTtd) {
    $rTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC);
    $ttdCount = (int)($rTtd['cnt'] ?? 0);
    sqlsrv_free_stmt($stmtTtd);
}

// IKS butuh 3 TTD, IKP butuh 4 TTD
$isIKS = stripos($ticket, 'IKS-') === 0;
$required = $isIKS ? 3 : 4;
$statusApproved = strtolower(trim((string)($row['status_ticket'] ?? ''))) === 'approved';

if (!$statusApproved && $ttdCount < $required) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'QR Code belum tersedia. TTD belum lengkap (' . $ttdCount . '/' . $required . ')',
        'ttd_count' => $ttdCount,
        'required'   => $required
    ]);
    exit;
}

// ── Bangun URL tujuan QR ────────────────────────────────────────────────────
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$qrUrl  = $scheme . '://' . $host . '/gg_app/pages/form_umum/scan_action.php?ticket=' . urlencode($ticket);

// ── Generate QR via chillerlan/php-qrcode (output PNG) ──────────────────────
if (!class_exists('\chillerlan\QRCode\QRCode')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Library QR Code tidak tersedia di vendor/']);
    exit;
}

try {
    $options = new \chillerlan\QRCode\QROptions;
    // outputInterface: pilih renderer GD untuk PNG (raw bytes)
    $options->outputInterface = \chillerlan\QRCode\Output\QRGdImagePNG::class;
    $options->scale        = 8;
    $options->quietzoneSize = 2;
    $options->eccLevel     = \chillerlan\QRCode\common\EccLevel::H; // High — agar lebih tahan scan
    $options->outputBase64 = false; // raw PNG bytes, bukan data URI

    $qrCode = new \chillerlan\QRCode\QRCode($options);
    $pngData = $qrCode->render($qrUrl);

    if (empty($pngData)) {
        throw new \RuntimeException('QR render menghasilkan data kosong');
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($pngData));
    echo $pngData;
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Gagal generate QR: ' . $e->getMessage()
    ]);
    exit;
}
