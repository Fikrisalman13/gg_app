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
$param = trim($_GET['param'] ?? '');

$map = [
    'air_boiler' => ['table' => 'air_boiler_actom', 'label' => 'Air Boiler 21 Ton Actom'],
    'steam_boiler' => ['table' => 'steam_boiler_actom', 'label' => 'Steam Boiler 21 Ton Actom'],
    'air_analog' => ['table' => 'air_analog_actom', 'label' => 'Air Analog 21 Ton Actom'],
    'analog_steam' => ['table' => 'analog_steam_actom', 'label' => 'Analog Steam 21 Ton Actom'],
];

if ($id <= 0 || !isset($map[$param])) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: 21tonactom.php');
    exit;
}

$table = $map[$param]['table'];
$label = $map[$param]['label'];

$sql = "SELECT id, tanggal, awal, ahir, total_pemakaian, pemakaianrata2perjam
        FROM dbo.$table WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: 21tonactom.php');
    exit;
}

$fmtInput = function($v){
    if ($v === null || $v === '') return '';
    return is_numeric($v) ? number_format((float)$v, 2, '.', ',') : (string)$v;
};
$fmtDate = function($v){
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    return is_string($v) ? $v : '';
};
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Edit <?= htmlspecialchars($label) ?></h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                    <a href="21tonactom.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_21tonactom.php" autocomplete="off" id="formEdit21ton">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                        <input type="hidden" name="param" value="<?= htmlspecialchars($param) ?>">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Awal</label>
                            <input type="text" class="form-control num-only" name="awal" id="editAwal" value="<?= htmlspecialchars($fmtInput($row['awal'] ?? null)) ?>" data-decimals="2" required>
                        </div>
                        <div class="form-group">
                            <label>Akhir</label>
                            <input type="text" class="form-control num-only" name="ahir" id="editAkhir" value="<?= htmlspecialchars($fmtInput($row['ahir'] ?? null)) ?>" data-decimals="2" required>
                        </div>
                        <div class="form-group">
                            <label>Total Pemakaian</label>
                            <input type="text" class="form-control" id="editTotal" value="<?= htmlspecialchars($fmtInput($row['total_pemakaian'] ?? null)) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Pemakaian Rata Rata Per Jam</label>
                            <input type="text" class="form-control" id="editRata" value="<?= htmlspecialchars($fmtInput($row['pemakaianrata2perjam'] ?? null)) ?>" readonly>
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
  function recalc(){
    var awal = parseNum($('#editAwal').val());
    var akhir = parseNum($('#editAkhir').val());
    if (isNaN(awal) || isNaN(akhir)) { $('#editTotal').val(''); $('#editRata').val(''); return; }
    var total = akhir - awal;
    $('#editTotal').val(fmtInput(total, 2));
    $('#editRata').val(fmtInput(total / 24, 2));
  }
  $(document).on('input','.num-only', function(){ this.value=this.value.replace(/[^0-9.,]/g,''); });
  $('#editAwal, #editAkhir').on('input blur', recalc);
});
</script>
