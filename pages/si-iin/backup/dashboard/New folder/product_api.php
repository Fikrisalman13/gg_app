<?php
// gg_app/pages/si-iin/product_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../koneksi.php';      // harus menyediakan $pdo (PDO instance)
require_once __DIR__ . '/../_shared/siin_lib.php';

$action = $_REQUEST['action'] ?? 'options';

function json_exit($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error($msg, $code = 400) {
    json_exit(['ok' => false, 'error' => $msg], $code);
}

/**
 * Helpers untuk memastikan category / uom dibuat bila input "new" diberikan
 */
function ensure_category_id(PDO $pdo, $category_id, $category_new, $now) {
    if (!empty($category_id)) return (int)$category_id;
    $nm = trim((string)$category_new);
    if ($nm === '') return null;
    $st = $pdo->prepare("INSERT INTO siin_category (name, created_at) VALUES (?, ?)");
    $st->execute([$nm, $now]);
    return (int)$pdo->lastInsertId();
}
function ensure_uom_id(PDO $pdo, $uom_id, $uom_new, $now) {
    if (!empty($uom_id)) return (int)$uom_id;
    $nm = strtoupper(trim((string)$uom_new));
    if ($nm === '') return null;
    $st = $pdo->prepare("INSERT INTO siin_uom (uomname, created_at) VALUES (?, ?)");
    $st->execute([$nm, $now]);
    return (int)$pdo->lastInsertId();
}

try {
    // basic validation
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection ($pdo) tidak tersedia. Periksa koneksi di koneksi.php');
    }

    if ($action === 'options') {
        $cats = $pdo->query("SELECT id, name FROM siin_category WHERE COALESCE(is_deleted,0)=0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $uoms = $pdo->query("SELECT id, uomname FROM siin_uom WHERE COALESCE(is_deleted,0)=0 ORDER BY uomname ASC")->fetchAll(PDO::FETCH_ASSOC);
        json_exit(['ok'=>true, 'categories' => $cats, 'uoms' => $uoms]);
    }

    // LIST for client-side DataTable (supports optional filters q, only_low, low_threshold)
    if ($action === 'list') {
        $q = trim($_GET['q'] ?? $_REQUEST['q'] ?? '');
        $only_low = isset($_GET['only_low']) ? (int)$_GET['only_low'] : (isset($_REQUEST['only_low']) ? (int)$_REQUEST['only_low'] : 0);
        $low_threshold = isset($_GET['low_threshold']) ? intval($_GET['low_threshold']) : (isset($_REQUEST['low_threshold']) ? intval($_REQUEST['low_threshold']) : 5);

        $sql = "SELECT p.id, p.prod_code, p.prod_name,
                       COALESCE(c.name,'') AS category,
                       COALESCE(u.uomname,'') AS uom,
                       COALESCE(p.stock,0) AS stock
                FROM siin_product p
                LEFT JOIN siin_category c ON c.id = p.category_id
                LEFT JOIN siin_uom u ON u.id = p.uom_id
                WHERE COALESCE(p.is_deleted,0) = 0";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.prod_code LIKE ? OR p.prod_name LIKE ? OR COALESCE(c.name,'') LIKE ? OR COALESCE(u.uomname,'') LIKE ?)";
            $like = "%{$q}%";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($only_low) {
            $sql .= " AND COALESCE(p.stock,0) < ?";
            $params[] = $low_threshold;
        }

        // default order by product name (client-side can still sort)
        $sql .= " ORDER BY p.prod_name ASC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        json_exit(['ok'=>true, 'data' => $rows]);
    }

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID tidak valid', 400);

        $st = $pdo->prepare("SELECT id, prod_code, prod_name, category_id, uom_id, stock FROM siin_product WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_error('Produk tidak ditemukan', 404);
        json_exit(['ok'=>true, 'data' => $row]);
    }

    if ($action === 'create' || $action === 'update') {
        $prod_code = trim($_POST['prod_code'] ?? '');
        $prod_name = trim($_POST['prod_name'] ?? '');
        $category_id = $_POST['category_id'] ?? '';
        $category_new = $_POST['category_new'] ?? '';
        $uom_id = $_POST['uom_id'] ?? '';
        $uom_new = $_POST['uom_new'] ?? '';

        if ($prod_code === '') json_error('Kode wajib diisi', 400);
        if ($prod_name === '') json_error('Nama wajib diisi', 400);

        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $cid = ensure_category_id($pdo, $category_id, $category_new, $now);
            $uid = ensure_uom_id($pdo, $uom_id, $uom_new, $now);

            if ($action === 'create') {
                $st = $pdo->prepare("SELECT COUNT(*) FROM siin_product WHERE prod_code = ?");
                $st->execute([$prod_code]);
                if ((int)$st->fetchColumn() > 0) {
                    $pdo->rollBack();
                    json_error('Kode produk sudah digunakan', 409);
                }

                $ins = $pdo->prepare("INSERT INTO siin_product (prod_code, prod_name, category_id, uom_id, stock, created_at, created_by)
                                      VALUES (?, ?, ?, ?, 0, ?, ?)");
                $createdBy = $_SESSION['UserName'] ?? null;
                $ins->execute([$prod_code, $prod_name, $cid, $uid, $now, $createdBy]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->commit();
                json_exit(['ok'=>true, 'id'=>$newId], 201);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { $pdo->rollBack(); json_error('ID tidak valid', 400); }

                $st = $pdo->prepare("SELECT COUNT(*) FROM siin_product WHERE prod_code = ? AND id <> ?");
                $st->execute([$prod_code, $id]);
                if ((int)$st->fetchColumn() > 0) {
                    $pdo->rollBack();
                    json_error('Kode produk sudah digunakan oleh item lain', 409);
                }

                $upd = $pdo->prepare("UPDATE siin_product
                                      SET prod_code = ?, prod_name = ?, category_id = ?, uom_id = ?, updated_at = ?, updated_by = ?
                                      WHERE id = ?");
                $updatedBy = $_SESSION['UserName'] ?? null;
                $upd->execute([$prod_code, $prod_name, $cid, $uid, $now, $updatedBy, $id]);
                $pdo->commit();
                json_exit(['ok'=>true, 'id'=>$id], 200);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action === 'delete') {
        $code = trim($_REQUEST['code'] ?? '');
        if ($code === '') json_error('Parameter code (prod_code) tidak disertakan', 400);

        // optional permission check here

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT id FROM siin_product WHERE prod_code = ?");
            $st->execute([$code]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->rollBack();
                json_error('Produk dengan kode tersebut tidak ditemukan', 404);
            }

            // Hard delete
            $del = $pdo->prepare("DELETE FROM siin_product WHERE prod_code = ?");
            $del->execute([$code]);
            $deleted = $del->rowCount();
            $pdo->commit();

            if ($deleted > 0) {
                json_exit(['ok'=>true, 'deleted' => $deleted, 'message' => 'Produk berhasil dihapus'], 200);
            } else {
                json_error('Tidak ada baris yang dihapus', 500);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    json_error('Aksi tidak dikenali', 400);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    // Return DB error to client (useful untuk debug). Hapus detail ini di production jika sensitif.
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    json_error('Server error: ' . $e->getMessage(), 500);
}
