<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; // TODO: ganti dengan MenuId washing di database
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
        <h1>Pemakaian Air Washing</h1>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Washing</h3>
                    <a href="report_washing.php" class="btn btn-info btn-sm float-right ml-2">
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
                    <table id="washingTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Meter Awal</th>
                                <th>Meter Akhir</th>
                                <th>Total Pemakaian</th>
                                <th>Operasional Mesin</th>
                                <th>Pemakaian Rata-Rata</th>
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

<div class="modal fade" id="modalTambahwashing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Data Washing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahwashing" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="meter_awal">Meter Awal (M<sup>3</sup>)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right washing-number" id="meter_awal" name="meter_awal" data-decimals="2" placeholder="-" required>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnIsiOtomatisWashing">
                                <i class="fas fa-magic mr-1"></i> Isi Otomatis dari Meter Akhir Sebelumnya
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="meter_akhir">Meter Akhir (M<sup>3</sup>)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right washing-number" id="meter_akhir" name="meter_akhir" data-decimals="2" placeholder="-" required>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="operasional_mesin">Operasional Mesin (Jam)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right washing-number" id="operasional_mesin" name="operasional_mesin" data-decimals="2" placeholder="-">
                            <div class="input-group-append">
                                <span class="input-group-text">Jam</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="total_pemakaian">Total Pemakaian (M<sup>3</sup>)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="total_pemakaian" name="total_pemakaian" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pemakaian_rata2perjam">Pemakaian Rata2 / Jam (M<sup>3</sup>)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="pemakaian_rata2perjam" name="pemakaian_rata2perjam" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
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

<div class="modal fade" id="modalTambahCatatanwashing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Catatan Washing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahCatatanwashing" autocomplete="off">
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
        if (isNaN(num)) return '';
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function isOffKeterangan() {
        var ket = ($('#keterangan').val() || '').trim().toLowerCase();
        return ket === 'off';
    }

    function recalcTotals() {
        if (isOffKeterangan()) {
            $('#operasional_mesin').val('').prop('disabled', true);
            $('#total_pemakaian').val('');
            $('#pemakaian_rata2perjam').val('');
            return;
        }

        $('#operasional_mesin').prop('disabled', false);
        var awal = parseNumericInput($('#meter_awal').val());
        var akhir = parseNumericInput($('#meter_akhir').val());
        var operasional = parseNumericInput($('#operasional_mesin').val());
        if (!isNaN(awal) && !isNaN(akhir)) {
            var total = akhir - awal;
            $('#total_pemakaian').val(formatNumberUS(total, 2));
            if (!isNaN(operasional) && operasional > 0) {
                var rata = total / operasional;
                $('#pemakaian_rata2perjam').val(formatNumberUS(rata, 2));
            } else {
                $('#pemakaian_rata2perjam').val('');
            }
        } else {
            $('#total_pemakaian').val('');
            $('#pemakaian_rata2perjam').val('');
        }
    }

    function setMeterAwalFromPrev(tanggal, showMessage) {
        if (!tanggal) {
            if (showMessage) {
                Swal.fire({ icon: 'warning', title: 'Tanggal belum dipilih', text: 'Pilih tanggal dulu.' });
            }
            return;
        }
        $.ajax({
            url: 'get_washing_prev.php',
            type: 'GET',
            dataType: 'json',
            data: { tanggal: tanggal },
            success: function(resp) {
                if (resp && resp.success && resp.meter_akhir !== null && resp.meter_akhir !== undefined) {
                    $('#meter_awal').val(formatNumberUS(resp.meter_akhir, 2));
                    recalcTotals();
                } else if (showMessage) {
                    Swal.fire({ icon: 'info', title: 'Data tidak ditemukan', text: 'Tidak ada data meter akhir sebelumnya untuk tanggal ini.' });
                }
            },
            error: function() {
                if (showMessage) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengambil data meter akhir sebelumnya.' });
                }
            }
        });
    }

    var table = $('#washingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'washing_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'tanggal_formatted' },
            { data: 'meter_awal', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'meter_akhir', render: function(data){ return (data === null || data === '') ? '-' : formatNumberUS(data, 2); } },
            { data: 'total_pemakaian', render: function(data, type, row){
                var isOff = ((row.keterangan || '').trim().toLowerCase() === 'off');
                if (isOff) return '-';
                return (data === null || data === '') ? '-' : formatNumberUS(data, 2);
            } },
            { data: 'operasional_mesin', render: function(data, type, row){
                var isOff = ((row.keterangan || '').trim().toLowerCase() === 'off');
                if (isOff) return '-';
                return (data === null || data === '') ? '-' : formatNumberUS(data, 2);
            } },
            { data: 'pemakaian_rata2perjam', render: function(data, type, row){
                var isOff = ((row.keterangan || '').trim().toLowerCase() === 'off');
                if (isOff) return '-';
                return (data === null || data === '') ? '-' : formatNumberUS(data, 2);
            } },
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
        $('#formTambahwashing')[0].reset();
        $('#modalTambahwashing').modal('show');
    });
    $('#btnTambahCatatan').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCatatanwashing')[0].reset();
        $('#modalTambahCatatanwashing').modal('show');
    });

    $(document).on('input', '.washing-number', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
        recalcTotals();
    });
    $('#keterangan').on('input change', recalcTotals);

    $('#btnIsiOtomatisWashing').on('click', function() {
        setMeterAwalFromPrev($('#tanggal').val(), true);
    });

    $('#formTambahwashing').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_washing.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahwashing').modal('hide');
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
    $('#formTambahCatatanwashing').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'save_catatan_washing.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahCatatanwashing').modal('hide');
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
        window.location.href = 'view_washing.php?id=' + id;
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'edit_washing.php?id=' + id;
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
                window.location.href = 'delete_washing.php?id=' + id;
            }
        });
    });
});
</script>
