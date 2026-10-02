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

$menuGroups = [
    [
        'key' => 'kimia',
        'title' => 'Kimia',
        'subtitle' => 'Modul biaya kimia per area',
        'icon' => 'fa-flask',
        'items' => [
            [
                'title' => 'Biaya Kimia IPAL',
                'subtitle' => 'Biaya kimia untuk pengolahan air limbah (IPAL)',
                'file' => '/gg_app/pages/biaya_energi/kimia_ipal/kimia_ipal.php',
                'icon' => 'fa-water'
            ],
            [
                'title' => 'Biaya Kimia IPAB',
                'subtitle' => 'Biaya kimia untuk pengolahan air Bersih (IPAB)',
                'file' => '/gg_app/pages/biaya_energi/kimia_ipab/kimia_ipab.php',
                'icon' => 'fa-tint'
            ],
            [
                'title' => 'Biaya Kimia Boiler',
                'subtitle' => 'Biaya kimia untuk area boiler',
                'file' => '/gg_app/pages/biaya_energi/kimia_boiler/kimia_boiler.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => 'Biaya Kimia Weaving',
                'subtitle' => 'Biaya kimia untuk area weaving',
                'file' => '/gg_app/pages/biaya_energi/kimia_weaving/kimia_weaving.php',
                'icon' => 'fa-industry'
            ],
        ],
    ],
    [
        'key' => 'batu_bara',
        'title' => 'Batu Bara',
        'subtitle' => 'Modul biaya batu bara dan bahan bakar boiler',
        'icon' => 'fa-fire-alt',
        'items' => [
            [
                'title' => 'Boiler Oil Xineng',
                'subtitle' => 'Pemakaian batu bara xineng, extractor, dan cyclone',
                'file' => '/gg_app/pages/biaya_energi/bb_boiler_oil_xineng/bb_boiler_oil_xineng.php',
                'icon' => 'fa-fire-alt'
            ],
            [
                'title' => '20T Baru Longchuan',
                'subtitle' => 'Pemakaian batu bara steam 20 ton, bottom ash, dan fly ash',
                'file' => '/gg_app/pages/biaya_energi/bb_20tbaru_longchuan/bb_20tbaru_longchuan.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => '21T Actom',
                'subtitle' => 'Pemakaian batu bara steam 21 ton, bottom ash, dan fly ash',
                'file' => '/gg_app/pages/biaya_energi/21t_actom/21t_actom.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => 'Boiler Jineng',
                'subtitle' => 'Pemakaian batu bara boiler jineng, buton ash, dan cyclon',
                'file' => '/gg_app/pages/biaya_energi/bb_boiler_jineng/bb_boiler_jineng.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => '20T Lama',
                'subtitle' => 'Pemakaian batu bara steam 20 ton lama, bottom ash, dan fly ash',
                'file' => '/gg_app/pages/biaya_energi/bb_20t_lama/bb_20t_lama.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => 'Boiler Wuxi',
                'subtitle' => 'Pemakaian batu bara boiler wuxi, buton ash, dan fly ash',
                'file' => '/gg_app/pages/biaya_energi/bb_boiler_wuxi/bb_boiler_wuxi.php',
                'icon' => 'fa-fire'
            ],
            [
                'title' => 'LPG Skid Tank',
                'subtitle' => 'Pemakaian LPG skid tank 4000/10000 dan total biaya harian',
                'file' => '/gg_app/pages/biaya_energi/lpg_skid_tank/lpg_skid_tank.php',
                'icon' => 'fa-gas-pump'
            ],
        ],
    ],
    [
        'key' => 'listrik',
        'title' => 'Listrik',
        'subtitle' => 'Modul biaya energi listrik',
        'icon' => 'fa-bolt',
        'items' => [
            [
                'title' => 'Listrik Gardu Induk',
                'subtitle' => 'Pemakaian energi listrik PLN gardu induk dan report harian',
                'file' => '/gg_app/pages/biaya_energi/listrik_gardu_induk/listrik_gardu_induk.php',
                'icon' => 'fa-bolt'
            ],
            [
                'title' => 'KWH Listrik',
                'subtitle' => 'Pencatatan ampere, perhitungan KWH otomatis, dan total biaya',
                'file' => '/gg_app/pages/biaya_energi/kwh_listrik/kwh_listrik.php',
                'icon' => 'fa-bolt'
            ],
            [
                'title' => 'KWH Listrik 2',
                'subtitle' => 'Pencatatan KWH meter harian per mesin dan total biaya',
                'file' => '/gg_app/pages/biaya_energi/kwh_listrik2/kwh_listrik2.php',
                'icon' => 'fa-bolt'
            ],
        ],
    ],
];

$rekapMenu = [
    'title' => 'Rekapan Biaya Energi',
    'subtitle' => 'Rekap biaya energi lintas modul per tanggal',
    'file' => '/gg_app/pages/biaya_energi/rekap_biaya_energi/rekap_biaya_energi.php',
    'icon' => 'fa-table',
];

