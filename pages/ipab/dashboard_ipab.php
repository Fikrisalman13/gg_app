<?php
// dashboard_ipab.php - Ringkasan dan analisa kualitas air IPAB
session_start();
ob_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
// samakan akses dengan report IPAB
$menuId = 223;
requireView($conn, $menuId);

$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
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

// target settings (same as report_ipab.php)
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

$getTargetEntry = function($bak, $param) use ($targets, $defaultTargets) {
    if (isset($targets[$bak][$param])) return $targets[$bak][$param];
    if (isset($defaultTargets[$bak][$param])) return $defaultTargets[$bak][$param];
    return [];
};

$checkThreshold = function ($bak, $param, $value) use ($getTargetEntry) {
    if (!is_numeric($value)) return false;
    $vals = $getTargetEntry($bak, $param);
    if (empty($vals)) return false;
    $val = floatval($value);

    if (isset($vals['min']) && $vals['min'] !== '' && isset($vals['max']) && $vals['max'] !== '') {
        if ($val < floatval($vals['min'])) return 'low';
        if ($val > floatval($vals['max'])) return 'high';
        return false;
    }
    if (isset($vals['std']) && $vals['std'] !== '') {
        $std = floatval($vals['std']);
        if ($val < $std) return 'low';
        if ($val > $std) return 'high';
        return false;
    }
    if (isset($vals['max']) && $vals['max'] !== '') {
        if ($val > floatval($vals['max'])) return 'high';
        return false;
    }
    if (isset($vals['min']) && $vals['min'] !== '') {
        if ($val < floatval($vals['min'])) return 'low';
        return false;
    }
    return false;
};

$dataRows = [];
if ($errorMsg === '') {
    $sql = "
        SELECT
            COALESCE(ph.Tanggal, dh.Tanggal, tb.Tanggal) AS Tanggal,
            COALESCE(ph.Shift, dh.Shift, tb.Shift) AS Shift,
            ph.Bak_Clarivier AS ph_BakClarivier,
            ph.Bak_2 AS ph_Bak2,
            ph.Bak_3 AS ph_Bak3,
            ph.Bak_4 AS ph_Bak4,
            ph.Air_Sungai AS ph_AirSungai,
            dh.Bak_3 AS dh_Bak3,
            dh.Bak_4 AS dh_Bak4,
            tb.Bak_3 AS tb_Bak3,
            tb.Bak_4 AS tb_Bak4
        FROM dbo.ph_ipab ph
        FULL OUTER JOIN dbo.dh_ipab dh ON ph.Tanggal = dh.Tanggal AND ph.Shift = dh.Shift
        FULL OUTER JOIN dbo.turbidity_ipab tb ON COALESCE(ph.Tanggal, dh.Tanggal) = tb.Tanggal AND COALESCE(ph.Shift, dh.Shift) = tb.Shift
        WHERE COALESCE(ph.Tanggal, dh.Tanggal, tb.Tanggal) BETWEEN ? AND ?
        ORDER BY Tanggal, Shift
    ";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dataRows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$overview = [
    'total_rows' => count($dataRows),
    'total_measurements' => 0,
    'exceed_total' => 0,
    'param_counts' => ['ph' => 0, 'dh' => 0, 'turbidity' => 0],
    'param_exceed' => ['ph' => 0, 'dh' => 0, 'turbidity' => 0],
    'unique_days' => 0,
    'unique_shifts' => 0,
];
$dates = [];
$shifts = [];
$breaches = [];
$shiftBreachCounts = ['1' => 0, '2' => 0, '3' => 0];

