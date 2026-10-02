<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';
$pid = (int)($_GET['product_id'] ?? 0);
if($pid<=0){ echo json_encode(['ok'=>false]); exit; }

function first_ir_with_product(PDO $pdo, $pid, $statusArr){
  $in = implode(',', array_fill(0, count($statusArr), '?'));
  $params = $statusArr; $params[] = $pid;
  $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($drv === 'sqlsrv') {
    $sql = "SELECT TOP 1 ir.request_no, ir.status
            FROM siin_ir ir
            JOIN siin_ir_item ii ON ii.ir_id = ir.id
            WHERE ir.status IN ($in) AND ii.product_id=?
            ORDER BY ir.ir_date ASC, ir.id ASC";
  } else {
    $sql = "SELECT ir.request_no, ir.status
            FROM siin_ir ir
            JOIN siin_ir_item ii ON ii.ir_id = ir.id
            WHERE ir.status IN ($in) AND ii.product_id=?
            ORDER BY ir.ir_date ASC, ir.id ASC
            LIMIT 1";
  }
  $st=$pdo->prepare($sql); $st->execute($params);
  return $st->fetch(PDO::FETCH_ASSOC);
}

$pick = first_ir_with_product($pdo, $pid, ['outstanding']);
if(!$pick) $pick = first_ir_with_product($pdo, $pid, ['Approved']);
if($pick){
  echo json_encode(['ok'=>true,'ir_no'=>$pick['request_no'],'ir_status'=>$pick['status']]);
}else{
  echo json_encode(['ok'=>false]);
}
