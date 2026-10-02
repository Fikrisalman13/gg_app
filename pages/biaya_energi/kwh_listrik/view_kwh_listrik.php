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
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><h1 class="m-0">Detail KWH Listrik</h1></div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
          <a href="kwh_listrik.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-bordered mb-3">
            <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($data['tanggal'] ? date('d-m-Y', strtotime($data['tanggal'])) : '-') ?></td></tr>
            <tr><th>Faktor Konversi</th><td><?= htmlspecialchars(kwhl_format_num($data['faktor_konversi'], 1)) ?></td></tr>
            <tr><th>Tarif per KWH</th><td><?= htmlspecialchars(kwhl_format_num($data['tarif_per_kwh'], 0)) ?></td></tr>
            <tr><th>Total KWH</th><td><?= htmlspecialchars(kwhl_format_num($data['total_kwh'], 3)) ?></td></tr>
            <tr><th>Total Biaya</th><td><?= htmlspecialchars(kwhl_format_num($data['total_biaya'], 0)) ?></td></tr>
            <tr><th>Keterangan</th><td><?= htmlspecialchars($data['ket'] ?: '-') ?></td></tr>
            <tr><th>Catatan</th><td><?= nl2br(htmlspecialchars($data['note'] ?: '-')) ?></td></tr>
          </table>

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
                  <td><?= htmlspecialchars(kwhl_format_num($data[$m['amp_key']] ?? 0, 2)) ?></td>
                <?php endforeach; ?>
              </tr>
              <tr>
                <th>KWH</th>
                <?php foreach ($machines as $code => $m): ?>
                  <td><?= htmlspecialchars(kwhl_format_num($data['kwh_' . $code] ?? 0, 3)) ?></td>
                <?php endforeach; ?>
              </tr>
              <tr>
                <th>TOTAL BIAYA</th>
                <?php foreach ($machines as $code => $m): ?>
                  <td><?= htmlspecialchars(kwhl_format_num($data['biaya_' . $code] ?? 0, 0)) ?></td>
                <?php endforeach; ?>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
