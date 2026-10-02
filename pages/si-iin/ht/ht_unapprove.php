<?php
// Unapprove HT: kembalikan stok & alokasi IR (qty_out) secara FIFO, buka kembali dokumen.
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../_shared/siin_lib.php'; // harus expose $pdo, nowIso(), current_user()

header('Content-Type: application/json; charset=utf-8');

try {
  if (!isset($_SESSION['UserName'])) throw new Exception('Sesi habis. Silakan login.');
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) throw new Exception('ID tidak valid');

  // --- Ambil header HT (cek status & IR) ---
  $stH = $pdo->prepare("SELECT id, ir_no, status, sign_name, sign_image_path FROM siin_ht WHERE id=?");
  $stH->execute([$id]);
  $H = $stH->fetch(PDO::FETCH_ASSOC);
  if (!$H) throw new Exception('Dokumen HT tidak ditemukan');

  $curStatus = strtolower((string)$H['status']);
  if ($curStatus !== 'approved') {
    // Aman saja mengizinkan unapprove dari status non-approved, tapi tidak ada yang perlu direstore.
    // Supaya eksplisit:
    throw new Exception('Dokumen belum berstatus Approved.');
  }

  $ir_no = trim((string)$H['ir_no']);
  if ($ir_no === '') throw new Exception('IR pada HT kosong.');

  // --- Ambil id IR untuk IR_NO ini ---
  $stIR = $pdo->prepare("SELECT id FROM siin_ir WHERE request_no=?");
  $stIR->execute([$ir_no]);
  $rowIR = $stIR->fetch(PDO::FETCH_ASSOC);
  if (!$rowIR) throw new Exception('Header IR tidak ditemukan.');
  $ir_id = (int)$rowIR['id'];

  // --- Ambil detail item HT ---
  $stItems = $pdo->prepare("SELECT product_id, qty FROM siin_ht_item WHERE ht_id=? ORDER BY id ASC");
  $stItems->execute([$id]);
  $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
  if (!$items) throw new Exception('Detail HT kosong, tidak ada yang di-unapprove.');

  // --- Siapkan helper stok (siin_product.stock + ledger) ---
  $selStock  = $pdo->prepare("SELECT stock FROM siin_product WHERE id=?");
  $updStock  = $pdo->prepare("UPDATE siin_product SET stock=?, updated_time=?, update_by=? WHERE id=?");
  $insLedger = $pdo->prepare(
    "INSERT INTO siin_stock_ledger (trx_time, product_id, qty_in, qty_out, balance_after, ref_type, ref_id, note, upduser)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );

  $stock_in = function(int $pid, float $qty, string $refType, int $refId, string $note)
              use ($selStock,$updStock,$insLedger) {
    $selStock->execute([$pid]);
    $bal = (float)$selStock->fetchColumn();
    if ($bal === false || $bal === null) $bal = 0;
    $new = $bal + (float)$qty;
    $updStock->execute([$new, nowIso(), current_user(), $pid]);
    $insLedger->execute([nowIso(), $pid, (float)$qty, 0, $new, $refType, $refId, $note, current_user()]);
  };

  // --- Siapkan helper rollback qty_out IR (FIFO) ---
  $selLines = $pdo->prepare(
    "SELECT id, qty, COALESCE(qty_out,0) AS qty_out
     FROM siin_ir_item
     WHERE ir_id=? AND product_id=?
     ORDER BY id ASC"
  );
  $setQtyOut = $pdo->prepare("UPDATE siin_ir_item SET qty_out=? WHERE id=?");

  // --- Mulai transaksi ---
  $pdo->beginTransaction();

  // 1) Kembalikan stok & rollback qty_out per item
  foreach ($items as $it) {
    $pid = (int)$it['product_id'];
    $qty = (float)$it['qty'];
    if ($pid <= 0 || $qty <= 0) continue;

    // a) Stok kembali masuk (reverse dari approve)
    $stock_in($pid, $qty, 'HT_UNAPPROVE', $id, "UNAPPROVE HT $ir_no");

    // b) Kurangi kembali alokasi qty_out di IR secara FIFO (kiri ke kanan)
    $need = $qty;
    $selLines->execute([$ir_id, $pid]);
    foreach ($selLines->fetchAll(PDO::FETCH_ASSOC) as $ln) {
      if ($need <= 0) break;
      $lineId = (int)$ln['id'];
      $used   = (float)$ln['qty_out'];
      if ($used <= 0) continue;

      $dec = min($used, $need);
      $setQtyOut->execute([$used - $dec, $lineId]);
      $need -= $dec;
    }
    // Jika $need > 0 di sini, artinya ada inkonsistensi data (lebih banyak yg mau direstore
    // dibanding yang pernah dialokasikan). Kita biarkan saja; stok sudah kembali, IR qty_out
    // tidak bisa negatif by query di atas.
  }

  // 2) Buka status HT & kosongkan tanda tangan
  $stUpdHT = $pdo->prepare(
    "UPDATE siin_ht
     SET status='open', sign_name=NULL, sign_image_path=NULL, upddate=?, upduser=?
     WHERE id=?"
  );
  $stUpdHT->execute([nowIso(), current_user(), $id]);

  // 3) Rehitung status IR: bila semua qty_out=0 => Approved, selain itu => outstanding
  $stCheckAnyOutstanding = $pdo->prepare(
    "SELECT SUM(CASE WHEN COALESCE(qty_out,0) < qty THEN 1 ELSE 0 END)
     FROM siin_ir_item WHERE ir_id=?"
  );
  $stCheckAnyOutstanding->execute([$ir_id]);
  $hasOutstandingCnt = (int)$stCheckAnyOutstanding->fetchColumn();

  $newIRStatus = $hasOutstandingCnt > 0 ? 'outstanding' : 'Approved';
  $stUpdIR = $pdo->prepare("UPDATE siin_ir SET status=?, upddate=?, upduser=? WHERE id=?");
  $stUpdIR->execute([$newIRStatus, nowIso(), current_user(), $ir_id]);

  // Audit
  audit_log($pdo, 'HT_UNAPPROVE', 'siin_ht', $id, [
    'ir_no' => $ir_no,
    'ir_status_after' => $newIRStatus
  ]);

  $pdo->commit();
  echo json_encode(['success'=>true, 'id'=>$id, 'ir_status'=>$newIRStatus]);
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
