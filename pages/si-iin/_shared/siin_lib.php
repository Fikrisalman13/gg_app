<?php
date_default_timezone_set('Asia/Jakarta');

// Gunakan koneksi pusat (koneksi.php). Jangan fallback ke SQLite.
$centralKon = realpath(__DIR__ . '/../../../koneksi.php');
if (!$centralKon || !file_exists($centralKon)){
  die("FATAL: koneksi pusat tidak ditemukan. Pastikan file koneksi.php ada di root proyek.\n");
}

// include koneksi pusat. koneksi.php seharusnya menyiapkan koneksi sqlsrv resource ($conn)
// atau PDO ($pdo or $pdo_mssql). Kami mengharapkan sebuah PDO instance $pdo tersedia.
require_once $centralKon;

// Jika koneksi PDO tidak tersedia, coba buat dari opsi yang mungkin ada di koneksi.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
  // jika koneksi.php menyimpan $connectionOptions dan $serverName, coba buat PDO
  if (
    isset($serverName) &&
    isset($connectionOptions) &&
    isset($connectionOptions['Database']) &&
    isset($connectionOptions['Uid']) &&
    isset($connectionOptions['PWD'])
  ) {
    try {
      /**
       * CATATAN:
       * - Encrypt=no;TrustServerCertificate=1 dipakai agar tidak error SSL
       *   "certificate chain was issued by an authority that is not trusted"
       *   di lingkungan lokal / dev.
       *
       * Untuk production yang aman:
       *   - pasang sertifikat yang valid di SQL Server
       *   - import CA ke client
       *   - lalu ubah DSN jadi:
       *     Encrypt=yes;TrustServerCertificate=0
       */
      $pdo_dsn = sprintf(
        "sqlsrv:Server=%s;Database=%s;Encrypt=no;TrustServerCertificate=1",
        $serverName,
        $connectionOptions['Database']
      );

      $pdo = new PDO($pdo_dsn, $connectionOptions['Uid'], $connectionOptions['PWD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      die("FATAL: Gagal membuat PDO dari koneksi pusat: " . $e->getMessage());
    }
  } else {
    die("FATAL: koneksi PDO tidak tersedia. Pastikan koneksi.php menyediakan PDO atau PDO driver pdo_sqlsrv terinstall.\n");
  }
}

require_once __DIR__ . '/ensure_dirs.php';

function nowIso(){ return date('Y-m-d H:i:s'); }

function dmY_to_Ymd($d){
  if(!$d) return null;
  $p = explode('-', $d);
  if(count($p)==3) return sprintf('%04d-%02d-%02d', $p[2], $p[1], $p[0]);
  return $d;
}

function Ymd_to_dmY($d){
  if(!$d) return '';
  if ($d instanceof DateTimeInterface) {
    return $d->format('d-m-Y');
  }
  $s = substr((string)$d,0,10);
  $p = explode('-', $s);
  if(count($p)==3) return sprintf('%02d-%02d-%04d', $p[2], $p[1], $p[0]);
  return (string)$d;
}

function clean_int($v){ return max(0,(int)$v); }
function current_user(){ return $_SESSION['UserName'] ?? 'system'; }

function get_product_stock(PDO $pdo, int $productId): float {
  $st = $pdo->prepare("SELECT COALESCE(stock,0) FROM siin_product WHERE id=?");
  $st->execute([$productId]);
  $val = $st->fetchColumn();
  return ($val === false || $val === null) ? 0.0 : (float)$val;
}

function get_open_ht_reserved_by_product(PDO $pdo, string $irNo, int $excludeHtId = 0): array {
  $irNo = trim($irNo);
  if ($irNo === '') return [];

  $sql = "
    SELECT i.product_id, COALESCE(SUM(i.qty), 0) AS reserved_qty
    FROM siin_ht h
    JOIN siin_ht_item i ON i.ht_id = h.id
    WHERE h.ir_no = ?
      AND LOWER(COALESCE(h.status, '')) = 'open'
  ";
  $params = [$irNo];

  if ($excludeHtId > 0) {
    $sql .= " AND h.id <> ? ";
    $params[] = $excludeHtId;
  }

  $sql .= " GROUP BY i.product_id ";

  $st = $pdo->prepare($sql);
  $st->execute($params);

  $map = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pid = (int)($row['product_id'] ?? 0);
    if ($pid <= 0) continue;
    $map[$pid] = (float)($row['reserved_qty'] ?? 0);
  }
  return $map;
}

