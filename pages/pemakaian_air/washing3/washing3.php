<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; // TODO: ganti dengan MenuId Washing3 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}
$canEdit = !empty($permissions['CanEdit']) && (int)$permissions['CanEdit'] === 1;
$canDelete = !empty($permissions['CanDelete']) && (int)$permissions['CanDelete'] === 1;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1>Pemakaian Air Washing 3</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/pemakaian_energi.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Washing 3</h3>
                    <a href="report_washing3.php" class="btn btn-info btn-sm float-right ml-2">
                        <i class="fas fa-file-alt"></i> Detail Report
                    </a>
                    <?php if (!empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1): ?>
                        <a href="#" class="btn btn-warning btn-sm float-right ml-2" id="btnTambahCatatan">
                            <i class="fas fa-sticky-note"></i> Tambah Catatan
                        </a>
                        <a href="#" class="btn btn-success btn-sm float-right" id="btnTambahData">
                            <i class="fas fa-plus"></i> Tambah Data
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body table-responsive">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="filterStartDate">Dari Tanggal</label>
                            <input type="date" id="filterStartDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="filterEndDate">Sampai Tanggal</label>
                            <input type="date" id="filterEndDate" class="form-control" autocomplete="off">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter">
                                <i class="fas fa-undo"></i> Reset Filter
                            </button>
                        </div>
                    </div>
                    <table id="washing3Table" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Water Flow (M<sup>3</sup>/h)</th>
                                <th>Operasional Mesin (Jam)</th>
                                <th>Total Pemakaian (M<sup>3</sup>)</th>
                                <th>Keterangan</th>
                                <th>Created By</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<div class="modal fade" id="modalTambahWashing3" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Washing 3</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahWashing3" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="meter_flow">Meter Flow</label>
                        <input type="text" class="form-control text-right w3-number" id="meter_flow" name="meter_flow" data-decimals="2" placeholder="-">
                        <small class="form-text text-muted" id="meter_flow_prev_info">Meter Flow sebelumnya: -</small>
                    </div>
                    <div class="form-group">
                        <label for="water_flow">Water Flow (M<sup>3</sup>/h)</label>
                        <input type="text" class="form-control text-right bg-light" id="water_flow" name="water_flow" data-decimals="2" placeholder="Otomatis dari (Meter Flow hari ini - Meter Flow sebelumnya) / Operasional Mesin" readonly>
                    </div>
                    <div class="form-group">
                        <label for="operasional_mesin">Operasional Mesin (Jam)</label>
                        <input type="text" class="form-control text-right w3-number" id="operasional_mesin" name="operasional_mesin" data-decimals="2" placeholder="-">
                    </div>
                    <div class="form-group">
                        <label for="total_pemakaian">Total Pemakaian (M<sup>3</sup>)</label>
                        <input type="text" class="form-control text-right w3-number" id="total_pemakaian" name="total_pemakaian" data-decimals="2" placeholder="Auto dari Water Flow x Operasional Mesin, bisa diubah manual">
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2" placeholder="Contoh: OFF"></textarea>
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

