<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { $_SESSION['error'] = 'Silakan login terlebih dahulu.'; header('Location: /gg_app/login.php'); exit; }
$menuId = 230; $permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) { $_SESSION['error'] = 'Anda tidak memiliki hak untuk melihat halaman ini.'; header('Location: /gg_app/index.php'); exit; }

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal === '') { $_SESSION['error'] = 'Tanggal tidak valid.'; header('Location: washing2.php'); exit; }

$w = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_watt_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];
$s = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_steam_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];
$m = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_water_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];

function numv($v){ if($v===null||$v==='') return '-'; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; }
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="wrapper"><div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Detail Washing 2</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3><a href="washing2.php" class="btn btn-secondary btn-sm float-right">Kembali</a></div><div class="card-body">
<table class="table table-bordered table-sm">
<tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($tanggal) ?></td></tr>
<tr class="table-primary"><th colspan="2">WATT PER HOUR METER</th></tr>
<tr><th>Watt Awal</th><td><?= htmlspecialchars(numv($w['WattAwal'] ?? null)) ?></td></tr>
<tr><th>Watt Akhir</th><td><?= htmlspecialchars(numv($w['WattAkhir'] ?? null)) ?></td></tr>
<tr><th>Operasional Mesin (Watt)</th><td><?= htmlspecialchars(numv($w['OperasionalMesin'] ?? null)) ?></td></tr>
<tr class="table-info"><th colspan="2">STEAM FLOW METER</th></tr>
<tr><th>Steam Awal</th><td><?= htmlspecialchars(numv($s['SteamAwal'] ?? null)) ?></td></tr>
<tr><th>Steam Akhir</th><td><?= htmlspecialchars(numv($s['SteamAkhir'] ?? null)) ?></td></tr>
<tr><th>Steam Pemakaian</th><td><?= htmlspecialchars(numv($s['SteamPemakaian'] ?? null)) ?></td></tr>
<tr class="table-success"><th colspan="2">WATER FLOW METER</th></tr>
<tr><th>Water Awal</th><td><?= htmlspecialchars(numv($m['WaterAwal'] ?? null)) ?></td></tr>
<tr><th>Water Akhir</th><td><?= htmlspecialchars(numv($m['WaterAkhir'] ?? null)) ?></td></tr>
<tr><th>Total Pemakaian</th><td><?= htmlspecialchars(numv($m['TotalPemakaian'] ?? null)) ?></td></tr>
<tr><th>Operasional Mesin (Water)</th><td><?= htmlspecialchars(numv($m['OperasionalMesin'] ?? null)) ?></td></tr>
<tr><th>Pemakaian Rata Rata/Jam</th><td><?= htmlspecialchars(numv($m['PemakaianRataPerJam'] ?? null)) ?></td></tr>
<tr><th>Keterangan</th><td><?= htmlspecialchars($m['Keterangan'] ?? '-') ?></td></tr>
<tr><th>Catatan</th><td><?= htmlspecialchars($m['Catatan'] ?? '-') ?></td></tr>
</table></div></div></div></section></div><?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?></div>
