<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';
$ir_no = trim($_GET['ir_no'] ?? '');
if($ir_no===''){ echo json_encode(['ok'=>false,'message'=>'IR kosong']); exit; }

$h=$pdo->prepare("SELECT id,status FROM siin_ir WHERE request_no=?");
$h->execute([$ir_no]); $H=$h->fetch();
if(!$H){ echo json_encode(['ok'=>false,'message'=>'IR tidak ditemukan']); exit; }

$it=$pdo->prepare("SELECT ii.product_id, p.prod_code, p.prod_name, ii.uom_id, u.uomname
                   FROM siin_ir_item ii
                   JOIN siin_product p ON p.id=ii.product_id
                   LEFT JOIN siin_uom u ON u.id=ii.uom_id
                   WHERE ii.ir_id=?");
$it->execute([$H['id']]); $items=$it->fetchAll();

echo json_encode(['ok'=>true,'status'=>$H['status'],'items'=>$items]);
