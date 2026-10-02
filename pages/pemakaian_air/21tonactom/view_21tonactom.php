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
    'air_boiler' => ['table' => 'air_boiler_actom', 'label' => 'Air Boiler 21 Ton Actom', 'unit' => 'M3'],
    'steam_boiler' => ['table' => 'steam_boiler_actom', 'label' => 'Steam Boiler 21 Ton Actom', 'unit' => 'TON'],
    'air_analog' => ['table' => 'air_analog_actom', 'label' => 'Air Analog 21 Ton Actom', 'unit' => 'M3'],
    'analog_steam' => ['table' => 'analog_steam_actom', 'label' => 'Analog Steam 21 Ton Actom', 'unit' => 'Ton'],
];

if ($id <= 0 || !isset($map[$param])) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: 21tonactom.php');
    exit;
}

$table = $map[$param]['table'];
$label = $map[$param]['label'];

$sql = "SELECT id, tanggal, awal, ahir, total_pemakaian, pemakaianrata2perjam, creatby, creatat, updateby, updateat
        FROM dbo.$table WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: 21tonactom.php');
    exit;
}

$fmtNum = function($v){
    if ($v === null || $v === '') return '-';
    return is_numeric($v) ? number_format((float)$v, 2, '.', ',') : (string)$v;
};
$fmtDateTime = function($v){
    if ($v instanceof DateTime) return $v->format('Y-m-d H:i:s');
    if (is_string($v) && $v !== '') return (string)$v;
    return '-';
};
$fmtDate = function($v){
    if ($v instanceof DateTime) return $v->format('Y-m-d');
    if (is_string($v) && $v !== '') return (string)$v;
    return '-';
};
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Detail <?= htmlspecialchars($label) ?></h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="21tonactom.php" class="btn btn-light btn-sm ml-auto">Kembali</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars($fmtDate($row['tanggal'] ?? null)) ?></td></tr>
                            <tr><th>Awal</th><td><?= htmlspecialchars($fmtNum($row['awal'] ?? null)) ?></td></tr>
                            <tr><th>Akhir</th><td><?= htmlspecialchars($fmtNum($row['ahir'] ?? null)) ?></td></tr>
                            <tr><th>Total Pemakaian</th><td><?= htmlspecialchars($fmtNum($row['total_pemakaian'] ?? null)) ?></td></tr>
                            <tr><th>Pemakaian Rata Per Jam</th><td><?= htmlspecialchars($fmtNum($row['pemakaianrata2perjam'] ?? null)) ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($row['creatby'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars($fmtDateTime($row['creatat'] ?? null)) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($row['updateby'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars($fmtDateTime($row['updateat'] ?? null)) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
