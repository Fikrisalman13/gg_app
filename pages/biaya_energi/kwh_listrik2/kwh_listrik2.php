<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik 2 jika sudah tersedia
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

$machines = kwhl2_machine_defs();
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1>Pencatatan KWH Listrik 2</h1>
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
          <h3 class="card-title"><i class="fas fa-bolt mr-1"></i> List Data KWH Listrik 2</h3>
          <div class="d-flex ml-auto" style="gap:8px;">
            <a href="report_kwh_listrik2.php" class="btn btn-info btn-sm"><i class="fas fa-file-alt"></i> Detail Report</a>
            <?php if ($canAdd): ?>
              <a href="#" class="btn btn-success btn-sm" id="btnTambahData"><i class="fas fa-plus"></i> Tambah Data (Semua Mesin)</a>
              <a href="#" class="btn btn-primary btn-sm" id="btnTambahDataSingle"><i class="fas fa-edit"></i> Input Per Mesin</a>
              <a href="#" class="btn btn-warning btn-sm" id="btnTambahCatatan"><i class="fas fa-sticky-note"></i> Tambah Catatan</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="card-body table-responsive">
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Dari Tanggal</label>
              <input type="date" id="filterStartDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label>Sampai Tanggal</label>
              <input type="date" id="filterEndDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter"><i class="fas fa-undo"></i> Reset Filter</button>
            </div>
          </div>

          <table id="kwhListrik2Table" class="table table-hover table-sm nowrap text-center" style="width:100%">
            <thead class="thead-light">
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Total KWH (kWh)</th>
                <th>Total Biaya (Rp)</th>
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
  .kwh2-input-table { border-collapse: collapse; width: 100%; min-width: 780px; }
  .kwh2-input-table th, .kwh2-input-table td { border: 1px solid #c9ced4; padding: 6px 8px; text-align: center; vertical-align: middle; }
  .kwh2-input-table .hdr-sec { background: #ffc000; font-weight: 700; color: #000; }
  .kwh2-input-table .hdr-biaya { background: #efefef; font-weight: 700; }
  .kwh2-input-table .label-col { font-weight: 700; background: #f4f6f9; text-align: left; padding-left: 12px; }
  .kwh2-input-table input { height: 32px; min-width: 90px; }
</style>

<!-- MODAL TAMBAH DATA (SEMUA MESIN) -->
<div class="modal fade" id="modalTambahKwhListrik2" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title"><i class="fas fa-bolt mr-1"></i> Input Data KWH Listrik 2 (Semua Mesin)</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formKwhListrik2" autocomplete="off">
        <div class="modal-body">
          <div class="form-row mb-3">
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tanggal</label>
              <input type="date" name="tanggal" id="xTanggal" class="form-control" required>
            </div>
            <div class="form-group col-md-3 mb-2">
              <label class="font-weight-bold">Tarif per KWH (Rp)</label>
              <input type="text" name="tarif_per_kwh" id="xTarif" class="form-control num-only" value="<?= htmlspecialchars((string)kwhl2_default_tarif()) ?>" required>
            </div>
            <div class="form-group col-md-4 mb-2">
              <label class="font-weight-bold">Keterangan</label>
              <input type="text" name="ket" id="xKet" class="form-control" placeholder="Isi keterangan jika diperlukan">
            </div>
            <div class="form-group col-md-2 mb-2 d-flex align-items-end">
              <button type="button" class="btn btn-info btn-sm w-100" id="btnAmbilSemuaKemarin" title="Ambil angka meter Hari Ini dari tanggal kemarin">
                <i class="fas fa-copy mr-1"></i> Ambil Kemarin
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="kwh2-input-table">
              <thead>
                <tr>
                  <th style="width:170px;">Item / Bagian</th>
                  <th style="background:#e8f4f8;">KWH Hari Ini (Meter)</th>
                  <th style="background:#fce8e6;">KWH Kemarin (Meter)</th>
                  <th class="hdr-sec">Pemakaian KWH (Auto)</th>
                  <th class="hdr-biaya">Total Biaya Rp (Auto)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($machines as $code => $m): ?>
                  <tr>
                    <td class="label-col"><?= htmlspecialchars($m['label']) ?></td>
                    <td>
                      <input type="text" class="form-control form-control-sm num-only kwh-hi-all" name="<?= htmlspecialchars($m['hi_key']) ?>" id="xHiAll_<?= htmlspecialchars($code) ?>" data-code="<?= htmlspecialchars($code) ?>" value="0">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm num-only kwh-km-all" name="<?= htmlspecialchars($m['km_key']) ?>" id="xKmAll_<?= htmlspecialchars($code) ?>" data-code="<?= htmlspecialchars($code) ?>" value="0">
                    </td>
                    <td style="background:#d9e2f3;">
                      <input type="text" class="form-control form-control-sm font-weight-bold text-center" id="xPemakaianAll_<?= htmlspecialchars($code) ?>" readonly style="background:transparent; border:none;">
                    </td>
                    <td style="background:#eee6d8;">
                      <input type="text" class="form-control form-control-sm font-weight-bold text-center" id="xBiayaAll_<?= htmlspecialchars($code) ?>" readonly style="background:transparent; border:none;">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="row mt-3">
            <div class="col-md-6">
              <div class="alert alert-info py-2 mb-0">Total KWH: <strong id="xTotalKwhAll">0.000</strong> kWh</div>
            </div>
            <div class="col-md-6">
              <div class="alert alert-warning py-2 mb-0">Total Biaya: Rp <strong id="xTotalBiayaAll">0</strong></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Data</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL INPUT PER MESIN (PERSIS SEPERTI 21 TON ACTOM) -->
<div class="modal fade" id="modalTambahKwhListrikSingle" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title" id="modalSingleTitle">Input Data Mesin</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formKwhListrikSingle" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label class="font-weight-bold">Pilih Mesin / Bagian</label>
            <select name="machine" id="xSingleMachine" class="form-control">
              <?php foreach ($machines as $code => $m): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= ($code === '21ton_actom' ? 'selected' : '') ?>>
                  <?= htmlspecialchars($m['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Tanggal</label>
            <input type="date" name="tanggal" id="xSingleTanggal" class="form-control" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-5">
              <label class="font-weight-bold">KWH Hari Ini</label>
              <input type="text" name="kwh_hari_ini" id="xSingleKwhHariIni" class="form-control num-only" value="0" required>
            </div>
            <div class="form-group col-md-5">
              <label class="font-weight-bold">KWH Kemarin</label>
              <input type="text" name="kwh_kemarin" id="xSingleKwhKemarin" class="form-control num-only" value="0" required>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-info btn-sm w-100" id="btnSingleAmbilKemarin" title="Ambil angka meter Hari Ini dari tanggal kemarin">
                <i class="fas fa-copy"></i> Ambil Data
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Pemakaian KWH</label>
            <input type="text" name="total_pemakaian" id="xSingleTotalPemakaian" class="form-control" readonly style="background-color: #f0f0f0;">
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Tarif per KWH</label>
            <input type="text" name="tarif_per_kwh" id="xSingleTarif" class="form-control num-only" value="<?= htmlspecialchars((string)kwhl2_default_tarif()) ?>" required>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Total Biaya</label>
            <input type="text" name="total_biaya" id="xSingleTotalBiaya" class="form-control" readonly style="background-color: #f0f0f0;">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL TAMBAH CATATAN -->
<div class="modal fade" id="modalTambahCatatanKwhListrik2" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Tambah Catatan KWH Listrik 2</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="formCatatanKwhListrik2" autocomplete="off">
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
    if (isNaN(n)) return '0';
    return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function formatDateYmdLocal(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function getYesterdayString(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    if (parts.length !== 3) return '';
    var tglObj = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    tglObj.setDate(tglObj.getDate() - 1);
    return formatDateYmdLocal(tglObj);
  }

  // Recalculate full form
  function recalcAll() {
    var tarif = parseNum($('#xTarif').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl2_default_tarif()) ?>;

    var totalKwh = 0;
    var totalBiaya = 0;

    Object.keys(machines).forEach(function (code) {
      var hi = parseNum($('#xHiAll_' + code).val());
      if (isNaN(hi)) hi = 0;
      var km = parseNum($('#xKmAll_' + code).val());
      if (isNaN(km)) km = 0;

      var pemakaian = hi - km;
      if (pemakaian < 0) pemakaian = 0;
      var biaya = pemakaian * tarif;

      totalKwh += pemakaian;
      totalBiaya += biaya;

      $('#xPemakaianAll_' + code).val(fmt(pemakaian, 3));
      $('#xBiayaAll_' + code).val(fmt(biaya, 0));
    });

    $('#xTotalKwhAll').text(fmt(totalKwh, 3));
    $('#xTotalBiayaAll').text(fmt(totalBiaya, 0));
  }

  // Recalculate single modal
  function recalcSingle() {
    var hi = parseNum($('#xSingleKwhHariIni').val());
    if (isNaN(hi)) hi = 0;
    var km = parseNum($('#xSingleKwhKemarin').val());
    if (isNaN(km)) km = 0;

    var pemakaian = hi - km;
    if (pemakaian < 0) pemakaian = 0;
    $('#xSingleTotalPemakaian').val(fmt(pemakaian, 3));

    var tarif = parseNum($('#xSingleTarif').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl2_default_tarif()) ?>;

    var biaya = pemakaian * tarif;
    $('#xSingleTotalBiaya').val(fmt(biaya, 0));
  }

  var table = $('#kwhListrik2Table').DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    responsive: true,
    ajax: {
      url: 'kwh_listrik2_serverside.php',
      type: 'POST',
      data: function (d) {
        d.start_date = $('#filterStartDate').val();
        d.end_date = $('#filterEndDate').val();
      }
    },
    columns: [
      { data: null, render: function (d, t, r, m) { return m.row + m.settings._iDisplayStart + 1; } },
      { data: 'tanggal_formatted' },
      { data: 'total_kwh', render: function (d) { return fmt(d, 3); } },
      { data: 'total_biaya', render: function (d) { return fmt(d, 0); } },
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

  // Open Full Form Modal
  $('#btnTambahData').on('click', function (e) {
    e.preventDefault();
    $('#formKwhListrik2')[0].reset();
    $('#xTanggal').val(new Date().toISOString().slice(0, 10));
    $('.kwh-hi-all, .kwh-km-all').val('0');
    $('#xTarif').val(<?= json_encode((string)kwhl2_default_tarif()) ?>);
    recalcAll();
    $('#modalTambahKwhListrik2').modal('show');
  });

  // Ambil data kemarin otomatis untuk semua mesin
  $('#btnAmbilSemuaKemarin').on('click', function (e) {
    e.preventDefault();
    var tanggal = $('#xTanggal').val();
    if (!tanggal) {
      Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih tanggal terlebih dahulu.' });
      return;
    }
    var tglKemarin = getYesterdayString(tanggal);

    $.post('get_kwh_kemarin.php', { tanggal: tglKemarin }, function (resp) {
      if (resp && resp.success && resp.machines) {
        var foundCount = 0;
        Object.keys(resp.machines).forEach(function (code) {
          var val = resp.machines[code].kwh_hari_ini || 0;
          $('#xKmAll_' + code).val(val);
          if (val > 0) foundCount++;
        });
        recalcAll();
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Nilai KWH Kemarin berhasil diisi dari data tanggal ' + tglKemarin + ' (' + foundCount + ' mesin ditemukan).'
        });
      } else {
        Swal.fire({
          icon: 'info',
          title: 'Informasi',
          text: 'Data KWH kemarin (tanggal ' + tglKemarin + ') tidak ditemukan di database.'
        });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengambil data kemarin.' });
    });
  });

  // Open Single Modal
  $('#btnTambahDataSingle').on('click', function (e) {
    e.preventDefault();
    $('#formKwhListrikSingle')[0].reset();
    $('#xSingleTanggal').val(new Date().toISOString().slice(0, 10));
    $('#xSingleMachine').val('21ton_actom').trigger('change');
    $('#xSingleKwhHariIni').val('0');
    $('#xSingleKwhKemarin').val('0');
    $('#xSingleTarif').val(<?= json_encode((string)kwhl2_default_tarif()) ?>);
    recalcSingle();
    $('#modalTambahKwhListrikSingle').modal('show');
  });

  $('#xSingleMachine').on('change', function () {
    var label = $(this).find('option:selected').text().trim();
    $('#modalSingleTitle').text('Input Data ' + label);
  });

  // Ambil data kemarin untuk single modal
  $('#btnSingleAmbilKemarin').on('click', function (e) {
    e.preventDefault();
    var tanggal = $('#xSingleTanggal').val();
    var machine = $('#xSingleMachine').val();
    if (!tanggal) {
      Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih tanggal terlebih dahulu.' });
      return;
    }
    var tglKemarin = getYesterdayString(tanggal);

    $.post('get_kwh_kemarin.php', { tanggal: tglKemarin }, function (resp) {
      if (resp && resp.success && resp.machines && resp.machines[machine]) {
        var kwhKemarin = resp.machines[machine].kwh_hari_ini || 0;
        $('#xSingleKwhKemarin').val(kwhKemarin);
        recalcSingle();
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Data KWH kemarin berhasil diambil dari tanggal ' + tglKemarin + ' (' + kwhKemarin + ' kWh)'
        });
      } else {
        Swal.fire({
          icon: 'info',
          title: 'Informasi',
          text: 'Data KWH kemarin (tanggal ' + tglKemarin + ') untuk mesin ini tidak ditemukan.'
        });
      }
    }, 'json').fail(function () {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengambil data kemarin.' });
    });
  });

  // Open Catatan Modal
  $('#btnTambahCatatan').on('click', function (e) {
    e.preventDefault();
    $('#formCatatanKwhListrik2')[0].reset();
    $('#xCatatanTanggal').val(new Date().toISOString().slice(0, 10));
    $('#modalTambahCatatanKwhListrik2').modal('show');
  });

  $(document).on('input', '.num-only', function () {
    this.value = this.value.replace(/[^0-9.,\-]/g, '');
  });

  $(document).on('input change', '.kwh-hi-all, .kwh-km-all, #xTarif', recalcAll);
  $(document).on('input change', '#xSingleKwhHariIni, #xSingleKwhKemarin, #xSingleTarif', recalcSingle);

  // Submit Full Form
  $('#formKwhListrik2').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_kwh_listrik2.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahKwhListrik2').modal('hide');
          Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Tersimpan.' });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
        }
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menyimpan data.' });
      }
    });
  });

  // Submit Single Form
  $('#formKwhListrikSingle').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_kwh_listrik2_single.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahKwhListrikSingle').modal('hide');
          Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Tersimpan.' });
          table.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menyimpan data.' });
        }
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menyimpan data.' });
      }
    });
  });

  // Submit Catatan Form
  $('#formCatatanKwhListrik2').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: 'save_catatan_kwh_listrik2.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          $('#modalTambahCatatanKwhListrik2').modal('hide');
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

  $(document).on('click', '.btn-detail', function () {
    var id = $(this).data('id');
    if (id) window.location.href = 'view_kwh_listrik2.php?id=' + encodeURIComponent(id);
  });
  $(document).on('click', '.btn-edit', function () {
    var id = $(this).data('id');
    if (id) window.location.href = 'edit_kwh_listrik2.php?id=' + encodeURIComponent(id);
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
        window.location.href = 'delete_kwh_listrik2.php?id=' + encodeURIComponent(id);
      }
    });
  });

  recalcAll();
});
</script>
