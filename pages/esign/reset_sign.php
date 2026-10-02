<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$nik = isset($_GET['nik']) ? $_GET['nik'] : '';
$source = isset($_GET['source']) ? $_GET['source'] : 'kontrak';

if ($id <= 0 || empty($nik)) {
    die("Parameter tidak valid.");
}

// Mulai transaksi
sqlsrv_begin_transaction($conn);

try {
    // 1. Reset status di kontrak_kerja
    $sql1 = "UPDATE kontrak_kerja SET status_tanda_tangan = '0', upduser = ?, upddate = GETDATE() WHERE id = ?";
    $updUser = $_SESSION['UserName'];
    $stmt1 = sqlsrv_query($conn, $sql1, array($updUser, $id));
    if ($stmt1 === false) {
        throw new Exception("Gagal reset status kontrak: " . print_r(sqlsrv_errors(), true));
    }

    // 2. Kosongkan signed PDF di kontrak_kerja_perjanjian
    $sql2 = "UPDATE kontrak_kerja_perjanjian SET file_pdf_signed = NULL WHERE id_kontrak = ?";
    $stmt2 = sqlsrv_query($conn, $sql2, array($id));
    if ($stmt2 === false) {
        throw new Exception("Gagal mengosongkan PDF: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);
    $_SESSION['success'] = "Tanda tangan berhasil direset. Dokumen kembali ke draf.";
} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

$redirect = $source === 'staff' ? 'prosesttd_staff.php' : 'prosesttd_kontrak.php';
header("Location: " . $redirect . "?nik=" . $nik);
exit;
?>
