<?php
session_start();
include '../../koneksi3.php';
require_once __DIR__ . '/cekhargabypr_lib.php';

if (!isset($_SESSION['UserName'])) { http_response_code(403); exit('Silakan login terlebih dahulu!'); }
if (!$conn3) { http_response_code(500); exit('Koneksi ke database gagal.'); }

$prNumbers = parseSelectedPrNumbers((string) ($_POST['prnumbers'] ?? ''));
$showAllHistory = (string) ($_POST['history_mode'] ?? '0') === '1';
if (!$prNumbers) { http_response_code(400); exit('Masukkan minimal 1 nomor PR terlebih dahulu.'); }
$groups = fetchPrPriceGroups($conn3, $prNumbers, $showAllHistory);

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=Cek_Harga_By_PR_' . date('Ymd_His') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');
$headers = ['No', 'Kode Product', 'Product Name', 'Struct Name', 'No PO', 'PO Date', 'Vendor Name', 'Price PO (Rupiah)', 'Satuan', 'Deskripsi', 'Stock Saat Ini', 'Nama Gudang'];
$safe = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo "\xEF\xBB\xBF<table border='1'>";
echo '<tr><td colspan="12"><b>CEK HARGA BY PURCHASE REQUISITION</b></td></tr>';
echo '<tr><td colspan="12">Tanggal Export: ' . $safe(date('d/m/Y H:i')) . '</td></tr>';
echo '<tr><td colspan="12">Mode: ' . ($showAllHistory ? 'Seluruh History PO' : 'PO Terakhir') . '</td></tr>';
foreach ($groups as $group) {
    echo '<tr><td colspan="12"><b>No PR</b> ' . $safe($group['no_pr']) . '</td></tr>';
    echo '<tr>';
    foreach ($headers as $header) echo '<th style="background-color:#e9ecef">' . $safe($header) . '</th>';
    echo '</tr>';
    if (!$group['rows']) {
        echo '<tr><td colspan="12">Tidak ada data PO untuk produk di PR ini.</td></tr>';
        continue;
    }
    foreach ($group['rows'] as $index => $row) {
        $date = !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '';
        $price = is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, '.', '') : '';
        $stockValue = $row['current_stock'] ?? null;
        $stock = is_numeric($stockValue) && (float) $stockValue != 0.0 ? number_format((float) $stockValue, 2, '.', '') : '-';
        $warehouseName = trim((string) ($row['warehouse_name'] ?? '')) !== '' ? $row['warehouse_name'] : '-';
        $values = [$index + 1, $row['kode_product'] ?? '', $row['product_name'] ?? '', $row['structname'] ?? '', $row['no_po'] ?? '', $date, $row['vendor_name'] ?? '', $price, $row['satuan'] ?? '', $row['deskripsi'] ?? '', $stock, $warehouseName];
        echo '<tr>';
        foreach ($values as $col => $value) {
            $text = $safe($value);
            $textFormat = in_array($col, [1, 4], true) ? " style=\"mso-number-format:'\\@';\"" : '';
            echo '<td' . $textFormat . '>' . $text . '</td>';
        }
        echo '</tr>';
    }
}
echo '<tr><td colspan="12"><b>Total Records: ' . array_sum(array_map(static fn($group) => count($group['rows']), $groups)) . '</b></td></tr></table>';
