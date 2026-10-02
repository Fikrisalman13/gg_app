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

// Ambil MenuId untuk Cacat Weaving Inspector
$menuId = 13; // Sesuaikan dengan MenuId yang benar

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
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

// Ambil ID dari parameter URL
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = "ID Cacat tidak valid.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

$cacatId = (int) $_POST['id'];


// Cek apakah ID ada dalam database sebelum menghapus
$checkSql = "SELECT COUNT(*) AS count FROM dbo.SMCacat WHERE CacatId = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, [$cacatId]);

if ($checkStmt === false) {
    die("Kesalahan saat mengecek data: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($checkStmt);

if ($row['count'] == 0) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

// Query untuk menghapus data cacat berdasarkan ID
$sql = "DELETE FROM dbo.SMCacat WHERE CacatId = ?";
$params = [$cacatId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data: " . print_r(sqlsrv_errors(), true);
} else {
    $_SESSION['success'] = "Data Cacat berhasil dihapus.";
}

header('Location: smcacat_inspecting_weaving.php');
exit;
