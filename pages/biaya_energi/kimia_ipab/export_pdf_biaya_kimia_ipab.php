<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$groupOrderExpr = "CASE m.grup_laporan
    WHEN 'IPAL' THEN 1
    WHEN 'PROSES' THEN 2
    WHEN 'DAF_LAMA' THEN 3
    WHEN 'DAF3_BARU' THEN 4
    WHEN 'DAF2_BARU' THEN 5
    ELSE 99 END";

$groupLabels = [
    'IPAL' => 'IPAL',
    'PROSES' => 'PROSES',
    'DAF_LAMA' => 'DAF LAMA',
    'DAF3_BARU' => 'DAF 3 BARU',
    'DAF2_BARU' => 'DAF 2 BARU',
];

$masters = [];
$masterById = [];
$groups = [];

$masterSql = "SELECT m.id, m.kode, m.nama_item, m.grup_laporan, m.satuan_pakai
              FROM dbo.kimia_ipab_master m
              WHERE m.aktif = 1
              ORDER BY $groupOrderExpr, m.id ASC";
$masterStmt = sqlsrv_query($conn, $masterSql);
if ($masterStmt === false) { while (ob_get_level() > 0) ob_end_clean(); echo 'SQL error: ' . print_r(sqlsrv_errors(), true); exit; }
while ($m = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
    $mid = (int)($m['id'] ?? 0);
    if ($mid <= 0) continue;
    $g = (string)($m['grup_laporan'] ?? 'LAINNYA');
    $item = [
        'id' => $mid,
        'kode' => (string)($m['kode'] ?? ''),
        'nama_item' => (string)($m['nama_item'] ?? ''),
        'grup_laporan' => $g,
        'satuan_pakai' => (string)($m['satuan_pakai'] ?? 'Kg'),
    ];
    $masters[] = $item;
    $masterById[$mid] = $item;
    if (!isset($groups[$g])) $groups[$g] = [];
    $groups[$g][] = $mid;
}
sqlsrv_free_stmt($masterStmt);

$dateRows = [];
$dataMap = [];
$sumPakaiByMaster = [];
$sumBiayaByMaster = [];
$sumHargaByMaster = [];
$countHargaByMaster = [];

$dataSql = "SELECT CAST(h.tanggal AS DATE) AS tanggal, h.master_id, h.pakai_kg, h.harga_rp, h.biaya_rp
            FROM dbo.kimia_ipab_harian h
            INNER JOIN dbo.kimia_ipab_master m ON m.id = h.master_id
            WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(h.tanggal AS DATE) ASC, $groupOrderExpr, h.master_id ASC";
$stmt = sqlsrv_query($conn, $dataSql, [$start, $end]);
if ($stmt === false) { while (ob_get_level() > 0) ob_end_clean(); echo 'SQL error: ' . print_r(sqlsrv_errors(), true); exit; }
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
    else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

    $mid = (int)($r['master_id'] ?? 0);
    if ($mid <= 0) continue;

    $pakai = is_numeric($r['pakai_kg']) ? (float)$r['pakai_kg'] : 0.0;
    $harga = is_numeric($r['harga_rp']) ? (float)$r['harga_rp'] : 0.0;
    $biaya = is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : ($pakai * $harga);

    if (!isset($dateRows[$dateKey])) $dateRows[$dateKey] = $dateKey;
    if (!isset($dataMap[$dateKey])) $dataMap[$dateKey] = [];
    $dataMap[$dateKey][$mid] = ['pakai' => $pakai, 'harga' => $harga, 'biaya' => $biaya];

    if (!isset($sumPakaiByMaster[$mid])) $sumPakaiByMaster[$mid] = 0.0;
    if (!isset($sumBiayaByMaster[$mid])) $sumBiayaByMaster[$mid] = 0.0;
    if (!isset($sumHargaByMaster[$mid])) $sumHargaByMaster[$mid] = 0.0;
    if (!isset($countHargaByMaster[$mid])) $countHargaByMaster[$mid] = 0;

    $sumPakaiByMaster[$mid] += $pakai;
    $sumBiayaByMaster[$mid] += $biaya;
    $sumHargaByMaster[$mid] += $harga;
    $countHargaByMaster[$mid]++;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$dates = array_values($dateRows);
sort($dates);
$rowCount = count($dates);

$defaultHargaByMaster = [];
foreach ($masters as $m) {
    $mid = $m['id'];
    $defaultHargaByMaster[$mid] = ($countHargaByMaster[$mid] ?? 0) > 0
        ? ($sumHargaByMaster[$mid] / $countHargaByMaster[$mid])
        : 0.0;
}

$sumTotalBiaya = 0.0;
$totalBiayaPerDate = [];
foreach ($dates as $dateKey) {
    $t = 0.0;
    foreach ($masters as $m) {
        $mid = $m['id'];
        $t += $dataMap[$dateKey][$mid]['biaya'] ?? 0.0;
    }
    $totalBiayaPerDate[$dateKey] = $t;
    $sumTotalBiaya += $t;
}
$avgTotalBiaya = $rowCount > 0 ? ($sumTotalBiaya / $rowCount) : 0.0;

$fmt = function ($val) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, 2, '.', ',');
};
$day = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };
$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);

$pageW = 297; $usable = $pageW - 12; // margins 6+6
$dateW = 16; $totalW = 28; $colW = 16; // per column width
$usableItemsW = $usable - $dateW - $totalW;
$maxItemsPerPage = max(1, (int)floor($usableItemsW / ($colW * 3)));

