<?php 
include 'db.php'; // Koneksi ke database

// Inisialisasi variabel filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Paginasi
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
if (isset($_GET['delete'])) {
    $packingno = $_GET['delete'];
    $delete_query = "DELETE FROM whtrans_packing WHERE packingno = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("s", $packingno);
    if ($stmt->execute()) {
        echo "<script>alert('Data packing berhasil dihapus');</script>";
        echo "<script>window.location = 'lihat_data_transfer.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data packing');</script>";
    }
}

// Proses hapus multiple
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_packing'])) {
        $packingnos = implode("','", $_POST['selected_packing']);
        $delete_query = "DELETE FROM whtrans_packing WHERE packingno IN ('$packingnos')";
        if ($conn->query($delete_query)) {
            echo "<script>alert('Data packing berhasil dihapus'); window.location = 'lihat_data_transfer.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data packing');</script>";
        }
    }
}

// Query dasar
$query = "SELECT a.transnmbr, a.transdate, a.from_wrhsid, a.to_wrhsid, 
                 b.packingno, b.no_do, b.custcode, b.custname, b.nobale, 
                 b.prodcode, b.prodname, b.prdnmbr, b.totalqty, b.uom, 
                 b.created_at, b.no_rak, b.deskripsi
          FROM whtrans AS a
          INNER JOIN whtrans_packing AS b ON a.transid = b.transid 
          WHERE 1=1";

// Tambahkan filter tanggal jika ada input
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND a.transdate BETWEEN '$start_date' AND '$end_date'";
}

// Tambahkan filter pencarian
if (!empty($search)) {
    $query .= " AND (b.packingno LIKE '%$search%' OR b.nobale LIKE '%$search%')";
}

// Hitung total data
$total_query = "SELECT COUNT(*) as total FROM whtrans AS a INNER JOIN whtrans_packing AS b ON a.transid = b.transid WHERE 1=1";
if (!empty($start_date) && !empty($end_date)) {
    $total_query .= " AND a.transdate BETWEEN '$start_date' AND '$end_date'";
}
if (!empty($search)) {
    $total_query .= " AND (b.packingno LIKE '%$search%' OR b.nobale LIKE '%$search%')";
}

$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Tambahkan LIMIT untuk paginasi
$query .= " ORDER BY a.transdate DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>

<?php require_once __DIR__ . '/layout.php'; transferPackingHeader(); ?>
<link href="bootstrap.min.css" rel="stylesheet">
<style>
    .filter-box { background: #f8f9fa; padding: 12px; margin-bottom: 18px; border-radius: 6px; }
    .transfer-table { min-width: 1750px; margin-bottom: 0; font-size: .82rem; }
    .transfer-table th { white-space: nowrap; vertical-align: middle; }
    .transfer-table td { vertical-align: middle; }
    .transfer-table .cell-wrap { min-width: 115px; max-width: 180px; white-space: normal; word-break: break-word; }
    .transfer-table .action-cell { min-width: 92px; white-space: nowrap; }
    .transfer-table .action-cell .btn { display: inline-block; width: auto; margin: 0 .2rem .2rem 0; }
    .transfer-table-header { display: flex; align-items: center; width: 100%; }
    .delete-selected-button { margin-left: auto; }
    @media (max-width: 767.98px) {
        .filter-box .form-group { margin-bottom: .5rem; }
    }
</style>
<script>
    function toggleCheckboxes(source) {
        var checkboxes = document.getElementsByName('selected_packing[]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
</script>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Data Transfer</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item active">Transfer Packing</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
    <div class="card">
      <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white transfer-table-header">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i> Daftar Transfer</h3>
        <button type="submit" form="transferTableForm" name="delete_selected" class="btn btn-danger btn-sm delete-selected-button" onclick="return confirm('Hapus semua data yang dipilih?');">
          <i class="fa fa-trash"></i> Hapus Terpilih
        </button>
      </div>
      <div class="card-body">
        <div class="filter-box">
          <form method="GET" class="form-inline">
            <div class="form-group mr-2">
              <label for="start_date" class="mr-2">Dari:</label>
              <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group mr-2">
              <label for="end_date" class="mr-2">Sampai:</label>
              <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="form-group mr-2">
              <label for="search" class="mr-2">Cari:</label>
              <input type="text" id="search" name="search" class="form-control form-control-sm" placeholder="Packing No / No Bale" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
            <a href="lihat_data_transfer.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Reset</a>
          </form>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2 small">
          <span>Tampilkan <strong><?php echo $limit; ?></strong> data per halaman</span>
          <a href="/gg_app/pages/Logistik/transfer_packing/" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
     <form method="POST" id="transferTableForm">
      <div class="table-responsive">
    <table class="table table-hover table-sm transfer-table">
        <thead class="thead-light">
            <tr>
                 <th><input type="checkbox" aria-label="Pilih semua data" onclick="toggleCheckboxes(this)"></th>
                <th>No</th>
                <th>No Transaksi</th>
                <th>Tgl Transfer</th>
                <th>Dari Gudang</th>
                <th>Ke Gudang</th>
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
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                $no = $offset + 1;
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                    <td class='text-center'><input type='checkbox' name='selected_packing[]' value='{$row['packingno']}'></td>
                            <td>{$no}</td>
                            <td>{$row['transnmbr']}</td>
                            <td>{$row['transdate']}</td>
                            <td>{$row['from_wrhsid']}</td>
                            <td>{$row['to_wrhsid']}</td>
                            <td>{$row['packingno']}</td>
                            <td>{$row['no_do']}</td>
                            <td>{$row['custcode']}</td>
                            <td class='cell-wrap'>{$row['custname']}</td>
                            <td>{$row['nobale']}</td>
                            <td>{$row['prodcode']}</td>
                            <td class='cell-wrap'>{$row['prodname']}</td>
                            <td>{$row['prdnmbr']}</td>
                            <td>{$row['totalqty']}</td>
                            <td>{$row['uom']}</td>
                            <td>{$row['no_rak']}</td>
                            <td class='cell-wrap'>{$row['deskripsi']}</td>
                            <td class='action-cell'>
                                <a href='edit_data_transfer.php?packingno=" . urlencode($row['packingno']) . "' class='btn btn-warning btn-sm' title='Edit' aria-label='Edit'>
                                    <i class='fa fa-edit'></i>
                                </a>
                                <a href='?delete=" . urlencode($row['packingno']) . "' 
                                   class='btn btn-danger btn-sm btn-action' title='Hapus' aria-label='Hapus'
                                   onclick='return confirm(\"Apakah Anda yakin ingin menghapus data ini?\");'>
                                   <i class='fa fa-trash'></i>
                                </a>
                            </td>
                        </tr>";
                    $no++;
                }
            } else {
                echo "<tr><td colspan='19' class='text-center text-muted py-4'>Tidak ada data yang tersedia.</td></tr>";
            }
            ?>
        </tbody>
    </table>
      </div>
     </form>
      </div>
    </div>
    <!-- Paginasi -->
    <nav class="mt-3" aria-label="Paginasi data transfer">
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
                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">Last</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
    </section>
</div>
<?php transferPackingFooter(); ?>
