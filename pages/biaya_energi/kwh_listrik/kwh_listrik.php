<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

// TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}
$canAdd = !empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1;
$canEdit = !empty($permissions['CanEdit']) && (int)$permissions['CanEdit'] === 1;
$canDelete = !empty($permissions['CanDelete']) && (int)$permissions['CanDelete'] === 1;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';

$machines = kwhl_machine_defs();
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1>Pencatatan Ampere dan KWH Listrik</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/gg_app/pages/biaya_energi/biaya_energi.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-bolt mr-1"></i> List Data KWH Listrik</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_kwh_listrik.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if ($canAdd): ?>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data</a>
              <a href="#" class="btn btn-primary btn-sm" id="btnTambahData2"><i class="fas fa-plus"></i> Tambah Data 2 (21T Actom)</a>
              <a href="#" class="btn btn-warning btn-sm" id="btnTambahCatatan"><i class="fas fa-sticky-note"></i> Tambah Catatan</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="card-body table-responsive">
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Dari Tanggal</label>
              <input type="date" id="filterStartDate" class="form-control">
            </div>
            <div class="col-md-3">
              <label>Sampai Tanggal</label>
              <input type="date" id="filterEndDate" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
            </div>
          </div>

          <table id="kwhListrikTable" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Ampere IPAB</th>
                <th>Ampere IPAL</th>
                <th>Total KWH</th>
                <th>Total Biaya (Rp)</th>
                <th>Faktor</th>
                <th>Tarif/KWH</th>
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

