<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canAdd = weaving_can($permissions, 'CanAdd');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$pelaksanaOptions = temp_compressor_v2_get_pelaksana_options($conn);
$hours = temp_compressor_v2_hours();
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center compressor-page-head">
                <div class="col-sm-8">
                    <h1 class="compressor-page-title">Check Sheet Kompressor Sullair (V2)</h1>
                </div>
                <div class="col-sm-4 text-right compressor-page-back">
                    <a href="/gg_app/pages/weaving/weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white compressor-list-header">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> List Data Check Sheet Kompressor Sullair V2</h3>
                    <div class="compressor-list-actions">
                        <?php if ($canAdd): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                        <?php endif; ?>
                        <?php if ($canReport): ?>
                            <a href="report_temp_compressor_v2.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body compressor-list-body">
                    <div class="row mb-3 compressor-filter-row">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filterWeaving">Weaving</label>
                            <select id="filterWeaving" class="form-control">
                                <option value="">Semua Weaving</option>
                                <option value="1">Weaving 1</option>
                                <option value="2">Weaving 2</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filterCompressor">Compressor No</label>
                            <select id="filterCompressor" class="form-control">
                                <option value="">Semua Compressor</option>
                                <option value="1">Compressor 1</option>
                                <option value="2">Compressor 2</option>
                                <option value="3">Compressor 3</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm w-100" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
                        </div>
                    </div>
                    <div class="compressor-list-table-wrap">
                        <table id="tempCompressorV2Table" class="table table-hover table-bordered table-sm nowrap text-center" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Weaving</th>
                                    <th>Compressor No</th>
                                    <th>Pelaksana</th>
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
    .compressor-page-title{font-size:24px;font-weight:600;line-height:1.2;margin:0}
    .compressor-list-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .compressor-list-actions{display:inline-flex;align-items:center;gap:6px;margin-left:auto}
    .compressor-filter-row{row-gap:10px}
    .compressor-filter-row label{margin-bottom:4px;font-size:13px}
    .compressor-list-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    #tempCompressorV2Table{width:100%!important}
    .compressor-action-group{display:inline-flex;align-items:center;justify-content:center;gap:4px}
    .compressor-action-group .btn{border-radius:4px!important}
    #modalTambahTempCompressor .modal-dialog{max-width:96vw}
    #modalTambahTempCompressor .modal-body{max-height:76vh;overflow:auto}
    .compressor-input-meta{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin-bottom:10px}
    .compressor-input-wrap{max-height:58vh;overflow:auto;border:1px solid #111;background:#fff}
    .compressor-input-table{border-collapse:collapse;width:100%;min-width:1100px;margin-bottom:0;background:#fff}
    .compressor-input-table th,.compressor-input-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:4px}
    .compressor-input-table .sheet-title{font-size:16px;font-weight:800;background:#fff}
    .compressor-input-table .meta-title{text-align:left;background:#fff;font-weight:700;font-size:12px;padding:6px}
    .compressor-input-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase;font-size:11px;line-height:1.15}
    .compressor-input-table input{width:70px;height:30px;border:1px solid #cbd5e1;text-align:center;padding:2px 4px}
    .compressor-input-table .time-cell{background:#f8f9fa;font-weight:600;white-space:nowrap}
    .compressor-input-table tr.is-existing .time-cell,
    .compressor-input-table td.is-existing-cell,
    .compressor-input-table .pelaksana-cell.has-existing{background:#e9ecef}
    .compressor-input-table input.is-existing-cell{background:#e9ecef;color:#495057;cursor:not-allowed;pointer-events:none}
    .compressor-mobile-hint{display:none;color:#6c757d;font-size:12px}
    @media (max-width:767.98px){
        .content-header{padding:10px .5rem 6px}
        .content{padding:0 .25rem}
        .compressor-page-title{font-size:18px}
        .compressor-page-back{text-align:left!important;margin-top:8px}
        .compressor-list-header{align-items:flex-start;padding:10px}
        .compressor-list-header .card-title{flex-basis:100%;font-size:12px;line-height:1.25}
        .compressor-list-actions{width:100%;margin-left:0;justify-content:flex-start}
        .compressor-list-actions .btn{flex:1 1 0;padding:5px 6px;font-size:11px}
        .compressor-list-body{padding:10px}
        #btnResetFilter{width:100%;height:34px;font-size:12px}
        .compressor-list-table-wrap{border:1px solid #dee2e6;border-radius:4px;background:#fff}
        #tempCompressorV2Table{min-width:940px;margin-bottom:0!important;font-size:11px}
        #modalTambahTempCompressor .modal-dialog{max-width:none;width:100%;height:100%;min-height:100%;margin:0}
        #modalTambahTempCompressor .modal-content{height:100vh;border-radius:0;border:0;display:flex;flex-direction:column}
        #modalTambahTempCompressor .modal-header{min-height:44px;padding:9px 12px}
        #modalTambahTempCompressor .modal-title{max-width:calc(100vw - 56px);font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #modalTambahTempCompressor .modal-body{flex:1 1 auto;max-height:none;overflow-y:auto;padding:10px}
        #modalTambahTempCompressor .modal-footer{position:sticky;bottom:0;z-index:5;background:#fff;padding:8px 10px;gap:6px;box-shadow:0 -3px 10px rgba(0,0,0,.08)}
        .compressor-input-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px}
        #modalTambahTempCompressor label{margin-bottom:3px;font-size:11px;font-weight:700}
        #modalTambahTempCompressor .form-control{height:34px;font-size:12px}
        .compressor-mobile-hint{display:block;margin-bottom:6px!important}
        .compressor-input-wrap{max-height:50vh;border-radius:4px}
        .compressor-input-table{min-width:1000px;font-size:11px}
        .compressor-input-table .sheet-title{font-size:12px}
        .compressor-input-table .head-blue{font-size:9px}
        .compressor-input-table th,.compressor-input-table td{padding:2px}
        .compressor-input-table input{width:60px;height:28px;font-size:11px}
    }
</style>

<div class="modal fade" id="modalTambahTempCompressor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Check Sheet Kompressor Sullair V2</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formTambahTempCompressor" autocomplete="off">
                <input type="hidden" name="edit_existing" id="compressor_edit_existing" value="0">
                <div class="modal-body">
                    <div class="compressor-input-meta">
                        <div class="form-group">
                            <label for="compressor_tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="compressor_tanggal" name="tanggal" required>
                        </div>
                        <div class="form-group">
                            <label for="compressor_weaving">Weaving</label>
                            <select class="form-control" id="compressor_weaving" name="weaving" required>
                                <option value="1">Weaving 1</option>
                                <option value="2">Weaving 2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="compressor_no">Compressor No</label>
                            <select class="form-control" id="compressor_no" name="compressor_no" required>
                                <option value="1">Compressor 1</option>
                                <option value="2">Compressor 2</option>
                                <option value="3">Compressor 3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="compressor_pelaksana_default">Pelaksana</label>
                            <select class="form-control select2bs4" id="compressor_pelaksana_default" name="pelaksana_default" data-placeholder="Pilih pelaksana" required>
                                <option value="">Pilih Pelaksana</option>
                                <?php foreach ($pelaksanaOptions as $pName): ?>
                                    <option value="<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="mb-2 text-right">
                            <button type="button" class="btn btn-warning btn-sm d-none" id="btnEditExistingCompressor">
                                <i class="fas fa-edit"></i> Edit Data Existing
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="compressor-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengisi semua kolom.</div>
                    <div class="compressor-input-wrap">
                        <table class="compressor-input-table">
                            <thead>
                                <tr><th colspan="13" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                                <tr>
                                    <th colspan="6" class="meta-title">
                                        COMPRESSOR NO: <span id="labelCompressorNo">1</span> &nbsp;&nbsp;&nbsp;&nbsp; WEAVING: <span id="labelWeaving">1</span>
                                    </th>
                                    <th colspan="5" class="meta-title text-center">
                                        TANGGAL: <span id="labelTanggal"></span>
                                    </th>
                                    <th colspan="2" class="meta-title text-center" style="white-space: nowrap;">
                                        SUM-FM-THK-016
                                    </th>
                                </tr>
                                <tr>
                                    <th class="head-blue" rowspan="2">JAM</th>
                                    <th class="head-blue" colspan="2">PRESSURE (BAR)</th>
                                    <th class="head-blue" colspan="3">TEMPERATURE</th>
                                    <th class="head-blue" rowspan="2">DRYER<br>(&deg;C)</th>
                                    <th class="head-blue" rowspan="2">ARUS<br>LISTRIK (A)</th>
                                    <th class="head-blue" colspan="4">AIR COOLING</th>
                                    <th class="head-blue" rowspan="2">PELAKSANA</th>
                                </tr>
                                <tr>
                                    <th class="head-blue">P1</th>
                                    <th class="head-blue">P2</th>
                                    <th class="head-blue">T1</th>
                                    <th class="head-blue">T2</th>
                                    <th class="head-blue">T3</th>
                                    <th class="head-blue">PRESS. IN</th>
                                    <th class="head-blue">PRESS. OUT</th>
                                    <th class="head-blue">TEMP. IN</th>
                                    <th class="head-blue">TEMP. OUT</th>
                                </tr>
                            </thead>
                            <tbody id="tempCompressorRows"></tbody>
                        </table>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label for="compressor_keterangan">Keterangan</label>
                        <textarea class="form-control" id="compressor_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
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

    var compressorHours = <?= json_encode($hours) ?>;

    function initPelaksanaSelect2() {
        if (!$.fn.select2) return;
        var $select = $('#compressor_pelaksana_default');
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({theme: 'bootstrap4', width: '100%', placeholder: 'Pilih pelaksana', allowClear: true, dropdownParent: $('#modalTambahTempCompressor')});
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
        $('#compressor_edit_existing').val('0');
        $('#btnEditExistingCompressor').addClass('d-none').prop('disabled', false)
            .html('<i class="fas fa-edit"></i> Edit Data Existing');
    }

    function updateExistingEditButton() {
        if (!appPermissions.canEdit) return;
        $('#btnEditExistingCompressor').toggleClass('d-none', $('#tempCompressorRows input.is-existing-cell').length === 0);
    }

    function enableExistingEditMode() {
        $('#compressor_edit_existing').val('1');
        $('#tempCompressorRows input.is-existing-cell')
            .prop('readonly', false)
            .removeAttr('tabindex')
            .removeClass('is-existing-cell');
        $('#tempCompressorRows td.is-existing-cell').removeClass('is-existing-cell');
        $('#tempCompressorRows tr.is-existing').removeClass('is-existing');
        $('#btnEditExistingCompressor').prop('disabled', true)
            .html('<i class="fas fa-check"></i> Mode Edit Aktif');
        updatePelaksanaCells();
    }

    var fields = ['pressure_p1', 'pressure_p2', 'temp_t1', 'temp_t2', 'temp_t3', 'dryer_c', 'arus_a', 'press_in', 'press_out', 'temp_in', 'temp_out'];

    function renderInputRows(existing, keterangan) {
        resetExistingEditMode();
        var html = '';
        compressorHours.forEach(function(hour, idx) {
            var data = existing && existing[hour] ? existing[hour] : null;
            var rowPelaksana = data && data.pelaksana ? data.pelaksana : '';
            html += '<tr' + (data ? ' class="is-existing"' : '') + ' data-existing="' + (data ? '1' : '0') + '">';
            html += '<td class="time-cell">' + (hour === '00:30' ? '24:30' : hour) + '<input type="hidden" name="rows[' + idx + '][jam]" value="' + hour + '"></td>';
            fields.forEach(function(field) {
                var value = data ? data[field] : '';
                var locked = data && value !== '';
                html += '<td' + (locked ? ' class="is-existing-cell"' : '') + '><input type="text" inputmode="decimal" maxlength="12" pattern="-?[0-9]+([.,][0-9]{1,2})?" class="compressor-value' + (locked ? ' is-existing-cell' : '') + '" name="rows[' + idx + '][' + field + ']" value="' + escapeHtml(value) + '" data-original="' + escapeHtml(value) + '"' + (locked ? ' readonly tabindex="-1"' : '') + '></td>';
            });
            html += '<td class="pelaksana-cell' + (rowPelaksana ? ' has-existing' : '') + '" data-original-pelaksana="' + escapeHtml(rowPelaksana) + '">' + escapeHtml(rowPelaksana) + '</td></tr>';
        });
        $('#tempCompressorRows').html(html);
        $('#compressor_keterangan').val(keterangan || '').data('original', keterangan || '');
        updatePelaksanaCells();
        updateExistingEditButton();
    }

    function tempCompressorValuesEqual(v1, v2) {
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
        $row.find('.compressor-value').each(function() {
            var val = $(this).val();
            var orig = $(this).data('original');
            if ($.trim(val) !== '') hasAnyInput = true;
            if (!tempCompressorValuesEqual(val, orig)) {
                isChanged = true;
            }
        });
        if (!isExisting) return hasAnyInput;
        return isChanged;
    }

    function selectedPelaksanaName() {
        if (!$('#compressor_pelaksana_default').val()) return '';
        return $('#compressor_pelaksana_default option:selected').text();
    }

    function rowHasInput($row) {
        var hasValue = false;
        $row.find('.compressor-value').each(function() {
            if ($.trim($(this).val()) !== '') {
                hasValue = true;
                return false;
            }
        });
        return hasValue;
    }

    function mergePelaksanaNames(existing, next) {
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

    function updatePelaksanaCells() {
        var pelaksana = selectedPelaksanaName();
        $('#tempCompressorRows tr').each(function() {
            var $row = $(this);
            var isExisting = $row.data('existing') == 1;
            var $cell = $row.find('.pelaksana-cell');
            var origPelaksana = String($cell.data('original-pelaksana') || '');

            if (isExisting) {
                if (rowIsModifiedOrNew($row)) {
                    var merged = origPelaksana ? mergePelaksanaNames(origPelaksana, pelaksana) : (rowHasInput($row) ? pelaksana : '');
                    $cell.text(merged);
                } else {
                    $cell.text(origPelaksana);
                }
            } else {
                $cell.text(rowHasInput($row) ? pelaksana : '');
            }
        });
    }

    function loadExisting() {
        var tanggal = $('#compressor_tanggal').val();
        var weaving = $('#compressor_weaving').val();
        var compNo = $('#compressor_no').val();

        $('#labelTanggal').text(formatDateID(tanggal));
        $('#labelWeaving').text(weaving);
        $('#labelCompressorNo').text(compNo);

        renderInputRows({}, '');
        if (!tanggal || !weaving || !compNo) return;
        $.getJSON('get_temp_compressor_v2_existing.php', {
            tanggal: tanggal,
            weaving: weaving,
            compressor_no: compNo
        }).done(function(res) {
            renderInputRows(res && res.success ? res.data : {}, res && res.keterangan ? res.keterangan : '');
        });
    }

    initPelaksanaSelect2();
    renderInputRows({}, '');

    var table = $('#tempCompressorV2Table').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: 'temp_compressor_v2_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.weaving = $('#filterWeaving').val();
                d.compressor_no = $('#filterCompressor').val();
            }
        },
        columns: [
            {data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }},
            {data: 'tanggal_formatted'},
            {data: 'weaving_label'},
            {data: 'compressor_label'},
            {data: 'pelaksana'},
            {data: 'created_by'},
            {data: 'updated_by'},
            {data: null, orderable: false, searchable: false, render: function(data, type, row) {
                var q = 'id=' + row.id + '&tanggal=' + encodeURIComponent(row.tanggal || '') + '&weaving=' + row.weaving + '&compressor_no=' + row.compressor_no;
                var html = '<div class="compressor-action-group">';
                html += '<a class="btn btn-info btn-sm" title="View" href="view_temp_compressor_v2.php?' + q + '"><i class="fas fa-eye"></i></a>';
                if (appPermissions.canEdit) html += '<a class="btn btn-warning btn-sm" title="Edit" href="edit_temp_compressor_v2.php?' + q + '"><i class="fas fa-edit"></i></a>';
                if (appPermissions.canDelete) html += '<button type="button" class="btn btn-danger btn-sm btn-delete" title="Delete" data-id="' + row.id + '" data-tanggal="' + (row.tanggal || '') + '" data-weaving="' + row.weaving + '" data-compressor="' + row.compressor_no + '"><i class="fas fa-trash"></i></button>';
                html += '</div>';
                return html;
            }}
        ]
    });

    $('#filterStartDate,#filterEndDate,#filterWeaving,#filterCompressor').on('change', function() { table.ajax.reload(); });
    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate,#filterEndDate,#filterWeaving,#filterCompressor').val('');
        table.ajax.reload();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahTempCompressor')[0].reset();
        $('#compressor_tanggal').val(todayLocal());
        $('#compressor_weaving').val('1');
        $('#compressor_no').val('1');
        $('#compressor_pelaksana_default').val('').trigger('change');
        $('#modalTambahTempCompressor').modal('show');
        loadExisting();
    });

    $('#compressor_tanggal,#compressor_weaving,#compressor_no').on('change', loadExisting);
    $('#compressor_pelaksana_default').on('change', updatePelaksanaCells);

    $('#tempCompressorRows').on('keypress', '.compressor-value', function(e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.which < 32) return;
        if (!/[0-9.,-]/.test(String.fromCharCode(e.which))) e.preventDefault();
    });
    $('#tempCompressorRows').on('input change', '.compressor-value', function() {
        var sanitized = sanitizeDecimalInput(this.value);
        if (this.value !== sanitized) this.value = sanitized;
        updatePelaksanaCells();
    });
    $('#btnEditExistingCompressor').on('click', enableExistingEditMode);

    $('#formTambahTempCompressor').on('submit', function(e) {
        e.preventDefault();
        var isEditExisting = $('#compressor_edit_existing').val() === '1';
        var currentKet = $.trim($('#compressor_keterangan').val());
        var origKet = $.trim(String($('#compressor_keterangan').data('original') || ''));
        var isKeteranganChanged = (currentKet !== origKet);

        var disabledInputs = [];
        $('#tempCompressorRows tr').each(function() {
            var $r = $(this);
            if (!rowIsModifiedOrNew($r)) {
                var $inps = $r.find('input');
                $inps.prop('disabled', true);
                disabledInputs.push($inps);
            }
        });

        var activeRowCount = $('#tempCompressorRows tr').filter(function() {
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
            url: 'save_temp_compressor_v2.php',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                $submitBtn.prop('disabled', false).text('Simpan');
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success');
                    $('#modalTambahTempCompressor').modal('hide');
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

    $('#tempCompressorV2Table').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var tanggal = $(this).data('tanggal');
        var weaving = $(this).data('weaving');
        var compressor = $(this).data('compressor');
        Swal.fire({
            title: 'Hapus data?',
            text: 'Data sheet Tanggal ' + formatDateID(tanggal) + ' (Weaving ' + weaving + ' - Compressor ' + compressor + ') akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('delete_temp_compressor_v2.php', {
                id: id,
                tanggal: tanggal,
                weaving: weaving,
                compressor_no: compressor
            }, function(res) {
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
