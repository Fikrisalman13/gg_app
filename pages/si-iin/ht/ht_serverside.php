<?php
/**
 * gg_app/pages/si-iin/ht/ht_serverside.php
 */
session_start();

require_once __DIR__ . '/../_shared/siin_lib.php'; // $pdo = SIIN

// Pastikan koneksi SIIN tak ketimpa koneksi HR
$pdo_siin = $pdo;

// (Opsional) koneksi HR / MSSQL (SMUserMS & m_emp)
$pdo_hr = null;
$HRCONN = null;

$KON = realpath(__DIR__ . '/../../../koneksi.php');
if ($KON) {
    require_once $KON;
    if (isset($pdo_mssql) && $pdo_mssql instanceof PDO) {
        $pdo_hr = $pdo_mssql;
    } elseif (isset($pdo) && $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $pdo_hr = $pdo;
    } elseif (isset($conn)) {
        $HRCONN = $conn;
    } elseif (isset($sqlsrvConn)) {
        $HRCONN = $sqlsrvConn;
    }
}

function dbdrv(PDO $p) {
    return $p->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function get_stock(PDO $p, int $product_id) {
    try {
        $st = $p->prepare("SELECT qty_onhand FROM siin_stock WHERE product_id=?");
        $st->execute([$product_id]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? 0 : (float)$v;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Helper: emp_name berisi id_emp → ambil nama_lengkap dari HR.
 * Jika gagal, kembalikan nilai apa adanya.
 */
function resolveEmpNameDisplay($raw, $pdo_hr = null, $HRCONN = null): string {
    $val = trim((string)$raw);
    if ($val === '') return '';

    $id_emp = (int)$val;
    if ($id_emp <= 0) {
        return $val;
    }

    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?");
        if ($st && $st->execute([$id_emp])) {
            $nm = $st->fetchColumn();
            if ($nm) return (string)$nm;
        }
    }

    if ($HRCONN) {
        $sql  = "SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?";
        $stmt = sqlsrv_query($HRCONN, $sql, [$id_emp]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['nama_lengkap'])) {
                sqlsrv_free_stmt($stmt);
                return (string)$row['nama_lengkap'];
            }
            sqlsrv_free_stmt($stmt);
        }
    }

    return $val;
}

/**
 * Helper: ambil EmpId dari tabel SMUserMS berdasarkan UserName.
 * Dipakai untuk filter h.emp_name (siin_ht) = EmpId user login.
 */
function getEmpIdFromUserName(string $userName, $pdo_hr = null, $HRCONN = null): ?int {
    $userName = trim($userName);
    if ($userName === '') return null;

    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 EmpId FROM SMUserMS WHERE UserName = ?");
        if ($st && $st->execute([$userName])) {
            $val = $st->fetchColumn();
            if ($val !== false && $val !== null) return (int)$val;
        }
    }

    if ($HRCONN) {
        $sql  = "SELECT TOP 1 EmpId FROM SMUserMS WHERE UserName = ?";
        $stmt = sqlsrv_query($HRCONN, $sql, [$userName]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row && isset($row['EmpId'])) {
                return (int)$row['EmpId'];
            }
        }
    }

    return null;
}

/**
 * Helper: dari UserName → nama_lengkap m_emp (kalau ada)
 */
function getFullNameFromUserName(string $userName, $pdo_hr = null, $HRCONN = null): ?string {
    $empId = getEmpIdFromUserName($userName, $pdo_hr, $HRCONN);
    if (!$empId) return null;

    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?");
        if ($st && $st->execute([$empId])) {
            $nm = $st->fetchColumn();
            if ($nm) return (string)$nm;
        }
    }

    if ($HRCONN) {
        $sql  = "SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?";
        $stmt = sqlsrv_query($HRCONN, $sql, [$empId]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row && isset($row['nama_lengkap'])) {
                return (string)$row['nama_lengkap'];
            }
        }
    }

    return null;
}

/**
 * Helper: ambil info asset (id_asset, id_kode, text) dari HR (m_asset + m_kode_asset)
 * berdasarkan id_asset atau id_kode.
 */
