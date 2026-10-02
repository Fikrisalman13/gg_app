<?php
// dashboard_ipal.php - Ringkasan dan analisa kualitas air IPAL
session_start();
ob_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 191; // samakan akses dengan laporan IPAL
requireView($conn, $menuId);

$bakList = ['PekatBesar','PekatKecil','Anoxit','EqualSum','Daff1','Daff2','Daff3','Aerasi1','Aerasi2','Aerasi3','Aerasi4','Sedimen','Outlet','SedimenBiologi','Flogulan','PostSedimen','Dwatring','SelokanPekat','SelokanReaktif','Raspam'];
$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$qualityStandards = [
    'SelokanPekat'=>['cod'=>['std'=>5000]],
    'PekatBesar'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>4000]],
    'Anoxit'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>2000]],
    'EqualSum'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>1300]],
    'Daff1'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>2000]],
    'Daff2'=>['cod'=>['std'=>800],'ph'=>['min'=>7,'max'=>9]],
    'Daff3'=>['cod'=>['std'=>800],'ptco'=>['std'=>600]],
    'SelokanReaktif'=>['cod'=>['std'=>1300]],
    'Aerasi1'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>250]],
    'Aerasi2'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>200]],
    'Aerasi3'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>180]],
    'Aerasi4'=>['cod'=>['std'=>300],'ph'=>['min'=>6,'max'=>9]],
    'Outlet'=>['ph'=>['min'=>7,'max'=>9],'cod'=>['std'=>115],'tss'=>['std'=>30],'ptco'=>['std'=>200]],
    'Dwatring'=>['cod'=>['std'=>2000],'tss'=>['std'=>30]]
];

$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
};

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';
$data = [];

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

