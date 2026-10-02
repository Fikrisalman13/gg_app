<?php
// view_turbidity_ipab.php - Tampilkan detail Turbidity IPAB
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}

$sql = "SELECT * FROM dbo.turbidity_ipab WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

function val_turbidity($v) {
    return ($v === null || $v === '') ? '' : htmlspecialchars($v);
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-primary"><th colspan="2">Identitas Pemeriksaan</th></tr>
        <tr><th style="width: 40%">Tanggal</th><td><?= htmlspecialchars($row['Tanggal']->format('Y-m-d')) ?></td></tr>
        <tr><th>Pengecek</th><td><?= htmlspecialchars($row['Pengecek']) ?></td></tr>
        <tr><th>Sampel Air</th><td><?php
          switch ($row['Shift']) {
            case '1': echo 'Pagi'; break;
            case '2': echo 'Siang'; break;
            case '3': echo 'Malam'; break;
            default: echo htmlspecialchars($row['Shift']); break;
          }
        ?></td></tr>
        <tr><th>Created By</th><td><?= htmlspecialchars($row['CreatedBy']) ?></td></tr>
        <tr><th>Created At</th><td><?= htmlspecialchars($row['CreatedAt']->format('Y-m-d H:i:s')) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-info"><th colspan="2">Data Turbidity per Bak</th></tr>
        <tr><th style="width: 50%">Nama Bak</th><th>Turbidity</th></tr>
        <tr><td>Bak 3</td><td><?= val_turbidity($row['Bak_3'] ?? null) ?></td></tr>
        <tr><td>Bak 4</td><td><?= val_turbidity($row['Bak_4'] ?? null) ?></td></tr>
      </table>
    </div>
  </div>
</div>
