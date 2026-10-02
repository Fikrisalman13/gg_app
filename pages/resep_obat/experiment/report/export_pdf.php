<?php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/report_export_data.php';

if (!isset($_SESSION['UserName']) || !report_export_can_view($conn)) {
  http_response_code(403);
  exit('Forbidden');
}

$rows = report_export_rows($conn, $conn3, $_GET);
usort($rows, function ($a, $b) {
  foreach (['label', 'kode_lab_warna', 'status', 'no_cp', 'experiment_seq', 'tgl_match', 'tgl_planning', 'mesin_paddry', 'qty', 'aktual_qty', 'posisi_hari_ini', 'acc_warna_r', 'keputusan', 'tindakan', 'qc_catatan'] as $key) {
    $cmp = strcmp((string) $a[$key], (string) $b[$key]);
    if ($cmp !== 0) return $cmp;
  }
  return 0;
});

$headers = ['No', 'Tgl Match', 'Label', 'KodeLab / Warna', 'Status', 'No CP', 'Exp', 'Tgl Planning', 'Tgl Celup Padd', 'Mesin Paddry', 'Planning Qty', 'Aktual Qty PMT', 'Posisi Hari Ini', 'ACC Warna R', 'Keputusan', 'Tindakan QC', 'Catatan QC'];
$fields = ['tgl_match', 'label', 'kode_lab_warna', 'status', 'no_cp', 'experiment_seq', 'tgl_planning', 'tgl_celup_padd', 'mesin_paddry', 'qty', 'aktual_qty', 'posisi_hari_ini', 'acc_warna_r', 'keputusan', 'tindakan', 'qc_catatan'];
$widths = [7, 14, 11, 29, 18, 21, 12, 15, 15, 17, 11, 11, 18, 18, 16, 18, 24];

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GG App');
$pdf->SetAuthor('GG App');
$pdf->SetTitle('Laporan Hasil Eksperimen');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(6, 8, 6);
$pdf->SetAutoPageBreak(false);
$pdf->SetFont('helvetica', '', 7.0);
$pdf->SetLineWidth(0.06);
$pdf->SetDrawColor(90, 90, 90);

$rowH = 10.0;
$headerH = 8.5;
$rowsPerPage = 18;
$pageRows = array_chunk($rows, $rowsPerPage);
if (!$pageRows) $pageRows = [[]];

$drawHeader = function () use ($pdf, $headers, $widths, $headerH) {
  $pdf->SetFont('helvetica', 'B', 11);
  $pdf->Cell(0, 6, 'Laporan Hasil Eksperimen', 0, 1, 'C');
  $pdf->Ln(1);
  $pdf->SetFont('helvetica', 'B', 6.3);
  $pdf->SetFillColor(238, 238, 238);
  foreach ($headers as $i => $h) {
    $pdf->MultiCell($widths[$i], $headerH, $h, 1, 'C', true, 0, null, null, true, 0, false, true, $headerH, 'M');
  }
  $pdf->Ln();
  $pdf->SetFont('helvetica', '', 7.0);
};

$calcLabelSpans = function (array $chunk): array {
  $spans = [];
  foreach ($chunk as $idx => $row) {
    if ($idx > 0 && (string) $row['label'] === (string) $chunk[$idx - 1]['label']) continue;
    $span = 1;
    for ($j = $idx + 1; $j < count($chunk) && (string) $chunk[$j]['label'] === (string) $row['label']; $j++) $span++;
    $spans[$idx] = $span;
  }
  return $spans;
};

$globalNo = 1;
foreach ($pageRows as $chunk) {
  $pdf->AddPage();
  $drawHeader();

  if (!$chunk) {
    $pdf->MultiCell(array_sum($widths), $rowH, 'Tidak ada data', 1, 'C', false, 1, null, null, true, 0, false, true, $rowH, 'M');
    continue;
  }

  $labelSpans = $calcLabelSpans($chunk);
  $pageStartNo = $globalNo;
  $yStart = $pdf->GetY();

  foreach ($chunk as $rowIndex => $row) {
    $x = 6;
    $y = $yStart + ($rowIndex * $rowH);

    $pdf->SetXY($x, $y);
    $pdf->MultiCell($widths[0], $rowH, (string) ($pageStartNo + $rowIndex), 1, 'C', false, 0, null, null, true, 0, false, true, $rowH, 'M');

    foreach ($fields as $fieldIndex => $field) {
      $colIndex = $fieldIndex + 1;
      $x += $widths[$colIndex - 1];

      $span = 1;
      if ($field === 'label') {
        if (!isset($labelSpans[$rowIndex])) continue;
        $span = $labelSpans[$rowIndex];
      }
      $cellH = $rowH * $span;
      $align = in_array($field, ['qty', 'aktual_qty'], true) ? 'R' : 'C';
      $value = $field === 'experiment_seq' ? ('#' . (string) $row[$field] . "\n" . (string) ($row['created_by'] ?? '-')) : (string) $row[$field];
      $pdf->SetXY($x, $y);
      $pdf->MultiCell($widths[$colIndex], $cellH, $value, 1, $align, false, 0, null, null, true, 0, false, true, $cellH, 'M');
    }
  }

  $globalNo += count($chunk);
}

$pdf->Output('laporan_hasil_eksperimen_' . date('Ymd_His') . '.pdf', 'D');
exit;
