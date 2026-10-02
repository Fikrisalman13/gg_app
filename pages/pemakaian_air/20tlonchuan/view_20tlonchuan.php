<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: 20tlonchuan.php'); exit; }

$sql = "SELECT * FROM dbo.pmlonchuan_hdr WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$hdr=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$hdr){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: 20tlonchuan.php'); exit; }

$dtlStmt = sqlsrv_query($conn, "SELECT jam, jenis, nilai_ton FROM dbo.pmlonchuan_dtl WHERE hdr_id=? ORDER BY jam ASC", [$id]);
$map = [];
if ($dtlStmt) {
    while ($r = sqlsrv_fetch_array($dtlStmt, SQLSRV_FETCH_ASSOC)) {
        $jam = (int)($r['jam'] ?? 0);
        if (!isset($map[$jam])) $map[$jam] = ['jam'=>$jam, 'air'=>null, 'steam'=>null];
        $jenis = strtoupper(trim((string)($r['jenis'] ?? '')));
        if ($jenis === 'AIR') $map[$jam]['air'] = (float)($r['nilai_ton'] ?? 0);
        if ($jenis === 'STEAM') $map[$jam]['steam'] = (float)($r['nilai_ton'] ?? 0);
    }
    sqlsrv_free_stmt($dtlStmt);
}

$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };

$sumAir = 0; $sumSteam = 0;
foreach ($map as $m){ $sumAir += (float)($m['air'] ?? 0); $sumSteam += (float)($m['steam'] ?? 0); }
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Laju Sesaat Boiler Lonchuan</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="20tlonchuan.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="35%">Tanggal</th><td><?= htmlspecialchars($fmtDate($hdr['periode'] ?? null)) ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($hdr['note'] ?? '')) ?></td></tr>
<tr><th>Created By</th><td><?= htmlspecialchars($hdr['created_by'] ?? '-') ?></td></tr>
<tr><th>Updated By</th><td><?= htmlspecialchars($hdr['updated_by'] ?? '-') ?></td></tr>
</table></div>

<div class="card-body table-responsive">
  <table class="table table-sm table-bordered text-center">
    <thead class="thead-light">
      <tr><th>Jam</th><th>Air (ton)</th><th>Steam (ton)</th></tr>
    </thead>
    <tbody>
      <?php if (empty($map)): ?>
        <tr><td colspan="3">Tidak ada data.</td></tr>
      <?php else: foreach ($map as $m): ?>
        <tr>
          <td><?= htmlspecialchars(str_pad((string)$m['jam'],2,'0',STR_PAD_LEFT) . ':00') ?></td>
          <td><?= htmlspecialchars($fmtNum($m['air'] ?? null)) ?></td>
          <td><?= htmlspecialchars($fmtNum($m['steam'] ?? null)) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="font-weight-bold">
          <td>TOTAL</td>
          <td><?= htmlspecialchars($fmtNum($sumAir)) ?></td>
          <td><?= htmlspecialchars($fmtNum($sumSteam)) ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
