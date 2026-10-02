<?php
// ======================================================
// ping_session.php
// Menjaga session tetap hidup (SAFE VERSION)
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika session user sudah tidak ada, beri sinyal logout
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'LOGOUT';
    exit;
}

// Refresh waktu aktivitas user
$_SESSION['LAST_ACTIVITY'] = time();

/**
 * PENTING:
 * - Jangan gunakan session_regenerate_id() di ping
 * - Jangan lakukan proses berat
 * - Lepaskan session secepat mungkin
 */
session_write_close();

// Response sederhana untuk JS
echo 'OK';
