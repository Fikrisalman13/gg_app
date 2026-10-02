<?php
// gg_app/pages/si-iin/dashboard/stock_serverside.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_shared/siin_lib.php';

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);

$q        = trim($_POST['q'] ?? '');
$onlyLow  = (int)($_POST['only_low'] ?? 0);
$lowThr   = (int)($_POST['low_threshold'] ?? 5);

$where  = " WHERE 1=1 ";
$params = [];

// filter pencarian
if($q !== ''){
  $where .= " AND (
      ISNULL(p.prod_code,'')   LIKE ?
      OR ISNULL(p.prod_name,'') LIKE ?
      OR ISNULL(c.name,'')      LIKE ?
      OR ISNULL(u.uomname,'')   LIKE ?
    )";
  $like = '%'.$q.'%';
  array_push($params, $like, $like, $like, $like);
}

// filter stock rendah
if($onlyLow === 1){
  $where .= " AND ISNULL(p.stock,0) < ?";
  $params[] = $lowThr;
}

// total semua baris (tanpa filter search)
$total = (int)$pdo->query("SELECT COUNT(*) FROM siin_product")->fetchColumn();

// total setelah filter
$cntSql = "
  SELECT COUNT(*)
  FROM siin_product p
  LEFT JOIN siin_category c ON c.id=p.category_id
  LEFT JOIN siin_uom u      ON u.id=p.uom_id
  $where
";
$cnt = $pdo->prepare($cntSql);
$cnt->execute($params);
$filtered = (int)$cnt->fetchColumn();

// siapkan nilai offset & limit untuk SQL Server
$offset = max(0, $start);

// DataTables kadang kirim length = -1 (artinya semua)
// untuk server-side, kita kasih default besar saja kalau -1
if ($length < 1) {
  $length = $filtered > 0 ? $filtered : 1000; // fallback
}

// SQL untuk data page
$sql = "
  SELECT 
    p.id,
    p.prod_code,
    p.prod_name,
    ISNULL(c.name,'-')    AS category,
    ISNULL(u.uomname,'-') AS uom,
    ISNULL(p.stock,0)     AS stock
  FROM siin_product p
  LEFT JOIN siin_category c ON c.id=p.category_id
  LEFT JOIN siin_uom u      ON u.id=p.uom_id
  $where
  ORDER BY p.id DESC
  OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY
";

$st = $pdo->prepare($sql);
$st->execute($params);

$data = [];
foreach($st as $r){
  $data[] = [
    'id'        => (int)$r['id'],
    'prod_code' => htmlspecialchars($r['prod_code'] ?? ''),
    'prod_name' => htmlspecialchars($r['prod_name'] ?? ''),
    'category'  => htmlspecialchars($r['category'] ?? '-'),
    'uom'       => htmlspecialchars($r['uom'] ?? '-'),
    'stock'     => number_format((int)$r['stock'])
  ];
}

echo json_encode([
  'draw'            => $draw,
  'recordsTotal'    => $total,
  'recordsFiltered' => $filtered,
  'data'            => $data
]);
