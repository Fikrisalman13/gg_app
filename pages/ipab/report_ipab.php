<?php
// report_ipab.php - Laporan IPAB (pH, DH, Turbidity)
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';

$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$shiftFilter = '';
$shiftFilterLabel = '';
$start = date('Y-m-01');
$end = date('Y-m-d');
$filterApplied = false;
$errorMsg = '';

$menuId = 223; // Menu IPAB
requireView($conn, $menuId);

$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
};

$normalizeShift = function ($shift) {
    $shift = trim((string) $shift);
    if ($shift === 'Pagi') return '1';
    if ($shift === 'Siang') return '2';
    if ($shift === 'Malam') return '3';
    if (in_array($shift, ['1','2','3'], true)) return $shift;
    return $shift;
};

$getDateStr = function ($val) {
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    return is_string($val) ? $val : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shiftInput = trim((string) ($_POST['shift'] ?? ''));
    $shiftFilter = isset($shiftLabel[$shiftInput]) ? $shiftInput : '';
    $shiftFilterLabel = $shiftFilter !== '' ? $shiftLabel[$shiftFilter] : '';
    $startInput = $normalizeDate($_POST['start_date'] ?? '');
    $endInput = $normalizeDate($_POST['end_date'] ?? '');

    if ($startInput === '' || $endInput === '') {
        $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    } else {
        $start = $startInput;
        $end = $endInput;
        $filterApplied = true;
    }
}

$rows = [];

if ($errorMsg === '') {
    // pH
    $sqlPh = "SELECT Id, Tanggal, Shift, Pengecek, Bak_Clarivier, Bak_2, Bak_3, Bak_4, Air_Sungai FROM dbo.ph_ipab WHERE Tanggal BETWEEN ? AND ?";
    $paramsPh = [$start, $end];
    if ($shiftFilter !== '') {
        $sqlPh .= " AND (Shift = ? OR Shift = ?)";
        $paramsPh[] = $shiftFilter;
        $paramsPh[] = $shiftFilterLabel;
    }
    $stmtPh = sqlsrv_query($conn, $sqlPh, $paramsPh);
    if ($stmtPh === false) {
        $errorMsg = 'Query pH gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($r = sqlsrv_fetch_array($stmtPh, SQLSRV_FETCH_ASSOC)) {
            $dateStr = $getDateStr($r['Tanggal']);
            $shiftCode = $normalizeShift($r['Shift']);
            $key = $dateStr . '|' . $shiftCode;
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'tanggal' => $dateStr,
                    'shift' => $shiftCode,
                    'ph_clarivier' => null,
                    'ph_bak2' => null,
                    'ph_bak3' => null,
                    'dh_bak3' => null,
                    'tb_bak3' => null,
                    'ph_bak4' => null,
                    'dh_bak4' => null,
                    'tb_bak4' => null,
                    'ph_airsungai' => null,
                ];
            }
            $rows[$key]['ph_clarivier'] = $r['Bak_Clarivier'] ?? null;
            $rows[$key]['ph_bak2'] = $r['Bak_2'] ?? null;
            $rows[$key]['ph_bak3'] = $r['Bak_3'] ?? null;
            $rows[$key]['ph_bak4'] = $r['Bak_4'] ?? null;
            $rows[$key]['ph_airsungai'] = $r['Air_Sungai'] ?? null;
        }
    }

    // DH
    $sqlDh = "SELECT Id, Tanggal, Shift, Pengecek, Bak_3, Bak_4 FROM dbo.dh_ipab WHERE Tanggal BETWEEN ? AND ?";
    $paramsDh = [$start, $end];
    if ($shiftFilter !== '') {
        $sqlDh .= " AND (Shift = ? OR Shift = ?)";
        $paramsDh[] = $shiftFilter;
        $paramsDh[] = $shiftFilterLabel;
    }
    $stmtDh = sqlsrv_query($conn, $sqlDh, $paramsDh);
    if ($stmtDh === false) {
        $errorMsg = 'Query DH gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($r = sqlsrv_fetch_array($stmtDh, SQLSRV_FETCH_ASSOC)) {
            $dateStr = $getDateStr($r['Tanggal']);
            $shiftCode = $normalizeShift($r['Shift']);
            $key = $dateStr . '|' . $shiftCode;
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'tanggal' => $dateStr,
                    'shift' => $shiftCode,
                    'ph_clarivier' => null,
                    'ph_bak2' => null,
                    'ph_bak3' => null,
                    'dh_bak3' => null,
                    'tb_bak3' => null,
                    'ph_bak4' => null,
                    'dh_bak4' => null,
                    'tb_bak4' => null,
                    'ph_airsungai' => null,
                ];
            }
            $rows[$key]['dh_bak3'] = $r['Bak_3'] ?? null;
            $rows[$key]['dh_bak4'] = $r['Bak_4'] ?? null;
        }
    }

    // Turbidity
    $sqlTb = "SELECT Id, Tanggal, Shift, Pengecek, Bak_3, Bak_4 FROM dbo.turbidity_ipab WHERE Tanggal BETWEEN ? AND ?";
    $paramsTb = [$start, $end];
    if ($shiftFilter !== '') {
        $sqlTb .= " AND (Shift = ? OR Shift = ?)";
        $paramsTb[] = $shiftFilter;
        $paramsTb[] = $shiftFilterLabel;
    }
    $stmtTb = sqlsrv_query($conn, $sqlTb, $paramsTb);
    if ($stmtTb === false) {
        $errorMsg = 'Query Turbidity gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($r = sqlsrv_fetch_array($stmtTb, SQLSRV_FETCH_ASSOC)) {
            $dateStr = $getDateStr($r['Tanggal']);
            $shiftCode = $normalizeShift($r['Shift']);
            $key = $dateStr . '|' . $shiftCode;
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'tanggal' => $dateStr,
                    'shift' => $shiftCode,
                    'ph_clarivier' => null,
                    'ph_bak2' => null,
                    'ph_bak3' => null,
                    'dh_bak3' => null,
                    'tb_bak3' => null,
                    'ph_bak4' => null,
                    'dh_bak4' => null,
                    'tb_bak4' => null,
                    'ph_airsungai' => null,
                ];
            }
            $rows[$key]['tb_bak3'] = $r['Bak_3'] ?? null;
            $rows[$key]['tb_bak4'] = $r['Bak_4'] ?? null;
        }
    }
}

