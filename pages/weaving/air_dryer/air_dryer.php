<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canAdd = weaving_can($permissions, 'CanAdd');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$hours = air_dryer_hours();
$petugasOptions = air_dryer_get_petugas_options($conn);
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center air-page-head">
                <div class="col-sm-8">
                    <h1 class="air-page-title">Pencatatan Air Dryer Weaving</h1>
                </div>
                <div class="col-sm-4 text-right air-page-back">
                    <a href="/gg_app/pages/weaving/weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white air-list-header">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> List Data Air Dryer Weaving</h3>
                    <div class="air-list-actions">
                        <?php if ($canAdd): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                        <?php endif; ?>
                        <?php if ($canReport): ?>
                            <a href="report_air_dryer.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body air-list-body">
                    <div class="row mb-3 air-filter-row">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
                        </div>
                    </div>
                    <div class="air-list-table-wrap">
                        <table id="airDryerTable" class="table table-hover table-bordered table-sm nowrap text-center" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Petugas</th>
                                    <th>Created By</th>
                                    <th>Updated By</th>
                                    <th style="width: 140px;">Aksi</th>
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
    .air-page-title{font-size:24px;font-weight:600;line-height:1.2;margin:0}
    .air-list-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .air-list-actions{display:inline-flex;align-items:center;gap:6px;margin-left:auto}
    .air-filter-row{row-gap:10px}
    .air-filter-row label{margin-bottom:4px;font-size:13px}
    .air-list-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    #airDryerTable{width:100%!important}
    .air-action-group{display:inline-flex;align-items:center;justify-content:center;gap:4px}
    .air-action-group .btn{border-radius:4px!important}
    #modalTambahAirDryer .modal-dialog{max-width:98vw}
    #modalTambahAirDryer .modal-body{max-height:76vh;overflow:auto}
    .air-input-wrap{max-height:58vh;overflow:auto;border:1px solid #111;background:#fff}
    .air-input-table{border-collapse:collapse;width:100%;min-width:1080px;margin-bottom:0;background:#fff}
    .air-input-table th,.air-input-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:4px}
    .air-input-table .sheet-title{font-size:16px;font-weight:800;background:#fff}
    .air-input-table .date-title{text-align:left;background:#fff;font-weight:700}
    .air-input-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase;font-size:11px;line-height:1.15}
    .air-input-table input{width:64px;height:30px;border:1px solid #cbd5e1;text-align:center;padding:2px 4px}
    .air-input-table .time-cell{background:#f8f9fa;font-weight:600;white-space:nowrap}
    .air-input-table td.is-existing-cell,
    .air-input-table .petugas-cell.has-existing{background:#e9ecef}
    .air-input-table input.is-existing-cell{background:#e9ecef;color:#495057;cursor:not-allowed;pointer-events:none}
    .air-mobile-hint{display:none;color:#6c757d;font-size:12px}
    @media (max-width:767.98px){
        .content-header{padding:10px .5rem 6px}
        .content{padding:0 .25rem}
        .air-page-title{font-size:18px}
        .air-page-back{text-align:left!important;margin-top:8px}
        .air-list-header{align-items:flex-start;padding:10px}
        .air-list-header .card-title{flex-basis:100%;font-size:12px;line-height:1.25}
        .air-list-actions{width:100%;margin-left:0;justify-content:flex-start}
        .air-list-actions .btn{flex:1 1 0;padding:5px 6px;font-size:11px}
        .air-list-body{padding:10px}
        #btnResetFilter{width:100%;height:34px;font-size:12px}
        .air-list-table-wrap{border:1px solid #dee2e6;border-radius:4px;background:#fff}
        #airDryerTable{min-width:1280px;margin-bottom:0!important;font-size:11px}
        #modalTambahAirDryer .modal-dialog{max-width:none;width:100%;height:100%;min-height:100%;margin:0}
        #modalTambahAirDryer .modal-content{height:100vh;border-radius:0;border:0;display:flex;flex-direction:column}
        #modalTambahAirDryer .modal-header{min-height:44px;padding:9px 12px}
        #modalTambahAirDryer .modal-title{max-width:calc(100vw - 56px);font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #modalTambahAirDryer .modal-body{flex:1 1 auto;max-height:none;overflow-y:auto;padding:10px}
        #modalTambahAirDryer .modal-footer{position:sticky;bottom:0;z-index:5;background:#fff;padding:8px 10px;gap:6px;box-shadow:0 -3px 10px rgba(0,0,0,.08)}
        #modalTambahAirDryer label{margin-bottom:3px;font-size:11px;font-weight:700}
        #modalTambahAirDryer .form-control{height:34px;font-size:12px}
        .air-mobile-hint{display:block;margin-bottom:6px!important}
        .air-input-wrap{max-height:52vh;border-radius:4px}
        .air-input-table{min-width:980px;font-size:11px}
        .air-input-table .sheet-title{font-size:12px}
        .air-input-table .head-blue{font-size:9px}
        .air-input-table th,.air-input-table td{padding:2px}
        .air-input-table input{width:54px;height:28px;font-size:11px}
    }
</style>

<div class="modal fade" id="modalTambahAirDryer" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Air Dryer Weaving</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formTambahAirDryer" autocomplete="off">
                <input type="hidden" name="edit_existing" id="air_edit_existing" value="0">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="air_tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="air_tanggal" name="tanggal" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="air_petugas_default">Petugas</label>
                            <select class="form-control select2bs4" id="air_petugas_default" name="petugas_default" data-placeholder="Cari / pilih petugas" required>
                                <option value="">Pilih Petugas</option>
                                <?php foreach ($petugasOptions as $petugasName): ?>
                                    <option value="<?= htmlspecialchars($petugasName) ?>"><?= htmlspecialchars($petugasName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($canEdit): ?>
                        <div class="mb-2 text-right">
                            <button type="button" class="btn btn-warning btn-sm d-none" id="btnEditExistingAir">
                                <i class="fas fa-edit"></i> Edit Data Existing
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="air-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengisi semua kolom.</div>
                    <div class="air-input-wrap">
                        <table class="air-input-table">
                            <thead>
                                <tr><th colspan="10" class="sheet-title">PENCATATAN AIR DRYER WEAVING</th></tr>
                                <tr>
                                    <th colspan="9" class="date-title">TANGGAL : <span id="airTanggalLabel"></span></th>
                                    <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-WV-017</th>
                                </tr>
                                <tr>
                                    <th class="head-blue" rowspan="3">Jam<br>Pengecekan</th>
                                    <th class="head-blue" colspan="4">Air Dryer 1</th>
                                    <th class="head-blue" colspan="4">Air Dryer 2</th>
                                    <th class="head-blue" rowspan="3">Petugas</th>
                                </tr>
                                <tr>
                                    <th class="head-blue" colspan="2">Temperatur Air</th>
                                    <th class="head-blue" colspan="2">Tekanan Air</th>
                                    <th class="head-blue" colspan="2">Temperatur Air</th>
                                    <th class="head-blue" colspan="2">Tekanan Air</th>
                                </tr>
                                <tr>
                                    <th class="head-blue">IN (&deg;C)</th>
                                    <th class="head-blue">OUT (&deg;C)</th>
                                    <th class="head-blue">IN (BAR)</th>
                                    <th class="head-blue">OUT (BAR)</th>
                                    <th class="head-blue">IN (&deg;C)</th>
                                    <th class="head-blue">OUT (&deg;C)</th>
                                    <th class="head-blue">IN (BAR)</th>
                                    <th class="head-blue">OUT (BAR)</th>
                                </tr>
                            </thead>
                            <tbody id="airDryerRows"></tbody>
                        </table>
                    </div>
                    <div class="form-group mt-3 mb-0">
                        <label for="air_keterangan">Keterangan</label>
                        <textarea class="form-control" id="air_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
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

    var airHours = <?= json_encode($hours) ?>;
    var airFields = ['ad1_temp_in', 'ad1_temp_out', 'ad1_press_in', 'ad1_press_out', 'ad2_temp_in', 'ad2_temp_out', 'ad2_press_in', 'ad2_press_out'];

    function initPetugasSelect2() {
        if (!$.fn.select2) return;
        var $select = $('#air_petugas_default');
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({theme: 'bootstrap4', width: '100%', placeholder: 'Cari / pilih petugas', allowClear: true, dropdownParent: $('#modalTambahAirDryer')});
    }

    function todayLocal() {
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 10);
    }

    function formatDateID(value) {
        if (!value) return '';
        var p = value.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : value;
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function sanitizeDecimalInput(value) {
        value = String(value || '').replace(/,/g, '.').replace(/[^0-9.-]/g, '');
        var negative = value.charAt(0) === '-';
        value = value.replace(/-/g, '');
        var hasDecimal = value.indexOf('.') !== -1;
        var parts = value.split('.');
        var integerPart = parts.shift().replace(/\D/g, '').slice(0, 8);
        var decimalPart = parts.join('').replace(/\D/g, '').slice(0, 2);
        return (negative ? '-' : '') + integerPart + (hasDecimal ? '.' + decimalPart : '');
    }

    function resetExistingEditMode() {
        $('#air_edit_existing').val('0');
        $('#btnEditExistingAir').addClass('d-none').prop('disabled', false)
            .html('<i class="fas fa-edit"></i> Edit Data Existing');
    }

    function updateExistingEditButton() {
        if (!appPermissions.canEdit) return;
        $('#btnEditExistingAir').toggleClass('d-none', $('#airDryerRows input.is-existing-cell').length === 0);
    }

    function enableExistingEditMode() {
        $('#air_edit_existing').val('1');
        $('#airDryerRows input.is-existing-cell')
            .prop('readonly', false)
            .removeAttr('tabindex')
            .removeClass('is-existing-cell');
        $('#airDryerRows td.is-existing-cell').removeClass('is-existing-cell');
        $('#airDryerRows tr.is-existing').removeClass('is-existing');
        $('#btnEditExistingAir').prop('disabled', true)
            .html('<i class="fas fa-check"></i> Mode Edit Aktif');
        updatePetugasCells();
    }

    function renderInputRows(existing, keterangan) {
        resetExistingEditMode();
        var html = '';
        airHours.forEach(function(hour, idx) {
            var data = existing && existing[hour] ? existing[hour] : null;
            var rowPetugas = data && data.petugas ? data.petugas : '';
            html += '<tr data-existing="' + (data ? '1' : '0') + '">';
            html += '<td class="time-cell">' + hour + '<input type="hidden" name="rows[' + idx + '][jam]" value="' + hour + '"></td>';
            airFields.forEach(function(field) {
                var value = data ? data[field] : '';
                var locked = data && value !== '';
                html += '<td' + (locked ? ' class="is-existing-cell"' : '') + '><input type="text" inputmode="decimal" maxlength="12" pattern="-?[0-9]+([.,][0-9]{1,2})?" class="air-value' + (locked ? ' is-existing-cell' : '') + '" name="rows[' + idx + '][' + field + ']" value="' + escapeHtml(value) + '" data-original="' + escapeHtml(value) + '"' + (locked ? ' readonly tabindex="-1"' : '') + '></td>';
            });
            html += '<td class="petugas-cell' + (rowPetugas ? ' has-existing' : '') + '" data-original-petugas="' + escapeHtml(rowPetugas) + '">' + escapeHtml(rowPetugas) + '</td>';
            html += '</tr>';
        });
        $('#airDryerRows').html(html);
        $('#air_keterangan').val(keterangan || '').data('original', keterangan || '');
        updatePetugasCells();
        updateExistingEditButton();
    }

    function airDryerValuesEqual(v1, v2) {
        var s1 = $.trim(String(v1 === null || v1 === undefined ? '' : v1));
        var s2 = $.trim(String(v2 === null || v2 === undefined ? '' : v2));
        if (s1 === '' && s2 === '') return true;
        if (s1 === '' || s2 === '') return false;
        var n1 = parseFloat(s1.replace(',', '.'));
        var n2 = parseFloat(s2.replace(',', '.'));
        if (!isNaN(n1) && !isNaN(n2)) {
            return Math.abs(n1 - n2) < 0.0001;
        }
        return s1 === s2;
    }

    function rowIsModifiedOrNew($row) {
        var isExisting = $row.data('existing') == 1;
        var hasAnyInput = false;
        var isChanged = false;
        $row.find('.air-value').each(function() {
            var val = $(this).val();
            var orig = $(this).data('original');
            if ($.trim(val) !== '') hasAnyInput = true;
            if (!airDryerValuesEqual(val, orig)) {
                isChanged = true;
            }
        });
        if (!isExisting) return hasAnyInput;
        return isChanged;
    }

    function selectedPetugasName() {
        if (!$('#air_petugas_default').val()) return '';
        return $('#air_petugas_default option:selected').text();
    }

    function rowHasInput($row) {
        var hasValue = false;
        $row.find('.air-value').each(function() {
            if ($.trim($(this).val()) !== '') {
                hasValue = true;
                return false;
            }
        });
        return hasValue;
    }

    function mergePetugasNames(existing, next) {
        var names = {};
        var list = [];
        function add(str) {
            if (!str) return;
            String(str).split(',').forEach(function(item) {
                var trimmed = $.trim(item);
                if (!trimmed) return;
                var key = trimmed.toLowerCase();
                if (!names[key]) {
                    names[key] = true;
                    list.push(trimmed);
                }
            });
        }
        add(existing);
        add(next);
        return list.join(', ');
    }

    function updatePetugasCells() {
        var petugas = selectedPetugasName();
        $('#airDryerRows tr').each(function() {
            var $row = $(this);
            var isExisting = $row.data('existing') == 1;
            var $cell = $row.find('.petugas-cell');
            var origPetugas = String($cell.data('original-petugas') || '');

            if (isExisting) {
                if (rowIsModifiedOrNew($row)) {
                    var merged = origPetugas ? mergePetugasNames(origPetugas, petugas) : (rowHasInput($row) ? petugas : '');
                    $cell.text(merged);
                } else {
                    $cell.text(origPetugas);
                }
            } else {
                $cell.text(rowHasInput($row) ? petugas : '');
            }
        });
    }

    function loadExisting() {
        var tanggal = $('#air_tanggal').val();
        $('#airTanggalLabel').text(formatDateID(tanggal));
        renderInputRows({}, '');
        if (!tanggal) return;
        $.getJSON('get_air_dryer_existing.php', {tanggal: tanggal}).done(function(res) {
            renderInputRows(res && res.success ? res.data : {}, res && res.keterangan ? res.keterangan : '');
        });
    }

    initPetugasSelect2();
    renderInputRows({}, '');

    var table = $('#airDryerTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: 'air_dryer_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
            }
        },
        columns: [
            {data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }},
            {data: 'tanggal_formatted'},
            {data: 'petugas'},
            {data: 'created_by'},
            {data: 'updated_by'},
            {data: null, orderable: false, searchable: false, render: function(data, type, row) {
                var html = '<div class="air-action-group">';
                html += '<a class="btn btn-info btn-sm" title="View" href="view_air_dryer.php?id=' + row.id + '"><i class="fas fa-eye"></i></a>';
                if (appPermissions.canEdit) html += '<a class="btn btn-warning btn-sm" title="Edit" href="edit_air_dryer.php?id=' + row.id + '"><i class="fas fa-edit"></i></a>';
                if (appPermissions.canDelete) html += '<button type="button" class="btn btn-danger btn-sm btn-delete" title="Delete" data-id="' + row.id + '"><i class="fas fa-trash"></i></button>';
                html += '</div>';
                return html;
            }}
        ]
    });

    $('#filterStartDate,#filterEndDate').on('change', function() { table.ajax.reload(); });
    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate,#filterEndDate').val('');
        table.ajax.reload();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahAirDryer')[0].reset();
        $('#air_tanggal').val(todayLocal());
        $('#air_petugas_default').val('').trigger('change');
        $('#modalTambahAirDryer').modal('show');
        loadExisting();
    });

    $('#air_tanggal').on('change', loadExisting);
    $('#air_petugas_default').on('change', updatePetugasCells);
    $('#airDryerRows').on('keypress', '.air-value', function(e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.which < 32) return;
        if (!/[0-9.,-]/.test(String.fromCharCode(e.which))) e.preventDefault();
    });
    $('#airDryerRows').on('input change', '.air-value', function() {
        var sanitized = sanitizeDecimalInput(this.value);
        if (this.value !== sanitized) this.value = sanitized;
        updatePetugasCells();
    });
    $('#btnEditExistingAir').on('click', enableExistingEditMode);

    $('#formTambahAirDryer').on('submit', function(e) {
        e.preventDefault();
        var isEditExisting = $('#air_edit_existing').val() === '1';
        var currentKet = $.trim($('#air_keterangan').val());
        var origKet = $.trim(String($('#air_keterangan').data('original') || ''));
        var isKeteranganChanged = (currentKet !== origKet);

        var disabledInputs = [];
        if (isEditExisting) {
            $('#airDryerRows tr').each(function() {
                var $r = $(this);
                if (!rowIsModifiedOrNew($r)) {
                    var $inps = $r.find('input');
                    $inps.prop('disabled', true);
                    disabledInputs.push($inps);
                }
            });
        }

        var activeRowCount = $('#airDryerRows tr').filter(function() {
            return rowIsModifiedOrNew($(this));
        }).length;

        if (activeRowCount === 0 && !isKeteranganChanged) {
            disabledInputs.forEach(function($inp) { $inp.prop('disabled', false); });
            Swal.fire('Info', 'Tidak ada baris data yang diubah atau ditambahkan.', 'info');
            return;
        }

        var postData = $(this).serialize();
        disabledInputs.forEach(function($inp) { $inp.prop('disabled', false); });

        var $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'save_air_dryer.php',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                $submitBtn.prop('disabled', false).text('Simpan');
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success');
                    $('#modalTambahAirDryer').modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Gagal', res.message || 'Data gagal disimpan.', 'error');
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text('Simpan');
                Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error');
            }
        });
    });

    $('#airDryerTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({title: 'Hapus data?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'}).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('delete_air_dryer.php', {id: id}, function(res) {
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
