<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: lpg_skid_tank.php'); exit; }
$sql = "SELECT h.*, m.nama AS tank_nama FROM dbo.lpg_skid_tank_harian h LEFT JOIN dbo.lpg_skid_tank_master m ON m.kode=h.tank_kode WHERE h.id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: lpg_skid_tank.php'); exit; }
$fmtNum=function($v){ return is_numeric($v)?number_format((float)$v,2,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail LPG Skid Tank</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="lpg_skid_tank.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>Tank</th><td><?= htmlspecialchars($row['tank_nama'] ?? $row['tank_kode'] ?? '') ?></td></tr>
<tr><th>Pemakaian (Kg)</th><td><?= htmlspecialchars($fmtNum($row['pemakaian_kg'] ?? 0)) ?></td></tr>
<tr><th>Harga (Rp)</th><td><?= htmlspecialchars($fmtNum($row['harga_rp'] ?? 0)) ?></td></tr>
<tr><th>Biaya (Rp/hari)</th><td><?= htmlspecialchars($fmtNum($row['biaya_rp'] ?? 0)) ?></td></tr>
<tr><th>Ket</th><td><?= htmlspecialchars($row['ket'] ?? '') ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>