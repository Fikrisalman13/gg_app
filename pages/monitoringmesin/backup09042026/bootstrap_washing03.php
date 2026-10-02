<?php
declare(strict_types=1);

session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/access_control_lib.php';
require_once __DIR__ . '/helper_washing03.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$currentUserId = (string) ($_SESSION['UserId'] ?? '');
$currentUser = $_SESSION['UserName'] ?? 'SYSTEM';
$flash = null;

try {
    $pdo = ws03_pdo();
    ws03_ensure_tables($pdo);
    ws03_backfill_measured_at($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $flash = ['type' => 'danger', 'message' => 'Gagal menyiapkan tabel Washing03: ' . $e->getMessage()];
}

