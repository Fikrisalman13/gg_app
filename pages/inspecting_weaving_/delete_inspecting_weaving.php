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
requireDelete($conn, MENU_INSPECT_HEADER);

// ===================================================
// 6. PROSES DELETE HEADER CACAT
// ===================================================

if (isset($_POST['id'])) {
    $noCP = $_POST['id'];

    // Cek referensi di tabel SMCacatDetail
    $checkDetail = "SELECT COUNT(*) AS total FROM dbo.SMCacatDetail WHERE NoCP = ?";
    $stmtCheck = sqlsrv_query($conn, $checkDetail, [$noCP]);

    if ($stmtCheck && $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        if ($row['total'] > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data tidak dapat dihapus karena sudah digunakan di transaksi detail (SMCacatDetail).'
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memeriksa referensi transaksi.'
        ]);
        exit;
    }

    // Lanjut hapus jika aman
    sqlsrv_begin_transaction($conn);

    try {
        $sqlDelete = "DELETE FROM dbo.FormInspectHd WHERE NoCP = ?";
        $stmtDelete = sqlsrv_query($conn, $sqlDelete, [$noCP]);

        if (!$stmtDelete) {
            throw new Exception("Gagal menghapus data: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus.']);
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
}


?>
