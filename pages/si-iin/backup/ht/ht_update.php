<?php
/**
 * UPDATE HT (endpoint terpisah — jika UI masih memakainya)
 * - Rollback detail lama → stock masuk + qty_out IR ditarik mundur (FIFO)
 * - Tulis detail baru → stock keluar + qty_out dialokasikan (FIFO)
 * - Semua berdasarkan SQLite schema yang kamu kirim
 */
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

header('Content-Type: application/json; charset=utf-8');

try{
  if(!isset($_SESSION['UserName'])) throw new Exception('Sesi habis. Silakan login.');

  $id=(int)($_POST['id'] ?? 0); if($id<=0) throw new Exception('ID invalid');

  // Ambil header HT
  $h=$pdo->prepare("SELECT ir_no, status FROM siin_ht WHERE id=?");
  $h->execute([$id]); $H=$h->fetch(PDO::FETCH_ASSOC);
  if(!$H) throw new Exception('HT tidak ditemukan');
  if(strtolower($H['status'])==='Approved') throw new Exception('Tidak dapat edit HT Approved');

  // IR id
  $stIR = $pdo->prepare("SELECT id FROM siin_ir WHERE request_no=?");
  $stIR->execute([trim($_POST['ir_no'] ?? $H['ir_no'])]); 
  $R = $stIR->fetch(PDO::FETCH_ASSOC);
  if(!$R) throw new Exception('IR tidak ditemukan');
  $ir_id = (int)$R['id'];

  $ht_date=dmY_to_Ymd($_POST['ht_date'] ?? date('Y-m-d'));
  $ir_no  =trim($_POST['ir_no'] ?? $H['ir_no']);

  // ===== Helpers stok & IR =====
  $selStock  = $pdo->prepare("SELECT stock FROM siin_product WHERE id=?");
  $updStock  = $pdo->prepare("UPDATE siin_product SET stock=? WHERE id=?");
  $insLedger = $pdo->prepare("INSERT INTO siin_stock_ledger (trx_time, product_id, qty_in, qty_out, balance_after, ref_type, ref_id, note, upduser)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

  $stock_out = function(int $pid, int $qty, string $refType, int $refId, string $note) use ($selStock,$updStock,$insLedger){
    $selStock->execute([$pid]); $bal=(int)$selStock->fetchColumn();
    $new = $bal - $qty; $updStock->execute([$new,$pid]);
    $insLedger->execute([nowIso(), $pid, 0, $qty, $new, $refType, $refId, $note, current_user()]);
  };
  $stock_in = function(int $pid, int $qty, string $refType, int $refId, string $note) use ($selStock,$updStock,$insLedger){
    $selStock->execute([$pid]); $bal=(int)$selStock->fetchColumn();
    $new = $bal + $qty; $updStock->execute([$new,$pid]);
    $insLedger->execute([nowIso(), $pid, $qty, 0, $new, $refType, $refId, $note, current_user()]);
  };

  $selAvailSum = $pdo->prepare("SELECT COALESCE(SUM(it.qty - COALESCE(it.qty_out,0)),0)
                                FROM siin_ir_item it WHERE it.ir_id=? AND it.product_id=?");
  $selLinesForAlloc = $pdo->prepare("SELECT id, qty, COALESCE(qty_out,0) AS qty_out
                                     FROM siin_ir_item
                                     WHERE ir_id=? AND product_id=?
                                     ORDER BY id ASC");
  $incQtyOut = $pdo->prepare("UPDATE siin_ir_item SET qty_out = COALESCE(qty_out,0) + ? WHERE id=?");
  $setQtyOut = $pdo->prepare("UPDATE siin_ir_item SET qty_out = ? WHERE id=?");

  $pdo->beginTransaction();

  // ===== Update header + audit =====
  $pdo->prepare("UPDATE siin_ht SET ht_date=?, ir_no=?, emp_name=?, dept_name=?, descr=?, upddate=?, upduser=?
                 WHERE id=?")
      ->execute([
        $ht_date,
        $ir_no,
        trim($_POST['emp_name'] ?? ''),
        trim($_POST['dept_name'] ?? ''),
        trim($_POST['descr'] ?? ''),
        nowIso(),
        current_user(),
        $id
      ]);

  // ===== Rollback detail lama =====
  $stOld = $pdo->prepare("SELECT product_id, qty FROM siin_ht_item WHERE ht_id=?");
  $stOld->execute([$id]); $oldItems = $stOld->fetchAll(PDO::FETCH_ASSOC);

  foreach($oldItems as $oi){
    $pid=(int)$oi['product_id']; $q=(int)$oi['qty'];
    if($q>0) $stock_in($pid,$q,'HT_ROLLBACK',$id,"ROLLBACK HT $id");
  }
  $selLines = $pdo->prepare("SELECT id, COALESCE(qty_out,0) AS qty_out
                             FROM siin_ir_item WHERE ir_id=? AND product_id=? ORDER BY id ASC");
  foreach($oldItems as $oi){
    $pid=(int)$oi['product_id']; $need=(int)$oi['qty'];
    $selLines->execute([$ir_id,$pid]);
    foreach($selLines->fetchAll(PDO::FETCH_ASSOC) as $ln){
      if($need<=0) break;
      $used=(int)$ln['qty_out']; if($used<=0) continue;
      $dec=min($used,$need);
      $setQtyOut->execute([$used-$dec,(int)$ln['id']]);
      $need-=$dec;
    }
  }
  $pdo->prepare("DELETE FROM siin_ht_item WHERE ht_id=?")->execute([$id]);

  // ===== Tulis item baru =====
  $codes = $_POST['prod_code'] ?? [];   // di form ini berisi product_id
  $uoms  = $_POST['uom_id'] ?? [];
  $qtys  = $_POST['qty'] ?? [];
  $n = max(count($codes), count($uoms), count($qtys));

  $insItem = $pdo->prepare("INSERT INTO siin_ht_item (ht_id, product_id, qty, uom_id) VALUES (?,?,?,?)");
  $selProd = $pdo->prepare("SELECT prod_code, prod_name, COALESCE(uom_id, ?) AS uom_id FROM siin_product WHERE id=?");

  for($i=0;$i<$n;$i++){
    $qty = (int)($qtys[$i] ?? 0);
    if($qty<=0) continue;

    $pid = (int)($codes[$i] ?? 0);
    if($pid<=0) continue;

    $uom = ensure_uom($pdo, $uoms[$i] ?? null);

    $selProd->execute([$uom,$pid]);
    $p = $selProd->fetch(PDO::FETCH_ASSOC);
    $pcode = $p['prod_code'] ?? (string)$pid;
    $pname = $p['prod_name'] ?? '';
    $uom_final = $p ? (int)$p['uom_id'] : $uom;

    // validasi outstanding IR
    $selAvailSum->execute([$ir_id, $pid]);
    $avail = (int)$selAvailSum->fetchColumn();
    if($qty > $avail){
      throw new Exception("Qty melebihi outstanding IR untuk [$pcode - $pname]. Sisa: {$avail}");
    }

    // detail
    $insItem->execute([$id, $pid, $qty, $uom_final]);

    // stock keluar + ledger
    $stock_out($pid,$qty,'HT',$id,"HT $ir_no");

    // alokasi qty_out FIFO
    $need = $qty;
    $selLinesForAlloc->execute([$ir_id, $pid]);
    foreach($selLinesForAlloc->fetchAll(PDO::FETCH_ASSOC) as $ln){
      if($need<=0) break;
      $cap = (int)$ln['qty'] - (int)$ln['qty_out'];
      if($cap<=0) continue;
      $take = min($cap, $need);
      $incQtyOut->execute([$take, (int)$ln['id']]);
      $need -= $take;
    }
    if($need>0) throw new Exception("Internal allocation error untuk [$pcode - $pname].");
  }

  // ===== Refresh status IR =====
  $stChk = $pdo->prepare("SELECT SUM(CASE WHEN COALESCE(qty_out,0) < qty THEN 1 ELSE 0 END)
                          FROM siin_ir_item WHERE ir_id=?");
  $stChk->execute([$ir_id]);
  $newStatus = ((int)$stChk->fetchColumn() > 0) ? 'outstanding' : 'closed';
  $pdo->prepare("UPDATE siin_ir SET status=?, upddate=?, upduser=? WHERE id=?")
      ->execute([$newStatus, nowIso(), current_user(), $ir_id]);

  audit_log($pdo,'HT_UPDATE','siin_ht',$id,$_POST);
  $pdo->commit();
  echo json_encode(['success'=>true,'id'=>$id]);
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
