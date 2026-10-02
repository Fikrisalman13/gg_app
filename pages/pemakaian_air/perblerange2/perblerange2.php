<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
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
        <h1>METER AIR MC PERBLE RANGE 2</h1>
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
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Data Meter Air Perble Range 2</h3>
                    <a href="report_perblerange2.php" class="btn btn-info btn-sm float-right ml-2" id="btnDetailReport">
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
                    <table id="perblerange2Table" class="table table-hover table-sm nowrap text-center" style="width:100%">
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

<!-- Modal Tambah Data perblerange2 -->
<div class="modal fade" id="modalTambahperblerange2" tabindex="-1" role="dialog" aria-labelledby="modalTambahperblerange2Label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalTambahperblerange2Label">Tambah Data Meter Air Perble Range 2</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahperblerange2" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="perblerange2Tanggal">Tanggal</label>
                        <input type="date" class="form-control" id="perblerange2Tanggal" name="tanggal" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="perblerange2Pbr1">PBR1</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right perblerange2-number-only" id="perblerange2Pbr1" name="pbr1" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="perblerange2Pbr2">PBR2</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right perblerange2-number-only" id="perblerange2Pbr2" name="pbr2" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2MeterAwal">Meter Awal (PBR1 + PBR2)</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="perblerange2MeterAwal" name="meter_awal" placeholder="-" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="d-block mb-1">Mode Meter Akhir</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="perblerange2MeterAkhirModeOtomatis" name="meter_akhir_mode" value="otomatis" class="custom-control-input" checked>
                            <label class="custom-control-label" for="perblerange2MeterAkhirModeOtomatis">Otomatis (H+1)</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="perblerange2MeterAkhirModeManual" name="meter_akhir_mode" value="manual" class="custom-control-input">
                            <label class="custom-control-label" for="perblerange2MeterAkhirModeManual">Manual</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2MeterAkhir">Meter Akhir</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right perblerange2-number-only bg-light" id="perblerange2MeterAkhir" name="meter_akhir" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                        <small class="form-text text-muted" id="perblerange2MeterAkhirHint">Mode otomatis: diambil dari nilai meter awal tanggal berikutnya (H+1).</small>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2OprMesin">Operasional Mesin / Jam</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right perblerange2-number-only" id="perblerange2OprMesin" name="oprasional_mesin" inputmode="decimal" pattern="[0-9.,]*" placeholder="-" data-decimals="2">
                            <div class="input-group-append">
                                <span class="input-group-text">Jam</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2TotalPemakaian">Total Pemakaian</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="perblerange2TotalPemakaian" name="total_pemakaian" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2RataJam">Pemakaian Rata Rata / Jam</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-right" id="perblerange2RataJam" name="pemakaian_rata2perjam" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">M<sup>3</sup></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2Keterangan">Keterangan</label>
                        <textarea class="form-control" id="perblerange2Keterangan" name="keterangan" rows="3" placeholder="Tulis keterangan..."></textarea>
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

