<?php
/**
 * gg_app/pages/si-iin/ht/ht_delete.php
 *
 * Hapus HT:
 * - Jika status HT = approved → rollback stok (product.stock + ledger) dan tarik mundur qty_out pada siin_ir_item.
 * - Setelah rollback, hitung ulang status IR (approved/outstanding/closed).
 * - Catat ke audit_log:
 *     a) Entri komprehensif (HT_DELETE)
 *     b) Entri per item yang di-rollback (HT_DELETE_ITEM)
 */
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../_shared/siin_lib.php'; // menyediakan $pdo, nowIso(), current_user(), audit_log()

header('Content-Type: application/json; charset=utf-8');

try {
  if (!isset($_SESSION['UserName'])) {
    throw new Exception('Sesi habis. Silakan login.');
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) throw new Exception('ID tidak valid');

  // Ambil header HT
  $stHdr = $pdo->prepare("
    SELECT id, ir_no, status, ht_date, emp_name, dept_name, no_grn
    FROM siin_ht WHERE id=?
  ");
  $stHdr->execute([$id]);
  $hdr = $stHdr->fetch(PDO::FETCH_ASSOC);
  if (!$hdr) throw new Exception('Data HT tidak ditemukan');

  // Cari IR ID
  $ir_id = 0;
  if (!empty($hdr['ir_no'])) {
    $stIR = $pdo->prepare("SELECT id FROM siin_ir WHERE request_no=?");
    $stIR->execute([$hdr['ir_no']]);
    $irRow = $stIR->fetch(PDO::FETCH_ASSOC);
    $ir_id = $irRow ? (int)$irRow['id'] : 0;
  }

  $pdo->beginTransaction();

  $wasApproved = (strtolower($hdr['status']) === 'approved');
  $rolledBackItems = []; // untuk audit ringkas & untuk loop per-item

  if ($wasApproved) {
    // Ambil detail HT
    $stItems = $pdo->prepare("SELECT id, product_id, qty FROM siin_ht_item WHERE ht_id=? ORDER BY id ASC");
    $stItems->execute([$id]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);

    // Helper stok
    $selStock  = $pdo->prepare("SELECT stock FROM siin_product WHERE id=?");
    $updStock  = $pdo->prepare("UPDATE siin_product SET stock=?, updated_time=?, update_by=? WHERE id=?");
    $insLedger = $pdo->prepare("
      INSERT INTO siin_stock_ledger
        (trx_time, product_id, qty_in, qty_out, balance_after, ref_type, ref_id, note, upduser)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stock_in = function(int $pid, float $qty, string $note) use ($selStock,$updStock,$insLedger,$id){
      $selStock->execute([$pid]);
      $bal = (float)$selStock->fetchColumn();
      $new = $bal + (float)$qty;
      $updStock->execute([$new, nowIso(), current_user(), $pid]);
      $insLedger->execute([nowIso(), $pid, (float)$qty, 0, $new, 'HT_DELETE', $id, $note, current_user()]);
    };

    // Helper IR qty_out (reverse FIFO)
    $selLines = $pdo->prepare("
      SELECT id, qty, COALESCE(qty_out,0) AS qty_out
      FROM siin_ir_item
      WHERE ir_id=? AND product_id=?
      ORDER BY id DESC
    ");
    $setQtyOut = $pdo->prepare("UPDATE siin_ir_item SET qty_out=? WHERE id=?");

    foreach ($items as $r) {
      $pid = (int)$r['product_id'];
      $qty = (float)$r['qty'];
      if ($pid <= 0 || $qty <= 0) continue;

      // Kembalikan stok fisik + ledger
      $stock_in($pid, $qty, "ROLLBACK HT ".$hdr['ir_no']);

      // Tarik mundur alokasi qty_out di IR (bila ada IR)
      $totalPulled = 0.0;
      if ($ir_id > 0) {
        $need = $qty;
        $selLines->execute([$ir_id, $pid]);
        foreach ($selLines->fetchAll(PDO::FETCH_ASSOC) as $ln) {
          if ($need <= 0) break;
          $used = (float)$ln['qty_out'];
          if ($used <= 0) continue;
          $dec = min($used, $need);
          $setQtyOut->execute([$used - $dec, (int)$ln['id']]);
          $need       -= $dec;
          $totalPulled += $dec;
        }
      }

      // simpan untuk audit
      $rolledBackItems[] = [
        'product_id'   => $pid,
        'qty_rollback' => $qty,
        'qty_out_pulled_back' => $ir_id > 0 ? $totalPulled : 0,
      ];

      // === AUDIT PER-ITEM ===
      audit_log(
        $pdo,
        'HT_DELETE_ITEM',
        'siin_ht',
        (int)$hdr['id'],
        [
          'product_id'   => $pid,
          'qty_rollback' => $qty,
          'ir_id'        => $ir_id,
          'ir_no'        => $hdr['ir_no'],
          'qty_out_pulled_back' => $ir_id > 0 ? $totalPulled : 0,
          'deleted_by'   => current_user(),
          'deleted_at'   => nowIso()
        ]
      );
    }
  }

  // Hapus detail & header
  $pdo->prepare("DELETE FROM siin_ht_item WHERE ht_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM siin_ht WHERE id=?")->execute([$id]);

  // Hitung ulang status IR (jika ada IR)
  $newStatus = null;
  if ($ir_id > 0) {
    $q = $pdo->prepare("
      SELECT
        SUM(CASE WHEN COALESCE(qty_out,0) > 0 THEN 1 ELSE 0 END) AS ada_out,
        SUM(CASE WHEN COALESCE(qty_out,0) < qty THEN 1 ELSE 0 END) AS masih_sisa
      FROM siin_ir_item
      WHERE ir_id=?
    ");
    $q->execute([$ir_id]);
    $v = $q->fetch(PDO::FETCH_ASSOC);

    if ((int)$v['ada_out'] === 0) {
      $newStatus = 'approved';
    } elseif ((int)$v['masih_sisa'] > 0) {
      $newStatus = 'outstanding';
    } else {
      $newStatus = 'closed';
    }

    $pdo->prepare("UPDATE siin_ir SET status=?, upddate=?, upduser=? WHERE id=?")
        ->execute([$newStatus, nowIso(), current_user(), $ir_id]);
  }

  // === AUDIT KOMPREHENSIF ===
  $auditPayload = [
    'ht_header_before_delete' => [
      'id'       => (int)$hdr['id'],
      'ir_no'    => $hdr['ir_no'],
      'status'   => $hdr['status'],
      'ht_date'  => $hdr['ht_date'],
      'emp_name' => $hdr['emp_name'],
      'dept'     => $hdr['dept_name'],
      'no_grn'   => $hdr['no_grn'],
    ],
    'was_approved'      => $wasApproved,
    'rolled_back_items' => $rolledBackItems,   // daftar item yang dipulihkan
    'ir_id'             => $ir_id,
    'ir_status_after'   => $newStatus,
    'deleted_at'        => nowIso(),
    'deleted_by'        => current_user(),
  ];
  audit_log($pdo, 'HT_DELETE', 'siin_ht', (int)$hdr['id'], $auditPayload);

  $pdo->commit();
  echo json_encode(['success' => true]);

} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
