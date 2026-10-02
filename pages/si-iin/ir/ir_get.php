<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';
$id=(int)($_GET['id'] ?? 0);
$h=$pdo->prepare("SELECT * FROM siin_ir WHERE id=?"); $h->execute([$id]); $h=$h->fetch();
if(!$h){ echo json_encode(null); exit; }

$it=$pdo->prepare("SELECT ii.*, p.prod_code, p.prod_name, u.uomname
                   FROM siin_ir_item ii
                   JOIN siin_product p ON p.id=ii.product_id
                   LEFT JOIN siin_uom u ON u.id=ii.uom_id
                   WHERE ii.ir_id=?");
$it->execute([$id]); $items=$it->fetchAll();

echo json_encode([
  'header'=>[
    'id'=>$h['id'],
    'ir_date'=>$h['ir_date'],
    'ir_date_dmY'=>Ymd_to_dmY($h['ir_date']),
    'request_no'=>$h['request_no'],
    'descr'=>$h['descr'],
    'status'=>$h['status']
  ],
  'items'=>$items
]);
