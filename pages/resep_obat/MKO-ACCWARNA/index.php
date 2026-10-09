<?php
session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$recentPeriods = [];
$setupError = null;

try {
    $gg = getSqlsrvConnection('gg');
    $conn = $gg;
    ensureTablesExist($gg);
    $recentPeriods = getRecentPeriods($gg, 20);
} catch (Throwable $e) {
    $setupError = $e->getMessage();
}

$totalPeriods = count($recentPeriods);
$latestPeriod = $recentPeriods[0] ?? null;
$readyRekapCount = 0;
$readyPptCount = 0;
foreach ($recentPeriods as $p) {
    if ((int)($p['rekap_count'] ?? 0) > 0) $readyRekapCount++;
    if ((int)($p['ppt_fail_count'] ?? 0) > 0 || (int)($p['ppt_beda_resep_count'] ?? 0) > 0) $readyPptCount++;
}

$defaultStartDate = '2026-04-23T00:00';
$defaultEndDate = '2026-04-29T23:59';

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<style>
    .mko-dashboard-hero {
        background: linear-gradient(135deg, #4b38b3 0%, #6f42c1 50%, #8e44ad 100%);
        color: #fff;
        border-radius: 12px;
        padding: 22px 28px;
        margin-bottom: 24px;
        box-shadow: 0 8px 24px rgba(111, 66, 193, 0.2);
    }
    .mko-stat-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #fff;
    }
    .mko-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    }
    .mko-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .mko-nav-pills .nav-link {
        border-radius: 8px;
        font-weight: 600;
        padding: 10px 18px;
        color: #495057;
        background: #f8f9fa;
        margin-right: 8px;
        margin-bottom: 8px;
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
    }
    .mko-nav-pills .nav-link:hover {
        background: #e9ecef;
        color: #212529;
    }
    .mko-nav-pills .nav-link.active {
        background: #6f42c1 !important;
        color: #fff !important;
        border-color: #6f42c1 !important;
        box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
    }
    .step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(0,0,0,0.1);
        font-size: 11px;
        margin-right: 8px;
        font-weight: 700;
    }
    .mko-nav-pills .nav-link.active .step-badge {
        background: rgba(255,255,255,0.3);
        color: #fff;
    }
    .mko-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    }
    .mko-card .card-header {
        background: #fff;
        border-bottom: 1px solid #f1f3f5;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        padding: 16px 20px;
    }
    .badge-count {
        font-size: 13px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 6px;
    }
    .table-period thead th {
        background-color: #f8f9fb;
        color: #495057;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11.5px;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef !important;
        vertical-align: middle;
        padding: 12px 14px;
    }
    .table-period tbody td {
        vertical-align: middle;
        padding: 10px 14px;
        font-size: 13px;
    }
    .table-period tbody tr:hover {
        background-color: #fbfbfe;
    }
    .action-btn-group .btn {
        margin: 0 2px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        padding: 4px 9px;
    }
    .active-period-box {
        background: #fdfdfe;
        border: 1px dashed #d6d8db;
        border-radius: 8px;
        padding: 10px 14px;
    }
    .date-pill {
        display: inline-block;
        font-family: inherit;
        font-size: 12px;
        line-height: 1.4;
    }
</style>

