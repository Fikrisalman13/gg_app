<?php
// ir_save.php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

header('Content-Type: application/json');

// helper: parse JSON body jika dikirim via fetch
$raw = file_get_contents('php://input');
if ($raw) {
  $data = json_decode($raw, true);
  if (is_array($data)) $_POST = $data + $_POST;
}

try {
  $pdo->beginTransaction();

  $ir_date = dmY_to_Ymd($_POST['ir_date'] ?? date('Y-m-d'));
  $reqno   = trim($_POST['request_no'] ?? '');
  $descr   = trim($_POST['descr'] ?? '');
  if($reqno==='') throw new Exception('Request No wajib diisi');

  // pastikan unik
  $st=$pdo->prepare("SELECT COUNT(*) FROM siin_ir WHERE request_no=?");
  $st->execute([$reqno]);
  if($st->fetchColumn()>0) throw new Exception('Request No sudah ada');

  // header (status default Approved seperti semula)
  $pdo->prepare("INSERT INTO siin_ir(ir_date,request_no,descr,status,upddate,upduser)
                 VALUES(?,?,?,?,?,?)")
      ->execute([$ir_date,$reqno,$descr,'Approved',nowIso(),current_user()]);
  $ir_id=(int)$pdo->lastInsertId();

  // items
  $items = $_POST['items'] ?? [];
  if(!$items) throw new Exception('Minimal 1 item');

  // pastikan kolom tambahan sudah ada di tabel: ppnmbr, ppdate, ponmbr, podate, grnnmbr, grndate
  $insItem = $pdo->prepare("
    INSERT INTO siin_ir_item(
      ir_id, product_id, qty, qty_out, uom_id, note,
      ppnmbr, ppdate, ponmbr, podate, grnnmbr, grndate
    )
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  foreach($items as $it){
    $qty = clean_int($it['qty'] ?? 0);
    if($qty<=0) continue;

    $rawCode = trim($it['prod_code'] ?? '');
    $rawName = trim($it['prod_name'] ?? '');
    $uom_id  = ensure_uom($pdo, $it['uom_id'] ?? null);
    $note    = trim($it['note'] ?? '');

    // data PP/PO/GRN per item (dari hidden input di form, diisi saat pilih GRN)
    $ppnmbr  = trim($it['ppnmbr']  ?? '');
    $ppdate  = trim($it['ppdate']  ?? '') ?: null; // diasumsikan sudah format YYYY-MM-DD
    $ponmbr  = trim($it['ponmbr']  ?? '');
    $podate  = trim($it['podate']  ?? '') ?: null;
    $grnnmbr = trim($it['grnnmbr'] ?? '');
    $grndate = trim($it['grndate'] ?? '') ?: null;

    // Ambil ID produk bila numeric; kalau tidak numeric gunakan code/name
    $pid = ensure_product($pdo, [
      'id'     => ctype_digit($rawCode) ? (int)$rawCode : (ctype_digit($rawName) ? (int)$rawName : null),
      'code'   => ctype_digit($rawCode) ? null : ($rawCode ?: null),
      'name'   => ctype_digit($rawName) ? null : ($rawName ?: null),
      'uom_id' => $uom_id,
    ]);
    if(!$pid) throw new Exception('Produk tidak valid pada salah satu item');

    // simpan item (qty_out=0) + data PP/PO/GRN per item
    $insItem->execute([
      $ir_id,
      $pid,
      $qty,
      0,
      $uom_id,
      $note,
      $ppnmbr,
      $ppdate,
      $ponmbr,
      $podate,
      $grnnmbr,
      $grndate
    ]);

    // ledger IN (jika fungsi tersedia)
    if(function_exists('stock_move')){
      stock_move($pdo, $pid, $qty, 0, 'IR', $ir_id, "IR $reqno");
    }
  }

  audit_log($pdo,'IR_CREATE','siin_ir',$ir_id,$_POST);
  $pdo->commit();
  echo json_encode(['success'=>true,'id'=>$ir_id]);
} catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
