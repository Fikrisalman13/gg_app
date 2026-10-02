<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu!');
}

if (!$conn || !$conn3) {
    die('Koneksi ke database gagal.');
}

date_default_timezone_set('Asia/Jakarta');

$userName = $_SESSION['UserName'];
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? $startDate;
$vendorQueryParam = $_GET['vendor_name'] ?? '';
$selectedVendorList = [];
if (is_array($vendorQueryParam)) {
    $selectedVendorList = array_values(array_filter(array_map('trim', $vendorQueryParam)));
} else {
    $selectedVendorList = array_values(array_filter(array_map('trim', preg_split('/\s*[;,]\s*/', (string)$vendorQueryParam))));
}

$savedOptionsStmt = sqlsrv_query($conn, 'SELECT vendor_name FROM dbo.po_option WHERE username = ? ORDER BY vendor_name', [$userName]);
$savedOptionList = [];
if ($savedOptionsStmt !== false) {
    while ($row = sqlsrv_fetch_array($savedOptionsStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['vendor_name'])) $savedOptionList[] = $row['vendor_name'];
    }
    sqlsrv_free_stmt($savedOptionsStmt);
}

$savedProdStmt = sqlsrv_query($conn, 'SELECT vendor_name, prodcode, prodname FROM dbo.po_option WHERE username = ? AND prodcode IS NOT NULL ORDER BY vendor_name, prodcode', [$userName]);
$savedProdList = [];
if ($savedProdStmt !== false) {
    while ($row = sqlsrv_fetch_array($savedProdStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['vendor_name'])) $savedProdList[] = $row;
    }
    sqlsrv_free_stmt($savedProdStmt);
}

$filterRules = [];
foreach ($savedOptionList as $savedVendorName) {
    $vendorKey = strtoupper(trim((string)$savedVendorName));
    if ($vendorKey === '') continue;
    if (!isset($filterRules[$vendorKey])) $filterRules[$vendorKey] = ['all' => false, 'codes' => []];
    $filterRules[$vendorKey]['all'] = true;
}
foreach ($savedProdList as $savedProd) {
    $vendorKey = strtoupper(trim((string)($savedProd['vendor_name'] ?? '')));
    $prodcodeKey = strtoupper(trim((string)($savedProd['prodcode'] ?? '')));
    if ($vendorKey === '') continue;
    if (!isset($filterRules[$vendorKey])) $filterRules[$vendorKey] = ['all' => false, 'codes' => []];
    if ($prodcodeKey !== '') {
        $filterRules[$vendorKey]['codes'][$prodcodeKey] = true;
        $filterRules[$vendorKey]['all'] = false;
    }
}

if (empty($selectedVendorList) && !empty($savedOptionList)) {
    $selectedVendorList = $savedOptionList;
}

$filterVendorLookup = [];
foreach ($selectedVendorList as $vendorFilter) {
    $vendorKey = strtoupper(trim((string)$vendorFilter));
    if ($vendorKey !== '') $filterVendorLookup[$vendorKey] = true;
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=ISO_PO_' . date('Ymd_His') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');

$query = "SELECT h.ponmbr, h.podate, h.povendorname, d.poprodname, h.fgstatus, d.poprodid, d.poprodcode, d.poqty FROM prpohd h INNER JOIN prpodt d ON h.pohdid = d.pohdid WHERE h.podate BETWEEN ? AND ? ORDER BY h.podate, h.ponmbr";
$stmt = $conn3->prepare($query);
$stmt->execute([$startDate, $endDate]);
$poData = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($poData)) $poData = [];
if (!empty($filterRules)) {
    $poData = array_values(array_filter($poData, function ($row) use ($filterRules, $filterVendorLookup) {
        $vendorKey = strtoupper(trim((string)($row['povendorname'] ?? '')));
        $prodcodeKey = strtoupper(trim((string)($row['poprodcode'] ?? '')));
        if ($vendorKey === '' || !isset($filterRules[$vendorKey])) return false;
        if (!empty($filterVendorLookup) && !isset($filterVendorLookup[$vendorKey])) return false;
        if (!empty($filterRules[$vendorKey]['codes'])) {
            return $prodcodeKey !== '' && isset($filterRules[$vendorKey]['codes'][$prodcodeKey]);
        }
        return $filterRules[$vendorKey]['all'];
    }));
}

$monthlySummary = [];
foreach ($poData as $row) {
    $poDate = !empty($row['podate']) ? date('Y-m', strtotime((string)$row['podate'])) : '';
    if ($poDate === '') continue;
    if (!isset($monthlySummary[$poDate])) {
        $monthlySummary[$poDate] = ['month' => $poDate, 'closed' => 0, 'items' => 0];
    }
    $monthlySummary[$poDate]['items']++;
    if ((string)($row['fgstatus'] ?? '') === 'X') {
        $monthlySummary[$poDate]['closed']++;
    }
}
$monthlySummary = array_values($monthlySummary);
usort($monthlySummary, function ($left, $right) {
    return strcmp($left['month'], $right['month']);
});

echo "<table border='1'>";
echo "<thead>";
echo "<tr style='background:#d9d9d9;font-weight:bold;text-align:center;'><th>BULAN</th><th>JUMLAH CLOSED</th><th>JUMLAH ITEM</th><th>Persentase Pemenuhan PO</th></tr>";
echo "</thead><tbody>";
if (empty($monthlySummary)) {
    echo "<tr><td colspan='4' style='text-align:center;'>Data kosong</td></tr>";
} else {
    foreach ($monthlySummary as $summary) {
        $percent = $summary['items'] > 0 ? ($summary['closed'] / $summary['items']) * 100 : 0;
        echo '<tr>';
        echo '<td>' . htmlspecialchars(date('F Y', strtotime($summary['month'] . '-01'))) . '</td>';
        echo '<td style="text-align:center;">' . (int)$summary['closed'] . '</td>';
        echo '<td style="text-align:center;">' . (int)$summary['items'] . '</td>';
        echo '<td style="text-align:right;">' . number_format($percent, 2, ',', '.') . '%</td>';
        echo '</tr>';
    }
}
echo '</tbody></table><br>';
echo "<table border='1'>";
echo "<thead>";
echo "<tr style='background:#d9d9d9;font-weight:bold;text-align:center;'>";
echo "<th>No</th><th>No PO</th><th>Tgl PO</th><th>Vendor</th><th>Item ID</th><th>Kode Produk</th><th>Nama Produk</th><th>Qty</th><th>Status</th>";
echo "</tr>";
echo "</thead><tbody>";
$no = 1;
foreach ($poData as $row) {
    $tgl = $row['podate'] instanceof DateTime ? $row['podate']->format('d/m/Y') : date('d/m/Y', strtotime((string)$row['podate']));
    $status = $row['fgstatus'] ?? '';
    $statusText = $status;
    if ($status === 'O') $statusText = 'Open';
    if ($status === 'X') $statusText = 'Close';
    if ($status === 'V') $statusText = 'Approve';
    if ($status === 'U') $statusText = 'Outstanding';
    if ($status === 'C') $statusText = 'Cancel';

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($row['ponmbr'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($tgl) . '</td>';
    echo '<td>' . htmlspecialchars($row['povendorname'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['poprodid'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['poprodcode'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['poprodname'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['poqty'] ?? '0')) . '</td>';
    echo '<td>' . htmlspecialchars($statusText) . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';
