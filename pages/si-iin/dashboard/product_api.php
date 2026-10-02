<?php
// gg_app/pages/si-iin/product_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../_shared/siin_lib.php';

$action = $_REQUEST['action'] ?? 'options';

function json_error($msg, $code=400){
  http_response_code($code);
  echo json_encode(['error'=>$msg]); exit;
}

// helper: ensure category/uom exists when "new" provided
function ensure_category_id(PDO $pdo, $category_id, $category_new){
  if($category_id) return (int)$category_id;
  $nm = trim((string)$category_new);
  if($nm==='') return null;
  $st = $pdo->prepare("INSERT INTO siin_category(name) VALUES(?)");
  $st->execute([$nm]);
  return (int)$pdo->lastInsertId();
}
function ensure_uom_id(PDO $pdo, $uom_id, $uom_new){
  if($uom_id) return (int)$uom_id;
  $nm = strtoupper(trim((string)$uom_new));
  if($nm==='') return null;
  $st = $pdo->prepare("INSERT INTO siin_uom(uomname) VALUES(?)");
  $st->execute([$nm]);
  return (int)$pdo->lastInsertId();
}

try{
  if($action==='options'){
    $cats = $pdo->query("SELECT id, name FROM siin_category ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $uoms = $pdo->query("SELECT id, uomname FROM siin_uom ORDER BY uomname ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['categories'=>$cats, 'uoms'=>$uoms]); exit;
  }

  if($action==='get'){
    $id = (int)($_GET['id'] ?? 0);
    if($id<=0) json_error('ID tidak valid');
    $st = $pdo->prepare("SELECT id, prod_code, prod_name, category_id, uom_id FROM siin_product WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if(!$row) json_error('Produk tidak ditemukan',404);
    echo json_encode($row); exit;
  }

  if($action==='create' || $action==='update'){
    $prod_code = trim($_POST['prod_code'] ?? '');
    $prod_name = trim($_POST['prod_name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $category_new = trim($_POST['category_new'] ?? '');
    $uom_id = trim($_POST['uom_id'] ?? '');
    $uom_new = trim($_POST['uom_new'] ?? '');

    if($prod_code==='') json_error('Kode wajib diisi');
    if($prod_name==='') json_error('Nama wajib diisi');

    // transaksi agar atomic (saat membuat kategori/uom baru)
    $pdo->beginTransaction();

    // buat kategori/uom bila perlu
    $cid = ensure_category_id($pdo, $category_id, $category_new);
    $uid = ensure_uom_id($pdo, $uom_id, $uom_new);

    if($action==='create'){
      // unique check prod_code
      $st = $pdo->prepare("SELECT COUNT(*) FROM siin_product WHERE prod_code=?");
      $st->execute([$prod_code]);
      if((int)$st->fetchColumn() > 0){
        $pdo->rollBack();
        json_error('Kode produk sudah digunakan');
      }
      $now = nowIso();
      $user = current_user();
      $ins = $pdo->prepare("
        INSERT INTO siin_product(
          prod_code, prod_name, category_id, uom_id, stock,
          created_at, created_by, updated_time, update_by
        )
        VALUES(?,?,?,?,0,?,?,?,?)
      ");
      $ins->execute([$prod_code, $prod_name, $cid, $uid, $now, $user, $now, $user]);
      $newId = (int)$pdo->lastInsertId();
      $pdo->commit();
      echo json_encode(['ok'=>true,'id'=>$newId]); exit;
    } else {
      $id = (int)($_POST['id'] ?? 0);
      if($id<=0){ $pdo->rollBack(); json_error('ID tidak valid'); }
      // unique check prod_code except self
      $st = $pdo->prepare("SELECT COUNT(*) FROM siin_product WHERE prod_code=? AND id<>?");
      $st->execute([$prod_code, $id]);
      if((int)$st->fetchColumn() > 0){
        $pdo->rollBack();
        json_error('Kode produk sudah digunakan oleh item lain');
      }
      $upd = $pdo->prepare("UPDATE siin_product
                            SET prod_code=?, prod_name=?, category_id=?, uom_id=?,
                                updated_time=?, update_by=?
                            WHERE id=?");
      $upd->execute([$prod_code, $prod_name, $cid, $uid, nowIso(), current_user(), $id]);
      $pdo->commit();
      echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
  }

  json_error('Aksi tidak dikenali',400);

} catch(Exception $e){
  if($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  json_error($e->getMessage(),500);
}