if ($errorMsg === '') {
    $sql = "
        SELECT
            COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) AS Tanggal,
            COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) AS Shift,
            ph.PekatBesar AS ph_PekatBesar, ph.PekatKecil AS ph_PekatKecil, ph.Anoxit AS ph_Anoxit, ph.EqualSum AS ph_EqualSum,
            ph.Daff1 AS ph_Daff1, ph.Daff2 AS ph_Daff2, ph.Daff3 AS ph_Daff3,
            ph.Aerasi1 AS ph_Aerasi1, ph.Aerasi2 AS ph_Aerasi2, ph.Aerasi3 AS ph_Aerasi3, ph.Aerasi4 AS ph_Aerasi4,
            ph.Sedimen AS ph_Sedimen, ph.SelokanPekat AS ph_SelokanPekat, ph.Outlet AS ph_Outlet,
            cod.PekatBesar AS cod_PekatBesar, cod.PekatKecil AS cod_PekatKecil, cod.Anoxit AS cod_Anoxit, cod.EqualSum AS cod_EqualSum,
            cod.Daff1 AS cod_Daff1, cod.Daff2 AS cod_Daff2, cod.Daff3 AS cod_Daff3,
            cod.Aerasi1 AS cod_Aerasi1, cod.Aerasi2 AS cod_Aerasi2, cod.Aerasi3 AS cod_Aerasi3, cod.Aerasi4 AS cod_Aerasi4,
            cod.Sedimen AS cod_Sedimen, cod.Dwatring AS cod_Dwatring, cod.SelokanPekat AS cod_SelokanPekat, cod.SelokanReaktif AS cod_SelokanReaktif, cod.Outlet AS cod_Outlet,
            tss.TSS_0 AS tss_TSS0, tss.Pekat_Besar AS tss_PekatBesar, tss.Pekat_Kecil AS tss_PekatKecil, tss.Anoxit AS tss_Anoxit, tss.Equal AS tss_EqualSum,
            tss.Daff_1 AS tss_Daff1, tss.Daff_2 AS tss_Daff2, tss.Daff_3 AS tss_Daff3,
            tss.Aerasi_1 AS tss_Aerasi1, tss.Aerasi_2 AS tss_Aerasi2, tss.Aerasi_3 AS tss_Aerasi3, tss.Aerasi_4 AS tss_Aerasi4,
            tss.Sedimen AS tss_Sedimen, tss.Dwatring AS tss_Dwatring, tss.Outlet AS tss_Outlet,
            mlss.Aerasi1 AS mlss_Aerasi1, mlss.Aerasi2 AS mlss_Aerasi2, mlss.Aerasi3 AS mlss_Aerasi3, mlss.Aerasi4 AS mlss_Aerasi4, mlss.Raspam AS mlss_Raspam,
            ptco.EqualSum AS ptco_EqualSum, ptco.Daff1 AS ptco_Daff1, ptco.Daff2 AS ptco_Daff2, ptco.Daff3 AS ptco_Daff3, ptco.Aerasi1 AS ptco_Aerasi1, ptco.SedimenBiologi AS ptco_SedimenBiologi,
            ptco.Flogulan AS ptco_Flogulan, ptco.PostSedimen AS ptco_PostSedimen, ptco.Dwatring AS ptco_Dwatring, ptco.Outlet AS ptco_Outlet
        FROM dbo.PH_Air ph
        FULL OUTER JOIN dbo.COD_air cod ON ph.Tanggal = cod.Tanggal AND ph.Shift = cod.Shift
        FULL OUTER JOIN dbo.TSS_Air tss ON COALESCE(ph.Tanggal, cod.Tanggal) = tss.Tanggal AND COALESCE(ph.Shift, cod.Shift) = tss.Shift
        FULL OUTER JOIN dbo.MLSS_Air mlss ON COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal) = mlss.Tanggal AND COALESCE(ph.Shift, cod.Shift, tss.Shift) = mlss.Shift
        FULL OUTER JOIN dbo.PTCO_Air ptco ON COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal) = ptco.Tanggal AND COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift) = ptco.Shift
        WHERE COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) BETWEEN ? AND ?
        ORDER BY Tanggal, Shift
    ";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$overview = [
    'total_rows' => count($data),
    'total_measurements' => 0,
    'exceed_total' => 0,
    'param_counts' => ['ph' => 0, 'cod' => 0, 'tss' => 0, 'mlss' => 0, 'ptco' => 0],
    'param_exceed' => ['ph' => 0, 'cod' => 0, 'tss' => 0, 'mlss' => 0, 'ptco' => 0],
    'unique_days' => 0,
    'unique_shifts' => 0,
];
$dates = [];
$shifts = [];
$breaches = [];
$shiftBreachCounts = ['1' => 0, '2' => 0, '3' => 0];

$checkThreshold = function ($bak, $param, $value) use ($qualityStandards) {
    // returns 'low' | 'high' | false
    if (!isset($qualityStandards[$bak][$param]) || !is_numeric($value)) {
        return false;
    }
    $limits = $qualityStandards[$bak][$param];
    $val = floatval($value);

    if (isset($limits['min']) && isset($limits['max'])) {
        if ($val < floatval($limits['min'])) return 'low';
        if ($val > floatval($limits['max'])) return 'high';
        return false;
    }
    if (isset($limits['std'])) {
        if ($val < floatval($limits['std'])) return 'low';
        if ($val > floatval($limits['std'])) return 'high';
        return false;
    }
    if (isset($limits['max'])) {
        if ($val < floatval($limits['max'])) return 'low';
        if ($val > floatval($limits['max'])) return 'high';
        return false;
    }
    if (isset($limits['min'])) {
        if ($val < floatval($limits['min'])) return 'low';
        if ($val > floatval($limits['min'])) return 'high';
        return false;
    }
    return false;
};

