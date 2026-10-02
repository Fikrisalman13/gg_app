<?php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';
try{
  $pdo->beginTransaction();
  $id=(int)($_POST['id'] ?? 0);
  if($id<=0) throw new Exception('ID invalid');

  $req=$pdo->prepare("SELECT request_no FROM siin_ir WHERE id=?");
  $req->execute([$id]); $reqno=$req->fetchColumn();
  if(!$reqno) throw new Exception('IR tidak ditemukan');

  // protect jika sudah ada HT referensi
  $chk=$pdo->prepare("SELECT COUNT(*) FROM siin_ht WHERE ir_no=?");
  $chk->execute([$reqno]);
  if($chk->fetchColumn()>0) throw new Exception('Tidak bisa hapus: sudah ada Serah Terima referensi IR ini');

  // reverse stok
  $it=$pdo->prepare("SELECT product_id,qty FROM siin_ir_item WHERE ir_id=?");
  $it->execute([$id]);
  foreach($it as $r){ stock_move($pdo,(int)$r['product_id'],0,(int)$r['qty'],'IR',$id,"DEL IR $reqno"); }

  $pdo->prepare("DELETE FROM siin_ir_item WHERE ir_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM siin_ir WHERE id=?")->execute([$id]);

  audit_log($pdo,'IR_DELETE','siin_ir',$id,['request_no'=>$reqno]);
  $pdo->commit();
  echo json_encode(['success'=>true]);
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
