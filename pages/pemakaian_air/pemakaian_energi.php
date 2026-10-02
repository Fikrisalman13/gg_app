<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
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
        'title' => 'Meter Air Jet Dyeing',
        'subtitle' => 'Pemakaian air area Jet Dyeing, Sizing, dan LA',
        'file' => '/gg_app/pages/pemakaian_air/jetdyeing/jetdyeing.php',
        'icon' => 'fa-tint'
    ],
    [
        'title' => 'Meter Air Mes, Kantin dan Pos Security',
        'subtitle' => 'Monitoring pemakaian air fasilitas umum',
        'file' => '/gg_app/pages/pemakaian_air/mkp/mkp.php',
        'icon' => 'fa-building'
    ],
    [
        'title' => 'Meter Air LAB',
        'subtitle' => 'Pemakaian air area LAB',
        'file' => '/gg_app/pages/pemakaian_air/lab/lab.php',
        'icon' => 'fa-flask'
    ],
    [
        'title' => 'Meter Air Mc Pad Steam',
        'subtitle' => 'Pemakaian air mesin Pad Steam',
        'file' => '/gg_app/pages/pemakaian_air/padsteam/padsteam.php',
        'icon' => 'fa-industry'
    ],
    [
        'title' => 'Meter Air PBR 1',
        'subtitle' => 'Pemakaian air mesin Perble Range 1',
        'file' => '/gg_app/pages/pemakaian_air/perblerange1/perblerange1.php',
        'icon' => 'fa-water'
    ],
    [
        'title' => 'Meter Air PBR 2',
        'subtitle' => 'Pemakaian air mesin Perble Range 2',
        'file' => '/gg_app/pages/pemakaian_air/perblerange2/perblerange2.php',
        'icon' => 'fa-water'
    ],
    [
        'title' => 'Meter Air, Listrik dan Steam Washing 2',
        'subtitle' => 'Pemakaian utilitas terintegrasi area Washing 2',
        'file' => '/gg_app/pages/pemakaian_air/washing2/washing2.php',
        'icon' => 'fa-bolt'
    ],
    [
        'title' => 'Meter Air Washing 3',
        'subtitle' => 'Pemakaian air mesin Washing 3',
        'file' => '/gg_app/pages/pemakaian_air/washing3/washing3.php',
        'icon' => 'fa-tachometer-alt'
    ],
    [
        'title' => 'Meter Air Washing',
        'subtitle' => 'Pemakaian air mesin Washing',
        'file' => '/gg_app/pages/pemakaian_air/washing/washing.php',
        'icon' => 'fa-cogs'
    ],
    [
        'title' => 'Listrik Per Bagian',
        'subtitle' => 'Pemakaian kWh per bagian utility, DF, weaving 1 dan weaving 2',
        'file' => '/gg_app/pages/pemakaian_air/listrik_perbagian/listrik_perbagian.php',
        'icon' => 'fa-bolt'
    ],
    [
        'title' => 'Air dan Steam 20 Ton Lama',
        'subtitle' => 'Pemakaian meter air boiler dan meter steam boiler area 20 ton lama',
        'file' => '/gg_app/pages/pemakaian_air/20tonlama/20tonlama.php',
        'icon' => 'fa-thermometer-half'
    ],
    [
        'title' => 'Air dan Steam 20 Ton Lonchuan',
        'subtitle' => 'Input per jam dan rekap harian laju sesaat boiler Lonchuan',
        'file' => '/gg_app/pages/pemakaian_air/20tlonchuan/20tlonchuan.php',
        'icon' => 'fa-fire'
    ],
    [
        'title' => 'Air Bersih & Limbah',
        'subtitle' => 'Pemakaian air bersih dari IPAB dan buangan air produk ke IPAL',
        'file' => '/gg_app/pages/pemakaian_air/air_bersih_limbah/air_bersih_limbah.php',
        'icon' => 'fa-tint'
    ],
    [
        'title' => 'Pencatatan IPAL',
        'subtitle' => 'Pencatatan SV30, pH, dan sludge IPAL per shift',
        'file' => '/gg_app/pages/pemakaian_air/pencatatan_ipal/pencatatan_ipal.php',
        'icon' => 'fa-vials'
    ],
    [
        'title' => 'Air dan Steam 21 Ton Actom',
        'subtitle' => 'Pemakaian air boiler, steam boiler dan air analog di area 21 Ton Actom',
        'file' => '/gg_app/pages/pemakaian_air/21tonactom/21tonactom.php',
        'icon' => 'fa-ship'
    ],
];

$menuGroups = [
    [
        'title' => 'Menu Meter Air',
        'subtitle' => 'Semua modul meter air dan steam',
        'icon' => 'fa-tint',
        'items' => []
    ],
    [
        'title' => 'Menu Listrik',
        'subtitle' => 'Modul pemakaian listrik',
        'icon' => 'fa-bolt',
        'items' => []
    ],
    [
        'title' => 'Pencatatan',
        'subtitle' => 'Modul pencatatan proses',
        'icon' => 'fa-clipboard-list',
        'items' => []
    ],
];