foreach ($data as $row) {
    $tanggal = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
    $shift = $row['Shift'];
    if ($tanggal) {
        $dates[$tanggal] = true;
    }
    if ($shift) {
        $shifts[$shift] = true;
    }
    $allParams = ['ph','cod','tss','mlss','ptco'];
    foreach ($bakList as $bak) {
        foreach ($allParams as $param) {
            $field = $param . '_' . $bak;
            $value = $row[$field] ?? null;
            if (!is_numeric($value)) {
                continue;
            }
            $overview['param_counts'][$param]++;
            $overview['total_measurements']++;
            $status = $checkThreshold($bak, $param, $value);
            if ($status !== false) {
                // count any breach (low or high) as an exceed for dashboard summary
                $overview['param_exceed'][$param]++;
                $overview['exceed_total']++;
                if ($shift && isset($shiftBreachCounts[$shift])) {
                    $shiftBreachCounts[$shift]++;
                }
                if (!isset($breaches[$bak][$param])) {
                    $thresholdVal = $qualityStandards[$bak][$param]['max'] ?? $qualityStandards[$bak][$param]['min'] ?? $qualityStandards[$bak][$param]['std'] ?? null;
                    $breaches[$bak][$param] = [
                        'count' => 0,
                        'max' => $value,
                        'threshold' => $thresholdVal,
                        'status' => $status,
                        'last_date' => $tanggal,
                        'last_shift' => $shiftLabel[$shift] ?? $shift,
                    ];
                }
                $breaches[$bak][$param]['count']++;
                // update max and last occurrence
                if ($value > $breaches[$bak][$param]['max']) {
                    $breaches[$bak][$param]['max'] = $value;
                }
                $breaches[$bak][$param]['last_date'] = $tanggal;
                $breaches[$bak][$param]['last_shift'] = $shiftLabel[$shift] ?? $shift;
                $breaches[$bak][$param]['status'] = $status;
            }
        }
    }
}

$overview['unique_days'] = count($dates);
$overview['unique_shifts'] = count($shifts);
$complianceRate = $overview['total_measurements'] > 0
    ? round(100 - (($overview['exceed_total'] / $overview['total_measurements']) * 100), 1)
    : 100;

$breachList = [];
foreach ($breaches as $bak => $params) {
    foreach ($params as $param => $stats) {
        $breachList[] = [
            'bak' => $bak,
            'param' => strtoupper($param),
            'count' => $stats['count'],
            'max' => $stats['max'],
            'threshold' => $stats['threshold'],
            'last_date' => $stats['last_date'],
            'last_shift' => $stats['last_shift'],
        ];
    }
}
usort($breachList, function ($a, $b) {
    if ($a['count'] === $b['count']) {
        return $b['max'] <=> $a['max'];
    }
    return $b['count'] <=> $a['count'];
});
$topBreaches = array_slice($breachList, 0, 5);

$dominantShift = null;
$dominantShiftCount = 0;
foreach ($shiftBreachCounts as $shiftKey => $count) {
    if ($count > $dominantShiftCount) {
        $dominantShiftCount = $count;
        $dominantShift = $shiftKey;
    }
}
$paramDominance = [];
foreach (['cod', 'ph', 'tss', 'ptco', 'mlss'] as $paramName) {
    if ($overview['exceed_total'] > 0 && ($overview['param_exceed'][$paramName] ?? 0) > 0) {
        $paramDominance[$paramName] = round((($overview['param_exceed'][$paramName] ?? 0) / max(1, $overview['exceed_total'])) * 100, 1);
    } else {
        $paramDominance[$paramName] = 0;
    }
}

$insights = [];
$recommendations = [];

