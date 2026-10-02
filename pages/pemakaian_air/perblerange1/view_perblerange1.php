<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: perblerange1.php'); exit; }

$sql = "SELECT * FROM dbo.perblerange1_harian WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: perblerange1.php'); exit; }

$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Meter Perble Range 1</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="perblerange1.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="35%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>Meter Awal (m3)</th><td><?= htmlspecialchars($fmtNum($row['meter_awal_m3'] ?? 0)) ?></td></tr>
<tr><th>Meter Akhir (m3)</th><td><?= htmlspecialchars($fmtNum($row['meter_akhir_m3'] ?? 0)) ?></td></tr>
<tr><th>Total Pemakaian (m3)</th><td><?= htmlspecialchars($fmtNum($row['total_pemakaian_m3'] ?? 0)) ?></td></tr>
<tr><th>Meter Awal Debit (m3)</th><td><?= htmlspecialchars($fmtNum($row['meter_awal_debit_m3'] ?? 0)) ?></td></tr>
<tr><th>Meter Akhir Debit (m3)</th><td><?= htmlspecialchars($fmtNum($row['meter_akhir_debit_m3'] ?? 0)) ?></td></tr>
<tr><th>Total Pemakaian Debit (m3)</th><td><?= htmlspecialchars($fmtNum($row['total_pemakaian_debit_m3'] ?? 0)) ?></td></tr>
<tr><th>Operasional MC PBR1 (jam)</th><td><?= htmlspecialchars($fmtNum($row['operasional_mc_pbr1_jam'] ?? 0)) ?></td></tr>
<tr><th>Pemakaian Rata/Jam (m3)</th><td><?= htmlspecialchars($fmtNum($row['pemakaian_rata_per_jam_m3'] ?? 0)) ?></td></tr>
<tr><th>Jumlah Debit</th><td><?= htmlspecialchars($fmtNum($row['jumlah_debit_m3'] ?? 0)) ?></td></tr>
<tr><th>Operasional Debit (jam)</th><td><?= htmlspecialchars($fmtNum($row['operasional_mc_pbr1_debit_jam'] ?? 0)) ?></td></tr>
<tr><th>Pemakaian Rata/Jam Debit</th><td><?= htmlspecialchars($fmtNum($row['pemakaian_rata_per_jam_debit_m3'] ?? 0)) ?></td></tr>
<tr><th>KET MC Yang Jalan</th><td><?= htmlspecialchars($row['ket_mc_yang_jalan'] ?? '-') ?></td></tr>
<tr><th>Keterangan</th><td><?= nl2br(htmlspecialchars($row['ket'] ?? '')) ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
<tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
