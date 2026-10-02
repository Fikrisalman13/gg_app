<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: lpg_skid_tank.php'); exit; }
$sql = "SELECT * FROM dbo.lpg_skid_tank_harian WHERE id=?";
$stmt=sqlsrv_query($conn,$sql,[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: lpg_skid_tank.php'); exit; }
$tanks=[]; $st=sqlsrv_query($conn,"SELECT kode,nama FROM dbo.lpg_skid_tank_master WHERE is_active=1 ORDER BY urutan,kode");
if($st){ while($t=sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC)) $tanks[]=$t; sqlsrv_free_stmt($st); }
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('Y-m-d'); return $v?date('Y-m-d',strtotime((string)$v)):''; };
$fi=function($v){ return is_numeric($v)?number_format((float)$v,2,'.',','):''; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Edit LPG Skid Tank</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Edit</h3><a href="lpg_skid_tank.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body"><form method="POST" action="update_lpg_skid_tank.php" autocomplete="off">
<input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
<div class="form-row">
<div class="form-group col-md-4"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?>" required></div>
<div class="form-group col-md-4"><label>Tank</label><select name="tank_kode" class="form-control" required><?php foreach($tanks as $t): ?><option value="<?= htmlspecialchars($t['kode']) ?>" <?= (($row['tank_kode'] ?? '') === $t['kode']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
<div class="form-group col-md-4"><label>Keterangan</label><input type="text" name="ket" class="form-control" value="<?= htmlspecialchars($row['ket'] ?? '') ?>"></div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Pemakaian (Kg)</label><input type="text" name="pemakaian_kg" class="form-control" value="<?= htmlspecialchars($fi($row['pemakaian_kg'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>Harga (Rp)</label><input type="text" name="harga_rp" class="form-control" value="<?= htmlspecialchars($fi($row['harga_rp'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>Note</label><input type="text" name="note" class="form-control" value="<?= htmlspecialchars($row['note'] ?? '') ?>"></div>
</div>
<button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button></form></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>