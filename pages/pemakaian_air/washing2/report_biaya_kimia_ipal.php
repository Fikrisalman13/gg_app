<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = $normalizeDate($_POST['start_date'] ?? '');
    $endInput = $normalizeDate($_POST['end_date'] ?? '');
    if ($startInput === '' || $endInput === '') {
        $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$days = [1, 2, 3, 4, 5, 6, 7];

$fmtNum = function($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};

$monthMap = [
    'January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI',
    'July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$groups = [
    ['title' => 'NUTRISI AEROBIK', 'harga' => 9000, 'rows' => [360, 360, 360, 360, 360, 360, 360]],
    ['title' => 'ALUM LIQUID', 'harga' => 1400, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'ORGANIK KOAGULAN', 'harga' => 5500, 'rows' => [1604.56, 1810.70, 1357.71, 1953.70, 1954.26, 1604.56, 1604.56], 'hargaLabel' => 'Include'],
    ['title' => 'H2SO4 60%', 'harga' => 1072.50, 'rows' => [700, 800, 400, 750, 600, 550, 600], 'hargaLabel' => 'Include'],
    ['title' => 'APE 01 CATIONIC/POLIMER', 'harga' => 66000, 'rows' => [15, 15, 13, 12, 13.5, 10.5, 11.5], 'hargaLabel' => 'Include'],
    ['title' => 'COUSTIC SODA 48 BE LIQ', 'harga' => 5885, 'rows' => [1123.2, 1152.0, 1238.4, 1382.4, 1411.2, 1123.2, 1109.6], 'hargaLabel' => 'Include'],
    ['title' => 'WTA PROSES', 'harga' => 26273.28, 'rows' => [244.8, 288.0, 216.0, 316.8, 316.8, 259.2, 259.2], 'hargaLabel' => 'Include'],
    ['title' => 'AFK', 'harga' => 10166.82, 'rows' => [200, 170, 150, 100, 150, 150, 150], 'hargaLabel' => 'Include'],
    ['title' => 'KURIFLOCK PROSES', 'harga' => 67100, 'rows' => [16.5, 17.5, 15.5, 16.5, 16.5, 21.0, 14.5], 'hargaLabel' => 'Include'],
    ['title' => 'ACID PHOSPHORIC', 'harga' => 22857.153, 'rows' => [3, 2, 3, 3, 3, 2, 3], 'hargaLabel' => 'Include'],
    ['title' => 'NS 15', 'harga' => 26436.19, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'POLIMER ANIONIC PROSES', 'harga' => 55000, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'DECLAIRING AGENT', 'harga' => 24813.49, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'FLOFIX DFDY', 'harga' => 24507.67, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'TRIFOAM S 100 (SAMPLE)', 'harga' => 19350, 'rows' => [4, 2, 2, 4, 4.5, 5.5, 7], 'hargaLabel' => 'Exclude'],
    ['title' => 'KURIFLOCK DAF LAMA', 'harga' => 67100, 'rows' => [3.74, 4.88, 3.62, 3.09, 3.33, 2.87, 3.16], 'hargaLabel' => 'Include'],
    ['title' => 'POLIMER ANIONIC DAF LAMA', 'harga' => 55000, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'WTA DAF LAMA', 'harga' => 26273.28, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'PAC POWDER DAF LAMA', 'harga' => 7300, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Exclude'],
    ['title' => 'ORGANIK KOAGULAN DAF LAMA', 'harga' => 5500, 'rows' => [185.13, 267.41, 255.08, 205.70, 226.77, 205.70, 205.70], 'hargaLabel' => 'Include'],
    ['title' => 'COUSTIC SODA 48 BE LIQ DAF LAMA', 'harga' => 5885, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'KURIFLOCK DAF 3 BARU', 'harga' => 67100, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'POLIMER ANIONIC DAF 3 BARU', 'harga' => 55000, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'WTA DAF 3 BARU', 'harga' => 26273.28, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'DECLAIRING AGENT DAF 3 BARU', 'harga' => 24813.49, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'FLOFIX DADY DAF 3 BARU', 'harga' => 24507.67, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'COUSTIC SODA 48 BE LIQ DAF 3 BARU', 'harga' => 5885, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'ORGANIK KOAGULAN DAF 3 BARU', 'harga' => 5500, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'KURIFLOCK DAF 2 BARU', 'harga' => 67100, 'rows' => [11, 12.09, 10.94, 12.38, 11.80, 10.93, 11.23], 'hargaLabel' => 'Include'],
    ['title' => 'POLIMER ANIONIC DAF 2 BARU', 'harga' => 55000, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'WTA DAF 2 BARU', 'harga' => 26273.28, 'rows' => [720, 633.6, 781.71, 691.2, 633.6, 720, 748.8], 'hargaLabel' => 'Include'],
    ['title' => 'DECLAIRING AGENT DAF 2 BARU', 'harga' => 24813.49, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'FLOFIX DADY DAF 2 BARU', 'harga' => 24507.67, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'ORGANIK KOAGULAN DAF 2 BARU', 'harga' => 5500, 'rows' => [802.27, 864.00, 781.71, 802.57, 822.84, 822.85, 781.71], 'hargaLabel' => 'Include'],
    ['title' => 'PAC POWDER', 'harga' => 7300, 'rows' => [0, 0, 0, 0, 0, 0, 0], 'hargaLabel' => 'Include'],
    ['title' => 'COUSTIC SODA 48 BE LIQ DAF 2 BARU', 'harga' => 5885, 'rows' => [288, 288, 345.6, 115.2, 172.8, 172.8, 172.8], 'hargaLabel' => 'Include'],
];

foreach ($groups as &$g) {
    $g['biaya'] = [];
    $sumPakai = 0.0;
    $sumBiaya = 0.0;
    foreach ($g['rows'] as $v) {
        $pakai = (float)$v;
        $biaya = $pakai * (float)$g['harga'];
        $g['biaya'][] = $biaya;
        $sumPakai += $pakai;
        $sumBiaya += $biaya;
    }
    $g['sum_pakai'] = $sumPakai;
    $g['sum_biaya'] = $sumBiaya;
    $g['avg_pakai'] = count($g['rows']) ? ($sumPakai / count($g['rows'])) : 0;
    $g['avg_biaya'] = count($g['rows']) ? ($sumBiaya / count($g['rows'])) : 0;
}
unset($g);

$totalPerDay = [];
foreach ($days as $idx => $d) {
    $totalBiaya = 0.0;
    foreach ($groups as $g) {
        $totalBiaya += (float)$g['biaya'][$idx];
    }
    $totalPerDay[$idx] = $totalBiaya;
}
$sumTotalBiaya = array_sum($totalPerDay);
$avgTotalBiaya = count($totalPerDay) ? ($sumTotalBiaya / count($totalPerDay)) : 0;
?>

<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">Report Biaya Kimia IPAL</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/washing2/washing2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
    <section class="content"><div class="container-fluid">

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;"><h3 class="card-title m-0"><i class="fas fa-filter"></i> Filter Rentang Tanggal</h3></div>
            <div class="card-body">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-lg-3"><label for="start_date" class="form-label fw-bold">Dari Tanggal</label><input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required></div>
                        <div class="col-sm-6 col-lg-3"><label for="end_date" class="form-label fw-bold">Sampai Tanggal</label><input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required></div>
                        <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap:8px;">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report Biaya Kimia IPAL</h3>
                </div>

                <style>
                  .ipal-wrap{overflow-x:auto;overflow-y:visible;background:#efefef;padding:8px;border:1px solid #c9c9c9}
                  .ipal-report{border-collapse:collapse;font-size:11px;min-width:6500px;background:#fff}
                  .ipal-report th,.ipal-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
                  .ipal-report .title{background:#e6e6e6;font-weight:700;font-size:28px;line-height:1.1}
                  .ipal-report .subtitle{background:#e6e6e6;font-weight:700;font-size:24px;line-height:1.1}
                  .ipal-report .month{background:#f5f5f5;font-weight:700;font-size:22px}
                  .ipal-report .group{background:#ffef00;font-weight:700}
                  .ipal-report .sub{background:#ffef00;font-weight:700}
                  .ipal-report .data{background:#d7d4b8}
                  .ipal-report .datecol{background:#dfe7d3;font-weight:700;min-width:82px}
                  .ipal-report .sum{background:#e2e2d1;font-weight:700}
                  .ipal-report .include{background:#ffef00;font-weight:700}
                  .ipal-report .totalbiaya-head,.ipal-report .totalbiaya-cell{background:#ffc000;font-weight:700}
                  .ipal-report .sticky-left{position:sticky;left:0;z-index:4}
                </style>

                <div class="card-body p-2">
                    <div class="ipal-wrap">
                        <table class="table table-sm ipal-report">
                            <thead>
                                <tr>
                                    <th class="title" colspan="<?= (count($groups) * 3) + 2 ?>">PEMAKAIAN OBAT UNTUK PENGOLAHAN AIR LIMBAH</th>
                                </tr>
                                <tr>
                                    <th class="subtitle" colspan="<?= (count($groups) * 3) + 2 ?>">KIMIA AKHIR</th>
                                </tr>
                                <tr>
                                    <th class="month sticky-left" colspan="2">Bulan : <?= htmlspecialchars($monthLabel) ?></th>
                                    <th class="month" colspan="<?= count($groups) * 3 ?>"></th>
                                </tr>
                                <tr>
                                    <th class="datecol sticky-left" rowspan="2">TANGGAL</th>
                                    <th class="totalbiaya-head" rowspan="2">TOTAL BIAYA</th>
                                    <?php foreach ($groups as $g): ?>
                                        <th class="group" colspan="3"><?= htmlspecialchars($g['title']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($groups as $g): ?>
                                        <th class="sub">PAKAI (Kg)</th>
                                        <th class="sub">HARGA (Rp)</th>
                                        <th class="sub">BIAYA (Rp)</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $idx => $day): ?>
                                    <tr>
                                        <td class="datecol sticky-left"><?= htmlspecialchars($day) ?></td>
                                        <td class="totalbiaya-cell"><?= htmlspecialchars($fmtNum($totalPerDay[$idx], 2)) ?></td>
                                        <?php foreach ($groups as $g): ?>
                                            <td class="data"><?= htmlspecialchars($fmtNum($g['rows'][$idx], 2)) ?></td>
                                            <td class="data"><?= htmlspecialchars($fmtNum($g['harga'], 2)) ?></td>
                                            <td class="data"><?= htmlspecialchars($fmtNum($g['biaya'][$idx], 2)) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="sum">
                                    <td class="sticky-left">TOTAL</td>
                                    <td class="totalbiaya-cell"><?= htmlspecialchars($fmtNum($sumTotalBiaya, 2)) ?></td>
                                    <?php foreach ($groups as $g): ?>
                                        <td><?= htmlspecialchars($fmtNum($g['sum_pakai'], 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($g['harga'], 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($g['sum_biaya'], 2)) ?></td>
                                    <?php endforeach; ?>
                                </tr>

                                <tr class="sum">
                                    <td class="sticky-left">RATA-RATA</td>
                                    <td class="totalbiaya-cell"><?= htmlspecialchars($fmtNum($avgTotalBiaya, 2)) ?></td>
                                    <?php foreach ($groups as $g): ?>
                                        <td><?= htmlspecialchars($fmtNum($g['avg_pakai'], 2)) ?></td>
                                        <td class="include"><?= htmlspecialchars($g['hargaLabel'] ?? 'Include') ?></td>
                                        <td><?= htmlspecialchars($fmtNum($g['avg_biaya'], 2)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div></section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
