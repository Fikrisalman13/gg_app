<?php
require 'db.php'; // Koneksi ke database

// Handle tambah data dengan tanggal otomatis
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_data'])) {
    $no_bale = $_POST['no_bale'];
    $QTY_batch = $_POST['QTY_batch'];
    $QTY_Meter = $_POST['QTY_Meter'];
    $QTY_yard = $_POST['QTY_yard'];
    $QTY_kg = $_POST['QTY_kg'];
    $no_cp = $_POST['no_cp'];
    $prod_cod = $_POST['prod_cod'];
    $Nama_product = $_POST['Nama_product'];
	$packing_no = $_POST['packing_no'];
    
    $query = "INSERT INTO bale_r (no_bale, QTY_batch, QTY_Meter, QTY_yard, QTY_kg, no_cp, prod_cod, Nama_product, tanggal_input, packing_no ) 
              VALUES ('$no_bale', '$QTY_batch', '$QTY_Meter', '$QTY_yard', '$QTY_kg', '$no_cp', '$prod_cod', '$Nama_product', NOW(),'$packing_no' )";
    mysqli_query($conn, $query);
}

// Handle hapus data
if (isset($_GET['delete'])) {
    $no_bale = $_GET['delete'];
    $query = "DELETE FROM bale_r WHERE no_bale='$no_bale'";
    mysqli_query($conn, $query);
}

// Filter berdasarkan tanggal
$where = "";
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] != "" && $_GET['end_date'] != "") {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $where = "WHERE tanggal_input BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

// Ambil data untuk ditampilkan
$query = "SELECT * FROM bale_r $where ORDER BY tanggal_input DESC";
$result = mysqli_query($conn, $query);
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link rel="stylesheet" href="/gg_app/pages/Logistik/transfer_packing/bootstrap.min.css">
<script src="/gg_app/pages/Logistik/transfer_packing/jquery-3.6.0.min.js"></script>
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
    <h2 class="text-center mb-4">Reprint Data Bale R</h2>
    

    <!-- Form Tambah Data -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col-md-3"><input type="text" name="no_bale" class="form-control" placeholder="No Bale" required></div>
		<div class="col-md-2"><input type="number" name="packing_no" class="form-control" placeholder="Packing no"></div>
            <div class="col-md-2"><input type="number" name="QTY_batch" class="form-control" placeholder="QTY Batch"></div>
            <div class="col-md-2"><input type="text" name="QTY_Meter" class="form-control" placeholder="QTY Meter"></div>
            <div class="col-md-2"><input type="text" name="QTY_yard" class="form-control" placeholder="QTY Yard"></div>
            <div class="col-md-2"><input type="text" name="QTY_kg" class="form-control" placeholder="QTY KG"></div>
        </div>
        <div class="row mt-2">
            <div class="col-md-3"><input type="text" name="no_cp" class="form-control" placeholder="No CP"></div>
            <div class="col-md-3"><input type="text" name="prod_cod" class="form-control" placeholder="Product Code"></div>
            <div class="col-md-4"><input type="text" name="Nama_product" class="form-control" placeholder="Nama Product"></div>
            <div class="col-md-2"><button type="submit" name="add_data" class="btn btn-success">Tambah</button></div>
        </div>
    </form>
        <!-- Form Filter Tanggal -->
    <form method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <input type="date" name="start_date" class="form-control" value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : '' ?>">
            </div>
            <div class="col-md-4">
                <input type="date" name="end_date" class="form-control" value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : '' ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </div>
    </form>


    <!-- Tabel Data Bale -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll"> Pilih Semua</th>
                <th>No Bale</th>
		<th>Packing No</th>
                <th>QTY Batch</th>
                <th>QTY Meter</th>
                <th>QTY Yard</th>
                <th>QTY KG</th>
                <th>No CP</th>
                <th>Product Code</th>
                <th>Nama Product</th>
                <th>Tanggal Input</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td><input type="checkbox" class="selectBale" name="no_bale[]" value="<?php echo $row['no_bale']; ?>"></td>
                    <td><?php echo $row['no_bale']; ?></td>
			 <td><?php echo $row['packing_no']; ?></td>
                    <td><?php echo $row['QTY_batch']; ?></td>
                    <td><?php echo $row['QTY_Meter']; ?></td>
                    <td><?php echo $row['QTY_yard']; ?></td>
                    <td><?php echo $row['QTY_kg']; ?></td>
                    <td><?php echo $row['no_cp']; ?></td>
                    <td><?php echo $row['prod_cod']; ?></td>
                    <td><?php echo $row['Nama_product']; ?></td>
                    <td><?php echo $row['tanggal_input']; ?></td>
                    <td>
                        <a href="?delete=<?php echo $row['no_bale']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                        <a href="edit.php?no_bale=<?php echo $row['no_bale']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Tombol Print QR Code -->
    <button class="btn btn-primary" id="printQR">Print QR Code</button>

    <script>
        $(document).ready(function () {
            // Handle Pilih Semua Checkbox
            $("#selectAll").on("change", function () {
                $(".selectBale").prop("checked", $(this).prop("checked"));
            });

            // Handle Print QR Code
            $("#printQR").on("click", function () {
                var selected = [];
                $(".selectBale:checked").each(function () {
                    selected.push($(this).val());
                });

                if (selected.length === 0) {
                    alert("Pilih minimal satu No Bale!");
                    return;
                }

                window.open("print_qr_pdf.php?no_bale[]=" + selected.join("&no_bale[]="), "_blank");
            });
        });
    </script>

    </section>
</div>
<?php transferPackingFooter(); ?>
