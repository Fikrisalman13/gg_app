<?php
// export_pdf_ipal_fixed.php — finalized FPDF exporter with automatic column chunking
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');
$shiftFilter = isset($_REQUEST['shift']) ? (string)$_REQUEST['shift'] : '';

// SQL (reused from Excel exporter)
$sql = "
SELECT
  COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) AS Tanggal,
  COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) AS Shift,
  ph.PekatBesar AS ph_PekatBesar, ph.PekatKecil AS ph_PekatKecil, ph.Anoxit AS ph_Anoxit, ph.EqualSum AS ph_EqualSum,
  ph.Daff1 AS ph_Daff1, ph.Daff2 AS ph_Daff2, ph.Daff3 AS ph_Daff3,
  ph.Aerasi1 AS ph_Aerasi1, ph.Aerasi2 AS ph_Aerasi2, ph.Aerasi3 AS ph_Aerasi3, ph.Aerasi4 AS ph_Aerasi4,
  ph.Sedimen AS ph_Sedimen, ph.SelokanPekat AS ph_SelokanPekat, ph.SelokanReaktif AS ph_SelokanReaktif, ph.Outlet AS ph_Outlet,
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
";
$params = [$start, $end];
if ($shiftFilter !== '') { $sql .= "\nAND COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) = ?"; $params[] = $shiftFilter; }
$sql .= "\nORDER BY Tanggal, Shift\n";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { if (ob_get_length()) ob_end_clean(); echo "SQL error: " . print_r(sqlsrv_errors(), true); exit; }

$data = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $r;

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

$shiftLabel = ['1'=>'Pagi','2'=>'Siang','3'=>'Malam'];

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

// Load overrides from settings file if present (use same logic as report)
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

$getCellClass = function($bak,$param,$value) use ($qualityStandards) {
    if (!isset($qualityStandards[$bak][$param]) || !is_numeric($value)) return '';
    $limits = $qualityStandards[$bak][$param]; $v = floatval($value);
    if (isset($limits['min']) && isset($limits['max'])) { if ($v < $limits['min']) return 'low'; if ($v > $limits['max']) return 'high'; return ''; }
    if (isset($limits['std'])) { if ($v < $limits['std']) return 'low'; if ($v > $limits['std']) return 'high'; return ''; }
    return '';
};

// params per bak
$bakList = [];
$bakLabels = [];
$paramsPerBak = [];
foreach ($tableBaks as $cfg) {
    $bak = $cfg['key'];
    $bakList[] = $bak;
    $bakLabels[$bak] = $cfg['label'];
    $paramsPerBak[$bak] = $cfg['params'];
}

// layout widths (A4 landscape)
$pdf = new FPDF('L','mm','A4'); $pdf->SetMargins(8,8,8); $pdf->SetAutoPageBreak(true,10);
$pageW = 297; $usable = $pageW - 16; // margins
// widen date column and each parameter column so long group names fit
$widthTanggal = 30; $widthShift = 16; $colWidth = 16;
$availableCols = floor(($usable - ($widthTanggal + $widthShift)) / $colWidth);

// per-parameter width helper (wider for Sedimen Biologi)
$getParamWidth = function($bak) use ($colWidth) {
    return ($bak === 'SedimenBiologi') ? ($colWidth + 10) : $colWidth;
};

// build groups and chunks
$groups = []; foreach ($bakList as $b) $groups[] = ['bak'=>$b,'count'=>count($paramsPerBak[$b])];
$chunks = []; $cur=[]; $curCount=0;
foreach ($groups as $g) {
    if ($curCount + $g['count'] > $availableCols) { if (!empty($cur)) { $chunks[] = $cur; $cur=[]; $curCount=0; } }
    $cur[] = $g['bak']; $curCount += $g['count'];
}
if (!empty($cur)) $chunks[] = $cur;

