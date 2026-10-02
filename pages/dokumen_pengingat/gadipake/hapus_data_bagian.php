<?php
include '../koneksi.php';

// Cek apakah ID tersedia di URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Query untuk menghapus data berdasarkan ID
    $query = "DELETE FROM bagian WHERE id = $id";

    if (mysqli_query($koneksi, $query)) {
        // Jika berhasil, kembali ke halaman bagian.php dengan pesan sukses
        echo "<script>alert('Data berhasil dihapus.'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
    } else {
        // Jika gagal, tampilkan pesan error
        echo "<script>alert('Gagal menghapus data: " . mysqli_error($koneksi) . "'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
    }
} else {
    // Jika ID tidak ditemukan, kembali ke halaman bagian.php
    echo "<script>alert('ID tidak ditemukan!'); window.location.href='/dokumen_pengingat/pages/bagian.php';</script>";
}
