<?php

/**
 * Membuat ID korelasi aman untuk respons gagal dan log Form Pengajuan Aplikasi.
 *
 * @return string ID korelasi request.
 */
function pengajuanAplikasiRequestId()
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Exception $exception) {
        return uniqid('app-', true);
    }
}

/**
 * Menulis kegagalan aplikasi sebagai JSON satu baris tanpa data sensitif.
 *
 * Logging tidak melempar error agar kegagalan menulis log tidak menutupi respons utama.
 *
 * @param string $requestId ID korelasi request.
 * @param string $action Aksi aplikasi yang gagal.
 * @param string $message Pesan internal aman.
 * @param array $context Konteks yang sudah disanitasi.
 * @param string $severity Severity log.
 * @return void
 */
function pengajuanAplikasiLogError(
    $requestId,
    $action,
    $message,
    $context = [],
    $severity = 'ERROR'
) {
    $logDirectory = __DIR__ . '/../../../logs';
    if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true)) {
        return;
    }

    $logEntry = [
        'timestamp' => date(DATE_ATOM),
        'severity' => $severity,
        'request_id' => $requestId,
        'module' => 'form_it/pengajuan_aplikasi',
        'action' => $action,
        'user' => $_SESSION['UserName'] ?? null,
        'message' => $message,
        'source' => basename($_SERVER['SCRIPT_FILENAME'] ?? __FILE__),
        'context' => $context,
    ];
    $encodedEntry = json_encode($logEntry, JSON_UNESCAPED_UNICODE);

    if ($encodedEntry === false) {
        return;
    }

    file_put_contents(
        $logDirectory . '/error-' . date('Y-m-d') . '.log',
        $encodedEntry . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

