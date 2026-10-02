<?php
// ======================================================
// hapus_dokumen_kontrak.php — FIX TANPA UBAH permissions.php
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ✅ SESSION WAJIB
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Paksa HTTP_REFERER agar permissions.php aman
if (!isset($_SERVER['HTTP_REFERER'])) {
    $_SERVER['HTTP_REFERER'] = '/gg_app/pages/dokumen_pengingat/kontrak/index.php';
}

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 168;
requireDelete($conn, $menuId);

// ------------------------------------------------------
// VALIDASI ID
// ------------------------------------------------------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: ../master_dokumen.php');
    exit;
}

// ------------------------------------------------------
// AMBIL FILE
// ------------------------------------------------------
$stmt = sqlsrv_query(
    $conn,
    "SELECT file_path FROM dr_kontrak WHERE id = ?",
    [$id]
);

$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: ../master_dokumen.php');
    exit;
}

$fileName = $row['file_path'];
$filePath = __DIR__ . '/uploads/kontrak/' . $fileName;

// ------------------------------------------------------
// DELETE DATABASE
// ------------------------------------------------------
sqlsrv_query($conn, "DELETE FROM dr_kontrak WHERE id = ?", [$id]);

// ------------------------------------------------------
// DELETE FILE
// ------------------------------------------------------
if ($fileName && file_exists($filePath)) {
    unlink($filePath);
}

$_SESSION['success'] = 'Dokumen kontrak berhasil dihapus.';
header('Location: ../master_dokumen.php');
exit;
