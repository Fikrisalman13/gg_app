<?php
include 'db.php'; // Ensure this is connected to your database

// Pagination settings
$recordsPerPage = 50; // Records per page
$page = isset($_GET['page']) ? $_GET['page'] : 1; // Current page (default is 1)
$offset = ($page - 1) * $recordsPerPage; // Calculate the offset for the SQL query

// Hapus data berdasarkan packingno
if (isset($_GET['delete'])) {
    $packingno = $_GET['delete'];
    $deleteSql = "DELETE FROM whpacking WHERE packingno = '$packingno'";
    if ($conn->query($deleteSql) === TRUE) {
        echo "<div class='alert alert-success'>Data berhasil dihapus!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
// Menyimpan data packing jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packingno = $_POST['packingno'];

    // Cek apakah packingno sudah ada dalam database
    $checkSql = "SELECT COUNT(*) AS count FROM whpacking WHERE packingno = '$packingno'";
    $checkResult = $conn->query($checkSql);
    $row = $checkResult->fetch_assoc();

    if ($row['count'] > 0) {
        echo "<script>alert('Packing No sudah terinput sebelumnya!'); window.history.back();</script>";
    } else {

    $packingno = $_POST['packingno'];
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

    // Query untuk menyimpan data ke tabel whpacking
    $sql = "INSERT INTO whpacking (packingno, wrhsid, no_do, custcode, custname, nobale, prodcode, prodname, prdnmbr, totalqty, uom, created_at)
            VALUES ('$packingno', '$wrhsid', '$no_do', '$custcode', '$custname', '$nobale', '$prodcode', '$prodname', '$prdnmbr', '$totalqty', '$uom', NOW())";

    if ($conn->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Data berhasil disimpan!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
}

// Sorting and filtering logic
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'DESC'; // Default descending order
$sortQuery = "ORDER BY created_at $sortOrder";
// Ambil parameter sort
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at DESC'; // Default

// Pastikan hanya nilai yang valid dapat digunakan
$allowedSortColumns = ['created_at DESC', 'created_at ASC', 'updated_at DESC', 'updated_at ASC'];
if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'created_at DESC';
}

// Sorting query
$sortQuery = "ORDER BY $sort";



// Filter by date range
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Filter by search keyword
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';
$searchQuery = "";
if ($searchKeyword) {
    $searchQuery = "AND (packingno LIKE '%$searchKeyword%' OR no_do LIKE '%$searchKeyword%' OR custname LIKE '%$searchKeyword%' OR prodname LIKE '%$searchKeyword%' OR prodcode LIKE '%$searchKeyword%')";
}

// Date range filter
if ($dateFrom && $dateTo) {
    $filterDateQuery = "AND created_at BETWEEN '$dateFrom' AND '$dateTo'";
} else {
    $filterDateQuery = '';
}

// Combine all filters
$finalQuery = "SELECT * FROM whpacking WHERE 1=1 $searchQuery $filterDateQuery $sortQuery LIMIT $offset, $recordsPerPage";

// Menampilkan data packing dari database
$result = $conn->query($finalQuery);

// Get total number of records for pagination
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM whpacking WHERE 1=1 $searchQuery $filterDateQuery");
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get the page range
$range = 2; // Number of page links to show in the range
$startPage = max(1, $page - $range);
$endPage = min($totalPages, $page + $range);

?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="/gg_app/pages/Logistik/transfer_packing/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f3f5;
        }
        .form-control, .form-select {
            box-shadow: none !important;
        }
        .btn-danger {
            color: #fff;
            background-color: #dc3545;
        }
    </style>
<div class="content-wrapper transfer-packing-page">
    <section class="content p-3">
    <div class="container my-5">
        <h2 class="text-primary text-center mb-4"><i class="fa-solid fa-list"></i> Input Data Packing Bale</i></h2>

        <!-- Form Input -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Tambah Data Packing</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="packingno" class="form-label">Nomor Packing</label>
                            <input type="text" id="packingno" name="packingno" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="wrhsid" class="form-label">Gudang</label>
                            <input type="text" id="wrhsid" name="wrhsid" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="no_do" class="form-label">No DO</label>
                            <input type="text" id="no_do" name="no_do" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="custcode" class="form-label">Kode Customer</label>
                            <input type="text" id="custcode" name="custcode" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="custname" class="form-label">Nama Customer</label>
                            <input type="text" id="custname" name="custname" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nobale" class="form-label">No Bale</label>
                            <input type="text" id="nobale" name="nobale" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prodcode" class="form-label">Kode Produk</label>
                            <input type="text" id="prodcode" name="prodcode" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prodname" class="form-label">Nama Produk</label>
                            <input type="text" id="prodname" name="prodname" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prdnmbr" class="form-label">No CP</label>
                            <input type="text" id="prdnmbr" name="prdnmbr" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="totalqty" class="form-label">Total Qty</label>
                            <input type="number" id="totalqty" name="totalqty" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="uom" class="form-label">UOM</label>
                            <input type="text" id="uom" name="uom" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter dan Sort -->
        <div class="my-4">
            <form method="GET" class="row gy-2 gx-3 align-items-center">
                <div class="col-md-3">
                    <label for="search" class="form-label">Cari</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Cari data...">
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" id="date_from" name="date_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Hingga Tanggal</label>
                    <input type="date" id="date_to" name="date_to" class="form-control">
                </div>
                
                <div class="col-md-3">
    <label for="sort" class="form-label">Urutkan Berdasarkan</label>
    <select id="sort" name="sort" class="form-select">
        <option value="created_at DESC">Tanggal Input Terbaru</option>
        <option value="created_at ASC">Tanggal Input Terlama</option>
        <option value="updated_at DESC">Update Terakhir Terbaru</option>
        <option value="updated_at ASC">Update Terakhir Terlama</option>
    </select>
</div>
<div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Data -->
        <div class="card">
            <div class="card-body">
                <table class="table table-hover text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Nomor Packing</th>
                            <th>Nomor Bale</th>
                            <th>No DO</th>
                            <th>Nama Customer</th>
                            <th>Nama Produk</th>
                            <th>Total Qty</th>
                            <th>UOM</th>
                            <th>Tanggal Input</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['packingno'] ?></td>
                                <td><?= $row['nobale'] ?></td>
                                <td><?= $row['no_do'] ?></td>
                                <td><?= $row['custname'] ?></td>
                                <td><?= $row['prodname'] ?></td>
                                <td><?= $row['totalqty'] ?></td>
                                <td><?= $row['uom'] ?></td>
                                <td><?= $row['created_at'] ?></td>
                                <td>
<a href="edit_packing.php?packingno=<?= $row['packingno'] ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
                                    <a href="?delete=<?= $row['packingno'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus data ini?');" title="Hapus Data">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
         <!-- Pagination -->
         <nav>
            <ul class="pagination mt-4 justify-content-center">
                <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=1">First</a>
                </li>
                <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                </li>
                <?php for ($i = $startPage; $i <= $endPage; $i++) { ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
                <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                </li>
                <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $totalPages ?>">Last</a>
                </li>
            </ul>
        </nav>
    </div>
    </section>
</div>
<?php transferPackingFooter(); ?>