<!-- Modal Tambah Catatan Perble Range 2 -->
<div class="modal fade" id="modalTambahCatatanperblerange2" tabindex="-1" role="dialog" aria-labelledby="modalTambahCatatanperblerange2Label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalTambahCatatanperblerange2Label">Tambah Catatan</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formTambahCatatanperblerange2" autocomplete="off">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="perblerange2CatatanTanggal">Tanggal</label>
                        <input type="date" class="form-control" id="perblerange2CatatanTanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label for="perblerange2Catatan">Catatan</label>
                        <textarea class="form-control" id="perblerange2Catatan" name="catatan" rows="4" placeholder="Tulis catatan..." required></textarea>
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

    function normalizeDateValue(raw) {
        var v = (raw || '').toString().trim();
        if (v === '') return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
        var m = v.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
        if (m) return m[3] + '-' + m[2] + '-' + m[1];
        return v;
    }

    function getMeterAkhirMode() {
        return $('input[name="meter_akhir_mode"]:checked').val() || 'otomatis';
    }

    function syncMeterAkhirMode() {
        var mode = getMeterAkhirMode();
        var isManual = (mode === 'manual');
        var $meterAkhir = $('#perblerange2MeterAkhir');
        $meterAkhir.prop('readonly', !isManual);
        $meterAkhir.prop('required', isManual);
        $meterAkhir.toggleClass('bg-light', !isManual);
        $('#perblerange2MeterAkhirHint').text(
            isManual
                ? 'Mode manual: isi meter akhir secara manual.'
                : 'Mode otomatis: diambil dari nilai meter awal tanggal berikutnya (H+1).'
        );
        if (!isManual) {
            fetchMeterAkhirFromNext();
        } else {
            recalcperblerange2();
        }
    }

    function updateMeterAwalFromPbr() {
        var pbr1 = parseNumericInput($('#perblerange2Pbr1').val());
        var pbr2 = parseNumericInput($('#perblerange2Pbr2').val());
        if (isNaN(pbr1) && isNaN(pbr2)) {
            $('#perblerange2MeterAwal').val('');
            return;
        }
        if (isNaN(pbr1)) pbr1 = 0;
        if (isNaN(pbr2)) pbr2 = 0;
        var sum = pbr1 + pbr2;
        $('#perblerange2MeterAwal').val(formatNumberUS(sum, 2));
    }

    function fetchMeterAkhirFromNext() {
        if (getMeterAkhirMode() !== 'otomatis') {
            recalcperblerange2();
            return;
        }
        var tanggal = normalizeDateValue($('#perblerange2Tanggal').val());
        if (!tanggal) {
            $('#perblerange2MeterAkhir').val('');
            recalcperblerange2();
            return;
        }
        $.ajax({
            url: 'get_perblerange2_next.php',
            type: 'GET',
            dataType: 'json',
            data: { tanggal: tanggal },
            success: function(resp) {
                if (getMeterAkhirMode() !== 'otomatis') return;
                if (resp && resp.success && resp.meter_awal !== null && resp.meter_awal !== '') {
                    $('#perblerange2MeterAkhir').val(formatNumberUS(resp.meter_awal, 2));
                } else {
                    $('#perblerange2MeterAkhir').val('');
                }
                recalcperblerange2();
            },
            error: function() {
                if (getMeterAkhirMode() !== 'otomatis') return;
                $('#perblerange2MeterAkhir').val('');
                recalcperblerange2();
            }
        });
    }

    var table = $('#perblerange2Table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'perblerange2_serverside.php',
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
        $('#formTambahperblerange2')[0].reset();
        $('#perblerange2Tanggal').val(new Date().toISOString().slice(0,10));
        $('#perblerange2MeterAkhirModeOtomatis').prop('checked', true);
        updateMeterAwalFromPbr();
        syncMeterAkhirMode();
        $('#modalTambahperblerange2').modal('show');
    });

    $('#btnTambahCatatan').on('click', function(e) {
        e.preventDefault();
        $('#formTambahCatatanperblerange2')[0].reset();
        $('#modalTambahCatatanperblerange2').modal('show');
    });

    function isOffKeterangan() {
        var ket = ($('#perblerange2Keterangan').val() || '').trim().toLowerCase();
        return ket === 'off';
    }

    function recalcperblerange2() {
        updateMeterAwalFromPbr();
        if (isOffKeterangan()) {
            $('#perblerange2OprMesin').val('');
            $('#perblerange2RataJam').val('');
            return;
        }
        var awal = parseNumericInput($('#perblerange2MeterAwal').val());
        var akhir = parseNumericInput($('#perblerange2MeterAkhir').val());
        var opr = parseNumericInput($('#perblerange2OprMesin').val());
        if (isNaN(awal) || isNaN(akhir)) {
            $('#perblerange2TotalPemakaian').val('');
            $('#perblerange2RataJam').val('');
            return;
        }
        var total = akhir - awal;
        $('#perblerange2TotalPemakaian').val(formatNumberUS(total, 2));
        if (!isNaN(opr) && opr > 0) {
            $('#perblerange2RataJam').val(formatNumberUS(total / opr, 2));
        } else {
            $('#perblerange2RataJam').val('');
        }
    }

    // Hanya angka, titik, dan koma
    $(document).on('input', '.perblerange2-number-only', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
        recalcperblerange2();
    });
    $(document).on('blur', '.perblerange2-number-only', function() {
        var decimals = parseInt($(this).data('decimals') || 4, 10);
        var num = parseNumericInput(this.value);
        if (!isNaN(num)) {
            this.value = formatNumberUS(num, decimals);
        }
        recalcperblerange2();
    });

    $('#perblerange2Keterangan').on('input change', function() {
        if (isOffKeterangan()) {
            $('#perblerange2OprMesin').val('').prop('disabled', true);
            $('#perblerange2RataJam').val('');
        } else {
            $('#perblerange2OprMesin').prop('disabled', false);
            recalcperblerange2();
        }
    });

    $('#perblerange2Tanggal').on('change', function() {
        if (getMeterAkhirMode() === 'otomatis') {
            fetchMeterAkhirFromNext();
        } else {
            recalcperblerange2();
        }
    });

    $('input[name="meter_akhir_mode"]').on('change', function() {
        syncMeterAkhirMode();
    });

    $('#formTambahperblerange2').on('submit', function(e) {
        e.preventDefault();
        if ($('#perblerange2MeterAwal').val().trim() === '') {
            Swal.fire({ icon: 'warning', title: 'Validasi', text: 'PBR1 dan PBR2 wajib diisi untuk menghitung Meter Awal.' });
            return;
        }
        if (getMeterAkhirMode() === 'manual') {
            var meterAkhirManual = parseNumericInput($('#perblerange2MeterAkhir').val());
            if (isNaN(meterAkhirManual)) {
                Swal.fire({ icon: 'warning', title: 'Validasi', text: 'Meter Akhir wajib diisi jika mode Manual dipilih.' });
                return;
            }
        }
        var $form = $(this);
        var formData = $form.serialize();
        $.ajax({
            url: 'save_perblerange2.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahperblerange2').modal('hide');
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

    $('#formTambahCatatanperblerange2').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize();
        $.ajax({
            url: 'save_catatan_perblerange2.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    $('#modalTambahCatatanperblerange2').modal('hide');
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

    // Placeholder handlers (sesuaikan URL sesuai kebutuhan)
    $(document).on('click', '.btn-detail', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'view_perblerange2.php?id=' + id;
    });

    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        if (!id) return;
        window.location.href = 'edit_perblerange2.php?id=' + id;
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
                window.location.href = 'delete_perblerange2.php?id=' + id;
            }
        });
    });

    syncMeterAkhirMode();
});
</script>