$totalModules = 1; // + rekap
foreach ($menuGroups as $group) {
    $totalModules += count($group['items'] ?? []);
}
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
        .energy-hero-body {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between;
            align-items: center;
        }
        .energy-hero-main {
            flex: 1 1 360px;
            min-width: 280px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .energy-hero-main .hero-title {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .energy-hero-main .hero-subtitle {
            opacity: .96;
        }
        .energy-hero-side {
            flex: 0 1 520px;
            width: 100%;
            max-width: 520px;
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: 10px;
        }
        .energy-hero-stats {
            display: flex;
            gap: 10px;
            flex: 0 0 auto;
        }
        .energy-hero .small-box {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 10px 10px;
            min-width: 92px;
            height: 64px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
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
        .energy-quick-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 260px;
            flex: 0 0 260px;
            height: 64px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            text-decoration: none;
            padding: 6px 10px;
            transition: all .18s ease;
        }
        .energy-quick-card:hover {
            color: #fff;
            text-decoration: none;
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.26);
            border-color: rgba(255, 255, 255, 0.65);
        }
        .energy-quick-card .quick-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1px;
        }
        .energy-quick-card .quick-subtitle {
            font-size: 10px;
            opacity: 0.95;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .energy-quick-card .quick-action {
            font-size: 10px;
            font-weight: 700;
        }
        @media (max-width: 992px) {
            .energy-hero-side {
                max-width: none;
                justify-items: start;
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .energy-quick-card {
                max-width: none;
                width: 100%;
                height: auto;
                flex: 1 1 220px;
            }
        }
        @media (max-width: 576px) {
            .energy-hero-stats {
                width: 100%;
                flex: 1 1 100%;
                justify-content: space-between;
            }
            .energy-hero .small-box {
                flex: 1 1 calc(50% - 5px);
            }
        }
        .energy-group-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .energy-group {
            border: 1px solid #e5eaf0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .energy-group summary {
            list-style: none;
            cursor: pointer;
        }
        .energy-group summary::-webkit-details-marker {
            display: none;
        }
        .energy-group-summary {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
            background: var(--energy-theme-dark);
            color: #fff;
        }
        .energy-group:not([open]) .energy-group-summary {
            border-bottom: 0;
        }
        .energy-group-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex: 0 0 38px;
        }
        .energy-group-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
            gap: 1px;
            flex: 1 1 auto;
        }
        .energy-group-title {
            font-weight: 700;
            color: #fff;
            line-height: 1.25;
        }
        .energy-group-subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.35;
        }
        .energy-group-right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12px;
            font-weight: 600;
            flex: 0 0 auto;
        }
        .energy-group-arrow {
            transition: transform 0.2s ease;
        }
        .energy-group[open] .energy-group-arrow {
            transform: rotate(180deg);
        }
        .energy-group-content {
            padding: 12px 12px 2px;
            background: var(--energy-theme-soft);
        }
        .energy-subgrid .energy-card {
            display: block;
            border: 1px solid var(--energy-theme-light);
            border-radius: 10px;
            padding: 14px 14px 12px 14px;
            background: #ffffff;
            color: #212529;
            text-decoration: none;
            height: 100%;
            transition: all 0.18s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            position: relative;
        }
        .energy-subgrid .energy-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--energy-theme-light);
            color: #212529;
            text-decoration: none;
        }
        .energy-subgrid .energy-icon {
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
        .energy-subgrid .energy-title {
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 4px;
            min-height: 38px;
        }
        .energy-subgrid .energy-subtitle {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.35;
            min-height: 32px;
            margin-bottom: 8px;
        }
        .energy-subgrid .energy-action {
            font-size: 12px;
            font-weight: 600;
            color: var(--energy-theme-dark);
        }
    </style>

    <section class="content-header">
        <div class="container-fluid">
            
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card energy-hero mb-3">
                <div class="card-body energy-hero-body">
                    <div class="energy-hero-main">
                        <h3 class="hero-title">Biaya Pemakaian Energi</h3>
                        <div class="hero-subtitle">Pilih area untuk input data, monitoring, dan report.</div>
                    </div>
                    <div class="energy-hero-side">
                        <div class="energy-hero-stats">
                            <div class="small-box">
                                <div class="num"><?= $totalModules ?></div>
                                <div class="label">Total Modul</div>
                            </div>
                            <div class="small-box">
                                <div class="num">Modul</div>
                                <div class="label">Fokus Monitoring</div>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($rekapMenu['file']) ?>" class="energy-quick-card">
                            <div class="quick-title"><i class="fas <?= htmlspecialchars($rekapMenu['icon']) ?> mr-1"></i> <?= htmlspecialchars($rekapMenu['title']) ?></div>
                            <div class="quick-subtitle"><?= htmlspecialchars($rekapMenu['subtitle']) ?></div>
                            <div class="quick-action">Buka Modul <i class="fas fa-arrow-right ml-1"></i></div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title mb-0"><i class="fas fa-th-large mr-1"></i> Pilihan Menu</h3>
                </div>
                <div class="card-body">
                    <div class="energy-group-list">
                        <?php foreach ($menuGroups as $idx => $group): ?>
                            <details class="energy-group">
                                <summary class="energy-group-summary">
                                    <span class="energy-group-icon"><i class="fas <?= htmlspecialchars($group['icon']) ?>"></i></span>
                                    <span class="energy-group-meta">
                                        <span class="energy-group-title">Menu <?= htmlspecialchars($group['title']) ?></span>
                                        <span class="energy-group-subtitle"><?= htmlspecialchars($group['subtitle']) ?></span>
                                    </span>
                                    <span class="energy-group-right">
                                        <span><?= count($group['items']) ?> Modul</span>
                                        <i class="fas fa-chevron-down energy-group-arrow"></i>
                                    </span>
                                </summary>
                                <div class="energy-group-content">
                                    <div class="row energy-subgrid">
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