if ($overview['total_measurements'] === 0) {
    $insights[] = 'Belum ada pembacaan pada rentang ini, sehingga kondisi IPAL masih belum terverifikasi.';
    $recommendations[] = 'Pastikan operator melakukan input harian agar tren lingkungan dapat dipantau kontinu.';
} elseif ($overview['exceed_total'] > 0) {
    $insights[] = sprintf(
        'Kepatuhan hanya %.1f%% dengan %d pembacaan melampaui ambang batas; pola ini menandakan kualitas buangan mulai menekan daya dukung sungai.',
        $complianceRate,
        $overview['exceed_total']
    );
    $dominantShiftLabel = $dominantShift ? ($shiftLabel[$dominantShift] ?? ('Shift ' . $dominantShift)) : null;
    if ($dominantShiftLabel && $dominantShiftCount > 0) {
        $insights[] = sprintf(
            'Sebanyak %d pelanggaran terjadi saat shift %s, indikasi adanya mismatch operasi (misal beban produksi) pada jam tersebut.',
            $dominantShiftCount,
            $dominantShiftLabel
        );
    }
    if (!empty($topBreaches)) {
        $top = $topBreaches[0];
        $insights[] = sprintf(
            'Unit %s dengan parameter %s menjadi penyumbang terbesar (%d kali, puncak %.2f vs batas %.2f).',
            $top['bak'],
            $top['param'],
            $top['count'],
            $top['max'],
            $top['threshold']
        );
    }
    if ($paramDominance['cod'] >= $paramDominance['ph']) {
        $insights[] = sprintf('%.1f%% pelanggaran berasal dari COD — jika tidak ditekan, oksigen terlarut di badan air akan habis dan biota dasar mati.', $paramDominance['cod']);
    } else {
        $insights[] = sprintf('%.1f%% pelanggaran berasal dari pH — fluktuasi ini berpotensi melarutkan logam berat dan memperparah toksisitas.', $paramDominance['ph']);
    }
    $insights[] = 'Air limbah yang terus melewati batas akan menimbulkan bioakumulasi, mengganggu rantai makanan, serta membuka risiko sanksi administratif.';

    if (!empty($topBreaches)) {
        $primary = $topBreaches[0];
        $recommendations[] = sprintf(
            'Jalankan root cause analysis untuk %s (%s) dengan sampling ulang, pengecekan dosing kimia, dan audit log sheet operator.',
            $primary['bak'],
            $primary['param']
        );
    }
    if ($dominantShiftLabel && $dominantShiftCount > 0) {
        $recommendations[] = sprintf(
            'Atur ulang jadwal equalizing atau buffer saat shift %s; gunakan interlock alarm agar lonjakan segera dikompensasikan.',
            $dominantShiftLabel
        );
    }
    if ($paramDominance['cod'] > 0) {
        $recommendations[] = 'Optimalkan aerasi dan nutrisi bak mikroba untuk mempercepat degradasi COD; sertakan uji respirometri mingguan.';
    }
    if ($paramDominance['ph'] > 0) {
        $recommendations[] = 'Kalibrasi sensor pH, evaluasi dosing kapur/acid, dan aktifkan mixing otomatis ketika deviasi >0.3 pH unit.';
    }
    $recommendations[] = 'Susun contingency plan: jika tiga shift berturut-turut melebihi ambang, tahan discharge dan lakukan re-cycle internal untuk mencegah kerusakan ekosistem.';
} else {
    $insights[] = 'Semua pembacaan berada di bawah standar; sistem IPAL beroperasi stabil dan aman bagi lingkungan.';
    $insights[] = 'Pertahankan SOP saat ini, namun lanjutkan pengawasan proaktif agar tren positif terjaga.';
    $recommendations[] = 'Lakukan audit internal bulanan untuk memastikan alat ukur tetap presisi meski tidak ada pelanggaran.';
    $recommendations[] = 'Uji sampling acak di hilir untuk memastikan tidak ada kontaminasi sekunder di jaringan pembuangan.';
}

if ($overview['unique_days'] > 0) {
    $insights[] = sprintf('Cakupan monitoring: %d hari dan %d shift.', $overview['unique_days'], $overview['unique_shifts']);
}
if (empty($recommendations)) {
    $recommendations[] = 'Tidak ada tindakan khusus yang diperlukan saat ini.';
}

