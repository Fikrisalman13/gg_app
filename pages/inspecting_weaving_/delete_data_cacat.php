<?php
session_start();
ob_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'] ?? null;

// Pastikan GroupId valid
if (!$groupId) {
    $_SESSION['error'] = "Session tidak valid.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Ambil MenuId untuk Inspecting Weaving
$menuId = 23;

// Query untuk mengambil hak akses
$sql = "SELECT TOP 1 CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal memeriksa hak akses: " . print_r(sqlsrv_errors(), true);
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

$permissions = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?? [];
sqlsrv_free_stmt($stmt);

// Cek apakah pengguna memiliki hak akses CanDelete
if (empty($permissions) || $permissions['CanDelete'] == 0) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Ambil Id dari POST request dan validasi
if (!empty($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);

    // Ambil NoCP berdasarkan Id yang akan dihapus
    $sqlGetNoCP = "SELECT NoCP FROM dbo.SMCacatDetail WHERE Id = ?";
    $paramsGetNoCP = [$id];
    $stmtGetNoCP = sqlsrv_query($conn, $sqlGetNoCP, $paramsGetNoCP);

    $noCP = null;
    if ($stmtGetNoCP !== false && $row = sqlsrv_fetch_array($stmtGetNoCP, SQLSRV_FETCH_ASSOC)) {
        $noCP = $row['NoCP'];
    }
    sqlsrv_free_stmt($stmtGetNoCP);

    // Lakukan penghapusan data
    $sqlDelete = "DELETE FROM dbo.SMCacatDetail WHERE Id = ?";
    $paramsDelete = [$id];
    $stmtDelete = sqlsrv_query($conn, $sqlDelete, $paramsDelete);

    if ($stmtDelete === false) {
        $_SESSION['error'] = "Terjadi kesalahan saat menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data berhasil dihapus.";

        // Cek apakah masih ada data dengan NoCP yang sama di SMCacatDetail
        if ($noCP) {
            $sqlCheck = "SELECT COUNT(*) AS count FROM dbo.SMCacatDetail WHERE NoCP = ?";
            $paramsCheck = [$noCP];
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

            if ($stmtCheck !== false && $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
                if ($rowCheck['count'] == 0) {
                    // Jika tidak ada lagi data dengan NoCP tersebut, ubah FgCacat menjadi NULL
                    $sqlUpdateFgCacat = "UPDATE dbo.FormInspectHd SET FgCacat = NULL WHERE NoCP = ?";
                    sqlsrv_query($conn, $sqlUpdateFgCacat, $paramsCheck);
                }
            }
            sqlsrv_free_stmt($stmtCheck);
        }
    }
    sqlsrv_free_stmt($stmtDelete);
} else {
    $_SESSION['error'] = "Data tidak valid.";
}

sqlsrv_close($conn);
header('Location: datacacat_inspecting_weaving.php');
exit;
ob_end_flush();
?>
