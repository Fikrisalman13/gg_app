<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
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
                    <h1>METER AIR LAB</h1>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Meter Air LAB</h3>
                    <a href="report_lab.php" class="btn btn-info btn-sm float-right ml-2">
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
                    <table id="labTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Awal (M<sup>3</sup>)</th>
                                <th>Akhir (M<sup>3</sup>)</th>
                                <th>Pemakaian (M<sup>3</sup>)</th>
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

<div class="modal fade" id="modalTambahLab" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Meter Air LAB</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahLab" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="lab_tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="lab_tanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="lab_meter_awal">Awal (M<sup>3</sup>)</label>
                        <input type="text" class="form-control text-right lab-number" id="lab_meter_awal" name="meter_awal" data-decimals="3" required>
                        <small class="form-text text-muted">Tips: klik tombol di bawah untuk isi data kolom AWAL</small>
                    </div>
                    <div class="form-group text-right mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAmbilPrevAkhir">
                            <i class="fas fa-history mr-1"></i> Ambil Awal = Akhir Sebelumnya
                        </button>
                    </div>
                    <div class="form-group">
                        <label for="lab_meter_akhir">Akhir (M<sup>3</sup>)</label>
                        <input type="text" class="form-control text-right lab-number" id="lab_meter_akhir" name="meter_akhir" data-decimals="3" required>
                    </div>
                    <div class="form-group">
                        <label for="lab_total_pemakaian">Pemakaian (M<sup>3</sup>)</label>
                        <input type="text" class="form-control text-right bg-light" id="lab_total_pemakaian" name="total_pemakaian" data-decimals="2" readonly>
                    </div>
                    <div class="form-group">
                        <label for="lab_keterangan">Keterangan</label>
                        <textarea class="form-control" id="lab_keterangan" name="keterangan" rows="2" placeholder="Opsional"></textarea>
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

<div class="modal fade" id="modalTambahCatatanLab" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Catatan LAB</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahCatatanLab" autocomplete="off">
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

    function recalcPemakaian() {
        var awal = parseNumericInput($('#lab_meter_awal').val());
        var akhir = parseNumericInput($('#lab_meter_akhir').val());
        if (isNaN(awal) || isNaN(akhir)) {
            $('#lab_total_pemakaian').val('');
            return;
        }
        $('#lab_total_pemakaian').val(formatInputNumber((akhir - awal), 2));
    }

    function fetchPrevAkhirForAwal() {
        var tanggal = normalizeDateValue($('#lab_tanggal').val());
        if (!tanggal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih tanggal terlebih dahulu.' });
            return;
        }

        $.ajax({
            url: 'get_lab_prev.php',
            type: 'GET',
            dataType: 'json',
            data: { tanggal: tanggal },
            success: function(resp) {
                if (resp && resp.success && resp.meter_akhir !== null && resp.meter_akhir !== '') {
                    $('#lab_meter_awal').val(formatInputNumber(resp.meter_akhir, 3));
                    recalcPemakaian();
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Meter awal diambil dari meter akhir hari sebelumnya.' });
                } else {
                    Swal.fire({ icon: 'info', title: 'Info', text: (resp && resp.message) ? resp.message : 'Data meter akhir sebelumnya tidak ditemukan.' });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat mengambil data sebelumnya.' });
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

    var table = $('#labTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'lab_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'tanggal_formatted' },
            { data: 'meter_awal', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 3); } },
            { data: 'meter_akhir', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 3); } },
            { data: 'total_pemakaian', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'keterangan', render: function(data){ return data || '-'; } },
            { data: 'created_by', render: function(data){ return data || '-'; } },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id) {
                    if (!id) return '-';
                    var html = '<div class="btn-group btn-group-sm">';
                    html += '<button class="btn btn-info btn-detail" data-id="' + id + '" title="Detail"><i class="fas fa-eye"></i></button>';
                    if (canEdit) {
                        html += '<button class="btn btn-warning btn-edit" data-id="' + id + '" title="Edit"><i class="fas fa-edit"></i></button>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
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
        $('#formTambahLab')[0].reset();
        $('#lab_tanggal').val(new Date().toISOString().slice(0, 10));
        $('#lab_total_pemakaian').val('');
        $('#modalTambahLab').modal('show');
    });

    $('#btnAmbilPrevAkhir').on('click', function() {
        fetchPrevAkhirForAwal();
    });

    $('#btnTambahCatatan').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCatatanLab')[0].reset();
        $('#catatan_tanggal').val(new Date().toISOString().slice(0, 10));
        $('#modalTambahCatatanLab').modal('show');
    });

    $(document).on('input', '.lab-number', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
        recalcPemakaian();
    });

    $(document).on('blur', '.lab-number', function() {
        formatNumberInput($(this));
        recalcPemakaian();
    });

    $('#formTambahLab').on('submit', function(e) {
        e.preventDefault();

        var awal = parseNumericInput($('#lab_meter_awal').val());
        var akhir = parseNumericInput($('#lab_meter_akhir').val());
        if ($('#lab_tanggal').val() === '' || isNaN(awal) || isNaN(akhir)) {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Tanggal, meter awal, dan meter akhir wajib diisi.' });
            return;
        }
        if (akhir < awal) {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Meter akhir tidak boleh lebih kecil dari meter awal.' });
            return;
        }

        $.ajax({
            url: 'save_lab.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahLab').modal('hide');
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

    $('#formTambahCatatanLab').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_catatan_lab.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahCatatanLab').modal('hide');
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
        window.location.href = 'view_lab.php?id=' + id;
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'edit_lab.php?id=' + id;
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
                window.location.href = 'delete_lab.php?id=' + id;
            }
        });
    });
});
</script>
