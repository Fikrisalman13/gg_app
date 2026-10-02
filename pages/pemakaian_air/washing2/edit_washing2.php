<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) { $_SESSION['error']='Silakan login terlebih dahulu.'; header('Location:/gg_app/login.php'); exit; }
$menuId=230; $permissions=getPermissions($conn,$_SESSION['GroupId'],$menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) { $_SESSION['error']='Anda tidak memiliki hak untuk mengedit data.'; header('Location:washing2.php'); exit; }

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal==='') { $_SESSION['error']='Tanggal tidak valid.'; header('Location:washing2.php'); exit; }
$w = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_watt_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];
$s = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_steam_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];
$m = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.washing2_water_meter WHERE Tanggal=?", [$tanggal]), SQLSRV_FETCH_ASSOC) ?: [];

function f($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; }
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/sidebar.php');
$themeColor=$_SESSION['Theme']??'primary';
?>
<div class="wrapper"><div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Edit Washing 2</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3></div>
<form method="post" action="update_washing2.php" autocomplete="off"><input type="hidden" name="tanggal_lama" value="<?= htmlspecialchars($tanggal) ?>"><div class="card-body">
<div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>" required></div>
<h6 class="mt-3"><b>WATT PER HOUR METER</b></h6>
<div class="row"><div class="col-md-4 form-group"><label>Watt Awal</label><input type="text" id="editWattAwal" name="watt_awal" class="form-control num-only" value="<?= htmlspecialchars(f($w['WattAwal']??null)) ?>"></div><div class="col-md-4 form-group"><label>Watt Akhir</label><input type="text" id="editWattAkhir" name="watt_akhir" class="form-control num-only" value="<?= htmlspecialchars(f($w['WattAkhir']??null)) ?>"></div><div class="col-md-4 form-group"><label>Operasional Mesin</label><input type="text" id="editWattOperasional" name="watt_operasional" class="form-control" value="<?= htmlspecialchars(f($w['OperasionalMesin']??null)) ?>" readonly></div></div>
<h6 class="mt-2"><b>STEAM FLOW METER</b></h6>
<div class="row"><div class="col-md-4 form-group"><label>Steam Awal</label><input type="text" id="editSteamAwal" name="steam_awal" class="form-control num-only" value="<?= htmlspecialchars(f($s['SteamAwal']??null)) ?>"></div><div class="col-md-4 form-group"><label>Steam Akhir</label><input type="text" id="editSteamAkhir" name="steam_akhir" class="form-control num-only" value="<?= htmlspecialchars(f($s['SteamAkhir']??null)) ?>"></div><div class="col-md-4 form-group"><label>Steam Pemakaian</label><input type="text" id="editSteamPemakaian" name="steam_pemakaian" class="form-control" value="<?= htmlspecialchars(f($s['SteamPemakaian']??null)) ?>" readonly></div></div>
<h6 class="mt-2"><b>WATER FLOW METER</b></h6>
<div class="row"><div class="col-md-4 form-group"><label>Water Awal</label><input type="text" id="editWaterAwal" name="water_awal" class="form-control num-only" value="<?= htmlspecialchars(f($m['WaterAwal']??null)) ?>"></div><div class="col-md-4 form-group"><label>Water Akhir</label><input type="text" id="editWaterAkhir" name="water_akhir" class="form-control num-only" value="<?= htmlspecialchars(f($m['WaterAkhir']??null)) ?>"></div><div class="col-md-4 form-group"><label>Total Pemakaian</label><input type="text" id="editWaterTotal" name="total_pemakaian" class="form-control" value="<?= htmlspecialchars(f($m['TotalPemakaian']??null)) ?>" readonly></div></div>
<div class="row"><div class="col-md-4 form-group"><label>Operasional Mesin</label><input type="text" id="editWaterOperasional" name="water_operasional" class="form-control num-only" value="<?= htmlspecialchars(f($m['OperasionalMesin']??null)) ?>"></div><div class="col-md-4 form-group"><label>Pemakaian Rata/Jam</label><input type="text" id="editWaterRata" name="pemakaian_rata" class="form-control" value="<?= htmlspecialchars(f($m['PemakaianRataPerJam']??null)) ?>" readonly></div></div>
<div class="form-group"><label>Keterangan</label><textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($m['Keterangan']??'') ?></textarea></div>
<div class="form-group"><label>Catatan</label><textarea name="catatan" class="form-control" rows="2"><?= htmlspecialchars($m['Catatan']??'') ?></textarea></div>
</div><div class="card-footer"><a href="washing2.php" class="btn btn-secondary">Batal</a> <button type="submit" class="btn btn-primary">Simpan</button></div></form>
</div></div></section></div><?php include($_SERVER['DOCUMENT_ROOT'].'/gg_app/includes/footer.php'); ?></div>
<script>
function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
function recalcEditWattOp(){ var a=parseNum($('#editWattAwal').val()); var b=parseNum($('#editWattAkhir').val()); if(isNaN(a)||isNaN(b)){ $('#editWattOperasional').val(''); return; } $('#editWattOperasional').val(fmtInput((b-a),2)); }
function recalcEditSteamPem(){ var a=parseNum($('#editSteamAwal').val()); var b=parseNum($('#editSteamAkhir').val()); if(isNaN(a)||isNaN(b)){ $('#editSteamPemakaian').val(''); return; } $('#editSteamPemakaian').val(fmtInput((b-a),2)); }
function recalcEditWater(){ var a=parseNum($('#editWaterAwal').val()); var b=parseNum($('#editWaterAkhir').val()); var o=parseNum($('#editWaterOperasional').val()); if(isNaN(a)||isNaN(b)){ $('#editWaterTotal').val(''); $('#editWaterRata').val(''); return; } var t=(b-a); $('#editWaterTotal').val(fmtInput(t,2)); if(!isNaN(o) && o>0){ $('#editWaterRata').val(fmtInput((t/o),2)); } else { $('#editWaterRata').val(''); } }
$(document).on('input','.num-only',function(){this.value=this.value.replace(/[^0-9.,]/g,'');});
$('#editWattAwal,#editWattAkhir').on('input blur', recalcEditWattOp);
$('#editSteamAwal,#editSteamAkhir').on('input blur', recalcEditSteamPem);
$('#editWaterAwal,#editWaterAkhir,#editWaterOperasional').on('input blur', recalcEditWater);
$(document).ready(function(){ recalcEditWattOp(); recalcEditSteamPem(); recalcEditWater(); });
</script>
