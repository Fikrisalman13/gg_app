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
    echo json_encode([
        "status" => "error",
        "message" => "Session expired. Please login again."
    ]);
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireDelete($conn, MENU_BAGIAN_INSPECT);

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
$bagianId = $_POST['id'] ?? null;

if (!$bagianId) {
    echo json_encode([
        "status" => "error",
        "message" => "Bagian ID not found!."
    ]);
    exit();
}
// Eksekusi Query DELETE
$sqlDelete = "DELETE FROM dbo.SMBagianWInspector WHERE BagianId = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$bagianId]);

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
    "message" => "bagian successfully deleted."
]);
exit;
?>