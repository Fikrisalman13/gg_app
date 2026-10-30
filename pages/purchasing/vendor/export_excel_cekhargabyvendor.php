<?php
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

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
$fileVendor = $vendorCode !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '_', $vendorCode) : 'Semua_Vendor';
header('Content-Disposition: attachment; filename=Riwayat_Harga_Vendor_' . $fileVendor . '_' . date('Ymd_His') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');
$safe = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$headers = ['No', 'Kode Product', 'Product Name', 'Struct Name', 'No PO', 'PO Date', 'No GRN', 'GRN Date', 'Vendor Name', 'Price PO (Rupiah)', 'Satuan', 'Deskripsi'];
echo "\xEF\xBB\xBF<table border='1'>";
echo '<tr><td colspan="12"><b>CEK HARGA BY VENDOR</b></td></tr>';
echo '<tr><td colspan="12">Vendor: ' . ($vendor ? $safe($vendor['vendorcode']) . ' - ' . $safe($vendor['vendorname']) : 'Semua Vendor') . '</td></tr>';
echo '<tr><td colspan="12">Tanggal Export: ' . $safe(date('d/m/Y H:i')) . '</td></tr>';
echo '<tr><td colspan="12">Mode: ' . ($showAllHistory ? 'Seluruh History PO' : 'PO Terakhir') . '</td></tr>';
echo '<tr>';
foreach ($headers as $header) echo '<th style="background-color:#e9ecef">' . $safe($header) . '</th>';
echo '</tr>';
foreach ($rows as $index => $row) {
    $poDate = !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '';
    $grnDate = !empty($row['grn_date']) ? date('d/m/Y', strtotime((string) $row['grn_date'])) : '';
    $price = is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, '.', '') : '';
    $values = [$index + 1, $row['kode_product'] ?? '', $row['product_name'] ?? '', $row['structname'] ?? '', $row['no_po'] ?? '', $poDate, $row['no_grn'] ?? '', $grnDate, $row['vendor_name'] ?? '', $price, $row['satuan'] ?? '', $row['deskripsi'] ?? ''];
    echo '<tr>';
    foreach ($values as $column => $value) {
        $format = in_array($column, [1, 4, 6], true) ? " style=\"mso-number-format:'\\@';\"" : '';
        echo '<td' . $format . '>' . $safe($value) . '</td>';
    }
    echo '</tr>';
}
echo '<tr><td colspan="12"><b>Total Records: ' . count($rows) . '</b></td></tr></table>';
