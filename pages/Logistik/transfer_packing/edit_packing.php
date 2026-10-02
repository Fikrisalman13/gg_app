<?php
include 'db.php'; // Pastikan file koneksi database sudah benar

// Ambil data berdasarkan packingno
if (isset($_GET['packingno'])) {
    $packingno = $_GET['packingno'];
    $sql = "SELECT * FROM whpacking WHERE packingno = '$packingno'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
    } else {
        echo "<div class='alert alert-danger'>Data tidak ditemukan!</div>";
        exit;
    }
}

// Proses update data jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_packingno = $_POST['old_packingno']; // Packingno lama
    $new_packingno = $_POST['new_packingno']; // Packingno baru
    $wrhsid = $_POST['wrhsid'];
    $no_do = $_POST['no_do'];
    $custcode = $_POST['custcode'];
    $custname = $_POST['custname'];
    $nobale = $_POST['nobale'];
    $prodcode = $_POST['prodcode'];
    $prodname = $_POST['prodname'];
    $prdnmbr = $_POST['prdnmbr'];
    $totalqty = $_POST['totalqty'];
    $uom = $_POST['uom'];

    // Update data di database
    $updateSql = "UPDATE whpacking SET 
        packingno='$new_packingno',
        wrhsid='$wrhsid', 
        no_do='$no_do', 
        custcode='$custcode', 
        custname='$custname', 
        nobale='$nobale', 
        prodcode='$prodcode', 
        prodname='$prodname', 
        prdnmbr='$prdnmbr', 
        totalqty='$totalqty', 
        uom='$uom', 
        updated_at=NOW() 
        WHERE packingno='$old_packingno'";

    if ($conn->query($updateSql) === TRUE) {
        echo "<div class='alert alert-success'>Data berhasil diperbarui! <a href='index.php'>Kembali</a></div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="/gg_app/pages/Logistik/transfer_packing/bootstrap.min.css" rel="stylesheet">
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
    <div class="container my-5">
        <h2 class="text-primary text-center mb-4"><i class="fas fa-edit"></i> Edit Data Packing</h2>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Form Edit Data Packing</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="old_packingno" value="<?= $data['packingno'] ?>">

                    <div class="mb-3">
                        <label for="new_packingno" class="form-label">Nomor Packing</label>
                        <input type="text" id="new_packingno" name="new_packingno" class="form-control" value="<?= $data['packingno'] ?>" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="wrhsid" class="form-label">Gudang</label>
                            <input type="text" id="wrhsid" name="wrhsid" class="form-control" value="<?= $data['wrhsid'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="no_do" class="form-label">No DO</label>
                            <input type="text" id="no_do" name="no_do" class="form-control" value="<?= $data['no_do'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="custcode" class="form-label">Kode Customer</label>
                            <input type="text" id="custcode" name="custcode" class="form-control" value="<?= $data['custcode'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="custname" class="form-label">Nama Customer</label>
                            <input type="text" id="custname" name="custname" class="form-control" value="<?= $data['custname'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nobale" class="form-label">No Bale</label>
                            <input type="text" id="nobale" name="nobale" class="form-control" value="<?= $data['nobale'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prodcode" class="form-label">Kode Produk</label>
                            <input type="text" id="prodcode" name="prodcode" class="form-control" value="<?= $data['prodcode'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prodname" class="form-label">Nama Produk</label>
                            <input type="text" id="prodname" name="prodname" class="form-control" value="<?= $data['prodname'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prdnmbr" class="form-label">No CP</label>
                            <input type="text" id="prdnmbr" name="prdnmbr" class="form-control" value="<?= $data['prdnmbr'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="totalqty" class="form-label">Total Qty</label>
                            <input type="number" id="totalqty" name="totalqty" class="form-control" value="<?= $data['totalqty'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="uom" class="form-label">UOM</label>
                            <input type="text" id="uom" name="uom" class="form-control" value="<?= $data['uom'] ?>" required>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </section>
</div>
<?php transferPackingFooter(); ?>
