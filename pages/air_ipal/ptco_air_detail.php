<?php
// ptco_air_detail.php - Tampilkan detail PTCO Air
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}
$sql = "SELECT * FROM dbo.PTCO_Air WHERE Id = ?";
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
        <tr><th style="width: 40%">Tanggal</th><td><?= isset($row['Tanggal']) && $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : htmlspecialchars($row['Tanggal']) ?></td></tr>
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
        <tr><th>Created At</th><td><?= isset($row['CreatedAt']) && $row['CreatedAt'] instanceof DateTime ? $row['CreatedAt']->format('Y-m-d H:i:s') : htmlspecialchars($row['CreatedAt']) ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm mb-2">
        <tr class="table-info"><th colspan="2">Data PTCO per Bak</th></tr>
        <tr><th style="width: 50%">Nama Bak</th><th>Nilai</th></tr>
        <?php
        $baks = [
          'EqualSum' => 'Equal Sum',
          'Daff1' => 'Daff 1',
          'Daff2' => 'Daff 2',
          'Daff3' => 'Daff 3',
          'Aerasi1' => 'Aerasi 1',
          'SedimenBiologi' => 'Sedimen Biologi',
          'Flogulan' => 'Flogulan',
          'PostSedimen' => 'Post Sedimen',
          'Dwatring' => 'Dwatring',
          'Outlet' => 'Outlet'
        ];
        foreach ($baks as $key => $label) {
          $value = isset($row[$key]) ? $row[$key] : '';
          echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . val_bak($value) . '</td></tr>';
        }
        ?>
      </table>
    </div>
  </div>
</div>
<?php