foreach ($dataRows as $row) {
    $tanggal = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
    $shift = $row['Shift'];
    if ($tanggal) $dates[$tanggal] = true;
    if ($shift) $shifts[$shift] = true;

    $measurements = [
        ['BakClarivier', 'ph', $row['ph_BakClarivier'] ?? null],
        ['Bak2', 'ph', $row['ph_Bak2'] ?? null],
        ['Bak3', 'ph', $row['ph_Bak3'] ?? null],
        ['Bak4', 'ph', $row['ph_Bak4'] ?? null],
        ['AirSungai', 'ph', $row['ph_AirSungai'] ?? null],
        ['Bak3', 'dh', $row['dh_Bak3'] ?? null],
        ['Bak4', 'dh', $row['dh_Bak4'] ?? null],
        ['Bak3', 'turbidity', $row['tb_Bak3'] ?? null],
        ['Bak4', 'turbidity', $row['tb_Bak4'] ?? null],
    ];

    foreach ($measurements as [$bak, $param, $value]) {
        if (!is_numeric($value)) continue;
        $overview['param_counts'][$param]++;
        $overview['total_measurements']++;
        $status = $checkThreshold($bak, $param, $value);
        if ($status !== false) {
            $overview['param_exceed'][$param]++;
            $overview['exceed_total']++;
            if ($shift && isset($shiftBreachCounts[$shift])) $shiftBreachCounts[$shift]++;

            if (!isset($breaches[$bak][$param])) {
                $vals = $getTargetEntry($bak, $param);
                $thresholdVal = $vals['max'] ?? $vals['min'] ?? $vals['std'] ?? null;
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
            if ($value > $breaches[$bak][$param]['max']) {
                $breaches[$bak][$param]['max'] = $value;
            }
            $breaches[$bak][$param]['last_date'] = $tanggal;
            $breaches[$bak][$param]['last_shift'] = $shiftLabel[$shift] ?? $shift;
            $breaches[$bak][$param]['status'] = $status;
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
    if ($a['count'] === $b['count']) return $b['max'] <=> $a['max'];
    return $b['count'] <=> $a['count'];
});
$topBreaches = array_slice($breachList, 0, 5);

$chartLabels = [];
$chart_ph = [];
$chart_dh = [];
$chart_turbidity = [];
$dateMap = [];
foreach ($dataRows as $row) {
    $tgl = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
    if (!isset($dateMap[$tgl])) $dateMap[$tgl] = ['ph'=>[], 'dh'=>[], 'turbidity'=>[]];

    $phVals = [
        $row['ph_BakClarivier'] ?? null,
        $row['ph_Bak2'] ?? null,
        $row['ph_Bak3'] ?? null,
        $row['ph_Bak4'] ?? null,
        $row['ph_AirSungai'] ?? null,
    ];
    foreach ($phVals as $v) if (is_numeric($v)) $dateMap[$tgl]['ph'][] = $v;

    if (is_numeric($row['dh_Bak3'] ?? null)) $dateMap[$tgl]['dh'][] = $row['dh_Bak3'];
    if (is_numeric($row['dh_Bak4'] ?? null)) $dateMap[$tgl]['dh'][] = $row['dh_Bak4'];
    if (is_numeric($row['tb_Bak3'] ?? null)) $dateMap[$tgl]['turbidity'][] = $row['tb_Bak3'];
    if (is_numeric($row['tb_Bak4'] ?? null)) $dateMap[$tgl]['turbidity'][] = $row['tb_Bak4'];
}
ksort($dateMap);
foreach ($dateMap as $d => $vals) {
    $chartLabels[] = $d;
    $chart_ph[] = count($vals['ph']) ? round(array_sum($vals['ph']) / count($vals['ph']), 2) : null;
    $chart_dh[] = count($vals['dh']) ? round(array_sum($vals['dh']) / count($vals['dh']), 2) : null;
    $chart_turbidity[] = count($vals['turbidity']) ? round(array_sum($vals['turbidity']) / count($vals['turbidity']), 2) : null;
}

?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <h1 class="m-0">Dashboard Kualitas Air IPAB</h1>
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

            <?php if (empty($dataRows)): ?>
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
                                <span><?= number_format($overview['param_counts']['ph']) ?> pH · <?= number_format($overview['param_counts']['dh']) ?> DH · <?= number_format($overview['param_counts']['turbidity']) ?> Turbidity</span>
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
                                <small><?= number_format($overview['param_exceed']['ph']) ?> pH · <?= number_format($overview['param_exceed']['dh']) ?> DH · <?= number_format($overview['param_exceed']['turbidity']) ?> Turbidity</small>
                            </div>
                            <div class="icon text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card mb-3" id="card-trend-ipab">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                            <div class="d-flex align-items-center w-100">
                                <h3 class="card-title m-0 flex-grow-1">
                                    <i class="fas fa-chart-bar me-1"></i> Grafik Tren Kualitas Air IPAB
                                </h3>
                                <button type="button" class="btn btn-light btn-sm ms-auto" id="btnExportGrafik">
                                <i class="fas fa-image"></i> Export Grafik
                                </button>
                            </div>
                        </div>
                            <div class="card-body">
                                <?php if (empty($chartLabels)): ?>
                                    <div class="alert alert-info mb-2">
                                        <strong>Tidak ada data IPAB untuk rentang ini.</strong>
                                        <div class="mt-1">Coba perpanjang rentang tanggal atau periksa input operator.</div>
                                    </div>
                                <?php else: ?>
                                    <canvas id="ipabChart" class="w-100" style="max-width:100%;height:280px;"></canvas>
                                    <div class="small text-muted mt-2">Grafik menampilkan tren rata-rata harian untuk pH, DH, dan Turbidity.</div>
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
                                        <div class="mt-1">Tidak ada data pelanggaran yang menonjol pada rentang tanggal ini.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($topBreaches as $b): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div class="me-3">
                                                    <div class="fw-bold"><?= htmlspecialchars($b['bak']) ?> <small class="text-muted"><?= htmlspecialchars($b['param']) ?></small></div>
                                                    <div class="small text-muted">Maks <?= number_format($b['max'],2) ?> · Batas <?= htmlspecialchars($b['threshold'] ?? '-') ?> — Terakhir <?= htmlspecialchars($b['last_date']) ?> (<?= htmlspecialchars($b['last_shift']) ?>)</div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-danger rounded-pill fs-6"><?= (int) $b['count'] ?>x</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
                <script>
                <?php
                    echo 'const ipabTrend = ' . json_encode([
                        'labels' => $chartLabels,
                        'ph' => $chart_ph,
                        'dh' => $chart_dh,
                        'turbidity' => $chart_turbidity
                    ]) . ';';
                ?>
                var ipabChartInstance = null;
                if (typeof ipabTrend !== 'undefined' && ipabTrend.labels && ipabTrend.labels.length) {
                    const ctx = document.getElementById('ipabChart').getContext('2d');
                    ipabChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ipabTrend.labels,
                            datasets: [
                                { label: 'pH', data: ipabTrend.ph, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.08)', tension:0.3, spanGaps:true },
                                { label: 'DH', data: ipabTrend.dh, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.08)', tension:0.3, spanGaps:true },
                                { label: 'Turbidity', data: ipabTrend.turbidity, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.08)', tension:0.3, spanGaps:true }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            var v = context.parsed.y;
                                            if (v === null || v === undefined) return context.dataset.label + ': -';
                                            var formatted = Number(v).toFixed(2);
                                            return context.dataset.label + ': ' + formatted;
                                        }
                                    }
                                }
                            },
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

                document.getElementById('btnExportGrafik')?.addEventListener('click', function() {
                    var canvas = document.getElementById('ipabChart');
                    if (!canvas) return;
                    var dataUrl = exportChartWithWhiteBackground(canvas);
                    var form = document.getElementById('exportGrafikForm');
                    if (!form) return;
                    form.querySelector('input[name="image_data"]').value = dataUrl;
                    form.submit();
                });
                </script>
                <form id="exportGrafikForm" method="post" action="export_grafik.php" style="display:none;">
                    <input type="hidden" name="image_data" value="">
                    <input type="hidden" name="filename" value="Grafik_IPAB_<?= date('Ymd_His') ?>">
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
