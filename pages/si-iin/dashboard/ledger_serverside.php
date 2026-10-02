<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Pakai helper & PDO SQL Server dari siin_lib.php
require_once __DIR__ . '/../_shared/siin_lib.php';

// DataTables params
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$sd     = trim($_POST['start_date'] ?? '');
$ed     = trim($_POST['end_date'] ?? '');
$q      = trim($_POST['q'] ?? '');

// filter
$where  = " WHERE 1=1 ";
$params = [];

// filter tanggal: pakai dmY_to_Ymd helper untuk konversi dd-mm-yyyy -> yyyy-mm-dd
if ($sd !== '') {
    $where   .= " AND CONVERT(date, l.trx_time) >= ?";
    $params[] = dmY_to_Ymd($sd);
}
if ($ed !== '') {
    $where   .= " AND CONVERT(date, l.trx_time) <= ?";
    $params[] = dmY_to_Ymd($ed);
}

// filter pencarian produk (kode/nama)
if ($q !== '') {
    $where .= " AND (
        ISNULL(p.prod_code,'') LIKE ?
        OR ISNULL(p.prod_name,'') LIKE ?
    )";
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}

// -----------------------------
// Ordering (translate DataTables -> SQL ORDER BY)
// -----------------------------
// Map DataTables column index => DB column
// DataTables columns in index.php:
// 0: No (not a DB column), 1: Waktu, 2: Produk, 3: Qty In, 4: Qty Out, 5: Saldo, 6: Ref, 7: User, 8: Catatan
$colMap = [
    1 => 'l.trx_time',
    2 => 'p.prod_name',        // ordering by product name (could also use prod_code)
    3 => 'l.qty_in',
    4 => 'l.qty_out',
    5 => 'l.balance_after',
    6 => 'l.ref_type',         // if you prefer combined ref string, server can't sort on concat easily; use ref_type
    7 => 'l.upduser',
    8 => 'l.note'
];

$orderSql = " ORDER BY l.trx_time DESC, l.id DESC "; // default ordering

if (isset($_POST['order']) && is_array($_POST['order'])) {
    $orders = [];
    foreach ($_POST['order'] as $ord) {
        $colIndex = isset($ord['column']) ? (int)$ord['column'] : null;
        $dir = isset($ord['dir']) && strtolower($ord['dir']) === 'desc' ? 'DESC' : 'ASC';
        if ($colIndex !== null && array_key_exists($colIndex, $colMap)) {
            // Whitelist column name from map
            $orders[] = $colMap[$colIndex] . ' ' . $dir;
        }
    }
    if (!empty($orders)) {
        $orderSql = ' ORDER BY ' . implode(', ', $orders);
    }
}

// -----------------------------
// Count total & filtered
// -----------------------------
try {
    // total all rows (ledger)
    $totalSql = "SELECT COUNT(*) FROM siin_stock_ledger";
    $total = (int)$pdo->query($totalSql)->fetchColumn();

    // total after filter
    $cntSql = "
      SELECT COUNT(*)
      FROM siin_stock_ledger l
      JOIN siin_product p ON p.id = l.product_id
      $where
    ";
    $stCnt = $pdo->prepare($cntSql);
    $stCnt->execute($params);
    $filtered = (int)$stCnt->fetchColumn();

    // paging (SQL Server OFFSET / FETCH)
    $offset = max(0, $start);
    if ($length < 1) { // DataTables may send -1 = all
        $length = $filtered > 0 ? $filtered : 1000;
    }

    // main query (apply ordering built above)
    $sql = "
      SELECT 
        l.trx_time,
        p.prod_code,
        p.prod_name,
        l.qty_in,
        l.qty_out,
        l.balance_after,
        l.ref_type,
        l.ref_id,
        l.note,
        l.upduser
      FROM siin_stock_ledger l
      JOIN siin_product p ON p.id = l.product_id
      $where
      $orderSql
      OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $data = [];
    foreach ($st as $r) {
        $trxTime = $r['trx_time'];
        if ($trxTime instanceof DateTimeInterface) {
            $trxTime = $trxTime->format('Y-m-d H:i:s');
        }

        // Build product display string (same as before)
        $product = trim(
            ($r['prod_code'] ? '[' . $r['prod_code'] . '] ' : '') .
            ($r['prod_name'] ?? '')
        );

        // Build ref display (same as before)
        $ref = htmlspecialchars(($r['ref_type'] ?? '') . ($r['ref_id'] ? '#' . $r['ref_id'] : ''));

        $data[] = [
            'trx_time'      => htmlspecialchars((string)$trxTime),
            'product'       => htmlspecialchars($product),
            // Keep numeric formatting for display but server sorts using numeric columns
            'qty_in'        => number_format((int)$r['qty_in']),
            'qty_out'       => number_format((int)$r['qty_out']),
            'balance_after' => number_format((int)$r['balance_after']),
            'ref'           => $ref,
            'upduser'       => htmlspecialchars($r['upduser'] ?? '-'),
            'note'          => htmlspecialchars($r['note'] ?? '')
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $total,
        'recordsFiltered' => $filtered,
        'data'            => $data
    ]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
