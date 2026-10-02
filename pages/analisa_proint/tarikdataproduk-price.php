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

date_default_timezone_set('Asia/Jakarta');

if (!$conn3) {
    die("Koneksi ke database gagal");
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$custcodeInput = isset($_GET['custcodes']) ? trim($_GET['custcodes']) : '';
$results = [];
$errorMessage = '';
$isFiltered = isset($_GET['filter']);

function parseCustCodes(string $input): array
{
    if ($input === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', strtoupper($input), -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];

    foreach ($parts as $part) {
        $code = trim($part);
        if ($code === '') {
            continue;
        }

        if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            continue;
        }

        $codes[$code] = $code;
    }

    return array_values($codes);
}

function fetchProductPriceData(PDO $conn3, array $custCodes): array
{
    if (empty($custCodes)) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($custCodes as $index => $custCode) {
        $key = ':cust' . $index;
        $placeholders[] = $key;
        $params[$key] = $custCode;
    }

    $query = "
        WITH TempPrice AS (
            SELECT DISTINCT
                INPriceTempHd.TempCode,
                INPriceTempHd.TempName,
                INPriceCurrent.ProdCode,
                INPriceCurrent.ProdName,
                INPriceCurrent.Price AS Current_Price,
                INPriceCurrent.effdate AS Effective_Date,
                SMUOM.UOMCode,
                SMCurrency.CurrCode,
                SMProdStruct.StructCode
            FROM INPriceCurrent
            INNER JOIN INPriceTempHd
                ON INPriceTempHd.pricetemphdid = INPriceCurrent.pricetemphdid
            INNER JOIN INPriceTempMbr
                ON INPriceTempMbr.pricetemphdid = INPriceTempHd.pricetemphdid
            LEFT OUTER JOIN SMUOM
                ON SMUOM.uomid = INPriceCurrent.uomid
            LEFT OUTER JOIN SMCurrency
                ON SMCurrency.currid = INPriceCurrent.currid
            LEFT OUTER JOIN SMProdStruct
                ON SMProdStruct.prodstructid = INPriceCurrent.prodstructid
        ),
        TempCustomer AS (
            SELECT
                INPriceTempHd.TempCode,
                SMCustomer.CusCode,
                SMCustomer.CusName,
                SMCustomerGrp.CusGrpCode AS Group_Cust
            FROM INPriceTempHd
            INNER JOIN INPriceTempMbr
                ON INPriceTempMbr.pricetemphdid = INPriceTempHd.pricetemphdid
            LEFT OUTER JOIN SMCustomer
                ON SMCustomer.cusid = INPriceTempMbr.cusid
            LEFT OUTER JOIN SMCustomerGrp
                ON SMCustomerGrp.cusgrpid = SMCustomer.cusgrpid
            WHERE SMCustomer.CusCode IN (" . implode(', ', $placeholders) . ")
        )
        SELECT
            TempCustomer.CusCode,
            TempCustomer.CusName,
            TempCustomer.Group_Cust,
            TempPrice.ProdCode,
            TempPrice.ProdName,
            TempPrice.Current_Price,
            TempPrice.Effective_Date,
            TempPrice.UOMCode,
            TempPrice.CurrCode,
            TempPrice.StructCode,
            TempPrice.TempCode,
            TempPrice.TempName
        FROM TempCustomer
        INNER JOIN TempPrice
            ON TempCustomer.TempCode = TempPrice.TempCode
        ORDER BY TempCustomer.CusCode, TempPrice.ProdCode
    ";

    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isFiltered) {
    $custCodes = parseCustCodes($custcodeInput);

    if (empty($custCodes)) {
        $errorMessage = 'Masukkan minimal 1 Cust Code yang valid, pisahkan dengan koma.';
    } else {
        try {
            $results = fetchProductPriceData($conn3, $custCodes);
            $custcodeInput = implode(',', $custCodes);
        } catch (Throwable $e) {
            $errorMessage = 'Gagal mengambil data: ' . $e->getMessage();
        }
    }
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Tarik Data Produk Price</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Tarik Data Produk Price</li>
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
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">
                                    <i class="fas fa-tags mr-1"></i> Data Produk dan Price per Customer
                                </h3>
                            </div>
                            <div class="card-body">
                                <form method="get">
                                    <input type="hidden" name="filter" value="1">
                                    <div class="form-group">
                                        <label for="custcodes">List Cust Code</label>
                                        <textarea
                                            name="custcodes"
                                            id="custcodes"
                                            rows="3"
                                            class="form-control"
                                            placeholder="Contoh: SC0183,SC0713,SC1138"
                                            required
                                        ><?= htmlspecialchars($custcodeInput) ?></textarea>
                                        <small class="form-text text-muted">
                                            Pisahkan antar kode customer dengan tanda koma.
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?>">
                                        <i class="fas fa-search"></i> Tampilkan Data
                                    </button>
                                    <?php if (!empty($results)) : ?>
                                        <a
                                            href="export_excel_tarikdataproduk_price.php?custcodes=<?= urlencode($custcodeInput) ?>"
                                            class="btn btn-success ml-2"
                                        >
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </a>
                                    <?php endif; ?>
                                </form>

                                <?php if ($errorMessage !== '') : ?>
                                    <div class="alert alert-danger mt-3 mb-0">
                                        <?= htmlspecialchars($errorMessage) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="table-responsive mt-4">
                                    <table id="produkPriceTable" class="table table-bordered table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center">
                                                <th>No</th>
                                                <th>Cust Code</th>
                                                <th>Cust Name</th>
                                                <th>Group Cust</th>
                                                <th>Prod Code</th>
                                                <th>Prod Name</th>
                                                <th>Current Price</th>
                                                <th>Effective Date</th>
                                                <th>UOM</th>
                                                <th>Currency</th>
                                                <th>Struct Code</th>
                                                <th>Temp Code</th>
                                                <th>Temp Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($results)) : ?>
                                                <?php $no = 1; ?>
                                                <?php foreach ($results as $row) : ?>
                                                    <?php
                                                    $effectiveDate = '';
                                                    if (!empty($row['effective_date'])) {
                                                        $effectiveDate = date('d/m/Y', strtotime((string) $row['effective_date']));
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $no++ ?></td>
                                                        <td><?= htmlspecialchars((string) $row['cuscode']) ?></td>
                                                        <td><?= htmlspecialchars((string) $row['cusname']) ?></td>
                                                        <td><?= htmlspecialchars((string) $row['group_cust']) ?></td>
                                                        <td><?= htmlspecialchars((string) $row['prodcode']) ?></td>
                                                        <td><?= htmlspecialchars((string) $row['prodname']) ?></td>
                                                        <td class="text-right"><?= is_numeric($row['current_price']) ? number_format((float) $row['current_price'], 2) : '' ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($effectiveDate) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) $row['uomcode']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) $row['currcode']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) $row['structcode']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) $row['tempcode']) ?></td>
                                                        <td><?= htmlspecialchars((string) $row['tempname']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php elseif ($isFiltered && $errorMessage === '') : ?>
                                                <tr>
                                                    <td colspan="13" class="text-center text-danger py-4">
                                                        Data tidak ditemukan untuk Cust Code yang dimasukkan.
                                                    </td>
                                                </tr>
                                            <?php else : ?>
                                                <tr>
                                                    <td colspan="13" class="text-center py-4">
                                                        Masukkan daftar Cust Code untuk menampilkan data.
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
        $("#produkPriceTable").DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [],
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
            }
        });
    });
</script>
