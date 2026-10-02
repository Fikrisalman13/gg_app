<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 4. IMPORT DEPENDENSI
// ===================================================
// Import konstanta dan permission
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 5. VALIDASI IZIN AKSES
// ===================================================
requireDelete($conn, MENU_USER_MANAGER);

// ===================================================
// 6. PROSES DELETE
// ===================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit();
}

// Validasi input
$userId = $_POST['id'] ?? null;

if (!$userId) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID tidak ditemukan."
    ]);
    exit();
}

// Eksekusi Query DELETE
$sqlDelete = "DELETE FROM dbo.SMUserMs WHERE UserId = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$userId]);


// Hasil
if ($stmtDelete === false) {
    echo json_encode([
        "status" => "error",
        "message" => print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "User berhasil dihapus."
]);
exit;
?>