<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');

$id = $_GET['id'] ?? '';
$tanggalParam = trim($_GET['tanggal'] ?? '');
$weavingParam = intval($_GET['weaving'] ?? 0);
$compressorParam = intval($_GET['compressor_no'] ?? 0);

if (!temp_compressor_v2_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel dbo.temp_compressor_v2 belum tersedia.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_v2_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $weavingParam > 0 && $compressorParam > 0) {
    $sheet = temp_compressor_v2_resolve_sheet_by_params($conn, $tanggalParam, $weavingParam, $compressorParam);
}

if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$resolvedId = (int)($sheet['Id'] ?? $id);
$tanggal = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$tanggalDisplay = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y');
$weaving = (int)($sheet['Weaving'] ?? 1);
$compressorNo = (int)($sheet['Compressor_No'] ?? 1);
$hours = temp_compressor_v2_hours();
$cells = temp_compressor_v2_get_sheet_cells($conn, $tanggal, $weaving, $compressorNo);

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
                    <h1 class="m-0">Detail Check Sheet Kompressor Sullair (V2)</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <?php if ($canEdit): ?>
                        <a href="edit_temp_compressor_v2.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>&weaving=<?= $weaving ?>&compressor_no=<?= $compressorNo ?>" class="btn btn-warning btn-sm mr-1"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="temp_compressor_v2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                            <a href="edit_temp_compressor_v2.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>&weaving=<?= $weaving ?>&compressor_no=<?= $compressorNo ?>" class="btn btn-warning btn-sm text-dark"><i class="fas fa-edit"></i> Edit</a>
                        <?php endif; ?>
                        <a href="temp_compressor_v2.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tr><th width="20%">Tanggal</th><td><?= htmlspecialchars($tanggalDisplay) ?></td></tr>
                            <tr><th>Weaving</th><td>Weaving <?= htmlspecialchars((string)$weaving) ?></td></tr>
                            <tr><th>Compressor No</th><td>Compressor <?= htmlspecialchars((string)$compressorNo) ?></td></tr>
                            <tr><th>Pelaksana</th><td><?= htmlspecialchars(temp_compressor_v2_pelaksana_for_sheet_query($conn, $tanggal, $weaving, $compressorNo)) ?: '-' ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($sheet['Keterangan'] ?? '-') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($sheet['CreatBy'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(temp_compressor_v2_fmt_date($sheet['CreatAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($sheet['UpdateBy'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars(temp_compressor_v2_fmt_date($sheet['UpdateAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                        </table>
                    </div>

                    <div class="compressor-report-wrap">
                        <table class="compressor-report-table">
                            <colgroup>
                                <col style="width: 60px;">
                                <col style="width: 55px;">
                                <col style="width: 55px;">
                                <col style="width: 55px;">
                                <col style="width: 55px;">
                                <col style="width: 55px;">
                                <col style="width: 60px;">
                                <col style="width: 70px;">
                                <col style="width: 60px;">
                                <col style="width: 60px;">
                                <col style="width: 60px;">
                                <col style="width: 60px;">
                                <col style="width: 180px;">
                            </colgroup>
                            <thead>
                                <tr><th colspan="13" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                                <tr>
                                    <th colspan="6" class="meta-title">
                                        COMPRESSOR NO: <strong><?= $compressorNo ?></strong> (1 / 2 / 3) &nbsp;&nbsp;&nbsp;&nbsp; WEAVING: <strong><?= $weaving ?></strong> (1 / 2)
                                    </th>
                                    <th colspan="5" class="meta-title text-center">
                                        TANGGAL: <strong><?= htmlspecialchars($tanggalDisplay) ?></strong>
                                    </th>
                                    <th colspan="2" class="meta-title text-center" style="white-space: nowrap;">
                                        SUM-FM-THK-016
                                    </th>
                                </tr>
                                <tr>
                                    <th class="head-blue" rowspan="2">JAM</th>
                                    <th class="head-blue" colspan="2">PRESSURE (BAR)</th>
                                    <th class="head-blue" colspan="3">TEMPERATURE</th>
                                    <th class="head-blue" rowspan="2">DRYER<br>(&deg;C)</th>
                                    <th class="head-blue" rowspan="2">ARUS<br>LISTRIK (A)</th>
                                    <th class="head-blue" colspan="4">AIR COOLING</th>
                                    <th class="head-blue" rowspan="2">PELAKSANA</th>
                                </tr>
                                <tr>
                                    <th class="head-blue">P1</th>
                                    <th class="head-blue">P2</th>
                                    <th class="head-blue">T1</th>
                                    <th class="head-blue">T2</th>
                                    <th class="head-blue">T3</th>
                                    <th class="head-blue">PRESS. IN</th>
                                    <th class="head-blue">PRESS. OUT</th>
                                    <th class="head-blue">TEMP. IN</th>
                                    <th class="head-blue">TEMP. OUT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hours as $hour): ?>
                                    <?php $cell = $cells[$hour] ?? []; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars(temp_compressor_v2_display_hour($hour)) ?></strong></td>
                                        <td><?= htmlspecialchars($cell['pressure_p1'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['pressure_p2'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['temp_t1'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['temp_t2'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['temp_t3'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['dryer_c'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['arus_a'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['press_in'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['press_out'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['temp_in'] ?? '') ?: '-' ?></td>
                                        <td><?= htmlspecialchars($cell['temp_out'] ?? '') ?: '-' ?></td>
                                        <td class="text-left"><?= htmlspecialchars($cell['pelaksana'] ?? '') ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
.compressor-report-wrap {
    overflow: auto;
    background: #f8fafc;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.compressor-report-table {
    border-collapse: collapse;
    background: #fff;
    width: 1050px;
    table-layout: fixed;
    font-size: 12px;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.compressor-report-table th, .compressor-report-table td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    height: 26px;
}
.compressor-report-table .sheet-title {
    font-size: 14px;
    font-weight: 800;
    background: #fff;
    letter-spacing: .5px;
}
.compressor-report-table .meta-title {
    font-size: 11px;
    background: #fff;
    padding: 4px 6px;
    text-align: left;
}
.compressor-report-table .head-blue {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    font-size: 10px;
    line-height: 1.15;
}
</style>
