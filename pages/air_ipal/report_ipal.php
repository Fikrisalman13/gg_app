<?php
// report_ipal.php - Laporan gabungan PH, COD, TSS, MLSS, PTCO IPAL
session_start();
ob_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Konfigurasi urutan kolom tabel (sesuai kebutuhan report)
$tableBaks = [
    ['key' => 'SelokanPekat',    'label' => 'Selokan Pekat',    'params' => ['cod', 'ph']],
    ['key' => 'PekatBesar',      'label' => 'Pekat Besar',      'params' => ['ph', 'cod', 'tss']],
    ['key' => 'PekatKecil',      'label' => 'Pekat Kecil',      'params' => ['ph', 'cod', 'tss']],
    ['key' => 'Dwatring',        'label' => 'Dwatring',         'params' => ['cod', 'tss', 'ptco']],
    ['key' => 'Anoxit',          'label' => 'Anoxit',           'params' => ['ph', 'cod', 'tss']],
    ['key' => 'Daff1',           'label' => 'Daff 1',           'params' => ['ph', 'cod', 'tss', 'ptco']],
    ['key' => 'SelokanReaktif',  'label' => 'Selokan Reaktif',  'params' => ['cod', 'ph']],
    ['key' => 'EqualSum',        'label' => 'Equal Sum',        'params' => ['ph', 'cod', 'tss', 'ptco']],
    ['key' => 'Daff2',           'label' => 'Daff 2',           'params' => ['ph', 'cod', 'tss', 'ptco']],
    ['key' => 'Daff3',           'label' => 'Daff 3',           'params' => ['ph', 'cod', 'tss', 'ptco']],
    ['key' => 'Aerasi1',         'label' => 'Aerasi 1',         'params' => ['ph', 'cod', 'tss', 'mlss', 'ptco']],
    ['key' => 'Aerasi2',         'label' => 'Aerasi 2',         'params' => ['ph', 'cod', 'tss', 'mlss']],
    ['key' => 'Aerasi3',         'label' => 'Aerasi 3',         'params' => ['ph', 'cod', 'tss', 'mlss']],
    ['key' => 'Aerasi4',         'label' => 'Aerasi 4',         'params' => ['ph', 'cod', 'tss', 'mlss']],
    ['key' => 'Raspam',          'label' => 'Raspam',           'params' => ['mlss']],
    ['key' => 'Sedimen',         'label' => 'Sedimen',          'params' => ['ph', 'cod', 'tss']],
    ['key' => 'SedimenBiologi',  'label' => 'Sedimen Biologi',  'params' => ['ptco']],
    ['key' => 'Flogulan',        'label' => 'Flogulan',         'params' => ['ptco']],
    ['key' => 'PostSedimen',     'label' => 'Post Sedimen',     'params' => ['ptco']],
    ['key' => 'Outlet',          'label' => 'Outlet',           'params' => ['ph', 'cod', 'tss', 'ptco']],
];

$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$shiftFilter = '';
$data = [];
$errorMsg = '';
$start = date('Y-m-01');
$end = date('Y-m-d');
$filterApplied = false;

// Custom parameters for specific baks
$paramsPerBak = [];
$bakLabels = [];
$totalParamCols = 0;
foreach ($tableBaks as $cfg) {
    $bak = $cfg['key'];
    $paramsPerBak[$bak] = $cfg['params'];
    $bakLabels[$bak] = $cfg['label'];
    $totalParamCols += count($cfg['params']);
}

