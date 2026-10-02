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
    $_SERVER['HTTP_REFERER'] = '/gg_app/pages/dokumen_pengingat/sertifikat/index.php';
}

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 169; // MENU SERTIFIKAT
requireDelete($conn, $menuId);

// ------------------------------------------------------
// VALIDASI PARAMETER
// ------------------------------------------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "ID dokumen tidak valid.";
    header("Location: ../master_dokumen.php");
    exit;
}

// ------------------------------------------------------
// AMBIL DATA FILE (JIKA ADA)
// ------------------------------------------------------
$sqlFile = "SELECT file_path FROM dr_sertifikat WHERE id = ?";
$stmtFile = sqlsrv_query($conn, $sqlFile, [$id]);

$filePath = null;
if ($stmtFile && $row = sqlsrv_fetch_array($stmtFile, SQLSRV_FETCH_ASSOC)) {
    $filePath = $row['file_path'];
}
if ($stmtFile) sqlsrv_free_stmt($stmtFile);

// ------------------------------------------------------
// DELETE DATABASE
// ------------------------------------------------------
$sqlDelete = "DELETE FROM dr_sertifikat WHERE id = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$id]);

if (!$stmtDelete) {
    $_SESSION['error'] = "Gagal menghapus data sertifikat.";
    header("Location: ../master_dokumen.php");
    exit;
}

sqlsrv_free_stmt($stmtDelete);

// ------------------------------------------------------
// DELETE FILE FISIK (OPSIONAL)
// ------------------------------------------------------
if (!empty($filePath)) {
    $fullPath = __DIR__ . "/uploads/sertifikat/" . $filePath;
    if (file_exists($fullPath)) {
        @unlink($fullPath); // aman, tidak error walau gagal
    }
}

// ------------------------------------------------------
// SUCCESS
// ------------------------------------------------------
$_SESSION['success'] = "Dokumen sertifikat berhasil dihapus.";
header("Location: ../master_dokumen.php");
exit;
