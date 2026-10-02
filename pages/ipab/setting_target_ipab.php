<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 190; // Menu IPAB
requireView($conn, $menuId);

$themeColor = $_SESSION['Theme'] ?? 'primary';

$bakList = ['BakClarivier', 'Bak2', 'Bak3', 'Bak4', 'AirSungai'];
$paramsPerBak = [
    'BakClarivier' => ['ph'],
    'Bak2' => ['ph'],
    'Bak3' => ['ph', 'dh', 'turbidity'],
    'Bak4' => ['ph', 'dh', 'turbidity'],
    'AirSungai' => ['ph'],
];

$defaultTargets = [
    'BakClarivier' => ['ph' => ['min' => 6.5, 'max' => 9]],
    'Bak2' => ['ph' => ['min' => 7, 'max' => 8]],
    'Bak3' => [
        'ph' => ['min' => 7, 'max' => 8],
        'dh' => ['std' => 2],
        'turbidity' => ['min' => 0, 'max' => 5],
    ],
    'Bak4' => [
        'ph' => ['min' => 7, 'max' => 8],
        'dh' => ['std' => 0],
        'turbidity' => ['min' => 0, 'max' => 5],
    ],
    'AirSungai' => ['ph' => ['min' => 7, 'max' => 8]],
];

$targetFile = __DIR__ . '/data/target_ipab.json';
$targets = [];
if (file_exists($targetFile)) {
    $targets = json_decode(file_get_contents($targetFile), true) ?: [];
}
if (empty($targets)) {
    $targets = $defaultTargets;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['targets'] ?? [];
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

    if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents($targetFile, json_encode($new, JSON_PRETTY_PRINT));
    $_SESSION['success'] = 'Target IPAB disimpan.';
    header('Location: setting_target_ipab.php');
    exit;
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Setting Target IPAB</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="report_ipab.php">Report IPAB</a></li>
                        <li class="breadcrumb-item active">Setting Target</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-bullseye mr-1"></i>Target Parameter IPAB</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="setting_target_ipab.php">
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
<?php foreach ($bakList as $bak): ?>
    <?php foreach ($paramsPerBak[$bak] as $param): ?>
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

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
