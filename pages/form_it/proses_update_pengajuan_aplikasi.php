<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../koneksi.php';
require_once __DIR__ . '/includes/pengajuan_aplikasi_logging.php';

$requestId = pengajuanAplikasiRequestId();

if (!isset($_SESSION['UserName'])) {
    sendUpdateError(401, 'Silakan login kembali.', $requestId);
}

$ticket = trim((string) ($_POST['ticket'] ?? ''));
if (!preg_match('/^APP-\d{8}-\d{3}$/', $ticket)) {
    sendUpdateError(422, 'Nomor pengajuan tidak valid.', $requestId);
}

$requiredFields = [
    'nama_pemohon',
    'tgl_pengajuan',
    'nama_aplikasi',
    'latar_belakang_kendala',
    'tujuan_pembuatan',
    'gambaran_proses',
    'fitur_utama',
    'manfaat_diharapkan',
];

foreach ($requiredFields as $requiredField) {
    if (trim((string) ($_POST[$requiredField] ?? '')) === '') {
        sendUpdateError(422, 'Semua field kebutuhan wajib diisi.', $requestId);
    }
}

$currentAttachment = findCurrentAttachment($conn, $ticket, $requestId);
$attachment = $currentAttachment;
$newFilePath = null;
$shouldDeleteOldFile = !empty($_POST['hapus_lampiran']);
$uploadedFile = $_FILES['lampiran'] ?? null;

if ($uploadedFile && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $attachment = storeReplacementAttachment($uploadedFile, $ticket, $requestId);
    $newFilePath = __DIR__ . '/../../' . $attachment['lampiran_path'];
    $shouldDeleteOldFile = true;
} elseif ($shouldDeleteOldFile) {
    $attachment = emptyAttachmentMetadata();
}

$updateSql = "UPDATE Form_Pengajuan_Aplikasi SET
                  nama_pemohon = ?,
                  jabatan = ?,
                  departemen = ?,
                  bagian = ?,
                  tgl_pengajuan = ?,
                  nama_aplikasi = ?,
                  latar_belakang_kendala = ?,
                  tujuan_pembuatan = ?,
                  gambaran_proses = ?,
                  fitur_utama = ?,
                  manfaat_diharapkan = ?,
                  lampiran_nama_asli = ?,
                  lampiran_nama_file = ?,
                  lampiran_mime = ?,
                  lampiran_ukuran = ?,
                  lampiran_path = ?,
                  updated_at = GETDATE(),
                  updated_by = ?
              WHERE ticket = ?";
$updateParameters = [
    trim((string) $_POST['nama_pemohon']),
    trim((string) ($_POST['jabatan'] ?? '')),
    trim((string) ($_POST['departemen'] ?? '')),
    trim((string) ($_POST['bagian'] ?? '')),
    trim((string) $_POST['tgl_pengajuan']),
    trim((string) $_POST['nama_aplikasi']),
    trim((string) $_POST['latar_belakang_kendala']),
    trim((string) $_POST['tujuan_pembuatan']),
    trim((string) $_POST['gambaran_proses']),
    trim((string) $_POST['fitur_utama']),
    trim((string) $_POST['manfaat_diharapkan']),
    $attachment['lampiran_nama_asli'],
    $attachment['lampiran_nama_file'],
    $attachment['lampiran_mime'],
    $attachment['lampiran_ukuran'],
    $attachment['lampiran_path'],
    $_SESSION['UserName'],
    $ticket,
];
$updateStatement = sqlsrv_query($conn, $updateSql, $updateParameters);

if ($updateStatement === false) {
    removeFileAfterFailure($newFilePath, $requestId);
    pengajuanAplikasiLogError($requestId, 'update', 'Update pengajuan aplikasi gagal.');
    sendUpdateError(500, 'Gagal memperbarui pengajuan.', $requestId);
}

if (
    $shouldDeleteOldFile
    && !empty($currentAttachment['lampiran_nama_file'])
    && $currentAttachment['lampiran_nama_file'] !== $attachment['lampiran_nama_file']
) {
    removeOldAttachment($currentAttachment['lampiran_nama_file'], $requestId);
}

echo json_encode([
    'success' => true,
    'message' => 'Pengajuan berhasil diperbarui',
    'ticket' => $ticket,
]);

/**
 * Mengirim respons JSON gagal dan menghentikan request update.
 *
 * @param int $statusCode HTTP status code.
 * @param string $message Pesan aman untuk pengguna.
 * @param string $requestId ID korelasi log.
 * @return void
 */
function sendUpdateError($statusCode, $message, $requestId)
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'request_id' => $requestId,
    ]);
    exit;
}

/**
 * Membaca metadata lampiran aktif dari SQL Server.
 *
 * @param resource $connection Koneksi SQL Server.
 * @param string $ticket Nomor pengajuan aplikasi.
 * @param string $requestId ID korelasi log.
 * @return array Metadata lampiran aktif.
 */
