<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: listrik_perbagian.php'); exit; }

$sql = "SELECT * FROM dbo.listrik_perbagian_harian WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: listrik_perbagian.php'); exit; }

$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Listrik Per Bagian</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="listrik_perbagian.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="35%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>KWH PLN</th><td><?= htmlspecialchars($fmtNum($row['kwh_pln'] ?? 0)) ?></td></tr>
<tr><th>KWH Utility AMP ACB</th><td><?= htmlspecialchars($fmtNum($row['kwh_utility_amp_acb'] ?? 0)) ?></td></tr>
<tr><th>KWH Utility kWh/hari</th><td><?= htmlspecialchars($fmtNum($row['kwh_utility_kwh_hari'] ?? 0)) ?></td></tr>
<tr><th>DF AMP ACB</th><td><?= htmlspecialchars($fmtNum($row['df_amp_acb'] ?? 0)) ?></td></tr>
<tr><th>DF kWh/hari</th><td><?= htmlspecialchars($fmtNum($row['df_kwh_hari'] ?? 0)) ?></td></tr>
<tr><th>Weaving 1 AMP ACB</th><td><?= htmlspecialchars($fmtNum($row['weaving1_amp_acb'] ?? 0)) ?></td></tr>
<tr><th>Weaving 1 kWh/hari</th><td><?= htmlspecialchars($fmtNum($row['weaving1_kwh_hari'] ?? 0)) ?></td></tr>
<tr><th>Weaving 2 AMP ACB</th><td><?= htmlspecialchars($fmtNum($row['weaving2_amp_acb'] ?? 0)) ?></td></tr>
<tr><th>Weaving 2 kWh/hari</th><td><?= htmlspecialchars($fmtNum($row['weaving2_kwh_hari'] ?? 0)) ?></td></tr>
<tr><th>Jumlah kWh/hari</th><td><?= htmlspecialchars($fmtNum($row['jumlah_kwh_hari'] ?? 0)) ?></td></tr>
<tr><th>Jumlah Ampere</th><td><?= htmlspecialchars($fmtNum($row['jumlah_ampere'] ?? 0)) ?></td></tr>
<tr><th>KWH/JAM</th><td><?= htmlspecialchars($fmtNum($row['kwh_per_jam'] ?? 0)) ?></td></tr>
<tr><th>Efisiensi (%)</th><td><?= htmlspecialchars($fmtNum($row['efisiensi_persen'] ?? 0)) ?></td></tr>
<tr><th>Faktor Konversi</th><td><?= htmlspecialchars($fmtNum($row['faktor_konversi'] ?? 0, 2)) ?></td></tr>
<tr><th>Kapasitas Pembagi</th><td><?= htmlspecialchars($fmtNum($row['kapasitas_pembagi'] ?? 0, 2)) ?></td></tr>
<tr><th>Ket</th><td><?= nl2br(htmlspecialchars($row['ket'] ?? '')) ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
<tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
