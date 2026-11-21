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

require_once __DIR__ . '/form_purchasing_issue_helper.php';

$ticket = trim($_POST['ticket'] ?? '');
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket tidak valid']);
    exit;
}

if (strpos($ticket, 'BPU-') === 0) {
    // 1. Hapus issue terkait dan relasi link di database
    formPurchasingDeleteIssue($conn, 'bukti_pengembalian_uang', $ticket);

    // 2. Hapus detail BPU
    sqlsrv_query($conn, "DELETE FROM dbo.Form_Purchasing_Pengembalian_Uang_Detail WHERE ticket = ?", [$ticket]);
    // Soft delete atau hard delete header BPU
    $sql = "UPDATE dbo.Form_Purchasing_Pengembalian_Uang SET is_deleted = 1, updated_at = GETDATE() WHERE ticket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt === false) {
        $sql = "DELETE FROM dbo.Form_Purchasing_Pengembalian_Uang WHERE ticket = ?";
        $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    }
} else {
    // 1. Hapus issue terkait dan relasi link di database
    formPurchasingDeleteIssue($conn, 'cash_on_delivery', $ticket);

    // 2. Hapus baris detail COD jika tabel ada
    $detailCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD_Detail'");
    if ($detailCheck && sqlsrv_fetch_array($detailCheck)) {
        sqlsrv_query($conn, "DELETE FROM dbo.Form_Purchasing_COD_Detail WHERE ticket = ?", [$ticket]);
    }

    $sql = "DELETE FROM dbo.Form_Purchasing_COD WHERE ticket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
}

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data dari database']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil dihapus!']);
