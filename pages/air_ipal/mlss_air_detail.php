<?php
// mlss_air_detail.php - Detail data MLSS Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}

$sql = "SELECT * FROM dbo.MLSS_Air WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

function formatShift($shift)
{
    return match ((string)$shift) {
        '1' => 'Pagi',
        '2' => 'Siang',
        '3' => 'Malam',
        default => htmlspecialchars((string)$shift)
    };
}

function formatNumber($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    return htmlspecialchars(number_format((float)$value, 2));
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-primary"><th colspan="2">Identitas Pemeriksaan</th></tr>
        <tr><th style="width:40%">Tanggal</th><td><?= $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : htmlspecialchars($row['Tanggal']) ?></td></tr>
        <tr><th>Pengecek</th><td><?= htmlspecialchars($row['Pengecek']) ?></td></tr>
        <tr><th>Sampel Air</th><td><?= formatShift($row['Shift']) ?></td></tr>
        <tr><th>Created By</th><td><?= htmlspecialchars($row['CreatedBy']) ?></td></tr>
        <tr><th>Created At</th><td><?= $row['CreatedAt'] instanceof DateTime ? $row['CreatedAt']->format('Y-m-d H:i:s') : htmlspecialchars($row['CreatedAt']) ?></td></tr>
        <tr><th>Update At</th><td><?= $row['UpdateAt'] instanceof DateTime ? $row['UpdateAt']->format('Y-m-d H:i:s') : htmlspecialchars($row['UpdateAt']) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-info"><th colspan="2">Nilai MLSS per Bak</th></tr>
        <tr><th style="width:50%">Aerasi 1</th><td><?= formatNumber($row['Aerasi1']) ?></td></tr>
        <tr><th>Aerasi 2</th><td><?= formatNumber($row['Aerasi2']) ?></td></tr>
        <tr><th>Aerasi 3</th><td><?= formatNumber($row['Aerasi3']) ?></td></tr>
        <tr><th>Aerasi 4</th><td><?= formatNumber($row['Aerasi4']) ?></td></tr>
        <tr><th>Raspam</th><td><?= formatNumber($row['Raspam'] ?? null) ?></td></tr>
      </table>
    </div>
  </div>
</div>
