<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');

$permissions = weaving_require($conn, 'CanView');
$canAdd = weaving_can($permissions, 'CanAdd');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$petugasOptions = [];
$petugasSql = "SELECT DISTINCT m_emp.nama_lengkap
    FROM dbo.m_emp
    LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
    LEFT JOIN dbo.m_bag ON COALESCE(m_subbag.id_bag, m_emp.id_bag) = m_bag.id_bag
    LEFT JOIN dbo.m_dept ON COALESCE(m_bag.id_dept, m_emp.id_dept) = m_dept.id_dept
    WHERE m_emp.aktif = 1
      AND m_dept.dept = ?
      AND m_bag.bagian = ?
    ORDER BY m_emp.nama_lengkap";
$petugasStmt = sqlsrv_query($conn, $petugasSql, ['Maintenance & Utility', 'Maintenance Weaving']);
if ($petugasStmt) {
    while ($petugasRow = sqlsrv_fetch_array($petugasStmt, SQLSRV_FETCH_ASSOC)) {
        $petugasOptions[] = $petugasRow['nama_lengkap'];
    }
    sqlsrv_free_stmt($petugasStmt);
}
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center ac-page-head">
                <div class="col-sm-7">
                    <h1 class="ac-page-title">Pengecekan AC Tempratur Area Weaving</h1>
                </div>
                <div class="col-sm-5 text-right ac-page-back">
                    <a href="/gg_app/pages/weaving/weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white ac-list-header">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> List Data Pengecekan AC Weaving</h3>
                    <div class="ac-list-actions">
                        <?php if ($canAdd): ?>
                            <a href="#" class="btn btn-success btn-sm" id="btnTambahData">
                                <i class="fas fa-plus"></i> Tambah Data
                            </a>
                        <?php endif; ?>
                        <?php if ($canReport): ?>
                            <a href="report_ac_weaving.php" class="btn btn-info btn-sm">
                                <i class="fas fa-file-alt"></i> Report
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body ac-list-body">
                    <div class="row mb-3 ac-filter-row">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filterMesin">AC</label>
                            <select id="filterMesin" class="form-control">
                                <option value="">Semua AC</option>
                                <option value="AC WEAVING 1">AC WEAVING 1</option>
                                <option value="AC WEAVING 2">AC WEAVING 2</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter">
                                <i class="fas fa-undo"></i> Reset Filter
                            </button>
                        </div>
                    </div>
                    <div class="ac-list-table-wrap">
                        <table id="acWeavingTable" class="table table-hover table-bordered table-sm nowrap text-center" style="width:100%">
                            <thead class="thead-light text-center">
                                <tr>
                                    <th>No</th>
                                    <th>AC</th>
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
    .ac-page-title {
        font-size: 24px;
        font-weight: 600;
        line-height: 1.2;
        margin: 0;
    }
    .ac-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }
    .ac-list-header .card-title {
        flex: 1 1 auto;
        min-width: 240px;
        line-height: 1.25;
    }
    .ac-list-actions {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }
    .ac-list-actions .btn {
        white-space: nowrap;
    }
    .ac-filter-row {
        row-gap: 10px;
    }
    .ac-filter-row label {
        margin-bottom: 4px;
        font-size: 13px;
    }
    .ac-filter-row .form-control,
    .ac-filter-row .btn {
        min-height: 34px;
    }
    .ac-list-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    #acWeavingTable {
        min-width: 760px;
    }
    #modalTambahAcWeaving .modal-dialog {
        max-width: 96vw;
    }
    #modalTambahAcWeaving .modal-body {
        max-height: 76vh;
        overflow: auto;
    }
    .ac-weaving-table-wrap {
        max-height: 58vh;
        overflow: auto;
        border: 1px solid #9da7b1;
    }
    .ac-weaving-input-table {
        border-collapse: collapse;
        width: 100%;
        min-width: 760px;
        margin-bottom: 0;
    }
    .ac-weaving-input-table th,
    .ac-weaving-input-table td {
        border: 1px solid #9da7b1;
        padding: 4px;
        text-align: center;
        vertical-align: middle;
    }
    .ac-weaving-input-table .title-row {
        background: #fff;
        font-weight: 700;
        text-align: left;
        border-left-color: #fff;
        border-right-color: #fff;
        border-top-color: #fff;
    }
    .ac-weaving-input-table .head-row th {
        background: #9dc3e6;
        color: #000;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .ac-weaving-input-table input,
    .ac-weaving-input-table select {
        height: 34px;
        min-width: 86px;
        padding: 4px 6px;
        text-align: center;
    }
    .ac-weaving-input-table .date-cell,
    .ac-weaving-input-table .time-cell {
        background: #f8f9fa;
        font-weight: 600;
        white-space: nowrap;
    }
    .ac-weaving-input-table .wide-cell {
        min-width: 130px;
    }
    .ac-input-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }
    .ac-weaving-input-table tr.is-existing .date-cell,
    .ac-weaving-input-table tr.is-existing .time-cell,
    .ac-weaving-input-table td.is-existing-cell {
        background: #e9ecef;
    }
    .ac-weaving-input-table tr.is-existing input.is-existing-cell {
        background: #e9ecef;
        color: #495057;
        cursor: not-allowed;
        pointer-events: none;
    }
    .ac-weaving-mobile-hint {
        display: none;
        color: #6c757d;
        font-size: 12px;
    }
    #modalTambahAcWeaving .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px);
    }
    #modalTambahAcWeaving .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 2.25rem;
    }
    .ac-action-group {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .ac-action-group .btn {
        border-radius: 4px !important;
    }
    @media (max-width: 767.98px) {
        .content-header {
            padding: 10px .5rem 6px;
        }
        .content {
            padding: 0 .25rem;
        }
        .ac-page-head {
            row-gap: 8px;
        }
        .ac-page-title {
            font-size: 18px;
            line-height: 1.2;
        }
        .ac-page-back {
            text-align: left !important;
        }
        .ac-page-back .btn {
            width: auto;
            padding: 4px 8px;
            font-size: 11px;
        }
        .ac-list-header {
            align-items: flex-start;
            padding: 10px;
        }
        .ac-list-header .card-title {
            flex-basis: 100%;
            min-width: 0;
            font-size: 12px;
        }
        .ac-list-actions {
            width: 100%;
            margin-left: 0;
            justify-content: flex-start;
        }
        .ac-list-actions .btn {
            flex: 1 1 0;
            padding: 5px 6px;
            font-size: 11px;
        }
        .ac-list-body {
            padding: 10px;
        }
        .ac-filter-row {
            margin-bottom: 8px !important;
        }
        .ac-filter-row > div {
            margin-bottom: 0;
        }
        .ac-filter-row label {
            font-size: 11px;
            font-weight: 600;
        }
        .ac-filter-row .form-control {
            height: 34px;
            font-size: 12px;
        }
        #btnResetFilter {
            width: 100%;
            height: 34px;
            font-size: 12px;
        }
        .ac-list-table-wrap {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
        }
        #acWeavingTable {
            min-width: 820px;
            margin-bottom: 0 !important;
            font-size: 11px;
        }
        #acWeavingTable th,
        #acWeavingTable td {
            padding: 5px 6px;
            vertical-align: middle;
        }
        #acWeavingTable_wrapper .row {
            margin-left: 0;
            margin-right: 0;
        }
        #acWeavingTable_wrapper .dataTables_length,
        #acWeavingTable_wrapper .dataTables_filter,
        #acWeavingTable_wrapper .dataTables_info,
        #acWeavingTable_wrapper .dataTables_paginate {
            text-align: left !important;
            font-size: 11px;
        }
        #acWeavingTable_wrapper .dataTables_filter input {
            width: 100% !important;
            margin-left: 0;
            margin-top: 4px;
            height: 30px;
            font-size: 12px;
        }
        #acWeavingTable_wrapper .dataTables_paginate {
            margin-top: 6px;
            overflow-x: auto;
            white-space: nowrap;
        }
        .ac-action-group .btn {
            padding: 4px 6px;
            font-size: 11px;
        }
        #modalTambahAcWeaving .modal-dialog {
            max-width: none;
            width: 100%;
            height: 100%;
            min-height: 100%;
            margin: 0;
        }
        #modalTambahAcWeaving .modal-content {
            height: 100vh;
            border-radius: 0;
            border: 0;
            display: flex;
            flex-direction: column;
        }
        #modalTambahAcWeaving .modal-header {
            align-items: center;
            min-height: 44px;
            padding: 9px 12px;
        }
        #modalTambahAcWeaving .modal-title {
            max-width: calc(100vw - 56px);
            font-size: 13px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #modalTambahAcWeaving .modal-body {
            flex: 1 1 auto;
            max-height: none;
            overflow-y: auto;
            padding: 10px;
        }
        #modalTambahAcWeaving .form-row {
            margin-left: -5px;
            margin-right: -5px;
        }
        #modalTambahAcWeaving .form-row > [class*="col-"] {
            padding-left: 5px;
            padding-right: 5px;
        }
        #modalTambahAcWeaving .form-group {
            margin-bottom: 8px;
        }
        #modalTambahAcWeaving label {
            margin-bottom: 3px;
            font-size: 11px;
            font-weight: 700;
        }
        #modalTambahAcWeaving .form-control {
            height: 34px;
            font-size: 12px;
            padding: 4px 8px;
        }
        #modalTambahAcWeaving .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 5;
            background: #fff;
            padding: 8px 10px;
            gap: 6px;
            box-shadow: 0 -3px 10px rgba(0,0,0,.08);
        }
        #modalTambahAcWeaving .modal-footer .btn {
            min-width: 74px;
            height: 34px;
            font-size: 12px;
        }
        #modalTambahAcWeaving .select2-container--bootstrap4 .select2-selection--single {
            height: 34px;
        }
        #modalTambahAcWeaving .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            font-size: 12px;
        }
        #modalTambahAcWeaving .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: 32px;
        }
        .ac-input-title-row {
            margin-top: 2px;
            margin-bottom: 5px;
        }
        .ac-input-title-row strong {
            font-size: 12px;
        }
        .ac-weaving-mobile-hint {
            margin-bottom: 6px !important;
            font-size: 11px;
        }
        .ac-weaving-table-wrap {
            max-height: 42vh;
            -webkit-overflow-scrolling: touch;
            border-radius: 4px;
            background: #fff;
        }
        .ac-weaving-input-table {
            min-width: 650px;
            font-size: 11px;
        }
        .ac-weaving-input-table th,
        .ac-weaving-input-table td {
            padding: 2px;
        }
        .ac-weaving-input-table th {
            font-size: 10px !important;
            line-height: 1.15;
        }
        .ac-weaving-input-table input,
        .ac-weaving-input-table select {
            height: 30px;
            min-width: 74px;
            font-size: 11px;
            padding: 3px 5px;
        }
        .ac-weaving-input-table .date-cell { min-width: 82px; }
        .ac-weaving-input-table .time-cell { min-width: 54px; }
        .ac-weaving-mobile-hint {
            display: block;
        }
        #ac_keterangan {
            height: 58px !important;
            min-height: 58px;
        }
    }
