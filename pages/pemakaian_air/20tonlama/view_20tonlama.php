<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: 20tonlama.php'); exit; }

$sql = "SELECT * FROM dbo.air_steam_20tonlama_harian WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: 20tonlama.php'); exit; }

$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
$fmtTime=function($v){ if($v instanceof DateTime) return $v->format('H:i'); $s=(string)$v; return $s!==''?substr($s,0,5):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Air dan Steam 20 Ton Lama</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="20tonlama.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="35%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>Air Awal (m3)</th><td><?= htmlspecialchars($fmtNum($row['air_awal_m3'] ?? 0)) ?></td></tr>
<tr><th>Air Akhir (m3)</th><td><?= htmlspecialchars($fmtNum($row['air_akhir_m3'] ?? 0)) ?></td></tr>
<tr><th>Air Total Pemakaian (m3)</th><td><?= htmlspecialchars($fmtNum($row['air_total_pemakaian_m3'] ?? 0)) ?></td></tr>
<tr><th>Air Rata-rata per Jam (m3)</th><td><?= htmlspecialchars($fmtNum($row['air_rata_rata_per_jam_m3'] ?? 0)) ?></td></tr>
<tr><th>Air KET</th><td><?= nl2br(htmlspecialchars($row['air_ket'] ?? '')) ?></td></tr>
<tr><th>Steam Awal (ton)</th><td><?= htmlspecialchars($fmtNum($row['steam_awal_ton'] ?? 0)) ?></td></tr>
<tr><th>Steam Akhir (ton)</th><td><?= htmlspecialchars($fmtNum($row['steam_akhir_ton'] ?? 0)) ?></td></tr>
<tr><th>Steam Total Pemakaian (ton)</th><td><?= htmlspecialchars($fmtNum($row['steam_total_pemakaian_ton'] ?? 0)) ?></td></tr>
<tr><th>Steam Rata-rata per Jam (ton)</th><td><?= htmlspecialchars($fmtNum($row['steam_rata_rata_per_jam_ton'] ?? 0)) ?></td></tr>
<tr><th>Steam KET</th><td><?= nl2br(htmlspecialchars($row['steam_ket'] ?? '')) ?></td></tr>
<tr><th>Cut Off Jam</th><td><?= htmlspecialchars($fmtTime($row['cut_off_jam'] ?? null)) ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
<tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