function resolveAssetInfo(?int $id_asset, ?int $id_kode, $pdo_hr = null, $HRCONN = null): array {
    $id_asset = (int)($id_asset ?? 0);
    $id_kode  = (int)($id_kode ?? 0);

    if (!$pdo_hr && !$HRCONN) {
        return ['id_asset' => null, 'id_kode' => null, 'text' => ''];
    }

    $row = null;

    if ($pdo_hr instanceof PDO) {
        $drv = $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($id_asset > 0) {
            $sql = ($drv === 'sqlsrv')
               ? "SELECT TOP 1 a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                  FROM m_asset a
                  JOIN m_kode_asset k ON k.id_kode = a.id_kode
                  WHERE a.id_asset = ?"
               : "SELECT a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                  FROM m_asset a
                  JOIN m_kode_asset k ON k.id_kode = a.id_kode
                  WHERE a.id_asset = ?
                  LIMIT 1";
            $st = $pdo_hr->prepare($sql);
            $st->execute([$id_asset]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } elseif ($id_kode > 0) {
            $sql = ($drv === 'sqlsrv')
               ? "SELECT TOP 1 a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                  FROM m_asset a
                  JOIN m_kode_asset k ON k.id_kode = a.id_kode
                  WHERE a.id_kode = ?"
               : "SELECT a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                  FROM m_asset a
                  JOIN m_kode_asset k ON k.id_kode = a.id_kode
                  WHERE a.id_kode = ?
                  LIMIT 1";
            $st = $pdo_hr->prepare($sql);
            $st->execute([$id_kode]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        if ($id_asset > 0) {
            $sql  = "SELECT TOP 1 a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                     FROM m_asset a
                     JOIN m_kode_asset k ON k.id_kode = a.id_kode
                     WHERE a.id_asset = ?";
            $stmt = sqlsrv_query($HRCONN, $sql, [$id_asset]);
            if ($stmt !== false) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);
            }
        } elseif ($id_kode > 0) {
            $sql  = "SELECT TOP 1 a.id_asset, a.id_kode, a.keterangan, k.deskripsi
                     FROM m_asset a
                     JOIN m_kode_asset k ON k.id_kode = a.id_kode
                     WHERE a.id_kode = ?";
            $stmt = sqlsrv_query($HRCONN, $sql, [$id_kode]);
            if ($stmt !== false) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    if (!$row) {
        return ['id_asset' => null, 'id_kode' => null, 'text' => ''];
    }

    $base = $row['deskripsi'] ?? ('Kode #' . $row['id_kode']);
    $ket  = trim((string)($row['keterangan'] ?? ''));
    $text = $ket !== '' ? ($base . ' / ' . $ket) : $base;

    return [
        'id_asset' => isset($row['id_asset']) ? (int)$row['id_asset'] : null,
        'id_kode'  => isset($row['id_kode']) ? (int)$row['id_kode'] : null,
        'text'     => $text
    ];
}

/* ---------- Role detection: IT1..IT10 vs regular user ---------- */
$isITUser     = false;
$currentEmpId = null;

$allowedUserNames = ['GG','IT1','IT2','IT3','IT4','IT5','IT6','IT7','IT8','IT9','IT10','ITADM'];

if (isset($_SESSION['UserName'])) {
    $currentUser = strtoupper((string)$_SESSION['UserName']);

    if (in_array($currentUser, $allowedUserNames, true)) {
        $isITUser = true;
    } else {
        if (!empty($_SESSION['EmpId'])) {
            $currentEmpId = (int)$_SESSION['EmpId'];
        } else {
            $currentEmpId = getEmpIdFromUserName($_SESSION['UserName'], $pdo_hr, $HRCONN);
        }
    }
}

/* ---------- hak penanda tangan "Yang Menyerahkan" utk user IT ---------- */
$canSenderSign  = false;
$senderFullName = '';

if (isset($_SESSION['UserName'])) {
    $currentUserRaw = (string)$_SESSION['UserName'];
    $currentUser    = strtoupper($currentUserRaw);

    if (in_array($currentUser, $allowedUserNames, true)) {
        $canSenderSign = true;

        $fullName = getFullNameFromUserName($currentUserRaw, $pdo_hr, $HRCONN);
        if ($fullName && trim($fullName) !== '') {
            $senderFullName = $fullName;
        } else {
            $senderFullName = $currentUser;
        }
    }
}

/* ---------- util kecil: ambil nama UOM ---------- */
if (($_GET['mode'] ?? null) === 'uom_name') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false]); exit; }
    $st = $pdo_siin->prepare("SELECT uomname FROM siin_uom WHERE id=?");
    $st->execute([$id]);
    $name = $st->fetchColumn();
    echo json_encode(['success'=> (bool)$name, 'uomname'=> $name ?: '']);
    exit;
}

