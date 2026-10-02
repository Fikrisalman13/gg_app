<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include 'hasil_produksi_susut_data.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
date_default_timezone_set('Asia/Jakarta');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$latestDataDate = null;
try {
    $latestDataDate = $conn3->query("SELECT TO_CHAR(MAX(enddate), 'YYYY-MM-DD') FROM pdproductionrtg WHERE compid = 2 AND rtgmsid = '692' AND enddate IS NOT NULL")->fetchColumn();
} catch (Throwable $e) {
    $latestDataDate = null;
}
$today = date('Y-m-d');
$filterSubmitted = !empty($_GET['tanggal_awal']) && !empty($_GET['tanggal_akhir']);
if ($filterSubmitted) {
    $result = hasilProduksiSusutFetchData($conn3, $_GET['tanggal_awal'], $_GET['tanggal_akhir'], 1000);
} else {
    $result = [
        'startDate' => $today,
        'endDate' => $today,
        'errors' => [],
        'rows' => [],
        'summary' => ['total_rows' => 0, 'total_greige_meter' => 0, 'total_hasil_meter' => 0, 'total_susut_meter' => 0, 'susut_percent' => 0],
        'maxDays' => 7,
        'limit' => 1000,
    ];
}
extract($result);
$columns = $rows ? array_keys($rows[0]) : [];
$hasRows = !empty($rows);
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
    .report-hero {
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(255, 255, 255, .97), rgba(245, 247, 255, .9));
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08)
    }

    .report-metric {
        height: 100%;
        border-radius: 16px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #fff, rgba(248, 250, 252, .92));
        border: 1px solid rgba(15, 23, 42, .08);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06)
    }

    .report-metric-label {
        display: block;
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em
    }

    .report-metric-value {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 800;
        line-height: 1.2
    }

    .table-wrap {
        overflow-x: auto
    }

    #susutTable th,
    #susutTable td {
        white-space: nowrap;
        vertical-align: middle
    }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark">Tarikan Susut Verpacking</h1><small class="text-muted">Filter
                        tanggal selesai, maksimal <?= $maxDays ?>
                        hari.<?php if ($latestDataDate): ?> Data Routing Verpacking tersedia sampai
                            <?= date('d/m/Y', strtotime($latestDataDate)) ?>.<?php endif; ?></small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Output VPK</a></li>
                        <li class="breadcrumb-item active">Hasil Produksi Susut</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card report-hero mb-3">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Data</h3>
                </div>
                <div class="card-body">
                    <form method="get" class="row align-items-end">
                        <div class="col-md-3 mb-2"><label for="tanggal_awal">Tanggal Awal</label><input type="date"
                                id="tanggal_awal" name="tanggal_awal" class="form-control"
                                value="<?= htmlspecialchars($startDate) ?>"></div>
                        <div class="col-md-3 mb-2"><label for="tanggal_akhir">Tanggal Akhir</label><input type="date"
                                id="tanggal_akhir" name="tanggal_akhir" class="form-control"
                                value="<?= htmlspecialchars($endDate) ?>"></div>
                        <div class="col-md-6 mb-2"><button type="submit"
                                class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-search mr-1"></i>
                                Tampilkan</button><a href="laporan_hasil_produksi_susut.php"
                                class="btn btn-outline-secondary ml-1">Reset</a><?php if ($filterSubmitted && empty($errors) && $hasRows): ?><a
                                    href="export_hasil_produksi_susut_excel.php?tanggal_awal=<?= urlencode($startDate) ?>&tanggal_akhir=<?= urlencode($endDate) ?>"
                                    class="btn btn-success ml-1"><i class="fas fa-file-excel mr-1"></i> Export
                                    Excel</a><?php endif; ?></div>
                    </form>
                    <?php if ($filterSubmitted): ?>
                        <div class="row mt-3">
                            <div class="col-lg col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Jumlah Baris Data</span><span
                                        class="report-metric-value"><?= number_format($summary['total_rows'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Total Greige Meter</span><span
                                        class="report-metric-value"><?= number_format($summary['total_greige_meter'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Total Hasil Meter</span><span
                                        class="report-metric-value"><?= number_format($summary['total_hasil_meter'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Total Susut Meter</span><span
                                        class="report-metric-value"><?= number_format($summary['total_susut_meter'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Susut Rata-rata (%)</span><span
                                        class="report-metric-value"><?= number_format($summary['susut_percent'], 2, ',', '.') ?>%</span>
                                </div>
                            </div>
                        </div><?php else: ?>
                        <div class="alert alert-info mt-3 mb-0"><i class="fas fa-info-circle mr-1"></i> Silakan pilih filter
                            tanggal lalu klik <strong>Tampilkan</strong> untuk memuat data.<?php if ($latestDataDate): ?>
                                Data terakhir Routing Verpacking:
                                <strong><?= date('d/m/Y', strtotime($latestDataDate)) ?></strong>.<?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><i
                        class="fas fa-exclamation-triangle mr-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <?php if ($filterSubmitted && empty($errors) && !$hasRows): ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-circle mr-1"></i>Tidak ada data pada periode
                    <?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?>.<?php if ($latestDataDate): ?>
                        Data Routing Verpacking terakhir tersedia sampai
                        <strong><?= date('d/m/Y', strtotime($latestDataDate)) ?></strong>.<?php endif; ?>
                </div><?php endif; ?>
            <?php if ($filterSubmitted && $hasRows): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title"><i class="fas fa-table mr-1"></i> Hasil Laporan</h3>
                        <div class="card-tools">Limit tampilan: <?= number_format($limit, 0, ',', '.') ?> baris</div>
                    </div>
                    <div class="card-body table-wrap">
                        <table id="susutTable" class="table table-bordered table-hover table-sm w-100">
                            <thead class="thead-light">
                                <tr><?php foreach ($columns as $column): ?>
                                        <th><?= htmlspecialchars($column) ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($rows as $row): ?>
                                    <tr><?php foreach ($columns as $column): ?>
                                            <td><?= htmlspecialchars((string) ($row[$column] ?? '')) ?></td><?php endforeach; ?>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div><?php endif; ?>
        </div>
    </section>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<?php if ($filterSubmitted && $hasRows): ?>
    <script>$(function () { $('#susutTable').DataTable({ responsive: false, scrollX: true, autoWidth: false, pageLength: 25, language: { lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada data', info: 'Halaman _PAGE_ dari _PAGES_', search: 'Cari:', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } } }); });</script>
<?php endif; ?>