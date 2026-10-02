<?php
// delete_inspecting_weaving.php
session_start();

// jangan tampilkan errors ke output JSON — tapi log saja
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_delete.log');

header('Content-Type: application/json; charset=utf-8');

// buffering untuk memastikan tidak ada stray output
ob_start();

include '../../koneksi.php';

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan.'];

try {
    if (!isset($_SESSION['UserName'])) {
        throw new Exception('Silakan login terlebih dahulu.');
    }

    if (!$conn) {
        throw new Exception('Koneksi ke database gagal.');
    }

    $groupId = $_SESSION['GroupId'] ?? 0;
    $menuId = 20;

    // Cek hak akses CanDelete
    $sql = "SELECT TOP 1 CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    if ($stmt === false) throw new Exception('Gagal cek hak akses.');
    $perm = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    if (!isset($perm['CanDelete']) || $perm['CanDelete'] == 0) {
        throw new Exception('Anda tidak memiliki hak untuk menghapus data.');
    }

    if (!isset($_POST['id']) || trim($_POST['id']) === '') {
        throw new Exception('ID tidak valid.');
    }
    $noCP = trim($_POST['id']);

    // Cek referensi di SMCacatDetail
    $checkDetail = "SELECT COUNT(*) AS total FROM dbo.SMCacatDetail WHERE NoCP = ?";
    $stmtCheck = sqlsrv_query($conn, $checkDetail, [$noCP]);
    if ($stmtCheck === false) throw new Exception('Gagal memeriksa referensi transaksi.');
    $r = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);
    if (intval($r['total'] ?? 0) > 0) {
        throw new Exception('Data tidak dapat dihapus karena sudah digunakan di transaksi detail (SMCacatDetail).');
    }

    // mulai transaksi
    if (!sqlsrv_begin_transaction($conn)) {
        throw new Exception('Gagal memulai transaksi.');
    }

    // Hapus SMCacatSummary (jika ada)
    $sqlDeleteSummary = "DELETE FROM dbo.SMCacatSummary WHERE NoCP = ?";
    $stmtDelSum = sqlsrv_query($conn, $sqlDeleteSummary, [$noCP]);
    if ($stmtDelSum === false) {
        throw new Exception('Gagal menghapus SMCacatSummary: ' . print_r(sqlsrv_errors(), true));
    }

    // Hapus FormInspectHd
    $sqlDelete = "DELETE FROM dbo.FormInspectHd WHERE NoCP = ?";
    $stmtDelete = sqlsrv_query($conn, $sqlDelete, [$noCP]);
    if ($stmtDelete === false) {
        throw new Exception('Gagal menghapus FormInspectHd: ' . print_r(sqlsrv_errors(), true));
    }

    // commit
    if (!sqlsrv_commit($conn)) {
        throw new Exception('Gagal melakukan commit transaksi.');
    }

    // optional: hapus cache listing jika kamu memakai cache file
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/page_*.json') as $file) {
            @unlink($file);
        }
        @unlink($cacheDir . '/count_fgc1.cache');
    }

    $response = ['status' => 'success', 'message' => 'Data berhasil dihapus.'];

} catch (Exception $ex) {
    // log lengkap di file error, tapi kirim pesan user-friendly
    error_log("[delete_inspecting_weaving] " . $ex->getMessage());
    $response = ['status' => 'error', 'message' => $ex->getMessage()];
}

// pastikan tidak ada output selain JSON
ob_clean();
echo json_encode($response);
exit;
