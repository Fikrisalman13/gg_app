<?php
// Sertakan file koneksi database
include 'db.php';

// Ambil filter status dari parameter URL
$status = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Hitung total data terkirim dan belum terkirim
$query_total_terkirim = "SELECT COUNT(*) AS total FROM whtrans_packing WHERE deskripsi = 'Sudah Dikirim'";
$query_total_belum = "SELECT COUNT(*) AS total FROM whtrans_packing WHERE deskripsi IS NULL OR deskripsi <> 'Sudah Dikirim'";

$result_terkirim = mysqli_query($conn, $query_total_terkirim);
$result_belum = mysqli_query($conn, $query_total_belum);

$total_terkirim = mysqli_fetch_assoc($result_terkirim)['total'];
$total_belum = mysqli_fetch_assoc($result_belum)['total'];

// Query berdasarkan filter status
$query = "SELECT packingno, no_do, custcode, custname, nobale, prodcode, prodname, prdnmbr, totalqty, uom, created_at, no_rak, deskripsi, nomor_surat_jalan, tanggal_kirim FROM whtrans_packing";
if ($status == "terkirim") {
    $query .= " WHERE deskripsi = 'Sudah Dikirim'";
} elseif ($status == "belum") {
    $query .= " WHERE deskripsi IS NULL OR deskripsi <> 'Sudah Dikirim'";
}
$query .= " LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
    <div class="container mt-4">
        <h2 class="text-center">Laporan Data Titipan Gudang3</h2>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card p-3 shadow-sm bg-white">
                    <h5 class="text-success">Total Terkirim: <?php echo $total_terkirim; ?> Packing No</h5>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-3 shadow-sm bg-white">
                    <h5 class="text-danger">Total Packing no yang ada: <?php echo $total_belum; ?> Packing No</h5>
                </div>
            </div>
        </div>
        
        <form method="GET" action="" class="mb-3">
            <label for="status" class="form-label">Filter Status:</label>
            <select name="status" id="status" class="form-select">
                <option value="">Semua</option>
                <option value="terkirim" <?php if ($status == 'terkirim') echo 'selected'; ?>>Sudah Dikirim</option>
                <option value="belum" <?php if ($status == 'belum') echo 'selected'; ?>>Belum Dikirim</option>
            </select>
            <button type="submit" class="btn btn-primary mt-2">Filter</button>
        </form>
        
        <a href="export_laporan.php?status=<?php echo $status; ?>" class="btn btn-success mb-3">Download Excel</a>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Packing No</th>
                        <th>No DO</th>
                        <th>Customer Code</th>
                        <th>Customer Name</th>
                        <th>No Bale</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Production Number</th>
                        <th>Total Quantity</th>
                        <th>UOM</th>
                        <th>Created At</th>
                        <th>No Rak</th>
                        <th>Deskripsi</th>
                        <th>Nomor Surat Jalan</th>
                        <th>Tanggal Kirim</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $row['packingno']; ?></td>
                            <td><?php echo $row['no_do']; ?></td>
                            <td><?php echo $row['custcode']; ?></td>
                            <td><?php echo $row['custname']; ?></td>
                            <td><?php echo $row['nobale']; ?></td>
                            <td><?php echo $row['prodcode']; ?></td>
                            <td><?php echo $row['prodname']; ?></td>
                            <td><?php echo $row['prdnmbr']; ?></td>
                            <td><?php echo $row['totalqty']; ?></td>
                            <td><?php echo $row['uom']; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td><?php echo $row['no_rak']; ?></td>
                            <td><?php echo $row['deskripsi']; ?></td>
                            <td><?php echo $row['nomor_surat_jalan']; ?></td>
                            <td><?php echo $row['tanggal_kirim']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <?php
        $total_query = "SELECT COUNT(*) AS total FROM whtrans_packing";
        $total_result = mysqli_query($conn, $total_query);
        $total_row = mysqli_fetch_assoc($total_result);
        $total_pages = ceil($total_row['total'] / $limit);
        
        $max_links = 15;
        $start_page = max(1, $page - floor($max_links / 2));
        $end_page = min($total_pages, $start_page + $max_links - 1);
        ?>
        <nav>
            <ul class="pagination">
                <?php if ($start_page > 1) { ?>
                    <li class="page-item"><a class="page-link" href="?status=<?php echo $status; ?>&page=1">1</a></li>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php } ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++) { ?>
                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>"> <?php echo $i; ?> </a>
                    </li>
                <?php } ?>
                <?php if ($end_page < $total_pages) { ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <li class="page-item"><a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                <?php } ?>
            </ul>
        </nav>
    </div>
    </section>
</div>
<?php transferPackingFooter(); ?>
