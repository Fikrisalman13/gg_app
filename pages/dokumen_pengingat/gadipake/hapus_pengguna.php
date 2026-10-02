<?php
include '../koneksi.php';

// Ambil ID pengguna dari URL
$id = $_GET['id'];

// Query untuk menghapus data pengguna berdasarkan ID
$delete_query = "DELETE FROM user WHERE id = $id";

if (mysqli_query($koneksi, $delete_query)) {
    echo "Pengguna berhasil dihapus.";
    header("Location: /dokumen_pengingat/pages/pengguna.php");
    exit();
} else {
    echo "Error: " . mysqli_error($koneksi);
}
?>