function assert_stock_available(PDO $pdo, int $productId, float $qty, string $context=''): float {
  $stock = get_product_stock($pdo, $productId);
  if ($qty > $stock) {
    $msg = 'Stok tidak cukup';
    if ($context !== '') {
      $msg .= ' untuk ' . $context;
    }
    $msg .= '. Stok tersedia: ' . rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.');
    $msg .= ', kebutuhan: ' . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    throw new Exception($msg);
  }
  return $stock;
}

/** AUDIT */
function audit_log(PDO $pdo, $action, $ref_type, $ref_id, $payloadArr = []){
  $stmt=$pdo->prepare("INSERT INTO siin_audit_log(event_time,actor,action,ref_type,ref_id,payload_json)
                       VALUES(?,?,?,?,?,?)");
  $stmt->execute([
    nowIso(),
    current_user(),
    $action,
    $ref_type,
    $ref_id,
    json_encode($payloadArr, JSON_UNESCAPED_UNICODE)
  ]);
}

/** LEDGER + STOCK */
function stock_move(PDO $pdo, $product_id, $qty_in, $qty_out, $ref_type, $ref_id, $note=''){
  $cur = get_product_stock($pdo, (int)$product_id);
  $new = $cur + (float)$qty_in - (float)$qty_out;
  if($new < 0){
    throw new Exception('Stok tidak cukup untuk transaksi ' . $ref_type . ' pada product_id=' . (int)$product_id);
  }
  $pdo->prepare("UPDATE siin_product SET stock=?, updated_time=?, update_by=? WHERE id=?")
      ->execute([$new, nowIso(), current_user(), (int)$product_id]);
  $pdo->prepare("INSERT INTO siin_stock_ledger(trx_time,product_id,qty_in,qty_out,balance_after,ref_type,ref_id,note,upduser)
                 VALUES(?,?,?,?,?,?,?,?,?)")
      ->execute([
        nowIso(),
        (int)$product_id,
        (float)$qty_in,
        (float)$qty_out,
        $new,
        $ref_type,
        (int)$ref_id,
        $note,
        current_user()
      ]);
}

/** UOM & Product helpers untuk Select2 (tagging) */
function ensure_uom(PDO $pdo, $val){
  if($val===null || $val==='') return null;
  if(ctype_digit((string)$val)){
    $ok = $pdo->query("SELECT id FROM siin_uom WHERE id=".(int)$val)->fetchColumn();
    if($ok) return (int)$val;
  }
  $st=$pdo->prepare("SELECT id FROM siin_uom WHERE uomname=?"); 
  $st->execute([$val]);
  $id=$st->fetchColumn();
  if($id) return (int)$id;
  $pdo->prepare("INSERT INTO siin_uom(uomname) VALUES(?)")->execute([$val]);
  return (int)$pdo->lastInsertId();
}

function ensure_product(PDO $pdo, $prod){
  if(!$prod) return null;
  if(isset($prod['id']) && ctype_digit((string)$prod['id'])){
    $ok=$pdo->query("SELECT id FROM siin_product WHERE id=".(int)$prod['id'])->fetchColumn();
    if($ok) return (int)$prod['id'];
  }
  if(!empty($prod['code'])){
    $st=$pdo->prepare("SELECT id FROM siin_product WHERE prod_code=?");
    $st->execute([$prod['code']]);
    $id=$st->fetchColumn(); 
    if($id) return (int)$id;
  }
  if(!empty($prod['name'])){
    $st=$pdo->prepare("SELECT id FROM siin_product WHERE prod_name=?");
    $st->execute([$prod['name']]);
    $id=$st->fetchColumn(); 
    if($id) return (int)$id;
  }
  $code   = $prod['code'] ?? null;
  $name   = $prod['name'] ?? ($prod['code'] ?? 'produk-baru');
  $uom_id = isset($prod['uom_id']) ? ensure_uom($pdo, $prod['uom_id']) : null;
  $now = nowIso();
  $user = current_user();
  $pdo->prepare("
    INSERT INTO siin_product(prod_code,prod_name,uom_id,stock,created_at,created_by,updated_time,update_by)
    VALUES(?,?,?,?,?,?,?,?)
  ")->execute([$code,$name,$uom_id,0,$now,$user,$now,$user]);
  return (int)$pdo->lastInsertId();
}

/** Recalc status IR (Approved/outstanding/closed) berdasar HT Approved */
function recalc_ir_status(PDO $pdo, $ir_no){
  $st=$pdo->prepare("SELECT id,status FROM siin_ir WHERE request_no=?");
  $st->execute([$ir_no]); 
  $hdr=$st->fetch();
  if(!$hdr) return;

  $it=$pdo->prepare("SELECT product_id, qty FROM siin_ir_item WHERE ir_id=?");
  $it->execute([$hdr['id']]);
  $need=[];
  foreach($it as $r){
    $need[(int)$r['product_id']] = ($need[(int)$r['product_id']]??0) + (int)$r['qty'];
  }
  if(!$need){
    $pdo->prepare("UPDATE siin_ir SET status='closed', upddate=?, upduser=? WHERE id=?")
        ->execute([nowIso(), current_user(), $hdr['id']]);
    return;
  }

  $sum=$pdo->prepare("
    SELECT hti.product_id, COALESCE(SUM(hti.qty),0) s
    FROM siin_ht ht
    JOIN siin_ht_item hti ON hti.ht_id=ht.id
    WHERE ht.ir_no=? AND LOWER(ht.status)='approved'
    GROUP BY hti.product_id
  ");
  $sum->execute([$ir_no]);
  $take=[];
  foreach($sum as $r){
    $take[(int)$r['product_id']] = (int)$r['s'];
  }

  $allMet=true; 
  $anyTake=false;
  foreach($need as $pid=>$qtyNeed){
    $got = $take[$pid] ?? 0;
    if($got>0) $anyTake=true;
    if($got < $qtyNeed) $allMet=false;
  }
  $new = $allMet ? 'closed' : ($anyTake ? 'outstanding' : 'Approved');
  if($new !== $hdr['status']){
    $pdo->prepare("UPDATE siin_ir SET status=?, upddate=?, upduser=? WHERE id=?")
        ->execute([$new, nowIso(), current_user(), $hdr['id']]);
  }
}

/** Simpan file e-sign (dataURL png), validasi <1MB */
function save_esign_png($dataUrl, $htId){
  if(strpos($dataUrl,'data:image/png;base64,')!==0){
    throw new Exception('Format tanda tangan tidak valid');
  }
  $raw = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')));
  if($raw===false) throw new Exception('Gagal decode tanda tangan');
  if(strlen($raw) > 1024*1024) throw new Exception('Ukuran tanda tangan > 1MB');

  $root = realpath(__DIR__ . '/../../../..'); // gg_app
  $dir  = $root . '/storage/esign';
  if(!is_dir($dir)) @mkdir($dir,0755,true);
  $fname = sprintf('HT-%d-%s.png', (int)$htId, date('Ymd_His'));
  $path  = $dir . '/' . $fname;

  if(file_put_contents($path, $raw)===false){
    throw new Exception('Gagal menyimpan file tanda tangan');
  }
  // Kembalikan relative path dari /gg_app
  return 'storage/esign/' . $fname;
}
