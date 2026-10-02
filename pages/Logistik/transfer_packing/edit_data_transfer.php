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
    $created_at = $_POST['created_at'];
    $no_rak = $_POST['no_rak'];
    $new_packingno = $_POST['packingno'];
    $nobale = $_POST['nobale'];
    $old_packingno = $_POST['old_packingno']; // Packingno sebelum diedit

    // Update query
    $update_query = "UPDATE whtrans_packing SET created_at = ?, no_rak = ?, packingno = ?, nobale = ? WHERE packingno = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssss", $created_at, $no_rak, $new_packingno, $nobale, $old_packingno); // Pakai old_packingno

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui');</script>";
        echo "<script>window.location = 'lihat_data_transfer.php';</script>";
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
        <input type="hidden" name="old_packingno" value="<?= $row['packingno'] ?>">
        <div class="mb-3">
            <label for="created_at" class="form-label">Tanggal Dibuat</label>
            <input type="datetime-local" class="form-control" id="created_at" name="created_at" value="<?= $row['created_at'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="packingno" class="form-label">Packing No</label>
            <input type="text" class="form-control" id="packingno" name="packingno" value="<?= $row['packingno'] ?>">
        </div>
         <div class="mb-3">
            <label for="packingno" class="form-label">No Bale</label>
            <input type="text" class="form-control" id="nobale" name="nobale" value="<?= $row['nobale'] ?>">
        </div>
        <div class="mb-3">
            <label for="no_rak" class="form-label">no_rak</label>
            <textarea class="form-control" id="no_rak" name="no_rak" rows="3" required><?= $row['no_rak'] ?></textarea>
        </div>
       
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="lihat_data_transfer.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>
    </section>
</div>
<?php transferPackingFooter(); ?>
