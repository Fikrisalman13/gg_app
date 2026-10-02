<?php
// tss_air_detail.php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID tidak ditemukan.</div>';
    exit;
}
$id = intval($_GET['id']);

$sql = "SELECT * FROM dbo.TSS_Air WHERE Id = ?";
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
  if ($isBak && (int)$row[$key] == 0) return '';
  return htmlspecialchars((string)$row[$key]);
}
?>
<div class="container-fluid">
  <h5 class="mb-3">Detail Data TSS Air</h5>
  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-active"><th colspan="2">Informasi Umum</th></tr>
        <tr><th style="width: 40%">Tanggal</th><td><?= val($row, 'Tanggal') ?></td></tr>
        <tr><th>Pengecek</th><td><?= val($row, 'Pengecek') ?></td></tr>
        <tr><th>Sampel Air</th><td><?= val($row, 'Shift') == '1' ? 'Pagi' : (val($row, 'Shift') == '2' ? 'Siang' : (val($row, 'Shift') == '3' ? 'Malam' : val($row, 'Shift'))); ?></td></tr>
        <tr><th>Created By</th><td><?= val($row, 'Created_By') ?></td></tr>
        <tr><th>Created At</th><td><?= val($row, 'Created_At') ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-active"><th colspan="2">Nilai TSS per Bak</th></tr>
        <tr><th style="width: 50%">TSS 0</th><td><?= val($row, 'TSS_0', true) ?></td></tr>
        <tr><th>Pekat Besar</th><td><?= val($row, 'Pekat_Besar', true) ?></td></tr>
        <tr><th>Pekat Kecil</th><td><?= val($row, 'Pekat_Kecil', true) ?></td></tr>
        <tr><th>Dwatring</th><td><?= val($row, 'Dwatring', true) ?></td></tr>
        <tr><th>Anoxit</th><td><?= val($row, 'Anoxit', true) ?></td></tr>
        <tr><th>Daff 1</th><td><?= val($row, 'Daff_1', true) ?></td></tr>
        <tr><th>Daff 2</th><td><?= val($row, 'Daff_2', true) ?></td></tr>
        <tr><th>Daff 3</th><td><?= val($row, 'Daff_3', true) ?></td></tr>
        <tr><th>Aerasi 1</th><td><?= val($row, 'Aerasi_1', true) ?></td></tr>
        <tr><th>Aerasi 2</th><td><?= val($row, 'Aerasi_2', true) ?></td></tr>
        <tr><th>Aerasi 3</th><td><?= val($row, 'Aerasi_3', true) ?></td></tr>
        <tr><th>Aerasi 4</th><td><?= val($row, 'Aerasi_4', true) ?></td></tr>
        <tr><th>Sedimen</th><td><?= val($row, 'Sedimen', true) ?></td></tr>
        <tr><th>Equal Sum</th><td><?= val($row, 'Equal', true) ?></td></tr>
        <tr><th>Outlet</th><td><?= val($row, 'Outlet', true) ?></td></tr>
      </table>
    </div>
  </div>
</div>
