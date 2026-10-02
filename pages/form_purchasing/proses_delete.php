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
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket tidak valid']);
    exit;
}

// Hapus baris detail jika tabel ada
$detailCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD_Detail'");
if ($detailCheck && sqlsrv_fetch_array($detailCheck)) {
    sqlsrv_query($conn, "DELETE FROM dbo.Form_Purchasing_COD_Detail WHERE ticket = ?", [$ticket]);
}

$sql = "DELETE FROM dbo.Form_Purchasing_COD WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data dari database']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil dihapus!']);