// Persiapan data chart (server-side) -- dipakai oleh conditional rendering di HTML
$chartLabels = [];
$chart_ph = $chart_cod = $chart_tss = $chart_ptco = $chart_mlss = [];
$dateMap = [];
foreach ($data as $row) {
    $tgl = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
    if (!isset($dateMap[$tgl])) $dateMap[$tgl] = ['ph'=>[], 'cod'=>[], 'tss'=>[], 'ptco'=>[], 'mlss'=>[]];
    if (isset($row['ph_Outlet']) && is_numeric($row['ph_Outlet'])) $dateMap[$tgl]['ph'][] = $row['ph_Outlet'];
    if (isset($row['cod_Outlet']) && is_numeric($row['cod_Outlet'])) $dateMap[$tgl]['cod'][] = $row['cod_Outlet'];
    if (isset($row['tss_Outlet']) && is_numeric($row['tss_Outlet'])) $dateMap[$tgl]['tss'][] = $row['tss_Outlet'];
    if (isset($row['ptco_Outlet']) && is_numeric($row['ptco_Outlet'])) $dateMap[$tgl]['ptco'][] = $row['ptco_Outlet'];
    if (isset($row['mlss_Aerasi4']) && is_numeric($row['mlss_Aerasi4'])) $dateMap[$tgl]['mlss'][] = $row['mlss_Aerasi4'];
}
ksort($dateMap);
foreach ($dateMap as $d => $vals) {
    $chartLabels[] = $d;
    $chart_ph[] = count($vals['ph']) ? array_sum($vals['ph'])/count($vals['ph']) : null;
    $chart_cod[] = count($vals['cod']) ? array_sum($vals['cod'])/count($vals['cod']) : null;
    $chart_tss[] = count($vals['tss']) ? array_sum($vals['tss'])/count($vals['tss']) : null;
    $chart_ptco[] = count($vals['ptco']) ? array_sum($vals['ptco'])/count($vals['ptco']) : null;
    $chart_mlss[] = count($vals['mlss']) ? array_sum($vals['mlss'])/count($vals['mlss']) : null;
}

