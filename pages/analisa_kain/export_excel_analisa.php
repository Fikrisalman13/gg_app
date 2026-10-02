<?php
// pages/analisa_kain/export_excel_analisa.php
// Export Rekap Analisa Masalah Kain ke file Excel (.xlsx) dengan Sheet Rekap + Pie Chart + Sheet per Jenis Masalah & Line Chart

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Auth check
if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

if (!$conn) {
    die("Koneksi ke database SQL Server GG tidak tersedia.");
}

// 1. Ambil Periode Terpilih
$selectedIds = [];
if (!empty($_POST['selected_periode']) && is_array($_POST['selected_periode'])) {
    foreach ($_POST['selected_periode'] as $idVal) {
        $cleanId = (int)$idVal;
        if ($cleanId > 0) $selectedIds[] = $cleanId;
    }
} elseif (!empty($_GET['ids'])) {
    $rawIds = explode(',', $_GET['ids']);
    foreach ($rawIds as $idVal) {
        $cleanId = (int)trim($idVal);
        if ($cleanId > 0) $selectedIds[] = $cleanId;
    }
}

$periods = [];
if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "SELECT * FROM analisa_kain_periode WHERE id IN ($placeholders) ORDER BY start_date ASC, id ASC";
    $stmt = sqlsrv_query($conn, $sql, $selectedIds);
} else {
    $sql = "SELECT * FROM analisa_kain_periode ORDER BY start_date ASC, id ASC";
    $stmt = sqlsrv_query($conn, $sql);
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['s_date'] = ($row['start_date'] instanceof DateTime) ? $row['start_date']->format('Y-m-d') : (string)$row['start_date'];
        $row['e_date'] = ($row['end_date'] instanceof DateTime) ? $row['end_date']->format('Y-m-d') : (string)$row['end_date'];
        $periods[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

if (empty($periods)) {
    die("Tidak ada periode yang dipilih atau terdaftar untuk diekspor. Silakan buat periode terlebih dahulu di halaman Rekap Analisa Kain.");
}

// 2. Tarik Seluruh Data Masalah Kain (id_kategori = 1, acuan created_at)
// Tentukan batas min start_date dan max end_date
$minStart = $periods[0]['s_date'];
$maxEnd   = $periods[count($periods) - 1]['e_date'];
foreach ($periods as $p) {
    if ($p['s_date'] < $minStart) $minStart = $p['s_date'];
    if ($p['e_date'] > $maxEnd)   $maxEnd   = $p['e_date'];
}

$sqlProblems = "SELECT 
                    p.id_problem,
                    p.nocp,
                    COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) as tgl_problem,
                    COALESCE(t.id_tag, 0) as id_tag,
                    COALESCE(t.nama_tag, 'Lain-Lain') as nama_tag,
                    COALESCE(s.status, 'Open') as status
                FROM dbo.fab_m_problem p
                LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
                LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
                WHERE p.id_kategori = 1
                  AND COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) BETWEEN ? AND ?
                ORDER BY t.nama_tag ASC, COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) ASC, p.nocp ASC";

$stmtProb = sqlsrv_query($conn, $sqlProblems, [$minStart, $maxEnd]);
if (!$stmtProb) {
    die("Gagal mengambil data masalah kain: " . print_r(sqlsrv_errors(), true));
}

// 3. Kelompokkan Data: $dataByTag[nama_tag][periode_id] = array of CPs
$dataByTag = [];
$tagTotalsPerPeriod = []; // [nama_tag][periode_id] = count
$tagTotalOverall = [];    // [nama_tag] = count
$totalSolvedAll = 0;
$totalOpenAll   = 0;
$totalMasalahAll = 0;

