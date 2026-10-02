<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
requireView($conn, $menuId);
$themeColor = $_SESSION['Theme'] ?? 'primary';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: kwh_listrik.php');
    exit;
}

if (!kwhl_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    $_SESSION['error'] = 'Tabel kwh_listrik belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}

$stmt = sqlsrv_query($conn, "SELECT * FROM {$tableName} WHERE id=?", [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: kwh_listrik.php');
    exit;
}

$data = kwhl_row_from_db($row);
$machines = kwhl_machine_defs();
$canEdit = !empty(getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId)['CanEdit']);
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><h1 class="m-0">Edit KWH Listrik</h1></div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Edit</h3>
          <a href="kwh_listrik.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
        </div>
        <div class="card-body">
          <?php if (!$canEdit): ?>
            <div class="alert alert-warning mb-0">Anda tidak memiliki hak mengedit data.</div>
          <?php else: ?>
            <form method="POST" action="update_kwh_listrik.php" autocomplete="off" id="formEditKwhListrik">
              <input type="hidden" name="id" value="<?= htmlspecialchars((string)$data['id']) ?>">

              <div class="form-row">
                <div class="form-group col-md-2">
                  <label>Tanggal</label>
                  <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($data['tanggal']) ?>" required>
                </div>
                <div class="form-group col-md-2">
                  <label>Faktor Konversi</label>
                  <input type="text" name="faktor_konversi" id="xFaktorEdit" class="form-control num-only" value="<?= htmlspecialchars(number_format((float)($data['faktor_konversi'] ?? 0), 1, '.', '')) ?>">
                </div>
                <div class="form-group col-md-2">
                  <label>Tarif per KWH</label>
                  <input type="text" name="tarif_per_kwh" id="xTarifEdit" class="form-control num-only" value="<?= htmlspecialchars(number_format((float)($data['tarif_per_kwh'] ?? 0), 0, '.', '')) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Keterangan</label>
                  <input type="text" name="ket" class="form-control" value="<?= htmlspecialchars($data['ket']) ?>">
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-bordered text-center">
                  <thead class="thead-light">
                    <tr>
                      <th>Bagian</th>
                      <?php foreach ($machines as $m): ?>
                        <th><?= htmlspecialchars($m['label']) ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th>AMPERE</th>
                      <?php foreach ($machines as $m): ?>
                        <td>
                          <input type="text" class="form-control form-control-sm num-only amp-edit" name="<?= htmlspecialchars($m['amp_key']) ?>" value="<?= htmlspecialchars(number_format((float)($data[$m['amp_key']] ?? 0), 2, '.', '')) ?>">
                        </td>
                      <?php endforeach; ?>
                    </tr>
                    <tr>
                      <th>KWH (Auto)</th>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><input type="text" class="form-control form-control-sm" id="xKwhEdit_<?= htmlspecialchars($code) ?>" readonly></td>
                      <?php endforeach; ?>
                    </tr>
                    <tr>
                      <th>BIAYA (Auto)</th>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><input type="text" class="form-control form-control-sm" id="xBiayaEdit_<?= htmlspecialchars($code) ?>" readonly></td>
                      <?php endforeach; ?>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="row mb-3">
                <div class="col-md-6"><div class="alert alert-info py-2 mb-0">Total KWH: <strong id="xTotalKwhEdit">0.000</strong></div></div>
                <div class="col-md-6"><div class="alert alert-warning py-2 mb-0">Total Biaya: <strong id="xTotalBiayaEdit">0</strong></div></div>
              </div>

              <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan Perubahan</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script>
$(function () {
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
    if (isNaN(n)) return '';
    return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function recalc() {
    var factor = parseNum($('#xFaktorEdit').val());
    if (isNaN(factor) || factor <= 0) factor = <?= json_encode((float)kwhl_default_factor()) ?>;
    var tarif = parseNum($('#xTarifEdit').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl_default_tarif()) ?>;
    var totalKwh = 0;
    var totalBiaya = 0;
    Object.keys(machines).forEach(function (code) {
      var ampKey = machines[code].amp_key;
      var amp = parseNum($('[name="' + ampKey + '"]').val());
      if (isNaN(amp)) amp = 0;
      var kwh = (amp * factor * 24) / 1000;
      var biaya = kwh * tarif;
      totalKwh += kwh;
      totalBiaya += biaya;
      $('#xKwhEdit_' + code).val(fmt(kwh, 3));
      $('#xBiayaEdit_' + code).val(fmt(biaya, 0));
    });
    $('#xTotalKwhEdit').text(fmt(totalKwh, 3));
    $('#xTotalBiayaEdit').text(fmt(totalBiaya, 0));
  }

  $(document).on('input', '.num-only', function () {
    this.value = this.value.replace(/[^0-9.,\-]/g, '');
  });
  $(document).on('input change', '.amp-edit, #xFaktorEdit, #xTarifEdit', recalc);
  recalc();
});
</script>
