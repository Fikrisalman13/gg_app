<?php
/** ht_save.php
 * CREATE / UPDATE HT
 * Catatan:
 * - TIDAK mengubah stok dan TIDAK mengalokasikan qty_out.
 * - Stok & qty_out baru diproses saat APPROVE (lihat ht_approve.php).
 */
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../_shared/siin_lib.php'; // $pdo, nowIso(), current_user(), dmY_to_Ymd()
header('Content-Type: application/json; charset=utf-8');

try{
  if(!isset($_SESSION['UserName'])) throw new Exception('Sesi habis. Silakan login.');

  // dukung input JSON
  $raw = file_get_contents('php://input');
  if ($raw) {
    $j = json_decode($raw, true);
    if (is_array($j)) $_POST = $j + $_POST;
  }

  $mode      = $_POST['mode'] ?? 'create';
  $id        = (int)($_POST['id'] ?? 0);

  $ht_date   = trim($_POST['ht_date'] ?? '');        // dd-mm-yyyy
  $ir_no     = trim($_POST['ir_no'] ?? '');          // request_no IR
  $emp_name  = trim($_POST['emp_name'] ?? '');
  $dept_name = trim($_POST['dept_name'] ?? '');
  $descr     = trim($_POST['descr'] ?? '');

  // Aset karyawan
  $emp_asset_id = isset($_POST['emp_asset_id']) && $_POST['emp_asset_id'] !== ''
    ? (int)$_POST['emp_asset_id'] : 0;              // ini id_asset
  $id_kode      = isset($_POST['id_kode']) && $_POST['id_kode'] !== ''
    ? (int)$_POST['id_kode'] : 0;                   // id_kode dari m_kode_asset (opsional)

  if($ht_date==='') throw new Exception('Tanggal wajib diisi');
  if($ir_no==='')   throw new Exception('Nomor IR wajib dipilih');

  $htYmd = dmY_to_Ymd($ht_date);

  // --- Ambil IR header ---
  $stIR = $pdo->prepare("SELECT id FROM siin_ir WHERE request_no = ?");
  $stIR->execute([$ir_no]);
  $IR = $stIR->fetch(PDO::FETCH_ASSOC);
  if(!$IR) throw new Exception('IR tidak ditemukan');
  $ir_id = (int)$IR['id'];

  // --- Items (tanpa sentuh stok/qty_out) ---
  $items = $_POST['items'] ?? [];
  if(!is_array($items) || !count($items)) throw new Exception('Item belum diisi');

  $pdo->beginTransaction();

  // Jika UPDATE dan user tidak kirim emp_asset_id/id_kode baru,
  // pertahankan nilai lama dari DB agar tidak menjadi 0.
  if ($mode === 'update') {
    if ($id <= 0) throw new Exception('ID HT tidak valid untuk update');

    $stOld = $pdo->prepare("SELECT id_asset, id_kode FROM siin_ht WHERE id = ?");
    $stOld->execute([$id]);
    $old = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new Exception('Data HT lama tidak ditemukan');

    if ($emp_asset_id <= 0 && isset($old['id_asset'])) {
      $emp_asset_id = (int)$old['id_asset'];
    }
    if ($id_kode <= 0 && isset($old['id_kode'])) {
      $id_kode = (int)$old['id_kode'];
    }
  }

  // Nol → jadikan NULL supaya aman di DB
  $emp_asset_id_db = $emp_asset_id > 0 ? $emp_asset_id : null;
  $id_kode_db      = $id_kode      > 0 ? $id_kode      : null;

  if($mode==='create'){
    // INSERT header HT (status OPEN, no_grn sementara NULL)
    $stH = $pdo->prepare("
      INSERT INTO siin_ht
        (ht_date, ir_no, emp_name, dept_name, descr, status,
         upddate, upduser, no_grn, id_asset, id_kode)
      VALUES
        (?, ?, ?, ?, ?, 'open',
         ?, ?, NULL, ?, ?)
    ");
    $stH->execute([
      $htYmd,
      $ir_no,
      $emp_name,
      $dept_name,
      $descr,
      nowIso(),
      current_user(),
      $emp_asset_id_db,
      $id_kode_db
    ]);

    $ht_id = (int)$pdo->lastInsertId();

    // format no_grn: GRN/mmYY/id  (contoh: GRN/1125/123)
    $mm = date('m', strtotime($htYmd));
    $yy = date('y', strtotime($htYmd));
    $no_grn = sprintf('GRN/%s%s/%d', $mm, $yy, $ht_id);

    $pdo->prepare("
      UPDATE siin_ht
         SET no_grn = ?, upddate = ?, upduser = ?
       WHERE id = ?
    ")->execute([$no_grn, nowIso(), current_user(), $ht_id]);

  } else {
    if($id<=0) throw new Exception('ID HT tidak valid untuk update');

    // UPDATE header HT (status tetap apa adanya; hanya OPEN yang boleh di-edit dari UI)
    $pdo->prepare("
      UPDATE siin_ht
         SET ht_date  = ?,
             ir_no    = ?,
             emp_name = ?,
             dept_name= ?,
             descr    = ?,
             id_asset = ?,
             id_kode  = ?,
             upddate  = ?,
             upduser  = ?
       WHERE id = ?
    ")->execute([
      $htYmd,
      $ir_no,
      $emp_name,
      $dept_name,
      $descr,
      $emp_asset_id_db,
      $id_kode_db,
      nowIso(),
      current_user(),
      $id
    ]);

    $ht_id = $id;

    // bersihkan detail lama (tidak perlu rollback stok, belum pernah dikurangi)
    $pdo->prepare("DELETE FROM siin_ht_item WHERE ht_id = ?")->execute([$ht_id]);
  }

  // --- simpan detail apa adanya ---
  $insItem = $pdo->prepare("
    INSERT INTO siin_ht_item (ht_id, product_id, qty, uom_id)
    VALUES (?, ?, ?, ?)
  ");
  foreach($items as $row){
    $pid_code = (int)($row['product_id_code'] ?? 0);
    $pid_name = (int)($row['product_id_name'] ?? 0);
    $product_id = $pid_code ?: $pid_name;

    $uom_id = isset($row['uom_id']) && $row['uom_id'] !== ''
      ? (int)$row['uom_id'] : null;

    $qty    = (float)($row['qty'] ?? 0);

    if($product_id <= 0 || $qty <= 0) continue;

    $insItem->execute([$ht_id, $product_id, $qty, $uom_id]);
  }

  $pdo->commit();
  echo json_encode(['success'=>true, 'id'=>$ht_id]);

}catch(Exception $e){
  if(isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
