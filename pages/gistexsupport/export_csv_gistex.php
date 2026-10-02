<?php
require_once '../../koneksi.php';
require_once 'helpers.php';

$no_po = trim($_GET['no_po'] ?? '');
if ($no_po === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'no_po parameter required';
    exit;
}

$sql = "
SELECT
    o.id,
    o.item,
    o.description,
    o.packaging,
    o.uom,
    d.lot,
    d.batchno,
    d.qtym,
    d.qtyyard
FROM orderitem_gistex o
INNER JOIN orderitem_gistex_dt d ON o.uniqueid = d.uniqueid_parent
WHERE o.no_po = ?
ORDER BY o.id ASC
";

$stmt = sqlsrv_query($conn, $sql, [$no_po]);
if (!$stmt) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Query error: ' . print_r(sqlsrv_errors(), true);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="order_po_' . $no_po . '.csv"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['id', 'item', 'description', 'pack_qty', 'packaging', 'lot', 'roll', 'qty_content_per_pack']);

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $uomUpper = strtoupper(trim($row['uom'] ?? ''));
    if ($uomUpper === 'METER') {
        $qty_content = $row['qtym'] ?? 0;
    } else {
        $qty_content = $row['qtyyard'] ?? 0;
    }

    $fields = [
        $row['id'] ?? '',
        $row['item'] ?? '',
        $row['description'] ?? '',
        '1', // pack_qty always 1
        $row['packaging'] ?? '',
        $row['lot'] ?? '',
        $row['batchno'] ?? '',
        $qty_content
    ];

    fputcsv($output, $fields);
}

fclose($output);
