<?php
// ======================================================
// hapus_surat_kendaraan.php — FINAL FIX (MATCH TABLE)
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

/* session wajib */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* paksa HTTP_REFERER agar permissions.php aman */
if (!isset($_SERVER['HTTP_REFERER'])) {
    $_SERVER['HTTP_REFERER'] = 'index.php';
}

// ------------------------------------------------------
// CORE INCLUDE
// ------------------------------------------------------
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 171; // MENU SURAT KENDARAAN
requireDelete($conn, $menuId);

// ------------------------------------------------------
// VALIDASI ID
// ------------------------------------------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<script>
        alert('ID tidak valid');
        window.location.href = 'index.php';
    </script>";
    exit;
}

// ------------------------------------------------------
// AMBIL DATA FILE (PASTI SESUAI TABEL)
// ------------------------------------------------------
$sqlSelect = "
SELECT file_kendaraan
FROM dr_surat_kendaraan
WHERE id = ?
";

$stmtSelect = sqlsrv_query($conn, $sqlSelect, [$id]);

if ($stmtSelect === false) {
    die('Select error: ' . print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($stmtSelect, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo "<script>
        alert('Data tidak ditemukan');
        window.location.href = 'index.php';
    </script>";
    exit;
}

// path file fisik
$uploadDir = __DIR__ . '/uploads/kendaraan/';
$filePath  = $uploadDir . $data['file_kendaraan'];

// ------------------------------------------------------
// DELETE DATA
// ------------------------------------------------------
$sqlDelete = "DELETE FROM dr_surat_kendaraan WHERE id = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$id]);

if ($stmtDelete === false) {
    die('Delete error: ' . print_r(sqlsrv_errors(), true));
}

// ------------------------------------------------------
// DELETE FILE FISIK
// ------------------------------------------------------
if (!empty($data['file_kendaraan']) && file_exists($filePath)) {
    @unlink($filePath);
}

// ------------------------------------------------------
// SUCCESS
// ------------------------------------------------------
echo "<script>
    alert('Surat kendaraan berhasil dihapus');
    window.location.href = '../master_dokumen.php';
</script>";
exit;