<style>
  .kwh-input-table { border-collapse: collapse; width: 100%; min-width: 1180px; }
  .kwh-input-table th, .kwh-input-table td { border: 1px solid #c9ced4; padding: 6px 8px; text-align: center; vertical-align: middle; }
  .kwh-input-table .grp-amp { background: #d9d9d9; font-weight: 700; }
  .kwh-input-table .grp-kwh { background: #ffc000; font-weight: 700; }
  .kwh-input-table .grp-biaya { background: #efefef; font-weight: 700; }
  .kwh-input-table .hdr-machine { background: #fff200; font-weight: 700; }
  .kwh-input-table .row-amp td { background: #f7f7f7; }
  .kwh-input-table .row-kwh td { background: #d9e2f3; }
  .kwh-input-table .row-biaya td { background: #eee6d8; }
  .kwh-input-table .label-col { font-weight: 700; background: #e8e8e8 !important; white-space: nowrap; }
  .kwh-input-table input { height: 32px; min-width: 88px; }
</style>

<div class="modal fade" id="modalTambahKwhListrik" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Ampere dan KWH Listrik</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formKwhListrik" autocomplete="off">
        <div class="modal-body">
          <div class="form-row mb-2">
            <div class="form-group col-md-2 mb-2">
              <label class="font-weight-bold">Tanggal</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-2 mb-2">
              <label class="font-weight-bold">Faktor Konversi</label>
              <input type="text" name="faktor_konversi" id="xFaktor" class="form-control num-only" value="<?= htmlspecialchars((string)kwhl_default_factor()) ?>">
            </div>
            <div class="form-group col-md-2 mb-2">
              <label class="font-weight-bold">Tarif per KWH</label>
              <input type="text" name="tarif_per_kwh" id="xTarif" class="form-control num-only" value="<?= htmlspecialchars((string)kwhl_default_tarif()) ?>">
            </div>
            <div class="form-group col-md-6 mb-2">
              <label class="font-weight-bold">Keterangan</label>
              <input type="text" name="ket" id="xKet" class="form-control" placeholder="Isi keterangan jika diperlukan">
            </div>
          </div>

          <div class="table-responsive">
            <table class="kwh-input-table">
              <thead>
                <tr>
                  <th rowspan="2" style="min-width:110px;">Bagian</th>
                  <th colspan="<?= count($machines) ?>" class="grp-amp">AMPERE (Input Manual)</th>
                </tr>
                <tr>
                  <?php foreach ($machines as $m): ?>
                    <th class="hdr-machine"><?= htmlspecialchars($m['label']) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <tr class="row-amp">
                  <td class="label-col">Ampere</td>
                  <?php foreach ($machines as $m): ?>
                    <td><input type="text" class="form-control form-control-sm num-only amp-input" name="<?= htmlspecialchars($m['amp_key']) ?>" value="0"></td>
                  <?php endforeach; ?>
                </tr>

                <tr>
                  <th colspan="<?= count($machines) + 1 ?>" class="grp-kwh">KWH (Otomatis = Ampere x Faktor x 24 / 1000)</th>
                </tr>
                <tr class="row-kwh">
                  <td class="label-col">KWH</td>
                  <?php foreach ($machines as $code => $m): ?>
                    <td><input type="text" class="form-control form-control-sm" id="xKwh_<?= htmlspecialchars($code) ?>" readonly></td>
                  <?php endforeach; ?>
                </tr>

                <tr>
                  <th colspan="<?= count($machines) + 1 ?>" class="grp-biaya">TOTAL BIAYA (Otomatis = KWH x Tarif/KWH)</th>
                </tr>
                <tr class="row-biaya">
                  <td class="label-col">Biaya</td>
                  <?php foreach ($machines as $code => $m): ?>
                    <td><input type="text" class="form-control form-control-sm" id="xBiaya_<?= htmlspecialchars($code) ?>" readonly></td>
                  <?php endforeach; ?>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row mt-2">
            <div class="col-md-6">
              <div class="alert alert-info py-2 mb-0">Total KWH: <strong id="xTotalKwh">0.000</strong></div>
            </div>
            <div class="col-md-6">
              <div class="alert alert-warning py-2 mb-0">Total Biaya: <strong id="xTotalBiaya">0</strong></div>
            </div>
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

<div class="modal fade" id="modalTambahCatatanKwhListrik" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan KWH Listrik</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanKwhListrik" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal" id="xCatatanTanggal" class="form-control" required>
          </div>
          <div class="form-group mb-0">
            <label>Catatan</label>
            <textarea name="note" id="xCatatanText" class="form-control" rows="4" required></textarea>
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

<div class="modal fade" id="modalTambahKwhListrik21T" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Input Data 21 Ton Actom</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formKwhListrik21T" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label class="font-weight-bold">Tanggal</label>
            <input type="date" name="tanggal_21t" id="x21TTanggal" class="form-control" required>
          </div>
          
          <div class="form-row">
            <div class="form-group col-md-5">
              <label class="font-weight-bold">KWH Hari Ini</label>
              <input type="text" name="kwh_hari_ini" id="x21TKwhHariIni" class="form-control num-only" value="0" required>
            </div>
            <div class="form-group col-md-5">
              <label class="font-weight-bold">KWH Kemarin</label>
              <input type="text" name="kwh_kemarin" id="x21TKwhKemarin" class="form-control num-only" value="0" required>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-info btn-sm w-100" id="btnAmbilDataKemarinOtomatis">
                <i class="fas fa-copy"></i> Ambil Data
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Pemakaian KWH</label>
            <input type="text" name="total_pemakaian" id="x21TTotalPemakaian" class="form-control" readonly style="background-color: #f0f0f0;">
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Tarif per KWH</label>
            <input type="text" name="tarif_per_kwh_21t" id="x21TTarif" class="form-control num-only" value="<?= htmlspecialchars((string)kwhl_default_tarif()) ?>" required>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Biaya</label>
            <input type="text" name="total_biaya_21t" id="x21TTotalBiaya" class="form-control" readonly style="background-color: #f0f0f0;">
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
$(function () {
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;
  var machines = <?= json_encode($machines) ?>;

  function parseNum(v) {
    if (v === null || v === undefined) return NaN;
    var s = String(v).trim().replace(/[^0-9,.\-]/g, '');
    if (s === '') return NaN;
    if (s.indexOf('.') !== -1 && s.indexOf(',') !== -1) s = s.replace(/,/g, '');
    else if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
    var n = Number(s);
    return isNaN(n) ? NaN : n;
  }

  function fmt(v, d) {
    var n = parseNum(v);
    if (isNaN(n)) return '-';
    return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function fmtInput(v, d) {
    var n = parseNum(v);
    if (isNaN(n)) return '';
    return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function getAmpByCode(code) {
    var conf = machines[code];
    if (!conf) return 0;
    var val = parseNum($('[name="' + conf.amp_key + '"]').val());
    return isNaN(val) ? 0 : val;
  }

  function recalcPreview() {
    var factor = parseNum($('#xFaktor').val());
    if (isNaN(factor) || factor <= 0) factor = <?= json_encode((float)kwhl_default_factor()) ?>;
    var tarif = parseNum($('#xTarif').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl_default_tarif()) ?>;

    var totalKwh = 0;
    var totalBiaya = 0;
    Object.keys(machines).forEach(function (code) {
      var amp = getAmpByCode(code);
      var kwh = (amp * factor * 24) / 1000;
      var biaya = kwh * tarif;
      totalKwh += kwh;
      totalBiaya += biaya;
      $('#xKwh_' + code).val(fmtInput(kwh, 3));
      $('#xBiaya_' + code).val(fmtInput(biaya, 0));
    });

    $('#xTotalKwh').text(fmt(totalKwh, 3));
    $('#xTotalBiaya').text(fmt(totalBiaya, 0));
  }

  var table = $('#kwhListrikTable').DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    ajax: {
      url: 'kwh_listrik_serverside.php',
      type: 'POST',
      data: function (d) {
        d.start_date = $('#filterStartDate').val();
        d.end_date = $('#filterEndDate').val();
      }
    },
    columns: [
      { data: null, render: function (d, t, r, m) { return m.row + m.settings._iDisplayStart + 1; } },
      { data: 'tanggal_formatted' },
      { data: 'amp_ipab', render: function (d) { return fmt(d, 2); } },
      { data: 'amp_ipal', render: function (d) { return fmt(d, 2); } },
      { data: 'total_kwh', render: function (d) { return fmt(d, 3); } },
      { data: 'total_biaya', render: function (d) { return fmt(d, 0); } },
      { data: 'faktor_konversi', render: function (d) { return fmt(d, 1); } },
      { data: 'tarif_per_kwh', render: function (d) { return fmt(d, 0); } },
      { data: 'ket', render: function (d) { return d || '-'; } },
      { data: 'created_by', render: function (d) { return d || '-'; } },
      { data: 'id', render: function (id) {
        if (!id) return '-';
        var btns = ['<button class="btn btn-info btn-detail" data-id="' + id + '"><i class="fas fa-eye"></i></button>'];
        if (canEdit) btns.push('<button class="btn btn-warning btn-edit" data-id="' + id + '"><i class="fas fa-edit"></i></button>');
        if (canDelete) btns.push('<button class="btn btn-danger btn-delete" data-id="' + id + '"><i class="fas fa-trash"></i></button>');
        return '<div class="btn-group btn-group-sm">' + btns.join('') + '</div>';
      }}
    ]
  });

  $('#filterStartDate,#filterEndDate').on('change', function () { table.ajax.reload(); });
  $('#btnResetFilter').on('click', function () {
    $('#filterStartDate,#filterEndDate').val('');
    table.search('').draw();
  });

  $('#btnTambahData').on('click', function (e) {
    e.preventDefault();
    $('#formKwhListrik')[0].reset();
    $('#xTanggal').val(new Date().toISOString().slice(0, 10));
    $('.amp-input').val('0');
    $('#xFaktor').val(<?= json_encode((string)kwhl_default_factor()) ?>);
    $('#xTarif').val(<?= json_encode((string)kwhl_default_tarif()) ?>);
    recalcPreview();
    $('#modalTambahKwhListrik').modal('show');
  });

  $('#btnTambahData2').on('click', function (e) {
    e.preventDefault();
    $('#formKwhListrik21T')[0].reset();
    $('#x21TTanggal').val(new Date().toISOString().slice(0, 10));
    $('#x21TKwhHariIni').val('0');
    $('#x21TKwhKemarin').val('0');
    $('#x21TTarif').val(<?= json_encode((string)kwhl_default_tarif()) ?>);
    calculat21TPemakaian();
    $('#modalTambahKwhListrik21T').modal('show');
  });

  function calculat21TPemakaian() {
    var kwhHariIni = parseNum($('#x21TKwhHariIni').val());
    if (isNaN(kwhHariIni)) kwhHariIni = 0;
    var kwhKemarin = parseNum($('#x21TKwhKemarin').val());
    if (isNaN(kwhKemarin)) kwhKemarin = 0;

    // Total Pemakaian = KWH Hari Ini - KWH Kemarin
    var totalPemakaian = kwhHariIni - kwhKemarin;
    if (totalPemakaian < 0) totalPemakaian = 0;

    $('#x21TTotalPemakaian').val(fmtInput(totalPemakaian, 3));

    var tarif = parseNum($('#x21TTarif').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl_default_tarif()) ?>;

    var totalBiaya = totalPemakaian * tarif;
    $('#x21TTotalBiaya').val(fmtInput(totalBiaya, 0));
  }

  $(document).on('input change', '#x21TKwhHariIni, #x21TKwhKemarin, #x21TTarif', function () {
    calculat21TPemakaian();
  });

  $('#btnAmbilDataKemarinOtomatis').on('click', function (e) {
    e.preventDefault();
    var tanggal = $('#x21TTanggal').val();
    if (!tanggal) {
      Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih tanggal terlebih dahulu.' });
      return;
    }

    // Hitung tanggal kemarin dengan kalender lokal (hindari pergeseran timezone)
    function formatDateYmdLocal(d) {
      var y = d.getFullYear();
      var m = String(d.getMonth() + 1).padStart(2, '0');
      var day = String(d.getDate()).padStart(2, '0');
      return y + '-' + m + '-' + day;
    }
    var parts = tanggal.split('-');
    var tglObj = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    tglObj.setDate(tglObj.getDate() - 1);
    var tglKemarinStr = formatDateYmdLocal(tglObj);

    // Ambil data kwh_hari_ini dari tanggal kemarin
    $.ajax({
      url: 'get_kwh_21t_kemarin.php',
      type: 'POST',
      data: { tanggal: tglKemarinStr },
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success && resp.data) {
          var kwhKemarin = resp.data.kwh_hari_ini_21t || 0;
          $('#x21TKwhKemarin').val(kwhKemarin);
          calculat21TPemakaian();
          Swal.fire({ 
            icon: 'success', 
            title: 'Berhasil', 
            text: 'Data KWH kemarin berhasil diambil dari tanggal ' + tglKemarinStr 
          });
        } else {
          Swal.fire({ 
            icon: 'info', 
            title: 'Informasi', 
            text: 'Data KWH kemarin (tanggal ' + tglKemarinStr + ') tidak ditemukan.' 
          });
        }
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengambil data.' });
      }
    });
  });

  $('#btnTambahCatatan').on('click', function (e) {
    e.preventDefault();
    $('#formCatatanKwhListrik')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0, 10));
    $('#modalTambahCatatanKwhListrik').modal('show');
  });

  $(document).on('input change', '#x21TKwhHariIni, #x21TKwhKemarin, #x21TTarif', function () {
    calculat21TPemakaian();
  });

  $(document).on('input', '.num-only', function () {
    this.value = this.value.replace(/[^0-9.,\-]/g, '');
  });

  $(document).on('input change', '.amp-input, #xFaktor, #xTarif', function () {
    recalcPreview();
  });

  $('#formKwhListrik').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_kwh_listrik.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahKwhListrik').modal('hide');
          Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Tersimpan.' });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
        }
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan.' });
      }
    });
  });

  $('#formCatatanKwhListrik').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_catatan_kwh_listrik.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahCatatanKwhListrik').modal('hide');
          Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Catatan tersimpan.' });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan catatan.' });
        }
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menyimpan catatan.' });
      }
    });
  });

  $('#formKwhListrik21T').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_kwh_listrik_21t.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahKwhListrik21T').modal('hide');
          Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Data tersimpan.' });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
        }
      },
      error: function (xhr) {
        var errorMsg = 'Terjadi kesalahan saat menyimpan data.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }
        console.log('Error Response:', xhr.responseText);
        Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
      }
    });
  });

  $(document).on('click', '.btn-detail', function () {
    var id = $(this).data('id');
    if (id) window.location.href = 'view_kwh_listrik.php?id=' + encodeURIComponent(id);
  });
  $(document).on('click', '.btn-edit', function () {
    var id = $(this).data('id');
    if (id) window.location.href = 'edit_kwh_listrik.php?id=' + encodeURIComponent(id);
  });
  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    if (!id) return;
    Swal.fire({
      title: 'Hapus Data?',
      text: 'Data akan dihapus permanen.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then(function (r) {
      if (r.isConfirmed) {
        window.location.href = 'delete_kwh_listrik.php?id=' + encodeURIComponent(id);
      }
    });
  });

  recalcPreview();
});
</script>
