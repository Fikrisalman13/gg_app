<?php
// gg_app/pages/si-iin/dashboard/stock_serverside.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_shared/siin_lib.php'; // harus menyediakan $pdo dan helper

// DataTables parameters
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);

// Filters from your frontend
$q        = trim($_POST['q'] ?? '');
$onlyLow  = (int)($_POST['only_low'] ?? 0);
$lowThr   = (int)($_POST['low_threshold'] ?? 5);

// -----------------------
// Build WHERE & params
// -----------------------
$where  = " WHERE 1=1 ";
$params = [];

if ($q !== '') {
  $where .= " AND (
      COALESCE(p.prod_code,'') LIKE ?
      OR COALESCE(p.prod_name,'') LIKE ?
      OR COALESCE(c.name,'') LIKE ?
      OR COALESCE(u.uomname,'') LIKE ?
    )";
  $like = '%'.$q.'%';
  array_push($params, $like, $like, $like, $like);
}

if ($onlyLow === 1) {
  $where .= " AND COALESCE(p.stock,0) < ?";
  $params[] = $lowThr;
}

// -----------------------
// Ordering (translate DataTables order -> SQL)
// -----------------------
// DataTables sends: order[0][column]=<index> & order[0][dir]=asc|desc
// Map column index (from front-end columns definition) to DB column safe name.
$columnIndex = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : null;
$columnDir   = isset($_POST['order'][0]['dir']) ? strtolower($_POST['order'][0]['dir']) : 'asc';

// Whitelist mapping: table column names used for ordering
// Index mapping must match DataTables columns in index.php:
// 0 -> No (not a DB column), 1 -> prod_code, 2 -> prod_name, 3 -> category, 4 -> uom, 5 -> stock, 6 -> aksi (ignore)
$colMap = [
  1 => 'p.prod_code',
  2 => 'p.prod_name',
  3 => 'c.name',
  4 => 'u.uomname',
  5 => 'p.stock'
];

$orderSql = " ORDER BY p.id DESC "; // default
if ($columnIndex !== null && array_key_exists($columnIndex, $colMap)) {
    $dir = ($columnDir === 'desc') ? 'DESC' : 'ASC';
    $orderSql = " ORDER BY " . $colMap[$columnIndex] . " " . $dir;
}

// -----------------------
// Count total & filtered
// -----------------------
try {
    // total records (without filters)
    $total = (int)$pdo->query("SELECT COUNT(*) FROM siin_product")->fetchColumn();

    // total filtered
    $cntSql = "
      SELECT COUNT(*)
      FROM siin_product p
      LEFT JOIN siin_category c ON c.id = p.category_id
      LEFT JOIN siin_uom u      ON u.id = p.uom_id
      $where
    ";
    $cnt = $pdo->prepare($cntSql);
    $cnt->execute($params);
    $filtered = (int)$cnt->fetchColumn();

    // -----------------------
    // Paging: OFFSET / FETCH
    // -----------------------
    $offset = max(0, $start);
    if ($length < 1) {
      // DataTables may send -1 meaning "all"
      $length = $filtered > 0 ? $filtered : 1000;
    }

    // Build data query (safe because $orderSql uses whitelist)
    $sql = "
      SELECT 
        p.id,
        p.prod_code,
        p.prod_name,
        COALESCE(c.name,'-')    AS category,
        COALESCE(u.uomname,'-') AS uom,
        COALESCE(p.stock,0)     AS stock
      FROM siin_product p
      LEFT JOIN siin_category c ON c.id = p.category_id
      LEFT JOIN siin_uom u      ON u.id = p.uom_id
      $where
      $orderSql
      OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);

    $data = [];
    foreach ($st as $r) {
      $data[] = [
        'id'        => (int)$r['id'],
        'prod_code' => htmlspecialchars($r['prod_code'] ?? ''),
        'prod_name' => htmlspecialchars($r['prod_name'] ?? ''),
        'category'  => htmlspecialchars($r['category'] ?? '-'),
        'uom'       => htmlspecialchars($r['uom'] ?? '-'),
        // Keep stock as numeric value (unformatted) so client-side or future server-side can sort/search easily.
        // But if you prefer formatted string for display, you can use number_format here.
        'stock'     => (int)$r['stock']
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
    // rollback not needed here but return helpful error (remove details in production)
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
