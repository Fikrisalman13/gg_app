<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

// Auth check
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themePalette = [
    'primary'   => ['dark' => '#0d6efd', 'light' => '#6ea8fe', 'soft' => '#e7f1ff'],
    'secondary' => ['dark' => '#6c757d', 'light' => '#adb5bd', 'soft' => '#f1f3f5'],
    'success'   => ['dark' => '#198754', 'light' => '#75b798', 'soft' => '#e8f5ee'],
    'danger'    => ['dark' => '#dc3545', 'light' => '#f08a93', 'soft' => '#fdecee'],
    'warning'   => ['dark' => '#d39e00', 'light' => '#f1cd6f', 'soft' => '#fff8e6'],
    'info'      => ['dark' => '#0dcaf0', 'light' => '#79deef', 'soft' => '#e8f9fd'],
    'dark'      => ['dark' => '#212529', 'light' => '#6c757d', 'soft' => '#eceef0'],
];
$palette = $themePalette[$themeColor] ?? $themePalette['primary'];

$menus = [
    [
        'title' => 'Analisa Masalah Warna',
        'subtitle' => 'Pencatatan dan analisa hasil celup, perbaikan warna, serta evaluasi produksi',
        'file' => '/gg_app/pages/analisa_warna/analisa_warna.php',
        'icon' => 'fa-palette'
    ],
    [
        'title' => 'Analisa Masalah Kain',
        'subtitle' => 'Otomatisasi rekap analisa kain dari database GG, monitoring per periode, dan export Excel',
        'file' => '/gg_app/pages/analisa_kain/rekap_otomatis.php',
        'icon' => 'fa-layer-group'
    ],
    [
        'title' => 'MKO ACC Warna',
        'subtitle' => 'Monitoring raw data routing, penarikan detail material obat, dan rekap periode ACC warna',
        'file' => '/gg_app/pages/resep_obat/MKO-ACCWARNA/',
        'icon' => 'fa-flask'
    ],
];

$menuGroups = [
    [
        'title' => 'Menu Analisa & Kualitas',
        'subtitle' => 'Modul analisa data produksi dan kualitas warna',
        'icon' => 'fa-chart-pie',
        'items' => $menus
    ],
];

$totalModules = count($menus);
?>

<div class="content-wrapper">
    <style>
        :root {
            --analisa-theme-dark: <?= htmlspecialchars($palette['dark']) ?>;
            --analisa-theme-light: <?= htmlspecialchars($palette['light']) ?>;
            --analisa-theme-soft: <?= htmlspecialchars($palette['soft']) ?>;
        }
        .analisa-hero {
            border-radius: 10px;
            background: linear-gradient(135deg, var(--analisa-theme-dark) 0%, var(--analisa-theme-light) 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
        }
        .analisa-hero .small-box {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 10px 12px;
            min-width: 120px;
            text-align: center;
        }
        .analisa-hero .small-box .num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }
        .analisa-hero .small-box .label {
            font-size: 12px;
            opacity: 0.95;
        }
        .analisa-group-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .analisa-group {
            border: 1px solid var(--analisa-theme-light);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .analisa-group summary {
            list-style: none;
        }
        .analisa-group summary::-webkit-details-marker {
            display: none;
        }
        .analisa-group-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            cursor: pointer;
            background: var(--analisa-theme-dark);
            color: #fff;
        }
        .analisa-group:not([open]) .analisa-group-summary {
            border-radius: 10px;
        }
        .analisa-group-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 14px;
            margin-right: 8px;
        }
        .analisa-group-meta {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        .analisa-group-title {
            font-weight: 700;
            line-height: 1.2;
        }
        .analisa-group-subtitle {
            font-size: 12px;
            opacity: 0.92;
            line-height: 1.2;
        }
        .analisa-group-right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .analisa-group-arrow {
            transition: transform 0.2s ease;
        }
        .analisa-group[open] .analisa-group-arrow {
            transform: rotate(180deg);
        }
        .analisa-group-content {
            background: var(--analisa-theme-soft);
            padding: 14px;
            border-top: 1px solid var(--analisa-theme-light);
        }
        .analisa-grid .analisa-card {
            display: block;
            border: 1px solid var(--analisa-theme-light);
            border-radius: 10px;
            padding: 14px 14px 12px 14px;
            background: #fff;
            color: #212529;
            text-decoration: none;
            height: 100%;
            transition: all 0.18s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .analisa-grid .analisa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--analisa-theme-dark);
            color: #212529;
            text-decoration: none;
        }
        .analisa-grid .analisa-icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--analisa-theme-soft);
            color: var(--analisa-theme-dark);
            font-size: 18px;
            margin-bottom: 10px;
        }
        .analisa-grid .analisa-title {
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 4px;
            min-height: 38px;
        }
        .analisa-grid .analisa-subtitle {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.35;
            min-height: 32px;
            margin-bottom: 8px;
        }
        .analisa-grid .analisa-action {
            font-size: 12px;
            font-weight: 600;
            color: var(--analisa-theme-dark);
        }
    </style>

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold">
                        <i class="fas fa-chart-line text-primary mr-2"></i>Analisa
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/">Home</a></li>
                        <li class="breadcrumb-item active">Analisa</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Hero Card -->
            <div class="card analisa-hero mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                    <div class="pr-3">
                        <h3 class="mb-1" style="font-weight:700;">Analisa</h3>
                        <div style="opacity:.95;">Pilih modul untuk input data, monitoring, dan analisa hasil produksi.</div>
                    </div>
                    <div class="d-flex mt-2 mt-md-0" style="gap:10px;">
                        <div class="small-box">
                            <div class="num"><?= $totalModules ?></div>
                            <div class="label">Total Modul</div>
                        </div>
                        <div class="small-box">
                            <div class="num"><?= count($menuGroups) ?></div>
                            <div class="label">Kategori</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Pilihan Menu Analisa -->
            <div class="card card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-th-large mr-1"></i> Pilihan Menu Analisa</h3>
                </div>
                <div class="card-body">
                    <div class="analisa-group-list">
                        <?php foreach ($menuGroups as $group): ?>
                            <details class="analisa-group" open>
                                <summary class="analisa-group-summary">
                                    <span class="analisa-group-icon"><i class="fas <?= htmlspecialchars($group['icon']) ?>"></i></span>
                                    <span class="analisa-group-meta">
                                        <span class="analisa-group-title"><?= htmlspecialchars($group['title']) ?></span>
                                        <span class="analisa-group-subtitle"><?= htmlspecialchars($group['subtitle']) ?></span>
                                    </span>
                                    <span class="analisa-group-right">
                                        <span><?= count($group['items']) ?> Modul</span>
                                        <i class="fas fa-chevron-down analisa-group-arrow"></i>
                                    </span>
                                </summary>
                                <div class="analisa-group-content">
                                    <div class="row analisa-grid">
                                        <?php foreach ($group['items'] as $menu): ?>
                                            <div class="col-sm-6 col-lg-4 mb-3">
                                                <a href="<?= htmlspecialchars($menu['file']) ?>" class="analisa-card">
                                                    <span class="analisa-icon"><i class="fas <?= htmlspecialchars($menu['icon']) ?>"></i></span>
                                                    <div class="analisa-title"><?= htmlspecialchars($menu['title']) ?></div>
                                                    <div class="analisa-subtitle"><?= htmlspecialchars($menu['subtitle']) ?></div>
                                                    <div class="analisa-action">Buka Modul <i class="fas fa-arrow-right ml-1"></i></div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
