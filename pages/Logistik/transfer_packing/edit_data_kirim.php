<?php
include 'db.php'; // Koneksi ke database

// Ambil packingno dari parameter GET
$packingno = $_GET['packingno'];

// Query untuk mengambil data berdasarkan packingno
$query = "SELECT * FROM whtrans_packing WHERE packingno = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $packingno);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    
    $no_rak = $_POST['no_rak'];
    $deskripsi = $_POST['deskripsi'];
    $nomor_surat_jalan = $_POST['nomor_surat_jalan'];
    $tanggal_kirim = $_POST['tanggal_kirim'];

    // Update query
    $update_query = "UPDATE whtrans_packing SET no_rak = ?, deskripsi = ?, nomor_surat_jalan = ?, tanggal_kirim = ? WHERE packingno = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssss", $no_rak, $deskripsi, $nomor_surat_jalan, $tanggal_kirim, $packingno);
    
    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui');</script>";
        echo "<script>window.location = 'lihat_data_kirim.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data');</script>";
    }
}
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
<div class="container mt-5">
    <h2>Edit Data Packing</h2>
    <form method="POST">
        
        <div class="mb-3">
            <label for="packingno" class="form-label">Packing No</label>
            <input type="text" class="form-control" id="packingno" name="packingno" value="<?= $row['packingno'] ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="no_rak" class="form-label">No Rak</label>
            <textarea class="form-control" id="no_rak" name="no_rak" rows="3" required><?= $row['no_rak'] ?></textarea>
        </div>
        <div class="mb-3">
            <label for="deskripsi" class="form-label">Deskripsi</label>
            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required><?= $row['deskripsi'] ?></textarea>
        </div>
        <div class="mb-3">
            <label for="nomor_surat_jalan" class="form-label">Nomor Surat Jalan</label>
            <input type="text" class="form-control" id="nomor_surat_jalan" name="nomor_surat_jalan" value="<?= $row['nomor_surat_jalan'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="tanggal_kirim" class="form-label">Tanggal Kirim</label>
            <input type="date" class="form-control" id="tanggal_kirim" name="tanggal_kirim" value="<?= $row['tanggal_kirim'] ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="lihat_data_kirim.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>
    </section>
</div>
<?php transferPackingFooter(); ?>
