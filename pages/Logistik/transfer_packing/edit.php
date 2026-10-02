<?php
require 'db.php'; // Koneksi ke database

// Cek apakah No Bale diberikan dalam URL
if (isset($_GET['no_bale'])) {
    $no_bale_lama = $_GET['no_bale']; // Simpan No Bale lama

    // Ambil data berdasarkan No Bale lama
    $query = "SELECT * FROM bale_r WHERE no_bale = '$no_bale_lama'";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);

    // Jika form disubmit
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_data'])) {
        $no_bale_baru = $_POST['no_bale']; // No Bale yang baru
        $QTY_batch = $_POST['QTY_batch'];
        $QTY_Meter = $_POST['QTY_Meter'];
        $QTY_yard = $_POST['QTY_yard'];
        $QTY_kg = $_POST['QTY_kg'];
        $no_cp = $_POST['no_cp'];
        $prod_cod = $_POST['prod_cod'];
        $Nama_product = $_POST['Nama_product'];

        // Cek apakah No Bale diubah
        if ($no_bale_lama !== $no_bale_baru) {
            // Hapus data lama
            $query_delete = "DELETE FROM bale_r WHERE no_bale = '$no_bale_lama'";
            mysqli_query($conn, $query_delete);

            // Tambahkan data baru dengan No Bale yang baru
            $query_insert = "INSERT INTO bale_r (no_bale, QTY_batch, QTY_Meter, QTY_yard, QTY_kg, no_cp, prod_cod, Nama_product, tanggal_input) 
                             VALUES ('$no_bale_baru', '$QTY_batch', '$QTY_Meter', '$QTY_yard', '$QTY_kg', '$no_cp', '$prod_cod', '$Nama_product', NOW())";
            mysqli_query($conn, $query_insert);
        } else {
            // Jika No Bale tidak berubah, cukup update data
            $query_update = "UPDATE bale_r SET 
                             QTY_batch = '$QTY_batch', 
                             QTY_Meter = '$QTY_Meter', 
                             QTY_yard = '$QTY_yard', 
                             QTY_kg = '$QTY_kg', 
                             no_cp = '$no_cp', 
                             prod_cod = '$prod_cod', 
                             Nama_product = '$Nama_product'
                             WHERE no_bale = '$no_bale_baru'";
            mysqli_query($conn, $query_update);
        }

        // Redirect ke halaman utama
        header("Location: reprint.php");
        exit;
    }
} else {
    // Jika tidak ada No Bale yang diberikan, kembali ke index
    header("Location: reprint.php");
    exit;
}
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
    <h2 class="text-center mb-4">Edit Data Bale</h2>

    <!-- Form Edit Data -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <input type="text" name="no_bale" class="form-control" value="<?= $data['no_bale']; ?>" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="QTY_batch" class="form-control" value="<?= $data['QTY_batch']; ?>" required>
            </div>
            <div class="col-md-2">
                <input type="text" name="QTY_Meter" class="form-control" value="<?= $data['QTY_Meter']; ?>" required>
            </div>
            <div class="col-md-2">
                <input type="text" name="QTY_yard" class="form-control" value="<?= $data['QTY_yard']; ?>" required>
            </div>
            <div class="col-md-2">
                <input type="text" name="QTY_kg" class="form-control" value="<?= $data['QTY_kg']; ?>" required>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-3">
                <input type="text" name="no_cp" class="form-control" value="<?= $data['no_cp']; ?>" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="prod_cod" class="form-control" value="<?= $data['prod_cod']; ?>" required>
            </div>
            <div class="col-md-4">
                <input type="text" name="Nama_product" class="form-control" value="<?= $data['Nama_product']; ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" name="update_data" class="btn btn-success">Simpan</button>
            </div>
        </div>
    </form>

    <a href="reprint.php" class="btn btn-secondary">Kembali</a>
    </section>
</div>
<?php transferPackingFooter(); ?>
