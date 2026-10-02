<?php
require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$no_po = $_GET['no_po'] ?? '';

// Get uom & packaging lists for select2
$uomList = [];
$q = sqlsrv_query($conn, 'SELECT uomid, uomname FROM uom_gistex ORDER BY uomname ASC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $uomList[] = $row;

$packList = [];
$q = sqlsrv_query($conn, 'SELECT packaging FROM packaged_gistex ORDER BY packaging ASC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $packList[] = $row;

$sql = "SELECT * FROM orderitem_gistex WHERE no_po = ? ORDER BY uniqueid DESC";
$stmt = sqlsrv_query($conn, $sql, [$no_po]);
$rows = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['dt_grouped'] = [];
        $row['has_dt'] = false;
        $row['gt_qtym'] = 0;
        $row['gt_qtyyard'] = 0;
        $row['gt_stdqty'] = 0;
        $qDt = sqlsrv_query($conn, "SELECT * FROM orderitem_gistex_dt WHERE uniqueid_parent = ? ORDER BY balenmbr ASC, id ASC", [$row['uniqueid']]);
        if ($qDt) {
            while ($dt = sqlsrv_fetch_array($qDt, SQLSRV_FETCH_ASSOC)) {
                $bm = $dt['balenmbr'] ?? '';
                if (!isset($row['dt_grouped'][$bm])) $row['dt_grouped'][$bm] = [];
                $row['dt_grouped'][$bm][] = $dt;
                $row['has_dt'] = true;
                $row['gt_qtym'] += (float)($dt['qtym'] ?? 0);
                $row['gt_qtyyard'] += (float)($dt['qtyyard'] ?? 0);
                $row['gt_stdqty'] += (float)($dt['stdqty'] ?? 0);
            }
        }
        $rows[] = $row;
    }
}
?>
<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Detail PO : <?= htmlspecialchars($no_po) ?></h1>
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
<a href="orderitem_gistex.php" class="btn btn-secondary mb-3">Back</a>

<table class="table table-bordered table-sm" id="mainTable">
<thead>
<tr>
    <th>PO Date</th>
    <th>Description</th>
    <th>Qty.Order <br><small class="text-muted font-weight-normal">/ Sisa</small></th>
    <th>UOM</th>
    <th>Packaging</th>
    <th>Created By</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<?php $uniqueid = htmlspecialchars($row['uniqueid']); ?>
<tr data-uniqueid="<?= $uniqueid ?>">
    <td><?= htmlspecialchars(gistexDate($row['create_date'])) ?></td>
    <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
    <td><?php
    $qtyOrder = (float)($row['Qty.Order'] ?? 0);
    $uom = strtoupper(trim($row['uom'] ?? ''));
    $grandTotal = ($uom === 'METER') ? $row['gt_qtym'] : $row['gt_qtyyard'];
    $sisa = $qtyOrder - $grandTotal;
    $excess = $sisa < 0 ? abs($sisa) : 0;
    if ($sisa < 0) $sisa = 0;
    echo number_format($qtyOrder, 2) . ' / ' . number_format($sisa, 2);
    if ($excess > 0): ?>
        <br><span class="badge badge-danger mt-1"><i class="fas fa-exclamation-triangle"></i> Melebihi Qty.Order: <?= number_format($excess, 2) ?></span>
    <?php endif; ?>
