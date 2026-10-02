<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

$menuId = 236;
requireView($conn, $menuId);
$themeColor = $_SESSION['Theme'] ?? 'primary';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: kwh_listrik2.php');
    exit;
}

if (!kwhl2_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel ' . kwhl2_table_display_name($conn) . ' belum tersedia.';
    header('Location: kwh_listrik2.php');
    exit;
}
$tableName = kwhl2_table_full_name($conn);
if ($tableName === '') {
    $_SESSION['error'] = 'Tabel kwh_listrik2 belum tersedia.';
    header('Location: kwh_listrik2.php');
    exit;
}

$stmt = sqlsrv_query($conn, "SELECT * FROM {$tableName} WHERE id=?", [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: kwh_listrik2.php');
    exit;
}

$data = kwhl2_row_from_db($row);
$machines = kwhl2_machine_defs();
$canEdit = !empty(getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId)['CanEdit']);
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><h1 class="m-0">Edit KWH Listrik 2</h1></div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Edit</h3>
          <a href="kwh_listrik2.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
        </div>
        <div class="card-body">
          <?php if (!$canEdit): ?>
            <div class="alert alert-warning mb-0">Anda tidak memiliki hak mengedit data.</div>
          <?php else: ?>
            <form method="POST" action="update_kwh_listrik2.php" autocomplete="off" id="formEditKwhListrik2">
              <input type="hidden" name="id" value="<?= htmlspecialchars((string)$data['id']) ?>">

              <div class="form-row mb-3">
                <div class="form-group col-md-3">
                  <label class="font-weight-bold">Tanggal</label>
                  <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($data['tanggal']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="font-weight-bold">Tarif per KWH (Rp)</label>
                  <input type="text" name="tarif_per_kwh" id="xTarifEdit" class="form-control num-only" value="<?= htmlspecialchars(number_format((float)($data['tarif_per_kwh'] ?? 0), 0, '.', '')) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label class="font-weight-bold">Keterangan</label>
                  <input type="text" name="ket" class="form-control" value="<?= htmlspecialchars($data['ket']) ?>" placeholder="Isi keterangan jika diperlukan">
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-bordered text-center">
                  <thead class="thead-light">
                    <tr>
                      <th style="width:160px;">Item / Mesin</th>
                      <th>KWH Hari Ini</th>
                      <th>KWH Kemarin</th>
                      <th style="background:#ffc000; color:#000;">Pemakaian KWH (Auto)</th>
                      <th style="background:#efefef;">Total Biaya Rp (Auto)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($machines as $code => $m): ?>
                      <tr>
                        <th class="text-left align-middle font-weight-bold" style="padding-left:12px;"><?= htmlspecialchars($m['label']) ?></th>
                        <td>
                          <input type="text" class="form-control form-control-sm num-only kwh-hi-input" name="<?= htmlspecialchars($m['hi_key']) ?>" id="xHi_<?= htmlspecialchars($code) ?>" data-code="<?= htmlspecialchars($code) ?>" value="<?= htmlspecialchars(number_format((float)($data[$m['hi_key']] ?? 0), 3, '.', '')) ?>">
                        </td>
                        <td>
                          <input type="text" class="form-control form-control-sm num-only kwh-km-input" name="<?= htmlspecialchars($m['km_key']) ?>" id="xKm_<?= htmlspecialchars($code) ?>" data-code="<?= htmlspecialchars($code) ?>" value="<?= htmlspecialchars(number_format((float)($data[$m['km_key']] ?? 0), 3, '.', '')) ?>">
                        </td>
                        <td style="background:#d9e2f3;">
                          <input type="text" class="form-control form-control-sm font-weight-bold text-center" id="xPemakaian_<?= htmlspecialchars($code) ?>" readonly style="background:transparent; border:none;">
                        </td>
                        <td style="background:#eee6d8;">
                          <input type="text" class="form-control form-control-sm font-weight-bold text-center" id="xBiaya_<?= htmlspecialchars($code) ?>" readonly style="background:transparent; border:none;">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="row mb-3 mt-2">
                <div class="col-md-6"><div class="alert alert-info py-2 mb-0">Total KWH: <strong id="xTotalKwhEdit">0.000</strong> kWh</div></div>
                <div class="col-md-6"><div class="alert alert-warning py-2 mb-0">Total Biaya: Rp <strong id="xTotalBiayaEdit">0</strong></div></div>
              </div>

              <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
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
    if (isNaN(n)) return '0';
    return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function recalc() {
    var tarif = parseNum($('#xTarifEdit').val());
    if (isNaN(tarif) || tarif <= 0) tarif = <?= json_encode((float)kwhl2_default_tarif()) ?>;
    var totalKwh = 0;
    var totalBiaya = 0;

    Object.keys(machines).forEach(function (code) {
      var hi = parseNum($('#xHi_' + code).val());
      if (isNaN(hi)) hi = 0;
      var km = parseNum($('#xKm_' + code).val());
      if (isNaN(km)) km = 0;

      var pemakaian = hi - km;
      if (pemakaian < 0) pemakaian = 0;
      var biaya = pemakaian * tarif;

      totalKwh += pemakaian;
      totalBiaya += biaya;

      $('#xPemakaian_' + code).val(fmt(pemakaian, 3));
      $('#xBiaya_' + code).val(fmt(biaya, 0));
    });

    $('#xTotalKwhEdit').text(fmt(totalKwh, 3));
    $('#xTotalBiayaEdit').text(fmt(totalBiaya, 0));
  }

  $(document).on('input', '.num-only', function () {
    this.value = this.value.replace(/[^0-9.,\-]/g, '');
  });
  $(document).on('input change', '.kwh-hi-input, .kwh-km-input, #xTarifEdit', recalc);
  recalc();
});
</script>
