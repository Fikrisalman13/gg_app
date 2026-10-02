<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("ID tidak valid.");
}

$sql = "SELECT file_pdf_signed, id_kontrak FROM kontrak_kerja_perjanjian WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($row['file_pdf_signed'] === null) {
        die("File PDF belum tersedia.");
    }

    // Ambil nomor dokumen untuk nama file (opsional)
    $sql_doc = "SELECT nomor_dokumen FROM kontrak_kerja WHERE id = ?";
    $stmt_doc = sqlsrv_query($conn, $sql_doc, array($row['id_kontrak']));
    $nomor_dokumen = "kontrak_signed";
    if ($stmt_doc && $row_doc = sqlsrv_fetch_array($stmt_doc, SQLSRV_FETCH_ASSOC)) {
        $nomor_dokumen = str_replace('/', '_', $row_doc['nomor_dokumen']);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nomor_dokumen . '.pdf"');
    echo $row['file_pdf_signed'];
} else {
    die("Data tidak ditemukan.");
}
?>
