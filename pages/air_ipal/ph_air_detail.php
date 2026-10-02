<?php
// ph_air_detail.php - Tampilkan detail PH Air berdasarkan tanggal, pengecek, shift
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');


$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="alert alert-danger">Parameter tidak lengkap.</div>';
    exit;
}
$sql = "SELECT * FROM dbo.PH_Air WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Data tidak ditemukan.</div>';
    exit;
}

// Fungsi bantu agar tampilan bak lebih bersih
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
        <tr class="table-info"><th colspan="2">Data pH Air per Bak</th></tr>
        <tr><th style="width: 50%">Nama Bak</th><th>pH Air</th></tr>
        <?php
        $baks = [
          'PekatBesar' => 'Pekat Besar',
          'PekatKecil' => 'Pekat Kecil',
          'Anoxit' => 'Anoxit',
          'Daff1' => 'Daff 1',
          'Daff2' => 'Daff 2',
          'Daff3' => 'Daff 3',
          'Aerasi1' => 'Aerasi 1',
          'Aerasi2' => 'Aerasi 2',
          'Aerasi3' => 'Aerasi 3',
          'Aerasi4' => 'Aerasi 4',
          'SelokanPekat' => 'Selokan Pekat',
          'SelokanReaktif' => 'Selokan Reaktif',
          'Sedimen' => 'Sedimen',
          'Outlet' => 'Outlet',
          'EqualSum' => 'Equal Sum',
        ];
        foreach ($baks as $key => $label) {
          $value = '';
          if (isset($row[$key])) {
            $value = $row[$key];
          } else {
            $oldKey = str_replace('Aerasi', 'Aerosi', $key);
            if (isset($row[$oldKey])) {
              $value = $row[$oldKey];
            }
          }
          echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . val_bak($value) . '</td></tr>';
        }
        ?>
      </table>
    </div>
  </div>
</div>
<?php
