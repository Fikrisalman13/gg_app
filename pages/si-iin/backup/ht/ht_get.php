<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';
$id=(int)($_GET['id'] ?? 0);
$h=$pdo->prepare("SELECT * FROM siin_ht WHERE id=?"); $h->execute([$id]); $H=$h->fetch();
if(!$H){ echo json_encode(null); exit; }

$it=$pdo->prepare("SELECT hti.product_id, p.prod_code, p.prod_name, hti.uom_id, u.uomname, hti.qty
                   FROM siin_ht_item hti
                   JOIN siin_product p ON p.id=hti.product_id
                   LEFT JOIN siin_uom u ON u.id=hti.uom_id
                   WHERE hti.ht_id=?");
$it->execute([$id]); $items=$it->fetchAll();

echo json_encode([
  'id'=>$H['id'],
  'ht_date_dmY'=>Ymd_to_dmY($H['ht_date']),
  'ir_no'=>$H['ir_no'],
  'ir_status'=>$pdo->prepare("SELECT status FROM siin_ir WHERE request_no=?")->execute([$H['ir_no']]) ? $pdo->query("SELECT status FROM siin_ir WHERE request_no='".$H['ir_no']."'")->fetchColumn() : null,
  'emp_name'=>$H['emp_name'],
  'dept_name'=>$H['dept_name'],
  'descr'=>$H['descr'],
  'status'=>$H['status'],
  'sign_name'=>$H['sign_name'],
  'sign_image_path'=>$H['sign_image_path'],
  'items'=>$items
]);
