<?php
session_start();
ob_start();
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil GroupId dan MenuId
$groupId = $_SESSION['GroupId'];
$menuId = 42; // MenuId untuk Shift

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
    header('Location:/gg_app/pages/sm_employee/shift.php');
    exit;
}

// Ambil ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Shift tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/shift.php');
    exit;
}
$idShift = $_GET['id'];

// Eksekusi hapus
$sqlDelete = "DELETE FROM dbo.m_shift WHERE id_shift = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$idShift]);

if ($stmtDelete === false) {
    $errors = sqlsrv_errors();
    if (!empty($errors) && strpos($errors[0]['message'], 'REFERENCE') !== false) {
        $_SESSION['error'] = "Data tidak dapat dihapus karena sedang digunakan di tabel lain.";
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat menghapus data.";
    }
} else {
    $_SESSION['success'] = "Data Shift berhasil dihapus.";
}

header('Location:/gg_app/pages/sm_employee/shift.php');
exit;
