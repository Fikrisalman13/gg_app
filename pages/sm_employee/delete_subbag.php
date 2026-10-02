<?php
session_start();
ob_start();
include '../../koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// MenuId untuk modul Sub Bagian
$menuId = 40; // Ganti sesuai dengan yang benar untuk subbag

// Cek hak akses CanDelete
$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canDelete = false;
if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canDelete = $row['CanDelete'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}

// Ambil ID dari parameter URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Sub Bagian tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}
$idSubbag = $_GET['id'];

// Cek relasi ke tabel m_jab (jabatan)
$sqlCheck = "SELECT COUNT(*) AS jumlah FROM dbo.m_jab WHERE id_subbag = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idSubbag]);

if ($stmtCheck === false) {
    $_SESSION['error'] = "Gagal mengecek relasi data: " . print_r(sqlsrv_errors(), true);
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}

$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
if ($rowCheck['jumlah'] > 0) {
    $_SESSION['error'] = "Tidak dapat menghapus: Sub Bagian masih digunakan di Menu Jabatan.";
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}
sqlsrv_free_stmt($stmtCheck);

// Eksekusi hapus
$sqlDelete = "DELETE FROM dbo.m_subbag WHERE id_subbag = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$idSubbag]);

if ($stmtDelete === false) {
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data.";
} else {
    $_SESSION['success'] = "Data Sub Bagian berhasil dihapus.";
}

header('Location:/gg_app/pages/sm_employee/subbag.php');
exit;