<div class="content-wrapper">
    <section class="content-header pb-1">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold" style="color: #2c3e50;">
                        <i class="fas fa-palette text-purple mr-2"></i>MKO ACC Warna
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="/gg_app/pages/home.php"><i class="fas fa-home mr-1"></i>Home</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/resep_obat/list_resep.php">Resep Obat</a></li>
                        <li class="breadcrumb-item active text-purple font-weight-bold">MKO ACC Warna</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($setupError): ?>
                <div class="alert alert-danger shadow-sm border-0">
                    <i class="fas fa-exclamation-triangle mr-2"></i><strong>Setup database gagal:</strong>
                    <?= htmlspecialchars($setupError) ?>
                </div>
            <?php endif; ?>

            <!-- STAT WIDGETS -->
            <div class="row mb-3">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card mko-stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="mko-stat-icon bg-light text-primary mr-3" style="background-color: #ede7f6 !important; color: #6f42c1 !important;">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div>
                                <span class="text-muted text-xs font-weight-bold text-uppercase">Total Periode</span>
                                <h3 class="font-weight-bold mb-0 text-dark"><?= $totalPeriods ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card mko-stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="mko-stat-icon bg-light text-info mr-3" style="background-color: #e1f5fe !important; color: #0288d1 !important;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="overflow-hidden" style="max-width: calc(100% - 60px);">
                                <span class="text-muted text-xs font-weight-bold text-uppercase">Periode Terakhir</span>
                                <h5 class="font-weight-bold mb-0 text-truncate text-dark" title="<?= htmlspecialchars($latestPeriod['kode_periode'] ?? '-') ?>">
                                    <?= htmlspecialchars($latestPeriod['kode_periode'] ?? '-') ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card mko-stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="mko-stat-icon bg-light text-success mr-3" style="background-color: #e8f5e9 !important; color: #2e7d32 !important;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <span class="text-muted text-xs font-weight-bold text-uppercase">Rekap Selesai</span>
                                <h3 class="font-weight-bold mb-0 text-dark"><?= $readyRekapCount ?> <small class="text-xs text-muted">Periode</small></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card mko-stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="mko-stat-icon bg-light text-warning mr-3" style="background-color: #fff8e1 !important; color: #f57f17 !important;">
                                <i class="fas fa-file-powerpoint"></i>
                            </div>
                            <div>
                                <span class="text-muted text-xs font-weight-bold text-uppercase">PPT Selesai</span>
                                <h3 class="font-weight-bold mb-0 text-dark"><?= $readyPptCount ?> <small class="text-xs text-muted">Periode</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 2: ACTION HUB (LEFT 8) & STATUS PANEL (RIGHT 4) -->
            <div class="row" id="actionHubCard">
                <div class="col-lg-8 mb-4">
                    <div class="card mko-card h-100">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center mb-1 mb-md-0">
                                <span class="btn btn-sm btn-icon mr-2 text-white" style="background: #6f42c1; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sliders-h"></i>
                                </span>
                                <div>
                                    <h5 class="card-title font-weight-bold mb-0 text-dark">Alur Penarikan & Pengolahan Data</h5>
                                    <small class="text-muted d-block">Pilih tahapan proses yang ingin dijalankan secara berurutan.</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="text-xs text-muted mr-2 font-weight-bold">Periode Aktif:</span>
                                <span id="activePeriodBadge" class="badge badge-light border text-purple font-weight-bold px-2 py-1" style="font-size: 12px; background: #f3e8fd; border-color: #d1b8f5 !important; color: #6f42c1;">
                                    Belum Dipilih
                                </span>
                            </div>
                        </div>

                        <div class="card-body">
                            <!-- Nav Pills -->
                            <ul class="nav mko-nav-pills mb-3" id="processPills" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="pill-raw-tab" data-toggle="pill" href="#pill-raw" role="tab">
                                        <span class="step-badge">1</span><i class="fas fa-cloud-download-alt mr-1"></i> Tarik Raw Data
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="pill-material-tab" data-toggle="pill" href="#pill-material" role="tab">
                                        <span class="step-badge">2</span><i class="fas fa-flask mr-1"></i> Tarik Material Obat
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="pill-rekap-tab" data-toggle="pill" href="#pill-rekap" role="tab">
                                        <span class="step-badge">3</span><i class="fas fa-table mr-1"></i> Tarik Rekap
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="pill-ppt-tab" data-toggle="pill" href="#pill-ppt" role="tab">
                                        <span class="step-badge">4</span><i class="fas fa-file-powerpoint mr-1"></i> Tarik PPT
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content border-top pt-3" id="processPillsContent">
                                <!-- TAB 1: RAW DATA -->
                                <div class="tab-pane fade show active" id="pill-raw" role="tabpanel">
                                    <form id="rawdataForm">
                                        <div class="form-group">
                                            <label for="rtgmsid" class="font-weight-bold text-dark">
                                                RTGMSID <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="rtgmsid" name="rtgmsid" rows="2" placeholder="Contoh: 601,613,589,639,861" required>601,613,589,639,861</textarea>
                                            <small class="form-text text-muted">Pisahkan dengan koma, spasi, atau baris baru.</small>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="startdate" class="font-weight-bold text-dark">Start Date <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control" id="startdate" name="startdate" value="<?= $defaultStartDate ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="enddate" class="font-weight-bold text-dark">End Date <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control" id="enddate" name="enddate" value="<?= $defaultEndDate ?>" required>
                                            </div>
                                        </div>

                                        <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" style="background-color: #f8faff; border-color: #e2e8f0 !important;">
                                            <i class="fas fa-info-circle text-primary mr-2" style="font-size: 18px;"></i>
                                            <span class="text-xs text-muted">
                                                Tarik routing dari <code>ERP_Crystal_SUM_Demo</code> $\rightarrow$ disimpan ke <code>GG.dbo.mko_rawdata</code> dan menghasilkan kode periode baru.
                                            </span>
                                        </div>

                                        <button type="submit" class="btn btn-primary px-4 font-weight-bold" id="btnFetchRaw" style="background-color: #4b38b3; border-color: #4b38b3;">
                                            <i class="fas fa-database mr-1"></i> Tarik Raw Data
                                        </button>
                                    </form>
                                </div>

                                <!-- TAB 2: MATERIAL OBAT -->
                                <div class="tab-pane fade" id="pill-material" role="tabpanel">
                                    <form id="materialForm">
                                        <div class="form-group">
                                            <label for="kode_periode" class="font-weight-bold text-dark">Kode Periode <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                </div>
                                                <input type="text" class="form-control" id="kode_periode" name="kode_periode" placeholder="Pilih dari tombol 'Pakai' di tabel bawah atau ketik manual" required>
                                            </div>
                                            <small class="form-text text-muted">Material obat akan diproses berdasarkan <code>prdnmbr</code> unik di <code>mko_rawdata</code>.</small>
                                        </div>

                                        <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" style="background-color: #f6fcf8; border-color: #d1e7dd !important;">
                                            <i class="fas fa-calculator text-success mr-2" style="font-size: 18px;"></i>
                                            <span class="text-xs text-muted">
                                                Kalkulasi <code>cost_meter</code> diproses otomatis langsung di query tanpa tabel temporary.
                                            </span>
                                        </div>

                                        <button type="submit" class="btn btn-success px-4 font-weight-bold" id="btnFetchMaterial">
                                            <i class="fas fa-flask mr-1"></i> Tarik Material Obat
                                        </button>
                                    </form>
                                </div>

                                <!-- TAB 3: REKAP -->
                                <div class="tab-pane fade" id="pill-rekap" role="tabpanel">
                                    <form id="rekapForm">
                                        <div class="form-group">
                                            <label for="kode_periode_rekap" class="font-weight-bold text-dark">Kode Periode <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                </div>
                                                <input type="text" class="form-control" id="kode_periode_rekap" name="kode_periode" placeholder="Pilih kode periode yang sudah selesai material obat" required>
                                            </div>
                                            <small class="form-text text-muted">Rekap mengelompokkan data material obat menjadi format PivotTable per kode warna.</small>
                                        </div>

                                        <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" style="background-color: #f7fafc; border-color: #e2e8f0 !important;">
                                            <i class="fas fa-filter text-info mr-2" style="font-size: 18px;"></i>
                                            <span class="text-xs text-muted">
                                                Status <strong>Master Resep (Ya/Belum)</strong> otomatis dicek ke <code>resep_obat</code> dengan filter <code>status_resep_lipat = 'Master Resep'</code>.
                                            </span>
                                        </div>

                                        <button type="submit" class="btn btn-info px-4 font-weight-bold" id="btnFetchRekap">
                                            <i class="fas fa-table mr-1"></i> Tarik Rekap
                                        </button>
                                    </form>
                                </div>

                                <!-- TAB 4: PPT -->
                                <div class="tab-pane fade" id="pill-ppt" role="tabpanel">
                                    <form id="pptForm">
                                        <div class="form-group">
                                            <label for="kode_periode_ppt" class="font-weight-bold text-dark">Kode Periode <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                </div>
                                                <input type="text" class="form-control" id="kode_periode_ppt" name="kode_periode" placeholder="Pilih kode periode yang sudah selesai rekap" required>
                                            </div>
                                            <small class="form-text text-muted">Membentuk data tabel <code>MKO_PPT_CPSTATUS_FAIL</code> & <code>MKO_PPT_PERBEDAAN_RESEP</code>.</small>
                                        </div>

                                        <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" style="background-color: #fffbf0; border-color: #ffeeba !important;">
                                            <i class="fas fa-chart-line text-warning mr-2" style="font-size: 18px;"></i>
                                            <span class="text-xs text-muted">
                                                Output: <strong>CP Fail</strong> (Master Resep = Ya, routing PADDRY, Status Fail) dan <strong>Perbedaan Resep</strong>.
                                            </span>
                                        </div>

                                        <button type="submit" class="btn btn-warning px-4 font-weight-bold text-dark" id="btnFetchPpt">
                                            <i class="fas fa-file-powerpoint mr-1"></i> Tarik PPT
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT 4: LIVE RESULT & ACTION SUMMARY -->
                <div class="col-lg-4 mb-4">
                    <div class="card mko-card h-100">
                        <div class="card-header">
                            <h5 class="card-title font-weight-bold mb-0 text-dark">
                                <i class="fas fa-terminal text-secondary mr-2"></i>Status & Hasil Proses
                            </h5>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <div id="resultBox" class="alert alert-light border p-3 mb-3 text-muted" style="min-height: 140px; font-size: 13px; line-height: 1.6; border-radius: 8px;">
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-2 text-secondary opacity-50"></i>
                                        <p class="mb-0">Belum ada proses yang dijalankan.</p>
                                        <small>Pilih periode dari tabel bawah atau jalankan penarikan baru.</small>
                                    </div>
                                </div>

                                <div id="quickActionBox" class="active-period-box d-none mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-xs text-uppercase text-muted">Aksi Cepat Periode:</span>
                                        <span id="quickPeriodName" class="badge badge-purple text-white font-weight-bold px-2 py-1" style="background: #6f42c1;"></span>
                                    </div>
                                    <div class="d-flex">
                                        <a href="#" id="quickViewBtn" class="btn btn-sm btn-outline-info flex-fill mr-1">
                                            <i class="fas fa-eye mr-1"></i> View Data
                                        </a>
                                        <a href="#" id="quickExportBtn" class="btn btn-sm btn-outline-success flex-fill">
                                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded text-xs text-muted border">
                                <div class="font-weight-bold text-dark mb-1"><i class="fas fa-lightbulb text-warning mr-1"></i> Tips Alur Kerja:</div>
                                Klik tombol <span class="badge badge-primary py-0 px-1">Pakai</span> pada baris tabel riwayat untuk otomatis memilih kode periode dan membuka tahapan yang belum selesai.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 3: RIWAYAT KODE PERIODE (FULL WIDTH) -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card mko-card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center mb-2 mb-md-0">
                                <span class="btn btn-sm btn-icon mr-2 text-white" style="background: #4b38b3; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-history"></i>
                                </span>
                                <div>
                                    <h5 class="card-title font-weight-bold mb-0 text-dark">Riwayat Kode Periode</h5>
                                    <small class="text-muted d-block">Daftar periode yang telah ditarik dan status kelengkapan datanya.</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-center">
                                <div class="input-group input-group-sm" style="width: 280px;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" id="filterPeriodeInput" class="form-control border-left-0" placeholder="Cari kode periode atau tanggal...">
                                </div>
                            </div>
                        </div>

                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover table-period mb-0" id="periodeTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="min-width: 220px;">Kode Periode</th>
                                        <th style="min-width: 200px;">Rentang Tanggal</th>
                                        <th class="text-center" style="width: 90px;">Raw</th>
                                        <th class="text-center" style="width: 90px;">Material</th>
                                        <th class="text-center" style="width: 90px;">Rekap</th>
                                        <th class="text-center" style="width: 95px;">PPT Fail</th>
                                        <th class="text-center" style="width: 110px;">PPT Beda</th>
                                        <th style="min-width: 140px;">Waktu Dibuat</th>
                                        <th class="text-center" style="min-width: 210px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$recentPeriods): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="fas fa-folder-open fa-2x mb-2 text-secondary opacity-50"></i>
                                                <p class="mb-0">Belum ada riwayat data periode.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentPeriods as $idx => $period): ?>
                                            <?php
                                                $rawCnt = (int) $period['raw_count'];
                                                $matCnt = (int) $period['material_count'];
                                                $rekCnt = (int) $period['rekap_count'];
                                                $pptFailCnt = (int) ($period['ppt_fail_count'] ?? 0);
                                                $pptBedaCnt = (int) ($period['ppt_beda_resep_count'] ?? 0);
                                            ?>
                                            <tr class="period-row">
                                                <td class="text-center text-muted font-weight-bold"><?= $idx + 1 ?></td>
                                                <td>
                                                    <span class="font-weight-bold text-dark d-block period-code">
                                                        <?= htmlspecialchars($period['kode_periode']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="date-pill text-muted">
                                                        <i class="far fa-calendar-alt text-primary mr-1"></i>
                                                        <span class="font-weight-bold text-dark"><?= htmlspecialchars(formatDisplayDate($period['period_start'])) ?></span>
                                                        <br>
                                                        <i class="far fa-calendar-check text-info mr-1"></i>
                                                        <span>s/d <?= htmlspecialchars(formatDisplayDate($period['period_end'])) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-light border badge-count text-primary" style="background: #edf2f7;">
                                                        <?= number_format($rawCnt) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $matCnt > 0 ? 'badge-light border text-success' : 'badge-light text-muted' ?> badge-count" style="<?= $matCnt > 0 ? 'background: #e6fffa; border-color: #b2f5ea !important;' : '' ?>">
                                                        <?= number_format($matCnt) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $rekCnt > 0 ? 'badge-light border text-purple font-weight-bold' : 'badge-light text-muted' ?> badge-count" style="<?= $rekCnt > 0 ? 'background: #f3e8fd; border-color: #d1b8f5 !important; color: #6f42c1;' : '' ?>">
                                                        <?= number_format($rekCnt) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($pptFailCnt > 0): ?>
                                                        <span class="badge badge-danger badge-count" title="<?= $pptFailCnt ?> CP Status Fail">
                                                            <?= number_format($pptFailCnt) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light text-muted badge-count">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($pptBedaCnt > 0): ?>
                                                        <span class="badge badge-warning badge-count font-weight-bold" title="<?= $pptBedaCnt ?> Perbedaan Resep">
                                                            <?= number_format($pptBedaCnt) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light text-muted badge-count">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-muted text-xs">
                                                    <i class="far fa-clock mr-1"></i><?= htmlspecialchars(formatDisplayDate($period['created_at'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="action-btn-group d-inline-flex">
                                                        <button
                                                            type="button"
                                                            class="btn btn-primary use-period-btn shadow-sm"
                                                            data-kode="<?= htmlspecialchars($period['kode_periode']) ?>"
                                                            data-material-count="<?= $matCnt ?>"
                                                            data-rekap-count="<?= $rekCnt ?>"
                                                            data-ppt-fail-count="<?= $pptFailCnt ?>"
                                                            data-ppt-beda-count="<?= $pptBedaCnt ?>"
                                                            title="Pakai periode ini untuk proses selanjutnya"
                                                            style="background-color: #6f42c1; border-color: #6f42c1;"
                                                        >
                                                            <i class="fas fa-play mr-1"></i>Pakai
                                                        </button>
                                                        <a
                                                            href="view_periode.php?kode_periode=<?= urlencode($period['kode_periode']) ?>"
                                                            class="btn btn-info shadow-sm"
                                                            title="Lihat detail lembar data"
                                                        >
                                                            <i class="fas fa-eye mr-1"></i>View
                                                        </a>
                                                        <a
                                                            href="export_periode_excel.php?kode_periode=<?= urlencode($period['kode_periode']) ?>"
                                                            class="btn btn-success shadow-sm"
                                                            title="Export laporan ke Excel (.xlsx)"
                                                        >
                                                            <i class="fas fa-file-excel mr-1"></i>Export
                                                        </a>
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-danger delete-period-btn"
                                                            data-kode="<?= htmlspecialchars($period['kode_periode']) ?>"
                                                            title="Hapus periode dan semua data terkait"
                                                        >
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
function setLoading(button, isLoading, text) {
    if (!button) return;
    button.disabled = isLoading;
    if (isLoading) {
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ' + text;
    } else if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
}

function renderResult(type, html) {
    var el = document.getElementById('resultBox');
    el.className = 'alert alert-' + type + ' border p-3 mb-3';
    el.innerHTML = html;
}

function updateActivePeriod(kode) {
    var badge = document.getElementById('activePeriodBadge');
    var qBox = document.getElementById('quickActionBox');
    var qName = document.getElementById('quickPeriodName');
    var qView = document.getElementById('quickViewBtn');
    var qExport = document.getElementById('quickExportBtn');

    if (badge) {
        badge.textContent = kode || 'Belum Dipilih';
    }

    if (kode) {
        if (qBox) qBox.classList.remove('d-none');
        if (qName) qName.textContent = kode;
        if (qView) qView.href = 'view_periode.php?kode_periode=' + encodeURIComponent(kode);
        if (qExport) qExport.href = 'export_periode_excel.php?kode_periode=' + encodeURIComponent(kode);
    }
}

// FORM 1: TARIK RAW DATA
document.getElementById('rawdataForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchRaw');
    setLoading(button, true, 'Memproses raw data...');

    try {
        var response = await fetch('fetch_rawdata.php', {
            method: 'POST',
            body: new FormData(this)
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik raw data.');
        }

        document.getElementById('kode_periode').value = data.kode_periode;
        document.getElementById('kode_periode_rekap').value = data.kode_periode;
        document.getElementById('kode_periode_ppt').value = '';
        updateActivePeriod(data.kode_periode);

        renderResult('success',
            '<strong><i class="fas fa-check-circle mr-1"></i> Raw data berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row: <strong>' + data.count + '</strong><br>' +
            'Jumlah prdnmbr unik: <strong>' + data.prdnmbr_count + '</strong>'
        );

        Swal.fire({
            title: 'Berhasil!',
            text: 'Raw data berhasil disimpan. Silakan lanjutkan ke Tarik Material Obat.',
            icon: 'success',
            confirmButtonColor: '#6f42c1'
        }).then(function () {
            window.location.reload();
        });
    } catch (error) {
        renderResult('danger', '<strong><i class="fas fa-times-circle mr-1"></i> Proses gagal:</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

// FORM 2: TARIK MATERIAL OBAT
document.getElementById('materialForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchMaterial');
    setLoading(button, true, 'Memproses material...');

    try {
        var payload = new URLSearchParams(new FormData(this));
        var response = await fetch('fetch_materialobat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik material obat.');
        }

        updateActivePeriod(data.kode_periode);
        renderResult('success',
            '<strong><i class="fas fa-check-circle mr-1"></i> Material obat berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row: <strong>' + data.count + '</strong><br>' +
            'Jumlah prdnmbr unik: <strong>' + data.prdnmbr_count + '</strong>'
        );

        Swal.fire({
            title: 'Berhasil!',
            text: 'Material obat berhasil disimpan ke MKO_materialobat.',
            icon: 'success',
            confirmButtonColor: '#28a745'
        }).then(function () {
            window.location.reload();
        });
    } catch (error) {
        renderResult('danger', '<strong><i class="fas fa-times-circle mr-1"></i> Proses gagal:</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

// FORM 3: TARIK REKAP
document.getElementById('rekapForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchRekap');
    setLoading(button, true, 'Memproses rekap...');

    try {
        var payload = new URLSearchParams(new FormData(this));
        var response = await fetch('fetch_rekap.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik rekap.');
        }

        updateActivePeriod(data.kode_periode);
        renderResult('success',
            '<strong><i class="fas fa-check-circle mr-1"></i> Rekap berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row rekap: <strong>' + data.count + '</strong>'
        );

        Swal.fire({
            title: 'Berhasil!',
            text: 'Rekap berhasil disimpan ke MKO_rekap.',
            icon: 'success',
            confirmButtonColor: '#17a2b8'
        }).then(function () {
            window.location.reload();
        });
    } catch (error) {
        renderResult('danger', '<strong><i class="fas fa-times-circle mr-1"></i> Proses gagal:</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

// FORM 4: TARIK PPT
document.getElementById('pptForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchPpt');
    setLoading(button, true, 'Memproses PPT...');

    try {
        var payload = new URLSearchParams(new FormData(this));
        var response = await fetch('fetch_ppt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik PPT.');
        }

        updateActivePeriod(data.kode_periode);
        renderResult('success',
            '<strong><i class="fas fa-check-circle mr-1"></i> Data PPT berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'CP status fail: <strong>' + data.cpstatus_fail_count + '</strong><br>' +
            'Perbedaan resep: <strong>' + data.perbedaan_resep_count + '</strong>'
        );

        Swal.fire({
            title: 'Berhasil!',
            text: 'Data PPT berhasil disimpan ke tabel PPT.',
            icon: 'success',
            confirmButtonColor: '#ffc107'
        }).then(function () {
            window.location.reload();
        });
    } catch (error) {
        renderResult('danger', '<strong><i class="fas fa-times-circle mr-1"></i> Proses gagal:</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

// TOMBOL "PAKAI"
document.querySelectorAll('.use-period-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        var kode = this.dataset.kode || '';
        var materialCount = parseInt(this.dataset.materialCount || '0', 10);
        var rekapCount = parseInt(this.dataset.rekapCount || '0', 10);
        var pptFailCount = parseInt(this.dataset.pptFailCount || '0', 10);
        var pptBedaCount = parseInt(this.dataset.pptBedaCount || '0', 10);

        document.getElementById('kode_periode').value = kode;
        document.getElementById('kode_periode_rekap').value = kode;
        document.getElementById('kode_periode_ppt').value = kode;

        updateActivePeriod(kode);

        // Smooth scroll ke Action Hub
        document.getElementById('actionHubCard').scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (materialCount > 0 && rekapCount > 0) {
            // Aktifkan tab PPT
            $('#pill-ppt-tab').tab('show');
            renderResult('warning',
                '<strong><i class="fas fa-info-circle mr-1"></i> Periode Terpilih: ' + kode + '</strong><br>' +
                'Sudah selesai Material Obat (' + materialCount + ') dan Rekap (' + rekapCount + ').<br>' +
                'Tab <strong>Tarik PPT</strong> telah dibuka.'
            );
            if (pptFailCount > 0 || pptBedaCount > 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Periode Lengkap',
                    text: 'Periode ini sudah lengkap diproses (Material, Rekap, dan PPT). Anda dapat langsung View Data atau Export Excel.',
                    confirmButtonColor: '#6f42c1'
                });
            }
            return;
        }

        if (materialCount > 0) {
            // Aktifkan tab Rekap
            $('#pill-rekap-tab').tab('show');
            renderResult('info',
                '<strong><i class="fas fa-info-circle mr-1"></i> Periode Terpilih: ' + kode + '</strong><br>' +
                'Sudah selesai Material Obat (' + materialCount + ').<br>' +
                'Tab <strong>Tarik Rekap</strong> telah dibuka.'
            );
            return;
        }

        // Aktifkan tab Material Obat
        $('#pill-material-tab').tab('show');
        renderResult('info',
            '<strong><i class="fas fa-info-circle mr-1"></i> Periode Terpilih: ' + kode + '</strong><br>' +
            'Tab <strong>Tarik Material Obat</strong> telah dibuka.'
        );
    });
});

// TOMBOL "HAPUS"
document.querySelectorAll('.delete-period-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        var kode = this.dataset.kode || '';
        if (!kode) return;

        Swal.fire({
            title: 'Hapus Kode Periode?',
            html: 'Semua data untuk <strong>' + kode + '</strong> di `mko_rawdata`, `MKO_materialobat`, `MKO_rekap`, dan tabel PPT akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then(async function (result) {
            if (!result.isConfirmed) return;

            try {
                var payload = new URLSearchParams();
                payload.set('kode_periode', kode);

                var response = await fetch('delete_periode.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Gagal menghapus data periode.');
                }

                renderResult(
                    'success',
                    '<strong><i class="fas fa-check mr-1"></i> Data periode berhasil dihapus:</strong><br>' +
                    'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
                    'mko_rawdata: <strong>' + data.deleted.mko_rawdata + '</strong><br>' +
                    'MKO_materialobat: <strong>' + data.deleted.MKO_materialobat + '</strong><br>' +
                    'MKO_rekap: <strong>' + data.deleted.MKO_rekap + '</strong><br>' +
                    'PPT Fail: <strong>' + data.deleted.MKO_PPT_CPSTATUS_FAIL + '</strong><br>' +
                    'PPT Beda Resep: <strong>' + data.deleted.MKO_PPT_PERBEDAAN_RESEP + '</strong>'
                );

                Swal.fire('Sukses', 'Data periode berhasil dihapus.', 'success')
                    .then(function () { window.location.reload(); });
            } catch (error) {
                renderResult('danger', '<strong><i class="fas fa-times mr-1"></i> Proses gagal:</strong><br>' + error.message);
                Swal.fire('Error', error.message, 'error');
            }
        });
    });
});

// LIVE SEARCH FILTER TABEL
document.getElementById('filterPeriodeInput').addEventListener('input', function () {
    var query = this.value.toLowerCase().trim();
    var rows = document.querySelectorAll('#periodeTable tbody tr.period-row');

    rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        if (text.indexOf(query) !== -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>