?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <h1 class="m-0">Dashboard Kualitas Air IPAL</h1>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;">
                    <h3 class="card-title m-0">
                        <i class="fas fa-filter"></i> Rentang Analisa
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($errorMsg) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                    <i class="fas fa-sync"></i> Proses
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (empty($data)): ?>
                <div class="alert alert-info">Belum ada data untuk rentang tanggal ini. Silakan pilih tanggal lain.</div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="small-box bg-light border">
                            <div class="inner">
                                <p class="text-muted mb-1">Total Catatan</p>
                                <h3><?= number_format($overview['total_rows']) ?></h3>
                                <span><?= number_format($overview['unique_days']) ?> hari | <?= number_format($overview['unique_shifts']) ?> shift</span>
                            </div>
                            <div class="icon text-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-database"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="small-box bg-light border">
                            <div class="inner">
                                <p class="text-muted mb-1">Pembacaan Dipantau</p>
                                <h3><?= number_format($overview['total_measurements']) ?></h3>
                                <span><?= number_format($overview['param_counts']['ph']) ?> pH · <?= number_format($overview['param_counts']['cod']) ?> COD · <?= number_format($overview['param_counts']['tss']) ?> TSS · <?= number_format($overview['param_counts']['mlss']) ?> MLSS · <?= number_format($overview['param_counts']['ptco']) ?> PTCO</span>
                            </div>
                            <div class="icon text-success">
                                <i class="fas fa-water"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="small-box bg-light border">
                            <div class="inner">
                                <p class="text-muted mb-1">Persentase Aman</p>
                                <h3><?= $complianceRate ?>%</h3>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $complianceRate) ?>%"></div>
                                </div>
                            </div>
                            <div class="icon text-success">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="small-box bg-light border border-danger">
                            <div class="inner">
                                <p class="text-muted mb-1">Melebihi Standar</p>
                                <h3 class="text-danger mb-0"><?= number_format($overview['exceed_total']) ?></h3>
                                <small><?= number_format($overview['param_exceed']['cod']) ?> COD · <?= number_format($overview['param_exceed']['ph']) ?> pH · <?= number_format($overview['param_exceed']['ptco']) ?> PTCO</small>
                            </div>
                            <div class="icon text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                                    <!-- Grafik Data IPAL: visualisasi sederhana -->
                    <div class="col-12">
                    <div class="card mb-3" id="card-trend-ipal">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                            <div class="d-flex align-items-center w-100">
                                <h3 class="card-title m-0 flex-grow-1"><i class="fas fa-chart-bar me-1"></i> Grafik Tren Kualitas Air IPAL</h3>
                                <button type="button" class="btn btn-light btn-sm ms-auto" id="btnExportGrafikIpal">
                                    <i class="fas fa-image"></i> Export Grafik
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                        <?php if (empty($chartLabels)): ?>
                            <div class="alert alert-info mb-2">
                                <strong>Tidak ada data IPAL untuk Outlet pada rentang ini.</strong>
                                <div class="mt-1">Coba perpanjang rentang tanggal atau periksa kembali input operator. Anda juga dapat menampilkan data untuk bak lain di laporan lengkap.</div>
                            </div>
                            <div class="row gx-2">
                                <div class="col-sm-6 col-lg-4"><small class="text-muted">Catatan: <?= number_format($overview['total_rows']) ?> baris</small></div>
                                <div class="col-sm-6 col-lg-4"><small class="text-muted">Hari tercover: <?= number_format($overview['unique_days']) ?></small></div>
                                <div class="col-sm-6 col-lg-4"><small class="text-muted">Kepatuhan: <?= $complianceRate ?>%</small></div>
                            </div>
                        <?php else: ?>
                            <canvas id="ipalChart" class="w-100" style="max-width:100%;height:280px;"></canvas>
                            <div class="small text-muted mt-2">Grafik menampilkan tren rata-rata harian untuk parameter utama (pH, COD, TSS, PTCO, MLSS) pada bak Outlet.</div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div> 
                    <div class="col-12">
                        <div class="card mb-3" id="card-breach">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                                <h3 class="card-title m-0"><i class="fas fa-radiation me-1"></i> Zona Perlu Tindakan</h3>
                            </div>
                                    <div class="card-body">
                                <?php if (empty($topBreaches)): ?>
                                    <div class="alert alert-info mb-0">
                                        <strong>Tidak ada pelanggaran signifikan.</strong>
                                        <div class="mt-1">Tidak ada data pelanggaran yang menonjol pada rentang tanggal ini. Coba perluas rentang tanggal atau periksa input operator.</div>
                                    </div>
                                    <div class="small text-muted mt-2">Ringkasan: <?= number_format($overview['exceed_total']) ?> pelanggaran • Kepatuhan <?= $complianceRate ?>% • <?= number_format($overview['unique_days']) ?> hari</div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($topBreaches as $b): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div class="me-3">
                                                    <div class="fw-bold"><?= htmlspecialchars($b['bak']) ?> <small class="text-muted"><?= htmlspecialchars($b['param']) ?></small></div>
                                                    <div class="small text-muted">Maks <?= number_format($b['max'],2) ?> · Batas <?= number_format($b['threshold'],2) ?> — Terakhir <?= htmlspecialchars($b['last_date']) ?> (<?= htmlspecialchars($b['last_shift']) ?>)</div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-danger rounded-pill fs-6"><?= (int) $b['count'] ?>x</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="small text-muted mt-2">Tampilkan detail pada laporan IPAL untuk data historis dan tindakan.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <!-- Kolom kanan dikosongkan agar layout lebih lega dan fokus pada grafik -->
                </div>



                <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
                <script>
                // Siapkan data PHP ke JS (hanya saat ada data)
                <?php
                    $chartLabels = [];
                    $phData = [];
                    $codData = [];
                    $tssData = [];
                    $ptcoData = [];
                    $mlssData = [];
                    $dateMap = [];
                    foreach ($data as $row) {
                        $tgl = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
                        if (!isset($dateMap[$tgl])) {
                            $dateMap[$tgl] = ['ph' => [], 'cod' => [], 'tss' => [], 'ptco' => [], 'mlss' => []];
                        }
                        if (is_numeric($row['ph_Outlet'])) $dateMap[$tgl]['ph'][] = $row['ph_Outlet'];
                        if (is_numeric($row['cod_Outlet'])) $dateMap[$tgl]['cod'][] = $row['cod_Outlet'];
                        if (is_numeric($row['tss_Outlet'])) $dateMap[$tgl]['tss'][] = $row['tss_Outlet'];
                        if (is_numeric($row['ptco_Outlet'])) $dateMap[$tgl]['ptco'][] = $row['ptco_Outlet'];
                        if (is_numeric($row['mlss_Aerasi4'])) $dateMap[$tgl]['mlss'][] = $row['mlss_Aerasi4'];
                    }
                    foreach ($dateMap as $tgl => $vals) {
                        $chartLabels[] = $tgl;
                        $phData[] = count($vals['ph']) ? array_sum($vals['ph'])/count($vals['ph']) : null;
                        $codData[] = count($vals['cod']) ? array_sum($vals['cod'])/count($vals['cod']) : null;
                        $tssData[] = count($vals['tss']) ? array_sum($vals['tss'])/count($vals['tss']) : null;
                        $ptcoData[] = count($vals['ptco']) ? array_sum($vals['ptco'])/count($vals['ptco']) : null;
                        $mlssData[] = count($vals['mlss']) ? array_sum($vals['mlss'])/count($vals['mlss']) : null;
                    }
                    echo 'const ipalData = ' . json_encode(['labels' => $chartLabels, 'ph' => $phData, 'cod' => $codData, 'tss' => $tssData, 'ptco' => $ptcoData, 'mlss' => $mlssData]) . ';';
                ?>

                if (typeof ipalData !== 'undefined' && ipalData.labels && ipalData.labels.length) {
                    // pastikan Chart.js sudah diload (sudah disertakan sebelumnya)
                    const ctx = document.getElementById('ipalChart').getContext('2d');
                    var ipalChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ipalData.labels,
                            datasets: [
                                { label: 'pH', data: ipalData.ph, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.08)', tension:0.3, spanGaps:true },
                                { label: 'COD', data: ipalData.cod, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.08)', tension:0.3, spanGaps:true },
                                { label: 'TSS', data: ipalData.tss, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.08)', tension:0.3, spanGaps:true },
                                { label: 'PTCO', data: ipalData.ptco, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.08)', tension:0.3, spanGaps:true },
                                { label: 'MLSS', data: ipalData.mlss, borderColor: '#6f42c1', backgroundColor: 'rgba(111,66,193,0.08)', tension:0.3, spanGaps:true }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { position: 'top' } },
                            scales: { y: { beginAtZero:false }, x: { ticks: { maxRotation:45 } } },
                            animation: { duration: 600 }
                        }
                    });
                }

                function exportChartWithWhiteBackground(canvas) {
                    var temp = document.createElement('canvas');
                    temp.width = canvas.width;
                    temp.height = canvas.height;
                    var tctx = temp.getContext('2d');
                    tctx.fillStyle = '#ffffff';
                    tctx.fillRect(0, 0, temp.width, temp.height);
                    tctx.drawImage(canvas, 0, 0);
                    return temp.toDataURL('image/jpeg', 0.95);
                }

                document.getElementById('btnExportGrafikIpal')?.addEventListener('click', function() {
                    var canvas = document.getElementById('ipalChart');
                    if (!canvas) return;
                    var dataUrl = exportChartWithWhiteBackground(canvas);
                    var form = document.getElementById('exportGrafikFormIpal');
                    if (!form) return;
                    form.querySelector('input[name="image_data"]').value = dataUrl;
                    form.submit();
                });
                </script>
                <form id="exportGrafikFormIpal" method="post" action="export_grafik.php" style="display:none;">
                    <input type="hidden" name="image_data" value="">
                    <input type="hidden" name="filename" value="Grafik_IPAL_<?= date('Ymd_His') ?>">
                </form>

            <?php endif; ?>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
