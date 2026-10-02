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

// Ambil product code dan filter type dari GET
$prodcode  = isset($_GET['prodcode'])  ? trim($_GET['prodcode'])  : '';
$transtype = isset($_GET['transtype']) ? trim($_GET['transtype']) : '';
$wrhsid    = isset($_GET['wrhsid']) && ctype_digit($_GET['wrhsid']) ? trim($_GET['wrhsid']) : '';

// Ambil daftar transaction type dari whtransms
$transTypeList = [];
try {
    $stmtMs = $conn3->query("SELECT transcode, transname FROM whtransms ORDER BY transname");
    $transTypeList = $stmtMs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transTypeList = [];
}

// Ambil daftar warehouse untuk filter
$warehouseList = [];
try {
    $stmtWrhs = $conn3->query('SELECT wrhsid, wrhscode, wrhsname FROM whwrhs ORDER BY wrhscode');
    $warehouseList = $stmtWrhs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $warehouseList = [];
}

// Lebar Select2 mengikuti label warehouse terpanjang (kode + nama).
$longestWarehouseLabel = 0;
foreach ($warehouseList as $warehouse) {
    $warehouseLabel = ($warehouse['wrhscode'] ?? '') . ' - ' . ($warehouse['wrhsname'] ?? '');
    $warehouseLabelLength = function_exists('mb_strlen') ? mb_strlen($warehouseLabel) : strlen($warehouseLabel);
    $longestWarehouseLabel = max($longestWarehouseLabel, $warehouseLabelLength);
}
$warehouseSelectWidth = min(520, max(260, (int) ceil(($longestWarehouseLabel * 8) + 70)));
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

                                    <label for="transtype" class="mr-2 ml-3">Tipe Transaksi:</label>
                                    <select id="transtype" name="transtype" class="form-control form-control-sm mr-2">
                                        <option value="">-- All --</option>
                                        <?php foreach ($transTypeList as $tt): ?>
                                        <option value="<?= htmlspecialchars($tt['transcode']) ?>"
                                            <?= ($transtype === $tt['transcode']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tt['transname']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label for="wrhsid" class="mr-2 ml-3">Warehouse:</label>
                                    <select id="wrhsid" name="wrhsid" class="form-control form-control-sm select2 warehouse-select mr-2">
                                        <option value="">-- All --</option>
                                        <?php foreach ($warehouseList as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['wrhsid']) ?>"
                                            <?= ($wrhsid === (string) $warehouse['wrhsid']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($warehouse['wrhscode'] . ' - ' . $warehouse['wrhsname']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-search-teal">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </form>

                                <!-- Tombol Export Excel -->
                            <?php if ($prodcode !== '') : ?>
                                <a href="export_excel_analisa_product.php?prodcode=<?= urlencode($prodcode) ?>&transtype=<?= urlencode($transtype) ?>&wrhsid=<?= urlencode($wrhsid) ?>" 
                                   id="btnExportExcel" class="btn btn-success btn-sm mb-3">
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
                                        <tbody></tbody>
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

<style>
    /* Footer menginisialisasi Select2 pada lebar 100%; batasi khusus filter warehouse ini. */
    .warehouse-select + .select2-container {
        width: <?= $warehouseSelectWidth ?>px !important;
    }

    .btn-search-teal {
        color: #fff;
        background-color: #20c997;
        border-color: #20c997;
    }

    .btn-search-teal:hover,
    .btn-search-teal:focus {
        color: #fff;
        background-color: #199d76;
        border-color: #199d76;
    }
</style>
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

<!-- SweetAlert2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function () {
    var prodcode  = '<?= addslashes($prodcode) ?>';
    var transtype = '<?= addslashes($transtype) ?>';
    var wrhsid    = '<?= addslashes($wrhsid) ?>';
    var warehouseSelectWidth = '<?= $warehouseSelectWidth ?>px';

    // Inisialisasi khusus filter Warehouse agar selalu menggunakan Select2.
    var $warehouseSelect = $('#wrhsid');
    if ($warehouseSelect.hasClass('select2-hidden-accessible')) {
        $warehouseSelect.select2('destroy');
    }
    $warehouseSelect.select2({
        theme: 'bootstrap4',
        width: warehouseSelectWidth,
        minimumResultsForSearch: 0
    });

    var table = $('#productTable').DataTable({
        processing : true,
        serverSide : true,
        responsive : true,
        autoWidth  : false,
        ajax: {
            url : '/gg_app/pages/analisa_proint/get_history_product_dt.php',
            type: 'POST',
            data: function (d) {
                d.prodcode  = prodcode;
                d.transtype = transtype;
                d.wrhsid    = wrhsid;
            }
        },
        columns: [
            { data: 0, className: 'text-center' },
            { data: 1 },
            { data: 2, className: 'text-center' },
            { data: 3 },
            { data: 4 },
            { data: 5, className: 'text-right' },
            { data: 6, className: 'text-right' },
            { data: 7, className: 'text-center' },
            { data: 8 },
        ],
        order: [[7, 'asc']],
        language: {
            processing : '<i class="fas fa-spinner fa-spin"></i> Memuat data...',
            lengthMenu : 'Tampilkan _MENU_ data per halaman',
            zeroRecords: 'Tidak ada data ditemukan',
            info       : 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty  : 'Tidak ada data tersedia',
            infoFiltered: '(disaring dari _MAX_ total data)',
            search     : 'Cari:',
            paginate: {
                first   : 'Pertama',
                last    : 'Terakhir',
                next    : 'Selanjutnya',
                previous: 'Sebelumnya'
            }
        }
    });

    // Handle Export Excel Loading Popup
    $('#btnExportExcel').on('click', function (e) {
        var url = $(this).attr('href');
        var downloadToken = new Date().getTime();
        $(this).attr('href', url + '&downloadToken=' + downloadToken);

        Swal.fire({
            title: 'Memproses Export...',
            html: 'Harap tunggu sebentar, data sedang disiapkan.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Loop to check the cookie
        var downloadTimer = setInterval(function () {
            var token = getCookie("downloadToken");
            if (token == downloadToken) {
                clearInterval(downloadTimer);
                deleteCookie("downloadToken");
                Swal.close();
            }
        }, 1000);

        // Reset href for next click
        setTimeout(() => {
            $(this).attr('href', url);
        }, 100);
    });

    function getCookie(name) {
        var parts = document.cookie.split(name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    }

    function deleteCookie(name) {
        document.cookie = name + '=; Max-Age=-99999999; path=/;';
    }
});
</script>

