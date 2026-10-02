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
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><h1 class="m-0">Detail KWH Listrik 2</h1></div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
          <a href="kwh_listrik2.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-bordered mb-3">
            <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($data['tanggal'] ? date('d-m-Y', strtotime($data['tanggal'])) : '-') ?></td></tr>
            <tr><th>Tarif per KWH</th><td>Rp <?= htmlspecialchars(kwhl2_format_num($data['tarif_per_kwh'], 0)) ?></td></tr>
            <tr><th>Total KWH</th><td><?= htmlspecialchars(kwhl2_format_num($data['total_kwh'], 3)) ?> kWh</td></tr>
            <tr><th>Total Biaya</th><td>Rp <?= htmlspecialchars(kwhl2_format_num($data['total_biaya'], 0)) ?></td></tr>
            <tr><th>Keterangan</th><td><?= htmlspecialchars($data['ket'] ?: '-') ?></td></tr>
            <tr><th>Catatan</th><td><?= nl2br(htmlspecialchars($data['note'] ?: '-')) ?></td></tr>
          </table>

          <table class="table table-sm table-bordered text-center">
            <thead class="thead-light">
              <tr>
                <th>Item / Mesin</th>
                <th>KWH Hari Ini</th>
                <th>KWH Kemarin</th>
                <th>Pemakaian KWH (kWh)</th>
                <th>Total Biaya (Rp)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($machines as $code => $m): ?>
                <tr>
                  <th class="text-left font-weight-bold" style="background:#f8f9fa; padding-left:12px;"><?= htmlspecialchars($m['label']) ?></th>
                  <td><?= htmlspecialchars(kwhl2_format_num($data[$m['hi_key']] ?? 0, 3)) ?></td>
                  <td><?= htmlspecialchars(kwhl2_format_num($data[$m['km_key']] ?? 0, 3)) ?></td>
                  <td class="font-weight-bold" style="background:#d9e2f3;"><?= htmlspecialchars(kwhl2_format_num($data[$m['kwh_key']] ?? 0, 3)) ?></td>
                  <td class="font-weight-bold" style="background:#eee6d8;"><?= htmlspecialchars(kwhl2_format_num($data[$m['biaya_key']] ?? 0, 0)) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr style="background:#ffc000; font-weight:bold;">
                <td class="text-left" style="padding-left:12px;">TOTAL</td>
                <td colspan="2">-</td>
                <td><?= htmlspecialchars(kwhl2_format_num($data['total_kwh'], 3)) ?></td>
                <td>Rp <?= htmlspecialchars(kwhl2_format_num($data['total_biaya'], 0)) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
