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

// Ambil MenuId untuk Mesin Type Weaving Inspector
$menuId = 14; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dan ambil hak akses
$canDelete = false;
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canDelete = $row['CanDelete'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

// Cek apakah pengguna memiliki hak akses CanDelete
if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

// Ambil ID dari POST request
$typeId = $_POST['id'] ?? null;
if (!$typeId) {
    $_SESSION['error'] = "ID tidak valid!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

// Query untuk menghapus data
$sql = "DELETE FROM dbo.SMMesinType WHERE TypeId = ?";
$params = [$typeId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal menghapus data!";
} else {
    $_SESSION['success'] = "Data berhasil dihapus!";
}

header('Location: mesintype_inspecting_weaving.php');
exit;