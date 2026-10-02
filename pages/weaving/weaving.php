<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/weaving_permissions.php');

$permissions = weaving_require($conn, 'CanView');

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
        'title' => 'Pengecekan AC Tempratur Area Weaving',
        'subtitle' => 'Pencatatan temperatur, humidity, amper, dan differential AC area weaving',
        'file' => '/gg_app/pages/weaving/ac_weaving/ac_weaving.php',
        'icon' => 'fa-snowflake'
    ],
    [
        'title' => 'Log Sheet Dryer D IN - W dan Cooling Tower',
        'subtitle' => 'Pencatatan pemeriksaan pershift dryer dan cooling tower area weaving',
        'file' => '/gg_app/pages/weaving/dryer_weaving/dryer_weaving.php',
        'icon' => 'fa-clipboard-check'
    ],
    [
        'title' => 'Check Sheet Kompressor Sullair (V2)',
        'subtitle' => 'Pencatatan check sheet kompressor sullair per unit (Weaving 1/2, Compressor 1/2/3)',
        'file' => '/gg_app/pages/weaving/temp_compressor_v2/temp_compressor_v2.php',
        'icon' => 'fa-clipboard-check'
    ],
    [
        'title' => 'Check Sheet Kompressor Sullair',
        'subtitle' => 'Arsip pencatatan temperatur kompressor format lama',
        'file' => '/gg_app/pages/weaving/temp_compressor/temp_compressor.php',
        'icon' => 'fa-temperature-high'
    ],
    [
        'title' => 'Pencatatan Air Dryer Weaving',
        'subtitle' => 'Pencatatan temperatur dan tekanan air dryer weaving per jam',
        'file' => '/gg_app/pages/weaving/air_dryer/air_dryer.php',
        'icon' => 'fa-wind'
    ],
    [
        'title' => 'Centac Compressor Ingersoll Rand Log Sheet',
        'subtitle' => 'Pencatatan status message CCIRL per jam pemeriksaan',
        'file' => '/gg_app/pages/weaving/ccirl/ccirl.php',
        'icon' => 'fa-tachometer-alt'
    ],
];

$menuGroups = [
    [
        'title' => 'Menu Pengecekan Area Weaving',
        'subtitle' => 'Modul monitoring area weaving',
        'icon' => 'fa-industry',
        'items' => $menus
    ],
];

$totalModules = count($menus);
?>

<div class="wrapper">
<div class="content-wrapper">
    <style>
        :root {
            --weaving-theme-dark: <?= htmlspecialchars($palette['dark']) ?>;
            --weaving-theme-light: <?= htmlspecialchars($palette['light']) ?>;
            --weaving-theme-soft: <?= htmlspecialchars($palette['soft']) ?>;
        }
        .weaving-hero {
            border-radius: 10px;
            background: linear-gradient(135deg, var(--weaving-theme-dark) 0%, var(--weaving-theme-light) 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
        }
        .weaving-hero .small-box {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 10px 12px;
            min-width: 120px;
            text-align: center;
        }
        .weaving-hero .small-box .num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }
        .weaving-hero .small-box .label {
            font-size: 12px;
            opacity: 0.95;
        }
        .weaving-group-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .weaving-group {
            border: 1px solid var(--weaving-theme-light);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .weaving-group summary {
            list-style: none;
        }
        .weaving-group summary::-webkit-details-marker {
            display: none;
        }
        .weaving-group-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            cursor: pointer;
            background: var(--weaving-theme-dark);
            color: #fff;
        }
        .weaving-group:not([open]) .weaving-group-summary {
            border-radius: 10px;
        }
        .weaving-group-icon {
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
        .weaving-group-meta {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        .weaving-group-title {
            font-weight: 700;
            line-height: 1.2;
        }
        .weaving-group-subtitle {
            font-size: 12px;
            opacity: 0.92;
            line-height: 1.2;
        }
        .weaving-group-right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .weaving-group-arrow {
            transition: transform 0.2s ease;
        }
        .weaving-group[open] .weaving-group-arrow {
            transform: rotate(180deg);
        }
        .weaving-group-content {
            background: var(--weaving-theme-soft);
            padding: 14px;
            border-top: 1px solid var(--weaving-theme-light);
        }
        .weaving-grid .weaving-card {
            display: block;
            border: 1px solid var(--weaving-theme-light);
            border-radius: 10px;
            padding: 14px 14px 12px 14px;
            background: #fff;
            color: #212529;
            text-decoration: none;
            height: 100%;
            transition: all 0.18s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .weaving-grid .weaving-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--weaving-theme-dark);
            color: #212529;
            text-decoration: none;
        }
        .weaving-grid .weaving-icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--weaving-theme-soft);
            color: var(--weaving-theme-dark);
            font-size: 18px;
            margin-bottom: 10px;
        }
        .weaving-grid .weaving-title {
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 4px;
            min-height: 38px;
        }
        .weaving-grid .weaving-subtitle {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.35;
            min-height: 32px;
            margin-bottom: 8px;
        }
        .weaving-grid .weaving-action {
            font-size: 12px;
            font-weight: 600;
            color: var(--weaving-theme-dark);
        }
    </style>

    <section class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Weaving</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card weaving-hero mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                    <div class="pr-3">
                        <h3 class="mb-1" style="font-weight:700;">Weaving</h3>
                        <div style="opacity:.95;">Pilih modul untuk input data, monitoring, dan report area weaving.</div>
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

            <div class="card card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-th-large mr-1"></i> Pilihan Menu Weaving</h3>
                </div>
                <div class="card-body">
                    <div class="weaving-group-list">
                        <?php foreach ($menuGroups as $group): ?>
                            <details class="weaving-group" open>
                                <summary class="weaving-group-summary">
                                    <span class="weaving-group-icon"><i class="fas <?= htmlspecialchars($group['icon']) ?>"></i></span>
                                    <span class="weaving-group-meta">
                                        <span class="weaving-group-title"><?= htmlspecialchars($group['title']) ?></span>
                                        <span class="weaving-group-subtitle"><?= htmlspecialchars($group['subtitle']) ?></span>
                                    </span>
                                    <span class="weaving-group-right">
                                        <span><?= count($group['items']) ?> Modul</span>
                                        <i class="fas fa-chevron-down weaving-group-arrow"></i>
                                    </span>
                                </summary>
                                <div class="weaving-group-content">
                                    <div class="row weaving-grid">
                                        <?php foreach ($group['items'] as $menu): ?>
                                            <div class="col-sm-6 col-lg-4 mb-3">
                                                <a href="<?= htmlspecialchars($menu['file']) ?>" class="weaving-card">
                                                    <span class="weaving-icon"><i class="fas <?= htmlspecialchars($menu['icon']) ?>"></i></span>
                                                    <div class="weaving-title"><?= htmlspecialchars($menu['title']) ?></div>
                                                    <div class="weaving-subtitle"><?= htmlspecialchars($menu['subtitle']) ?></div>
                                                    <div class="weaving-action">Buka Modul <i class="fas fa-arrow-right ml-1"></i></div>
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
</div>
