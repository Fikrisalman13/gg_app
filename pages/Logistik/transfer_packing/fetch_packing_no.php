<?php
include 'db.php';

// Query untuk mengambil semua packingno yang belum memiliki nomor_surat_jalan
$query = "SELECT DISTINCT packingno 
          FROM whtrans_packing 
          WHERE nomor_surat_jalan IS NULL OR nomor_surat_jalan = ''";

$result = mysqli_query($conn, $query);

// Memeriksa apakah query berhasil
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<option value="' . $row['packingno'] . '">' . $row['packingno'] . '</option>';
    }
}
?>