<div class="modal fade" id="modalTambahCatatanWashing3" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Catatan Washing 3</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahCatatanWashing3" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="catatan_tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="catatan_tanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="catatan_text">Catatan</label>
                        <textarea class="form-control" id="catatan_text" name="catatan" rows="3" placeholder="Catatan tambahan" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Catatan</button>
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
    var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

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

    function formatInputNumber(value, decimals) {
        var num = parseNumericInput(value);
        if (isNaN(num)) return '';
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function normalizeDateValue(raw) {
        var v = (raw || '').toString().trim();
        if (v === '') return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
        var m = v.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
        if (m) return m[3] + '-' + m[2] + '-' + m[1];
        return v;
    }

    var prevMeterFlow = NaN;

    function recalcWaterFlowAndTotal() {
        var meterFlow = parseNumericInput($('#meter_flow').val());
        var operasional = parseNumericInput($('#operasional_mesin').val());
        if (!isNaN(meterFlow) && !isNaN(prevMeterFlow) && !isNaN(operasional) && operasional > 0) {
            var waterFlow = (meterFlow - prevMeterFlow) / operasional;
            $('#water_flow').val(formatInputNumber(waterFlow, 2));
            $('#total_pemakaian').val(formatInputNumber(waterFlow * operasional, 2));
        } else {
            $('#water_flow').val('');
        }
    }

    function fetchPrevMeterFlow() {
        var tanggal = normalizeDateValue($('#tanggal').val());
        if (!tanggal) {
            prevMeterFlow = NaN;
            $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
            recalcWaterFlowAndTotal();
            return;
        }

        $.ajax({
            url: 'get_washing3_prev.php',
            type: 'GET',
            dataType: 'json',
            data: { tanggal: tanggal },
            success: function(resp) {
                if (resp && resp.success && resp.meter_flow !== null && resp.meter_flow !== '') {
                    prevMeterFlow = parseNumericInput(resp.meter_flow);
                    $('#meter_flow_prev_info').text('Meter Flow sebelumnya: ' + formatInputNumber(prevMeterFlow, 2));
                } else {
                    prevMeterFlow = NaN;
                    $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
                }
                recalcWaterFlowAndTotal();
            },
            error: function() {
                prevMeterFlow = NaN;
                $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
                recalcWaterFlowAndTotal();
            }
        });
    }

    function formatNumberInput($el) {
        var decimals = parseInt($el.data('decimals') || 2, 10);
        var num = parseNumericInput($el.val());
        if (!isNaN(num)) {
            $el.val(formatInputNumber(num, decimals));
        }
    }

    var table = $('#washing3Table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'washing3_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'tanggal_formatted' },
            { data: 'water_flow', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'operasional_mesin', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'total_pemakaian', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'keterangan' },
            { data: 'created_by' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id) {
                    if (!id) return '-';
                    var html = '<div class="btn-group btn-group-sm">';
                    html += '<button class="btn btn-info btn-detail" data-id="' + id + '" title="Detail"><i class="fas fa-eye"></i></button>';
                    if (canEdit) html += '<button class="btn btn-warning btn-edit" data-id="' + id + '" title="Edit"><i class="fas fa-edit"></i></button>';
                    if (canDelete) html += '<button class="btn btn-danger btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    html += '</div>';
                    return html;
                }
            }
        ],
        ordering: false,
        responsive: true,
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

    $('#filterStartDate, #filterEndDate').on('change', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterStartDate').val('');
        $('#filterEndDate').val('');
        table.search('').draw();
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahWashing3')[0].reset();
        $('#tanggal').val(new Date().toISOString().slice(0,10));
        prevMeterFlow = NaN;
        $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
        $('#water_flow').val('');
        fetchPrevMeterFlow();
        $('#modalTambahWashing3').modal('show');
    });
    $('#btnTambahCatatan').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCatatanWashing3')[0].reset();
        $('#modalTambahCatatanWashing3').modal('show');
    });

    $(document).on('input', '.w3-number', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
    });
    $(document).on('blur', '.w3-number', function() {
        formatNumberInput($(this));
    });

    $('#tanggal').on('change', function() {
        fetchPrevMeterFlow();
    });
    $('#meter_flow, #operasional_mesin').on('input', recalcWaterFlowAndTotal);

    $('#formTambahWashing3').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_washing3.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahWashing3').modal('hide');
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
    $('#formTambahCatatanWashing3').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_catatan_washing3.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahCatatanWashing3').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Catatan tersimpan.' });
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan catatan.' });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menyimpan catatan.' });
            }
        });
    });

    $(document).on('click', '.btn-detail', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'view_washing3.php?id=' + id;
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'edit_washing3.php?id=' + id;
    });

    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (!id) return;
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = 'delete_washing3.php?id=' + id;
            }
        });
    });
});
</script>
