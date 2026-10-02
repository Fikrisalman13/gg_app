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
    die("Koneksi ke database gagal.");
}

date_default_timezone_set('Asia/Jakarta');

$custcodes_input = isset($_POST['custcodes']) ? trim($_POST['custcodes']) : '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($custcodes_input)) {
    // Parse custcodes (split by newline, comma, space)
    $codes = preg_split('/[\s,]+/', $custcodes_input, -1, PREG_SPLIT_NO_EMPTY);

    if (count($codes) > 0) {
        // Prepare IN clause
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        // Query khusus untuk Summary Card (Cepat & Ringan)
        $query = "SELECT 
            c.CusCode,
            c.CusName,
            COUNT(DISTINCT p.ProdCode) AS ProductCount
        FROM SMCustomer c
        INNER JOIN INPriceTempMbr m ON c.cusid = m.cusid
        INNER JOIN INPriceTempHd h ON m.pricetemphdid = h.pricetemphdid
        INNER JOIN INPriceCurrent p ON h.pricetemphdid = p.pricetemphdid
        WHERE c.CusCode IN ($placeholders)
        GROUP BY c.CusCode, c.CusName
        ORDER BY c.CusCode";

        $stmt = $conn3->prepare($query);
        $stmt->execute($codes);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Cek Customer Code & Price</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Cek Custcode Price</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-<?php echo htmlspecialchars($themeColor); ?> card-outline shadow-sm">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title"><i class="fas fa-search mr-1"></i> Filter Pencarian</h3>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label>Customer Code <span class="text-muted font-weight-normal">(Pisahkan
                                                dengan koma, spasi, atau baris baru untuk mencari lebih dari
                                                1)</span></label>
                                        <textarea name="custcodes" class="form-control" rows="4"
                                            placeholder="Contoh:&#10;SC0183&#10;SC0713&#10;SC1138"><?= htmlspecialchars($custcodes_input) ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?>">
                                        <i class="fas fa-search"></i> Cari Data
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt"></i> Reset
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <?php if (!empty($summary)): ?>
                        <div class="row">
                            <?php foreach ($summary as $row): 
                                        $row = array_change_key_case($row, CASE_LOWER);
                                        $cc = $row['cuscode'];
                                        $cn = $row['cusname'];
                                        $count = $row['productcount'];
                                    ?>
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="info-box shadow-sm">
                                        <span class="info-box-icon bg-<?php echo htmlspecialchars($themeColor); ?>"><i
                                                class="fas fa-boxes"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text text-bold"
                                                title="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cc); ?></span>
                                            <span class="info-box-number"><?php echo number_format($count); ?> <small>Products</small></span>
                                            <span class="text-muted text-sm text-truncate" style="max-width: 150px; display: block;"
                                                title="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> Hasil Pencarian</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="resultTable" class="table table-hover table-sm">
                                            <thead class="thead-light">
                                                <tr class="text-center align-middle">
                                                    <th>CusCode</th>
                                                    <th>CusName</th>
                                                    <th>Group Cust</th>
                                                    <th>ProdCode</th>
                                                    <th>ProdName</th>
                                                    <th>Current Price</th>
                                                    <th>Effective Date</th>
                                                    <th>UOMCode</th>
                                                    <th>CurrCode</th>
                                                    <th>StructCode</th>
                                                    <th>TempCode</th>
                                                    <th>TempName</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jszip/jszip.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function () {
        if ($('#resultTable').length > 0) {
            $('#resultTable').DataTable({
                "serverSide": true,
                "processing": true,
                "ajax": {
                    "url": "cek_custcode_price_ajax.php",
                    "type": "POST",
                    "data": function(d) {
                        d.custcodes = $('textarea[name="custcodes"]').val();
                    }
                },
                "columns": [
                    { "data": "cuscode", "className": "text-center align-middle font-weight-bold text-<?php echo htmlspecialchars($themeColor); ?>" },
                    { "data": "cusname", "className": "align-middle" },
                    { "data": "group_cust", "className": "text-center align-middle" },
                    { "data": "prodcode", "className": "text-center align-middle" },
                    { "data": "prodname", "className": "align-middle" },
                    { "data": "current_price", "className": "text-right align-middle font-weight-bold text-success" },
                    { "data": "effective_date", "className": "text-center align-middle" },
                    { "data": "uomcode", "className": "text-center align-middle" },
                    { "data": "currcode", "className": "text-center align-middle" },
                    { "data": "structcode", "className": "text-center align-middle" },
                    { 
                        "data": "tempcode", 
                        "className": "text-center align-middle",
                        "render": function(data, type, row) {
                            return '<span class="badge badge-info">' + data + '</span>';
                        }
                    },
                    { "data": "tempname", "className": "align-middle" }
                ],
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25,
                "order": [[ 6, "desc" ]],
                "dom": "<'row'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4 text-center'B><'col-sm-12 col-md-4'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                "buttons": [
                    {
                        text: '<i class="fas fa-file-excel"></i> Export Semua ke Excel',
                        className: 'btn btn-success btn-sm',
                        action: function ( e, dt, node, config ) {
                            var form = $('<form action="export_excel_custcode_price.php" method="post" target="_blank" style="display:none;"></form>');
                            form.append('<textarea name="custcodes">' + $('textarea[name="custcodes"]').val() + '</textarea>');
                            $('body').append(form);
                            form.submit();
                            form.remove();
                        }
                    }
                ],
                "language": {
                    "processing": "Memproses...",
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Tidak ada data ditemukan",
                    "info": "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    "infoEmpty": "Tidak ada data tersedia",
                    "infoFiltered": "(disaring dari _MAX_ total data)",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        }
    });
</script>