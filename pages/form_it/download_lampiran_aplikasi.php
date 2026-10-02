<?php

session_start();

require_once '../../koneksi.php';
require_once __DIR__ . '/includes/pengajuan_aplikasi_logging.php';

$requestId = pengajuanAplikasiRequestId();

if (!isset($_SESSION['UserName'])) {
    sendDownloadError(401, 'Sesi login tidak valid.', $requestId);
}

$ticket = trim((string) ($_GET['ticket'] ?? ''));
if (!preg_match('/^APP-\d{8}-\d{3}$/', $ticket)) {
    sendDownloadError(400, 'Nomor pengajuan tidak valid.', $requestId);
}

$attachmentSql = "SELECT lampiran_nama_asli,
                         lampiran_nama_file,
                         lampiran_mime
                  FROM Form_Pengajuan_Aplikasi
                  WHERE ticket = ?";
$attachmentStatement = sqlsrv_query($conn, $attachmentSql, [$ticket]);

if ($attachmentStatement === false) {
    pengajuanAplikasiLogError($requestId, 'download', 'Query metadata lampiran gagal.');
    sendDownloadError(500, 'Lampiran gagal diproses.', $requestId);
}

$attachment = sqlsrv_fetch_array($attachmentStatement, SQLSRV_FETCH_ASSOC);
if (!$attachment) {
    sendDownloadError(404, 'Lampiran tidak ditemukan.', $requestId);
}

$storedFileName = basename((string) ($attachment['lampiran_nama_file'] ?? ''));
$storageDirectory = realpath(__DIR__ . '/../../storage/form_it/pengajuan_aplikasi');
$storedFilePath = false;

if ($storageDirectory && $storedFileName !== '') {
    $storedFilePath = realpath($storageDirectory . DIRECTORY_SEPARATOR . $storedFileName);
}

$expectedPathPrefix = $storageDirectory
    ? $storageDirectory . DIRECTORY_SEPARATOR
    : '';
$isValidStoredFile = $storedFilePath
    && $expectedPathPrefix !== ''
    && strpos($storedFilePath, $expectedPathPrefix) === 0
    && is_file($storedFilePath);

if (!$isValidStoredFile) {
    pengajuanAplikasiLogError(
        $requestId,
        'download',
        'File lampiran tidak ditemukan pada storage.',
        ['ticket' => $ticket],
        'WARNING'
    );
    sendDownloadError(404, 'Lampiran tidak ditemukan.', $requestId);
}

$downloadFileName = basename(
    (string) ($attachment['lampiran_nama_asli'] ?: $storedFileName)
);
$downloadFileName = preg_replace('/[\x00-\x1F\x7F"\\\\]/', '_', $downloadFileName);
$downloadMimeType = $attachment['lampiran_mime'] ?: 'application/octet-stream';

header('Content-Type: ' . $downloadMimeType);
header('Content-Length: ' . filesize($storedFilePath));
header(
    'Content-Disposition: attachment; filename="' . $downloadFileName
    . '"; filename*=UTF-8\'\'' . rawurlencode($downloadFileName)
);
header('X-Content-Type-Options: nosniff');

$downloadResult = readfile($storedFilePath);
if ($downloadResult === false) {
    pengajuanAplikasiLogError(
        $requestId,
        'download',
        'Gagal mengalirkan lampiran ke pengguna.',
        ['ticket' => $ticket]
    );
}
exit;

/**
 * Mengirim respons teks aman untuk kegagalan download.
 *
 * @param int $statusCode HTTP status code.
 * @param string $message Pesan aman untuk pengguna.
 * @param string $requestId ID korelasi log.
 * @return void
 */
function sendDownloadError($statusCode, $message, $requestId)
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Request-ID: ' . $requestId);
    exit($message . ' ID: ' . $requestId);
}