</td>
    <td><?= htmlspecialchars($row['uom'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['packaging'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['created_by'] ?? '') ?></td>
    <td>
        <button type="button" class="btn btn-primary btn-sm btn-add-packingno"
                data-uniqueid="<?= $uniqueid ?>">
            <i class="fas fa-box"></i> Add Packingno
        </button>
        <button type="button" class="btn btn-info btn-sm btn-view-packingno"
                data-uniqueid="<?= $uniqueid ?>">
            <i class="fas fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-success btn-sm btn-edit-row"
                data-uniqueid="<?= $uniqueid ?>">
            <i class="fas fa-edit"></i> Edit
        </button>
    </td>
</tr>
<tr class="packingno-detail-row" data-parent="<?= $uniqueid ?>" style="display:none;">
    <td colspan="7" style="padding:0;background:#f9f9f9;">
        <div class="p-3">
            <h6 class="font-weight-bold">Packingno Details</h6>
            <div class="dt-content" data-uniqueid="<?= $uniqueid ?>">
                <?php if (empty($row['dt_grouped'])): ?>
                    <p class="text-muted small mb-0">Belum ada data packingno.</p>
                <?php else: ?>
                    <?php
                    $grandTotalQtym = 0;
                    $grandTotalQtyyard = 0;
                    $grandTotalStdqty = 0;
                    foreach ($row['dt_grouped'] as $balenmbr => $dtRows) {
                        foreach ($dtRows as $dt) {
                            $grandTotalQtym += (float)($dt['qtym'] ?? 0);
                            $grandTotalQtyyard += (float)($dt['qtyyard'] ?? 0);
                            $grandTotalStdqty += (float)($dt['stdqty'] ?? 0);
                        }
                    }
                    ?>
                    <div class="font-weight-bold bg-primary text-white p-2 mb-3 rounded">
                        Grand Total — Qtym: <?= number_format($grandTotalQtym, 2) ?> | Qtyyard: <?= number_format($grandTotalQtyyard, 2) ?> | Stdqty: <?= number_format($grandTotalStdqty, 2) ?>
                    </div>

                    <?php foreach ($row['dt_grouped'] as $balenmbr => $dtRows): ?>
                        <?php
                        $bmTotalQtym = 0;
                        $bmTotalQtyyard = 0;
                        $bmTotalStdqty = 0;
                        $hasLot = false;
                        foreach ($dtRows as $dt) {
                            $bmTotalQtym += (float)($dt['qtym'] ?? 0);
                            $bmTotalQtyyard += (float)($dt['qtyyard'] ?? 0);
                            $bmTotalStdqty += (float)($dt['stdqty'] ?? 0);
                            if (!empty($dt['lot'])) $hasLot = true;
                        }
                        ?>
                        <div class="mb-3 border rounded p-2 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary">Balenmbr: <?= htmlspecialchars($balenmbr) ?></strong>
                                <a class="btn btn-danger btn-sm btn-delete-balenmbr"
                                   href="#"
                                   data-balenmbr="<?= htmlspecialchars($balenmbr) ?>"
                                   data-uniqueid="<?= $uniqueid ?>"
                                   data-no_po="<?= htmlspecialchars($no_po) ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                            <table class="table table-sm table-bordered mb-1">
                                <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Prodname</th>
                                    <th>Prodcode</th>
                                    <th>Batchno</th>
                                    <th>Qtym</th>
                                    <th>Qtyyard</th>
                                    <th>Stdqty</th>
                                    <?php if ($hasLot): ?><th>Lot</th><?php endif; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($dtRows as $dt): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($dt['prodname'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($dt['prodcode'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($dt['batchno'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($dt['qtym'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($dt['qtyyard'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($dt['stdqty'] ?? '') ?></td>
                                    <?php if ($hasLot): ?><td><?= htmlspecialchars($dt['lot'] ?? '') ?></td><?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                <tr class="font-weight-bold">
                                    <td colspan="4" class="text-right">Subtotal <?= htmlspecialchars($balenmbr) ?></td>
                                    <td><?= number_format($bmTotalQtym, 2) ?></td>
                                    <td><?= number_format($bmTotalQtyyard, 2) ?></td>
                                    <td><?= number_format($bmTotalStdqty, 2) ?></td>
                                    <?php if ($hasLot): ?><td></td><?php endif; ?>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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

<!-- Modal Add Packingno -->
<div class="modal fade" id="packingnoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="packingnoForm" method="post" action="add_packingno_save.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add Packingno</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="uniqueid" id="pUniqueid">
                    <input type="hidden" name="no_po" value="<?= htmlspecialchars($no_po) ?>">
                    <div class="mb-3">
                        <label>Cari Balenmbr</label>
                        <input type="text" id="searchBalenmbr" class="form-control" placeholder="Ketik balenmbr..." autocomplete="off">
                    </div>
                    <div id="balenmbrResults" style="max-height:350px;overflow-y:auto;">
                        <table class="table table-sm table-bordered">
                            <thead><tr>
                                <th style="width:40px"><input type="checkbox" id="checkAll"></th>
                                <th>Balenmbr</th>
                                <th>Prodcode</th>
                                <th>Prodname</th>
                                <th>FGStatus</th>
                                <th>Wrhsid</th>
                                <th>TotQtym</th>
                                <th>TotQtyyard</th>
                                <th>TotQtykg</th>
                            </tr></thead>
                            <tbody id="balenmbrBody"></tbody>
                        </table>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span id="pageInfo" class="text-muted small"></span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPage" style="display:none"><i class="fas fa-chevron-left"></i> Prev</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPage" style="display:none">Next <i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <span id="checkedCount" class="text-primary font-weight-bold small"></span>
                    <div>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Packingno</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="post" action="update_orderitem.php">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Order Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="uniqueid" id="edit_uniqueid">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" class="form-control" name="description" id="edit_description" readonly>
                    </div>
                    <div class="form-group">
                        <label>Qty.Order</label>
                        <input type="number" step="0.01" class="form-control" name="qty_order" id="edit_qty">
                    </div>
                    <div class="form-group">
                        <label>UOM</label>
                        <select class="form-control select2-uom" name="uom" id="edit_uom" style="width:100%">
                            <option value="">-- Pilih UOM --</option>
                            <?php foreach ($uomList as $u): ?>
                                <option value="<?= htmlspecialchars($u['uomid']) ?>"><?= htmlspecialchars($u['uomname'] . ' | ' . $u['uomid']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Packaging</label>
                        <select class="form-control select2-pack" name="packaging" id="edit_packaging" style="width:100%">
                            <option value="">-- Pilih Packaging --</option>
                            <?php foreach ($packList as $p): ?>
                                <option value="<?= htmlspecialchars($p['packaging']) ?>"><?= htmlspecialchars($p['packaging']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    var currentPage = 1;
    var currentSearch = '';
    var currentUniqueid = '';
    var checkedSet = {};

    $('.select2-uom').select2({ width: '100%', placeholder: '-- Pilih UOM --', allowClear: true, dropdownParent: $('#editModal') });
    $('.select2-pack').select2({ width: '100%', placeholder: '-- Pilih Packaging --', allowClear: true, dropdownParent: $('#editModal') });

    // Add Packingno
    $(document).on('click', '.btn-add-packingno', function(){
        currentUniqueid = $(this).data('uniqueid');
        $('#pUniqueid').val(currentUniqueid);
        currentPage = 1;
        currentSearch = '';
        checkedSet = {};
        $('#searchBalenmbr').val('');
        loadBalenmbr();
        $('#packingnoModal').modal('show');
    });

    // View toggle
    $(document).on('click', '.btn-view-packingno', function(){
        var uniqueid = $(this).data('uniqueid');
        $('.packingno-detail-row[data-parent="' + uniqueid + '"]').toggle();
    });

    // Edit row
    $(document).on('click', '.btn-edit-row', function(){
        var tr = $(this).closest('tr');
        var uniqueid = $(this).data('uniqueid');
        var cells = tr.find('td');
        $('#edit_uniqueid').val(uniqueid);
        $('#edit_description').val($.trim(cells.eq(1).text()));
        $('#edit_qty').val($.trim(cells.eq(2).text()));
        $('#edit_uom').val($.trim(cells.eq(3).text())).trigger('change');
        $('#edit_packaging').val($.trim(cells.eq(4).text())).trigger('change');
        $('#editModal').modal('show');
    });

    // Delete balenmbr - AJAX, then reload page to refresh view
    $(document).on('click', '.btn-delete-balenmbr', function(e){
        e.preventDefault();
        var btn = $(this);
        var balenmbr = btn.data('balenmbr');
        var uniqueid = btn.data('uniqueid');
        var no_po = btn.data('no_po');

        if (!confirm('Hapus semua data balenmbr ' + balenmbr + '?')) return;

        $.get('delete_packingno_dt.php', {
            balenmbr: balenmbr,
            uniqueid: uniqueid,
            no_po: no_po,
            ajax: 1
        }, function(resp){
            if (resp.success) {
                // Reload page to reflect changes, preserving the view toggle state
                location.reload();
            } else {
                alert('Gagal hapus: ' + (resp.error || 'unknown'));
            }
        });
    });

    // Search
    $('#searchBalenmbr').on('keyup', function(){
        currentSearch = $(this).val().trim();
        currentPage = 1;
        loadBalenmbr();
    });

    function loadBalenmbr(){
        $.getJSON('add_packingno_data.php', {
            search: currentSearch,
            page: currentPage,
            limit: 5,
            uniqueid: currentUniqueid
        }, function(resp){
            var tbody = $('#balenmbrBody');
            tbody.empty();
            if (resp.total === 0) {
                tbody.append('<tr><td colspan="9" class="text-muted">Tidak ada data</td></tr>');
                $('#pageInfo').text('0 records');
                $('#prevPage, #nextPage').hide();
                return;
            }
            resp.data.forEach(function(row){
                var checked = checkedSet[row.balenmbr] ? 'checked' : '';
                tbody.append(
                    '<tr>' +
                    '<td><input type="checkbox" name="balenmbr[]" value="' + escapeHtml(row.balenmbr) + '" ' + checked + '></td>' +
                    '<td>' + escapeHtml(row.balenmbr) + '</td>' +
                    '<td>' + escapeHtml(row.prodcode || '') + '</td>' +
                    '<td>' + escapeHtml(row.prodname || '') + '</td>' +
                    '<td>' + escapeHtml(row.fgstatus || '') + '</td>' +
                    '<td>' + escapeHtml(row.wrhsid || '') + '</td>' +
                    '<td>' + escapeHtml(row.totqtym || '') + '</td>' +
                    '<td>' + escapeHtml(row.totqtyyard || '') + '</td>' +
                    '<td>' + escapeHtml(row.totqtykg || '') + '</td>' +
                    '</tr>'
                );
            });
            var totalPages = Math.ceil(resp.total / resp.limit);
            $('#pageInfo').text('Page ' + resp.page + ' of ' + totalPages + ' (' + resp.total + ' records)');
            $('#prevPage').toggle(resp.page > 1).data('page', resp.page - 1);
            $('#nextPage').toggle(resp.page < totalPages).data('page', resp.page + 1);
            updateCheckAll();
        });
    }

    $(document).on('click', '#prevPage, #nextPage', function(){
        currentPage = $(this).data('page');
        loadBalenmbr();
    });

    $(document).on('change', '#balenmbrBody input[type="checkbox"]', function(){
        var val = $(this).val();
        if ($(this).prop('checked')) checkedSet[val] = true;
        else delete checkedSet[val];
        updateCheckAll();
    });

    $('#checkAll').on('change', function(){
        var checked = $(this).prop('checked');
        $('#balenmbrBody input[type="checkbox"]').each(function(){
            $(this).prop('checked', checked);
            var val = $(this).val();
            if (checked) checkedSet[val] = true;
            else delete checkedSet[val];
        });
    });

    function updateCheckAll(){
        var all = $('#balenmbrBody input[type="checkbox"]').length;
        var checked = $('#balenmbrBody input[type="checkbox"]:checked').length;
        $('#checkAll').prop('checked', all > 0 && all === checked);
        var totalChecked = Object.keys(checkedSet).length;
        $('#checkedCount').text(totalChecked > 0 ? totalChecked + ' packingno selected' : '');
    }

    $('#packingnoForm').on('submit', function(){
        var form = $(this);
        form.find('input[name="balenmbr[]"]').remove();
        $.each(checkedSet, function(key){
            form.append($('<input>', { type: 'hidden', name: 'balenmbr[]', value: key }));
        });
    });

    function escapeHtml(text){
        return $('<div>').text(text).html();
    }

    function numberFormat(n){
        return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
});
</script>
<?php include '../../includes/footer.php'; ?>