// Standar kualitas air
$qualityStandards = [
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

// Load overrides from settings file if present
$targetFile = __DIR__ . '/data/target_parameters.json';
if (file_exists($targetFile)) {
    $over = json_decode(file_get_contents($targetFile), true) ?: [];
    if (is_array($over)) {
        foreach ($over as $bak => $params) {
            foreach ($params as $p => $vals) {
                if (!isset($qualityStandards[$bak])) $qualityStandards[$bak] = [];
                // merge
                if (isset($vals['min']) || isset($vals['max'])) {
                    if (!isset($qualityStandards[$bak][$p])) $qualityStandards[$bak][$p] = [];
                    if (isset($vals['min'])) $qualityStandards[$bak][$p]['min'] = $vals['min'];
                    if (isset($vals['max'])) $qualityStandards[$bak][$p]['max'] = $vals['max'];
                }
                if (isset($vals['std'])) {
                    if (!isset($qualityStandards[$bak][$p])) $qualityStandards[$bak][$p] = [];
                    $qualityStandards[$bak][$p]['std'] = $vals['std'];
                }
            }
        }
    }
}

// Fungsi untuk menentukan kelas CSS sel berdasarkan nilai dan standar
$getCellClass = function ($bak, $param, $value) use ($qualityStandards) {
    if (!isset($qualityStandards[$bak][$param]) || !is_numeric($value)) {
        return '';
    }
    
    $limits = $qualityStandards[$bak][$param];
    $val = floatval($value);

    // Range (min & max)
    if (isset($limits['min']) && isset($limits['max'])) {
        if ($val < floatval($limits['min'])) return 'ipal-low';
        if ($val > floatval($limits['max'])) return 'ipal-high';
        return '';
    }

    // Single standard ('std')
    if (isset($limits['std'])) {
        if ($val < floatval($limits['std'])) return 'ipal-low';
        if ($val > floatval($limits['std'])) return 'ipal-high';
        return '';
    }

    // Only max
    if (isset($limits['max'])) {
        if ($val < floatval($limits['max'])) return 'ipal-low';
        if ($val > floatval($limits['max'])) return 'ipal-high';
        return '';
    }

    // Only min
    if (isset($limits['min'])) {
        if ($val < floatval($limits['min'])) return 'ipal-low';
        if ($val > floatval($limits['min'])) return 'ipal-high';
        return '';
    }

    return '';
};

// Fungsi render sel tabel
$renderCell = function ($bak, $param, $value) use ($getCellClass) {
    $class = $getCellClass($bak, $param, $value);
    $classAttr = $class ? ' class="' . $class . '"' : '';
    
    if ($value === null || $value === '') {
        $content = '<span class="empty-cell">-</span>';
    } else {
        $content = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    return '<td' . $classAttr . '>' . $content . '</td>';
};

// Utility normalisasi tanggal ke YYYY-MM-DD
$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
};

// Set menuId sesuai menu IPAL
$menuId = 191;
requireView($conn, $menuId);

// Handle Filter Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shiftInput = trim((string) ($_POST['shift'] ?? ''));
    $shiftFilter = isset($shiftLabel[$shiftInput]) ? $shiftInput : '';
    $startInput = $normalizeDate($_POST['start_date'] ?? '');
    $endInput = $normalizeDate($_POST['end_date'] ?? '');
    
    if ($startInput === '' || $endInput === '') {
        $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    } else {
        $start = $startInput;
        $end = $endInput;
        $filterApplied = true;
    }
} elseif (!empty($_GET['start']) && !empty($_GET['end'])) {
    $startGet = $normalizeDate($_GET['start']);
    $endGet = $normalizeDate($_GET['end']);
    $shiftGet = trim((string) ($_GET['shift'] ?? ''));
    $shiftFilter = isset($shiftLabel[$shiftGet]) ? $shiftGet : '';
    
    if ($startGet !== '' && $endGet !== '') {
        $start = $startGet;
        $end = $endGet;
        $filterApplied = true;
    }
}