/* ---------- ping HR ---------- */
if (($_GET['mode'] ?? null) === 'emp_ping') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'                  => (bool)($pdo_hr || $HRCONN),
        'has_pdo_sqlsrv'      => (bool)$pdo_hr,
        'has_sqlsrv_resource' => (bool)$HRCONN
    ]);
    exit;
}

/* ---------- Select2 IR ---------- */
if (($_GET['mode'] ?? null) === 'ir') {
    header('Content-Type: application/json');
    try {
        $q   = trim($_GET['q'] ?? '');
        $drv = dbdrv($pdo_siin);

        $base = "
          SELECT h.id, h.request_no, h.ir_date, h.status,
                 CASE WHEN (
                   SELECT COUNT(*) FROM siin_ir_item x
                   WHERE x.ir_id=h.id AND COALESCE(x.qty_out,0) < x.qty
                 )>0 THEN 1 ELSE 0 END AS has_outstanding,
                 CASE WHEN LOWER(h.status)='approved' THEN 1 ELSE 0 END AS is_approved
          FROM   siin_ir h
        ";
        $where  = $q !== '' ? ' WHERE h.request_no LIKE ? ' : '';
        $params = $q !== '' ? ['%'.$q.'%'] : [];
        $order  = " ORDER BY has_outstanding DESC, is_approved DESC, ir_date ASC, id ASC ";

        if ($drv === 'sqlsrv')
            $sql = "SELECT TOP 50 * FROM ( $base $where ) T $order";
        else
            $sql = " $base $where $order LIMIT 50 ";

        $st   = $pdo_siin->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            try {
                if ($drv === 'sqlsrv') {
                    $fsql = "SELECT TOP 50 request_no, status FROM siin_ir ORDER BY ir_date DESC, id DESC";
                } else {
                    $fsql = "SELECT request_no, status FROM siin_ir ORDER BY ir_date DESC, id DESC LIMIT 50";
                }
                $fst   = $pdo_siin->query($fsql);
                $frows = $fst ? $fst->fetchAll(PDO::FETCH_ASSOC) : [];
                if ($frows) {
                    $rows = [];
                    foreach ($frows as $r) {
                        $rows[] = [
                            'id'         => $r['request_no'],
                            'request_no' => $r['request_no'],
                            'status'     => $r['status'] ?? ''
                        ];
                    }
                }
                if ($rows) {
                    @file_put_contents(
                        __DIR__ . '/ht_ir_fallback.log',
                        date('c') . " - used fallback, got " . count($rows) . " rows\n",
                        FILE_APPEND
                    );
                }
            } catch (Throwable $e) {
                @file_put_contents(
                    __DIR__ . '/ht_ir_fallback.log',
                    date('c') . " - fallback error: " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'   => $r['request_no'],
                'text' => $r['request_no'].' ('.strtoupper($r['status']).')'
            ];
        }
        echo json_encode($out);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

/* ---------- Select2 EMP ---------- */
if (($_GET['mode'] ?? null) === 'emp') {
    header('Content-Type: application/json');
    if (!$pdo_hr && !$HRCONN) { echo json_encode([]); exit; }

    $q    = trim($_GET['q'] ?? '');
    $like = '%'.$q.'%';

    $sql_sqlsrv = "
        SELECT DISTINCT TOP 50 e.id_emp, e.nama_lengkap, s.subbag
        FROM m_emp e
        LEFT JOIN m_subbag s ON s.id_subbag = e.id_subbag
        WHERE e.id_emp IS NOT NULL
          AND e.nama_lengkap LIKE ?
        ORDER BY e.nama_lengkap ASC
    ";

    $sql_other = "
        SELECT DISTINCT e.id_emp, e.nama_lengkap, s.subbag
        FROM m_emp e
        LEFT JOIN m_subbag s ON s.id_subbag = e.id_subbag
        WHERE e.id_emp IS NOT NULL
          AND e.nama_lengkap LIKE ?
        ORDER BY e.nama_lengkap ASC
        LIMIT 50
    ";

    $rows = [];
    if ($pdo_hr instanceof PDO) {
        $st = $pdo_hr->prepare(
            ($pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') ? $sql_sqlsrv : $sql_other
        );
        $st->execute([$like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = sqlsrv_query($HRCONN, $sql_sqlsrv, [$like]);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'   => (string)$r['id_emp'],
            'text' => $r['nama_lengkap'],
            'dept' => $r['subbag'] ?? ''
        ];
    }
    echo json_encode($out);
    exit;
}

/* ---------- Select2 ASET KARYAWAN ---------- */
if (($_GET['mode'] ?? null) === 'emp_assets') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$pdo_hr && !$HRCONN) {
        echo json_encode([]);
        exit;
    }

    $id_emp = (int)($_GET['id_emp'] ?? 0);
    $q      = trim($_GET['q'] ?? '');
    $like   = '%'.$q.'%';

    $sql_sqlsrv = "
        SELECT TOP 50 a.id_asset, a.id_kode, a.keterangan, k.deskripsi
        FROM m_asset a
        JOIN m_kode_asset k ON k.id_kode = a.id_kode
        WHERE a.id_emp = ? AND a.id_status = 1
          AND (k.deskripsi LIKE ? OR a.keterangan LIKE ? OR ? = '')
        ORDER BY k.deskripsi ASC, a.id_asset ASC
    ";

    $sql_other = "
        SELECT a.id_asset, a.id_kode, a.keterangan, k.deskripsi
        FROM m_asset a
        JOIN m_kode_asset k ON k.id_kode = a.id_kode
        WHERE a.id_emp = ? AND a.id_status = 1
          AND (k.deskripsi LIKE ? OR a.keterangan LIKE ? OR ? = '')
        ORDER BY k.deskripsi ASC, a.id_asset ASC
        LIMIT 50
    ";

    $rows   = [];
    $params = [$id_emp, $like, $like, $q === '' ? '' : $like];

    if ($pdo_hr instanceof PDO) {
        $drv = $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = ($drv === 'sqlsrv') ? $sql_sqlsrv : $sql_other;
        $st  = $pdo_hr->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = sqlsrv_query($HRCONN, $sql_sqlsrv, [$id_emp, $like, $like, $q === '' ? '' : $like]);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $base = $r['deskripsi'] ?? ('Kode #' . $r['id_kode']);
        $ket  = trim((string)($r['keterangan'] ?? ''));
        $text = $ket !== '' ? ($base . ' / ' . $ket) : $base;

        $out[] = [
            'id'      => (string)$r['id_asset'],
            'text'    => $text,
            'id_kode' => isset($r['id_kode']) ? (int)$r['id_kode'] : null
        ];
    }

    echo json_encode($out);
    exit;
}

/* ---------- return nama penanda tangan untuk HT ---------- */
if (($_GET['mode'] ?? null) === 'ht_sender') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID tidak valid');

        $st = $pdo_siin->prepare("SELECT sign_name, emp_name FROM siin_ht WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('HT tidak ditemukan');

        $name = '';
        if (isset($row['sign_name']) && trim($row['sign_name']) !== '') {
            $name = trim($row['sign_name']);
        } else {
            $name = resolveEmpNameDisplay($row['emp_name'] ?? '', $pdo_hr, $HRCONN);
        }

        echo json_encode(['success' => true, 'name' => $name]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ---------- Items IR utk ir_no ---------- */
if (($_GET['mode'] ?? null) === 'ir_items') {
    header('Content-Type: application/json; charset=utf-8');

    $ir_no = trim($_GET['ir_no'] ?? '');
    $exclude_ht_id = (int)($_GET['exclude_ht_id'] ?? 0);
    if ($ir_no === '') { echo json_encode(['success'=>true,'items'=>[]]); exit; }

    $stH = $pdo_siin->prepare("SELECT id, ir_date, status FROM siin_ir WHERE request_no=?");
    $stH->execute([$ir_no]);
    $hdr = $stH->fetch(PDO::FETCH_ASSOC);
    if (!$hdr) { echo json_encode(['success'=>true,'items'=>[]]); exit; }

    $openReserved = get_open_ht_reserved_by_product($pdo_siin, $ir_no, $exclude_ht_id);

    $sql = "SELECT it.id, it.product_id, p.prod_code, p.prod_name,
                   it.uom_id, it.qty, COALESCE(it.qty_out,0) AS qty_out,
                   u.uomname
            FROM   siin_ir_item it
            JOIN   siin_product p ON p.id = it.product_id
            LEFT   JOIN siin_uom u ON u.id = it.uom_id
            WHERE  it.ir_id=?
            ORDER  BY it.id ASC";
    $st   = $pdo_siin->prepare($sql);
    $st->execute([(int)$hdr['id']]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        $openQty = (float)($openReserved[$pid] ?? 0);
        $outstanding = max(0, (float)$r['qty'] - (float)$r['qty_out'] - $openQty);
        $items[] = [
            'product_id'      => $pid,
            'prod_code'       => $r['prod_code'],
            'prod_name'       => $r['prod_name'],
            'uom_id'          => (int)$r['uom_id'],
            'uomname'         => $r['uomname'],
            'outstanding_qty' => $outstanding,
            'open_reserved_qty'=> $openQty
        ];
    }
    usort($items, function($a,$b){
        $ka = ($a['outstanding_qty'] > 0) ? 0 : 1;
        $kb = ($b['outstanding_qty'] > 0) ? 0 : 1;
        return $ka <=> $kb;
    });

    echo json_encode(['success'=>true,'items'=>$items]);
    exit;
}

/* ---------- Rekomendasi IR utk product_id ---------- */
if (($_GET['mode'] ?? null) === 'ir_suggest_by_product') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pid = (int)($_GET['product_id'] ?? 0);
        if ($pid <= 0) throw new Exception('product_id kosong');
        $drv = dbdrv($pdo_siin);

        $sql1 = ($drv === 'sqlsrv')
            ?"SELECT TOP 1 h.request_no ir_no,h.ir_date,h.status
              FROM siin_ir h JOIN siin_ir_item it ON it.ir_id=h.id
              WHERE it.product_id=? AND (it.qty-COALESCE(it.qty_out,0))>0
              ORDER BY h.ir_date ASC,h.id ASC"
            :"SELECT h.request_no ir_no,h.ir_date,h.status
              FROM siin_ir h JOIN siin_ir_item it ON it.ir_id=h.id
              WHERE it.product_id=? AND (it.qty-COALESCE(it.qty_out,0))>0
              ORDER BY h.ir_date ASC,h.id ASC LIMIT 1";

        $st1 = $pdo_siin->prepare($sql1);
        $st1->execute([$pid]);
        $best = $st1->fetch(PDO::FETCH_ASSOC);

        if (!$best) {
            $sql2 = ($drv === 'sqlsrv')
                ?"SELECT TOP 1 h.request_no ir_no,h.ir_date,h.status
                  FROM siin_ir h JOIN siin_ir_item it ON it.ir_id=h.id
                  WHERE it.product_id=? AND h.status='Approved'
                  ORDER BY h.ir_date ASC,h.id ASC"
                :"SELECT h.request_no ir_no,h.ir_date,h.status
                  FROM siin_ir h JOIN siin_ir_item it ON it.ir_id=h.id
                  WHERE it.product_id=? AND h.status='Approved'
                  ORDER BY h.ir_date ASC,h.id ASC LIMIT 1";

            $st2 = $pdo_siin->prepare($sql2);
            $st2->execute([$pid]);
            $best = $st2->fetch(PDO::FETCH_ASSOC);
        }

        if (!$best) throw new Exception('Tidak ada IR cocok');
        echo json_encode(['success'=>true,'ir'=>$best]);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

/* ---------- Detail HT (header + items) ---------- */
if (($_GET['mode'] ?? null) === 'ht_detail') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID tidak valid');

        $whereExtra = '';
        $params     = [$id];
        if (!$isITUser && $currentEmpId !== null) {
            $whereExtra = " AND emp_name = ? ";
            $params[]   = (string)$currentEmpId;
        }

        // ⬇️ TAMBAH id_asset DI SELECT
        $stH = $pdo_siin->prepare(
            "SELECT id, ht_date, ir_no, emp_name, dept_name, descr, status, sign_name, id_kode, id_asset
             FROM siin_ht
             WHERE id = ? $whereExtra"
        );
        $stH->execute($params);
        $H = $stH->fetch(PDO::FETCH_ASSOC);
        if (!$H) throw new Exception('HT tidak ditemukan atau bukan milik Anda');

        $H['emp_display'] = resolveEmpNameDisplay($H['emp_name'] ?? '', $pdo_hr, $HRCONN);

        // ⬇️ BANGUN TEXT ASSET: Deskripsi + keterangan, dari HR (m_asset + m_kode_asset)
        $assetInfo = resolveAssetInfo(
            isset($H['id_asset']) ? (int)$H['id_asset'] : null,
            isset($H['id_kode'])  ? (int)$H['id_kode']  : null,
            $pdo_hr,
            $HRCONN
        );
        $H['id_asset']   = $assetInfo['id_asset'];
        $H['id_kode']    = $assetInfo['id_kode'];
        $H['asset_text'] = $assetInfo['text'];

        $stI = $pdo_siin->prepare(
            "SELECT i.product_id, i.qty, i.uom_id,
                    p.prod_code, p.prod_name, u.uomname
             FROM siin_ht_item i
             JOIN siin_product p ON p.id=i.product_id
             LEFT JOIN siin_uom u ON u.id=i.uom_id
             WHERE i.ht_id=?
             ORDER BY i.id ASC"
        );
        $stI->execute([$id]);
        $items = $stI->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true, 'header'=>$H, 'items'=>$items]);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

/* ---------- DataTables daftar HT ---------- */
$draw   = (int)($_POST['draw']   ?? 1);
$start  = (int)($_POST['start']  ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

$total = (int)$pdo_siin->query("SELECT COUNT(*) FROM siin_ht")->fetchColumn();
$drv   = dbdrv($pdo_siin);

$offset = max(0, $start);
if ($length < 1) {
    $length = $total > 0 ? $total : 1000;
}

$where  = '';
$params = [];
if (!$isITUser && $currentEmpId !== null) {
    $where  = " WHERE h.emp_name = ? ";
    $params = [(string)$currentEmpId];
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $searchSql = "
      (
        COALESCE(h.no_grn, '') LIKE ?
        OR COALESCE(h.ir_no, '') LIKE ?
        OR COALESCE(h.emp_name, '') LIKE ?
        OR COALESCE(h.dept_name, '') LIKE ?
        OR COALESCE(h.status, '') LIKE ?
        OR EXISTS (
          SELECT 1
          FROM siin_ht_item i2
          JOIN siin_product p2 ON p2.id = i2.product_id
          WHERE i2.ht_id = h.id
            AND (
              COALESCE(p2.prod_code, '') LIKE ?
              OR COALESCE(p2.prod_name, '') LIKE ?
            )
        )
      )
    ";

    if ($where) {
        $where .= " AND " . $searchSql;
    } else {
        $where = " WHERE " . $searchSql;
    }

    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

if ($where) {
    $sqlCount = "SELECT COUNT(*) FROM siin_ht h" . $where;
    $stCount  = $pdo_siin->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();
}
$filtered = $total;

if ($drv === 'sqlsrv') {
    $sql = "SELECT h.id,h.ht_date,h.no_grn,h.ir_no,h.emp_name,h.dept_name,h.status,
                   (SELECT COUNT(*) FROM siin_ht_item i WHERE i.ht_id=h.id) cnt
            FROM siin_ht h
            $where
            ORDER BY h.id DESC
            OFFSET $offset ROWS FETCH NEXT $length ROWS ONLY";
    $st = $pdo_siin->prepare($sql);
    $st->execute($params);
} else {
    $sql = "SELECT h.id,h.ht_date,h.no_grn,h.ir_no,h.emp_name,h.dept_name,h.status,
                   (SELECT COUNT(*) FROM siin_ht_item i WHERE i.ht_id=h.id) cnt
            FROM siin_ht h
            $where
            ORDER BY h.id DESC
            LIMIT $length OFFSET $offset";
    $st = $pdo_siin->prepare($sql);
    $st->execute($params);
}

$data = [];
foreach ($st as $r) {
    $status = strtolower($r['status']);
    $badge  = ($status === 'approved' ? 'success' : ($status === 'open' ? 'info' : 'secondary'));

    $empDisplay = resolveEmpNameDisplay($r['emp_name'] ?? '', $pdo_hr, $HRCONN);

    $aksi = '<div class="btn-group btn-group-sm">';

    if ($isITUser) {
        // IT: punya View + tool lain
        $aksi .= '<button class="btn btn-outline-primary btn-view" data-id="'.$r['id'].'">
                    <i class="fas fa-eye"></i>
                  </button>';

        if ($status === 'open') {
            $aksi .= '<button class="btn btn-outline-success btn-approve" data-id="'.$r['id'].'">
                        <i class="fas fa-pen-nib"></i>
                      </button>';
            $aksi .= '<button class="btn btn-outline-danger btn-delete" data-id="'.$r['id'].'">
                        <i class="fas fa-trash"></i>
                      </button>';
        } elseif ($status === 'approved') {
            $aksi .= '<button class="btn btn-outline-secondary btn-unapprove" data-id="'.$r['id'].'">
                        <i class="fas fa-undo"></i>
                      </button>';
            $aksi .= '<button class="btn btn-outline-danger btn-pdf" data-id="'.$r['id'].'">
                        <i class="fas fa-file-pdf"></i>
                      </button>';
        } else {
            $aksi .= '<button class="btn btn-outline-danger btn-delete" data-id="'.$r['id'].'">
                        <i class="fas fa-trash"></i>
                      </button>';
        }

        if ($canSenderSign && $senderFullName !== '') {
            $aksi .= '<button type="button"
                          class="btn btn-outline-primary btn-esign-sender"
                          data-id="'.$r['id'].'"
                          data-nama="'.htmlspecialchars($senderFullName, ENT_QUOTES, 'UTF-8').'"
                    ><i class="fas fa-pen"></i></button>';
        }
    } else {
        // NON-IT: SEKARANG JUGA PUNYA TOMBOL VIEW
        $aksi .= '<button class="btn btn-outline-primary btn-view" data-id="'.$r['id'].'">
                    <i class="fas fa-eye"></i>
                  </button>';

        if ($status === 'open') {
            $aksi .= '<button class="btn btn-outline-success btn-approve" data-id="'.$r['id'].'">
                        <i class="fas fa-pen-nib"></i>
                      </button>';
        } elseif ($status === 'approved') {
            $aksi .= '<button class="btn btn-outline-danger btn-pdf" data-id="'.$r['id'].'">
                        <i class="fas fa-file-pdf"></i>
                      </button>';
        }
    }

    $aksi .= '</div>';

    $data[] = [
        'ht_date'   => Ymd_to_dmY($r['ht_date']),
        'no_grn'    => htmlspecialchars($r['no_grn'] ?? '-', ENT_QUOTES, 'UTF-8'),
        'ir_no'     => htmlspecialchars($r['ir_no'], ENT_QUOTES, 'UTF-8'),
        'emp_name'  => htmlspecialchars($empDisplay ?? '', ENT_QUOTES, 'UTF-8'),
        'dept_name' => htmlspecialchars($r['dept_name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'produk'    => $r['cnt'].' item',
        'status'    => '<span class="badge badge-'.$badge.'">'.strtoupper($r['status']).'</span>',
        'aksi'      => $aksi
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $filtered,
    'data'            => $data
]);
