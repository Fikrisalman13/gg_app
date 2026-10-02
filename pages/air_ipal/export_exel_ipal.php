<?php
// Export Excel IPAL: CSV
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$shiftFilter = isset($_POST['shift']) ? (string) $_POST['shift'] : '';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_IPAL_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

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
// if a specific shift is requested, add it to the WHERE clause
if ($shiftFilter !== '') {
    $sql .= "\n    AND COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) = ?";
    $params[] = $shiftFilter;
}
// always order
$sql .= "\n    ORDER BY Tanggal, Shift\n";
$executeWithRetry = function(&$conn, $sql, $params) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errs = sqlsrv_errors();
        // if communication error (08S01) attempt one reconnect and retry
        $shouldRetry = false;
        foreach ($errs as $e) {
            if (isset($e['SQLSTATE']) && $e['SQLSTATE'] === '08S01') {
                $shouldRetry = true;
                break;
            }
        }
        if ($shouldRetry) {
            // try reconnect by re-including koneksi (use include to force re-run)
            @sqlsrv_close($conn);
            include $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt !== false) return $stmt;
        }
        // log and return false
        error_log("[export_exel_ipal] SQL error: " . print_r($errs, true));
        return false;
    }
    return $stmt;
};

$stmt = $executeWithRetry($conn, $sql, $params);
if ($stmt === false) {
    $errs = sqlsrv_errors();
    echo "<br/><b>SQL error</b>: " . htmlspecialchars(print_r($errs, true));
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = $row;
}

