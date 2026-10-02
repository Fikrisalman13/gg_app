<?php
// ir_items.php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

header('Content-Type: application/json');

try {
  $reqno = trim($_GET['request_no'] ?? '');
  if ($reqno==='') throw new Exception('request_no kosong');

  // ambil header IR
  $st = $pdo->prepare("SELECT id, ir_date, request_no, descr, status FROM siin_ir WHERE request_no=?");
  $st->execute([$reqno]);
  $ir = $st->fetch(PDO::FETCH_ASSOC);
  if (!$ir) throw new Exception('IR tidak ditemukan');

  // ambil items IR + produk
  $sql = "SELECT i.id as ir_item_id, i.product_id, p.code as product_code, p.name as product_name,
                 i.qty, i.qty_out, (i.qty - i.qty_out) as available_qty,
                 i.uom_id, i.note
          FROM siin_ir_item i
          JOIN siin_product p ON p.id = i.product_id
          WHERE i.ir_id = ?
          ORDER BY p.code, p.name";
  $st = $pdo->prepare($sql);
  $st->execute([$ir['id']]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['success'=>true, 'ir'=>$ir, 'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
