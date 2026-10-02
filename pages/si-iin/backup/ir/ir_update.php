<?php
// ir_update.php
session_start();
require_once __DIR__ . '/../_shared/siin_lib.php';

header('Content-Type: application/json');

try {
  $pdo->beginTransaction();

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    throw new Exception('ID tidak valid');
  }

  // ambil header lama
  $H = $pdo->prepare("SELECT * FROM siin_ir WHERE id=?");
  $H->execute([$id]);
  $H = $H->fetch();
  if (!$H) {
    throw new Exception('IR tidak ditemukan');
  }

  $ir_date = dmY_to_Ymd($_POST['ir_date'] ?? $H['ir_date']);
  $reqno   = trim($_POST['request_no'] ?? $H['request_no']);
  $descr   = trim($_POST['descr'] ?? '');

  if ($reqno === '') {
    throw new Exception('Request No wajib diisi');
  }

  // unik request_no (kecuali baris ini sendiri)
  $u = $pdo->prepare("SELECT COUNT(*) FROM siin_ir WHERE request_no=? AND id<>?");
  $u->execute([$reqno, $id]);
  if ($u->fetchColumn() > 0) {
    throw new Exception('Request No dipakai dok lain');
  }

  // Map item lama (untuk delta stok)
  $old = [];
  $rs = $pdo->prepare("SELECT product_id, qty FROM siin_ir_item WHERE ir_id=?");
  $rs->execute([$id]);
  foreach ($rs as $r) {
    $pid = (int)$r['product_id'];
    $old[$pid] = ($old[$pid] ?? 0) + (int)$r['qty'];
  }

  // Hapus item lama
  $pdo->prepare("DELETE FROM siin_ir_item WHERE ir_id=?")->execute([$id]);

  // Update header
  $pdo->prepare("UPDATE siin_ir
                 SET ir_date=?, request_no=?, descr=?, upddate=?, upduser=?
                 WHERE id=?")
      ->execute([$ir_date, $reqno, $descr, nowIso(), current_user(), $id]);

  // Item baru
  $items = $_POST['items'] ?? [];
  if (!$items) {
    throw new Exception('Minimal 1 item');
  }

  // Insert item dengan kolom tambahan PP/PO/GRN
  $insItem = $pdo->prepare("
    INSERT INTO siin_ir_item(
      ir_id, product_id, qty, qty_out, uom_id, note,
      ppnmbr, ppdate, ponmbr, podate, grnnmbr, grndate
    )
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  $new = [];
  foreach ($items as $it) {
    $qty = clean_int($it['qty'] ?? 0);
    if ($qty <= 0) {
      continue;
    }

    $rawCode = trim($it['prod_code'] ?? '');
    $rawName = trim($it['prod_name'] ?? '');
    $uom_id  = ensure_uom($pdo, $it['uom_id'] ?? null);
    $note    = trim($it['note'] ?? '');

    // data PP/PO/GRN per item (dari hidden input di baris item)
    $ppnmbr  = trim($it['ppnmbr']  ?? '');
    $ppdate  = trim($it['ppdate']  ?? '') ?: null;  // diharapkan sudah YYYY-MM-DD
    $ponmbr  = trim($it['ponmbr']  ?? '');
    $podate  = trim($it['podate']  ?? '') ?: null;
    $grnnmbr = trim($it['grnnmbr'] ?? '');
    $grndate = trim($it['grndate'] ?? '') ?: null;

    // Pastikan product ID
    $pid = ensure_product($pdo, [
      'id'     => ctype_digit($rawCode) ? (int)$rawCode : (ctype_digit($rawName) ? (int)$rawName : null),
      'code'   => ctype_digit($rawCode) ? null : ($rawCode ?: null),
      'name'   => ctype_digit($rawName) ? null : ($rawName ?: null),
      'uom_id' => $uom_id,
    ]);
    if (!$pid) {
      throw new Exception('Produk tidak valid pada salah satu item');
    }

    // Simpan item baru, qty_out = 0 (sama seperti ir_save.php)
    $insItem->execute([
      $id,
      $pid,
      $qty,
      0,        // qty_out
      $uom_id,
      $note,
      $ppnmbr,
      $ppdate,
      $ponmbr,
      $podate,
      $grnnmbr,
      $grndate
    ]);

    // kumpulkan qty baru per product untuk delta stok
    $new[$pid] = ($new[$pid] ?? 0) + $qty;
  }

  // Delta stok (menyesuaikan ledger IR)
  $all = array_unique(array_merge(array_keys($old), array_keys($new)));
  foreach ($all as $pid) {
    $diff = ($new[$pid] ?? 0) - ($old[$pid] ?? 0);
    if ($diff > 0) {
      if (function_exists('stock_move')) {
        stock_move($pdo, $pid, $diff, 0, 'IR', $id, "ADJ IR $reqno");
      }
    } elseif ($diff < 0) {
      if (function_exists('stock_move')) {
        stock_move($pdo, $pid, 0, abs($diff), 'IR', $id, "ADJ IR $reqno");
      }
    }
  }

  audit_log($pdo, 'IR_UPDATE', 'siin_ir', $id, $_POST);
  $pdo->commit();

  echo json_encode(['success' => true, 'id' => $id]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
