<?php
ob_start();
session_start();
include '../../../koneksi3.php';
require_once __DIR__ . '/cekhargabyvendor_lib.php';

if (!isset($_SESSION['UserName'])) { http_response_code(403); exit('Silakan login terlebih dahulu!'); }
if (!$conn3) { http_response_code(500); exit('Koneksi ke database gagal.'); }

$vendorCode = trim((string) ($_POST['vendorcode'] ?? ''));
$vendor = fetchActiveVendor($conn3, $vendorCode);
$productCodes = parseVendorProductCodes((string) ($_POST['prodcodes'] ?? ''));
$showAllHistory = (string) ($_POST['history_mode'] ?? '0') === '1';
if ($vendorCode !== '' && !$vendor) { http_response_code(400); exit('Vendor tidak ditemukan atau tidak aktif.'); }
if (!$productCodes) { http_response_code(400); exit('Pilih minimal 1 product.'); }
$rows = fetchVendorPriceRows($conn3, $vendorCode, $productCodes, $showAllHistory);

$tcpdfPath = __DIR__ . '/../../Logistik/transfer_packing/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) { http_response_code(500); exit('Library PDF tidak ditemukan.'); }
require_once $tcpdfPath;

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Cek Harga by Vendor');
$pdf->SetTitle('Cek Harga by Vendor');
$pdf->SetMargins(7, 12, 7);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(0, 8, 'CEK HARGA BY VENDOR', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, 'Vendor: ' . ($vendor ? $vendor['vendorcode'] . ' - ' . $vendor['vendorname'] : 'Semua Vendor'), 0, 1, 'L');
$pdf->Cell(0, 5, 'Tanggal export: ' . date('d/m/Y H:i') . ' | Mode: ' . ($showAllHistory ? 'Seluruh History PO' : 'PO Terakhir'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 6);

$headers = ['No', 'Kode Product', 'Product Name', 'Struct Name', 'No PO', 'PO Date', 'No GRN', 'GRN Date', 'Vendor Name', 'Price PO (Rupiah)', 'Satuan', 'Deskripsi'];
$widths = [7, 22, 35, 24, 22, 18, 23, 18, 34, 27, 13, 53];
$sumWidths = array_sum($widths);
$html = '<table width="100%" border="1" cellpadding="3"><thead><tr style="font-weight:bold;background-color:#e9ecef">';
foreach ($headers as $i => $header) {
    $html .= '<th width="' . round($widths[$i] / $sumWidths * 100, 2) . '%">' . htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
}
$html .= '</tr></thead><tbody>';
foreach ($rows as $index => $row) {
    $values = [
        (string) ($index + 1), (string) ($row['kode_product'] ?? ''), (string) ($row['product_name'] ?? ''),
        (string) ($row['structname'] ?? ''), (string) ($row['no_po'] ?? ''),
        !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '',
        (string) ($row['no_grn'] ?? ''), !empty($row['grn_date']) ? date('d/m/Y', strtotime((string) $row['grn_date'])) : '',
        (string) ($row['vendor_name'] ?? ''),
        is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, ',', '.') : '',
        (string) ($row['satuan'] ?? ''), (string) ($row['deskripsi'] ?? ''),
    ];
    $html .= '<tr>';
    foreach ($values as $column => $value) {
        $style = in_array($column, [0, 1, 4, 5, 6, 7, 10], true) ? ' style="text-align:center"' : '';
        $html .= '<td width="' . round($widths[$column] / $sumWidths * 100, 2) . '%"' . $style . '>'
            . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}
if (!$rows) $html .= '<tr><td colspan="12" align="center">Data PO/GRN tidak ditemukan untuk filter ini.</td></tr>';
$html .= '</tbody></table><br><b>Total Records: ' . count($rows) . '</b>';
$pdf->writeHTML($html, true, false, true, false, '');

$fileVendor = $vendorCode !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '_', $vendorCode) : 'Semua_Vendor';
$filename = 'Riwayat_Harga_Vendor_' . $fileVendor . '_' . date('Ymd_His') . '.pdf';
while (ob_get_level() > 0) ob_end_clean();
$pdf->Output($filename, 'I');
