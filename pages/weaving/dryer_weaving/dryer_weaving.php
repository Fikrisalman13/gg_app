<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canAdd = weaving_can($permissions, 'CanAdd');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$petugasOptions = dryer_weaving_get_petugas_options($conn);
$items = dryer_weaving_items();
$hours = dryer_weaving_hours();
$noOptions = dryer_weaving_no_options();
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center dryer-page-head">
                <div class="col-sm-8">
                    <h1 class="dryer-page-title">Log Sheet Pershift Dryer D IN - W dan Cooling Tower</h1>
                </div>
                <div class="col-sm-4 text-right dryer-page-back">
                    <a href="/gg_app/pages/weaving/weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white dryer-list-header">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> List Data Dryer Weaving</h3>
                    <div class="dryer-list-actions">
                        <?php if ($canAdd): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                        <?php endif; ?>
                        <?php if ($canReport): ?>
                            <a href="report_dryer_weaving.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body dryer-list-body">
                    <div class="row mb-3 dryer-filter-row">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filterDryerNo">Dryer No</label>
                            <select id="filterDryerNo" class="form-control">
                                <option value="">Semua Dryer</option>
                                <?php foreach ($noOptions as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filterCtNo">CT No</label>
                            <select id="filterCtNo" class="form-control">
                                <option value="">Semua CT</option>
                                <?php foreach ($noOptions as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
                        </div>
                    </div>
                    <div class="dryer-list-table-wrap">
                        <table id="dryerWeavingTable" class="table table-hover table-bordered table-sm nowrap text-center" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Dryer No</th>
                                    <th>CT No</th>
                                    <th>Tanggal</th>
                                    <th>Petugas</th>
                                    <th>Shift</th>
                                    <th>Created By</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
    .dryer-page-title{font-size:24px;font-weight:600;line-height:1.2;margin:0}
    .dryer-list-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .dryer-list-actions{display:inline-flex;align-items:center;gap:6px;margin-left:auto}
    .dryer-list-actions .btn{white-space:nowrap}
    .dryer-filter-row{row-gap:10px}
    .dryer-filter-row label{margin-bottom:4px;font-size:13px}
    .dryer-filter-row .form-control,.dryer-filter-row .btn{min-height:34px}
    .dryer-list-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    #dryerWeavingTable{min-width:840px}
    .dryer-action-group{display:inline-flex;align-items:center;justify-content:center;gap:4px}
    .dryer-action-group .btn{border-radius:4px!important}
    #modalTambahDryerWeaving .modal-dialog{max-width:98vw}
    #modalTambahDryerWeaving .modal-body{max-height:76vh;overflow:auto}
    .dryer-input-meta{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px;margin-bottom:10px}
    .dryer-input-table-wrap{max-height:58vh;overflow:auto;border:1px solid #111;background:#fff}
    .dryer-input-table{border-collapse:collapse;width:100%;min-width:1320px;margin-bottom:0;background:#fff}
    .dryer-input-table th,.dryer-input-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px}
    .dryer-input-table .meta-row th{border-color:#fff;background:#fff;text-align:left;font-size:13px}
    .dryer-input-table .main-title{font-size:16px;font-weight:800;text-align:center}
    .dryer-input-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;font-size:11px;text-transform:uppercase}
    .dryer-input-table .category-row td{background:#b7b7b7;font-weight:700;text-align:left;color:#000}
    .dryer-input-table .item-cell{text-align:left;min-width:150px}
    .dryer-input-table .standard-cell{min-width:90px;font-weight:600}
    .dryer-input-table input{width:44px;height:30px;border:1px solid #cbd5e1;text-align:center;padding:2px 3px}
    .dryer-input-table tr.is-existing td{background:#e9ecef}
    .dryer-input-table input.is-existing{background:#e9ecef;color:#495057;cursor:not-allowed;pointer-events:none}
    .dryer-mobile-hint{display:none;color:#6c757d;font-size:12px}
    #modalTambahDryerWeaving .select2-container--bootstrap4 .select2-selection--single{height:calc(2.25rem + 2px)}
    #modalTambahDryerWeaving .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered{line-height:2.25rem}
    @media (max-width:767.98px){
        .content-header{padding:10px .5rem 6px}
        .content{padding:0 .25rem}
        .dryer-page-title{font-size:18px}
        .dryer-page-back{text-align:left!important;margin-top:8px}
        .dryer-list-header{align-items:flex-start;padding:10px}
        .dryer-list-header .card-title{flex-basis:100%;font-size:12px;line-height:1.25}
        .dryer-list-actions{width:100%;margin-left:0;justify-content:flex-start}
        .dryer-list-actions .btn{flex:1 1 0;padding:5px 6px;font-size:11px}
        .dryer-list-body{padding:10px}
        .dryer-filter-row label{font-size:11px;font-weight:600}
        .dryer-filter-row .form-control{height:34px;font-size:12px}
        #btnResetFilter{width:100%;height:34px;font-size:12px}
        .dryer-list-table-wrap{border:1px solid #dee2e6;border-radius:4px;background:#fff}
        #dryerWeavingTable{min-width:860px;margin-bottom:0!important;font-size:11px}
        #dryerWeavingTable th,#dryerWeavingTable td{padding:5px 6px;vertical-align:middle}
        #modalTambahDryerWeaving .modal-dialog{max-width:none;width:100%;height:100%;min-height:100%;margin:0}
        #modalTambahDryerWeaving .modal-content{height:100vh;border-radius:0;border:0;display:flex;flex-direction:column}
        #modalTambahDryerWeaving .modal-header{min-height:44px;padding:9px 12px}
        #modalTambahDryerWeaving .modal-title{max-width:calc(100vw - 56px);font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #modalTambahDryerWeaving .modal-body{flex:1 1 auto;max-height:none;overflow-y:auto;padding:10px}
        #modalTambahDryerWeaving .modal-footer{position:sticky;bottom:0;z-index:5;background:#fff;padding:8px 10px;gap:6px;box-shadow:0 -3px 10px rgba(0,0,0,.08)}
        #modalTambahDryerWeaving .modal-footer .btn{min-width:74px;height:34px;font-size:12px}
        .dryer-input-meta{display:block}
        #modalTambahDryerWeaving .form-group{margin-bottom:8px}
        #modalTambahDryerWeaving label{margin-bottom:3px;font-size:11px;font-weight:700}
        #modalTambahDryerWeaving .form-control{height:34px;font-size:12px;padding:4px 8px}
        .dryer-mobile-hint{display:block;margin-bottom:6px!important}
        .dryer-input-table-wrap{max-height:48vh;border-radius:4px;-webkit-overflow-scrolling:touch}
        .dryer-input-table{min-width:1100px;font-size:11px}
        .dryer-input-table .main-title{font-size:12px}
        .dryer-input-table .head-blue{font-size:9px;line-height:1.15}
        .dryer-input-table th,.dryer-input-table td{padding:2px}
        .dryer-input-table input{width:40px;height:28px;font-size:11px}
    }
</style>

<div class="modal fade" id="modalTambahDryerWeaving" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Dryer Weaving</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formTambahDryerWeaving" autocomplete="off">
                <input type="hidden" name="edit_existing" id="dryer_edit_existing" value="0">
                <div class="modal-body">
                    <div class="dryer-input-meta">
                        <div class="form-group">
                            <label for="dryer_tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="dryer_tanggal" name="tanggal" required>
                        </div>
                        <div class="form-group">
                            <label for="dryer_no">Dryer No</label>
                            <select class="form-control" id="dryer_no" name="dryer_no" required>
                                <?php foreach ($noOptions as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ct_no">CT No</label>
                            <select class="form-control" id="ct_no" name="ct_no" required>
                                <?php foreach ($noOptions as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dryer_petugas_default">Petugas</label>
                            <select class="form-control select2bs4" id="dryer_petugas_default" name="petugas_default" data-placeholder="Cari / pilih petugas" required>
                                <option value="">Pilih Petugas</option>
                                <?php foreach ($petugasOptions as $petugasName): ?>
                                    <option value="<?= htmlspecialchars($petugasName) ?>"><?= htmlspecialchars($petugasName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dryer_shift_default">Shift</label>
                            <select class="form-control" id="dryer_shift_default" name="shift_default">
                                <?php foreach (dryer_weaving_shift_options() as $shiftName): ?>
                                    <option value="<?= htmlspecialchars($shiftName) ?>"><?= htmlspecialchars($shiftName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="mb-2 text-right">
                            <button type="button" class="btn btn-warning btn-sm d-none" id="btnEditExistingDryer">
                                <i class="fas fa-edit"></i> Edit Data Existing
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="dryer-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengisi jam pemeriksaan.</div>
                    <div class="dryer-input-table-wrap">
                        <table class="dryer-input-table">
                            <thead>
                                <tr class="meta-row">
                                    <th colspan="<?= 2 + count($hours) ?>" class="main-title">LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND</th>
                                </tr>
                                <tr>
                                    <th class="head-blue" rowspan="2">Item Check</th>
                                    <th class="head-blue" rowspan="2">Standard</th>
                                    <th class="head-blue" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th>
                                </tr>
                                <tr>
                                    <?php foreach ($hours as $hour): ?>
                                        <th class="head-blue"><?= htmlspecialchars(dryer_weaving_display_hour($hour)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="dryerWeavingRows"></tbody>
                        </table>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label for="dryer_keterangan">Keterangan</label>
                        <textarea class="form-control" id="dryer_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    var appPermissions = {
        canEdit: <?= $canEdit ? 'true' : 'false' ?>,
        canDelete: <?= $canDelete ? 'true' : 'false' ?>
    };

    var dryerItems = <?= json_encode($items) ?>;
    var dryerHours = <?= json_encode($hours) ?>;

    function initPetugasSelect2() {
        if (!$.fn.select2) return;
        var $select = $('#dryer_petugas_default');
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Cari / pilih petugas',
            allowClear: true,
            dropdownParent: $('#modalTambahDryerWeaving')
        });
    }

    function todayLocal() {
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 10);
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function resetExistingEditMode() {
        $('#dryer_edit_existing').val('0');
        $('#btnEditExistingDryer').addClass('d-none').prop('disabled', false)
            .html('<i class="fas fa-edit"></i> Edit Data Existing');
    }

    function updateExistingEditButton() {
        if (!appPermissions.canEdit) return;
        $('#btnEditExistingDryer').toggleClass('d-none', $('#dryerWeavingRows input.is-existing').length === 0);
    }

    function enableExistingEditMode() {
        $('#dryer_edit_existing').val('1');
        $('#dryerWeavingRows input.is-existing')
            .prop('readonly', false)
            .removeAttr('tabindex')
            .removeClass('is-existing');
        $('#btnEditExistingDryer').prop('disabled', true)
            .html('<i class="fas fa-check"></i> Mode Edit Aktif');
    }

    function dryerValuesEqual(val1, val2) {
        var s1 = (val1 === null || val1 === undefined ? '' : String(val1)).replace(',', '.').trim();
        var s2 = (val2 === null || val2 === undefined ? '' : String(val2)).replace(',', '.').trim();
        if (s1 === '' && s2 === '') return true;
        if (s1 === '' || s2 === '') return false;
        var n1 = parseFloat(s1);
        var n2 = parseFloat(s2);
        if (!isNaN(n1) && !isNaN(n2)) {
            return Math.abs(n1 - n2) < 0.0001;
        }
        return s1.toLowerCase() === s2.toLowerCase();
    }

    function cellIsModifiedOrNew($input) {
        var val = $input.val().trim();
        var isExisting = $input.attr('data-existing') === '1';
        var orig = ($input.attr('data-original') || '').trim();
        if (!isExisting) {
            return val !== '';
        }
        return !dryerValuesEqual(val, orig);
    }

    function renderInputRows(existing, keterangan) {
        resetExistingEditMode();
        $('#dryer_keterangan').val(keterangan || '').attr('data-original', keterangan || '');
        var currentCategory = '';
        var html = '';
        dryerItems.forEach(function(item) {
            if (item.category !== currentCategory) {
                currentCategory = item.category;
                html += '<tr class="category-row"><td colspan="' + (2 + dryerHours.length) + '">' + escapeHtml(currentCategory) + '</td></tr>';
            }
            html += '<tr><td class="item-cell">' + item.item + '</td><td class="standard-cell">' + escapeHtml(item.standard) + '</td>';
            dryerHours.forEach(function(hour) {
                var key = item.key + '|' + hour;
                var data = existing && existing[key] ? existing[key] : null;
                var value = (data && data.nilai !== null && data.nilai !== undefined) ? data.nilai : '';
                var isExist = (data && data.nilai !== null && data.nilai !== undefined && String(data.nilai).trim() !== '');
                html += '<td><input type="text" class="dryer-cell' + (isExist ? ' is-existing' : '') + '" ' +
                    'data-existing="' + (isExist ? '1' : '0') + '" ' +
                    'data-original="' + escapeHtml(value) + '" ' +
                    'name="rows[' + item.key + '][' + hour + ']" value="' + escapeHtml(value) + '"' +
                    (isExist ? ' readonly tabindex="-1"' : '') + '></td>';
            });
            html += '</tr>';
        });
        $('#dryerWeavingRows').html(html);
        updateExistingEditButton();
    }

    function loadExisting() {
        var tanggal = $('#dryer_tanggal').val();
        var dryerNo = $('#dryer_no').val();
        var ctNo = $('#ct_no').val();
        renderInputRows({}, '');
        if (!tanggal || !dryerNo || !ctNo) return;
        $.getJSON('get_dryer_weaving_existing.php', {tanggal: tanggal, dryer_no: dryerNo, ct_no: ctNo})
            .done(function(res) {
                if (res && res.success) {
                    renderInputRows(res.data || {}, res.keterangan || '');
                } else {
                    renderInputRows({}, '');
                }
            });
    }

    initPetugasSelect2();
    renderInputRows({}, '');

    var table = $('#dryerWeavingTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: 'dryer_weaving_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.dryer_no = $('#filterDryerNo').val();
                d.ct_no = $('#filterCtNo').val();
            }
        },
        columns: [
            {data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }},
            {data: 'dryer_no'},
            {data: 'ct_no'},
            {data: 'tanggal_formatted'},
            {data: 'petugas'},
            {data: 'shift'},
            {data: 'created_by'},
            {data: null, orderable: false, searchable: false, render: function(data, type, row) {
                var html = '<div class="dryer-action-group">';
                html += '<a class="btn btn-info btn-sm" title="View" href="view_dryer_weaving.php?id=' + row.id + '"><i class="fas fa-eye"></i></a>';
                if (appPermissions.canEdit) html += '<a class="btn btn-warning btn-sm" title="Edit" href="edit_dryer_weaving.php?id=' + row.id + '"><i class="fas fa-edit"></i></a>';
                if (appPermissions.canDelete) html += '<button type="button" class="btn btn-danger btn-sm btn-delete" title="Delete" data-id="' + row.id + '"><i class="fas fa-trash"></i></button>';
                html += '</div>';
                return html;
            }}
        ]
    });

    $('#filterStartDate,#filterEndDate,#filterDryerNo,#filterCtNo').on('change', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate,#filterEndDate,#filterDryerNo,#filterCtNo').val('');
        table.ajax.reload();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahDryerWeaving')[0].reset();
        $('#dryer_tanggal').val(todayLocal());
        $('#dryer_no').val('01');
        $('#ct_no').val('01');
        $('#dryer_petugas_default').val('').trigger('change');
        $('#dryer_keterangan').val('').attr('data-original', '');
        renderInputRows({}, '');
        $('#modalTambahDryerWeaving').modal('show');
        loadExisting();
    });

    $('#dryer_tanggal,#dryer_no,#ct_no').on('change', loadExisting);
    $('#btnEditExistingDryer').on('click', enableExistingEditMode);

    $('#formTambahDryerWeaving').on('submit', function(e) {
        e.preventDefault();

        var activeCells = 0;
        var hasNewCells = false;
        $('#dryerWeavingRows input.dryer-cell').each(function() {
            var $input = $(this);
            if (cellIsModifiedOrNew($input)) {
                activeCells++;
                if ($input.attr('data-existing') !== '1') {
                    hasNewCells = true;
                }
            }
        });

        var origKet = ($('#dryer_keterangan').attr('data-original') || '').trim();
        var currKet = $('#dryer_keterangan').val().trim();
        var isKeteranganChanged = (origKet !== currKet);

        if (activeCells === 0 && !isKeteranganChanged) {
            Swal.fire('Validasi', 'Tidak ada data baru atau data yang diedit untuk disimpan.', 'warning');
            return;
        }

        if (hasNewCells && ($('#dryer_petugas_default').val() === '' || $('#dryer_shift_default').val() === '')) {
            Swal.fire('Validasi', 'Petugas dan shift wajib dipilih untuk data baru yang ditambahkan.', 'warning');
            return;
        }

        // Disable input yang tidak diubah agar tidak terkirim saat serialize
        $('#dryerWeavingRows input.dryer-cell').each(function() {
            var $input = $(this);
            if (!cellIsModifiedOrNew($input)) {
                $input.prop('disabled', true);
            }
        });

        $.ajax({
            url: 'save_dryer_weaving.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            complete: function() {
                $('#dryerWeavingRows input.dryer-cell').prop('disabled', false);
            },
            success: function(res) {
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success');
                    $('#modalTambahDryerWeaving').modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Gagal', res.message || 'Data gagal disimpan.', 'error');
                }
            },
            error: function() {
                Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error');
            }
        });
    });

    $('#dryerWeavingTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus data?',
            text: 'Seluruh isi log sheet tanggal ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('delete_dryer_weaving.php', {id: id}, function(res) {
                if (res.success) {
                    Swal.fire('Terhapus', res.message, 'success');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Gagal', res.message || 'Data gagal dihapus.', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error');
            });
        });
    });
});
</script>