// Fetch Data
if ($errorMsg === '') {
    $sql = "
        SELECT
            COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) AS Tanggal,
            COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) AS Shift,
            NULL AS ph_TSS0,
            ph.PekatBesar AS ph_PekatBesar, ph.PekatKecil AS ph_PekatKecil, ph.Anoxit AS ph_Anoxit, ph.EqualSum AS ph_EqualSum,
            ph.Daff1 AS ph_Daff1, ph.Daff2 AS ph_Daff2, ph.Daff3 AS ph_Daff3,
            ph.Aerasi1 AS ph_Aerasi1, ph.Aerasi2 AS ph_Aerasi2, ph.Aerasi3 AS ph_Aerasi3, ph.Aerasi4 AS ph_Aerasi4,
            ph.Sedimen AS ph_Sedimen, ph.SelokanPekat AS ph_SelokanPekat, ph.SelokanReaktif AS ph_SelokanReaktif, ph.Outlet AS ph_Outlet,
            NULL AS cod_TSS0,
            cod.PekatBesar AS cod_PekatBesar, cod.PekatKecil AS cod_PekatKecil, cod.Anoxit AS cod_Anoxit, cod.EqualSum AS cod_EqualSum,
            cod.Daff1 AS cod_Daff1, cod.Daff2 AS cod_Daff2, cod.Daff3 AS cod_Daff3,
            cod.Aerasi1 AS cod_Aerasi1, cod.Aerasi2 AS cod_Aerasi2, cod.Aerasi3 AS cod_Aerasi3, cod.Aerasi4 AS cod_Aerasi4,
            cod.Sedimen AS cod_Sedimen, cod.Dwatring AS cod_Dwatring, cod.SelokanPekat AS cod_SelokanPekat, cod.SelokanReaktif AS cod_SelokanReaktif, cod.Outlet AS cod_Outlet,
            tss.TSS_0 AS tss_TSS0,
            tss.Pekat_Besar AS tss_PekatBesar, tss.Pekat_Kecil AS tss_PekatKecil, tss.Anoxit AS tss_Anoxit, tss.Equal AS tss_EqualSum,
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
    ";
    
    $params = [$start, $end];
    
    if ($shiftFilter !== '') {
        $sql .= " AND COALESCE(ph.Shift, cod.Shift, tss.Shift) = ?";
        $params[] = $shiftFilter;
    }
    
    $sql .= " ORDER BY Tanggal, Shift";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
}
?>

