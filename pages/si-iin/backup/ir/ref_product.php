<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

$mode = ($_GET['mode'] ?? 'name'); // 'code'|'name'
$q = trim($_GET['q'] ?? '');
$out=[];

$drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$like = '%'.$q.'%';
if ($drv === 'sqlsrv') {
  $sql = "SELECT TOP 50 p.id, p.prod_code, p.prod_name, u.uomname, u.id uom_id
        FROM siin_product p
        LEFT JOIN siin_uom u ON u.id=p.uom_id
        WHERE (p.prod_code LIKE ? OR p.prod_name LIKE ?)
        ORDER BY p.prod_name ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$like, $like]);
} else {
  $sql = "SELECT p.id, p.prod_code, p.prod_name, u.uomname, u.id uom_id
        FROM siin_product p
        LEFT JOIN siin_uom u ON u.id=p.uom_id
        WHERE (p.prod_code LIKE ? OR p.prod_name LIKE ?)
        ORDER BY p.prod_name ASC
        LIMIT 50";
  $st = $pdo->prepare($sql);
  $st->execute([$like, $like]);
}
foreach($st as $r){
  $out[] = [
    'id' => (int)$r['id'],
    'text' => ($mode==='code' ? ($r['prod_code'] ?: $r['prod_name']) : ($r['prod_name'] ?: $r['prod_code'])),
    'prod_code' => $r['prod_code'],
    'prod_name' => $r['prod_name'],
    'uom_id' => $r['uom_id'],
    'uomname' => $r['uomname'],
  ];
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE);