// Inisialisasi struktur tag
while ($r = sqlsrv_fetch_array($stmtProb, SQLSRV_FETCH_ASSOC)) {
    $tglStr = ($r['tgl_problem'] instanceof DateTime) ? $r['tgl_problem']->format('Y-m-d') : (string)$r['tgl_problem'];
    $tag = trim($r['nama_tag']);
    if (empty($tag)) $tag = 'Lain-Lain';
    $status = trim($r['status']);
    if ($status !== 'Solved') $status = 'Open';

    // Cari periode yang cocok
    foreach ($periods as $p) {
        if ($tglStr >= $p['s_date'] && $tglStr <= $p['e_date']) {
            $pid = $p['id'];

            if (!isset($dataByTag[$tag])) {
                $dataByTag[$tag] = [];
                $tagTotalsPerPeriod[$tag] = [];
                $tagTotalOverall[$tag] = 0;
            }
            if (!isset($dataByTag[$tag][$pid])) {
                $dataByTag[$tag][$pid] = [
                    'nama_periode' => $p['nama_periode'],
                    'cps' => []
                ];
                $tagTotalsPerPeriod[$tag][$pid] = 0;
            }

            $dataByTag[$tag][$pid]['cps'][] = [
                'id_problem' => $r['id_problem'],
                'nocp'       => $r['nocp'],
                'status'     => $status,
                'tgl'        => $tglStr
            ];

            $tagTotalsPerPeriod[$tag][$pid]++;
            $tagTotalOverall[$tag]++;
            $totalMasalahAll++;
            if ($status === 'Solved') $totalSolvedAll++;
            else $totalOpenAll++;

            break;
        }
    }
}
sqlsrv_free_stmt($stmtProb);

// Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();

// Style Presets
$navyFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '3366CC'] // Vibrant Blue (sesuai contoh screenshot)
    ]
];
$iceBlueFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9E1F2'] // Soft Ice Blue
    ]
];
$subtleGrayFill = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F2F2F2']
    ]
];
$allBorders = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$thickBottomBorder = [
    'borders' => [
        'bottom' => [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '3366CC']
        ]
    ]
];

// Helper sanitize sheet title
function sanitizeSheetTitle($title, &$used) {
    $clean = preg_replace('/[\\\\\/\\?\\*\\:\\[\\]]/', '-', $title);
    $clean = trim($clean);
    if (mb_strlen($clean) > 31) {
        $clean = mb_substr($clean, 0, 31);
    }
    if (empty($clean)) $clean = 'Sheet';

    $final = $clean;
    $i = 1;
    while (isset($used[strtolower($final)])) {
        $suffix = " ($i)";
        $maxLen = 31 - mb_strlen($suffix);
        $final = mb_substr($clean, 0, $maxLen) . $suffix;
        $i++;
    }
    $used[strtolower($final)] = true;
    return $final;
}
$usedSheetTitles = [];

// ============================================================================
// SHEET 1: REKAP MASALAH KAIN (PERSIS GAMBAR 2)
// ============================================================================
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1Title = 'Rekap Masalah Kain';
$sheet1->setTitle($sheet1Title);
$usedSheetTitles[strtolower($sheet1Title)] = true;
$sheet1->setShowGridlines(true);

// 1. Header Baris 1: MASALAH KAIN (A1:E1) & Update DD-MM-YYYY (G1:I1)
$sheet1->mergeCells('A1:E1');
$sheet1->setCellValue('A1', 'MASALAH KAIN');
$sheet1->getStyle('A1:E1')->applyFromArray($allBorders);
$sheet1->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3366CC');
$sheet1->getStyle('A1:E1')->getFont()->setBold(true)->setSize(14)->setName('Calibri')->getColor()->setRGB('FFFFFF');
$sheet1->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet1->getRowDimension(1)->setRowHeight(26);

