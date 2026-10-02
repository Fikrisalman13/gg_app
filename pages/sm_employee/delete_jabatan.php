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

// MenuId untuk modul Jabatan
$menuId = 41; // Ganti sesuai dengan MenuId untuk jabatan

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

// Jika tidak punya hak hapus
if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location:/gg_app/pages/sm_employee/jabatan.php');
    exit;
}

// Ambil ID dari parameter URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Jabatan tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/jabatan.php');
    exit;
}
$idJab = $_GET['id'];

// Cek apakah id_jab digunakan di tabel lain (opsional, sesuaikan jika perlu)

// Eksekusi hapus
$sqlDelete = "DELETE FROM dbo.m_jab WHERE id_jab = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$idJab]);

if ($stmtDelete === false) {
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data.";
} else {
    $_SESSION['success'] = "Data Jabatan berhasil dihapus.";
}

header('Location:/gg_app/pages/sm_employee/jabatan.php');
exit;
