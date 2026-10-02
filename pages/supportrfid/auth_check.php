<?php
// Mulai session dengan aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cegah warning di mode mobile/incognito
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Optional: debugging log
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/auth_debug_' . date('Y-m-d') . '.log';
    file_put_contents(
        $logFile,
        "[" . date('H:i:s') . "] No session detected from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . PHP_EOL,
        FILE_APPEND
    );

    header("Location: login.php");
    exit;
}
?>