// render pages
$firstPage = true;
foreach ($chunks as $chunk) {
    $pdf->AddPage();
    if ($firstPage) {
        $pdf->SetFont('Arial','B',12); $pdf->Cell(0,7,'HASIL CHECK AIR LIMBAH',0,1,'C');
        $pdf->SetFont('Arial','',10); $pdf->Cell(0,6,'PERIODE '.date('d F Y',strtotime($start)).' s/d '.date('d F Y',strtotime($end)),0,1,'C'); $pdf->Ln(3);
        $firstPage = false;
    } else {
        $pdf->Ln(3);
    }

    // header group row
    // use slightly smaller font but wider columns so long group names fit
    $pdf->SetFont('Arial','B',7); $pdf->SetFillColor(31,78,120); $pdf->SetTextColor(255);
    $pdf->Cell($widthTanggal,8,'Tanggal',1,0,'C',true); $pdf->Cell($widthShift,8,'Shift',1,0,'C',true);
    foreach ($chunk as $bak) {
        $span = count($paramsPerBak[$bak]);
        $label = $bakLabels[$bak];
        $pdf->Cell($span * $getParamWidth($bak),8,$label,1,0,'C',true);
    }
    $pdf->Ln();

    // header subrow (param labels)
    $pdf->SetFont('Arial','B',7); $pdf->SetFillColor(79,129,189); $pdf->SetTextColor(255);
    $pdf->Cell($widthTanggal,6,'',1,0,'C',true); $pdf->Cell($widthShift,6,'',1,0,'C',true);
    foreach ($chunk as $bak) { foreach ($paramsPerBak[$bak] as $p) { $lab = strtoupper($p); if ($p==='mlss') $lab='MLSS'; if ($p==='ptco') $lab='PTCO'; $pdf->Cell($getParamWidth($bak),6,$lab,1,0,'C',true); } }
    $pdf->Ln();

    // data rows
    $pdf->SetFont('Arial','',8); $pdf->SetTextColor(0);
    foreach ($data as $row) {
        $tgl = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : ($row['Tanggal'] ?? '-');
        $pdf->Cell($widthTanggal,6,$tgl,1,0,'C'); $pdf->Cell($widthShift,6,$shiftLabel[$row['Shift']] ?? ($row['Shift'] ?? '-'),1,0,'C');
        foreach ($chunk as $bak) {
            foreach ($paramsPerBak[$bak] as $p) {
                $pref = $p==='ph' ? 'ph_' : ($p==='cod' ? 'cod_' : ($p==='tss' ? 'tss_' : ($p==='mlss' ? 'mlss_' : 'ptco_')));
                $key = $pref . $bak; $v = $row[$key] ?? null;
                $text = ($v === null || $v === '') ? '-' : (is_numeric($v) ? rtrim(rtrim(number_format($v,2,'.',''),'0'),'.') : (string)$v);
                $cls = $getCellClass($bak,$p,$v);
                if ($cls==='high') { $pdf->SetFillColor(255,158,166); $pdf->Cell($getParamWidth($bak),6,$text,1,0,'C',true); $pdf->SetFillColor(255,255,255); }
                elseif ($cls==='low') { $pdf->SetFillColor(255,235,59); $pdf->Cell($getParamWidth($bak),6,$text,1,0,'C',true); $pdf->SetFillColor(255,255,255); }
                else { $pdf->Cell($getParamWidth($bak),6,$text,1,0,'C'); }
            }
        }
        $pdf->Ln();
    }

    // averages
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell($widthTanggal+$widthShift,7,'Rata - Rata',1,0,'C');
    foreach ($chunk as $bak) {
        foreach ($paramsPerBak[$bak] as $p) {
            $sum=0; $cnt=0; foreach ($data as $r) { $pref = $p==='ph' ? 'ph_' : ($p==='cod' ? 'cod_' : ($p==='tss' ? 'tss_' : ($p==='mlss' ? 'mlss_' : 'ptco_'))); $val = $r[$pref.$bak] ?? null; if (is_numeric($val)) { $sum += floatval($val); $cnt++; } }
            $avg = $cnt ? rtrim(rtrim(number_format($sum/$cnt,2,'.',''),'0'),'.') : '-'; $cls = ($avg==='-') ? '' : $getCellClass($bak,$p,$avg);
            if ($cls==='high') { $pdf->SetFillColor(255,158,166); $pdf->Cell($getParamWidth($bak),7,$avg,1,0,'C',true); $pdf->SetFillColor(255,255,255); }
            elseif ($cls==='low') { $pdf->SetFillColor(255,235,59); $pdf->Cell($getParamWidth($bak),7,$avg,1,0,'C',true); $pdf->SetFillColor(255,255,255); }
            else { $pdf->Cell($getParamWidth($bak),7,$avg,1,0,'C'); }
        }
    }
    $pdf->Ln();

    // targets
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell($widthTanggal+$widthShift,7,'Target',1,0,'C');
    foreach ($chunk as $bak) {
        foreach ($paramsPerBak[$bak] as $p) {
            $lab = '-'; if (isset($qualityStandards[$bak][$p])) { $l=$qualityStandards[$bak][$p]; if (isset($l['min'])&&isset($l['max'])) $lab = $l['min'].'-'.$l['max']; elseif (isset($l['std'])) $lab = (string)$l['std']; }
            $pdf->Cell($getParamWidth($bak),7,$lab,1,0,'C');
        }
    }
    $pdf->Ln(8);
}

// Clear all output buffers to ensure headers can be sent
while (ob_get_level() > 0) ob_end_clean();

// Return PDF as string and send explicit headers to force download as PDF
$filename = 'Laporan_IPAL_'.date('Ymd_His').'.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
exit;
