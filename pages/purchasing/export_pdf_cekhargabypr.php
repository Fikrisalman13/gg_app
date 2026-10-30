<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi3.php';
require_once __DIR__ . '/cekhargabypr_lib.php';

if (!isset($_SESSION['UserName'])) { http_response_code(403); exit('Silakan login terlebih dahulu!'); }
if (!$conn3) { http_response_code(500); exit('Koneksi ke database gagal.'); }

$prNumbers = parseSelectedPrNumbers((string) ($_POST['prnumbers'] ?? ''));
$showAllHistory = (string) ($_POST['history_mode'] ?? '0') === '1';
$hideDescription = (string) ($_POST['hide_description'] ?? '0') === '1';
if (!$prNumbers) { http_response_code(400); exit('Masukkan minimal 1 nomor PR terlebih dahulu.'); }
$groups = fetchPrPriceGroups($conn3, $prNumbers, $showAllHistory);
$tcpdfPath = __DIR__ . '/../Logistik/transfer_packing/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) { http_response_code(500); exit('Library PDF tidak ditemukan.'); }
require_once $tcpdfPath;

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('CEK HARGA BY PURCHASE REQUISITION');
$pdf->SetTitle('CEK HARGA BY PURCHASE REQUISITION');
$pdf->setPrintHeader(false);
$pdf->SetMargins(8, 12, 8);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(0, 8, 'CEK HARGA BY PURCHASE REQUISITION', 0, 1, 'C');
$titleLineY = $pdf->GetY();
$pdf->Line(8, $titleLineY, 289, $titleLineY);
$pdf->Ln(2);
$pdf->SetFont('helvetica', '', 7);

$headers = ['No', 'Kode Product', 'Product Name', 'Struct Name', 'No PO', 'PO Date', 'Vendor Name', 'Price PO (Rupiah)', 'Satuan', 'Deskripsi', 'Stock Saat Ini', 'Nama Gudang'];
$widths = [7, 24, 35, 23, 22, 18, 34, 27, 12, 39, 20, 14];
if ($hideDescription) {
    array_splice($headers, 9, 1);
    array_splice($widths, 9, 1);
}
$tableHeader = '<table width="100%" border="1" cellpadding="3"><thead><tr style="font-weight:bold;background-color:#e9ecef">';
foreach ($headers as $i => $header) $tableHeader .= '<th width="' . round($widths[$i] / array_sum($widths) * 100, 2) . '%">' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
$tableHeader .= '</tr></thead><tbody>';

foreach ($groups as $group) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Ln(3);
    $costCenter = trim((string) $group['cost_center_code'] . ' - ' . (string) $group['cost_center_name'], ' -');
    $pdf->SetFont('helvetica', '', 8);
    $infoHtml = '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
    foreach ([
        'No PR' => (string) $group['no_pr'],
        'Request' => (string) $group['request'],
        'Cost Center' => $costCenter,
        'Deskripsi PR' => (string) $group['pr_description'],
    ] as $label => $value) {
        $infoHtml .= '<tr><td width="13%">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td><td width="2%">:</td><td width="85%">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>';
    }
    $infoHtml .= '</table>';
    $pdf->SetFont('helvetica', '', 7);
    $html = $tableHeader;
    if (!$group['rows']) {
        $html .= '<tr><td colspan="' . count($headers) . '" align="center">Tidak ada data PO untuk produk di PR ini.</td></tr>';
    } else {
        foreach ($group['rows'] as $index => $row) {
            $cells = [
                (string) ($index + 1), (string) ($row['kode_product'] ?? ''), (string) ($row['product_name'] ?? ''),
                (string) ($row['structname'] ?? ''), (string) ($row['no_po'] ?? ''),
                !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '',
                (string) ($row['vendor_name'] ?? ''),
                is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, ',', '.') : '',
                (string) ($row['satuan'] ?? ''), (string) ($row['deskripsi'] ?? ''),
                is_numeric($row['current_stock'] ?? null) && (float) $row['current_stock'] != 0.0 ? number_format((float) $row['current_stock'], 2, ',', '.') : '-',
                trim((string) ($row['warehouse_name'] ?? '')) !== '' ? (string) $row['warehouse_name'] : '-',
            ];
            if ($hideDescription) array_splice($cells, 9, 1);
            $html .= '<tr>';
            foreach ($cells as $cellIndex => $cell) {
                $cellStyle = $cellIndex === 1 ? ' style="text-align:center;white-space:nowrap"' : '';
                $html .= '<td width="' . round($widths[$cellIndex] / array_sum($widths) * 100, 2) . '%"' . $cellStyle . '>'
                    . htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table>';
    $historyLabel = $showAllHistory ? 'Seluruh History Pembelian' : 'History Pembelian Terakhir';
    $historyHtml = '<div style="font-weight:bold;margin:0;padding:0">' . htmlspecialchars($historyLabel, ENT_QUOTES, 'UTF-8') . '</div>';
    $pdf->writeHTML($infoHtml . $historyHtml . $html, true, false, true, false, '');
}

$filename = 'Cek_Harga_By_PR_' . date('Ymd_His') . '.pdf';
while (ob_get_level() > 0) {
    ob_end_clean();
}
$pdf->Output($filename, 'I');
