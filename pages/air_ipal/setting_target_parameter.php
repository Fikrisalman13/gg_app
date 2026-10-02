<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Basic auth + layout
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/menu_constants.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 191; // same as report IPAL
requireView($conn, $menuId);

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Define bak list and params same as report
$bakList = [
    'PekatBesar', 'PekatKecil', 'Anoxit', 'EqualSum',
    'Daff1', 'Daff2', 'Daff3',
    'Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4',
    'Sedimen', 'Outlet',
    'SedimenBiologi', 'Flogulan', 'PostSedimen', 'Dwatring', 'SelokanPekat', 'SelokanReaktif',
    'Raspam'
];

$customBakParams = [
    'SedimenBiologi' => ['ptco'],
    'Flogulan'       => ['ptco'],
    'PostSedimen'    => ['ptco'],
    'Dwatring'       => ['cod','tss','ptco'],
    'SelokanPekat'   => ['cod','ph'],
    'SelokanReaktif' => ['cod','ph'],
    'Raspam'         => ['mlss'],
];

$paramsPerBak = [];
foreach ($bakList as $bak) {
    if (isset($customBakParams[$bak])) {
        $params = $customBakParams[$bak];
    } else {
        $params = ['ph','cod','tss'];
        if (strpos($bak, 'Aerasi') === 0) $params[] = 'mlss';
        // PTCO for some
        $ptcoBaks = ['EqualSum','Daff1','Daff2','Daff3','Aerasi1','SedimenBiologi','Flogulan','PostSedimen','Dwatring','Outlet'];
        if (in_array($bak, $ptcoBaks, true)) $params[] = 'ptco';
    }
    $paramsPerBak[$bak] = $params;
}

// load existing targets file
$targetFile = __DIR__ . '/data/target_parameters.json';
$targets = [];
if (file_exists($targetFile)) {
    $targets = json_decode(file_get_contents($targetFile), true) ?: [];
}

// If no saved targets, prefill with default quality standards from report
$defaultQualityStandards = [
    'SelokanPekat' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 5000],
    ],
    'PekatBesar' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 4000],
    ],
    'Anoxit' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 2000],
    ],
    'EqualSum' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 1300],
    ],
    'Daff1' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 2000],
    ],
    'Daff2' => [
        'cod' => ['std' => 800],
        'ph'  => ['min' => 7.0, 'max' => 9.0],
    ],
    'Daff3' => [
        'cod'  => ['std' => 800],
        'ptco' => ['std' => 600],
    ],
    'SelokanReaktif' => [
        'cod' => ['std' => 1300],
        'ph'  => ['min' => 7.0, 'max' => 9.0],
    ],
    'Aerasi1' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 250],
    ],
    'Aerasi2' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 200],
    ],
    'Aerasi3' => [
        'ph'  => ['min' => 7.0, 'max' => 9.0],
        'cod' => ['std' => 180],
    ],
    'Aerasi4' => [
        'cod' => ['std' => 300],
        'ph'  => ['min' => 6.0, 'max' => 9.0],
    ],
    'Outlet' => [
        'ph'   => ['min' => 7.0, 'max' => 9.0],
        'cod'  => ['std' => 115],
        'tss'  => ['std' => 30],
        'ptco' => ['std' => 200],
    ],
    'Dwatring' => [
        'cod' => ['std' => 2000],
        'tss' => ['std' => 30],
    ],
];

if (empty($targets)) {
    // convert defaultQualityStandards into same form as $targets (min/max/std)
    foreach ($paramsPerBak as $bak => $params) {
        foreach ($params as $param) {
            $val = $defaultQualityStandards[$bak][$param] ?? [];
            $targets[$bak][$param] = [
                'min' => isset($val['min']) ? $val['min'] : '',
                'max' => isset($val['max']) ? $val['max'] : '',
                'std' => isset($val['std']) ? $val['std'] : ''
            ];
        }
    }
}

// handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['targets'] ?? [];
    // sanitize/normalize
    $new = [];
    foreach ($posted as $bak => $params) {
        foreach ($params as $param => $vals) {
            $std = trim($vals['std'] ?? '');
            $min = trim($vals['min'] ?? '');
            $max = trim($vals['max'] ?? '');
            $entry = [];
            if ($std !== '') $entry['std'] = is_numeric($std) ? (float)$std : $std;
            if ($min !== '') $entry['min'] = is_numeric($min) ? (float)$min : $min;
            if ($max !== '') $entry['max'] = is_numeric($max) ? (float)$max : $max;
            if (!empty($entry)) $new[$bak][$param] = $entry;
        }
    }

    // ensure data dir exists
    if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents($targetFile, json_encode($new, JSON_PRETTY_PRINT));
    $_SESSION['success'] = 'Target parameter disimpan.';
    header('Location: setting_target_parameter.php');
    exit;
}

// UI
?>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Setting Target Parameter IPAL</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="report_ipal.php">Report IPAL</a></li>
                        <li class="breadcrumb-item active">Setting Target</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-bullseye mr-1"></i>Target Parameter</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="setting_target_parameter.php">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Bak</th>
                                    <th>Parameter</th>
                                    <th>Min</th>
                                    <th>Max</th>
                                    <th>Std</th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($paramsPerBak as $bak => $params): ?>
    <?php foreach ($params as $param): ?>
        <?php $val = $targets[$bak][$param] ?? []; ?>
        <tr>
            <td><?= htmlspecialchars($bak) ?></td>
            <td><?= htmlspecialchars($param) ?></td>
            <td><input class="form-control form-control-sm" name="targets[<?= htmlspecialchars($bak) ?>][<?= htmlspecialchars($param) ?>][min]" value="<?= htmlspecialchars($val['min'] ?? '') ?>"></td>
            <td><input class="form-control form-control-sm" name="targets[<?= htmlspecialchars($bak) ?>][<?= htmlspecialchars($param) ?>][max]" value="<?= htmlspecialchars($val['max'] ?? '') ?>"></td>
            <td><input class="form-control form-control-sm" name="targets[<?= htmlspecialchars($bak) ?>][<?= htmlspecialchars($param) ?>][std]" value="<?= htmlspecialchars($val['std'] ?? '') ?>"></td>
        </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
                            </tbody>
                        </table>
                        <button class="btn btn-<?= htmlspecialchars($themeColor) ?>">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php');
