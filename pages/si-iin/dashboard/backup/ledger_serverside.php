<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Pakai helper & PDO SQL Server dari siin_lib.php
require_once __DIR__ . '/../_shared/siin_lib.php';

// util konversi tanggal (dd-mm-yyyy) → (yyyy-mm-dd)
// kalau di siin_lib.php sudah ada dmY_to_Ymd, boleh pakai itu saja.
// Di sini kita pakai versi dari siin_lib.php, jadi TIDAK kita definisikan ulang.

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$sd     = trim($_POST['start_date'] ?? '');
$ed     = trim($_POST['end_date'] ?? '');
$q      = trim($_POST['q'] ?? '');

// filter
$where  = " WHERE 1=1 ";
$params = [];

// filter tanggal: pakai CONVERT(date, ...) untuk SQL Server
if($sd !== ''){
  $where   .= " AND CONVERT(date, l.trx_time) >= ?";
  $params[] = dmY_to_Ymd($sd);
}
if($ed !== ''){
  $where   .= " AND CONVERT(date, l.trx_time) <= ?";
  $params[] = dmY_to_Ymd($ed);
}

// filter pencarian produk
if($q !== ''){
  $where .= " AND (
      ISNULL(p.prod_code,'') LIKE ?
      OR ISNULL(p.prod_name,'') LIKE ?
    )";
  $like = '%'.$q.'%';
  array_push($params, $like, $like);
}

// total semua baris (tanpa filter)
$total = (int)$pdo->query("SELECT COUNT(*) FROM siin_stock_ledger")->fetchColumn();

// total setelah filter
$cntSql = "
  SELECT COUNT(*)
  FROM siin_stock_ledger l
  JOIN siin_product p ON p.id = l.product_id
  $where
";
$stCnt = $pdo->prepare($cntSql);
$stCnt->execute($params);
$filtered = (int)$stCnt->fetchColumn();

// paging untuk SQL Server
$offset = max(0, $start);
if ($length < 1) {
  // DataTables kadang kirim -1, artinya semua data
  $length = $filtered > 0 ? $filtered : 1000;
}

// query data dengan OFFSET/FETCH
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
  ORDER BY l.trx_time DESC, l.id DESC
  OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY
";
$st = $pdo->prepare($sql);
$st->execute($params);

$data = [];
foreach($st as $r){
  // trx_time bisa berupa string atau DateTime tergantung driver
  $trxTime = $r['trx_time'];
  if ($trxTime instanceof DateTimeInterface) {
    $trxTime = $trxTime->format('Y-m-d H:i:s');
  }

  $product = trim(
    ($r['prod_code'] ? '['.$r['prod_code'].'] ' : '') .
    ($r['prod_name'] ?? '')
  );
  $ref = $r['ref_type'] . ($r['ref_id'] ? '#'.$r['ref_id'] : '');

  $data[] = [
    'trx_time'      => htmlspecialchars((string)$trxTime),
    'product'       => htmlspecialchars($product),
    'qty_in'        => number_format((int)$r['qty_in']),
    'qty_out'       => number_format((int)$r['qty_out']),
    'balance_after' => number_format((int)$r['balance_after']),
    'ref'           => htmlspecialchars($ref),
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
