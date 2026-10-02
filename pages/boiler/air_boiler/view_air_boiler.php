<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: air_boiler.php');
    exit;
}

$sql = "SELECT x.*,
               CASE WHEN x.tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_umpan_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_umpan_ppm,
               CASE WHEN x.tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_boiler_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_boiler_ppm,
               CASE WHEN x.tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_umpan_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_umpan_display_ms,
               CASE WHEN x.tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(x.tds_boiler_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_boiler_display_ms
        FROM dbo.air_boiler_alstom_harian x
        WHERE x.id=?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: air_boiler.php');
    exit;
}

$fmtNum = function ($v, $d = 2) {
    return is_numeric($v) ? number_format((float)$v, $d, '.', ',') : '-';
};
$fmtDate = function ($v) {
    if ($v instanceof DateTime) return $v->format('d-m-Y');
    return $v ? date('d-m-Y', strtotime((string)$v)) : '-';
};
$fmtDateTime = function ($v) {
    if ($v instanceof DateTime) return $v->format('d-m-Y H:i');
    return $v ? date('d-m-Y H:i', strtotime((string)$v)) : '-';
};
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1 class="m-0">Detail Monitoring Air Boiler - Alstom</h1>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
          <a href="air_boiler.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
        </div>

        <div class="card-body table-responsive">
          <table class="table table-sm table-bordered">
            <tr><th style="width:35%;">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
            <tr><th>Temp Air Umpan (C)</th><td><?= htmlspecialchars($fmtNum($row['temp_umpan_c'] ?? null)) ?></td></tr>
            <tr><th>DH std &lt; 1</th><td><?= htmlspecialchars($fmtNum($row['dh_std_lt1'] ?? null)) ?></td></tr>
            <tr><th>TDS Air Umpan (ms/cm)</th><td><?= htmlspecialchars($fmtNum($row['tds_umpan_ms'] ?? null)) ?></td></tr>
            <tr><th>TDS Air Umpan (ppm)</th><td><?= htmlspecialchars($fmtNum($row['tds_umpan_ppm'] ?? null)) ?></td></tr>
            <tr><th>pH Air Boiler</th><td><?= htmlspecialchars((string)($row['ph_boiler'] ?? '-')) ?></td></tr>
            <tr><th>Temp Air Boiler (C)</th><td><?= htmlspecialchars($fmtNum($row['temp_boiler_c'] ?? null)) ?></td></tr>
            <tr><th>TDS Air Boiler (ms/cm)</th><td><?= htmlspecialchars($fmtNum($row['tds_boiler_ms'] ?? null)) ?></td></tr>
            <tr><th>TDS Air Boiler (ppm)</th><td><?= htmlspecialchars($fmtNum($row['tds_boiler_ppm'] ?? null)) ?></td></tr>
            <tr><th>Jumlah Blowdown</th><td><?= htmlspecialchars((string)($row['blowdown_jumlah'] ?? '-')) ?></td></tr>
            <tr><th>Keterangan</th><td><?= nl2br(htmlspecialchars((string)($row['keterangan'] ?? '-'))) ?></td></tr>
            <tr><th>Display Air Umpan (ms/cm)</th><td><?= htmlspecialchars($fmtNum($row['tds_umpan_display_ms'] ?? null)) ?></td></tr>
            <tr><th>Display Air Boiler (ms/cm)</th><td><?= htmlspecialchars($fmtNum($row['tds_boiler_display_ms'] ?? null)) ?></td></tr>
            <tr><th>Created By</th><td><?= htmlspecialchars((string)($row['creatby'] ?? '-')) ?></td></tr>
            <tr><th>Created At</th><td><?= htmlspecialchars($fmtDateTime($row['creatat'] ?? null)) ?></td></tr>
            <tr><th>Updated By</th><td><?= htmlspecialchars((string)($row['updateby'] ?? '-')) ?></td></tr>
            <tr><th>Updated At</th><td><?= htmlspecialchars($fmtDateTime($row['updateat'] ?? null)) ?></td></tr>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
