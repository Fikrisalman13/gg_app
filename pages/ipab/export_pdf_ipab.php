<?php
// export_pdf_ipab.php - Export PDF IPAB (pH, DH, Turbidity)
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');
$shiftFilter = isset($_REQUEST['shift']) ? (string)$_REQUEST['shift'] : '';

$shiftLabel = ['1' => 'Pagi', '2' => 'Siang', '3' => 'Malam'];
$shiftFilterLabel = ($shiftFilter !== '' && isset($shiftLabel[$shiftFilter])) ? $shiftLabel[$shiftFilter] : '';

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
if ($stmtPh === false) { if (ob_get_length()) ob_end_clean(); echo "SQL error pH: " . print_r(sqlsrv_errors(), true); exit; }
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
if ($stmtDh === false) { if (ob_get_length()) ob_end_clean(); echo "SQL error DH: " . print_r(sqlsrv_errors(), true); exit; }
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
if ($stmtTb === false) { if (ob_get_length()) ob_end_clean(); echo "SQL error Turbidity: " . print_r(sqlsrv_errors(), true); exit; }
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
foreach ($valueCols as $col) { $avg[$col] = 0; $cnt[$col] = 0; }
foreach ($rowsList as $r) {
    foreach ($valueCols as $col) {
        if (is_numeric($r[$col])) { $avg[$col] += $r[$col]; $cnt[$col]++; }
    }
}

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

$formatVal = function($col, $val) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return (string)$val;
    if (strpos($col, 'dh_') === 0 || strpos($col, 'tb_') === 0) return (string)intval($val);
    $num = number_format((float)$val, 2, '.', '');
    return rtrim(rtrim($num, '0'), '.');
};

// Build PDF (landscape)
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'HASIL DATA KUALITAS AIR IPAB', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'PERIODE ' . date('d F Y', strtotime($start)) . ' s/d ' . date('d F Y', strtotime($end)), 0, 1, 'C');
$pdf->Ln(2);

$wTanggal = 28;
$wShift = 16;
// custom widths for 9 value columns to avoid header overlap
$colWidths = [20, 14, 14, 14, 16, 14, 14, 16, 20];
$pageWidth = $pdf->GetPageWidth();
$tableWidth = $wTanggal + $wShift + array_sum($colWidths);
$leftX = ($pageWidth - $tableWidth) / 2;

// Header group row
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(47, 52, 59);
$pdf->SetTextColor(255);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 8, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell($wShift, 8, 'Shift', 1, 0, 'C', true);
$pdf->Cell($colWidths[0], 8, 'Bak Clarivier', 1, 0, 'C', true);
$pdf->Cell($colWidths[1], 8, 'Bak 2', 1, 0, 'C', true);
$pdf->Cell($colWidths[2] + $colWidths[3] + $colWidths[4], 8, 'Bak 3', 1, 0, 'C', true);
$pdf->Cell($colWidths[5] + $colWidths[6] + $colWidths[7], 8, 'Bak 4', 1, 0, 'C', true);
$pdf->Cell($colWidths[8], 8, 'Air Sungai', 1, 0, 'C', true);
$pdf->Ln();

// Header sub row
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(74, 79, 87);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal, 6, '', 1, 0, 'C', true);
$pdf->Cell($wShift, 6, '', 1, 0, 'C', true);
$subLabels = ['pH','pH','pH','DH','Turbidity','pH','DH','Turbidity','pH'];
foreach ($subLabels as $i => $lab) {
    $pdf->Cell($colWidths[$i], 6, $lab, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0);
foreach ($rowsList as $r) {
    $pdf->SetX($leftX);
    $pdf->Cell($wTanggal, 6, $r['tanggal'], 1, 0, 'C');
    $pdf->Cell($wShift, 6, $shiftLabel[$r['shift']] ?? $r['shift'], 1, 0, 'C');
    $colKeys = [
        'ph_clarivier','ph_bak2','ph_bak3','dh_bak3','tb_bak3',
        'ph_bak4','dh_bak4','tb_bak4','ph_airsungai'
    ];
    foreach ($colKeys as $i => $colKey) {
        $raw = $r[$colKey] ?? null;
        $text = $formatVal($colKey, $raw);
        $cls = $getCellClass($colKey, $raw);
        if ($cls === 'high') {
            $pdf->SetFillColor(255, 158, 166);
            $pdf->Cell($colWidths[$i], 6, $text, 1, 0, 'C', true);
        } elseif ($cls === 'low') {
            $pdf->SetFillColor(255, 235, 59);
            $pdf->Cell($colWidths[$i], 6, $text, 1, 0, 'C', true);
        } else {
            $pdf->Cell($colWidths[$i], 6, $text, 1, 0, 'C');
        }
    }
    $pdf->Ln();
}

// Average row
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(189, 189, 189);
$pdf->SetX($leftX);
$pdf->Cell($wTanggal + $wShift, 7, 'Rata - Rata', 1, 0, 'C', true);
foreach ($valueCols as $col) {
    $val = $cnt[$col] ? ($avg[$col] / $cnt[$col]) : null;
    $txt = $val === null ? '-' : $formatVal($col, $val);
    $width = $colWidths[array_search($col, $valueCols, true)];
    $cls = $getCellClass($col, $val);
    if ($cls === 'high') {
        $pdf->SetFillColor(255, 158, 166);
        $pdf->Cell($width, 7, $txt, 1, 0, 'C', true);
    } elseif ($cls === 'low') {
        $pdf->SetFillColor(255, 235, 59);
        $pdf->Cell($width, 7, $txt, 1, 0, 'C', true);
    } else {
        $pdf->SetFillColor(189, 189, 189);
        $pdf->Cell($width, 7, $txt, 1, 0, 'C', true);
    }
}
$pdf->Ln();

// Target row
$pdf->SetX($leftX);
$pdf->Cell($wTanggal + $wShift, 7, 'Target', 1, 0, 'C', true);
foreach ($valueCols as $col) {
    $width = $colWidths[array_search($col, $valueCols, true)];
    $map = $colToTarget[$col] ?? null;
    $lab = $map ? $targetLabel($map[0], $map[1]) : '-';
    $pdf->Cell($width, 7, $lab, 1, 0, 'C', true);
}
$pdf->Ln();

// Clear output buffers
while (ob_get_level() > 0) { ob_end_clean(); }

$filename = 'Laporan_IPAB_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_free_stmt($stmtPh);
sqlsrv_free_stmt($stmtDh);
sqlsrv_free_stmt($stmtTb);
sqlsrv_close($conn);
exit;
