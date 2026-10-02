<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'products') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['UserName'])) {
        echo json_encode([
            'draw' => isset($_GET['draw']) ? (int) $_GET['draw'] : 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Silakan login terlebih dahulu!',
        ]);
        exit;
    }

    if (!$conn3) {
        echo json_encode([
            'draw' => isset($_GET['draw']) ? (int) $_GET['draw'] : 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Koneksi ke database gagal.',
        ]);
        exit;
    }

    $draw = isset($_GET['draw']) ? (int) $_GET['draw'] : 0;
    $start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
    $length = isset($_GET['length']) ? (int) $_GET['length'] : 10;
    $length = $length > 0 ? min($length, 100) : 10;
    $searchValue = isset($_GET['search']['value']) ? trim((string) $_GET['search']['value']) : '';

    $whereSql = "WHERE COALESCE(prodcode, '') <> ''";
    $params = [];

    if ($searchValue !== '') {
        $whereSql .= " AND (prodcode ILIKE :search OR prodname ILIKE :search)";
        $params[':search'] = '%' . $searchValue . '%';
    }

    $stmtTotal = $conn3->query("SELECT COUNT(*) AS total FROM smproduct WHERE COALESCE(prodcode, '') <> ''");
    $recordsTotal = (int) ($stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmtFiltered = $conn3->prepare("SELECT COUNT(*) AS total FROM smproduct $whereSql");
    foreach ($params as $key => $value) {
        $stmtFiltered->bindValue($key, $value);
    }
    $stmtFiltered->execute();
    $recordsFiltered = (int) ($stmtFiltered->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $query = "
        SELECT prodcode, prodname
        FROM smproduct
        $whereSql
        ORDER BY prodcode
        LIMIT :length OFFSET :start
    ";
    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->execute();

    $data = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row = array_change_key_case($row, CASE_LOWER);
        $data[] = [
            'prodcode' => htmlspecialchars((string) ($row['prodcode'] ?? '')),
            'prodname' => htmlspecialchars((string) ($row['prodname'] ?? '')),
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data,
    ]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'filtered_products') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['UserName'])) {
        echo json_encode(['success' => false, 'data' => [], 'error' => 'Silakan login terlebih dahulu!']);
        exit;
    }

    if (!$conn3) {
        echo json_encode(['success' => false, 'data' => [], 'error' => 'Koneksi ke database gagal.']);
        exit;
    }

    $searchValue = isset($_GET['search_value']) ? trim((string) $_GET['search_value']) : '';
    $whereSql = "WHERE COALESCE(prodcode, '') <> ''";
    $params = [];

    if ($searchValue !== '') {
        $whereSql .= " AND (prodcode ILIKE :search OR prodname ILIKE :search)";
        $params[':search'] = '%' . $searchValue . '%';
    }

    $query = "
        SELECT prodcode, prodname
        FROM smproduct
        $whereSql
        ORDER BY prodcode
    ";
    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $data = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row = array_change_key_case($row, CASE_LOWER);
        $data[] = [
            'prodcode' => (string) ($row['prodcode'] ?? ''),
            'prodname' => (string) ($row['prodname'] ?? ''),
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');

if (!$conn || !$conn3) {
    die("Koneksi ke database gagal.");
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$prodcodesInput = isset($_POST['prodcodes']) ? trim((string) $_POST['prodcodes']) : '';
$results = [];
$errorMessage = '';
$isSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST';

function parseProductCodes(string $input): array
{
    if ($input === '') {
        return [];
    }

    $parts = preg_split('/[\s,]+/', strtoupper($input), -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];

    foreach ($parts as $part) {
        $code = trim($part);
        if ($code === '') {
            continue;
        }
        $codes[$code] = $code;
    }

    return array_values($codes);
}

function fetchLastPoPricesByProduct(PDO $conn3, array $prodCodes): array
{
    if (empty($prodCodes)) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($prodCodes as $index => $prodCode) {
        $key = ':prod' . $index;
        $placeholders[] = $key;
        $params[$key] = $prodCode;
    }

    $query = "
        WITH selected_products AS (
            SELECT p.prodid, p.prodcode, p.prodname, p.prodstructid
            FROM smproduct p
            WHERE p.prodcode IN (" . implode(', ', $placeholders) . ")
        ),
        last_po AS (
            SELECT DISTINCT ON (p.prodcode)
                p.prodcode,
                h.pohdid
            FROM prpohd h
            JOIN prpodt d
                ON h.pohdid = d.pohdid
            JOIN selected_products p
                ON d.poprodid = p.prodid
            ORDER BY p.prodcode, h.podate DESC, h.pohdid DESC
        )
        SELECT
            p.prodcode AS kode_product,
            p.prodname AS product_name,
            s.structname,
            h.ponmbr AS no_po,
            h.createdate AS po_date,
            CAST(d.poprice * h.pocurrrate AS numeric(18,4)) AS price_po_rupiah,
            u.uomcode AS satuan
        FROM last_po AS lp
        JOIN prpohd AS h
            ON lp.pohdid = h.pohdid
        JOIN prpodt AS d
            ON h.pohdid = d.pohdid
        JOIN selected_products AS p
            ON d.poprodid = p.prodid
            AND lp.prodcode = p.prodcode
        JOIN smuom AS u
            ON d.pouomid = u.uomid
        LEFT JOIN smprodstruct AS s
            ON p.prodstructid = s.prodstructid
        ORDER BY p.prodcode
    ";

    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isSubmitted) {
    $prodCodes = parseProductCodes($prodcodesInput);

    if (empty($prodCodes)) {
        $errorMessage = 'Pilih minimal 1 product terlebih dahulu.';
    } else {
        try {
            $results = fetchLastPoPricesByProduct($conn3, $prodCodes);
            $prodcodesInput = implode("\n", $prodCodes);
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
                        <h1 class="m-0">Cek Harga by Product</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Cek Harga by Product</li>
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
                                <h3 class="card-title"><i class="fas fa-tags mr-1"></i> Filter Product</h3>
                            </div>
                            <div class="card-body">
                                <form method="post" id="priceByProductForm">
                                    <div class="form-group">
                                        <label for="prodcodes">Selected Product</label>
                                        <textarea
                                            id="prodcodes"
                                            name="prodcodes"
                                            class="form-control form-control-sm"
                                            rows="4"
                                            placeholder="Paste product code di sini, pisahkan dengan enter"
                                        ><?= htmlspecialchars($prodcodesInput) ?></textarea>
                                        <div id="selectedProductList" class="mt-2"></div>
                                    </div>

                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#productModal">
                                        <i class="fas fa-plus"></i> Add Product
                                    </button>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" id="clearSelectedProducts">
                                        <i class="fas fa-trash"></i> Hapus Filter
                                    </button>
                                    <?php if (!empty($results)) : ?>
                                        <button
                                            type="submit"
                                            class="btn btn-success btn-sm"
                                            formaction="export_excel_cekhargabyproduct.php"
                                            formtarget="_blank"
                                        >
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <?php if ($errorMessage !== '') : ?>
                                    <div class="alert alert-danger mt-3 mb-0">
                                        <?= htmlspecialchars($errorMessage) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i> Hasil Pencarian</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="priceTable" class="table table-hover table-bordered table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center">
                                                <th>No</th>
                                                <th>Kode Product</th>
                                                <th>Product Name</th>
                                                <th>Struct Name</th>
                                                <th>No PO</th>
                                                <th>PO Date</th>
                                                <th>Price PO (Rupiah)</th>
                                                <th>Satuan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($results)) : ?>
                                                <?php $no = 1; ?>
                                                <?php foreach ($results as $row) : ?>
                                                    <?php $row = array_change_key_case($row, CASE_LOWER); ?>
                                                    <tr>
                                                        <td class="text-center"><?= $no++ ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) ($row['kode_product'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['product_name'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($row['structname'] ?? '')) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars((string) ($row['no_po'] ?? '')) ?></td>
                                                        <td class="text-center">
                                                            <?= !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '' ?>
                                                        </td>
                                                        <td class="text-right">
                                                            <?= is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, ',', '.') : '' ?>
                                                        </td>
                                                        <td class="text-center"><?= htmlspecialchars((string) ($row['satuan'] ?? '')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php elseif ($isSubmitted && $errorMessage === '') : ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-danger py-4">
                                                        Data PO tidak ditemukan untuk product yang dipilih.
                                                    </td>
                                                </tr>
                                            <?php else : ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        Pilih product lalu klik Search.
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

<div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="productModalLabel">Add Product</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="productLookupTable" class="table table-hover table-sm w-100">
                        <thead class="thead-light">
                            <tr class="text-center">
                                <th style="width: 90px;">
                                    <div class="icheck-primary d-inline">
                                        <input type="checkbox" id="checkAllFilteredProducts">
                                        <label for="checkAllFilteredProducts" class="mb-0">All</label>
                                    </div>
                                </th>
                                <th>Prod Code</th>
                                <th>Prod Name</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span class="mr-auto text-muted" id="selectedCount">0 product dipilih</span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm" id="saveProducts">
                    <i class="fas fa-save"></i> Save
                </button>
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
        var selectedProducts = {};

        function syncSelectedFromTextarea() {
            var value = $('#prodcodes').val();
            selectedProducts = {};
            value.split(/[\s,]+/).forEach(function(code) {
                code = $.trim(code).toUpperCase();
                if (code !== '') {
                    selectedProducts[code] = { prodcode: code, prodname: '' };
                }
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            $('#selectedCount').text(Object.keys(selectedProducts).length + ' product dipilih');
        }

        function renderSelectedProductList() {
            var codes = Object.keys(selectedProducts).sort();
            var html = '';

            if (codes.length === 0) {
                $('#selectedProductList').html('<small class="text-muted">Belum ada product dipilih.</small>');
                return;
            }

            codes.forEach(function(code) {
                html += '<span class="badge badge-light border mr-1 mb-1 p-2">'
                    + $('<div>').text(code).html()
                    + ' <button type="button" class="btn btn-xs btn-link text-danger p-0 ml-1 remove-selected-product" data-prodcode="'
                    + $('<div>').text(code).html()
                    + '"><i class="fas fa-times"></i></button>'
                    + '</span>';
            });

            $('#selectedProductList').html(html);
        }

        function writeSelectedToTextarea() {
            var codes = Object.keys(selectedProducts).sort();
            $('#prodcodes').val(codes.join("\n"));
            updateSelectedCount();
            renderSelectedProductList();
        }

        function refreshProductTableChecks() {
            if ($.fn.DataTable.isDataTable('#productLookupTable')) {
                productTable.rows().invalidate().draw(false);
            }
        }

        $('#prodcodes').on('input', function() {
            syncSelectedFromTextarea();
            renderSelectedProductList();
            refreshProductTableChecks();
        });

        syncSelectedFromTextarea();
        renderSelectedProductList();

        var hasColspanRow = $('#priceTable tbody tr td[colspan]').length > 0;
        if (!hasColspanRow) {
            $('#priceTable').DataTable({
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
        }

        var productTable = $('#productLookupTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: 'cekhargabyproduct.php',
                type: 'GET',
                data: function(d) {
                    d.ajax = 'products';
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center align-middle',
                    render: function(data, type, row) {
                        var checked = selectedProducts[row.prodcode] ? 'checked' : '';
                        return '<input type="checkbox" class="product-check" value="' + row.prodcode + '" data-prodname="' + $('<div>').text(row.prodname).html() + '" ' + checked + '>';
                    }
                },
                { data: 'prodcode', className: 'text-center align-middle' },
                { data: 'prodname', className: 'align-middle' }
            ],
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            order: [[1, 'asc']],
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari Product:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });

        $('#productLookupTable').on('change', '.product-check', function() {
            var prodcode = $(this).val();
            var prodname = $(this).data('prodname') || '';

            if ($(this).is(':checked')) {
                selectedProducts[prodcode] = { prodcode: prodcode, prodname: prodname };
            } else {
                delete selectedProducts[prodcode];
            }

            updateSelectedCount();
            renderSelectedProductList();
            $('#checkAllFilteredProducts').prop('checked', false);
        });

        $('#checkAllFilteredProducts').on('change', function() {
            var shouldCheck = $(this).is(':checked');
            var searchValue = productTable.search();
            var $checkbox = $(this);

            $checkbox.prop('disabled', true);

            $.ajax({
                url: 'cekhargabyproduct.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    ajax: 'filtered_products',
                    search_value: searchValue
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    alert(response && response.error ? response.error : 'Gagal mengambil data product.');
                    $checkbox.prop('checked', false);
                    return;
                }

                response.data.forEach(function(product) {
                    if (shouldCheck) {
                        selectedProducts[product.prodcode] = {
                            prodcode: product.prodcode,
                            prodname: product.prodname || ''
                        };
                    } else {
                        delete selectedProducts[product.prodcode];
                    }
                });

                writeSelectedToTextarea();
                refreshProductTableChecks();
            }).fail(function() {
                alert('Gagal mengambil data product.');
                $checkbox.prop('checked', false);
            }).always(function() {
                $checkbox.prop('disabled', false);
            });
        });

        $('#productModal').on('shown.bs.modal', function() {
            productTable.columns.adjust();
        });

        $('#saveProducts').on('click', function() {
            writeSelectedToTextarea();
            $('#productModal').modal('hide');
        });

        $('#selectedProductList').on('click', '.remove-selected-product', function() {
            var prodcode = $(this).data('prodcode');
            delete selectedProducts[prodcode];
            writeSelectedToTextarea();
            $('#checkAllFilteredProducts').prop('checked', false);
            refreshProductTableChecks();
        });

        $('#clearSelectedProducts').on('click', function() {
            selectedProducts = {};
            writeSelectedToTextarea();
            $('#checkAllFilteredProducts').prop('checked', false);
            refreshProductTableChecks();
        });
    });
</script>
