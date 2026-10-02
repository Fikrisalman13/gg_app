<?php
// Export Excel IPAB: HTML table (Excel-compatible)
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$shiftFilter = isset($_POST['shift']) ? (string) $_POST['shift'] : '';

$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$shiftFilterLabel = ($shiftFilter !== '' && isset($shiftLabel[$shiftFilter])) ? $shiftLabel[$shiftFilter] : '';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_IPAB_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

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

$rows = [];

// pH
$sqlPh = "SELECT Tanggal, Shift, Bak_Clarivier, Bak_2, Bak_3, Bak_4, Air_Sungai FROM dbo.ph_ipab WHERE Tanggal BETWEEN ? AND ?";
$paramsPh = [$start, $end];
if ($shiftFilter !== '') {
    $sqlPh .= " AND (Shift = ? OR Shift = ?)";
    $paramsPh[] = $shiftFilter;
    $paramsPh[] = $shiftFilterLabel;
}
$sqlPh .= " ORDER BY Tanggal, Shift";
$stmtPh = sqlsrv_query($conn, $sqlPh, $paramsPh);
if ($stmtPh === false) {
    echo "<b>SQL error pH</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}
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

// DH
$sqlDh = "SELECT Tanggal, Shift, Bak_3, Bak_4 FROM dbo.dh_ipab WHERE Tanggal BETWEEN ? AND ?";
$paramsDh = [$start, $end];
if ($shiftFilter !== '') {
    $sqlDh .= " AND (Shift = ? OR Shift = ?)";
    $paramsDh[] = $shiftFilter;
    $paramsDh[] = $shiftFilterLabel;
}
$sqlDh .= " ORDER BY Tanggal, Shift";
$stmtDh = sqlsrv_query($conn, $sqlDh, $paramsDh);
if ($stmtDh === false) {
    echo "<b>SQL error DH</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}
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

// Turbidity
$sqlTb = "SELECT Tanggal, Shift, Bak_3, Bak_4 FROM dbo.turbidity_ipab WHERE Tanggal BETWEEN ? AND ?";
$paramsTb = [$start, $end];
if ($shiftFilter !== '') {
    $sqlTb .= " AND (Shift = ? OR Shift = ?)";
    $paramsTb[] = $shiftFilter;
    $paramsTb[] = $shiftFilterLabel;
}
$sqlTb .= " ORDER BY Tanggal, Shift";
$stmtTb = sqlsrv_query($conn, $sqlTb, $paramsTb);
if ($stmtTb === false) {
    echo "<b>SQL error Turbidity</b>: " . htmlspecialchars(print_r(sqlsrv_errors(), true));
    exit;
}
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
        if ($val < floatval($vals['min'])) return 'low';
        if ($val > floatval($vals['max'])) return 'high';
        return '';
    }
    if (isset($vals['std']) && $vals['std'] !== '') {
        $std = floatval($vals['std']);
        if ($val < $std) return 'low';
        if ($val > $std) return 'high';
        return '';
    }
    if (isset($vals['max']) && $vals['max'] !== '') {
        return ($val > floatval($vals['max'])) ? 'high' : '';
    }
    if (isset($vals['min']) && $vals['min'] !== '') {
        return ($val < floatval($vals['min'])) ? 'low' : '';
    }
    return '';
};

$cellStyle = function($class) {
    if ($class === 'high') return " style='background:#ff9ea6;color:#5c0008;font-weight:700;'";
    if ($class === 'low') return " style='background:#ffeb3b;color:#222;font-weight:700;'";
    return '';
};


echo "<html><head><meta charset='UTF-8'><style>
    table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
    th, td { border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; }
    th.group { background: #2f343b; color: #fff; font-weight: bold; }
    th.sub { background: #4a4f57; color: #fff; }
    tr.total td, tr.target-row td { font-weight: bold; background: #bbbbbb; }
    </style></head><body>";

echo "<table>";
echo "<tr><th class='group' colspan='11'>HASIL DATA KUALITAS AIR IPAB</th></tr>";
echo "<tr><th colspan='11'>PERIODE " . htmlspecialchars($start) . " s/d " . htmlspecialchars($end) . "</th></tr>";
echo "<tr><td colspan='11'>&nbsp;</td></tr>";

echo "<tr>
        <th class='group' rowspan='2'>Tanggal</th>
        <th class='group' rowspan='2'>Shift</th>
        <th class='group' colspan='1'>Bak Clarivier</th>
        <th class='group' colspan='1'>Bak 2</th>
        <th class='group' colspan='3'>Bak 3</th>
        <th class='group' colspan='3'>Bak 4</th>
        <th class='group' colspan='1'>Air Sungai</th>
      </tr>";
echo "<tr>
        <th class='sub'>pH</th>
        <th class='sub'>pH</th>
        <th class='sub'>pH</th>
        <th class='sub'>DH</th>
        <th class='sub'>Turbidity</th>
        <th class='sub'>pH</th>
        <th class='sub'>DH</th>
        <th class='sub'>Turbidity</th>
        <th class='sub'>pH</th>
      </tr>";

if (empty($rowsList)) {
    echo "<tr><td colspan='11'>Tidak ada data</td></tr>";
} else {
    foreach ($rowsList as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['tanggal']) . "</td>";
        echo "<td>" . htmlspecialchars($shiftLabel[$r['shift']] ?? $r['shift']) . "</td>";
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
        foreach ($cols as $colKey => $colVal) {
            $cls = $getCellClass($colKey, $colVal);
            echo "<td" . $cellStyle($cls) . ">" . $formatVal($colKey, $colVal) . "</td>";
        }
        echo "</tr>";
    }

    echo "<tr class='total'><td colspan='2'>Rata - Rata</td>";
    foreach ($valueCols as $col) {
        $val = $cnt[$col] ? ($avg[$col] / $cnt[$col]) : null;
        $txt = $val === null ? '-' : $formatVal($col, $val);
        $cls = $getCellClass($col, $val);
        echo "<td" . $cellStyle($cls) . ">" . $txt . "</td>";
    }
    echo "</tr>";

    echo "<tr class='target-row'><td colspan='2'>Target</td>";
    foreach ($valueCols as $col) {
        $map = $colToTarget[$col] ?? null;
        $lab = $map ? $targetLabel($map[0], $map[1]) : '-';
        echo "<td style=\"mso-number-format:'\\@';\">" . htmlspecialchars((string)$lab) . "</td>";
    }
    echo "</tr>";
}

echo "</table></body></html>";

sqlsrv_free_stmt($stmtPh);
sqlsrv_free_stmt($stmtDh);
sqlsrv_free_stmt($stmtTb);
sqlsrv_close($conn);
exit;
