<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
$menuId=236; requireView($conn,$menuId); $themeColor=$_SESSION['Theme'] ?? 'primary';
$id=intval($_GET['id'] ?? 0); if($id<=0){ $_SESSION['error']='ID tidak valid.'; header('Location: listrik_gardu_induk.php'); exit; }
$stmt=sqlsrv_query($conn,"SELECT * FROM dbo.listrik_gardu_induk_harian WHERE id=?",[$id]);
$row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null; if($stmt) sqlsrv_free_stmt($stmt);
if(!$row){ $_SESSION['error']='Data tidak ditemukan.'; header('Location: listrik_gardu_induk.php'); exit; }
$fmtDate=function($v){ if($v instanceof DateTime) return $v->format('Y-m-d'); return $v?date('Y-m-d',strtotime((string)$v)):''; };
$fi=function($v,$d=3){ return is_numeric($v)?number_format((float)$v,$d,'.',','):''; };
?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1 class="m-0">Edit Listrik Gardu Induk</h1></div></section>
<section class="content"><div class="container-fluid"><div class="card"><div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Edit</h3><a href="listrik_gardu_induk.php" class="btn btn-light btn-sm ml-auto">Kembali</a></div>
<div class="card-body"><form method="POST" action="update_listrik_gardu_induk.php" autocomplete="off">
<input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
<div class="form-row"><div class="form-group col-md-4"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?>" required></div>
<div class="form-group col-md-4"><label>LVBP (kWh)</label><input type="text" name="lvbp_kwh" class="form-control" value="<?= htmlspecialchars($fi($row['lvbp_kwh'] ?? 0,3)) ?>" required></div>
<div class="form-group col-md-4"><label>VBP (kWh)</label><input type="text" name="vbp_kwh" class="form-control" value="<?= htmlspecialchars($fi($row['vbp_kwh'] ?? 0,3)) ?>" required></div></div>
<div class="form-row"><div class="form-group col-md-3"><label>KVARH</label><input type="text" name="kvarh" class="form-control" value="<?= htmlspecialchars($fi($row['kvarh'] ?? 0,3)) ?>"></div>
<div class="form-group col-md-3"><label>Cos Phi</label><input type="text" name="cos_phi" class="form-control" value="<?= htmlspecialchars($fi($row['cos_phi'] ?? 0,3)) ?>"></div>
<div class="form-group col-md-3"><label>Faktor Kali</label><input type="text" name="faktor_kali" class="form-control" value="<?= htmlspecialchars($fi($row['faktor_kali'] ?? 6000,3)) ?>"></div>
<div class="form-group col-md-3"><label>Rp/kWh</label><input type="text" name="rp_per_kwh" class="form-control" value="<?= htmlspecialchars($fi($row['rp_per_kwh'] ?? 1155.417,3)) ?>"></div></div>
<div class="form-row"><div class="form-group col-md-4"><label>PF Standar</label><input type="text" name="pf_standar" class="form-control" value="<?= htmlspecialchars($fi($row['pf_standar'] ?? 0.95,3)) ?>"></div>
<div class="form-group col-md-4"><label>Kapasitas KVA</label><input type="text" name="kapasitas_kva" class="form-control" value="<?= htmlspecialchars($fi($row['kapasitas_kva'] ?? 5190,2)) ?>"></div>
<div class="form-group col-md-4"><label>Keterangan</label><input type="text" name="ket" class="form-control" value="<?= htmlspecialchars($row['ket'] ?? '') ?>"></div></div>
<div class="form-group"><label>Note</label><input type="text" name="note" class="form-control" value="<?= htmlspecialchars($row['note'] ?? '') ?>"></div>
<button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button></form></div></div></div></section></div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

