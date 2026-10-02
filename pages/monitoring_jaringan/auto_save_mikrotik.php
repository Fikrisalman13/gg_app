<?php
// ======================================================
// auto_save_mikrotik.php - Autosave data Mikrotik
// ======================================================

// ======================================================
// 1. SESSION & TIMEZONE
// ======================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

// ======================================================
// 2. VALIDASI LOGIN (Optional - bisa dijalankan tanpa login)
// ======================================================
// Uncomment jika ingin memastikan user login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    die("UNAUTHORIZED");
}

// ======================================================
// 3. KONEKSI & DEPENDENSI
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// 4. PREVENT DOUBLE-RUN PER MENIT
// ======================================================
$lastRunFile = __DIR__ . "/last_run.txt";
$now = date("Y-m-d H:i");

if (file_exists($lastRunFile)) {
    $last = trim(file_get_contents($lastRunFile));

// ==========================
// JIKA FILE SUDAH DI JALANKAN PADA MENIT YANG SAMA, MAKA STOP
// ==========================
    if ($last === $now) {
        die("ALREADY_SAVED");
    }
}

// ======================================================
// 5. TIME STAMP
// ======================================================
file_put_contents($lastRunFile, $now);


// ======================================================
// 6. HELPER FUNCTIONS
// ======================================================
function cleanTarget($target) {
    return explode('/', $target)[0];
}


// ======================================================
// 7. FETCH MIKROTIK DATA
// ======================================================
try {
$API = new RouterosAPI();

if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

    $queues = $API->comm('/queue/simple/print');
    $API->disconnect();

    foreach ($queues as $q) {

        if (isset($q['bytes']) && strpos($q['bytes'], '/') !== false) {
            [$uBytes, $dBytes] = explode('/', $q['bytes']);
        } else {
            $uBytes = 0;
            $dBytes = 0;
        }

        $name   = $q['name'] ?? '-';
        $target = cleanTarget($q['target'] ?? '-');

        $upload    = (int)$uBytes;
        $download  = (int)$dBytes;
        $totalData = $upload + $download;

        // ======================================================
        // 8. INSERT ke SQL Server
        // ======================================================
        $sql = "INSERT INTO monitoring_jaringan 
                (date, name, target, upload, download, total, created_at)
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

        $params = [
            date('Y-m-d H:i:s'),  
            $name,
            $target,
            $upload,
            $download,
            $totalData
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if (!$stmt) {
            $errors = sqlsrv_errors();
            error_log("SQL ERROR auto_save_mikrotik: " . print_r($errors, true));
            // Log ke file untuk debugging
            file_put_contents(__DIR__ . '/autosave_error.log', 
                date('Y-m-d H:i:s') . " - SQL Error: " . print_r($errors, true) . "\n", 
                FILE_APPEND
            );
        }
    }

    echo "OK";

} else {
    echo "FAILED_CONNECT";
    error_log("Mikrotik connection failed at " . date('Y-m-d H:i:s'));
}
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
    error_log("Exception in auto_save_mikrotik: " . $e->getMessage());
}
?>
