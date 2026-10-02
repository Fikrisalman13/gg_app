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
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 76; // sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Ambil product code dari GET
$prodcode = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$results = [];

if ($prodcode !== '') {
    $query = "
        SELECT
            a.transdestnmbr AS transno, 
            a.transdestdate AS transdate, 
            a.transdesttype AS transtype, 
            d.transname, 
            c.wrhscode, 
            c.wrhsname, 
            b.prodcode, 
            b.prodname, 
            b.transinqty AS qty_in, 
            b.transoutqty AS qty_out, 
            e.uomname, 
            b.upddate, 
            b.upduser
        FROM whtranshd AS a
        LEFT JOIN whtransdt AS b ON a.transhdid = b.transhdid
        LEFT JOIN whwrhs AS c ON a.transdestwrhsid = c.wrhsid
        LEFT JOIN whtransms AS d ON a.transdesttype = d.transcode
        LEFT JOIN smuom AS e ON b.transuomid = e.uomid
        WHERE b.prodcode = :prodcode
        ORDER BY b.upddate ASC, a.transdesttype DESC
    ";
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':prodcode', $prodcode);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">History Produk</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">History Produk</li>
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
                                    History Transaksi Produk</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Product -->
                                <form method="get" class="form-inline mb-3">
                                    <label for="prodcode" class="mr-2">Product Code:</label>
                                    <input type="text" id="prodcode" name="prodcode" class="form-control form-control-sm mr-2" 
                                           value="<?= htmlspecialchars($prodcode) ?>" placeholder="Masukkan kode produk" required>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </form>

                                <!-- Tombol Export Excel -->
                            <?php if (!empty($results)) : ?>
                                <a href="export_excel_analisa_product.php?prodcode=<?= urlencode($prodcode) ?>" 
                                class="btn btn-success btn-sm mb-3">
                                <i class="fas fa-file-excel"></i> Export to Excel
                                </a>
                            <?php endif; ?>


                               <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="productTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>Transaksi</th>
                                                <th>Tanggal</th>
                                                <th>Warehouse</th>
                                                <th>Produk</th>
                                                <th>Qty In</th>
                                                <th>Qty Out</th>
                                                <th>UOM</th>
                                                <th>Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";

                                                // Trans No + Trans Name gabungan
                                                echo "<td>".htmlspecialchars($row['transno'])."<br><small class='text-muted'>".htmlspecialchars($row['transname'])."</small></td>";

                                                // Tanggal
                                                $transdate = $row['transdate'] ? date("d/m/Y", strtotime($row['transdate'])) : '';
                                                echo "<td class='text-center'>".$transdate."</td>";

                                                // Warehouse gabungan
                                                echo "<td>".htmlspecialchars($row['wrhscode'])."<br><small class='text-muted'>".htmlspecialchars($row['wrhsname'])."</small></td>";

                                                // Product gabungan
                                                echo "<td>".htmlspecialchars($row['prodcode'])."<br><small class='text-muted'>".htmlspecialchars($row['prodname'])."</small></td>";

                                                // Qty In / Out
                                                echo "<td class='text-right'>".number_format($row['qty_in'],2)."</td>";
                                                echo "<td class='text-right'>".number_format($row['qty_out'],2)."</td>";

                                                // UOM
                                                echo "<td class='text-center'>".htmlspecialchars($row['uomname'])."</td>";

                                                // Update Date + User gabungan
                                                $updDate = $row['upddate'] ? date("d/m/Y H:i", strtotime($row['upddate'])) : '';
                                                echo "<td>".$updDate."<br><small class='text-muted'>".htmlspecialchars($row['upduser'])."</small></td>";

                                                echo "</tr>";
                                            }
                                        } else {
                                            if ($prodcode !== '') {
                                                echo "<tr><td colspan='9' class='text-center py-4 text-danger'>Data tidak ditemukan untuk produk: <strong>".htmlspecialchars($prodcode)."</strong></td></tr>";
                                            } else {
                                                echo "<tr><td colspan='9' class='text-center py-4'>Silakan masukkan kode produk</td></tr>";
                                            }
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
        $("#productTable").DataTable({
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

