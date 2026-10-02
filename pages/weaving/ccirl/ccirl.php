<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canAdd = weaving_can($permissions, 'CanAdd');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$hours = ccirl_hours();
$items = ccirl_items();
$petugasOptions = ccirl_get_petugas_options($conn);
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center cc-page-head">
                <div class="col-sm-8"><h1 class="cc-page-title">Centac Compressor Ingersoll Rand Log Sheet</h1></div>
                <div class="col-sm-4 text-right cc-page-back"><a href="/gg_app/pages/weaving/weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white cc-list-header">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> List Data CCIRL</h3>
                    <div class="cc-list-actions">
                        <?php if ($canAdd): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
                        <?php endif; ?>
                        <?php if ($canReport): ?>
                            <a href="report_ccirl.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body cc-list-body">
                    <div class="row mb-3 cc-filter-row">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filterCompressorNo">Compressor No</label>
                            <select id="filterCompressorNo" class="form-control">
                                <option value="">Semua</option>
                                <?php foreach (ccirl_no_options() as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
                        </div>
                    </div>
                    <div class="cc-list-table-wrap">
                        <table id="ccirlTable" class="table table-hover table-bordered table-sm nowrap text-center" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Compressor No</th>
                                    <th>Tanggal</th>
                                    <th>Petugas</th>
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
    .cc-page-title{font-size:24px;font-weight:600;line-height:1.2;margin:0}
    .cc-list-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .cc-list-actions{display:inline-flex;align-items:center;gap:6px;margin-left:auto}
    .cc-filter-row{row-gap:10px}
    .cc-filter-row label{margin-bottom:4px;font-size:13px}
    .cc-list-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    #ccirlTable{min-width:720px}
    .cc-action-group{display:inline-flex;align-items:center;justify-content:center;gap:4px}
    .cc-action-group .btn{border-radius:4px!important}
    #modalTambahCcirl .modal-dialog{max-width:98vw}
    #modalTambahCcirl .modal-body{max-height:76vh;overflow:auto}
    .cc-input-meta{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:10px;margin-bottom:10px}
    .cc-input-wrap{max-height:58vh;overflow:auto;border:1px solid #111;background:#fff}
    .cc-input-table{border-collapse:collapse;width:100%;min-width:1350px;margin-bottom:0;background:#fff}
    .cc-input-table th,.cc-input-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px}
    .cc-input-table .sheet-title{font-size:15px;font-weight:800;background:#fff}
    .cc-input-table .date-title{text-align:left;background:#fff;font-weight:700}
    .cc-input-table .head-grey{background:#d9d9d9;font-weight:700;color:#000;text-transform:uppercase;font-size:11px}
    .cc-input-table .status-cell{background:#d9d9d9;text-align:left;min-width:220px;font-weight:600}
    .cc-input-table input{width:44px;height:28px;border:1px solid #cbd5e1;text-align:center;padding:2px 3px}
    .cc-input-table td.is-existing-cell,.cc-input-table input.is-existing-cell{background:#e9ecef;color:#495057;cursor:not-allowed;pointer-events:none}
    .cc-input-table .petugas-title{background:#d9d9d9;font-weight:700;text-align:center}
    .cc-input-table .petugas-cell.has-existing{background:#e9ecef}
    .cc-mobile-hint{display:none;color:#6c757d;font-size:12px}
    @media (max-width:767.98px){
        .content-header{padding:10px .5rem 6px}
        .content{padding:0 .25rem}
        .cc-page-title{font-size:18px}
        .cc-page-back{text-align:left!important;margin-top:8px}
        .cc-list-header{align-items:flex-start;padding:10px}
        .cc-list-header .card-title{flex-basis:100%;font-size:12px;line-height:1.25}
        .cc-list-actions{width:100%;margin-left:0;justify-content:flex-start}
        .cc-list-actions .btn{flex:1 1 0;padding:5px 6px;font-size:11px}
        .cc-list-body{padding:10px}
        #btnResetFilter{width:100%;height:34px;font-size:12px}
        .cc-list-table-wrap{border:1px solid #dee2e6;border-radius:4px;background:#fff}
        #ccirlTable{min-width:760px;margin-bottom:0!important;font-size:11px}
        #modalTambahCcirl .modal-dialog{max-width:none;width:100%;height:100%;min-height:100%;margin:0}
        #modalTambahCcirl .modal-content{height:100vh;border-radius:0;border:0;display:flex;flex-direction:column}
        #modalTambahCcirl .modal-header{min-height:44px;padding:9px 12px}
        #modalTambahCcirl .modal-title{max-width:calc(100vw - 56px);font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #modalTambahCcirl .modal-body{flex:1 1 auto;max-height:none;overflow-y:auto;padding:10px}
        #modalTambahCcirl .modal-footer{position:sticky;bottom:0;z-index:5;background:#fff;padding:8px 10px;gap:6px;box-shadow:0 -3px 10px rgba(0,0,0,.08)}
        #modalTambahCcirl label{margin-bottom:3px;font-size:11px;font-weight:700}
        #modalTambahCcirl .form-control{height:34px;font-size:12px}
        .cc-input-meta{display:block}
        .cc-mobile-hint{display:block;margin-bottom:6px!important}
        .cc-input-wrap{max-height:52vh;border-radius:4px}
        .cc-input-table{min-width:1200px;font-size:11px}
        .cc-input-table .sheet-title{font-size:12px}
        .cc-input-table .head-grey{font-size:9px}
        .cc-input-table th,.cc-input-table td{padding:2px}
        .cc-input-table input{width:40px;height:26px;font-size:11px}
    }
</style>

<div class="modal fade" id="modalTambahCcirl" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data CCIRL</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formTambahCcirl" autocomplete="off">
                <input type="hidden" name="edit_existing" id="cc_edit_existing" value="0">
                <div class="modal-body">
                    <div class="cc-input-meta">
                        <div class="form-group">
                            <label for="cc_tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="cc_tanggal" name="tanggal" required>
                        </div>
                        <div class="form-group">
                            <label for="cc_compressor_no">Compressor No</label>
                            <select class="form-control" id="cc_compressor_no" name="compressor_no" required>
                                <?php foreach (ccirl_no_options() as $no): ?>
                                    <option value="<?= htmlspecialchars($no) ?>"><?= htmlspecialchars($no) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cc_petugas_default">Petugas</label>
                            <select class="form-control select2bs4" id="cc_petugas_default" name="petugas_default" data-placeholder="Cari / pilih petugas" required>
                                <option value="">Pilih Petugas</option>
                                <?php foreach ($petugasOptions as $petugasName): ?>
                                    <option value="<?= htmlspecialchars($petugasName) ?>"><?= htmlspecialchars($petugasName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="mb-2 text-right">
                            <button type="button" class="btn btn-warning btn-sm d-none" id="btnEditExistingCcirl">
                                <i class="fas fa-edit"></i> Edit Data Existing
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="cc-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengisi jam pemeriksaan.</div>
                    <div class="cc-input-wrap">
                        <table class="cc-input-table">
                            <thead>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="sheet-title">CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET</th></tr>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">COMPRESSOR NO : <span id="ccCompressorLabel">1</span></th></tr>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">TANGGAL : <span id="ccTanggalLabel"></span></th></tr>
                                <tr>
                                    <th class="head-grey" rowspan="2">Status Message</th>
                                    <th class="head-grey" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th>
                                </tr>
                                <tr>
                                    <?php foreach ($hours as $hour): ?>
                                        <th class="head-grey"><?= htmlspecialchars(ccirl_display_hour($hour)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="ccirlRows"></tbody>
                        </table>
                    </div>
                    <div class="form-group mt-3 mb-0">
                        <label for="cc_keterangan">Keterangan</label>
                        <textarea class="form-control" id="cc_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
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

    var ccHours = <?= json_encode($hours) ?>;
    var ccItems = <?= json_encode($items) ?>;

    function initPetugasSelect2() {
        if (!$.fn.select2) return;
        var $select = $('#cc_petugas_default');
        if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
        $select.select2({theme: 'bootstrap4', width: '100%', placeholder: 'Cari / pilih petugas', allowClear: true, dropdownParent: $('#modalTambahCcirl')});
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

    function selectedPetugasName() {
        if (!$('#cc_petugas_default').val()) return '';
        return $('#cc_petugas_default option:selected').text();
    }

    function hourHasInput(hour) {
        var hasValue = false;
        $('#ccirlRows input.cc-value[data-hour="' + hour + '"]').each(function() {
            if ($.trim($(this).val()) !== '') {
                hasValue = true;
                return false;
            }
        });
        return hasValue;
    }

    function updatePetugasCells() {
        var petugas = selectedPetugasName();
        ccHours.forEach(function(hour) {
            var $cell = $('#ccPetugas_' + hour.replace(':', ''));
            if ($cell.data('existing') === 1 && $.trim($cell.text()) !== '') return;
            $cell.text(hourHasInput(hour) ? petugas : '');
        });
    }

    function mergePetugasText(current, next) {
        var names = [];
        var seen = {};
        (String(current || '') + ',' + String(next || '')).split(',').forEach(function(name) {
            name = $.trim(name);
            var key = name.toLowerCase();
            if (name && !seen[key]) {
                seen[key] = true;
                names.push(name);
            }
        });
        return names.join(', ');
    }

    function resetExistingEditMode() {
        $('#cc_edit_existing').val('0');
        $('#btnEditExistingCcirl').addClass('d-none').prop('disabled', false)
            .html('<i class="fas fa-edit"></i> Edit Data Existing');
    }

    function updateExistingEditButton() {
        if (!appPermissions.canEdit) return;
        $('#btnEditExistingCcirl').toggleClass('d-none', $('#ccirlRows input.is-existing-cell').length === 0);
    }

    function enableExistingEditMode() {
        $('#cc_edit_existing').val('1');
        $('#ccirlRows input.is-existing-cell')
            .prop('readonly', false)
            .removeAttr('tabindex')
            .removeClass('is-existing-cell');
        $('#ccirlRows td.is-existing-cell').removeClass('is-existing-cell');
        $('#btnEditExistingCcirl').prop('disabled', true)
            .html('<i class="fas fa-check"></i> Mode Edit Aktif');
    }

    function renderInputRows(existing, keterangan) {
        resetExistingEditMode();
        var html = '';
        ccItems.forEach(function(item) {
            html += '<tr><td class="status-cell">' + item.label + '</td>';
            ccHours.forEach(function(hour) {
                var key = item.key + '|' + hour;
                var data = existing && existing[key] ? existing[key] : null;
                var value = data ? data.nilai : '';
                var locked = data && value !== '';
                html += '<td' + (locked ? ' class="is-existing-cell"' : '') + '><input type="text" class="cc-value' + (locked ? ' is-existing-cell' : '') + '" data-hour="' + hour + '" name="rows[' + item.key + '][' + hour + ']" value="' + escapeHtml(value) + '" data-original="' + escapeHtml(value) + '"' + (locked ? ' readonly tabindex="-1"' : '') + '></td>';
            });
            html += '</tr>';
        });
        html += '<tr><td class="petugas-title">PETUGAS</td>';
        ccHours.forEach(function(hour) {
            var petugas = '';
            var existingFlag = 0;
            ccItems.forEach(function(item) {
                var key = item.key + '|' + hour;
                if (existing && existing[key] && existing[key].petugas) {
                    petugas = mergePetugasText(petugas, existing[key].petugas);
                    existingFlag = 1;
                }
            });
            html += '<td class="petugas-cell' + (existingFlag ? ' has-existing' : '') + '" id="ccPetugas_' + hour.replace(':', '') + '" data-existing="' + existingFlag + '">' + escapeHtml(petugas) + '</td>';
        });
        html += '</tr>';
        $('#ccirlRows').html(html);
        if (keterangan !== undefined) {
            $('#cc_keterangan').val(keterangan || '').data('original', keterangan || '');
        }
        updatePetugasCells();
        updateExistingEditButton();
    }

    function loadExisting() {
        var tanggal = $('#cc_tanggal').val();
        var compressorNo = $('#cc_compressor_no').val();
        $('#ccTanggalLabel').text(formatDateID(tanggal));
        $('#ccCompressorLabel').text(compressorNo || '1');
        renderInputRows({}, '');
        if (!tanggal || !compressorNo) return;
        $.getJSON('get_ccirl_existing.php', {tanggal: tanggal, compressor_no: compressorNo}).done(function(res) {
            renderInputRows(res && res.success ? res.data : {}, res && res.keterangan ? res.keterangan : '');
        });
    }

    initPetugasSelect2();
    renderInputRows({}, '');

    var table = $('#ccirlTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: 'ccirl_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.compressor_no = $('#filterCompressorNo').val();
            }
        },
        columns: [
            {data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }},
            {data: 'compressor_no'},
            {data: 'tanggal_formatted'},
            {data: 'petugas'},
            {data: 'created_by'},
            {data: null, orderable: false, searchable: false, render: function(data, type, row) {
                var html = '<div class="cc-action-group">';
                html += '<a class="btn btn-info btn-sm" title="View" href="view_ccirl.php?id=' + row.id + '"><i class="fas fa-eye"></i></a>';
                if (appPermissions.canEdit) html += '<a class="btn btn-warning btn-sm" title="Edit" href="edit_ccirl.php?id=' + row.id + '"><i class="fas fa-edit"></i></a>';
                if (appPermissions.canDelete) html += '<button type="button" class="btn btn-danger btn-sm btn-delete" title="Delete" data-id="' + row.id + '"><i class="fas fa-trash"></i></button>';
                html += '</div>';
                return html;
            }}
        ]
    });

    $('#filterStartDate,#filterEndDate,#filterCompressorNo').on('change', function() { table.ajax.reload(); });
    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate,#filterEndDate,#filterCompressorNo').val('');
        table.ajax.reload();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCcirl')[0].reset();
        $('#cc_keterangan').val('').data('original', '');
        $('#cc_tanggal').val(todayLocal());
        $('#cc_compressor_no').val('1');
        $('#cc_petugas_default').val('').trigger('change');
        $('#modalTambahCcirl').modal('show');
        loadExisting();
    });

    $('#cc_tanggal,#cc_compressor_no').on('change', loadExisting);
    $('#cc_petugas_default').on('change', updatePetugasCells);
    $('#ccirlRows').on('input change', '.cc-value', updatePetugasCells);
    $('#btnEditExistingCcirl').on('click', enableExistingEditMode);

    $('#formTambahCcirl').on('submit', function(e) {
        e.preventDefault();
        var isEditExisting = $('#cc_edit_existing').val() === '1';
        var $unmodifiedInputs = $();

        if (isEditExisting) {
            var anyCellChanged = false;
            var inputsToDisable = [];

            $('#ccirlRows input.cc-value').each(function() {
                var $inp = $(this);
                var currentVal = $.trim($inp.val());
                var origVal = $.trim(String($inp.data('original') !== undefined ? $inp.data('original') : ''));
                var changed = false;

                if (currentVal !== '' && origVal === '') {
                    changed = true;
                } else if (currentVal !== '' && origVal !== '') {
                    var cNum = parseFloat(currentVal.replace(',', '.'));
                    var oNum = parseFloat(origVal.replace(',', '.'));
                    if (!isNaN(cNum) && !isNaN(oNum)) {
                        changed = Math.abs(cNum - oNum) > 0.00001;
                    } else {
                        changed = (currentVal !== origVal);
                    }
                } else if (currentVal === '' && origVal !== '') {
                    changed = true;
                }

                if (changed) {
                    anyCellChanged = true;
                } else {
                    inputsToDisable.push($inp);
                }
            });

            var origKet = $.trim(String($('#cc_keterangan').data('original') || ''));
            var currentKet = $.trim($('#cc_keterangan').val());
            var ketChanged = (currentKet !== origKet);

            if (!anyCellChanged && !ketChanged) {
                Swal.fire('Info', 'Tidak ada perubahan data.', 'info');
                return;
            }

            inputsToDisable.forEach(function($inp) {
                $inp.prop('disabled', true);
            });
            $unmodifiedInputs = $(inputsToDisable.map(function($i){ return $i[0]; }));
        }

        var formData = $(this).serialize();

        if (isEditExisting && $unmodifiedInputs.length > 0) {
            $unmodifiedInputs.prop('disabled', false);
        }

        $.ajax({
            url: 'save_ccirl.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success');
                    $('#modalTambahCcirl').modal('hide');
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

    $('#ccirlTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({title: 'Hapus data?', text: 'Seluruh isi log sheet tanggal ini akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'}).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('delete_ccirl.php', {id: id}, function(res) {
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