$rowsList = array_values($rows);
usort($rowsList, function($a, $b) {
    if ($a['tanggal'] === $b['tanggal']) {
        return strcmp($a['shift'], $b['shift']);
    }
    return strcmp($a['tanggal'], $b['tanggal']);
});

$valueCols = [
    'ph_clarivier', 'ph_bak2', 'ph_bak3', 'dh_bak3', 'tb_bak3',
    'ph_bak4', 'dh_bak4', 'tb_bak4', 'ph_airsungai'
];

$avg = [];
$cnt = [];
foreach ($valueCols as $col) {
    $avg[$col] = 0;
    $cnt[$col] = 0;
}
foreach ($rowsList as $r) {
    foreach ($valueCols as $col) {
        if (is_numeric($r[$col])) {
            $avg[$col] += $r[$col];
            $cnt[$col]++;
        }
    }
}

$formatVal = function($col, $val) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return htmlspecialchars((string)$val);
    if (strpos($col, 'dh_') === 0 || strpos($col, 'tb_') === 0) {
        return htmlspecialchars((string)intval($val));
    }
    return htmlspecialchars(number_format((float)$val, 2, '.', ''));
};

// Target settings (load from file if present)
$defaultTargets = [
    'BakClarivier' => [
        'ph' => ['min' => 6.5, 'max' => 9],
    ],
    'Bak2' => [
        'ph' => ['min' => 7, 'max' => 8],
    ],
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
    'AirSungai' => [
        'ph' => ['min' => 7, 'max' => 8],
    ],
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

$targetLabel = function($bak, $param) use ($getTargetEntry) {
    $vals = $getTargetEntry($bak, $param);
    if (isset($vals['min']) && $vals['min'] !== '' && isset($vals['max']) && $vals['max'] !== '') {
        return $vals['min'] . '-' . $vals['max'];
    }
    if (isset($vals['std']) && $vals['std'] !== '') return (string)$vals['std'];
    if (isset($vals['min']) && $vals['min'] !== '') return (string)$vals['min'];
    if (isset($vals['max']) && $vals['max'] !== '') return (string)$vals['max'];
    return '-';
};

$colToTarget = [
    'ph_clarivier' => ['BakClarivier', 'ph'],
    'ph_bak2' => ['Bak2', 'ph'],
    'ph_bak3' => ['Bak3', 'ph'],
    'dh_bak3' => ['Bak3', 'dh'],
    'tb_bak3' => ['Bak3', 'turbidity'],
    'ph_bak4' => ['Bak4', 'ph'],
    'dh_bak4' => ['Bak4', 'dh'],
    'tb_bak4' => ['Bak4', 'turbidity'],
    'ph_airsungai' => ['AirSungai', 'ph'],
];

$getCellClass = function($col, $value) use ($colToTarget, $getTargetEntry) {
    if (!is_numeric($value)) return '';
    $map = $colToTarget[$col] ?? null;
    if (!$map) return '';
    $vals = $getTargetEntry($map[0], $map[1]);
    if (empty($vals)) return '';
    $val = floatval($value);

    if (isset($vals['min']) && $vals['min'] !== '' && isset($vals['max']) && $vals['max'] !== '') {
        if ($val < floatval($vals['min'])) return 'ipab-low';
        if ($val > floatval($vals['max'])) return 'ipab-high';
        return '';
    }
    if (isset($vals['std']) && $vals['std'] !== '') {
        $std = floatval($vals['std']);
        if ($val < $std) return 'ipab-low';
        if ($val > $std) return 'ipab-high';
        return '';
    }
    if (isset($vals['max']) && $vals['max'] !== '') {
        return ($val > floatval($vals['max'])) ? 'ipab-high' : '';
    }
    if (isset($vals['min']) && $vals['min'] !== '') {
        return ($val < floatval($vals['min'])) ? 'ipab-low' : '';
    }
    return '';
};
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <h1 class="m-0">Laporan Kualitas Air IPAB</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm mb-3">
                <?php if (!$filterApplied && empty($rowsList)): ?>
                    <div class="alert alert-info mb-2" role="alert">
                        <i class="fas fa-info-circle me-1"></i> Pilih rentang tanggal untuk menampilkan data
                    </div>
                <?php endif; ?>

                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;">
                    <h3 class="card-title m-0">
                        <i class="fas fa-filter"></i> Filter Rentang Tanggal
                    </h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="shift" class="form-label fw-bold">Shift</label>
                                <select id="shift" name="shift" class="form-control form-control-sm">
                                    <option value="">Semua Shift</option>
                                    <?php foreach ($shiftLabel as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $shiftFilter === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap: 8px;">
                                <button type="submit" name="filter" value="1" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                    <i class="fas fa-search"></i> Proses
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="resetFilterBtn">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex mt-3" style="gap: 8px;">
                        <form method="post" action="export_excel_ipab.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <input type="hidden" name="shift" value="<?= htmlspecialchars($shiftFilter) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </form>
                        <form method="post" action="export_pdf_ipab.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <input type="hidden" name="shift" value="<?= htmlspecialchars($shiftFilter) ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Export PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i> Hasil Data Kualitas Air IPAB</h3>
                    </div>

                    <style>
                        .ipab-report-table { border-color: #dee2e6; }
                        .ipab-report-table th, .ipab-report-table td { text-align: center; vertical-align: middle; }
                        .ipab-report-table thead th {
                            background: #2f343b;
                            color: #fff;
                            font-weight: 700;
                            border-color: #dee2e6;
                        }
                        .ipab-report-table thead tr:nth-child(2) th {
                            background: #4a4f57;
                        }
                        .ipab-report-table tr.avg-row td {
                            background: #bdbdbd;
                            color: #111;
                            font-weight: 700;
                        }
                        .ipab-report-table tr.target-row td {
                            background: #bdbdbd;
                            color: #111;
                            font-weight: 700;
                        }
                        .ipab-report-table td.empty-cell { color: #777; }
                        .ipab-report-table td.ipab-high {
                            background: #ff9ea6;
                            color: #5c0008;
                            font-weight: 700;
                            box-shadow: inset 0 0 0 1px #dc3545;
                        }
                        .ipab-report-table td.ipab-low {
                            background: #ffeb3b;
                            color: #222;
                            font-weight: 700;
                        }
                        .ipab-report-table tr.avg-row td.ipab-low { background: #ffeb3b; color: #222; }
                        .ipab-report-table tr.avg-row td.ipab-high { background: #ff9ea6; color: #5c0008; }

                        .ipab-table-container {
                            max-height: 70vh;
                            overflow-y: auto;
                            overflow-x: auto;
                            position: relative;
                            border: 1px solid #dee2e6;
                        }
                        .ipab-report-table thead th {
                            position: sticky;
                            z-index: 10;
                            top: 0;
                        }
                        .ipab-report-table thead tr:nth-child(1) th {
                            top: 0;
                            z-index: 11;
                        }
                        .ipab-report-table thead tr:nth-child(2) th {
                            top: 32px;
                            z-index: 10;
                        }
                    </style>

                    <div class="table-responsive ipab-table-container">
                        <table class="table table-bordered table-sm ipab-report-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">Tanggal</th>
                                    <th rowspan="2">Shift</th>
                                    <th colspan="1">Bak Clarivier</th>
                                    <th colspan="1">Bak 2</th>
                                    <th colspan="3">Bak 3</th>
                                    <th colspan="3">Bak 4</th>
                                    <th colspan="1">Air Sungai</th>
                                </tr>
                                <tr>
                                    <th>pH</th>
                                    <th>pH</th>
                                    <th>pH</th>
                                    <th>DH</th>
                                    <th>Turbidity</th>
                                    <th>pH</th>
                                    <th>DH</th>
                                    <th>Turbidity</th>
                                    <th>pH</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rowsList)): ?>
                                    <tr><td colspan="11">Tidak ada data</td></tr>
                                <?php else: foreach ($rowsList as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['tanggal']) ?></td>
                                        <td><?= htmlspecialchars($shiftLabel[$r['shift']] ?? $r['shift']) ?></td>
                                        <?php
                                        $cols = [
                                            'ph_clarivier' => $r['ph_clarivier'],
                                            'ph_bak2' => $r['ph_bak2'],
                                            'ph_bak3' => $r['ph_bak3'],
                                            'dh_bak3' => $r['dh_bak3'],
                                            'tb_bak3' => $r['tb_bak3'],
                                            'ph_bak4' => $r['ph_bak4'],
                                            'dh_bak4' => $r['dh_bak4'],
                                            'tb_bak4' => $r['tb_bak4'],
                                            'ph_airsungai' => $r['ph_airsungai'],
                                        ];
                                        foreach ($cols as $colKey => $colVal):
                                            $cls = $getCellClass($colKey, $colVal);
                                        ?>
                                            <td class="<?= htmlspecialchars($cls) ?>"><?= $formatVal($colKey, $colVal) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                    <tr class="avg-row">
                                        <td colspan="2">Rata - Rata</td>
                                        <?php foreach ($valueCols as $col): ?>
                                            <?php
                                            $val = $cnt[$col] ? ($avg[$col] / $cnt[$col]) : null;
                                            $txt = $val === null ? '-' : $formatVal($col, $val);
                                            $cls = $getCellClass($col, $val);
                                            ?>
                                            <td class="<?= htmlspecialchars($cls) ?>"><?= $txt ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr class="target-row">
                                        <td colspan="2">Target</td>
                                        <?php foreach ($valueCols as $col): ?>
                                            <?php
                                            $map = $colToTarget[$col] ?? null;
                                            $lab = $map ? $targetLabel($map[0], $map[1]) : '-';
                                            ?>
                                            <td><?= htmlspecialchars($lab) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var resetBtn = document.getElementById('resetFilterBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('shift').selectedIndex = 0;
            window.location.href = window.location.pathname;
        });
    }
});
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
