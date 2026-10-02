<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanView');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !dryer_weaving_table_exists($conn)) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: dryer_weaving.php');
    exit;
}

$sheet = dryer_weaving_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: dryer_weaving.php');
    exit;
}

$tanggal = dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$cells = dryer_weaving_get_sheet_cells($conn, $tanggal, $sheet['Dryer_No'], $sheet['Ct_No']);
$shifts = dryer_weaving_shifts_for_sheet($cells);
$petugasByHour = dryer_weaving_petugas_by_hour_for_sheet($cells);
$sheet['Petugas'] = dryer_weaving_petugas_for_sheet($cells);
$items = dryer_weaving_items();
$hours = dryer_weaving_hours();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Detail Dryer Weaving</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="dryer_weaving.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tr><th width="20%">Tanggal</th><td><?= htmlspecialchars(dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></td></tr>
                            <tr><th>Dryer No</th><td><?= htmlspecialchars($sheet['Dryer_No'] ?? '-') ?></td></tr>
                            <tr><th>CT No</th><td><?= htmlspecialchars($sheet['Ct_No'] ?? '-') ?></td></tr>
                            <tr><th>Petugas</th><td><?= htmlspecialchars($sheet['Petugas'] ?? '-') ?></td></tr>
                            <tr><th>Shift</th><td><?= htmlspecialchars($sheet['ShiftName'] ?? '-') ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($sheet['Keterangan'] ?? '-') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($sheet['CreatBy'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(dryer_weaving_fmt_date($sheet['CreatAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($sheet['UpdateBy'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars(dryer_weaving_fmt_date($sheet['UpdateAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                        </table>
                    </div>
                    <div class="dryer-sheet-wrap">
                        <table class="dryer-sheet-table">
                            <thead>
                                <tr><th colspan="<?= 2 + count($hours) ?>" class="sheet-title">LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND</th></tr>
                                <tr>
                                    <th class="head-blue" rowspan="2">Item Check</th>
                                    <th class="head-blue" rowspan="2">Standard</th>
                                    <th class="head-blue" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th>
                                </tr>
                                <tr>
                                    <?php foreach ($hours as $hour): ?>
                                        <th class="head-blue"><?= htmlspecialchars(dryer_weaving_display_hour($hour)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $currentCategory = ''; ?>
                                <?php foreach ($items as $item): ?>
                                    <?php if ($item['category'] !== $currentCategory): $currentCategory = $item['category']; ?>
                                        <tr class="category-row"><td colspan="<?= 2 + count($hours) ?>"><?= htmlspecialchars($currentCategory) ?></td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="item-cell"><?= $item['item'] ?></td>
                                        <td class="standard-cell"><?= htmlspecialchars($item['standard']) ?></td>
                                        <?php foreach ($hours as $hour): $key = $item['key'] . '|' . $hour; ?>
                                            <td><?= htmlspecialchars($cells[$key]['nilai'] ?? '') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="2" class="category-row" style="text-align:center; font-weight:700;">SHIFT</td>
                                    <?php foreach ($hours as $hour): ?>
                                        <td><?= htmlspecialchars($shifts[$hour] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td colspan="2" class="category-row" style="text-align:center; font-weight:700;">PETUGAS</td>
                                    <?php foreach ($hours as $hour): ?>
                                        <td style="font-size:11px;"><?= htmlspecialchars($petugasByHour[$hour] ?? '') ?></td>
                                    <?php endforeach; ?>
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
.dryer-sheet-wrap{overflow:auto;border:1px solid #111;background:#fff}
.dryer-sheet-table{border-collapse:collapse;width:100%;min-width:1320px;font-size:12px}
.dryer-sheet-table th,.dryer-sheet-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:24px}
.dryer-sheet-table .sheet-title{font-size:16px;font-weight:800;background:#fff}
.dryer-sheet-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase}
.dryer-sheet-table .category-row td{background:#b7b7b7;font-weight:700;text-align:left}
.dryer-sheet-table .item-cell{text-align:left;min-width:150px}
.dryer-sheet-table .standard-cell{font-weight:600;min-width:90px}
</style>