// Tanggal Update di G1:I1
$sheet1->mergeCells('G1:I1');
$sheet1->setCellValue('G1', 'Update ' . date('d-m-Y'));
$sheet1->getStyle('G1:I1')->applyFromArray($allBorders);
$sheet1->getStyle('G1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3366CC');
$sheet1->getStyle('G1:I1')->getFont()->setBold(true)->setSize(11)->setName('Calibri')->getColor()->setRGB('FFFFFF');
$sheet1->getStyle('G1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// 2. Header Baris 2: Kolom Tabel Utama (A2:E2) & Kolom Ringkasan (G2:I2)
$sheet1->setCellValue('A2', 'Jenis Masalah');
$sheet1->setCellValue('B2', 'Periode');
$sheet1->setCellValue('C2', 'No CP');
$sheet1->setCellValue('D2', 'Status');
$sheet1->setCellValue('E2', 'Total');

$sheet1->getStyle('A2:E2')->applyFromArray($allBorders);
$sheet1->getStyle('A2:E2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
$sheet1->getStyle('A2:E2')->getFont()->setBold(true)->setSize(11)->setName('Calibri')->getColor()->setRGB('000000');
$sheet1->getStyle('A2:E2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet1->getRowDimension(2)->setRowHeight(22);

// Header Summary di G2:I2
$sheet1->setCellValue('G2', 'Total Masalah');
$sheet1->setCellValue('H2', 'Solved');
$sheet1->setCellValue('I2', 'Open');
$sheet1->getStyle('G2:I2')->applyFromArray($allBorders);
$sheet1->getStyle('G2:I2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
$sheet1->getStyle('G2:I2')->getFont()->setBold(true)->setSize(11)->setName('Calibri')->getColor()->setRGB('000000');
$sheet1->getStyle('G2:I2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// 3. Isi Data Tabel Utama (Baris 3+)
$currentRow = 3;

if (!empty($dataByTag)) {
    foreach ($dataByTag as $tagName => $tagPeriods) {
        $tagStartRow = $currentRow;

        foreach ($tagPeriods as $pid => $pInfo) {
            $periodStartRow = $currentRow;

            foreach ($pInfo['cps'] as $cp) {
                $sheet1->getRowDimension($currentRow)->setRowHeight(20);

                $sheet1->setCellValue("C$currentRow", $cp['nocp']);
                $sheet1->setCellValue("D$currentRow", $cp['status']);
                $currentRow++;
            }

            $periodEndRow = $currentRow - 1;

            // Merge Kolom B (Periode)
            if ($periodEndRow >= $periodStartRow) {
                if ($periodEndRow > $periodStartRow) {
                    $sheet1->mergeCells("B$periodStartRow:B$periodEndRow");
                }
                $sheet1->setCellValue("B$periodStartRow", $pInfo['nama_periode']);

                // Merge Kolom E (Total per periode)
                if ($periodEndRow > $periodStartRow) {
                    $sheet1->mergeCells("E$periodStartRow:E$periodEndRow");
                }
                $countInPeriod = count($pInfo['cps']);
                $sheet1->setCellValue("E$periodStartRow", $countInPeriod);
            }
        }

        $tagEndRow = $currentRow - 1;

        // Merge Kolom A (Jenis Masalah)
        if ($tagEndRow >= $tagStartRow) {
            if ($tagEndRow > $tagStartRow) {
                $sheet1->mergeCells("A$tagStartRow:A$tagEndRow");
            }
            $sheet1->setCellValue("A$tagStartRow", $tagName);
        }
    }
} else {
    // Kosong
    $sheet1->setCellValue('A3', 'Tidak ada data masalah kain');
    $sheet1->mergeCells('A3:E3');
    $currentRow = 4;
}

$endRow = $currentRow - 1;

// Styling Data Table
if ($endRow >= 3) {
    $sheet1->getStyle("A3:E$endRow")->applyFromArray($allBorders);
    $sheet1->getStyle("A3:E$endRow")->getFont()->setSize(10)->setName('Calibri');
    $sheet1->getStyle("A3:A$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet1->getStyle("B3:B$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("C3:C$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet1->getStyle("D3:D$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("E3:E$endRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("E3:E$endRow")->getNumberFormat()->setFormatCode('#,##0');
}

// 4. Data Angka Ringkasan di G3:I3
$sheet1->setCellValue('G3', $totalMasalahAll);
$sheet1->setCellValue('H3', $totalSolvedAll);
$sheet1->setCellValue('I3', $totalOpenAll);

$sheet1->getStyle('G3:I3')->applyFromArray($allBorders);
$sheet1->getStyle('G3:I3')->getFont()->setBold(true)->setSize(11);
$sheet1->getStyle('G3:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet1->getStyle('G3:I3')->getNumberFormat()->setFormatCode('#,##0');

// Set Column Dimensions
$sheet1->getColumnDimension('A')->setWidth(26);
$sheet1->getColumnDimension('B')->setWidth(30);
$sheet1->getColumnDimension('C')->setWidth(22);
$sheet1->getColumnDimension('D')->setWidth(14);
$sheet1->getColumnDimension('E')->setWidth(12);
$sheet1->getColumnDimension('F')->setWidth(4); // Spacing column
$sheet1->getColumnDimension('G')->setWidth(16);
$sheet1->getColumnDimension('H')->setWidth(14);
$sheet1->getColumnDimension('I')->setWidth(14);

// 5. Pie Chart di Kolom G5:K18 (Sesuai Gambar 2)
if ($totalMasalahAll > 0) {
    $pieCategories = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheet1Title'!\$H\$2:\$I\$2", null, 2)
    ];
    $pieValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheet1Title'!\$H\$3:\$I\$3", null, 2)
    ];

    $pieSeries = new DataSeries(
        DataSeries::TYPE_PIECHART,
        null,
        range(0, count($pieValues) - 1),
        $pieCategories, // Labels
        $pieCategories, // Categories
        $pieValues      // Values
    );

    $plotAreaPie = new PlotArea(null, [$pieSeries]);
    $legendPie = new Legend(Legend::POSITION_TOP, null, false);
    $titlePie = new Title('MASALAH KAIN');

    $pieChart = new Chart(
        'chart_masalah_kain_pie',
        $titlePie,
        $legendPie,
        $plotAreaPie,
        true,
        DataSeries::EMPTY_AS_GAP
    );

    // Border halus chart
    $pieChart->getBorderLines()->setLineColorProperties('D9D9D9');
    $pieChart->getBorderLines()->setLineStyleProperties(0.75);

    $pieChart->setTopLeftPosition('G5');
    $pieChart->setBottomRightPosition('K19');
    $sheet1->addChart($pieChart);
}

// ============================================================================
// SHEET 2..N: SHEET INDIVIDUAL PER JENIS MASALAH (TAG) & LINE CHART
// ============================================================================
// Sesuai kesepakatan: Hanya buat sheet untuk Jenis Masalah yang memiliki data (> 0)
foreach ($dataByTag as $tagName => $tagPeriods) {
    if (empty($tagTotalOverall[$tagName])) continue;

    $cleanTitle = sanitizeSheetTitle($tagName, $usedSheetTitles);
    $sheetTag = $spreadsheet->createSheet();
    $sheetTag->setTitle($cleanTitle);
    $sheetTag->setShowGridlines(true);

    // Title di A1
    $sheetTag->setCellValue('A1', $tagName);
    $sheetTag->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Calibri')->getColor()->setRGB('000000');
    $sheetTag->getRowDimension(1)->setRowHeight(24);
    $sheetTag->getRowDimension(2)->setRowHeight(12);

    // Header Tabel di Row 3: No | Periode | Total
    $sheetTag->setCellValue('A3', 'No');
    $sheetTag->setCellValue('B3', 'Periode');
    $sheetTag->setCellValue('C3', 'Total');

    $sheetTag->getStyle('A3:C3')->applyFromArray($allBorders);
    $sheetTag->getStyle('A3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3366CC');
    $sheetTag->getStyle('A3:C3')->getFont()->setBold(true)->setSize(11)->setName('Calibri')->getColor()->setRGB('FFFFFF');
    $sheetTag->getStyle('A3:C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetTag->getRowDimension(3)->setRowHeight(22);

    // Baris Data di Row 4+ (Tampilkan hanya periode yang memiliki masalah > 0 persis Gambar 5)
    $r = 4;
    $noTag = 1;
    foreach ($periods as $p) {
        $pid = $p['id'];
        $count = $tagTotalsPerPeriod[$tagName][$pid] ?? 0;
        if ($count <= 0) continue; // Lewati periode dengan total 0

        $sheetTag->getRowDimension($r)->setRowHeight(20);
        $sheetTag->setCellValue("A$r", $noTag++);
        $sheetTag->setCellValue("B$r", $p['nama_periode']);
        $sheetTag->setCellValue("C$r", $count);
        $r++;
    }
    $lastR = $r - 1;
    $numRows = $noTag - 1;

    // Formatting Data Table
    $sheetTag->getStyle("A4:C$lastR")->applyFromArray($allBorders);
    $sheetTag->getStyle("A4:C$lastR")->getFont()->setSize(10)->setName('Calibri');
    $sheetTag->getStyle("A4:A$lastR")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetTag->getStyle("B4:B$lastR")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetTag->getStyle("C4:C$lastR")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetTag->getStyle("C4:C$lastR")->getNumberFormat()->setFormatCode('#,##0');

    $sheetTag->getColumnDimension('A')->setWidth(8);
    $sheetTag->getColumnDimension('B')->setWidth(32);
    $sheetTag->getColumnDimension('C')->setWidth(12);
    $sheetTag->getColumnDimension('D')->setWidth(4); // Spacing column

    // Native Line Chart di Kolom E3:K17 (Persis Gambar 3, 4, 5)
    $escapedSheet = "'" . str_replace("'", "''", $cleanTitle) . "'";
    $dataSeriesLabels = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "$escapedSheet!\$C\$3", null, 1)
    ];
    $xAxisTickValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "$escapedSheet!\$B\$4:\$B\$$lastR", null, $numRows)
    ];
    $dataSeriesValues = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "$escapedSheet!\$C\$4:\$C\$$lastR", null, $numRows)
    ];

    // Marker & Warna Garis
    $dataSeriesValues[0]->setFillColor('4472C4');

    $series = new DataSeries(
        DataSeries::TYPE_LINECHART,
        DataSeries::GROUPING_STANDARD,
        range(0, count($dataSeriesValues) - 1),
        $dataSeriesLabels,
        $xAxisTickValues,
        $dataSeriesValues
    );

    $plotArea = new PlotArea(null, [$series]);
    $chartTitle = new Title($tagName);

    $lineChart = new Chart(
        'chart_' . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($cleanTitle)),
        $chartTitle,
        null, // Tanpa legend untuk single series
        $plotArea,
        true,
        DataSeries::EMPTY_AS_GAP
    );

    // Garis Kisi Horizontal (Major Gridlines pada Sumbu Y)
    $catGridlines = new GridLines();
    $catGridlines->setLineColorProperties('D9D9D9');
    $catGridlines->setLineStyleProperties(0.75);
    $lineChart->getChartAxisY()->setMajorGridlines($catGridlines);

    // Garis Bingkai Halus pada Grafik
    $lineChart->getBorderLines()->setLineColorProperties('D9D9D9');
    $lineChart->getBorderLines()->setLineStyleProperties(0.75);

    // Posisikan chart di E3:K17
    $lineChart->setTopLeftPosition('E3');
    $lineChart->setBottomRightPosition('K18');
    $sheetTag->addChart($lineChart);
}

// ============================================================================
// SHEET TERAKHIR: AKAR MASALAH DAN PERBAIKAN (KHUSUS STATUS SOLVED PADA PERIODE TERBARU)
// ============================================================================
$latestPeriod = end($periods);

$sqlAkar = "SELECT 
                p.id_problem,
                p.nocp,
                COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) as tgl_problem,
                COALESCE(t.id_tag, 0) as id_tag,
                COALESCE(t.nama_tag, 'Lain-Lain') as nama_tag,
                s.analisa_akar_masalah,
                s.tindakan_perbaikan,
                s.tindakan_pencegahan,
                s.status
            FROM dbo.fab_m_problem p
            LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
            INNER JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
            WHERE p.id_kategori = 1
              AND COALESCE(p.tgl_lkp_qc, CAST(p.created_at AS DATE)) BETWEEN ? AND ?
              AND s.status = 'Solved'
              AND s.analisa_akar_masalah IS NOT NULL
              AND LTRIM(RTRIM(s.analisa_akar_masalah)) <> ''
            ORDER BY t.nama_tag ASC, p.nocp ASC";