// Konfigurasi urutan kolom tabel (sinkron dengan report_ipal.php)
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
$qualityStandards = [
    'SelokanPekat' => [ 'cod' => ['std' => 5000] ],
    'PekatBesar' => [ 'ph' => ['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>4000] ],
    'Anoxit' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>2000] ],
    'EqualSum' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>1300] ],
    'Daff1' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>2000] ],
    'Daff2' => [ 'cod'=>['std'=>800], 'ph'=>['min'=>7.0,'max'=>9.0] ],
    'Daff3' => [ 'cod'=>['std'=>800], 'ptco'=>['std'=>600] ],
    'SelokanReaktif' => [ 'cod'=>['std'=>1300] ],
    'Aerasi1' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>250] ],
    'Aerasi2' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>200] ],
    'Aerasi3' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>180] ],
    'Aerasi4' => [ 'cod'=>['std'=>300], 'ph'=>['min'=>6.0,'max'=>9.0] ],
    'Outlet' => [ 'ph'=>['min'=>7.0,'max'=>9.0], 'cod'=>['std'=>115], 'tss'=>['std'=>30], 'ptco'=>['std'=>200] ],
    'Dwatring' => [ 'cod'=>['std'=>2000], 'tss'=>['std'=>30] ],
];
$targetFile = __DIR__ . '/data/target_parameters.json';
if (file_exists($targetFile)) {
    $over = json_decode(file_get_contents($targetFile), true) ?: [];
    if (is_array($over)) {
        foreach ($over as $bak => $params) {
            foreach ($params as $p => $vals) {
                if (!isset($qualityStandards[$bak])) $qualityStandards[$bak] = [];
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
$getCellClass = function ($bak, $param, $value) use ($qualityStandards) {
    if (!isset($qualityStandards[$bak][$param]) || !is_numeric($value)) {
        return '';
    }
    $limits = $qualityStandards[$bak][$param];
    $val = floatval($value);

    if (isset($limits['min']) && isset($limits['max'])) {
        if ($val < floatval($limits['min'])) return 'ipal-low';
        if ($val > floatval($limits['max'])) return 'ipal-high';
        return '';
    }
    if (isset($limits['std'])) {
        if ($val < floatval($limits['std'])) return 'ipal-low';
        if ($val > floatval($limits['std'])) return 'ipal-high';
        return '';
    }
    if (isset($limits['max'])) {
        if ($val < floatval($limits['max'])) return 'ipal-low';
        if ($val > floatval($limits['max'])) return 'ipal-high';
        return '';
    }
    if (isset($limits['min'])) {
        if ($val < floatval($limits['min'])) return 'ipal-low';
        if ($val > floatval($limits['min'])) return 'ipal-high';
        return '';
    }
    return '';
};
$renderCell = function ($bak, $param, $value) use ($getCellClass) {
    $class = $getCellClass($bak, $param, $value);
    $classAttr = $class ? " class='" . $class . "'" : '';
    $content = ($value === null || $value === '') ? '-' : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    return "<td" . $classAttr . ">" . $content . '</td>';
};

$paramsPerBak = [];
$bakList = [];
$bakLabels = [];
$totalCols = 0;
foreach ($tableBaks as $cfg) {
    $bak = $cfg['key'];
    $bakList[] = $bak;
    $bakLabels[$bak] = $cfg['label'];
    $paramsPerBak[$bak] = $cfg['params'];
    $totalCols += count($cfg['params']);
}

// prepare agg arrays
$sum = [];
$count = [];
foreach ($bakList as $bak) {
    foreach ($paramsPerBak[$bak] as $param) {
        $sum[$param][$bak] = 0;
        $count[$param][$bak] = 0;
    }
}

$colSpan = 2 + $totalCols;

echo "<html><head><meta charset='UTF-8'><style>
    /* Excel-specific tweaks: prevent Excel from collapsing row height or moving text to top */
    @page { margin: 1cm; }
    table { mso-table-lspace:0pt; mso-table-rspace:0pt; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
    th, td { border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; mso-number-format:\@; mso-line-height-rule:exactly; }
    tr { mso-height-rule:exactly; height: 20px; }
    th, td { line-height: 20px; }
        th.group { background: #1f4e78; color: #fff; font-weight: bold; }
        th.sub { background: #4f81bd; color: #fff; }
        td.label { text-align: left; font-weight: bold; }
        tr.total td, tr.target-row td { font-weight: bold; background: #bbbbbb; }
        td.ipal-high { background: #ff9ea6; color: #5c0008; font-weight: 700; border-color: #dc3545; }
        td.ipal-low { background: #ffeb3b; color: #222; font-weight: 700; }
        /* allow avg cell coloring to override gray */
        tr.total td.ipal-low { background: #ffeb3b; }
        tr.total td.ipal-high { background: #ff9ea6; }
        .target-row td { white-space: nowrap; }
    </style></head><body>";

echo "<table>
        <tr><th class='group' colspan='$colSpan'>HASIL CHECK AIR LIMBAH</th></tr>
        <tr><th colspan='$colSpan'>PERIODE " . date('d F Y', strtotime($start)) . " s/d " . date('d F Y', strtotime($end)) . "</th></tr>
        <tr><td colspan='$colSpan'>&nbsp;</td></tr>";

echo "<tr>
        <th class='group' rowspan='2'>Tanggal</th>
        <th class='group' rowspan='2'>Shift</th>";
foreach ($bakList as $bak) {
    echo "<th class='group' colspan='" . count($paramsPerBak[$bak]) . "'>" . htmlspecialchars($bakLabels[$bak]) . "</th>";
}
echo "</tr><tr>";
foreach ($bakList as $bak) {
    foreach ($paramsPerBak[$bak] as $p) {
        $label = strtoupper($p);
        if ($p === 'mlss') $label = 'MLSS';
        if ($p === 'ptco') $label = 'PTCO';
        echo "<th class='sub'>" . $label . "</th>";
    }
}
echo "</tr>";

foreach ($data as $row) {
    echo "<tr>";
    $tanggal = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
    $shift = $shiftLabel[$row['Shift']] ?? $row['Shift'];
    echo "<td>" . htmlspecialchars($tanggal) . "</td>";
    echo "<td>" . htmlspecialchars($shift) . "</td>";
    foreach ($bakList as $bak) {
        foreach ($paramsPerBak[$bak] as $param) {
            $prefix = $param === 'ph' ? 'ph_' : ($param === 'cod' ? 'cod_' : ($param === 'tss' ? 'tss_' : ($param === 'mlss' ? 'mlss_' : 'ptco_')));
            $key = $prefix . $bak;
            $val = isset($row[$key]) ? $row[$key] : null;
            if (is_numeric($val)) {
                $sum[$param][$bak] += $val;
                $count[$param][$bak]++;
            }
            echo $renderCell($bak, $param, $val);
        }
    }
    echo "</tr>";
}

// render average row with coloring
echo "<tr class='total'><td colspan='2'>Rata - Rata</td>";
foreach ($bakList as $bak) {
    foreach ($paramsPerBak[$bak] as $param) {
        $avgVal = $count[$param][$bak] ? ($sum[$param][$bak]/$count[$param][$bak]) : null;
        $avgText = $avgVal !== null ? number_format($avgVal, 2) : '-';
        $cls = $avgVal !== null ? $getCellClass($bak, $param, $avgVal) : '';
        $classAttr = $cls ? " class='" . $cls . "'" : '';
        echo "<td" . $classAttr . ">" . htmlspecialchars($avgText) . "</td>";
    }
}
echo "</tr>";

// target label helper
$targetLabel = function($bak, $param) use ($qualityStandards) {
    if (!isset($qualityStandards[$bak][$param])) return '-';
    $limits = $qualityStandards[$bak][$param];
    if (isset($limits['min']) && isset($limits['max'])) return $limits['min'] . '-' . $limits['max'];
    if (isset($limits['std'])) return (string) $limits['std'];
    if (isset($limits['max'])) return (string) $limits['max'];
    if (isset($limits['min'])) return (string) $limits['min'];
    return '-';
};

// render target row
echo "<tr class='target-row'><td colspan='2'>Target</td>";
foreach ($bakList as $bak) {
    foreach ($paramsPerBak[$bak] as $param) {
        $lab = htmlspecialchars($targetLabel($bak, $param));
        echo "<td>" . $lab . "</td>";
    }
}
echo "</tr></table>";

echo "</body></html>";
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
exit;
