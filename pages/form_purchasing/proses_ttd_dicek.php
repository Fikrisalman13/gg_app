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
    echo json_encode(['success' => false, 'message' => 'Unauthorized / Sesi login telah berakhir']);
    exit;
}

$ticket = trim($_POST['ticket'] ?? '');
$signature_data = trim($_POST['signature_data'] ?? '');
$nama_dicek = trim($_POST['nama_dicek'] ?? '');

if (empty($nama_dicek)) {
    $nama_dicek = !empty($_SESSION['NamaLengkap']) ? trim($_SESSION['NamaLengkap']) : trim($_SESSION['UserName']);
}

if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Nomor tiket tidak valid']);
    exit;
}

if (empty($signature_data)) {
    echo json_encode(['success' => false, 'message' => 'Tanda tangan digital tidak boleh kosong']);
    exit;
}

// Pastikan kolom tanda tangan ada di tabel jika tabel sudah terbuat sebelumnya
$colCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Purchasing_COD' AND COLUMN_NAME = 'ttd_dicek_oleh'");
if ($colCheck && !sqlsrv_fetch_array($colCheck)) {
    sqlsrv_query($conn, "ALTER TABLE dbo.Form_Purchasing_COD ADD ttd_dibuat_oleh NVARCHAR(MAX) NULL, ttd_dicek_oleh NVARCHAR(MAX) NULL, tgl_dicek DATETIME NULL");
}

$updated_by = $_SESSION['UserName'] ?? 'system';

$sql = "UPDATE dbo.Form_Purchasing_COD 
        SET ttd_dicek_oleh = ?,
            dicek_oleh = ?,
            tgl_dicek = GETDATE(),
            status = 'Approved',
            updated_by = ?,
            updated_at = GETDATE()
        WHERE ticket = ?";

$stmt = sqlsrv_query($conn, $sql, [$signature_data, $nama_dicek, $updated_by, $ticket]);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errMsg = 'Gagal menyimpan tanda tangan ke database';
    if (!empty($errors)) {
        $errMsg .= ': ' . $errors[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

echo json_encode([
    'success' => true,
    'ticket' => $ticket,
    'nama_dicek' => $nama_dicek,
    'message' => 'Dokumen berhasil diverifikasi & ditandatangani oleh ' . $nama_dicek . ' (KABAG)!'
]);
