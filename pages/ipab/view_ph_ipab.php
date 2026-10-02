<?php
// view_ph_ipab.php - Tampilkan detail pH IPAB
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}

$sql = "SELECT * FROM dbo.ph_ipab WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

function val_bak($v) {
    return ($v === null || $v === '' || (is_numeric($v) && (float)$v == 0)) ? '' : htmlspecialchars($v);
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-primary"><th colspan="2">Identitas Pemeriksaan</th></tr>
        <tr><th style="width: 40%">Tanggal</th><td><?= htmlspecialchars($row['Tanggal']->format('Y-m-d')) ?></td></tr>
        <tr><th>Pengecek</th><td><?= htmlspecialchars($row['Pengecek']) ?></td></tr>
        <tr><th>Sampel Air</th><td><?= htmlspecialchars($row['Shift']) ?></td></tr>
        <tr><th>Created By</th><td><?= htmlspecialchars($row['CreatedBy']) ?></td></tr>
        <tr><th>Created At</th><td><?= htmlspecialchars($row['CreatedAt']->format('Y-m-d H:i:s')) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-info"><th colspan="2">Data pH per Bak</th></tr>
        <tr><th style="width: 50%">Nama Bak</th><th>pH</th></tr>
        <?php
        $baks = [
          'Bak_Clarivier' => 'Bak Clarivier',
          'Bak_2' => 'Bak 2',
          'Bak_3' => 'Bak 3',
          'Bak_4' => 'Bak 4',
          'Air_Sungai' => 'Air Sungai',
        ];
        foreach ($baks as $key => $label) {
          $value = $row[$key] ?? '';
          echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . val_bak($value) . '</td></tr>';
        }
        ?>
      </table>
    </div>
  </div>
</div>