$stmtAkar = sqlsrv_query($conn, $sqlAkar, [$latestPeriod['s_date'], $latestPeriod['e_date']]);
$akarMasalahByTag = [];

if ($stmtAkar) {
    while ($row = sqlsrv_fetch_array($stmtAkar, SQLSRV_FETCH_ASSOC)) {
        $tag = trim($row['nama_tag'] ?? '');
        if (empty($tag)) $tag = 'Lain-Lain';

        $akarMasalahByTag[$tag][] = [
            'nocp'       => trim($row['nocp'] ?? ''),
            'akar'       => trim($row['analisa_akar_masalah'] ?? ''),
            'perbaikan'  => trim($row['tindakan_perbaikan'] ?? ''),
            'pencegahan' => trim($row['tindakan_pencegahan'] ?? '')
        ];
    }
    sqlsrv_free_stmt($stmtAkar);
}

// Buat sheet baru bernama 'Akar Masalah dan Perbaikan'
$sheetAkarTitle = sanitizeSheetTitle('Akar Masalah dan Perbaikan', $usedSheetTitles);
$sheetAkar = $spreadsheet->createSheet();
$sheetAkar->setTitle($sheetAkarTitle);
$sheetAkar->setShowGridlines(true);

// 1. Judul Periode di Baris 1: "Periode: Nama Periode"
$sheetAkar->setCellValue('A1', 'Periode: ' . $latestPeriod['nama_periode']);
$sheetAkar->getStyle('A1')->getFont()->setBold(true)->setSize(12)->setName('Calibri');
$sheetAkar->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheetAkar->getRowDimension(1)->setRowHeight(24);

