<?php
// cod_air_detail.php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID tidak ditemukan.</div>';
    exit;
}
$id = intval($_GET['id']);

$sql = "SELECT * FROM dbo.COD_air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

function val($row, $key, $isBak = false) {
  if (!isset($row[$key]) || $row[$key] === null) return '';
  if ($row[$key] instanceof DateTime) {
    return $row[$key]->format('Y-m-d');
  }
  // Untuk bak, jika 0 atau 0.00, kosongkan
  if ($isBak && (float)$row[$key] == 0) return '';
  return htmlspecialchars((string)$row[$key]);
}
?>
<div class="container-fluid">
  <h5 class="mb-3">Detail Data COD Air</h5>
  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-active"><th colspan="2">Informasi Umum</th></tr>
        <tr><th style="width: 40%">Tanggal</th><td><?= val($row, 'Tanggal') instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : val($row, 'Tanggal') ?></td></tr>
        <tr><th>Pengecek</th><td><?= val($row, 'Pengecek') ?></td></tr>
        <tr><th>Sampel Air</th><td><?= val($row, 'Shift') == '1' ? 'Pagi' : (val($row, 'Shift') == '2' ? 'Siang' : (val($row, 'Shift') == '3' ? 'Malam' : val($row, 'Shift'))); ?></td></tr>
        <tr><th>Nilai Kalibrasi</th><td><?= val($row, 'NilaiKalibrasi') ?></td></tr>
        <tr><th>Created By</th><td><?= val($row, 'CreatedBy') ?></td></tr>
        <tr><th>Created At</th><td><?= val($row, 'CreatedAt') instanceof DateTime ? $row['CreatedAt']->format('Y-m-d H:i:s') : val($row, 'CreatedAt') ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-active"><th colspan="2">Nilai COD per Bak</th></tr>
        <tr><th style="width: 50%">Pekat Besar</th><td><?= val($row, 'PekatBesar', true) ?></td></tr>
        <tr><th>Pekat Kecil</th><td><?= val($row, 'PekatKecil', true) ?></td></tr>
        <tr><th>Anoxit</th><td><?= val($row, 'Anoxit', true) ?></td></tr>
        <tr><th>Daff 1</th><td><?= val($row, 'Daff1', true) ?></td></tr>
        <tr><th>Daff 2</th><td><?= val($row, 'Daff2', true) ?></td></tr>
        <tr><th>Daff 3</th><td><?= val($row, 'Daff3', true) ?></td></tr>
        <tr><th>Aerasi 1</th><td><?= val($row, 'Aerasi1', true) ?></td></tr>
        <tr><th>Aerasi 2</th><td><?= val($row, 'Aerasi2', true) ?></td></tr>
        <tr><th>Aerasi 3</th><td><?= val($row, 'Aerasi3', true) ?></td></tr>
        <tr><th>Aerasi 4</th><td><?= val($row, 'Aerasi4', true) ?></td></tr>
        <tr><th>Selokan Pekat</th><td><?= val($row, 'SelokanPekat', true) ?></td></tr>
        <tr><th>Selokan Reaktif</th><td><?= val($row, 'SelokanReaktif', true) ?></td></tr>
        <tr><th>Dwatring</th><td><?= val($row, 'Dwatring', true) ?></td></tr>
        <tr><th>Sedimen</th><td><?= val($row, 'Sedimen', true) ?></td></tr>
        <tr><th>Outlet</th><td><?= val($row, 'Outlet', true) ?></td></tr>
        <tr><th>Equal Sum</th><td><?= val($row, 'EqualSum', true) ?></td></tr>
      </table>
    </div>
  </div>
</div>