</style>

<div class="modal fade" id="modalTambahAcWeaving" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Pengecekan AC Weaving</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahAcWeaving" autocomplete="off">
                <input type="hidden" name="edit_existing" id="ac_edit_existing" value="0">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="ac_mesin">AC</label>
                            <select class="form-control" id="ac_mesin" name="mesin" required>
                                <option value="AC WEAVING 1">AC WEAVING 1</option>
                                <option value="AC WEAVING 2">AC WEAVING 2</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="ac_tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="ac_tanggal" name="tanggal" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="ac_petugas_default">Petugas</label>
                            <select class="form-control select2bs4" id="ac_petugas_default" name="petugas_default" data-placeholder="Cari / pilih petugas">
                                <option value="">Pilih Petugas</option>
                                <?php foreach ($petugasOptions as $petugasName): ?>
                                    <option value="<?= htmlspecialchars($petugasName) ?>"><?= htmlspecialchars($petugasName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="ac_shift_default">Shift</label>
                            <select class="form-control" id="ac_shift_default" name="shift_default">
                                <option value="NON SHIFT">NON SHIFT</option>
                                <option value="PAGI">PAGI</option>
                                <option value="SIANG">SIANG</option>
                                <option value="MALAM">MALAM</option>
                            </select>
                        </div>
                    </div>

                    <div class="ac-input-title-row">
                        <strong id="inputTableTitle">AC WEAVING 1</strong>
                        <?php if ($canEdit): ?>
                            <button type="button" class="btn btn-warning btn-sm d-none" id="btnEditExistingAc">
                                <i class="fas fa-edit"></i> Edit Data Existing
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="ac-weaving-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengisi semua kolom.</div>

                    <div class="ac-weaving-table-wrap">
                        <table class="ac-weaving-input-table">
                            <thead>
                                <tr class="head-row">
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>pB1 Dew<br>Point</th>
                                    <th>Humidity</th>
                                    <th>Amper</th>
                                    <th>Differential<br>Best Air</th>
                                </tr>
                            </thead>
                            <tbody id="acWeavingRows"></tbody>
                        </table>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label for="ac_keterangan">Keterangan</label>
                        <textarea class="form-control" id="ac_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
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

    function initPetugasSelect2() {
        if (!$.fn.select2) return;
        var $select = $('#ac_petugas_default');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Cari / pilih petugas',
            allowClear: true,
            dropdownParent: $('#modalTambahAcWeaving')
        });
    }

    initPetugasSelect2();

    function resetExistingEditMode() {
        $('#ac_edit_existing').val('0');
        $('#btnEditExistingAc').addClass('d-none').prop('disabled', false)
            .html('<i class="fas fa-edit"></i> Edit Data Existing');
    }

    function updateExistingEditButton() {
        if (!appPermissions.canEdit) return;
        $('#btnEditExistingAc').toggleClass('d-none', $('#acWeavingRows input.is-existing-cell').length === 0);
    }

    function enableExistingEditMode() {
        $('#ac_edit_existing').val('1');
        $('#acWeavingRows input.is-existing-cell')
            .prop('readonly', false)
            .removeAttr('tabindex')
            .removeClass('is-existing-cell');
        $('#acWeavingRows td.is-existing-cell').removeClass('is-existing-cell');
        $('#btnEditExistingAc').prop('disabled', true)
            .html('<i class="fas fa-check"></i> Mode Edit Aktif');
    }

    function parseNumericInput(value) {
        if (value === null || value === undefined) return NaN;
        var str = String(value).trim();
        if (str === '') return NaN;
        str = str.replace(/,/g, '');
        var num = Number(str);
        return isNaN(num) ? NaN : num;
    }

    function formatNumberUS(value, decimals) {
        var num = parseNumericInput(value);
        if (isNaN(num)) return '-';
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatDateID(value) {
        if (!value) return '';
        var parts = value.split('-');
        if (parts.length !== 3) return value;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function todayLocal() {
        var now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 10);
    }

    function addDays(dateValue, days) {
        var parts = dateValue.split('-');
        if (parts.length !== 3) return dateValue;
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        date.setDate(date.getDate() + days);
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    function syncTitle() {
        $('#inputTableTitle').text($('#ac_mesin').val() || 'AC WEAVING 1');
    }

    function getFixedJamRows() {
        return [
            { label: '06:30', value: '06:30', dayOffset: 0 },
            { label: '07:30', value: '07:30', dayOffset: 0 },
            { label: '08:30', value: '08:30', dayOffset: 0 },
            { label: '09:30', value: '09:30', dayOffset: 0 },
            { label: '10:30', value: '10:30', dayOffset: 0 },
            { label: '11:30', value: '11:30', dayOffset: 0 },
            { label: '12:30', value: '12:30', dayOffset: 0 },
            { label: '13:30', value: '13:30', dayOffset: 0 },
            { label: '14:30', value: '14:30', dayOffset: 0 },
            { label: '15:30', value: '15:30', dayOffset: 0 },
            { label: '16:30', value: '16:30', dayOffset: 0 },
            { label: '17:30', value: '17:30', dayOffset: 0 },
            { label: '18:30', value: '18:30', dayOffset: 0 },
            { label: '19:30', value: '19:30', dayOffset: 0 },
            { label: '20:30', value: '20:30', dayOffset: 0 },
            { label: '21:30', value: '21:30', dayOffset: 0 },
            { label: '22:30', value: '22:30', dayOffset: 0 },
            { label: '23:30', value: '23:30', dayOffset: 0 },
            { label: '00:30', value: '00:30', dayOffset: 0 },
            { label: '01:30', value: '01:30', dayOffset: 0 },
            { label: '02:30', value: '02:30', dayOffset: 0 },
            { label: '03:30', value: '03:30', dayOffset: 0 },
            { label: '04:30', value: '04:30', dayOffset: 0 },
            { label: '05:30', value: '05:30', dayOffset: 0 }
        ];
    }

    function formatExistingNumber(value, decimals) {
        if (value === null || value === undefined || value === '') return '';
        return formatNumberUS(value, decimals);
    }

    function markExistingRow($row, item) {
        $row.addClass('is-existing').attr('data-existing', '1');
        var fields = [
            { name: 'dew_point', value: item.dew_point, decimals: 2 },
            { name: 'humidity', value: item.humidity, decimals: 1 },
            { name: 'amper', value: item.amper, decimals: 0 },
            { name: 'differential', value: item.differential, decimals: 0 }
        ];
        fields.forEach(function(field) {
            var formatted = formatExistingNumber(field.value, field.decimals);
            var $input = $row.find('[name$="[' + field.name + ']"]');
            $input.val(formatted).attr('data-original', formatted);
            if (formatted !== '') {
                $input.prop('readonly', true).addClass('is-existing-cell');
                $input.closest('td').addClass('is-existing-cell');
            }
        });
    }

    function loadExistingRows() {
        var mesin = $('#ac_mesin').val();
        var tanggal = $('#ac_tanggal').val();
        if (!mesin || !tanggal) return;

        $.ajax({
            url: 'get_ac_weaving_existing.php',
            type: 'GET',
            dataType: 'json',
            data: { mesin: mesin, tanggal: tanggal },
            success: function(resp) {
                if (!resp || !resp.success) return;

                var ket = (resp.keterangan !== undefined && resp.keterangan !== null) ? resp.keterangan : '';
                $('#ac_keterangan').val(ket).attr('data-original', ket);

                if (resp.data) {
                    $('#acWeavingRows tr').each(function() {
                        var $row = $(this);
                        var key = $row.data('tanggal') + '|' + $row.data('jam');
                        if (resp.data[key]) {
                            markExistingRow($row, resp.data[key]);
                        }
                    });
                }
                updateExistingEditButton();
            }
        });
    }

    function renderFixedRows() {
        resetExistingEditMode();
        $('#ac_keterangan').val('').attr('data-original', '');
        var baseDate = $('#ac_tanggal').val() || todayLocal();
        var rows = getFixedJamRows();
        var html = '';

        for (var i = 0; i < rows.length; i++) {
            var rowDate = addDays(baseDate, rows[i].dayOffset);
            html += '<tr data-row-index="' + i + '" data-existing="0" data-tanggal="' + rowDate + '" data-jam="' + rows[i].value + '">';
            html += '<td class="date-cell">' + formatDateID(rowDate) + '<input type="hidden" name="rows[' + i + '][tanggal]" value="' + rowDate + '"></td>';
            html += '<td class="time-cell">' + rows[i].label + '<input type="hidden" name="rows[' + i + '][jam]" value="' + rows[i].value + '"><input type="hidden" name="rows[' + i + '][jam_label]" value="' + rows[i].label + '"><input type="hidden" class="row-aktual" name="rows[' + i + '][aktual_check]" value=""></td>';
            html += '<td><input type="text" class="form-control form-control-sm ac-number row-number" name="rows[' + i + '][dew_point]"></td>';
            html += '<td><input type="text" class="form-control form-control-sm ac-number row-number" name="rows[' + i + '][humidity]"></td>';
            html += '<td><input type="text" class="form-control form-control-sm ac-number row-number" name="rows[' + i + '][amper]"></td>';
            html += '<td><input type="text" class="form-control form-control-sm ac-number row-number" name="rows[' + i + '][differential]"></td>';
            html += '</tr>';
        }

        $('#acWeavingRows').html(html);
        syncTitle();
        updateExistingEditButton();
        loadExistingRows();
    }

    function rowHasMeasurement($row) {
        var hasValue = false;
        $row.find('.row-number').each(function() {
            if ($(this).val().trim() !== '') {
                hasValue = true;
            }
        });
        return hasValue;
    }

    function rowIsModifiedOrNew($row) {
        if (!rowHasMeasurement($row)) {
            return false;
        }
        var isExisting = $row.attr('data-existing') === '1';
        if (!isExisting) {
            return true;
        }
        var changed = false;
        $row.find('.row-number').each(function() {
            var orig = $(this).attr('data-original');
            if (orig === undefined) orig = '';
            var curr = $(this).val().trim();
            var origNum = parseNumericInput(orig);
            var currNum = parseNumericInput(curr);
            if (isNaN(origNum) && !isNaN(currNum)) changed = true;
            else if (!isNaN(origNum) && isNaN(currNum)) changed = true;
            else if (!isNaN(origNum) && !isNaN(currNum) && Math.abs(origNum - currNum) > 0.0001) changed = true;
        });
        return changed;
    }

    function currentTimeValue() {
        var now = new Date();
        return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }

    var table = $('#acWeavingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ac_weaving_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.mesin = $('#filterMesin').val();
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'mesin', render: function(data) { return data || '-'; } },
            { data: 'tanggal_formatted', render: function(data) { return data || '-'; } },
            { data: 'petugas', render: function(data) { return data || '-'; } },
            { data: 'shift', render: function(data) { return data || '-'; } },
            { data: 'created_by', render: function(data) { return data || '-'; } },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id) {
                    if (!id) return '-';
                    var html = '<div class="ac-action-group">';
                    html += '<a class="btn btn-info btn-sm" title="View" href="view_ac_weaving.php?id=' + id + '"><i class="fas fa-eye"></i></a>';
                    if (appPermissions.canEdit) html += '<a class="btn btn-warning btn-sm" title="Edit" href="edit_ac_weaving.php?id=' + id + '"><i class="fas fa-edit"></i></a>';
                    if (appPermissions.canDelete) html += '<button type="button" class="btn btn-danger btn-sm btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    html += '</div>';
                    return html;
                }
            }
        ],
        ordering: false,
        responsive: false,
        scrollX: true,
        autoWidth: false,
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
        }
    });

    $('#filterStartDate, #filterEndDate, #filterMesin').on('change', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate').val('');
        $('#filterEndDate').val('');
        $('#filterMesin').val('');
        table.search('').draw();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahAcWeaving')[0].reset();
        $('#ac_mesin').val('AC WEAVING 1');
        $('#ac_petugas_default').val(null).trigger('change');
        $('#ac_tanggal').val(todayLocal());
        $('#ac_shift_default').val('NON SHIFT');
        renderFixedRows();
        $('#modalTambahAcWeaving').modal('show');
    });

    $('#ac_mesin').on('change input', renderFixedRows);
    $('#ac_tanggal').on('change', renderFixedRows);
    $('#btnEditExistingAc').on('click', enableExistingEditMode);

    $(document).on('input', '.ac-number', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
    });

    $('#formTambahAcWeaving').on('submit', function(e) {
        e.preventDefault();

        var activeRows = 0;
        var hasNewRows = false;
        var invalid = false;
        var validationMsg = '';

        $('#acWeavingRows tr').each(function() {
            var $row = $(this);
            if (!rowIsModifiedOrNew($row)) {
                return;
            }

            activeRows++;
            var isExisting = $row.attr('data-existing') === '1';
            if (!isExisting) {
                hasNewRows = true;
                $row.find('.row-aktual').val(currentTimeValue());
            }

            var $numbers = $row.find('.row-number');
            $numbers.each(function() {
                var value = $(this).val().trim();
                if (value !== '' && isNaN(parseNumericInput(value))) {
                    invalid = true;
                    validationMsg = 'Setiap nilai yang diisi harus berupa angka valid.';
                }
            });
        });

        var origKeterangan = ($('#ac_keterangan').attr('data-original') || '').trim();
        var currKeterangan = $('#ac_keterangan').val().trim();
        var isKeteranganChanged = (origKeterangan !== currKeterangan);

        if (activeRows === 0 && !isKeteranganChanged) {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Tidak ada data baru atau data yang diedit untuk disimpan.' });
            return;
        }

        if (hasNewRows && ($('#ac_petugas_default').val().trim() === '' || $('#ac_shift_default').val() === '')) {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Petugas dan shift wajib dipilih untuk data baru yang ditambahkan.' });
            return;
        }

        if (invalid) {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: validationMsg || 'Data tidak valid.' });
            return;
        }

        // Disable baris yang tidak diubah dan baris kosong agar tidak terkirim saat serialize
        $('#acWeavingRows tr').each(function() {
            var $row = $(this);
            if (!rowIsModifiedOrNew($row)) {
                $row.find('input, select').prop('disabled', true);
            }
        });

        $.ajax({
            url: 'save_ac_weaving.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            complete: function() {
                $('#acWeavingRows input, #acWeavingRows select').prop('disabled', false);
            },
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahAcWeaving').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Data tersimpan.' });
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menyimpan data.' });
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (!id) return;
        Swal.fire({
            title: 'Hapus data?',
            text: 'Seluruh isi log sheet tanggal ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('delete_ac_weaving.php', {id: id}, function(res) {
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

    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ icon: 'success', title: 'Sukses!', text: <?= json_encode($_SESSION['success']) ?>, timer: 3000, showConfirmButton: false });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ icon: 'error', title: 'Gagal!', text: <?= json_encode($_SESSION['error']) ?>, timer: 3000, showConfirmButton: false });
    <?php unset($_SESSION['error']); endif; ?>
});
</script>
