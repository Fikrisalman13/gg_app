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

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// MenuId untuk modul Department
$menuId = 38; // Ganti sesuai yang benar

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
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}

// Ambil ID dari parameter URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Department tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}
$deptId = $_GET['id'];

// Cek apakah id_dept dipakai di tabel lain (contoh: m_bag)
$sqlCheck = "SELECT COUNT(*) AS jumlah FROM dbo.m_bag WHERE id_dept = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$deptId]);

if ($stmtCheck === false) {
    $_SESSION['error'] = "Gagal mengecek relasi data: " . print_r(sqlsrv_errors(), true);
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}

$rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
if ($rowCheck['jumlah'] > 0) {
    $_SESSION['error'] = "Tidak dapat menghapus: Department masih digunakan di Menu Bagian.";
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}
sqlsrv_free_stmt($stmtCheck);

// Lanjut hapus jika aman
$sqlDelete = "DELETE FROM dbo.m_dept WHERE id_dept = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$deptId]);

if ($stmtDelete === false) {
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data.";
} else {
    $_SESSION['success'] = "Data Department berhasil dihapus.";
}

header('Location:/gg_app/pages/sm_employee/dept.php');
exit;