$masterIds = array_map(function($m){ return $m['id']; }, $masters);
$chunks = array_chunk($masterIds, $maxItemsPerPage);

$renderHeader = function($chunkIds) use ($pdf, $groups, $groupLabels, $masterById, $colW, $dateW, $totalW) {
    $pdf->SetFont('Arial','B',8);
    $pdf->SetFillColor(217,217,217);
    $pdf->Cell($dateW,7,'TANGGAL',1,0,'C',true);

    foreach ($groups as $gKey => $itemIds) {
        $filtered = array_values(array_intersect($itemIds, $chunkIds));
        if (empty($filtered)) continue;
        $label = $groupLabels[$gKey] ?? $gKey;
        $pdf->Cell(count($filtered)*3*$colW,7,$label,1,0,'C',true);
    }
    $pdf->Cell($totalW,7,'TOTAL BIAYA',1,0,'C',true);
    $pdf->Ln();

    $pdf->SetFillColor(255,242,0);
    $pdf->SetFont('Arial','B',7);
    $pdf->Cell($dateW,6,'',1,0,'C',true);
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            if (!in_array($mid, $chunkIds, true)) continue;
            $name = $masterById[$mid]['nama_item'] ?? '-';
            $pdf->Cell(3*$colW,6,substr($name,0,24),1,0,'C',true);
        }
    }
    $pdf->Cell($totalW,6,'',1,0,'C',true);
    $pdf->Ln();

    $pdf->SetFillColor(255,242,0);
    $pdf->SetFont('Arial','B',7);
    $pdf->Cell($dateW,6,'',1,0,'C',true);
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            if (!in_array($mid, $chunkIds, true)) continue;
            $satuan = $masterById[$mid]['satuan_pakai'] ?? 'Kg';
            $pdf->Cell($colW,6,'PAKAI',1,0,'C',true);
            $pdf->Cell($colW,6,'HARGA',1,0,'C',true);
            $pdf->Cell($colW,6,'BIAYA',1,0,'C',true);
        }
    }
    $pdf->Cell($totalW,6,'',1,0,'C',true);
    $pdf->Ln();
};

$first = true;
foreach ($chunks as $chunkIds) {
    $pdf->AddPage();
    if ($first) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,7,'PEMAKAIAN OBAT UNTUK PENGOLAHAN AIR LIMBAH',0,1,'C');
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(0,6,'KIMIA AKHIR',0,1,'C');
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0,6,'BULAN : ' . $monthLabel,0,1,'C');
        $pdf->Ln(1);
        $first = false;
    } else {
        $pdf->Ln(1);
    }

    $renderHeader($chunkIds);

    $pdf->SetFont('Arial','',7);
    foreach ($dates as $dateKey) {
        $pdf->Cell($dateW,6,$day($dateKey),1,0,'C');
        foreach ($groups as $itemIds) {
            foreach ($itemIds as $mid) {
                if (!in_array($mid, $chunkIds, true)) continue;
                $cell = $dataMap[$dateKey][$mid] ?? null;
                $pakai = $cell['pakai'] ?? 0.0;
                $harga = $cell['harga'] ?? ($defaultHargaByMaster[$mid] ?? 0.0);
                $biaya = $cell['biaya'] ?? ($pakai * $harga);
                $pdf->Cell($colW,6,$fmt($pakai),1,0,'R');
                $pdf->Cell($colW,6,$fmt($harga),1,0,'R');
                $pdf->Cell($colW,6,$fmt($biaya),1,0,'R');
            }
        }
        $pdf->Cell($totalW,6,$fmt($totalBiayaPerDate[$dateKey] ?? 0.0),1,0,'R');
        $pdf->Ln();
    }

    // total
    $pdf->SetFont('Arial','B',7);
    $pdf->Cell($dateW,6,'TOTAL',1,0,'C');
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            if (!in_array($mid, $chunkIds, true)) continue;
            $pdf->Cell($colW,6,$fmt($sumPakaiByMaster[$mid] ?? 0),1,0,'R');
            $pdf->Cell($colW,6,$fmt($defaultHargaByMaster[$mid] ?? 0),1,0,'R');
            $pdf->Cell($colW,6,$fmt($sumBiayaByMaster[$mid] ?? 0),1,0,'R');
        }
    }
    $pdf->Cell($totalW,6,$fmt($sumTotalBiaya),1,0,'R');
    $pdf->Ln();

    // average
    $pdf->Cell($dateW,6,'RATA2',1,0,'C');
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            if (!in_array($mid, $chunkIds, true)) continue;
            $avgPakai = $rowCount > 0 ? (($sumPakaiByMaster[$mid] ?? 0) / $rowCount) : 0;
            $avgBiaya = $rowCount > 0 ? (($sumBiayaByMaster[$mid] ?? 0) / $rowCount) : 0;
            $pdf->Cell($colW,6,$fmt($avgPakai),1,0,'R');
            $pdf->Cell($colW,6,'Include',1,0,'C');
            $pdf->Cell($colW,6,$fmt($avgBiaya),1,0,'R');
        }
    }
    $pdf->Cell($totalW,6,$fmt($avgTotalBiaya),1,0,'R');
    $pdf->Ln();
}

while (ob_get_level() > 0) ob_end_clean();
$filename = 'Laporan_Biaya_kimia_ipab_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;

