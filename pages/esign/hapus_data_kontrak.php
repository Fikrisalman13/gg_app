<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$nik = isset($_GET['nik']) ? $_GET['nik'] : '';

if ($id <= 0 || empty($nik)) {
    die("Parameter tidak valid.");
}

// Mulai transaksi
sqlsrv_begin_transaction($conn);

try {
    // 1. Hapus dari kontrak_kerja_perjanjian
    $sql1 = "DELETE FROM kontrak_kerja_perjanjian WHERE id_kontrak = ?";
    $stmt1 = sqlsrv_query($conn, $sql1, array($id));
    if ($stmt1 === false) {
        throw new Exception("Gagal menghapus data dari kontrak_kerja_perjanjian: " . print_r(sqlsrv_errors(), true));
    }

    // 2. Hapus dari kontrak_kerja
    $sql2 = "DELETE FROM kontrak_kerja WHERE id = ?";
    $stmt2 = sqlsrv_query($conn, $sql2, array($id));
    if ($stmt2 === false) {
        throw new Exception("Gagal menghapus data dari kontrak_kerja: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);
    $_SESSION['success'] = "Data kontrak berhasil dihapus.";
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

header("Location: prosesttd_kontrak.php?nik=" . $nik);
exit;
?>
