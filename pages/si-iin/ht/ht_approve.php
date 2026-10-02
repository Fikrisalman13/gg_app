<?php
// Approve HT: simpan e-signature + KURANGI stok & alokasikan qty_out IR (FIFO)
// + insert 2 record ke asset_history.
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../_shared/siin_lib.php'; // $pdo, nowIso(), current_user()
header('Content-Type: application/json; charset=utf-8');

try {
  if (!isset($_SESSION['UserName'])) {
    throw new Exception('Sesi habis. Silakan login.');
  }

  $id         = (int)($_POST['id'] ?? 0);
  $sign_name  = trim($_POST['sign_name'] ?? '');
  $imgDataUrl = $_POST['sign_image_data'] ?? '';

  if ($id <= 0)          throw new Exception('ID tidak valid');
  if ($sign_name === '') throw new Exception('Nama penanda tangan wajib diisi');

  // --- muat header HT (sekaligus ambil id_asset & descr utk asset_history / note) ---
  $H = $pdo->prepare("SELECT id, ir_no, status, id_asset, descr FROM siin_ht WHERE id = ?");
  $H->execute([$id]);
  $hdr = $H->fetch(PDO::FETCH_ASSOC);
  if (!$hdr) {
    throw new Exception('HT tidak ditemukan');
  }
  if (strtolower($hdr['status']) === 'approved') {
    throw new Exception('Dokumen sudah Approved.');
  }

  $ir_no    = $hdr['ir_no'];
  $id_asset = isset($hdr['id_asset']) ? (int)$hdr['id_asset'] : 0;
  $descr    = trim((string)($hdr['descr'] ?? ''));

  // --- muat detail HT (sekalian prod_name utk keperluan note history) ---
  $it = $pdo->prepare("
    SELECT i.product_id, i.qty, p.prod_name
    FROM   siin_ht_item i
    JOIN   siin_product p ON p.id = i.product_id
    WHERE  i.ht_id = ?
    ORDER  BY i.id ASC
  ");
  $it->execute([$id]);
  $items = $it->fetchAll(PDO::FETCH_ASSOC);
  if (!$items) {
    throw new Exception('Tidak ada item HT.');
  }

  // --- resolve IR id dari ir_no ---
  $stIR = $pdo->prepare("SELECT id FROM siin_ir WHERE request_no = ?");
  $stIR->execute([$ir_no]);
  $ir = $stIR->fetch(PDO::FETCH_ASSOC);
  if (!$ir) {
    throw new Exception('IR tidak ditemukan.');
  }
  $ir_id = (int)$ir['id'];

  // --- helper stok & ledger ---
  $selStock  = $pdo->prepare("SELECT stock FROM siin_product WHERE id = ?");
  $updStock  = $pdo->prepare("UPDATE siin_product SET stock = ?, updated_time = ?, update_by = ? WHERE id = ?");
  $insLedger = $pdo->prepare("
    INSERT INTO siin_stock_ledger
      (trx_time, product_id, qty_in, qty_out, balance_after, ref_type, ref_id, note, upduser)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stock_out = function(int $pid, float $qty, string $note) use ($pdo, $updStock, $insLedger, $id) {
    $bal = assert_stock_available($pdo, $pid, $qty, $note);
    $new = $bal - (float)$qty;

    $updStock->execute([$new, nowIso(), current_user(), $pid]);
    $insLedger->execute([
      nowIso(),   // trx_time
      $pid,       // product_id
      0,          // qty_in
      $qty,       // qty_out
      $new,       // balance_after
      'HT',       // ref_type
      $id,        // ref_id
      $note,      // note
      current_user()
    ]);
  };

  // --- helper FIFO allocation qty_out di tabel siin_ir_item ---
  $selLinesForAlloc = $pdo->prepare("
    SELECT id, qty, COALESCE(qty_out, 0) AS qty_out
    FROM   siin_ir_item
    WHERE  ir_id = ? AND product_id = ?
    ORDER  BY id ASC
  ");
  $incQtyOut = $pdo->prepare("
    UPDATE siin_ir_item
       SET qty_out = COALESCE(qty_out, 0) + ?
     WHERE id = ?
  ");

  // --- simpan e-sign (file png) ---
  $imgPath = null;
  if (strpos($imgDataUrl, 'data:image') === 0) {
    $sub = substr($imgDataUrl, strpos($imgDataUrl, ',') + 1);
    $bin = base64_decode($sub);
    if ($bin !== false) {
      $dir = realpath(__DIR__ . '/esign') ?: (__DIR__ . '/esign');
      if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
      }
      $imgPath = $dir . '/ht_' . $id . '.png';
      file_put_contents($imgPath, $bin);
    }
  }

  $pdo->beginTransaction();

  // --- proses stok & alokasi qty_out IR per item ---
  foreach ($items as $r) {
    $pid = (int)$r['product_id'];
    $qty = (float)$r['qty'];
    if ($pid <= 0 || $qty <= 0) {
      continue;
    }

    // stok keluar
    $stock_out($pid, $qty, "HT " . $ir_no);

    // FIFO qty_out
    $need = $qty;
    $selLinesForAlloc->execute([$ir_id, $pid]);
    foreach ($selLinesForAlloc->fetchAll(PDO::FETCH_ASSOC) as $ln) {
      if ($need <= 0) break;
      $cap = (float)$ln['qty'] - (float)$ln['qty_out'];
      if ($cap <= 0) continue;

      $take = min($cap, $need);
      $incQtyOut->execute([$take, (int)$ln['id']]);
      $need -= $take;
    }

    if ($need > 0) {
      throw new Exception("Qty IR tidak cukup untuk product_id = $pid");
    }
  }

  // --- refresh status IR (outstanding / closed) ---
  $stChk = $pdo->prepare("
    SELECT SUM(
             CASE WHEN COALESCE(qty_out, 0) < qty THEN 1 ELSE 0 END
           )
    FROM   siin_ir_item
    WHERE  ir_id = ?
  ");
  $stChk->execute([$ir_id]);
  $irStatus = ((int)$stChk->fetchColumn() > 0) ? 'outstanding' : 'closed';

  $pdo->prepare("
    UPDATE siin_ir
       SET status = ?, upddate = ?, upduser = ?
     WHERE id = ?
  ")->execute([$irStatus, nowIso(), current_user(), $ir_id]);

  // --- update header HT menjadi Approved ---
  $pdo->prepare("
    UPDATE siin_ht
       SET status = 'Approved',
           sign_name = ?,
           sign_image_path = ?,
           upddate = ?,
           upduser = ?
     WHERE id = ?
  ")->execute([$sign_name, $imgPath, nowIso(), current_user(), $id]);

  // --- INSERT ke asset_history (2 record) ---
  // id_asset: sekarang pakai kolom id_asset (BUKAN id_kode)
  if ($id_asset > 0) {
    // gabung nama produk untuk note
    $prodNames = [];
    foreach ($items as $r) {
      if (!empty($r['prod_name'])) {
        $prodNames[] = $r['prod_name'];
      }
    }
    $prodNames = array_unique($prodNames);
    $prodNamesStr = implode(', ', $prodNames);

    // note: Maintenance menggunakan sparepart dengan ir_no - prodNames - descr
    $noteHistory = 'Maintenance menggunakan sparepart dengan ' . $ir_no . ' - ' . $prodNamesStr;
    if ($descr !== '') {
      $noteHistory .= ' - ' . $descr;
    }

    $insHist = $pdo->prepare("
      INSERT INTO asset_history
        (id_asset, old_status, new_status, note, created_by, created_at, jenis_perubahan)
      VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

    $now  = nowIso();
    $user = current_user();

    // record 1 : use -> maintenance
    $insHist->execute([
      $id_asset,
      'Used',
      'Maintenance',
      $noteHistory,
      $user,
      $now,
      'status asset'
    ]);

    // record 2 : maintenance -> use
    $insHist->execute([
      $id_asset,
      'Maintenance',
      'Used',
      $noteHistory,
      $user,
      $now,
      'status asset'
    ]);
  }

  // audit log
  audit_log($pdo, 'HT_APPROVE', 'siin_ht', $id, [
    'signer' => $sign_name,
    'esign'  => $imgPath
  ]);

  $pdo->commit();

  echo json_encode([
    'success'    => true,
    'id'         => $id,
    'ir_status'  => $irStatus
  ]);

} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
