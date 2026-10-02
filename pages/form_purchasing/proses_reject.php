<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak ditemukan']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$ticket = trim($_POST['ticket'] ?? '');
$alasan = trim($_POST['alasan'] ?? '');

if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket tidak valid']);
    exit;
}

$sql = "UPDATE dbo.Form_Purchasing_COD SET status = 'Ditolak', alasan_reject = ?, updated_by = ?, updated_at = GETDATE() WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$alasan, $_SESSION['UserName'], $ticket]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menolak pengajuan']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil ditolak!']);