foreach ($menus as $menu) {
    if ($menu['title'] === 'Listrik Per Bagian') {
        $menuGroups[1]['items'][] = $menu;
        continue;
    }
    if ($menu['title'] === 'Pencatatan IPAL') {
        $menuGroups[2]['items'][] = $menu;
        continue;
    }
    $menuGroups[0]['items'][] = $menu;
}

$totalModules = count($menus);
?>

<div class="wrapper">
<div class="content-wrapper">
    <style>
        :root {
            --energy-theme-dark: <?= htmlspecialchars($palette['dark']) ?>;
            --energy-theme-light: <?= htmlspecialchars($palette['light']) ?>;
            --energy-theme-soft: <?= htmlspecialchars($palette['soft']) ?>;
        }
        .energy-hero {
            border-radius: 10px;
            background: linear-gradient(135deg, var(--energy-theme-dark) 0%, var(--energy-theme-light) 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
        }
        .energy-hero .small-box {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 10px 12px;
            min-width: 120px;
            text-align: center;
        }
        .energy-hero .small-box .num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }
        .energy-hero .small-box .label {
            font-size: 12px;
            opacity: 0.95;
        }
        .energy-group-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .energy-group {
            border: 1px solid var(--energy-theme-light);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .energy-group summary {
            list-style: none;
        }
        .energy-group summary::-webkit-details-marker {
            display: none;
        }
        .energy-group-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            cursor: pointer;
            background: var(--energy-theme-dark);
            color: #fff;
        }
        .energy-group:not([open]) .energy-group-summary {
            border-radius: 10px;
        }
        .energy-group-icon {
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
        .energy-group-meta {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        .energy-group-title {
            font-weight: 700;
            line-height: 1.2;
        }
        .energy-group-subtitle {
            font-size: 12px;
            opacity: 0.92;
            line-height: 1.2;
        }
        .energy-group-right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .energy-group-arrow {
            transition: transform 0.2s ease;
        }
        .energy-group[open] .energy-group-arrow {
            transform: rotate(180deg);
        }
        .energy-group-content {
            background: var(--energy-theme-soft);
            padding: 14px;
            border-top: 1px solid var(--energy-theme-light);
        }
        .energy-grid .energy-card {
            display: block;
            border: 1px solid var(--energy-theme-light);
            border-radius: 10px;
            padding: 14px 14px 12px 14px;
            background: #fff;
            color: #212529;
            text-decoration: none;
            height: 100%;
            transition: all 0.18s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .energy-grid .energy-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--energy-theme-dark);
            color: #212529;
            text-decoration: none;
        }
        .energy-grid .energy-icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--energy-theme-soft);
            color: var(--energy-theme-dark);
            font-size: 18px;
            margin-bottom: 10px;
        }
        .energy-grid .energy-title {
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 4px;
            min-height: 38px;
        }
        .energy-grid .energy-subtitle {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.35;
            min-height: 32px;
            margin-bottom: 8px;
        }
        .energy-grid .energy-action {
            font-size: 12px;
            font-weight: 600;
            color: var(--energy-theme-dark);
        }
    </style>

    <section class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Pemakaian Energi</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card energy-hero mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                    <div class="pr-3">
                        <h3 class="mb-1" style="font-weight:700;">Pemakaian Energi</h3>
                        <div style="opacity:.95;">Pilih area meter untuk input data, monitoring, dan report.</div>
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
                    <h3 class="card-title mb-0"><i class="fas fa-th-large mr-1"></i> Pilihan Menu Pemakaian Energi</h3>
                </div>
                <div class="card-body">
                    <div class="energy-group-list">
                        <?php foreach ($menuGroups as $group): ?>
                            <details class="energy-group">
                                <summary class="energy-group-summary">
                                    <span class="energy-group-icon"><i class="fas <?= htmlspecialchars($group['icon']) ?>"></i></span>
                                    <span class="energy-group-meta">
                                        <span class="energy-group-title"><?= htmlspecialchars($group['title']) ?></span>
                                        <span class="energy-group-subtitle"><?= htmlspecialchars($group['subtitle']) ?></span>
                                    </span>
                                    <span class="energy-group-right">
                                        <span><?= count($group['items']) ?> Modul</span>
                                        <i class="fas fa-chevron-down energy-group-arrow"></i>
                                    </span>
                                </summary>
                                <div class="energy-group-content">
                                    <div class="row energy-grid">
                                        <?php foreach ($group['items'] as $menu): ?>
                                            <div class="col-sm-6 col-lg-4 mb-3">
                                                <a href="<?= htmlspecialchars($menu['file']) ?>" class="energy-card">
                                                    <span class="energy-icon"><i class="fas <?= htmlspecialchars($menu['icon']) ?>"></i></span>
                                                    <div class="energy-title"><?= htmlspecialchars($menu['title']) ?></div>
                                                    <div class="energy-subtitle"><?= htmlspecialchars($menu['subtitle']) ?></div>
                                                    <div class="energy-action">Buka Modul <i class="fas fa-arrow-right ml-1"></i></div>
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
