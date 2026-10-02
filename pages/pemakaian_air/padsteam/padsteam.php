<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; // TODO: ganti dengan MenuId padsteam di database
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
        <h1>METER AIR MC PAD STEAM</h1>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Meter Air Pad Steam</h3>
                    <a href="report_padsteam.php" class="btn btn-info btn-sm float-right ml-2" id="btnDetailReport">
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
                    <table id="padsteamTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
                        <thead class="thead-light text-center">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Meter Awal</th>
                                <th>Meter Akhir</th>
                                <th>Total Pemakaian</th>
                                <th>Operasional Mesin / Jam</th>
                                <th>Pemakaian Rata Rata / Jam</th>
                                <th>Created By</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<!-- Modal Tambah Data padsteam -->
<div class="modal fade" id="modalTambahpadsteam" tabindex="-1" role="dialog" aria-labelledby="modalTambahpadsteamLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalTambahpadsteamLabel">Tambah Data Meter Air Pad Steam</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahpadsteam" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="padsteamTanggal">Tanggal</label>
                        <input type="date" class="form-control" id="padsteamTanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="padsteamMeterAwal">Meter Awal</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right padsteam-number-only" id="padsteamMeterAwal" name="meter_awal" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2" required>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnIsiOtomatis">
                                <i class="fas fa-magic mr-1"></i> Isi Otomatis dari Meter Akhir Sebelumnya
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="padsteamMeterAkhir">Meter Akhir</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right padsteam-number-only" id="padsteamMeterAkhir" name="meter_akhir" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2" required>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="padsteamOprMesin">Operasional Mesin / Jam</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right padsteam-number-only" id="padsteamOprMesin" name="oprasional_mesin" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2">
                            <div class="input-group-append">
                                <span class="input-group-text">Jam</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="padsteamTotalPemakaian">Total Pemakaian</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="padsteamTotalPemakaian" name="total_pemakaian" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="padsteamRataJam">Pemakaian Rata Rata / Jam</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="padsteamRataJam" name="pemakaian_rata2perjam" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="padsteamKeterangan">Keterangan</label>
                        <textarea class="form-control" id="padsteamKeterangan" name="keterangan" rows="3" placeholder="Tulis keterangan..."></textarea>
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

<!-- Modal Tambah Catatan Pad Steam -->
<div class="modal fade" id="modalTambahCatatanPadsteam" tabindex="-1" role="dialog" aria-labelledby="modalTambahCatatanPadsteamLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalTambahCatatanPadsteamLabel">Tambah Catatan</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahCatatanPadsteam" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="padsteamCatatanTanggal">Tanggal</label>
                        <input type="date" class="form-control" id="padsteamCatatanTanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="padsteamCatatan">Catatan</label>
                        <textarea class="form-control" id="padsteamCatatan" name="catatan" rows="4" placeholder="Tulis catatan..." required></textarea>
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

