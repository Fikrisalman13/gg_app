<?php
include 'db.php'; // Include koneksi database

// Cek apakah data yang diperlukan diterima
if (isset($_POST['tanggal_kirim'], $_POST['suratjalan'], $_POST['packingno'])) {
    $tanggal_kirim = $_POST['tanggal_kirim'];
    $suratjalan = $_POST['suratjalan'];
    $packingno = $_POST['packingno'];

    // Loop untuk memperbarui setiap nomor packing
    foreach ($packingno as $noPacking) {
        // Query untuk memperbarui data di tabel whtrans_packing
        $stmt = $conn->prepare("UPDATE whtrans_packing SET tanggal_kirim = ?, nomor_surat_jalan = ?, deskripsi = 'Sudah Dikirim' WHERE packingno = ?");
        $stmt->bind_param("sss", $tanggal_kirim, $suratjalan, $noPacking);

        // Eksekusi query dan cek apakah berhasil
        if (!$stmt->execute()) {
            echo 'Gagal memperbarui data: ' . $stmt->error;
            exit;
        }
    }

    // Jika semua berhasil, kirimkan response success
    echo 'success';

    // Tutup statement dan koneksi
    $stmt->close();
    $conn->close();
} else {
    echo 'Data yang dibutuhkan tidak diterima.';
}
?>
