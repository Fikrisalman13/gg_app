<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !air_dryer_table_exists($conn)) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: air_dryer.php');
    exit;
}

$sheet = air_dryer_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: air_dryer.php');
    exit;
}

$tanggal = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$tanggalDisplay = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y');
$hours = air_dryer_hours();
$cells = air_dryer_get_sheet_cells($conn, $tanggal);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h1 class="m-0">Detail Pencatatan Air Dryer Weaving</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <?php if ($canEdit): ?>
                        <a href="edit_air_dryer.php?id=<?= urlencode((string)$id) ?>" class="btn btn-warning btn-sm mr-1"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="air_dryer.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center justify-content-between">
                    <h3 class="card-title m-0"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <div>
                        <?php if ($canEdit): ?>
                            <a href="edit_air_dryer.php?id=<?= urlencode((string)$id) ?>" class="btn btn-warning btn-sm text-dark"><i class="fas fa-edit"></i> Edit</a>
                        <?php endif; ?>
                        <a href="air_dryer.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tr><th width="20%">Tanggal</th><td><?= htmlspecialchars(air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></td></tr>
                            <tr><th>Petugas</th><td><?= htmlspecialchars(air_dryer_petugas_for_sheet_query($conn, $tanggal)) ?: '-' ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($sheet['Keterangan'] ?? '-') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($sheet['CreatBy'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(air_dryer_fmt_date($sheet['CreatAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($sheet['UpdateBy'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars(air_dryer_fmt_date($sheet['UpdateAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                        </table>
                    </div>
                    <div class="air-report-wrap">
                        <div class="air-report-panel">
                            <table class="air-report-table">
                                <colgroup>
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 20%;">
                                </colgroup>
                                <thead>
                                    <tr><th colspan="10" class="sheet-title">PENCATATAN AIR DRYER WEAVING</th></tr>
                                    <tr>
                                        <th colspan="9" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars($tanggalDisplay) ?></th>
                                        <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-WV-017</th>
                                    </tr>
                                    <tr>
                                        <th class="head-blue" rowspan="3">Jam<br>Pengecekan</th>
                                        <th class="head-blue" colspan="4">Air Dryer 1</th>
                                        <th class="head-blue" colspan="4">Air Dryer 2</th>
                                        <th class="head-blue" rowspan="3">Petugas</th>
                                    </tr>
                                    <tr>
                                        <th class="head-blue" colspan="2">Temperatur Air</th>
                                        <th class="head-blue" colspan="2">Tekanan Air</th>
                                        <th class="head-blue" colspan="2">Temperatur Air</th>
                                        <th class="head-blue" colspan="2">Tekanan Air</th>
                                    </tr>
                                    <tr>
                                        <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                                        <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                                        <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                                        <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hours as $hour): $row = $cells[$hour] ?? []; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($hour) ?></td>
                                            <td><?= htmlspecialchars($row['ad1_temp_in'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad1_temp_out'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad1_press_in'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad1_press_out'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad2_temp_in'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad2_temp_out'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad2_press_in'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['ad2_press_out'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['petugas'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="keterangan-row">
                                        <td style="background:#b7b7b7; font-weight:700; text-align:center;">KETERANGAN</td>
                                        <td colspan="9" style="text-align:left; padding:4px 8px; background:#fff; font-weight:500; font-size:11px;"><?= htmlspecialchars(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.air-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
.air-report-panel{background:#fff;border:1px solid #64748b;box-shadow:0 1px 3px rgba(15,23,42,.08);min-width:1060px}
.air-report-table{border-collapse:collapse;background:#fff;width:100%;table-layout:fixed;font-size:11px}
.air-report-table th,.air-report-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:23px}
.air-report-table .sheet-title{font-size:14px;font-weight:800;background:#fff}
.air-report-table .date-title{text-align:left;background:#fff;font-weight:700}
.air-report-table .date-title.text-center{text-align:center}
.air-report-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase;font-size:10px;line-height:1.15}
</style>
