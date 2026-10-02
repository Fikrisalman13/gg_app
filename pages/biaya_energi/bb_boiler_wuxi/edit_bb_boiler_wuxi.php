<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId=230; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: bb_boiler_wuxi.php'); exit; }
$stmt=sqlsrv_query($conn,"SELECT * FROM dbo.bb_boiler_wuxi_harian WHERE id=?",[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: bb_boiler_wuxi.php'); exit; }
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('Y-m-d'); return $v?date('Y-m-d',strtotime((string)$v)):''; };
$fi=function($v){ return is_numeric($v)?number_format((float)$v,2,'.',','):''; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Edit BB Boiler Wuxi</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Edit</h3><a href="bb_boiler_wuxi.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body"><form method="POST" action="update_bb_boiler_wuxi.php" autocomplete="off">
<input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
<div class="form-row"><div class="form-group col-md-4"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?>" required></div>
<div class="form-group col-md-4"><label>Pemakaian (Kg)</label><input type="text" name="pemakaian_kg" class="form-control" value="<?= htmlspecialchars($fi($row['pemakaian_kg'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>Harga/Kg (Rp)</label><input type="text" name="harga_rp_per_kg" class="form-control" value="<?= htmlspecialchars($fi($row['harga_rp_per_kg'] ?? 0)) ?>" required></div></div>
<div class="form-row"><div class="form-group col-md-4"><label>Total Biaya Boiler (Rp)</label><input type="text" name="total_biaya_boiler_wuxi_rp" class="form-control" value="<?= htmlspecialchars($fi($row['total_biaya_boiler_wuxi_rp'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>Extractor (Kg)</label><input type="text" name="extractor_kg" class="form-control" value="<?= htmlspecialchars($fi($row['extractor_kg'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>C/Grate (Kg)</label><input type="text" name="cgrate_kg" class="form-control" value="<?= htmlspecialchars($fi($row['cgrate_kg'] ?? 0)) ?>" required></div></div>
<div class="form-row"><div class="form-group col-md-4"><label>FLY ASH (Kg)</label><input type="text" name="fly_ash_kg" class="form-control" value="<?= htmlspecialchars($fi($row['fly_ash_kg'] ?? 0)) ?>" required></div>
<div class="form-group col-md-4"><label>Keterangan</label><input type="text" name="ket" class="form-control" value="<?= htmlspecialchars($row['ket'] ?? '') ?>"></div>
<div class="form-group col-md-4"><label>Note</label><input type="text" name="note" class="form-control" value="<?= htmlspecialchars($row['note'] ?? '') ?>"></div></div>
<button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button></form></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
