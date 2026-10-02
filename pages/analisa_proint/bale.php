
<?php
session_start();
ob_start();
include '../../koneksi.php';
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
// Ambil parameter batchno multi-line
$batchno_input = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
$results = [];

if ($batchno_input !== '') {
    // Proses input: satu batchno per baris, buang kosong
    $batchnos = array_filter(array_map('trim', preg_split('/\r?\n/', $batchno_input)));
    if (count($batchnos) > 0) {
        $placeholders = implode(',', array_fill(0, count($batchnos), '?'));
        $query = "WITH LatestUpdate AS (\n    SELECT batchno, MAX(upddate) AS max_upddate FROM whbaledt GROUP BY batchno\n)\nSELECT DISTINCT\n    wb.balehdid AS balehdid,\n    wb.refnmbr AS baleno,\n    wb.balenmbr AS packingno,\n    rd.batchno AS batchno\nFROM pdresultdt rd\nINNER JOIN pdresulthd rh ON rd.resulthdid = rh.resulthdid\nLEFT JOIN smuom uom1 ON rd.resultstduomid = uom1.uomid\nLEFT JOIN smuom uom2 ON rd.resultuomid = uom2.uomid\nINNER JOIN pdproductionhd ph ON rh.productionhdid = ph.productionhdid\nLEFT JOIN smuom uom ON rd.resultuomid = uom.uomid\nLEFT JOIN whtransdtbatch tb ON rd.batchno = tb.batchno AND rd.whtranshdid = tb.transhdid\nINNER JOIN whbaledt wd ON rd.batchno = wd.batchno\nINNER JOIN LatestUpdate lu ON wd.batchno = lu.batchno AND wd.upddate = lu.max_upddate\nINNER JOIN whbalehd wb ON wd.balehdid = wb.balehdid\nINNER JOIN whbaleprod wp ON wd.balehdid = wp.balehdid AND wp.prodid = tb.prodid\nINNER JOIN whwrhs wh ON wp.wrhsid = wh.wrhsid\nWHERE wd.batchno IN ($placeholders)";
        $stmt = $conn3->prepare($query);
        $stmt->execute($batchnos);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">cek baleno dan packingno by Batch</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">History Batch</li>
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
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                                History Transaksi Batch</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="batchno">Batch No (satu per baris):</label>
                                                <textarea id="batchno" name="batchno" class="form-control form-control-sm" rows="5" placeholder="Masukkan satu batchno per baris"><?= htmlspecialchars($batchno_input) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6" style="margin-top: 32px;">
                                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                                <i class="fas fa-search"></i> Cari
                                            </button>
                                            <a href="?" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-refresh"></i> Reset
                                            </a>
                                            <?php if ($batchno_input !== '' && count($results) > 0): ?>
                                                <a href="export_excel_bale.php?batchno=<?= urlencode($batchno_input) ?>" class="btn btn-success btn-sm ml-2">
                                                    <i class="fas fa-file-excel"></i> Export ke Excel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>

                                <?php // Tombol export Excel sudah ada di form atas, bagian ini dihapus agar tidak error dan tidak dobel ?>

                               <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="baleTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>Balehdid</th>
                                                <th>Baleno</th>
                                                <th>Packingno</th>
                                                <th>Batchno</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td>".htmlspecialchars($row['balehdid'])."</td>";
                                                echo "<td>".htmlspecialchars($row['baleno'])."</td>";
                                                echo "<td>".htmlspecialchars($row['packingno'])."</td>";
                                                echo "<td>".htmlspecialchars($row['batchno'])."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='text-center py-4'>Silakan masukkan Batch No</td></tr>";
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
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $("#batchTable").DataTable({
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
                previous: "Sebelumnya"
            }
        },
    });
    });
</script>
