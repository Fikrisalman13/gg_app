<?php
session_start();
ob_start();

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(h.periode AS DATE) AS tanggal,
               CAST(d.jam AS INT) AS jam,
               ISNULL(SUM(CASE WHEN d.jenis='AIR' THEN d.nilai_ton ELSE 0 END),0) AS jumlah_air_ton,
               ISNULL(SUM(CASE WHEN d.jenis='STEAM' THEN d.nilai_ton ELSE 0 END),0) AS jumlah_steam_ton
        FROM dbo.pmlonchuan_hdr h
        INNER JOIN dbo.pmlonchuan_dtl d ON d.hdr_id = h.id
        WHERE CAST(h.periode AS DATE) BETWEEN ? AND ?
        GROUP BY CAST(h.periode AS DATE), CAST(d.jam AS INT)
        ORDER BY CAST(h.periode AS DATE) ASC,
                 CASE
                   WHEN CAST(d.jam AS INT) >= 9 THEN CAST(d.jam AS INT) - 9
                   ELSE CAST(d.jam AS INT) + 15
                 END ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo 'SQL error: ' . print_r(sqlsrv_errors(), true);
    exit;
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    $r['tanggal'] = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
    $r['jam'] = isset($r['jam']) ? (int)$r['jam'] : null;
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);

$groupedRows = [];
foreach ($rows as $r) {
    $dateKey = (string)($r['tanggal'] ?? '');
    if ($dateKey === '') continue;
    if (!isset($groupedRows[$dateKey])) {
        $groupedRows[$dateKey] = [
            'tanggal' => $dateKey,
            'rows' => [],
            'tot_air' => 0,
            'tot_steam' => 0,
            'avg_air' => 0,
            'avg_steam' => 0,
        ];
    }
    $air = (float)($r['jumlah_air_ton'] ?? 0);
    $steam = (float)($r['jumlah_steam_ton'] ?? 0);
    $groupedRows[$dateKey]['rows'][] = [
        'jam' => $r['jam'] ?? null,
        'jumlah_air_ton' => $air,
        'jumlah_steam_ton' => $steam,
    ];
    $groupedRows[$dateKey]['tot_air'] += $air;
    $groupedRows[$dateKey]['tot_steam'] += $steam;
}
foreach ($groupedRows as &$g) {
    $dayCount = count($g['rows']);
    $g['avg_air'] = $dayCount > 0 ? ($g['tot_air'] / $dayCount) : 0;
    $g['avg_steam'] = $dayCount > 0 ? ($g['tot_steam'] / $dayCount) : 0;
}
unset($g);

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    $str = number_format((float)$val, $dec, '.', ',');
    return rtrim(rtrim($str, '0'), '.');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };
$fmtHour = function ($jam) {
    if ($jam === null || $jam === '' || !is_numeric($jam)) return '';
    $j = (int)$jam;
    if ($j < 0 || $j > 23) return '';
    return str_pad((string)$j, 2, '0', STR_PAD_LEFT) . ':00';
};

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();
$pdf->SetLineWidth(0.3);

$colW = [36, 24, 65, 65];
$rowH = 6;

$printHeader = function () use ($pdf, $colW, $rowH, $monthLabel) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(183, 202, 226);
    $pdf->Cell(array_sum($colW), 7, 'BERDASARKAN LAJU SESAAT BOILER LONCHUAN - ' . $monthLabel, 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(243, 226, 226);
    $pdf->Cell($colW[0], $rowH, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($colW[1], $rowH, 'Jam', 1, 0, 'C', true);
    $pdf->Cell($colW[2], $rowH, 'Jumlah Air (ton)', 1, 0, 'C', true);
    $pdf->Cell($colW[3], $rowH, 'Jumlah Steam (ton)', 1, 1, 'C', true);
};

$printHeader();

$pdf->SetFont('Arial', '', 9);
if (empty($groupedRows)) {
    $pdf->Cell(array_sum($colW), $rowH, 'Tidak ada data pada rentang tanggal ini.', 1, 1, 'C');
} else {
    foreach ($groupedRows as $day) {
        $dayRows = $day['rows'];
        $dayRowCount = count($dayRows);
        if ($dayRowCount === 0) continue;

        $neededHeight = ($rowH * $dayRowCount) + ($rowH * 2);
        $bottomLimit = $pdf->GetPageHeight() - 10;
        if ($pdf->GetY() + $neededHeight > $bottomLimit) {
            $pdf->AddPage();
            $printHeader();
        }

        $xStart = $pdf->GetX();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(219, 226, 207);
        $pdf->Cell($colW[0], $rowH * $dayRowCount, $fmtDay($day['tanggal']), 1, 0, 'C', true);

        foreach ($dayRows as $idx => $r) {
            if ($idx > 0) $pdf->SetX($xStart + $colW[0]);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(219, 226, 207);
            $pdf->Cell($colW[1], $rowH, $fmtHour($r['jam'] ?? null), 1, 0, 'C', true);

            $pdf->SetFont('Arial', '', 9);
            $pdf->SetFillColor(184, 199, 217);
            $pdf->Cell($colW[2], $rowH, $fmtNum($r['jumlah_air_ton']), 1, 0, 'C', true);
            $pdf->Cell($colW[3], $rowH, $fmtNum($r['jumlah_steam_ton']), 1, 1, 'C', true);
        }

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(236, 233, 214);
        $pdf->Cell($colW[0], $rowH, 'TOTAL/HARI', 1, 0, 'C', true);
        $pdf->Cell($colW[1], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($colW[2], $rowH, $fmtNum($day['tot_air']), 1, 0, 'C', true);
        $pdf->Cell($colW[3], $rowH, $fmtNum($day['tot_steam']), 1, 1, 'C', true);

        $pdf->Cell($colW[0], $rowH, 'RATA-RATA/HARI', 1, 0, 'C', true);
        $pdf->Cell($colW[1], $rowH, '', 1, 0, 'C', true);
        $pdf->Cell($colW[2], $rowH, $fmtNum($day['avg_air']), 1, 0, 'C', true);
        $pdf->Cell($colW[3], $rowH, $fmtNum($day['avg_steam']), 1, 1, 'C', true);
    }
}

while (ob_get_level() > 0) { ob_end_clean(); }
$filename = 'report_20tlonchuan_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

sqlsrv_close($conn);
exit;
