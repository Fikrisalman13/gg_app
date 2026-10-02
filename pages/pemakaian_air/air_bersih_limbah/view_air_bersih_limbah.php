<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/sync_air_bersih_limbah.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: air_bersih_limbah.php'); exit; }

$sql = "SELECT * FROM dbo.air_bersih_limbah_harian WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: air_bersih_limbah.php'); exit; }

$tanggalSync = abl_normalize_date($row['tanggal'] ?? '');
if ($tanggalSync !== '') {
    $sourceValues = abl_collect_source_values($conn, $tanggalSync);
    abl_sync_date($conn, $tanggalSync, $sourceValues);
    $row = abl_overlay_row_with_source_values($row, $sourceValues);
}

$fmtNum=function($v,$d=2){ return is_numeric($v)?number_format((float)$v,$d,'.',','):'-'; };
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('d-m-Y'); return $v?date('d-m-Y',strtotime((string)$v)):'-'; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Detail Air Bersih & Limbah</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="air_bersih_limbah.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body table-responsive"><table class="table table-sm table-bordered">
<tr><th width="35%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
<tr><th>Flow Meter Intake IPAB (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['flow_meter_intake_ipab_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Flow Meter Bak 2 ke IPAL (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['flow_meter_bak_dua_ke_ipal_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Flow Meter Bak 3 ke Bak 4 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Washing 1 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['washing_1_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Washing 2 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['washing_2_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Washing 3 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['washing_3_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Perble Range 1 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['perble_range_1_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Perble Range 2 (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['perble_range_2_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Pad Steam (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['pad_steam_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Jetdying/Sizing (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['jetdying_sizing_la_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Kantin/Mes/Pos Security (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['kantin_mes_pos_security_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Boiler (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['boiler_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Air MC Produksi dan lain-lain (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['air_mc_produksi_dan_lain_lain_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Weaving dan lain-lain (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['weaving_dan_lain_lain_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Buangan air produk ke IPAL (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['buangan_air_produk_ke_ipal_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Flowmeter Output IPAL (m3/hari)</th><td><?= htmlspecialchars($fmtNum($row['flowmeter_output_ipal_m3_hari'] ?? 0)) ?></td></tr>
<tr><th>Note</th><td><?= nl2br(htmlspecialchars($row['note'] ?? '')) ?></td></tr>
<tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
</table></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
