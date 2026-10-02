<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');

$id = $_GET['id'] ?? '';
$tanggalParam = trim($_GET['tanggal'] ?? '');

if (!temp_compressor_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel dbo.temp_compressor belum tersedia.';
    header('Location: temp_compressor.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '') {
    $sheet = temp_compressor_resolve_sheet_by_date($conn, $tanggalParam);
}

if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: temp_compressor.php');
    exit;
}

$resolvedId = (int)($sheet['Id'] ?? $id);
$tanggal = temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$tanggalDisplay = temp_compressor_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y');
$hours = temp_compressor_hours();
$cells = temp_compressor_get_sheet_cells($conn, $tanggal);

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
                    <h1 class="m-0">Detail Check Sheet Kompressor Sullair</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <?php if ($canEdit): ?>
                        <a href="edit_temp_compressor.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>" class="btn btn-warning btn-sm mr-1"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="temp_compressor.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                            <a href="edit_temp_compressor.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>" class="btn btn-warning btn-sm text-dark"><i class="fas fa-edit"></i> Edit</a>
                        <?php endif; ?>
                        <a href="temp_compressor.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tr><th width="20%">Tanggal</th><td><?= htmlspecialchars($tanggalDisplay) ?></td></tr>
                            <tr><th>Petugas</th><td><?= htmlspecialchars(temp_compressor_petugas_for_sheet_query($conn, $tanggal)) ?: '-' ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($sheet['Keterangan'] ?? '-') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($sheet['CreatBy'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(temp_compressor_fmt_date($sheet['CreatAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($sheet['UpdateBy'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars(temp_compressor_fmt_date($sheet['UpdateAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
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
                                <col style="width: 50px;">
                                <col style="width: 50px;">
                                <col style="width: 50px;">
                                <col style="width: 50px;">
                                <col style="width: 50px;">
                                <col style="width: 60px;">
                                <col style="width: 55px;">
                                <col style="width: 55px;">
                                <col style="width: 180px;">
                            </colgroup>
                            <thead>
                                <tr><th colspan="15" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                                <tr>
                                    <th colspan="14" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars($tanggalDisplay) ?></th>
                                    <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-016</th>
                                </tr>
                                <tr>
                                    <th class="head-blue" rowspan="2">Jam</th>
                                    <th class="head-blue" colspan="2">Compressor 1</th>
                                    <th class="head-blue" colspan="2">Compressor 2</th>
                                    <th class="head-blue" rowspan="2">Amper</th>
                                    <th class="head-blue" colspan="2">Pressure Bar</th>
                                    <th class="head-blue" colspan="3">Temperature &deg;C</th>
                                    <th class="head-blue" rowspan="2">Dryer &deg;C<br>(Celcius)</th>
                                    <th class="head-blue" colspan="2">Tekanan Air</th>
                                    <th class="head-blue" rowspan="2">Petugas</th>
                                </tr>
                                <tr>
                                    <th class="head-blue">IN &deg;C</th>
                                    <th class="head-blue">OUT &deg;C</th>
                                    <th class="head-blue">IN &deg;C</th>
                                    <th class="head-blue">OUT &deg;C</th>
                                    <th class="head-blue">P1</th>
                                    <th class="head-blue">P2</th>
                                    <th class="head-blue">T1</th>
                                    <th class="head-blue">T2</th>
                                    <th class="head-blue">T3</th>
                                    <th class="head-blue">IN</th>
                                    <th class="head-blue">OUT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hours as $hour): $row = $cells[$hour] ?? []; ?>
                                    <tr>
                                        <td><?= htmlspecialchars(temp_compressor_display_hour($hour)) ?></td>
                                        <td><?= htmlspecialchars($row['c1_in'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['c1_out'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['c2_in'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['c2_out'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['amper'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['pressure_p1'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['pressure_p2'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['temp_t1'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['temp_t2'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['temp_t3'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['dryer_c'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['tekanan_in'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['tekanan_out'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['petugas'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="keterangan-row">
                                    <td style="background:#b7b7b7; font-weight:700; text-align:center;">KETERANGAN</td>
                                    <td colspan="14" style="text-align:left; padding:5px 8px; background:#fff; font-weight:500;"><?= htmlspecialchars(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') ?></td>
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
    padding: 4px 6px;
    height: 24px;
}
.compressor-report-table .sheet-title {
    font-size: 14px;
    font-weight: 800;
    background: #fff;
}
.compressor-report-table .date-title {
    text-align: left;
    background: #fff;
    font-weight: 700;
}
.compressor-report-table .date-title.text-center {
    text-align: center;
}
.compressor-report-table .head-blue {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    font-size: 11px;
}
@media (max-width: 767.98px) {
    .compressor-report-table {
        width: 1050px;
    }
}
</style>
