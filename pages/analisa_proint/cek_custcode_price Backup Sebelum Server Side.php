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

        // Optimized query: No temp tables, directly JOINing the required tables based on filtered customers
        $query = "SELECT DISTINCT
            c.CusCode,
            c.CusName,
            cg.CusGrpCode AS Group_Cust,
            p.ProdCode,
            p.ProdName,
            p.Price AS Current_Price,
            p.effdate AS Effective_Date,
            u.UOMCode,
            curr.CurrCode,
            ps.StructCode,
            h.TempCode
        FROM SMCustomer c
        INNER JOIN INPriceTempMbr m ON c.cusid = m.cusid
        INNER JOIN INPriceTempHd h ON m.pricetemphdid = h.pricetemphdid
        INNER JOIN INPriceCurrent p ON h.pricetemphdid = p.pricetemphdid
        LEFT OUTER JOIN SMCustomerGrp cg ON c.cusgrpid = cg.cusgrpid
        LEFT OUTER JOIN SMUOM u ON p.uomid = u.uomid
        LEFT OUTER JOIN SMCurrency curr ON p.currid = curr.currid
        LEFT OUTER JOIN SMProdStruct ps ON p.prodstructid = ps.prodstructid
        WHERE c.CusCode IN ($placeholders)
        ORDER BY c.CusCode, p.ProdCode";

        $stmt = $conn3->prepare($query);
        $stmt->execute($codes);
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($results)): ?>
                                                    <?php foreach ($results as $res):
                                                        $row = array_change_key_case($res, CASE_LOWER);
                                                        ?>
                                                        <tr>
                                                            <td
                                                                class="text-center align-middle font-weight-bold text-<?php echo htmlspecialchars($themeColor); ?>">
                                                                <?= htmlspecialchars($row['cuscode'] ?? '') ?>
                                                            </td>
                                                            <td class="align-middle"><?= htmlspecialchars($row['cusname'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= htmlspecialchars($row['group_cust'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= htmlspecialchars($row['prodcode'] ?? '') ?>
                                                            </td>
                                                            <td class="align-middle"><?= htmlspecialchars($row['prodname'] ?? '') ?>
                                                            </td>
                                                            <td class="text-right align-middle font-weight-bold text-success">
                                                                <?= is_numeric($row['current_price'] ?? null) ? number_format((float) $row['current_price'], 2, ',', '.') : htmlspecialchars($row['current_price'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= !empty($row['effective_date']) ? date('d-m-Y', strtotime($row['effective_date'])) : '' ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= htmlspecialchars($row['uomcode'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= htmlspecialchars($row['currcode'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle">
                                                                <?= htmlspecialchars($row['structcode'] ?? '') ?>
                                                            </td>
                                                            <td class="text-center align-middle"><span
                                                                    class="badge badge-info"><?= htmlspecialchars($row['tempcode'] ?? '') ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="11" class="text-center text-danger font-weight-bold py-4">
                                                            <i
                                                                class="fas fa-exclamation-triangle fa-2x mb-2 d-block text-warning"></i>
                                                            Data tidak ditemukan untuk Customer Code yang dimasukkan.
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
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
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25,
                "dom": "<'row'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4 text-center'B><'col-sm-12 col-md-4'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                "buttons": [
                    {
                        extend: 'excelHtml5',
                        className: 'btn btn-success btn-sm',
                        text: '<i class="fas fa-file-excel"></i> Export Excel',
                        title: 'Data Cust Price ' + new Date().toLocaleDateString('en-GB')
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