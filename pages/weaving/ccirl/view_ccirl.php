<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanView');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !ccirl_table_exists($conn)) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: ccirl.php');
    exit;
}
$sheet = ccirl_get_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: ccirl.php');
    exit;
}

$tanggal = ccirl_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$cells = ccirl_get_cells($conn, $tanggal, $sheet['Compressor_No']);
$items = ccirl_items();
$hours = ccirl_hours();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1>Detail CCIRL</h1></div></section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="ccirl.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <div class="cc-sheet-wrap">
                        <table class="cc-sheet-table">
                            <thead>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="sheet-title">CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET</th></tr>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">COMPRESSOR NO : <?= htmlspecialchars($sheet['Compressor_No'] ?? '') ?></th></tr>
                                <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">TANGGAL : <?= htmlspecialchars(ccirl_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></th></tr>
                                <tr><th class="head-grey" rowspan="2">Status Message</th><th class="head-grey" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th></tr>
                                <tr><?php foreach ($hours as $hour): ?><th class="head-grey"><?= htmlspecialchars(ccirl_display_hour($hour)) ?></th><?php endforeach; ?></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="status-cell"><?= $item['label'] ?></td>
                                        <?php foreach ($hours as $hour): $key = $item['key'] . '|' . $hour; ?>
                                            <td><?= htmlspecialchars($cells[$key]['nilai'] ?? '') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td class="petugas-title">PETUGAS</td>
                                    <?php foreach ($hours as $hour): $petugas = ccirl_petugas_for_hour($cells, $items, $hour); ?>
                                        <td><?= htmlspecialchars($petugas) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="petugas-title" style="background:#d9d9d9; font-weight:700;">KETERANGAN</td>
                                    <td colspan="<?= count($hours) ?>" style="text-align:left; padding:4px 8px; background:#fff; font-weight:500;"><?= htmlspecialchars(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>
<style>
.cc-sheet-wrap{overflow:auto;border:1px solid #111;background:#fff}
.cc-sheet-table{border-collapse:collapse;width:100%;min-width:1350px;font-size:11px}
.cc-sheet-table th,.cc-sheet-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:22px}
.cc-sheet-table .sheet-title{font-size:15px;font-weight:800;background:#fff}
.cc-sheet-table .date-title{text-align:left;background:#fff;font-weight:700}
.cc-sheet-table .head-grey,.cc-sheet-table .status-cell,.cc-sheet-table .petugas-title{background:#d9d9d9;font-weight:700}
.cc-sheet-table .status-cell{text-align:left}
</style>
