<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: kimia_weaving.php');
    exit;
}

$sql = "SELECT h.id, h.tanggal, h.master_id, h.pakai_kg, h.harga_rp, h.biaya_rp, h.catatan,
               m.nama_item, m.grup_laporan, m.satuan_pakai
        FROM dbo.kimia_weaving_harian h
        INNER JOIN dbo.kimia_weaving_master m ON m.id = h.master_id
        WHERE h.id=?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: kimia_weaving.php');
    exit;
}

$fmtInput = function($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$fmtDate = function($v){ if($v instanceof DateTime) return $v->format('Y-m-d'); return is_string($v)?$v:''; };
?>

<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1 class="m-0">Edit Kimia Weaving</h1></div></section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                    <a href="kimia_weaving.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_kimia_weaving.php" id="formEditKimiaIpal" autocomplete="off">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$row['id']) ?>">
                        <div class="form-group">
                            <label>Parameter</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['nama_item'] ?? '') ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Grup</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['grup_laporan'] ?? '') ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Pakai (<?= htmlspecialchars($row['satuan_pakai'] ?? 'Kg') ?>)</label>
                            <input type="text" class="form-control num-only" name="pakai_kg" id="editPakai" data-decimals="2" value="<?= htmlspecialchars($fmtInput($row['pakai_kg'] ?? null)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Harga (Rp)</label>
                            <input type="text" class="form-control num-only" name="harga_rp" id="editHarga" data-decimals="2" value="<?= htmlspecialchars($fmtInput($row['harga_rp'] ?? null)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Biaya (Rp)</label>
                            <input type="text" class="form-control" id="editBiaya" value="<?= htmlspecialchars($fmtInput($row['biaya_rp'] ?? null)) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Catatan</label>
                            <textarea class="form-control" name="catatan" rows="2"><?= htmlspecialchars($row['catatan'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script>
$(function(){
  function parseNum(v){ if(v===null||v===undefined) return NaN; v=String(v).trim().replace(/,/g,''); if(v==='') return NaN; var n=Number(v); return isNaN(n)?NaN:n; }
  function fmtInput(v,d){ var n=parseNum(v); return isNaN(n)?'':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
  function recalc(){ var p=parseNum($('#editPakai').val()); var h=parseNum($('#editHarga').val()); if(isNaN(p)||isNaN(h)){ $('#editBiaya').val(''); return; } $('#editBiaya').val(fmtInput((p*h), 2)); }
  $(document).on('input','.num-only', function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $(document).on('blur','.num-only', function(){ var decimals=parseInt($(this).data('decimals') || 2, 10); var n=parseNum(this.value); if(!isNaN(n)) this.value=fmtInput(n, decimals); });
  $('#editPakai,#editHarga').on('input blur', recalc);
});
</script>