function findCurrentAttachment($connection, $ticket, $requestId)
{
    $selectSql = "SELECT lampiran_nama_asli,
                         lampiran_nama_file,
                         lampiran_mime,
                         lampiran_ukuran,
                         lampiran_path
                  FROM Form_Pengajuan_Aplikasi
                  WHERE ticket = ?";
    $selectStatement = sqlsrv_query($connection, $selectSql, [$ticket]);

    if ($selectStatement === false) {
        pengajuanAplikasiLogError($requestId, 'update', 'Gagal membaca pengajuan sebelum update.');
        sendUpdateError(500, 'Gagal membaca pengajuan.', $requestId);
    }

    $currentAttachment = sqlsrv_fetch_array($selectStatement, SQLSRV_FETCH_ASSOC);
    if (!$currentAttachment) {
        sendUpdateError(404, 'Pengajuan tidak ditemukan.', $requestId);
    }

    return $currentAttachment;
}

/**
 * Memvalidasi dan menyimpan lampiran pengganti ke storage form.
 *
 * @param array $uploadedFile Data file dari $_FILES.
 * @param string $ticket Nomor pengajuan aplikasi.
 * @param string $requestId ID korelasi log.
 * @return array Metadata lampiran baru untuk database.
 */
function storeReplacementAttachment($uploadedFile, $ticket, $requestId)
{
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK || $uploadedFile['size'] > 5242880) {
        sendUpdateError(422, 'Lampiran tidak valid atau melebihi 5 MB.', $requestId);
    }

    $allowedMimeTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($uploadedFile['tmp_name']);
    $sourceExtension = strtolower(pathinfo((string) $uploadedFile['name'], PATHINFO_EXTENSION));
    $sourceExtension = $sourceExtension === 'jpeg' ? 'jpg' : $sourceExtension;

    if (!isset($allowedMimeTypes[$detectedMimeType])) {
        sendUpdateError(422, 'Format lampiran tidak sesuai isi file.', $requestId);
    }
    if ($allowedMimeTypes[$detectedMimeType] !== $sourceExtension) {
        sendUpdateError(422, 'Format lampiran tidak sesuai isi file.', $requestId);
    }

    $uploadDirectory = __DIR__ . '/../../storage/form_it/pengajuan_aplikasi';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
        pengajuanAplikasiLogError($requestId, 'upload', 'Folder lampiran tidak dapat dibuat.');
        sendUpdateError(500, 'Folder lampiran tidak tersedia.', $requestId);
    }

    $storedFileName = $ticket . '-lampiran.' . $sourceExtension;
    $storedFilePath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedFileName;
    if (!move_uploaded_file($uploadedFile['tmp_name'], $storedFilePath)) {
        pengajuanAplikasiLogError($requestId, 'upload', 'Pemindahan lampiran update gagal.');
        sendUpdateError(500, 'Lampiran gagal disimpan.', $requestId);
    }

    return [
        'lampiran_nama_asli' => basename((string) $uploadedFile['name']),
        'lampiran_nama_file' => $storedFileName,
        'lampiran_mime' => $detectedMimeType,
        'lampiran_ukuran' => (int) $uploadedFile['size'],
        'lampiran_path' => 'storage/form_it/pengajuan_aplikasi/' . $storedFileName,
    ];
}

/**
 * Menyediakan metadata kosong ketika lampiran dihapus.
 *
 * @return array Metadata lampiran bernilai null.
 */
function emptyAttachmentMetadata()
{
    return [
        'lampiran_nama_asli' => null,
        'lampiran_nama_file' => null,
        'lampiran_mime' => null,
        'lampiran_ukuran' => null,
        'lampiran_path' => null,
    ];
}

/**
 * Menghapus file baru setelah update database gagal.
 *
 * @param string|null $filePath Path absolut file baru.
 * @param string $requestId ID korelasi log.
 * @return void
 */
function removeFileAfterFailure($filePath, $requestId)
{
    if (!$filePath || !is_file($filePath)) {
        return;
    }
    if (!unlink($filePath)) {
        pengajuanAplikasiLogError(
            $requestId,
            'upload_cleanup',
            'File lampiran baru gagal dibersihkan setelah update gagal.'
        );
    }
}

/**
 * Menghapus lampiran lama setelah metadata database berhasil diperbarui.
 *
 * @param string $storedFileName Nama file tersimpan dari database.
 * @param string $requestId ID korelasi log.
 * @return void
 */
function removeOldAttachment($storedFileName, $requestId)
{
    $safeFileName = basename($storedFileName);
    $oldFilePath = __DIR__ . '/../../storage/form_it/pengajuan_aplikasi/' . $safeFileName;

    if (!is_file($oldFilePath)) {
        return;
    }
    if (!unlink($oldFilePath)) {
        pengajuanAplikasiLogError(
            $requestId,
            'upload_cleanup',
            'Lampiran lama gagal dihapus setelah update berhasil.',
            ['stored_file' => $safeFileName],
            'WARNING'
        );
    }
}

