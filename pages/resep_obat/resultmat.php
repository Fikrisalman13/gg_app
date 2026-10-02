



<?php
session_start();
ob_start();
// Koneksi utama untuk sidebar/menu (SQL Server)
include '../../koneksi.php';
// Koneksi ERP Crystal (PostgreSQL)
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn3) {
    die("Koneksi ke database gagal");
}
date_default_timezone_set('Asia/Jakarta');
// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 75; // Sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}
// Ambil parameter filter
$resultdate_start = isset($_GET['resultdate_start']) ? trim($_GET['resultdate_start']) : '';
$resultdate_end = isset($_GET['resultdate_end']) ? trim($_GET['resultdate_end']) : '';
$prodcode_input = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$transdestnmbr = isset($_GET['transdestnmbr']) ? trim($_GET['transdestnmbr']) : '';
$results = [];

if ($resultdate_start !== '' && $resultdate_end !== '') {
    $params = [$resultdate_start, $resultdate_end];
    $where = "pdresultmat.resultdate BETWEEN ? AND ?";
    $query = "SELECT\n        whtranshd.transdestnmbr AS trans_No,\n        whtranshd.transdestdate AS trans_Date,\n        whwrhs.wrhsname AS Warehouse,\n        whtransms.transname AS Trans_Name,\n        pdproductionhd.prdnmbr AS PRDNMBR,\n        pdresultmat.prodcode AS Product_code,\n        pdresultmat.prodname AS Product_name,\n        pdresultmat.matstdqty AS STD_QTY,\n        smuom.uomname AS UOM\n    FROM\n        pdresultmat\n    JOIN whtranshd\n        ON pdresultmat.whtranshdid = whtranshd.transhdid\n    JOIN pdproductionhd\n        ON pdresultmat.productionhdid = pdproductionhd.productionhdid\n    JOIN whwrhs\n        ON whtranshd.transdestwrhsid = whwrhs.wrhsid\n    JOIN whtransms\n        ON whtranshd.transdesttype = whtransms.transcode\n    JOIN smuom\n        ON pdresultmat.matstduomid = smuom.uomid\n    WHERE ";

    if ($prodcode_input !== '') {
        $prodcode_arr = array_filter(array_map('trim', preg_split('/\r?\n|,/', $prodcode_input)));
        if (count($prodcode_arr) > 0) {
            $prodcode_placeholders = implode(',', array_fill(0, count($prodcode_arr), '?'));
            $where .= " AND pdresultmat.prodcode IN ($prodcode_placeholders)";
            $params = array_merge($params, $prodcode_arr);
        }
    }
    if ($transdestnmbr !== '') {
        $where .= " AND whtranshd.transdestnmbr = ?";
        $params[] = $transdestnmbr;
    }
    // Hilangkan AND pertama jika ada
    $where = preg_replace('/^ AND /', '', $where);
    $query .= $where;
    $stmt = $conn3->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Analisa Product Result Material</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Analisa Product Result</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i> Analisa Product Result Material</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-3" id="filterForm">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="resultdate_start">Result Date Mulai:</label>
                                                <input type="date" id="resultdate_start" name="resultdate_start" class="form-control form-control-sm" value="<?= htmlspecialchars($resultdate_start) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="resultdate_end">Result Date Selesai:</label>
                                                <input type="date" id="resultdate_end" name="resultdate_end" class="form-control form-control-sm" value="<?= htmlspecialchars($resultdate_end) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="prodcode">Product Code (pisahkan koma/enter):</label>
                                                <textarea id="prodcode" name="prodcode" class="form-control form-control-sm" rows="3" placeholder="Masukkan satu atau lebih product code, pisahkan dengan koma atau enter."><?= htmlspecialchars($prodcode_input) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="transdestnmbr">Trans No (Opsional):</label>
                                                <input type="text" id="transdestnmbr" name="transdestnmbr" class="form-control form-control-sm" value="<?= htmlspecialchars($transdestnmbr) ?>" placeholder="Isi jika ingin filter Trans No">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-3 justify-content-end">
                                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm font-weight-bold px-4 shadow-sm" id="btnCari" style="min-width:140px;">
                                            <i class="fas fa-search"></i> Cari
                                        </button>
                                        <a href="?" class="btn btn-secondary btn-sm font-weight-bold px-4 shadow-sm" style="min-width:140px;">
                                            <i class="fas fa-refresh"></i> Reset
                                        </a>
                                        <?php if (count($results) > 0) : ?>
                                            <a href="export_excel_resultmat.php?resultdate_start=<?=urlencode($resultdate_start)?>&resultdate_end=<?=urlencode($resultdate_end)?>&prodcode=<?=urlencode($prodcode_input)?>&transdestnmbr=<?=urlencode($transdestnmbr)?>" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm" style="min-width:140px;">
                                                <i class="fas fa-file-excel"></i> Export ke Excel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <!-- SweetAlert2 -->
                                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                                    <script>
                                    document.getElementById('filterForm').addEventListener('submit', function(e) {
                                        var prodcode = document.getElementById('prodcode').value.trim();
                                        var transno = document.getElementById('transdestnmbr').value.trim();
                                        if (prodcode === '' && transno === '') {
                                            e.preventDefault();
                                            Swal.fire({
                                                icon: 'warning',
                                                title: 'Input Kurang Lengkap',
                                                text: 'Masukkan minimal salah satu: Product Code atau Trans No!',
                                                confirmButtonColor: '#3085d6',
                                            });
                                            return false;
                                        }
                                    });
                                    </script>
                                </form>
                                <!-- Data Table -->
                                <div class="table-responsive">
                                    <table id="resultmatTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>Trans No</th>
                                                <th>Trans Date</th>
                                                <th>Warehouse</th>
                                                <th>Trans Name</th>
                                                <th>PRDNMBR</th>
                                                <th>Product Code</th>
                                                <th>Product Name</th>
                                                <th>STD QTY</th>
                                                <th>UOM</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td>".htmlspecialchars($row['trans_no'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['trans_date'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['warehouse'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['trans_name'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['prdnmbr'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['product_code'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['product_name'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['std_qty'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['uom'] ?? '')."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='10' class='text-center py-4'>Silakan masukkan filter dan tekan Cari</td></tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $("#resultmatTable").DataTable({
            responsive: true,
            autoWidth: false,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                        <div class="card shadow-lg border-0 rounded-lg">
                            <div class="card-header bg-gradient-<?php echo htmlspecialchars($themeColor);?> text-white d-flex align-items-center" style="min-height:60px;">
                                <i class="fas fa-cubes fa-lg mr-2"></i>
                                <h3 class="card-title mb-0 font-weight-bold">Analisa Product Result Material</h3>
                            </div>
                            <div class="card-body bg-light">
                                <!-- Filter Form -->
                                <form method="get" class="mb-4 px-2 py-3 rounded shadow-sm bg-white" id="filterForm" style="border:1px solid #e3e6f0;">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3 mb-2">
                                            <label for="resultdate_start" class="font-weight-bold">Result Date Mulai:</label>
                                            <input type="date" id="resultdate_start" name="resultdate_start" class="form-control form-control-sm" value="<?= htmlspecialchars($resultdate_start) ?>">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label for="resultdate_end" class="font-weight-bold">Result Date Selesai:</label>
                                            <input type="date" id="resultdate_end" name="resultdate_end" class="form-control form-control-sm" value="<?= htmlspecialchars($resultdate_end) ?>">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label for="prodcode" class="font-weight-bold">Product Code <span class="text-muted small">(koma/enter)</span>:</label>
                                            <textarea id="prodcode" name="prodcode" class="form-control form-control-sm" rows="3" placeholder="Masukkan satu atau lebih product code, pisahkan dengan koma atau enter."><?= htmlspecialchars($prodcode_input) ?></textarea>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label for="transdestnmbr" class="font-weight-bold">Trans No <span class="text-muted small">(Opsional)</span>:</label>
                                            <input type="text" id="transdestnmbr" name="transdestnmbr" class="form-control form-control-sm" value="<?= htmlspecialchars($transdestnmbr) ?>" placeholder="Isi jika ingin filter Trans No">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-12 d-flex justify-content-end align-items-center flex-wrap gap-2">
                                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-lg font-weight-bold px-4 mx-1 shadow-sm" id="btnCari" style="min-width:20px;">
                                                <i class="fas fa-search"></i> Cari
                                            </button>
                                            <a href="?" class="col-md-12 d-flex justify-content-end align-items-center flex-wrap gap-2" style="min-width:20px;">
                                                <i class="fas fa-refresh"></i> Reset
                                            </a>
                                            <?php if (count($results) > 0) : ?>
                                                <a href="export_excel_resultmat.php?resultdate_start=<?=urlencode($resultdate_start)?>&resultdate_end=<?=urlencode($resultdate_end)?>&prodcode=<?=urlencode($prodcode_input)?>&transdestnmbr=<?=urlencode($transdestnmbr)?>" class="btn btn-success btn-lg px-4 mx-1 shadow-sm" style="min-width:40px;">
                                                    <i class="fas fa-file-excel"></i> Export ke Excel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                                <!-- Data Table -->
                                <div class="table-responsive rounded shadow-sm">
                                    <table id="resultmatTable" class="table table-hover table-bordered table-striped table-sm mb-0">
                                        <thead class="thead-dark">
                                            <tr class="text-center align-middle">
                                                <th style="background:#f8f9fc;">No</th>
                                                <th style="background:#f8f9fc;">Trans No</th>
                                                <th style="background:#f8f9fc;">Trans Date</th>
                                                <th style="background:#f8f9fc;">Warehouse</th>
                                                <th style="background:#f8f9fc;">Trans Name</th>
                                                <th style="background:#f8f9fc;">PRDNMBR</th>
                                                <th style="background:#f8f9fc;">Product Code</th>
                                                <th style="background:#f8f9fc;">Product Name</th>
                                                <th style="background:#f8f9fc;">STD QTY</th>
                                                <th style="background:#f8f9fc;">UOM</th>
                                            </tr>