// Baris 2: Spasi kosong
$sheetAkar->getRowDimension(2)->setRowHeight(12);

// 2. Header Tabel di Baris 3: Merah Tua / Maroon Solid (#C00000)
$sheetAkar->setCellValue('A3', 'Deskripsi Masalah');
$sheetAkar->setCellValue('B3', 'No CP');
$sheetAkar->setCellValue('C3', 'Akar Masalah');
$sheetAkar->setCellValue('D3', 'Tindakan Perbaikan');
$sheetAkar->setCellValue('E3', 'Tindakan Pencegahan');

$sheetAkar->getStyle('A3:E3')->applyFromArray($allBorders);
$sheetAkar->getStyle('A3:E3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C00000');
$sheetAkar->getStyle('A3:E3')->getFont()->setBold(true)->setSize(11)->setName('Calibri')->getColor()->setRGB('FFFFFF');
$sheetAkar->getStyle('A3:E3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheetAkar->getRowDimension(3)->setRowHeight(26);

// 3. Baris Data (Row 4+)
$currRowAkar = 4;

if (!empty($akarMasalahByTag)) {
    foreach ($akarMasalahByTag as $tagName => $rows) {
        $tagStart = $currRowAkar;

        foreach ($rows as $item) {
            $sheetAkar->setCellValue("B$currRowAkar", $item['nocp']);
            $sheetAkar->setCellValue("C$currRowAkar", $item['akar']);
            $sheetAkar->setCellValue("D$currRowAkar", $item['perbaikan']);
            $sheetAkar->setCellValue("E$currRowAkar", $item['pencegahan']);
            $currRowAkar++;
        }

        $tagEnd = $currRowAkar - 1;

        // Merge Kolom A (Deskripsi Masalah) per tag
        if ($tagEnd >= $tagStart) {
            if ($tagEnd > $tagStart) {
                $sheetAkar->mergeCells("A$tagStart:A$tagEnd");
            }
            $sheetAkar->setCellValue("A$tagStart", $tagName);
        }

        // Merge baris berurutan untuk Kolom C, D, E jika isinya sama persis
        $numRows = count($rows);
        $fieldCols = [
            'akar'       => 'C',
            'perbaikan'  => 'D',
            'pencegahan' => 'E'
        ];

        foreach ($fieldCols as $field => $colLetter) {
            $runStart = 0;
            while ($runStart < $numRows) {
                $runEnd = $runStart;
                $val = trim((string)($rows[$runStart][$field] ?? ''));

                while ($runEnd + 1 < $numRows && trim((string)($rows[$runEnd + 1][$field] ?? '')) === $val && $val !== '') {
                    $runEnd++;
                }

                if ($runEnd > $runStart) {
                    $startExcelRow = $tagStart + $runStart;
                    $endExcelRow   = $tagStart + $runEnd;
                    $sheetAkar->mergeCells("{$colLetter}{$startExcelRow}:{$colLetter}{$endExcelRow}");
                }

                $runStart = $runEnd + 1;
            }
        }
    }
} else {
    $sheetAkar->setCellValue('A4', 'Tidak ada data akar masalah dan perbaikan pada periode ' . $latestPeriod['nama_periode'] . '.');
    $sheetAkar->mergeCells('A4:E4');
    $currRowAkar = 5;
}

$endRowAkar = $currRowAkar - 1;
if ($endRowAkar >= 4) {
    $sheetAkar->getStyle("A4:E$endRowAkar")->applyFromArray($allBorders);
    $sheetAkar->getStyle("A4:E$endRowAkar")->getFont()->setSize(10)->setName('Calibri');
    $sheetAkar->getStyle("A4:A$endRowAkar")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetAkar->getStyle("B4:B$endRowAkar")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheetAkar->getStyle("C4:E$endRowAkar")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
}

// Lebar Kolom
$sheetAkar->getColumnDimension('A')->setWidth(20);
$sheetAkar->getColumnDimension('B')->setWidth(22);
$sheetAkar->getColumnDimension('C')->setWidth(50);
$sheetAkar->getColumnDimension('D')->setWidth(50);
$sheetAkar->getColumnDimension('E')->setWidth(50);

// Set active sheet kembali ke Sheet 1
$spreadsheet->setActiveSheetIndex(0);

// Download File Excel (.xlsx)
$filename = "Rekap_Analisa_Kain_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');
exit;
