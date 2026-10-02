<?php
/**
 * Session Keep-Alive Endpoint
 * Dipanggil secara periodik oleh browser untuk mencegah sesi PHP
 * dari timeout ketika halaman dashboard ditampilkan 24 jam.
 */

// Sama seperti header.php - gunakan cookie_lifetime 24 jam agar konsisten
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '86400');
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path'     => '/gg_app/',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (
    (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) &&
    (!isset($_SESSION['UserName']) || empty($_SESSION['UserName']))
) {
    echo json_encode(['alive' => false, 'message' => 'Session expired']);
    exit;
}

// Sentuh session agar idle timer di-reset
$_SESSION['last_ping'] = time();

echo json_encode(['alive' => true, 'ts' => time()]);
exit;
