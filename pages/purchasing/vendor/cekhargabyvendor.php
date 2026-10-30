<?php
session_start();
ob_start();
include '../../../koneksi.php';
include '../../../koneksi3.php';
require_once __DIR__ . '/cekhargabyvendor_lib.php';

function vendorJson(array $data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

if (isset($_GET['ajax']) && in_array($_GET['ajax'], ['vendors', 'products', 'filtered_products'], true)) {
    if (!isset($_SESSION['UserName'])) {
        vendorJson(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'success' => false, 'error' => 'Silakan login terlebih dahulu!']);
    }
    if (!$conn3) {
        vendorJson(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'success' => false, 'error' => 'Koneksi ke database gagal.']);
    }

    $ajax = $_GET['ajax'];
    if ($ajax === 'vendors') {
        $search = trim((string) ($_GET['search']['value'] ?? ''));
        $where = "WHERE fgstatus = 'A'";
        $params = [];
        if ($search !== '') {
            $where .= ' AND (vendorcode ILIKE :search OR vendorname ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $total = (int) $conn3->query("SELECT COUNT(*) FROM smvendor WHERE fgstatus = 'A'")->fetchColumn();
        $count = $conn3->prepare("SELECT COUNT(*) FROM smvendor $where");
        foreach ($params as $key => $value) $count->bindValue($key, $value);
        $count->execute();
        $filtered = (int) $count->fetchColumn();
        $length = (int) ($_GET['length'] ?? 10);
        $length = $length > 0 ? min($length, 100) : 10;
        $stmt = $conn3->prepare("SELECT vendorcode, vendorname FROM smvendor $where ORDER BY vendorcode LIMIT :length OFFSET :start");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->bindValue(':start', max(0, (int) ($_GET['start'] ?? 0)), PDO::PARAM_INT);
        $stmt->execute();
        vendorJson(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $vendorCode = trim((string) ($_GET['vendorcode'] ?? ''));
    $vendor = fetchActiveVendor($conn3, $vendorCode);
    if ($vendorCode !== '' && !$vendor) {
        vendorJson(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'success' => false, 'error' => 'Vendor tidak ditemukan atau tidak aktif.']);
    }

    $where = 'WHERE 1=1';
    $allWhere = 'WHERE 1=1';
    $params = [];
    $totalParams = [];
    if ($vendor) {
        $where .= ' AND LOWER(BTRIM(h.povendorname)) = LOWER(BTRIM(:vendorname))';
        $allWhere .= ' AND LOWER(BTRIM(h.povendorname)) = LOWER(BTRIM(:all_vendorname))';
        $params[':vendorname'] = $vendor['vendorname'];
        $totalParams[':all_vendorname'] = $vendor['vendorname'];
    }
    $search = trim((string) ($_GET['search']['value'] ?? $_GET['search_value'] ?? ''));
    if ($search !== '') {
        $where .= ' AND (p.prodcode ILIKE :search OR p.prodname ILIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    $productsFrom = "FROM smproduct p JOIN prpodt d ON d.poprodid = p.prodid JOIN prpohd h ON h.pohdid = d.pohdid $where";
    if ($ajax === 'filtered_products') {
        $stmt = $conn3->prepare("SELECT DISTINCT p.prodcode, p.prodname $productsFrom ORDER BY p.prodcode");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        vendorJson(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $countTotal = $conn3->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT p.prodcode, p.prodname $productsFrom) product_list");
    foreach ($params as $key => $value) $countTotal->bindValue($key, $value);
    $countTotal->execute();
    $filtered = (int) $countTotal->fetchColumn();
    $countAll = $conn3->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT p.prodcode, p.prodname FROM smproduct p JOIN prpodt d ON d.poprodid = p.prodid JOIN prpohd h ON h.pohdid = d.pohdid $allWhere) product_list");
    foreach ($totalParams as $key => $value) $countAll->bindValue($key, $value);
    $countAll->execute();
    $total = (int) $countAll->fetchColumn();
    $length = (int) ($_GET['length'] ?? 10);
    $length = $length > 0 ? min($length, 100) : 10;
    $stmt = $conn3->prepare("SELECT DISTINCT p.prodcode, p.prodname $productsFrom ORDER BY p.prodcode LIMIT :length OFFSET :start");
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->bindValue(':start', max(0, (int) ($_GET['start'] ?? 0)), PDO::PARAM_INT);
    $stmt->execute();
    vendorJson(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}
date_default_timezone_set('Asia/Jakarta');
if (!$conn || !$conn3) die('Koneksi ke database gagal.');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$vendorCodeInput = trim((string) ($_POST['vendorcode'] ?? ''));
$vendor = $vendorCodeInput !== '' ? fetchActiveVendor($conn3, $vendorCodeInput) : null;
$productCodesInput = trim((string) ($_POST['prodcodes'] ?? ''));
$productCodes = parseVendorProductCodes($productCodesInput);
$historyMode = (string) ($_POST['history_mode'] ?? '0') === '1';
$showAllHistory = isset($_POST['toggle_history']) ? !$historyMode : $historyMode;
$results = [];
$errorMessage = '';
$isSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isSubmitted) {
    if ($vendorCodeInput !== '' && !$vendor) {
        $errorMessage = 'Vendor tidak ditemukan atau tidak aktif.';
    } elseif (!$productCodes) {
        $errorMessage = 'Masukkan atau pilih minimal 1 kode product.';
    } else {
        try {
            $results = fetchVendorPriceRows($conn3, $vendorCodeInput, $productCodes, $showAllHistory);
            $productCodesInput = implode("\n", $productCodes);
        } catch (Throwable $e) {
            $errorMessage = 'Gagal mengambil data: ' . $e->getMessage();
        }
    }
}
$selectedProductLabels = [];
foreach ($productCodes as $code) $selectedProductLabels[$code] = $code;
foreach ($results as $row) $selectedProductLabels[(string) $row['kode_product']] = (string) $row['kode_product'] . ' - ' . (string) $row['product_name'];
?>
<div class="wrapper"><div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0">Cek Harga by Vendor</h1></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li><li class="breadcrumb-item active">Cek Harga by Vendor</li></ol></div>
    </div></div></div>
    <div class="content"><div class="container-fluid">
        <div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Vendor dan Product</h3></div>
            <div class="card-body"><form method="post" id="vendorPriceForm">
                <div class="form-group"><label for="vendorname">Vendor Aktif (opsional)</label>
                    <div class="input-group input-group-sm"><input type="text" id="vendorname" class="form-control" value="<?= htmlspecialchars((string) ($vendor['vendorname'] ?? '')) ?>" placeholder="Kosongkan untuk semua vendor" readonly>
                        <input type="hidden" id="vendorcode" name="vendorcode" value="<?= htmlspecialchars($vendorCodeInput) ?>">
                        <div class="input-group-append"><button type="button" class="btn btn-info" data-toggle="modal" data-target="#vendorModal"><i class="fas fa-search"></i> Pilih Vendor</button></div>
                    </div>
                    <small id="vendorCodeLabel" class="form-text text-muted"><?= $vendor ? 'Vendor Code: ' . htmlspecialchars((string) $vendor['vendorcode']) : 'Kosongkan vendor untuk mencari semua vendor.' ?></small>
                </div>
                <div class="form-group"><label for="prodcodes">Selected Product</label>
                    <textarea id="prodcodes" name="prodcodes" class="form-control form-control-sm" rows="3" placeholder="Ketik kode product, satu per baris atau pisahkan dengan koma"><?= htmlspecialchars($productCodesInput) ?></textarea>
                    <div id="selectedProductList" class="mt-2"></div>
                </div>
                <input type="hidden" name="history_mode" value="<?= $showAllHistory ? '1' : '0' ?>">
                <button type="button" id="addProductButton" class="btn btn-info btn-sm" data-toggle="modal" data-target="#productModal"><i class="fas fa-plus"></i> Add Product</button>
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Search</button>
                <button type="button" class="btn btn-warning btn-sm" id="clearFilters"><i class="fas fa-trash"></i> Hapus Filter</button>
                <?php if ($isSubmitted && $productCodes && $errorMessage === ''): ?>
                    <button type="submit" name="toggle_history" value="1" class="btn btn-secondary btn-sm"><i class="fas fa-history"></i> <?= $showAllHistory ? 'Show Latest PO' : 'Show All History' ?></button>
                    <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_cekhargabyvendor.php" formtarget="_blank"><i class="fas fa-file-excel"></i> Export Excel</button>
                    <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_cekhargabyvendor.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
                <?php endif; ?>
            </form>
            <?php if ($errorMessage !== ''): ?><div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?></div>
        </div>

        <?php if ($isSubmitted && $results): ?>
        <div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title"><i class="fas fa-list mr-1"></i> Hasil Harga PO dan GRN</h3></div><div class="card-body"><div class="table-responsive">
            <table id="vendorPriceTable" class="table table-hover table-bordered table-sm"><thead class="thead-light"><tr class="text-center"><th>No</th><th>Kode Product</th><th>Product Name</th><th>Struct Name</th><th>No PO</th><th>PO Date</th><th>No GRN</th><th>GRN Date</th><th>Vendor Name</th><th>Price PO (Rupiah)</th><th>Satuan</th><th>Deskripsi</th></tr></thead><tbody>
            <?php foreach ($results as $index => $row): ?><tr>
                <td class="text-center"><?= $index + 1 ?></td><td class="text-center"><?= htmlspecialchars((string) $row['kode_product']) ?></td><td><?= htmlspecialchars((string) $row['product_name']) ?></td><td><?= htmlspecialchars((string) ($row['structname'] ?? '')) ?></td><td class="text-center"><?= htmlspecialchars((string) ($row['no_po'] ?? '')) ?></td><td class="text-center"><?= !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '' ?></td><td class="text-center"><?= htmlspecialchars((string) ($row['no_grn'] ?? '')) ?></td><td class="text-center"><?= !empty($row['grn_date']) ? date('d/m/Y', strtotime((string) $row['grn_date'])) : '' ?></td><td><?= htmlspecialchars((string) ($row['vendor_name'] ?? '')) ?></td><td class="text-right"><?= is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, ',', '.') : '' ?></td><td class="text-center"><?= htmlspecialchars((string) ($row['satuan'] ?? '')) ?></td><td><?= htmlspecialchars((string) ($row['deskripsi'] ?? '')) ?></td>
            </tr><?php endforeach; ?></tbody></table>
        </div></div></div>
        <?php elseif ($isSubmitted && $errorMessage === ''): ?><div class="card"><div class="card-body text-center text-muted">Tidak ada data PO/GRN untuk product dan vendor yang dipilih (atau semua vendor jika vendor dikosongkan).</div></div><?php endif; ?>
    </div></div>
</div></div>

<div class="modal fade" id="vendorModal" tabindex="-1" role="dialog" aria-labelledby="vendorModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title" id="vendorModalLabel">Pilih Vendor (Status A)</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="table-responsive"><table id="vendorLookupTable" class="table table-hover table-sm w-100"><thead class="thead-light"><tr><th>Pilih</th><th>Vendor Code</th><th>Vendor Name</th></tr></thead><tbody></tbody></table></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button></div>
</div></div></div>

<div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title" id="productModalLabel">Pilih Product dari PO<?= $vendor ? ' Vendor Terpilih' : ' Semua Vendor' ?></h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="table-responsive"><table id="productLookupTable" class="table table-hover table-sm w-100"><thead class="thead-light"><tr><th class="text-center"><input type="checkbox" id="checkAllFilteredProducts"> All</th><th>Kode Product</th><th>Product Name</th></tr></thead><tbody></tbody></table></div></div>
    <div class="modal-footer"><span class="mr-auto text-muted" id="selectedProductCount">0 product dipilih</span><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button><button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm" id="saveProducts"><i class="fas fa-save"></i> Save</button></div>
</div></div></div>

<?php include '../../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
$(function () {
    var selectedProducts = <?= json_encode($selectedProductLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var currentVendorCode = $('#vendorcode').val();
    var vendorTable;
    var productTable;
    function renderSelectedProducts() {
        var codes = Object.keys(selectedProducts).sort();
        $('#selectedProductCount').text(codes.length + ' product dipilih');
        if (!codes.length) { $('#selectedProductList').html('<small class="text-muted">Belum ada product dipilih.</small>'); return; }
        var html = '';
        codes.forEach(function (code) {
            var label = $('<div>').text(selectedProducts[code]).html();
            var attr = $('<div>').text(code).html();
            html += '<span class="badge badge-light border mr-1 mb-1 p-2">' + label + ' <button type="button" class="btn btn-xs btn-link text-danger p-0 ml-1 remove-product" data-code="' + attr + '"><i class="fas fa-times"></i></button></span>';
        });
        $('#selectedProductList').html(html);
    }
    function writeSelectedProducts() { $('#prodcodes').val(Object.keys(selectedProducts).sort().join('\n')); renderSelectedProducts(); refreshProductChecks(); }
    $('#prodcodes').on('input', function () {
        var previous = selectedProducts;
        selectedProducts = {};
        String($(this).val()).split(/[\s,]+/).forEach(function (code) {
            code = $.trim(code).toUpperCase();
            if (code) selectedProducts[code] = previous[code] || code;
        });
        renderSelectedProducts();
        refreshProductChecks();
    });
    function refreshProductChecks() { if (productTable && $.fn.DataTable.isDataTable('#productLookupTable')) productTable.rows().invalidate().draw(false); }
    renderSelectedProducts();

    vendorTable = $('#vendorLookupTable').DataTable({ serverSide: true, processing: true, ajax: { url: 'cekhargabyvendor.php', type: 'GET', data: function (d) { d.ajax = 'vendors'; } }, columns: [
        { data: null, orderable: false, searchable: false, render: function (data, type, row) { return '<button type="button" class="btn btn-primary btn-xs choose-vendor" data-code="' + $('<div>').text(row.vendorcode).html() + '" data-name="' + $('<div>').text(row.vendorname).html() + '">Pilih</button>'; } },
        { data: 'vendorcode' }, { data: 'vendorname' }
    ], pageLength: 10, order: [[1, 'asc']], language: { processing: 'Memproses...', lengthMenu: 'Tampilkan _MENU_ vendor', zeroRecords: 'Vendor tidak ditemukan', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ vendor', infoEmpty: 'Tidak ada vendor tersedia', search: 'Cari vendor:', paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' } } });

    productTable = $('#productLookupTable').DataTable({ serverSide: true, processing: true, ajax: { url: 'cekhargabyvendor.php', type: 'GET', data: function (d) { d.ajax = 'products'; d.vendorcode = currentVendorCode; } }, columns: [
        { data: null, orderable: false, searchable: false, className: 'text-center', render: function (data, type, row) { var code = String(row.prodcode); return '<input type="checkbox" class="product-check" value="' + $('<div>').text(code).html() + '" ' + (selectedProducts[code] ? 'checked' : '') + '>'; } },
        { data: 'prodcode', className: 'text-center' }, { data: 'prodname' }
    ], pageLength: 10, order: [[1, 'asc']], language: { processing: 'Memproses...', lengthMenu: 'Tampilkan _MENU_ product', zeroRecords: 'Product tidak ditemukan untuk vendor ini', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ product', infoEmpty: 'Tidak ada product tersedia', search: 'Cari kode/nama product:', paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' } } });

    $('#vendorLookupTable').on('click', '.choose-vendor', function () {
        var code = String($(this).data('code'));
        var name = String($(this).data('name'));
        if (code !== currentVendorCode) { selectedProducts = {}; $('#prodcodes').val(''); }
        currentVendorCode = code;
        $('#vendorcode').val(code);
        $('#vendorname').val(name);
        $('#vendorCodeLabel').text('Vendor Code: ' + code);
        $('#addProductButton').prop('disabled', false);
        renderSelectedProducts();
        $('#vendorModal').modal('hide');
        productTable.ajax.reload();
    });
    $('#productLookupTable').on('change', '.product-check', function () {
        var code = String($(this).val());
        var row = productTable.row($(this).closest('tr')).data();
        if (this.checked) selectedProducts[code] = code + ' - ' + row.prodname; else delete selectedProducts[code];
        renderSelectedProducts();
        $('#checkAllFilteredProducts').prop('checked', false);
    });
    $('#checkAllFilteredProducts').on('change', function () {
        var shouldCheck = this.checked, $box = $(this);
        $box.prop('disabled', true);
        $.getJSON('cekhargabyvendor.php', { ajax: 'filtered_products', vendorcode: currentVendorCode, search_value: productTable.search() }).done(function (response) {
            if (!response || !response.success) { alert(response && response.error ? response.error : 'Gagal mengambil product.'); $box.prop('checked', false); return; }
            response.data.forEach(function (product) { var code = String(product.prodcode); if (shouldCheck) selectedProducts[code] = code + ' - ' + product.prodname; else delete selectedProducts[code]; });
            writeSelectedProducts();
        }).fail(function () { alert('Gagal mengambil product.'); $box.prop('checked', false); }).always(function () { $box.prop('disabled', false); });
    });
    $('#productModal').on('shown.bs.modal', function () { productTable.columns.adjust(); });
    $('#saveProducts').on('click', function () { writeSelectedProducts(); $('#productModal').modal('hide'); });
    $('#selectedProductList').on('click', '.remove-product', function () { delete selectedProducts[String($(this).data('code'))]; writeSelectedProducts(); });
    $('#clearFilters').on('click', function () { currentVendorCode = ''; selectedProducts = {}; $('#vendorcode, #vendorname, #prodcodes').val(''); $('#vendorCodeLabel').text('Pilih vendor sebelum memilih product.'); $('#addProductButton').prop('disabled', true); $('#checkAllFilteredProducts').prop('checked', false); renderSelectedProducts(); productTable.ajax.reload(); });
    $('#vendorModal').on('shown.bs.modal', function () { vendorTable.columns.adjust(); });
    $('#vendorPriceTable').DataTable({ responsive: true, autoWidth: false, pageLength: 25, order: [], language: { lengthMenu: 'Tampilkan _MENU_ data per halaman', zeroRecords: 'Tidak ada data ditemukan', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data tersedia', search: 'Cari:', paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' } } });
});
</script>
