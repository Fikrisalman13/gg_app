<?php
include 'db.php'; // Koneksi ke database

// Inisialisasi variabel filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Paginasi
$limit = 10; // Jumlah item per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Handle "hapus" action dengan mengubah nilai tertentu menjadi NULL
if (isset($_GET['delete'])) {
    $packingno = $_GET['delete'];
    $update_query = "UPDATE whtrans_packing SET deskripsi = NULL, nomor_surat_jalan = NULL, tanggal_kirim = NULL WHERE packingno = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("s", $packingno);
    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui menjadi NULL');</script>";
        echo "<script>window.location = 'lihat_data_kirim.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui data');</script>";
    }
}



// Query untuk menghitung total data
$total_query = "SELECT COUNT(*) as total FROM whtrans_packing AS b WHERE b.nomor_surat_jalan IS NOT NULL AND b.nomor_surat_jalan != ''";
if (!empty($start_date) && !empty($end_date)) {
    $total_query .= " AND b.tanggal_kirim BETWEEN '$start_date' AND '$end_date'";
}
if (!empty($search)) {
    $total_query .= " AND (b.packingno LIKE '%$search%' OR b.nobale LIKE '%$search%')";
}

$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data transfer yang memiliki nomor surat jalan
$query = "SELECT
    b.tanggal_kirim,
    b.nomor_surat_jalan,
    b.packingno, 
    b.no_do, 
    b.custcode, 
    b.custname, 
    b.nobale, 
    b.prodcode, 
    b.prodname, 
    b.prdnmbr, 
    b.totalqty, 
    b.uom, 
    b.created_at,
    b.no_rak,
    b.deskripsi
FROM whtrans_packing AS b
WHERE b.nomor_surat_jalan IS NOT NULL AND b.nomor_surat_jalan != ''";

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND b.tanggal_kirim BETWEEN '$start_date' AND '$end_date'";
}
if (!empty($search)) {
    $query .= " AND (b.packingno LIKE '%$search%' OR b.nobale LIKE '%$search%')";
}

$query .= " ORDER BY b.tanggal_kirim DESC LIMIT $limit OFFSET $offset";

$result = $conn->query($query);
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="/gg_app/pages/Logistik/transfer_packing/bootstrap.min.css" rel="stylesheet">
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
<div class="container mt-5">
    <div class="text-center mb-4">
        <h2 class="text-primary"><i class="fa fa-database"></i> Data Transfer Berhasil</h2>
    </div>

    <form method="GET" class="mb-3 row">
        <div class="col-md-3">
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="Cari Packing No / No Bale" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
            <a href="lihat_data_transfer.php" class="btn btn-secondary"><i class="fa fa-refresh"></i> Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Kirim</th>
                            <th>No Surat Jalan</th>
                            <th>Packing No</th>
                            <th>No DO</th>
                            <th>Kode Customer</th>
                            <th>Nama Customer</th>
                            <th>No Bale</th>
                            <th>Kode Produk</th>
                            <th>Nama Produk</th>
                            <th>No CP</th>
                            <th>Total Qty</th>
                            <th>Satuan</th>
                            <th>No Rak</th>
                            <th>Deskripsi</th>
                            <th>Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            $no = $offset + 1; // Menghitung nomor urut
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$no}</td>
                                        <td>{$row['tanggal_kirim']}</td>
                                        <td>{$row['nomor_surat_jalan']}</td>
                                        <td>{$row['packingno']}</td>
                                        <td>{$row['no_do']}</td>
                                        <td>{$row['custcode']}</td>
                                        <td>{$row['custname']}</td>
                                        <td>{$row['nobale']}</td>
                                        <td>{$row['prodcode']}</td>
                                        <td>{$row['prodname']}</td>
                                        <td>{$row['prdnmbr']}</td>
                                        <td>{$row['totalqty']}</td>
                                        <td>{$row['uom']}</td>
                                        <td>{$row['no_rak']}</td>
                                        <td>{$row['deskripsi']}</td>
                                        <td>{$row['created_at']}</td>
                                        <td>
                                          <a href='edit_data_kirim.php?packingno={$row['packingno']}' 
                                               class='btn btn-warning btn-sm btn-action'>
                                               <i class='fa fa-edit'></i> Edit</a>
                                            <a href='?delete={$row['packingno']}' 
                                               class='btn btn-danger btn-sm btn-action' 
                                               onclick='return confirm(\"Apakah Anda yakin ingin menghapus data ini?\");'>
                                               <i class='fa fa-trash'></i> Hapus</a>
                                        </td>
                                      </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='17' class='text-center text-danger'>Tidak ada data transfer yang tersedia.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="index.php" class="btn btn-success"><i class="fa fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </div>

    <!-- Paginasi -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">First</a>
                </li>
            <?php endif; ?>

            <?php
            // Menampilkan halaman 1 hingga 5
            for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++):
            ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode(                    $start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
    </section>
</div>
<?php transferPackingFooter(); ?>