<div class="content-wrapper" id="ipalReportWrapper">
    <!-- Header Content -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <h1 class="m-0">Laporan Gabungan Kualitas Air IPAL</h1>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm mb-3">
                <?php if (!$filterApplied && empty($data)): ?>
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
                            <div class="col-sm-6 col-lg-3 d-flex align-items-end gap-4">
                                <button type="submit" name="filter" value="1" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                    <i class="fas fa-search"></i> Proses
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm ms-3" id="resetFilterBtn">
                                    <i class="fas fa-undo"></i> Reset Filter
                                </button>
                            </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var resetBtn = document.getElementById('resetFilterBtn');
                            if (resetBtn) {
                                resetBtn.addEventListener('click', function() {
                                    // Reset filter fields to default values
                                    document.getElementById('start_date').value = '';
                                    document.getElementById('end_date').value = '';
                                    document.getElementById('shift').selectedIndex = 0;
                                    // Optionally submit the form or reload the page to clear filters
                                    window.location.href = window.location.pathname;
                                });
                            }
                        });
                        </script>
                        </div>
                    </form>

                    <div class="d-flex gap-2 mt-3">
                        <form method="post" action="export_exel_ipal.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <input type="hidden" name="shift" value="<?= htmlspecialchars($shiftFilter) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </form>
                        <form method="post" action="export_pdf_ipal_fixed.php" class="m-0 p-0">
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
            <?php elseif (!empty($data)): ?>
                <div class="card ipal-result-card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i> Hasil Data Kualitas Air</h3>
                    </div>

                    <style>
                        .ipal-result-card {
                            border: 0;
                            border-radius: 8px;
                            overflow: hidden;
                        }

                        .ipal-result-card .card-header {
                            border-bottom: 0;
                            min-height: 44px;
                        }

                        .ipal-table-toolbar {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 0.75rem;
                            padding: 0.75rem 1rem;
                            background: #f8fafc;
                            border-bottom: 1px solid #dce3ea;
                            color: #344054;
                            font-size: 0.82rem;
                        }

                        .ipal-table-meta,
                        .ipal-table-legend {
                            display: flex;
                            align-items: center;
                            flex-wrap: wrap;
                            gap: 0.5rem 0.85rem;
                        }

                        .ipal-table-meta span,
                        .ipal-legend-item {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.35rem;
                            white-space: nowrap;
                        }

                        .ipal-legend-dot {
                            width: 0.8rem;
                            height: 0.8rem;
                            border-radius: 3px;
                            border: 1px solid rgba(0,0,0,0.16);
                        }

                        .ipal-legend-dot.high { background: #fecdd3; border-color: #fb7185; }
                        .ipal-legend-dot.low { background: #fde68a; border-color: #f59e0b; }

                        .ipal-table-container {
                            max-height: 72vh;
                            overflow: auto;
                            position: relative;
                            border: 1px solid #dce3ea;
                            border-top: 0;
                            background:
                                linear-gradient(90deg, #fff 30%, rgba(255,255,255,0)),
                                linear-gradient(270deg, #fff 30%, rgba(255,255,255,0)) 100% 0,
                                linear-gradient(90deg, rgba(15,23,42,0.16), rgba(15,23,42,0)),
                                linear-gradient(270deg, rgba(15,23,42,0.16), rgba(15,23,42,0)) 100% 0;
                            background-attachment: local, local, scroll, scroll;
                            background-repeat: no-repeat;
                            background-size: 36px 100%, 36px 100%, 14px 100%, 14px 100%;
                            scrollbar-gutter: stable;
                        }

                        .ipal-table-container::-webkit-scrollbar { width: 12px; height: 12px; }
                        .ipal-table-container::-webkit-scrollbar-track { background: #eef2f6; }
                        .ipal-table-container::-webkit-scrollbar-thumb {
                            background: #98a2b3;
                            border: 3px solid #eef2f6;
                            border-radius: 999px;
                        }
                        .ipal-table-container::-webkit-scrollbar-thumb:hover { background: #667085; }

                        .ipal-table {
                            width: max-content;
                            min-width: 100%;
                            margin-bottom: 0;
                            table-layout: fixed;
                            border-collapse: separate;
                            border-spacing: 0;
                            font-size: 0.76rem;
                            line-height: 1.25;
                            color: #182230;
                        }

                        .ipal-table th,
                        .ipal-table td {
                            min-width: 58px;
                            max-width: 86px;
                            padding: 0.42rem 0.5rem;
                            text-align: center;
                            vertical-align: middle;
                            white-space: nowrap;
                            border-color: #dce3ea !important;
                        }

                        .ipal-table .col-date { left: 0; min-width: 88px; max-width: 88px; }
                        .ipal-table .col-shift { left: 88px; min-width: 72px; max-width: 72px; }
                        .ipal-table .summary-label { left: 0; min-width: 160px; max-width: 160px; }

                        .ipal-table thead th {
                            position: sticky;
                            z-index: 10;
                            border-bottom: 1px solid #9aa4b2 !important;
                            box-shadow: inset -1px 0 0 rgba(255,255,255,0.16);
                        }

                        .ipal-table thead tr:first-child th {
                            top: 0;
                            height: 42px;
                            z-index: 12;
                        }

                        .ipal-table thead tr:nth-child(2) th {
                            top: 42px;
                            z-index: 11;
                        }

                        .ipal-table th.bak-header {
                            background: #29313a;
                            color: #fff;
                            font-weight: 700;
                            font-size: 0.76rem;
                        }

                        .ipal-table th.param-header {
                            background: #586474;
                            color: #fff;
                            font-size: 0.7rem;
                            font-weight: 700;
                            letter-spacing: 0;
                            text-transform: uppercase;
                        }

                        .ipal-table th.sticky-col {
                            z-index: 15;
                            box-shadow: inset -1px 0 0 #98a2b3;
                        }

                        .ipal-table tbody td.sticky-col {
                            position: sticky;
                            z-index: 5;
                            background: #fff;
                            box-shadow: inset -1px 0 0 #dce3ea;
                            font-weight: 600;
                        }

                        .ipal-table tbody tr:nth-child(even) td { background-color: #f8fbfd; }
                        .ipal-table tbody tr:nth-child(even) td.sticky-col { background-color: #f8fbfd; }
                        .ipal-table tbody tr:hover td { background-color: #eef6ff; }
                        .ipal-table tbody tr:hover td.sticky-col { background-color: #eef6ff; }

                        .ipal-table .empty-cell {
                            color: #98a2b3;
                            font-style: italic;
                        }

                        .ipal-table td.ipal-high {
                            background: #fecdd3 !important;
                            color: #7f1d1d;
                            font-weight: 700;
                            box-shadow: inset 0 0 0 1px #fb7185;
                        }

                        .ipal-table td.ipal-low {
                            background: #fde68a !important;
                            color: #713f12;
                            font-weight: 700;
                            box-shadow: inset 0 0 0 1px #f59e0b;
                        }

                        .ipal-table tr.rata2-row td,
                        .ipal-table tr.target-row td {
                            position: sticky;
                            bottom: 35px;
                            z-index: 6;
                            background: #e9eef5 !important;
                            color: #1d2939;
                            font-weight: 700;
                            border-top: 2px solid #b8c2cc !important;
                        }

                        .ipal-table tr.target-row td {
                            bottom: 0;
                            background: #d4dbe4 !important;
                            border-top: 1px solid #a9b4c0 !important;
                        }

                        .ipal-table tr.rata2-row td.sticky-col,
                        .ipal-table tr.target-row td.sticky-col {
                            z-index: 8;
                        }

                        .ipal-table tr.rata2-row td.ipal-low { background: #fde68a !important; color: #713f12; }
                        .ipal-table tr.rata2-row td.ipal-high { background: #fecdd3 !important; color: #7f1d1d; }

                        .ipal-table td.target-ph {
                            text-align: center;
                            vertical-align: middle;
                            padding-left: 0;
                        }

                        /* Fullscreen styles */
                        body.report-ipal-fs-active { overflow: hidden; }
                        body.report-ipal-fs-active #ipalReportWrapper { height: auto !important; min-height: 100vh; }
                        
                        #ipalReportWrapper.fullscreen-active,
                        #ipalReportWrapper:fullscreen,
                        #ipalReportWrapper:-webkit-full-screen,
                        #ipalReportWrapper:-ms-fullscreen {
                            overflow: auto;
                            -webkit-overflow-scrolling: touch;
                            padding: 1.5rem;
                            background: #fff;
                            min-height: 100vh;
                            scrollbar-gutter: stable both-edges;
                            scrollbar-width: thin;
                            scrollbar-color: rgba(0,0,0,0.45) rgba(0,0,0,0.08);
                        }
                        
                        #ipalReportWrapper.fullscreen-active::-webkit-scrollbar { width: 12px; height: 12px; }
                        #ipalReportWrapper.fullscreen-active::-webkit-scrollbar-track { background: rgba(0,0,0,0.08); border-radius: 999px; }
                        #ipalReportWrapper.fullscreen-active::-webkit-scrollbar-thumb {
                            background: rgba(0,0,0,0.45);
                            border-radius: 999px;
                            border: 2px solid rgba(255,255,255,0.6);
                        }
                        #ipalReportWrapper.fullscreen-active::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.65); }

                        /* Fullscreen Toggle Button */
                        .fullscreen-toggle {
                            position: fixed;
                            right: 1.5rem;
                            bottom: 1.5rem;
                            width: 56px;
                            height: 56px;
                            border-radius: 50%;
                            border: none;
                            background: #ffc107;
                            color: #212529;
                            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                            font-size: 1.3rem;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            z-index: 1050;
                            transition: transform 0.2s ease, box-shadow 0.2s ease;
                        }
                        .fullscreen-toggle:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 14px 28px rgba(0,0,0,0.35);
                        }
                        .fullscreen-toggle.active { background: #28a745; color: #fff; }

                        /* Fullscreen Hint */
                        .fullscreen-hint {
                            position: fixed;
                            top: 1rem;
                            left: 50%;
                            transform: translateX(-50%) translateY(-10px);
                            background: rgba(33,37,41,0.9);
                            color: #fff;
                            padding: 0.35rem 1rem;
                            border-radius: 999px;
                            font-size: 0.9rem;
                            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
                            opacity: 0;
                            pointer-events: none;
                            transition: opacity 0.25s ease, transform 0.25s ease;
                            z-index: 1100;
                        }
                        .fullscreen-hint.show { opacity: 1; transform: translateX(-50%) translateY(0); }
                        
                        @media (max-width: 576px) {
                            .ipal-table-toolbar {
                                align-items: flex-start;
                                flex-direction: column;
                            }

                            .fullscreen-toggle {
                                width: 48px;
                                height: 48px;
                                font-size: 1.1rem;
                                right: 1rem;
                                bottom: 1rem;
                            }
                        }
                    </style>

                    <div class="ipal-table-toolbar">
                        <div class="ipal-table-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($start) ?> s/d <?= htmlspecialchars($end) ?></span>
                            <span><i class="fas fa-clock"></i> <?= htmlspecialchars($shiftFilter !== '' ? $shiftLabel[$shiftFilter] : 'Semua Shift') ?></span>
                            <span><i class="fas fa-database"></i> <?= count($data) ?> baris</span>
                        </div>
                        <div class="ipal-table-legend">
                            <span class="ipal-legend-item"><span class="ipal-legend-dot high"></span> Di atas target</span>
                            <span class="ipal-legend-item"><span class="ipal-legend-dot low"></span> Di bawah target</span>
                        </div>
                    </div>

                    <div class="ipal-table-container">
                        <table class="table table-bordered table-sm ipal-table">
                            <thead>
                                <tr>
                                    <th class="bak-header sticky-col col-date" rowspan="2">Tanggal</th>
                                    <th class="bak-header sticky-col col-shift" rowspan="2">Shift</th>
                                    
                                    <?php foreach ($tableBaks as $cfg):
                                        $bak = $cfg['key'];
                                    ?>
                                        <th class="bak-header" colspan="<?= count($paramsPerBak[$bak]) ?>"><?= htmlspecialchars($bakLabels[$bak]) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($tableBaks as $cfg):
                                        $bak = $cfg['key'];
                                        foreach ($paramsPerBak[$bak] as $p):
                                            $label = strtoupper($p);
                                            if ($p === 'mlss') $label = 'MLSS';
                                            if ($p === 'ptco') $label = 'PTCO';
                                    ?>
                                        <th class="param-header"><?= htmlspecialchars($label) ?></th>
                                    <?php endforeach; endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Initialize sum and count arrays
                                $sum = [];
                                $count = [];
                                foreach ($tableBaks as $cfg) {
                                    $bak = $cfg['key'];
                                    foreach ($paramsPerBak[$bak] as $param) {
                                        $sum[$param][$bak] = 0;
                                        $count[$param][$bak] = 0;
                                    }
                                }
                                
                                foreach ($data as $row):
                                    $rowDate = $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
                                    $rowShift = $shiftLabel[$row['Shift']] ?? $row['Shift'];
                                ?>
                                <tr>
                                    <td class="sticky-col col-date"><?= htmlspecialchars($rowDate) ?></td>
                                    <td class="sticky-col col-shift"><?= htmlspecialchars($rowShift) ?></td>
                                    <?php foreach ($tableBaks as $cfg):
                                        $bak = $cfg['key'];
                                    ?>
                                        <?php
                                        foreach ($paramsPerBak[$bak] as $param) {
                                            $key = $param . '_' . $bak;
                                            $val = isset($row[$key]) ? $row[$key] : null;
                                            
                                            if (is_numeric($val)) {
                                                $sum[$param][$bak] += $val;
                                                $count[$param][$bak]++;
                                            }
                                            echo $renderCell($bak, $param, $val);
                                        }
                                        ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (count($data) > 0): ?>
                                    <!-- Row Rata-rata -->
                                    <tr class="rata2-row">
                                        <td class="sticky-col summary-label" colspan="2">Rata-rata</td>
                                        <?php foreach ($tableBaks as $cfg):
                                            $bak = $cfg['key'];
                                        ?>
                                            <?php foreach ($paramsPerBak[$bak] as $param):
                                                $avgVal = $count[$param][$bak] ? ($sum[$param][$bak] / $count[$param][$bak]) : null;
                                                $avgText = $avgVal !== null ? number_format($avgVal, 2) : '-';
                                                
                                                $avgClass = '';
                                                if ($avgVal !== null) {
                                                    $avgClass = $getCellClass($bak, $param, $avgVal);
                                                }
                                                $classAttr = trim('rata2 ' . $avgClass);
                                            ?>
                                                <td class="<?= htmlspecialchars($classAttr) ?>"><?= htmlspecialchars($avgText) ?></td>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>

                                    <?php
                                    // Target label helper
                                    $targetLabel = function($bak, $param) use ($qualityStandards) {
                                        if (!isset($qualityStandards[$bak][$param])) return '-';
                                        $limits = $qualityStandards[$bak][$param];
                                        if (isset($limits['min']) && isset($limits['max'])) return $limits['min'] . '-' . $limits['max'];
                                        if (isset($limits['std'])) return (string) $limits['std'];
                                        if (isset($limits['max'])) return (string) $limits['max'];
                                        if (isset($limits['min'])) return (string) $limits['min'];
                                        return '-';
                                    };
                                    ?>

                                    <!-- Row Target -->
                                    <tr class="rata2-row target-row">
                                        <td class="sticky-col summary-label" colspan="2">Target</td>
                                        <?php foreach ($tableBaks as $cfg):
                                            $bak = $cfg['key'];
                                        ?>
                                            <?php foreach ($paramsPerBak[$bak] as $param):
                                                $tdClass = 'rata2' . ($param === 'ph' ? ' target-ph' : '');
                                            ?>
                                                <td class="<?= htmlspecialchars($tdClass) ?>"><?= htmlspecialchars($targetLabel($bak, $param)) ?></td>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="alert alert-warning">Data tidak ditemukan untuk rentang tanggal yang dipilih.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Fullscreen Controls -->
<div id="ipalFullscreenHint" class="fullscreen-hint">
    <i class="fas fa-desktop me-1"></i> Tekan Esc untuk keluar fullscreen
</div>
<button type="button" id="ipalFullscreenToggle" class="fullscreen-toggle" aria-label="Toggle fullscreen">
    <i class="fas fa-expand-arrows-alt"></i>
</button>

<script>
(function(){
    const wrapper = document.getElementById('ipalReportWrapper');
    const toggleBtn = document.getElementById('ipalFullscreenToggle');
    const icon = toggleBtn ? toggleBtn.querySelector('i') : null;
    const hint = document.getElementById('ipalFullscreenHint');
    
    if (!wrapper || !toggleBtn || !icon) return;

    const requestFs = (el) => {
        if (el.requestFullscreen) return el.requestFullscreen();
        if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
        if (el.msRequestFullscreen) return el.msRequestFullscreen();
        return Promise.reject('Fullscreen API tidak tersedia');
    };

    const exitFs = () => {
        if (document.exitFullscreen) return document.exitFullscreen();
        if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
        if (document.msExitFullscreen) return document.msExitFullscreen();
        return Promise.resolve();
    };

    const setState = (active) => {
        toggleBtn.classList.toggle('active', active);
        wrapper.classList.toggle('fullscreen-active', active);
        document.body.classList.toggle('report-ipal-fs-active', active);
        
        icon.classList.toggle('fa-expand-arrows-alt', !active);
        icon.classList.toggle('fa-compress-arrows-alt', active);
        
        if (hint) {
            hint.classList.toggle('show', active);
            if (active) {
                clearTimeout(setState.timer);
                setState.timer = setTimeout(() => hint.classList.remove('show'), 4000);
            }
        }
    };

    toggleBtn.addEventListener('click', () => {
        const isFs = document.fullscreenElement === wrapper || document.webkitFullscreenElement === wrapper;
        if (isFs) {
            exitFs();
        } else {
            requestFs(wrapper).catch(() => wrapper.scrollIntoView({behavior:'smooth'}));
        }
    });

    document.addEventListener('fullscreenchange', () => {
        const active = document.fullscreenElement === wrapper;
        setState(active);
    });
    document.addEventListener('webkitfullscreenchange', () => {
        const active = document.webkitFullscreenElement === wrapper;
        setState(active);
    });
})();
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
