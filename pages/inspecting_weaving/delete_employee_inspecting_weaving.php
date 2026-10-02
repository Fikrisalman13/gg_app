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
requireDelete($conn, MENU_EMP_INSPECT);

// ===================================================
// 6. PROSES DELETE EMPLOYEE
// ===================================================

// Hanya menerima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit;
}

// Ambil ID Employee dari POST
$empId = $_POST['id'] ?? null;

// Validasi ID Employee
if (!$empId) {
    echo json_encode([
        "status" => "error",
        "message" => "Employee ID not found!"
    ]);
    exit;
}

// Eksekusi Query DELETE
$sqlDelete = "DELETE FROM dbo.SMEmployeeInspector WHERE EmpId = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$empId]);

// Hasil Eksekusi
if ($stmtDelete === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Error deleting data: " . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Employee successfully deleted."
]);
exit;