<!-- DataTables & SweetAlert -->
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

    var table = $('#padsteamTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'padsteam_serverside.php',
            type: 'POST',
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
            },
            error: function(xhr, error, thrown) {
                let msg = 'Gagal memuat data: ' + (thrown || 'Unknown error');
                try {
                    let json = JSON.parse(xhr.responseText);
                    if (json.error) {
                        msg += '\nSQL Error: ' + JSON.stringify(json.error);
                    }
                } catch (e) {
                    msg += '\nResponse: ' + xhr.responseText;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg
                });
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'tanggal_formatted' },
            { data: 'meter_awal', render: function(data){
                if (data === null || data === '') return '-';
                return formatNumberUS(data, 2) + ' M<sup>3</sup>';
            } },
            { data: 'meter_akhir', render: function(data){
                if (data === null || data === '') return '-';
                return formatNumberUS(data, 2) + ' M<sup>3</sup>';
            } },
            { data: 'total_pemakaian', render: function(data){
                if (data === null || data === '') return '-';
                return formatNumberUS(data, 2) + ' M<sup>3</sup>';
            } },
            { data: 'oprasional_mesin', render: function(data){
                if (data === null || data === '') return '-';
                return formatNumberUS(data, 2);
            } },
            { data: 'pemakaian_rata_rata_jam', render: function(data){
                if (data === null || data === '') return '-';
                return formatNumberUS(data, 2) + ' M<sup>3</sup>';
            } },
            { data: 'created_by' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
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
        $('#formTambahpadsteam')[0].reset();
        $('#modalTambahpadsteam').modal('show');
    });

    $('#btnTambahCatatan').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCatatanPadsteam')[0].reset();
        $('#modalTambahCatatanPadsteam').modal('show');
    });

    function isOffKeterangan() {
        var ket = ($('#padsteamKeterangan').val() || '').trim().toLowerCase();
        return ket === 'off';
    }

    function recalcPadsteam() {
        if (isOffKeterangan()) {
            $('#padsteamOprMesin').val('');
            $('#padsteamRataJam').val('');
            return;
        }
        var awal = parseNumericInput($('#padsteamMeterAwal').val());
        var akhir = parseNumericInput($('#padsteamMeterAkhir').val());
        var opr = parseNumericInput($('#padsteamOprMesin').val());
        if (isNaN(awal) || isNaN(akhir)) {
            $('#padsteamTotalPemakaian').val('');
            $('#padsteamRataJam').val('');
            return;
        }
        var total = akhir - awal;
        $('#padsteamTotalPemakaian').val(formatNumberUS(total, 2));
        if (!isNaN(opr) && opr > 0) {
            $('#padsteamRataJam').val(formatNumberUS(total / opr, 2));
        } else {
            $('#padsteamRataJam').val('');
        }
    }

    // Hanya angka, titik, dan koma
    $(document).on('input', '.padsteam-number-only', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
        recalcPadsteam();
    });
    $(document).on('blur', '.padsteam-number-only', function() {
        var decimals = parseInt($(this).data('decimals') || 4, 10);
        var num = parseNumericInput(this.value);
        if (!isNaN(num)) {
            this.value = formatNumberUS(num, decimals);
        }
        recalcPadsteam();
    });

    $('#padsteamKeterangan').on('input change', function() {
        if (isOffKeterangan()) {
            $('#padsteamOprMesin').val('').prop('disabled', true);
            $('#padsteamRataJam').val('');
        } else {
            $('#padsteamOprMesin').prop('disabled', false);
            recalcPadsteam();
        }
    });

    $('#formTambahpadsteam').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize();
        $.ajax({
            url: 'save_padsteam.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahpadsteam').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Data tersimpan.' });
                    if (table) table.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
                }
            },
            error: function(xhr) {
                var msg = 'Terjadi kesalahan saat menyimpan data.';
                if (xhr.responseText) msg = xhr.responseText;
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    $('#formTambahCatatanPadsteam').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize();
        $.ajax({
            url: 'save_catatan_padsteam.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahCatatanPadsteam').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Catatan tersimpan.' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan catatan.' });
                }
            },
            error: function(xhr) {
                var msg = 'Terjadi kesalahan saat menyimpan catatan.';
                if (xhr.responseText) msg = xhr.responseText;
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    // Isi otomatis meter awal dari meter akhir tanggal sebelumnya
    $('#btnIsiOtomatis').on('click', function() {
        var tanggal = $('#padsteamTanggal').val();
        if (!tanggal) {
            Swal.fire({ icon: 'warning', title: 'Tanggal belum dipilih', text: 'Pilih tanggal dulu.' });
            return;
        }
        $.ajax({
            url: 'get_padsteam_prev.php',
            type: 'GET',
            dataType: 'json',
            data: { tanggal: tanggal },
            success: function(resp) {
                if (resp && resp.success && resp.meter_akhir !== null && resp.meter_akhir !== '') {
                    $('#padsteamMeterAwal').val(formatNumberUS(resp.meter_akhir, 2));
                } else {
                    Swal.fire({ icon: 'info', title: 'Data tidak ditemukan', text: 'Tidak ada data meter akhir sebelumnya untuk tanggal ini.' });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengambil data meter akhir sebelumnya.' });
            }
        });
    });

    // Placeholder handlers (sesuaikan URL sesuai kebutuhan)
    $(document).on('click', '.btn-detail', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'view_padsteam.php?id=' + id;
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'edit_padsteam.php?id=' + id;
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
                window.location.href = 'delete_padsteam.php?id=' + id;
            }
        });
    });
});
</script>
