<?php
require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$masterList = [];
$uomList = [];
$packList = [];
$orderList = [];

$q = sqlsrv_query($conn, 'SELECT id, item, kode_produk FROM master_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $masterList[] = $row;

$q = sqlsrv_query($conn, 'SELECT id, uomid, uomname FROM uom_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $uomList[] = $row;

$q = sqlsrv_query($conn, 'SELECT id, packaging FROM packaged_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $packList[] = $row;

$today = date('Y-m-d');
$from  = trim($_GET['from']  ?? $today);
$to    = trim($_GET['to']    ?? $today);
if ($from === '') $from = $today;
if ($to === '') $to = $today;
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'ASC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$sql = "
SELECT
    no_po,
    MIN(create_date) as create_date,
    COUNT(*) as total_item,
    MAX(created_by) as created_by
FROM orderitem_gistex
WHERE CAST(create_date as date)
      BETWEEN ? AND ?
";

$params = [$from, $to];

if($search != ''){
    $sql .= " AND no_po LIKE ? ";
    $params[] = "%{$search}%";
}

$sql .= "
GROUP BY no_po
ORDER BY no_po ".($sort == 'DESC' ? 'DESC' : 'ASC')."
OFFSET ? ROWS
FETCH NEXT ? ROWS ONLY
";

$params[] = $offset;
$params[] = $limit;

$q = sqlsrv_query($conn,$sql,$params);

$orderList = [];

if($q){
    while($row = sqlsrv_fetch_array($q,SQLSRV_FETCH_ASSOC)){
        $orderList[] = $row;
    }
}
?>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Order Item Gistex</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <!-- Nomor PO Input -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Nomor PO</label>
                            <input type="text" id="nomorPoInput" class="form-control" placeholder="Masukkan Nomor PO">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-primary mr-2" id="btnAddItem">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                            <button type="button" class="btn btn-success" id="btnSavePo">
                                <i class="fas fa-save"></i> Save PO
                            </button>
                        </div>
                    </div>

                    <!-- Pending Items Table -->
                    <div id="pendingSection" style="display:none;">
                        <h5>Items to Save</h5>
                        <table class="table table-sm table-bordered" id="pendingTable">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Qty.Order</th>
                                    <th>Packaging</th>
                                    <th>UOM</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
<?php
$today = date('Y-m-d');
?>

<div class="row mb-3">

    <div class="col-md-2">
        <label>From Date</label>
        <input type="date"
               id="filterFrom"
               class="form-control"
               value="<?= $_GET['from'] ?? $today ?>">
    </div>

    <div class="col-md-2">
        <label>To Date</label>
        <input type="date"
               id="filterTo"
               class="form-control"
               value="<?= $_GET['to'] ?? $today ?>">
    </div>

    <div class="col-md-3">
        <label>Search PO</label>
        <input type="text"
               id="searchPo"
               class="form-control"
               placeholder="Cari No PO..."
               value="<?= $_GET['search'] ?? '' ?>">
    </div>

</div>

    

</div>
                    <!-- Saved Items Table -->
                    <h5 class="mt-4">Saved Orders</h5>
                    <table class="table table-sm table-bordered">
                        <thead>
<tr>
    <th>PO Date</th>

    <th>
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&search=<?= urlencode($search) ?>&sort=<?= $sort=='ASC'?'DESC':'ASC' ?>">PO <?= $sort=='ASC' ? '▲' : '▼' ?></a>
    </th>

    <th>Total Item</th>
    <th>Created By</th>
    <th>Aksi</th>
</tr>
</thead>
                        <tbody>

<?php foreach($orderList as $row): ?>

<tr>

    <td>
        <?= htmlspecialchars(
            gistexDate($row['create_date'])
        ) ?>
    </td>

    <td>
        <?= htmlspecialchars($row['no_po']) ?>
    </td>

    <td>
        <?= htmlspecialchars($row['total_item']) ?>
    </td>

    <td>
        <?= htmlspecialchars($row['created_by']) ?>
    </td>

    <td>
        <a class="btn btn-info btn-sm"
           href="detail_po.php?no_po=<?= urlencode($row['no_po']) ?>">
            <i class="fas fa-eye"></i>
        </a>
        <a class="btn btn-success btn-sm"
           href="export_csv_gistex.php?no_po=<?= urlencode($row['no_po']) ?>">
            <i class="fas fa-download"></i>
        </a>
    </td>

</tr>

<?php endforeach; ?>

</tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal Add Item -->
<div class="modal fade" id="orderItemModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Item</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Description</label>
                    <select id="descriptionSelect" class="form-control">
                        <option value="">-- Pilih Kode Produk --</option>
                        <?php foreach ($masterList as $row): ?>
                            <option value="<?= htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-id="<?= htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-item="<?= htmlspecialchars($row['item'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-kodeproduk="<?= htmlspecialchars($row['kode_produk'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(($row['kode_produk'] ?? '') . ' | Item: ' . ($row['item'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Qty.Order</label>
                    <input type="number" step="0.01" id="qtyOrderInput" class="form-control" placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label>Packaging</label>
                    <select id="packagingSelect" class="form-control">
                        <option value="">-- Pilih Packaging --</option>
                        <?php foreach ($packList as $row): ?>
                            <option value="<?= htmlspecialchars($row['packaging'] ?? '') ?>">
                                <?= htmlspecialchars($row['packaging'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>UOM</label>
                    <select id="uomSelect" class="form-control">
                        <option value="">-- Pilih UOM --</option>
                        <?php foreach ($uomList as $row): ?>
                            <option value="<?= htmlspecialchars($row['uomname'] ?? '') ?>">
                                <?= htmlspecialchars($row['uomname'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnAddRow">
                    <i class="fas fa-plus"></i> Add To List
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="update_orderitem.php" method="post">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Edit Order Item
                    </h5>

                    <button type="button"
                            class="close"
                            data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden"
                           name="uniqueid"
                           id="edit_uniqueid">

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text"
                               class="form-control"
                               name="description"
                               id="edit_description"
                               readonly>
                    </div>

                    <div class="form-group">
                        <label>Qty.Order</label>
                        <input type="number"
                               step="0.01"
                               class="form-control"
                               name="qty_order"
                               id="edit_qty">
                    </div>

                    <div class="form-group">
                        <label>Packaging</label>
                        <select class="form-control"
                                name="packaging"
                                id="edit_packaging">

                            <?php foreach($packList as $p): ?>
                                <option value="<?= htmlspecialchars($p['packaging']) ?>">
                                    <?= htmlspecialchars($p['packaging']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label>UOM</label>
                        <select class="form-control"
                                name="uom"
                                id="edit_uom">

                            <?php foreach($uomList as $u): ?>
                                <option value="<?= htmlspecialchars($u['uomname']) ?>">
                                    <?= htmlspecialchars($u['uomname']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit"
                            class="btn btn-success">
                        Save Changes
                    </button>

                    <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                        Cancel
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
<!-- Hidden Form -->
<form method="post" action="save_orderitem.php" id="orderForm"></form>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {

    let pendingRows = [];

    let masterLookup = <?= json_encode($masterList, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

let masterData = {};

masterLookup.forEach(function(m){

    masterData[m.id] = {
        kode_produk : m.kode_produk || '',
        item        : m.item || ''
    };

});

    // Select2
    $('#descriptionSelect').select2({
        width: '100%',
        placeholder: '-- Pilih Kode Produk --',
        allowClear: true,
        dropdownParent: $('#orderItemModal')
    });

    $('#uomSelect').select2({
        width: '100%',
        placeholder: '-- Pilih UOM --',
        allowClear: true,
        dropdownParent: $('#orderItemModal')
    });

    $('#packagingSelect').select2({
        width: '100%',
        placeholder: '-- Pilih Packaging --',
        allowClear: true,
        dropdownParent: $('#orderItemModal')
    });

    // =========================
    // ADD ITEM
    // =========================
    $('#btnAddItem').on('click', function () {

        let po = $('#nomorPoInput').val().trim();

        if (po === '') {
            alert('Nomor PO wajib diisi!');
            $('#nomorPoInput').focus();
            return;
        }

        $('#orderItemModal').modal('show');
    });

    // =========================
    // ADD ROW
    // =========================
   $('#btnAddRow').on('click', function () {

    let masterId = $('#descriptionSelect').val();

    if (!masterId) {
        alert('Description wajib dipilih!');
        return;
    }

   let master = masterData[masterId];

let kodeProduk = master.kode_produk;
let masterItem = master.item;

    let qty = $('#qtyOrderInput').val();

    let packaging = $('#packagingSelect').val();
    let packagingText = $('#packagingSelect option:selected').text();

    let uom = $('#uomSelect').val();
    let uomText = $('#uomSelect option:selected').text();
if (!qty || parseFloat(qty) <= 0) {
    alert('Qty.Order harus lebih dari 0');
    return;
}
    pendingRows.push({
        description: kodeProduk,
        master_id: masterId,
        master_item: masterItem,
        qty_order: qty,
        packaging: packaging,
        packaging_text: packagingText,
        uom: uom,
        uom_text: uomText
    });

    console.log('ROW BARU', pendingRows);

    renderPending();

    $('#orderItemModal').modal('hide');
});

    // =========================
    // RENDER TABLE
    // =========================
    function renderPending() {

        let tbody = $('#pendingTable tbody');

        tbody.empty();

        $('#pendingSection').toggle(
            pendingRows.length > 0
        );

        pendingRows.forEach(function(row,index){

            tbody.append(`
                <tr>
                    <td>${escapeHtml(row.description)}</td>
                    <td>${escapeHtml(row.qty_order)}</td>
                    <td>${escapeHtml(row.packaging_text)}</td>
                    <td>${escapeHtml(row.uom_text)}</td>
                    <td>
                        <button
                            type="button"
                            class="btn btn-danger btn-sm btn-remove-row"
                            data-index="${index}">
                            <i class="fas fa-trash"></i>
                            Hapus
                        </button>
                    </td>
                </tr>
            `);

        });
    }

    // =========================
    // DELETE ROW
    // =========================
    $(document).on('click','.btn-remove-row',function(){

        let idx = parseInt($(this).data('index'));

        pendingRows.splice(idx,1);

        renderPending();
    });

    // =========================
    // SAVE PO
    // =========================
    $('#btnSavePo').on('click', function () {

    let po = $('#nomorPoInput').val().trim();

    if (po === '') {
        alert('Nomor PO wajib diisi!');
        return;
    }

    if (pendingRows.length === 0) {
        alert('Minimal 1 item harus ditambahkan!');
        return;
    }

    let form = $('#orderForm');

    form.empty();

    form.append(
        $('<input>', {
            type: 'hidden',
            name: 'no_po',
            value: po
        })
    );

    pendingRows.forEach(function(row){

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'description[]',
                value: row.description
            })
        );

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'master_id[]',
                value: row.master_id
            })
        );

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'master_item[]',
                value: row.master_item
            })
        );

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'qty_order[]',
                value: row.qty_order
            })
        );

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'packaging[]',
                value: row.packaging
            })
        );

        form.append(
            $('<input>', {
                type: 'hidden',
                name: 'uom[]',
                value: row.uom
            })
        );

    });

    console.log('DATA DIKIRIM', pendingRows);

    form.submit();
});
$(document).on('click', '.btn-edit', function () {

    $('#edit_uniqueid').val(
        $(this).data('uniqueid')
    );

    $('#edit_description').val(
        $(this).data('description')
    );

    $('#edit_qty').val(
        $(this).data('qty')
    );

    $('#edit_packaging').val(
        $(this).data('packaging')
    );

    $('#edit_uom').val(
        $(this).data('uom')
    );

    $('#editModal').modal('show');
});
    // =========================
    // ESCAPE HTML
    // =========================
    function escapeHtml(text) {

        return $('<div>')
            .text(text)
            .html();
    }

});
$('#filterFrom,#filterTo').on('change', function(){

    let from = $('#filterFrom').val();
    let to = $('#filterTo').val();
    let search = $('#searchPo').val();

    location.href =
        '?from=' + encodeURIComponent(from) +
        '&to=' + encodeURIComponent(to) +
        '&search=' + encodeURIComponent(search);

});
$('#searchPo').on('keypress', function(e){

    if(e.which == 13){

        let from = $('#filterFrom').val();
        let to = $('#filterTo').val();
        let search = $(this).val();

        location.href =
            '?from=' + encodeURIComponent(from) +
            '&to=' + encodeURIComponent(to) +
            '&search=' + encodeURIComponent(search);

    }

});
</script>
<?php include '../../includes/footer.php'; ?>

