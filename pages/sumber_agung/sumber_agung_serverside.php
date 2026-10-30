<?php
/**
 * API Backend Sumber Agung - Manajemen Data Titipan DO
 * Menangani semua operasi AJAX untuk modul Sumber Agung (internal).
 *
 * Aksi yang didukung:
 *  - load_data       : Load data dari dbo.sa_titipan dengan filter (GET)
 *  - get_summary     : Hitung summary cards (GET)
 *  - search_titipan  : Search data titipan dari ERP PostgreSQL (GET)
 *  - submit_titipan  : Simpan data terpilih ke dbo.sa_titipan (POST)
 *  - upload_doc      : Upload file PDF / simpan URL packing list atau surat jalan (POST)
 *  - publish         : Set is_published=1 untuk items terpilih (POST)
 *  - get_detail      : Ambil detail satu baris (GET)
 *  - delete_data     : Soft delete data (POST)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/koneksi.php';   // SQL Server GG ($conn)
require_once $rootPath . '/koneksi3.php';  // PostgreSQL ERP_Crystal_SUM ($conn3) - READ ONLY

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// =========================================================================
// HELPER: IDENTITAS USER
// =========================================================================
if (!function_exists('getSAUserIdentity')) {
function getSAUserIdentity($conn) {
    $user = trim($_SESSION['UserName'] ?? '');
    $nama = trim($_SESSION['NamaLengkap'] ?? '');
    $dept = '';
    $groupId = $_SESSION['GroupId'] ?? null;
    $groupName = $_SESSION['GroupName'] ?? '';

    if (!empty($conn) && !empty($user)) {
        $q = sqlsrv_query($conn, "SELECT u.GroupId, g.GroupName, e.nama_lengkap, d.dept
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
            LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
            LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
            WHERE u.UserName = ?", [$user]);
        if ($q && ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC))) {
            if (!empty($row['nama_lengkap'])) $nama = trim($row['nama_lengkap']);
            if (!empty($row['dept'])) $dept = trim($row['dept']);
            if (!empty($row['GroupId'])) $groupId = $row['GroupId'];
            if (!empty($row['GroupName'])) $groupName = trim($row['GroupName']);
        }
    }
    if (empty($nama)) $nama = $user;

    $isAdmin = (
        (!empty($groupName) && strcasecmp(trim($groupName), 'Administrator') === 0) ||
        (int)$groupId === 1
    );
    $isPortal = (!empty($groupName) && (stripos($groupName, 'portal') !== false || stripos($groupName, 'potal') !== false));

    return [
        'user'       => $user,
        'nama'       => $nama,
        'dept'       => $dept,
        'group_id'   => $groupId,
        'group_name' => $groupName,
        'is_admin'   => $isAdmin,
        'is_portal'  => $isPortal,
        'formatted'  => !empty($dept) ? "{$nama} ({$dept})" : $nama
    ];
}
}

$currentUser = getSAUserIdentity($conn);

if (empty($currentUser['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi login tidak valid.']);
    exit;
}

// =========================================================================
// CEK TABEL
// =========================================================================
$tableReady = false;
if (!empty($conn)) {
    $qCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'sa_titipan'");
    if ($qCheck && sqlsrv_has_rows($qCheck)) {
        $tableReady = true;
    }
}

// =========================================================================
// HELPER: Format tanggal SQL Server
// =========================================================================
if (!function_exists('formatDateSA')) {
function formatDateSA($val, $format = 'd/m/Y') {
    if (empty($val)) return '-';
    if ($val instanceof DateTimeInterface) return $val->format($format);
    return date($format, strtotime($val));
}
}

// =========================================================================
// ROUTER
// =========================================================================
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {

    // =====================================================================
    // LOAD DATA
    // =====================================================================
    case 'load_data':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia. Jalankan schema SQL terlebih dahulu.']);
            exit;
        }

        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to'] ?? '');
        $filterDoc = trim($_GET['filter_doc'] ?? '');
        $filterPub = trim($_GET['filter_pub'] ?? '');

        $where = "is_deleted = 0";
        $params = [];

        if (!empty($dateFrom) && !empty($dateTo)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif (!empty($dateFrom)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) >= ?";
            $params[] = $dateFrom;
        } elseif (!empty($dateTo)) {
            $where .= " AND COALESCE(tgl_kirim, tgl_titipan) <= ?";
            $params[] = $dateTo;
        }

        // Filter status dokumen
        if ($filterDoc === 'belum_lengkap' || $filterDoc === 'belum_komplit') {
            $where .= " AND (packinglist_file IS NULL OR suratjalan_file IS NULL)";
        } elseif ($filterDoc === 'lengkap' || $filterDoc === 'komplit') {
            $where .= " AND packinglist_file IS NOT NULL AND suratjalan_file IS NOT NULL";
        }

        // Filter status publish
        if ($filterPub === 'belum_publish') {
            $where .= " AND is_published = 0";
        } elseif ($filterPub === 'published') {
            $where .= " AND is_published = 1 AND is_viewed = 0";
        } elseif ($filterPub === 'sudah_dilihat') {
            $where .= " AND is_published = 1 AND is_viewed = 1";
        }

        $sql = "SELECT * FROM dbo.sa_titipan WHERE {$where} ORDER BY COALESCE(tgl_kirim, tgl_titipan) DESC, id DESC";
        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        $no = 1;

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $hasPL = !empty($row['packinglist_file']);
                $hasSJ = !empty($row['suratjalan_file']);

                // Status dokumen
                if ($hasPL && $hasSJ) {
                    $statusDoc = 'Lengkap';
                } else {
                    $statusDoc = 'Belum Lengkap';
                }

                // Status publish
                if (!$row['is_published']) {
                    $statusPub = 'Belum Publish';
                } elseif ($row['is_published'] && !$row['is_viewed']) {
                    $statusPub = 'Published';
                } else {
                    $statusPub = 'Sudah Dilihat';
                }

                $data[] = [
                    'no'             => $no++,
                    'id'             => (int)$row['id'],
                    'tgl_kirim'      => formatDateSA($row['tgl_kirim']),
                    'tgl_kirim_raw'  => !empty($row['tgl_kirim']) ? ($row['tgl_kirim'] instanceof DateTimeInterface ? $row['tgl_kirim']->format('Y-m-d') : date('Y-m-d', strtotime($row['tgl_kirim']))) : null,
                    'no_sc'          => trim($row['no_sc'] ?? '-'),
                    'tgl_sc'         => formatDateSA($row['tgl_sc']),
                    'product_name'   => trim($row['product_name'] ?? '-'),
                    'product_code'   => trim($row['product_code'] ?? ''),
                    'label_jual'     => trim($row['label_jual'] ?? '-'),
                    'cust_color'     => trim($row['cust_color'] ?? '-'),
                    'no_titipan'     => trim($row['no_titipan'] ?? '-'),
                    'tgl_titipan'    => formatDateSA($row['tgl_titipan']),
                    'no_do'          => trim($row['no_do'] ?? '-'),
                    'tgl_do'         => formatDateSA($row['tgl_do']),
                    'cust_id'        => trim($row['cust_id'] ?? ''),
                    'cust_name'      => trim($row['cust_name'] ?? ''),
                    'do_qty'         => $row['do_qty'] !== null ? (float)$row['do_qty'] : null,
                    'qty_kirim'      => $row['qty_kirim'] !== null ? (float)$row['qty_kirim'] : null,
                    'uom'            => trim($row['uom'] ?? '-'),
                    'ket_titipan_1'  => trim($row['ket_titipan_1'] ?? ''),
                    'ket_titipan_2'  => trim($row['ket_titipan_2'] ?? ''),
                    'dodesc'         => trim($row['dodesc'] ?? ''),
                    'no_packing'     => trim($row['no_packing'] ?? '-'),
                    'no_bale'        => trim($row['no_bale'] ?? '-'),
                    'totqtym'        => $row['totqtym'] !== null ? (float)$row['totqtym'] : null,
                    'totqtyyard'     => $row['totqtyyard'] !== null ? (float)$row['totqtyyard'] : null,
                    'countbatch'     => $row['countbatch'] !== null ? (int)$row['countbatch'] : null,
                    'dodtdesc'       => trim($row['dodtdesc'] ?? ''),
                    'has_packinglist'=> $hasPL,
                    'has_suratjalan' => $hasSJ,
                    'packinglist_file' => $row['packinglist_file'],
                    'packinglist_type' => $row['packinglist_type'],
                    'suratjalan_file'  => $row['suratjalan_file'],
                    'suratjalan_type'  => $row['suratjalan_type'],
                    'status_doc'     => $statusDoc,
                    'status_pub'     => $statusPub,
                    'is_published'   => (bool)$row['is_published'],
                    'is_viewed'      => (bool)$row['is_viewed'],
                ];
            }
        }

        echo json_encode(['status' => 'ok', 'data' => $data]);
        break;

    // =====================================================================
    // GET SUMMARY
    // =====================================================================
    case 'get_summary':
        if (!$tableReady) {
            echo json_encode(['status' => 'ok', 'summary' => ['belum_lengkap' => 0, 'published' => 0, 'sudah_dilihat' => 0]]);
            exit;
        }

        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to'] ?? '');

        $dateCond = "";
        $dateParams = [];

        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateCond = " AND COALESCE(tgl_kirim, tgl_titipan) BETWEEN ? AND ?";
            $dateParams = [$dateFrom, $dateTo];
        } elseif (!empty($dateFrom)) {
            $dateCond = " AND COALESCE(tgl_kirim, tgl_titipan) >= ?";
            $dateParams = [$dateFrom];
        } elseif (!empty($dateTo)) {
            $dateCond = " AND COALESCE(tgl_kirim, tgl_titipan) <= ?";
            $dateParams = [$dateTo];
        }

        $summary = ['belum_lengkap' => 0, 'published' => 0, 'sudah_dilihat' => 0];

        // Belum Lengkap: salah satu atau keduanya null
        $q1 = sqlsrv_query($conn,
            "SELECT COUNT(*) AS cnt FROM dbo.sa_titipan WHERE is_deleted = 0 AND (packinglist_file IS NULL OR suratjalan_file IS NULL)" . $dateCond,
            $dateParams);
        if ($q1 && ($r = sqlsrv_fetch_array($q1, SQLSRV_FETCH_ASSOC))) {
            $summary['belum_lengkap'] = (int)$r['cnt'];
        }

        // Published (belum dilihat)
        $q2 = sqlsrv_query($conn,
            "SELECT COUNT(*) AS cnt FROM dbo.sa_titipan WHERE is_deleted = 0 AND is_published = 1 AND is_viewed = 0" . $dateCond,
            $dateParams);
        if ($q2 && ($r = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC))) {
            $summary['published'] = (int)$r['cnt'];
        }

        // Sudah Dilihat
        $q3 = sqlsrv_query($conn,
            "SELECT COUNT(*) AS cnt FROM dbo.sa_titipan WHERE is_deleted = 0 AND is_published = 1 AND is_viewed = 1" . $dateCond,
            $dateParams);
        if ($q3 && ($r = sqlsrv_fetch_array($q3, SQLSRV_FETCH_ASSOC))) {
            $summary['sudah_dilihat'] = (int)$r['cnt'];
        }

        echo json_encode(['status' => 'ok', 'summary' => $summary]);
        break;

    // =====================================================================
    // SEARCH TITIPAN / DO - Query data dari ERP PostgreSQL (READ ONLY)
    // =====================================================================
    case 'search_titipan':
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP tidak tersedia.']);
            exit;
        }

        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to'] ?? '');
        $searchField = trim($_GET['search_field'] ?? 'all');
        $keyword  = trim($_GET['keyword'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = max(10, min(100, (int)($_GET['limit'] ?? 25)));
        $offset   = ($page - 1) * $limit;

        // Default rentang tanggal DO: hari ini jika tidak dispesifikasi
        if (empty($dateFrom)) $dateFrom = date('Y-m-d');
        if (empty($dateTo))   $dateTo   = date('Y-m-d');

        try {
            // Filter khusus customer Sumber Agung: LS0014 (SUMBER AGUNG INTERNUSA, PT) & LP0072 (PT. SUMBER AGUNG INTERNUSA)
            // Serta NOT EXISTS di tabel whdotitipan (hanya DO non-titipan / langsung)
            $where = " WHERE smcustomer.cuscode IN ('LS0014','LP0072')
                       AND whdohd.dodate BETWEEN :date_from AND :date_to
                       AND NOT EXISTS (
                           SELECT 1
                           FROM whdotitipan
                           WHERE whdotitipan.donmbr = whdohd.donmbr
                       ) ";
            $params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

            if (!empty($keyword)) {
                $term = '%' . $keyword . '%';
                if ($searchField === 'no_packing') {
                    $where .= " AND bale_agg.no_packing ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'no_bale') {
                    $where .= " AND bale_agg.no_bale ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'no_do') {
                    $where .= " AND whdohd.donmbr ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'cust_name') {
                    $where .= " AND (smcustomer.cusname ILIKE :kw OR smcustomer.cuscode ILIKE :kw) ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'prodcode') {
                    $where .= " AND item_agg.product_code ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'prodname') {
                    $where .= " AND (item_agg.product_name ILIKE :kw OR item_agg.product_code ILIKE :kw) ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'label_jual') {
                    $where .= " AND item_agg.label_jual ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'cust_color') {
                    $where .= " AND item_agg.cust_color ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'sonmbr') {
                    $where .= " AND item_agg.no_sc ILIKE :kw ";
                    $params[':kw'] = $term;
                } else {
                    $where .= " AND (
                        whdohd.donmbr ILIKE :kw1 OR
                        bale_agg.no_packing ILIKE :kw2 OR
                        bale_agg.no_bale ILIKE :kw3 OR
                        smcustomer.cusname ILIKE :kw4 OR
                        item_agg.product_name ILIKE :kw5 OR
                        item_agg.no_sc ILIKE :kw6 OR
                        item_agg.product_code ILIKE :kw7 OR
                        item_agg.label_jual ILIKE :kw8 OR
                        item_agg.cust_color ILIKE :kw9
                    )";
                    for ($i = 1; $i <= 9; $i++) {
                        $params[":kw{$i}"] = $term;
                    }
                }
            }

            // Count total
            if (empty($keyword)) {
                $sqlCount = "
                    SELECT COUNT(*) AS total
                    FROM whdohd
                    LEFT JOIN smcustomer ON whdohd.cusid = smcustomer.cusid
                    {$where}
                ";
            } else {
                $sqlCount = "
                    SELECT COUNT(*) AS total
                    FROM whdohd
                    LEFT JOIN smcustomer ON whdohd.cusid = smcustomer.cusid
                    LEFT JOIN LATERAL (
                        SELECT
                            MAX(insohd.sonmbr) AS no_sc,
                            STRING_AGG(DISTINCT whdodt.prodcode, ', ') AS product_code,
                            STRING_AGG(DISTINCT whdodt.prodname, ', ') AS product_name,
                            STRING_AGG(DISTINCT smprodtechdata.cuscolor, ', ') AS cust_color,
                            STRING_AGG(DISTINCT smprodtechdata.labeljual, ', ') AS label_jual
                        FROM whdodt
                        LEFT JOIN smprodtechdata ON whdodt.prodid = smprodtechdata.prodid
                        LEFT JOIN insohd ON whdodt.sohdid = insohd.sohdid
                        WHERE whdodt.dohdid = whdohd.dohdid
                    ) item_agg ON true
                    LEFT JOIN LATERAL (
                        SELECT
                            STRING_AGG(DISTINCT whbalehd.balenmbr, ', ') AS no_packing,
                            STRING_AGG(DISTINCT whbalehd.refnmbr, ', ') AS no_bale
                        FROM whbaleprod
                        LEFT JOIN whbalehd ON whbaleprod.balehdid = whbalehd.balehdid
                        WHERE whbaleprod.dohdid = whdohd.dohdid
                    ) bale_agg ON true
                    {$where}
                ";
            }
            $stmtCount = $conn3->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRows = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Data query
            $sqlData = "
                SELECT
                    whdohd.dohdid,
                    whdohd.donmbr AS no_do,
                    whdohd.dodate AS tgl_do,
                    smcustomer.cusname AS cust_name,
                    smcustomer.cuscode AS cust_id,
                    whdohd.dodesc,
                    item_agg.no_sc,
                    item_agg.tgl_sc,
                    item_agg.product_code,
                    item_agg.product_name,
                    item_agg.cust_color,
                    item_agg.label_jual,
                    item_agg.do_qty,
                    item_agg.uom,
                    item_agg.dodtdesc,
                    bale_agg.no_packing,
                    bale_agg.no_bale,
                    bale_agg.totqtym,
                    bale_agg.totqtyyard,
                    bale_agg.countbatch
                FROM whdohd
                LEFT JOIN smcustomer ON whdohd.cusid = smcustomer.cusid
                LEFT JOIN LATERAL (
                    SELECT
                        MAX(insohd.sonmbr) AS no_sc,
                        MAX(insohd.sodate) AS tgl_sc,
                        STRING_AGG(DISTINCT whdodt.prodcode, ', ') AS product_code,
                        STRING_AGG(DISTINCT whdodt.prodname, ', ') AS product_name,
                        STRING_AGG(DISTINCT smprodtechdata.cuscolor, ', ') AS cust_color,
                        STRING_AGG(DISTINCT smprodtechdata.labeljual, ', ') AS label_jual,
                        SUM(whdodt.doqty) AS do_qty,
                        MAX(smuom.uomname) AS uom,
                        STRING_AGG(DISTINCT whdodt.dodtdesc, ' | ') AS dodtdesc
                    FROM whdodt
                    LEFT JOIN smprodtechdata ON whdodt.prodid = smprodtechdata.prodid
                    LEFT JOIN insohd ON whdodt.sohdid = insohd.sohdid
                    LEFT JOIN smuom ON whdodt.stduomid = smuom.uomid
                    WHERE whdodt.dohdid = whdohd.dohdid
                ) item_agg ON true
                LEFT JOIN LATERAL (
                    SELECT
                        STRING_AGG(DISTINCT whbalehd.balenmbr, ', ' ORDER BY whbalehd.balenmbr) AS no_packing,
                        STRING_AGG(DISTINCT whbalehd.refnmbr, ', ' ORDER BY whbalehd.refnmbr) AS no_bale,
                        SUM(whbaleprod.totqtym) AS totqtym,
                        SUM(whbaleprod.totqtyyard) AS totqtyyard,
                        SUM(whbaleprod.countbatch) AS countbatch
                    FROM whbaleprod
                    LEFT JOIN whbalehd ON whbaleprod.balehdid = whbalehd.balehdid
                    WHERE whbaleprod.dohdid = whdohd.dohdid
                ) bale_agg ON true
                {$where}
                ORDER BY whdohd.dodate DESC, whdohd.donmbr ASC
                OFFSET :offset LIMIT :limit
            ";
            $stmtData = $conn3->prepare($sqlData);
            foreach ($params as $k => $v) {
                $stmtData->bindValue($k, $v);
            }
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->execute();

            // Get existing DOs already in sa_titipan (kunci: no_do)
            $existingKeys = [];
            if ($tableReady) {
                $qExist = sqlsrv_query($conn, "SELECT no_do FROM dbo.sa_titipan WHERE is_deleted = 0");
                if ($qExist) {
                    while ($rE = sqlsrv_fetch_array($qExist, SQLSRV_FETCH_ASSOC)) {
                        $key = trim($rE['no_do'] ?? '');
                        if (!empty($key)) {
                            $existingKeys[$key] = true;
                        }
                    }
                }
            }

            $data = [];
            $seq = $offset + 1;
            while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                $noDo = trim($row['no_do'] ?? '');
                $noSC = trim($row['no_sc'] ?? '');
                $prodCode = trim($row['product_code'] ?? '');
                $noBale = trim($row['no_bale'] ?? '');
                $noPacking = trim($row['no_packing'] ?? '');
                $key = $noDo;

                $tglSC = !empty($row['tgl_sc']) ? date('Y-m-d', strtotime($row['tgl_sc'])) : null;
                $totQtyYard = $row['totqtyyard'] !== null ? (float)$row['totqtyyard'] : ($row['do_qty'] !== null ? (float)$row['do_qty'] : null);

                $data[] = [
                    'seq'             => $seq++,
                    'no_do'           => $noDo,
                    'tgl_do'          => !empty($row['tgl_do']) ? date('d/m/Y', strtotime($row['tgl_do'])) : '-',
                    'tgl_do_raw'      => !empty($row['tgl_do']) ? date('Y-m-d', strtotime($row['tgl_do'])) : null,
                    'cust_id'         => trim($row['cust_id'] ?? ''),
                    'cust_name'       => trim($row['cust_name'] ?? ''),
                    'no_sc'           => $noSC,
                    'tgl_sc'          => $tglSC ? date('d/m/Y', strtotime($tglSC)) : '-',
                    'tgl_sc_raw'      => $tglSC,
                    'product_code'    => $prodCode,
                    'product_name'    => trim($row['product_name'] ?? ''),
                    'label_jual'      => trim($row['label_jual'] ?? '-'),
                    'cust_color'      => trim($row['cust_color'] ?? '-'),
                    'do_qty'          => $row['do_qty'] !== null ? (float)$row['do_qty'] : null,
                    'qty_kirim'       => $totQtyYard,
                    'uom'             => trim($row['uom'] ?? ''),
                    'dodesc'          => trim($row['dodesc'] ?? ''),
                    'dodtdesc'        => trim($row['dodtdesc'] ?? ''),
                    'no_packing'      => $noPacking,
                    'no_bale'         => $noBale,
                    'totqtym'         => $row['totqtym'] !== null ? (float)$row['totqtym'] : null,
                    'totqtyyard'      => $totQtyYard,
                    'countbatch'      => $row['countbatch'] !== null ? (int)$row['countbatch'] : null,
                    'already_saved'   => isset($existingKeys[$key]),
                    'unique_key'      => $key,
                ];
            }

            $totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;

            echo json_encode([
                'status'      => 'ok',
                'page'        => $page,
                'limit'       => $limit,
                'total_rows'  => $totalRows,
                'total_pages' => $totalPages,
                'data'        => $data,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memuat data DO dari ERP: ' . $e->getMessage()]);
        }
        break;

    // =====================================================================
    // SUBMIT TITIPAN - Simpan data terpilih ke dbo.sa_titipan
    // =====================================================================
    case 'submit_titipan':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia.']);
            exit;
        }

        $items = json_decode($_POST['items'] ?? '[]', true);
        if (empty($items) || !is_array($items)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 data untuk disimpan.']);
            exit;
        }

        $tglKirim = trim($_POST['tgl_kirim'] ?? '');
        if (empty($tglKirim)) {
            echo json_encode(['status' => 'error', 'message' => 'Tanggal Kirim wajib diisi sebelum menyimpan data.']);
            exit;
        }

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($items as $item) {
            $noDo = trim($item['no_do'] ?? '');
            $noSC = trim($item['no_sc'] ?? '');
            $prodCode = trim($item['product_code'] ?? '');
            $noBale = trim($item['no_bale'] ?? '');
            $noPacking = trim($item['no_packing'] ?? '');

            if (empty($noDo)) continue;

            // Cek duplikat berdasarkan no_do
            $qDup = sqlsrv_query($conn,
                "SELECT TOP 1 id FROM dbo.sa_titipan WHERE no_do = ? AND is_deleted = 0",
                [$noDo]);
            if ($qDup && sqlsrv_fetch_array($qDup, SQLSRV_FETCH_ASSOC)) {
                $skippedCount++;
                continue;
            }

            $qtyKirim = ($item['totqtyyard'] !== null && $item['totqtyyard'] !== '') ? (float)$item['totqtyyard'] : ($item['qty_kirim'] ?? $item['do_qty'] ?? null);

            $sqlInsert = "INSERT INTO dbo.sa_titipan
                (tgl_kirim, no_titipan, tgl_titipan, no_do, tgl_do, cust_id, cust_name,
                 no_sc, tgl_sc, product_code, product_name, label_jual, cust_color, do_qty, qty_kirim,
                 uom, ket_titipan_1, ket_titipan_2, dodesc,
                 no_packing, no_bale, totqtym, totqtyyard, countbatch, dodtdesc,
                 created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

            $stmtInsert = sqlsrv_query($conn, $sqlInsert, [
                $tglKirim,
                trim($item['no_titipan'] ?? '') ?: null,
                !empty($item['tgl_titipan_raw']) ? $item['tgl_titipan_raw'] : null,
                $noDo,
                !empty($item['tgl_do_raw']) ? $item['tgl_do_raw'] : null,
                trim($item['cust_id'] ?? ''),
                trim($item['cust_name'] ?? ''),
                $noSC,
                !empty($item['tgl_sc_raw']) ? $item['tgl_sc_raw'] : null,
                $prodCode,
                trim($item['product_name'] ?? ''),
                trim($item['label_jual'] ?? ''),
                trim($item['cust_color'] ?? ''),
                $item['do_qty'] ?? null,
                $qtyKirim,
                trim($item['uom'] ?? ''),
                trim($item['ket_titipan_1'] ?? ''),
                trim($item['ket_titipan_2'] ?? ''),
                trim($item['dodesc'] ?? ''),
                $noPacking,
                $noBale,
                $item['totqtym'] ?? null,
                $item['totqtyyard'] ?? null,
                $item['countbatch'] ?? null,
                trim($item['dodtdesc'] ?? ''),
                $currentUser['user']
            ]);

            if ($stmtInsert) {
                $insertedCount++;
            }
        }

        $msg = "{$insertedCount} data berhasil disimpan.";
        if ($skippedCount > 0) {
            $msg .= " {$skippedCount} data dilewati karena sudah ada.";
        }

        echo json_encode(['status' => 'ok', 'message' => $msg, 'inserted' => $insertedCount, 'skipped' => $skippedCount]);
        break;

    // =====================================================================
    // UPLOAD DOKUMEN (Packing List / Surat Jalan)
    // =====================================================================
    case 'upload_doc':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia.']);
            exit;
        }

        $docId = (int)($_POST['id'] ?? 0);
        $docType = trim($_POST['doc_type'] ?? ''); // 'packinglist' atau 'suratjalan'
        $inputType = trim($_POST['input_type'] ?? ''); // 'file' atau 'url'

        if ($docId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid.']);
            exit;
        }
        if (!in_array($docType, ['packinglist', 'suratjalan'])) {
            echo json_encode(['status' => 'error', 'message' => 'Tipe dokumen tidak valid.']);
            exit;
        }

        $filePath = null;
        $fileType = null;

        if ($inputType === 'url') {
            $url = trim($_POST['doc_url'] ?? '');
            if (empty($url)) {
                echo json_encode(['status' => 'error', 'message' => 'URL dokumen tidak boleh kosong.']);
                exit;
            }
            $filePath = $url;
            $fileType = 'url';
        } elseif ($inputType === 'file') {
            if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['status' => 'error', 'message' => 'File tidak ditemukan atau gagal diupload.']);
                exit;
            }

            $file = $_FILES['doc_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                echo json_encode(['status' => 'error', 'message' => 'Hanya file PDF yang diperbolehkan.']);
                exit;
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 5MB.']);
                exit;
            }

            $prefix = ($docType === 'packinglist') ? 'PL' : 'SJ';
            $uploadDir = $rootPath . '/uploads/sumber_agung/' . $docType . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = "{$prefix}_" . date('Ymd') . "_{$docId}.pdf";
            $destPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file.']);
                exit;
            }

            $filePath = "/gg_app/uploads/sumber_agung/{$docType}/{$fileName}";
            $fileType = 'file';
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tipe input tidak valid (file/url).']);
            exit;
        }

        // Update database
        $colFile = ($docType === 'packinglist') ? 'packinglist_file' : 'suratjalan_file';
        $colType = ($docType === 'packinglist') ? 'packinglist_type' : 'suratjalan_type';
        $colAt   = ($docType === 'packinglist') ? 'packinglist_at'   : 'suratjalan_at';
        $colBy   = ($docType === 'packinglist') ? 'packinglist_by'   : 'suratjalan_by';

        $sqlUpdate = "UPDATE dbo.sa_titipan SET {$colFile} = ?, {$colType} = ?, {$colAt} = GETDATE(), {$colBy} = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ? AND is_deleted = 0";
        $stmtUp = sqlsrv_query($conn, $sqlUpdate, [$filePath, $fileType, $currentUser['formatted'], $currentUser['user'], $docId]);

        if (!$stmtUp) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal update database: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        $label = ($docType === 'packinglist') ? 'Packing List' : 'Surat Jalan';
        echo json_encode(['status' => 'ok', 'message' => "{$label} berhasil diupload.", 'file_path' => $filePath, 'file_type' => $fileType]);
        break;

    // =====================================================================
    // PUBLISH - Set is_published=1 untuk items terpilih
    // =====================================================================
    case 'publish':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia.']);
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: explode(',', $ids);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), function($v) { return $v > 0; })));

        if (empty($ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 data untuk di-publish.']);
            exit;
        }

        $successCount = 0;
        foreach ($ids as $id) {
            $stmtPub = sqlsrv_query($conn,
                "UPDATE dbo.sa_titipan SET is_published = 1, published_at = GETDATE(), published_by = ?, updated_by = ?, updated_at = GETDATE() WHERE id = ? AND is_deleted = 0 AND is_published = 0",
                [$currentUser['formatted'], $currentUser['user'], $id]);
            if ($stmtPub && sqlsrv_rows_affected($stmtPub) > 0) {
                $successCount++;
            }
        }

        echo json_encode(['status' => 'ok', 'message' => "{$successCount} data berhasil di-publish ke portal.", 'count' => $successCount]);
        break;

    // =====================================================================
    // GET DETAIL - Ambil detail satu baris
    // =====================================================================
    case 'get_detail':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia.']);
            exit;
        }

        $detailId = (int)($_GET['id'] ?? 0);
        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
            exit;
        }

        $qDetail = sqlsrv_query($conn, "SELECT * FROM dbo.sa_titipan WHERE id = ? AND is_deleted = 0", [$detailId]);
        if (!$qDetail || !($rDetail = sqlsrv_fetch_array($qDetail, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
            exit;
        }

        $detail = [
            'id'             => (int)$rDetail['id'],
            'tgl_kirim'      => formatDateSA($rDetail['tgl_kirim']),
            'tgl_kirim_raw'  => !empty($rDetail['tgl_kirim']) ? ($rDetail['tgl_kirim'] instanceof DateTimeInterface ? $rDetail['tgl_kirim']->format('Y-m-d') : date('Y-m-d', strtotime($rDetail['tgl_kirim']))) : null,
            'no_sc'          => trim($rDetail['no_sc'] ?? '-'),
            'tgl_sc'         => formatDateSA($rDetail['tgl_sc']),
            'product_name'   => trim($rDetail['product_name'] ?? '-'),
            'product_code'   => trim($rDetail['product_code'] ?? ''),
            'label_jual'     => trim($rDetail['label_jual'] ?? '-'),
            'cust_color'     => trim($rDetail['cust_color'] ?? '-'),
            'no_titipan'     => trim($rDetail['no_titipan'] ?? '-'),
            'tgl_titipan'    => formatDateSA($rDetail['tgl_titipan']),
            'no_do'          => trim($rDetail['no_do'] ?? '-'),
            'tgl_do'         => formatDateSA($rDetail['tgl_do']),
            'cust_id'        => trim($rDetail['cust_id'] ?? ''),
            'cust_name'      => trim($rDetail['cust_name'] ?? ''),
            'do_qty'         => $rDetail['do_qty'] !== null ? (float)$rDetail['do_qty'] : null,
            'qty_kirim'      => $rDetail['qty_kirim'] !== null ? (float)$rDetail['qty_kirim'] : null,
            'uom'            => trim($rDetail['uom'] ?? '-'),
            'ket_titipan_1'  => trim($rDetail['ket_titipan_1'] ?? ''),
            'ket_titipan_2'  => trim($rDetail['ket_titipan_2'] ?? ''),
            'dodesc'         => trim($rDetail['dodesc'] ?? ''),
            'no_packing'     => trim($rDetail['no_packing'] ?? '-'),
            'no_bale'        => trim($rDetail['no_bale'] ?? '-'),
            'totqtym'        => $rDetail['totqtym'] !== null ? (float)$rDetail['totqtym'] : null,
            'totqtyyard'     => $rDetail['totqtyyard'] !== null ? (float)$rDetail['totqtyyard'] : null,
            'countbatch'     => $rDetail['countbatch'] !== null ? (int)$rDetail['countbatch'] : null,
            'dodtdesc'       => trim($rDetail['dodtdesc'] ?? ''),
            'packinglist_file' => $rDetail['packinglist_file'],
            'packinglist_type' => $rDetail['packinglist_type'],
            'packinglist_at'   => formatDateSA($rDetail['packinglist_at'], 'd/m/Y H:i'),
            'packinglist_by'   => trim($rDetail['packinglist_by'] ?? ''),
            'suratjalan_file'  => $rDetail['suratjalan_file'],
            'suratjalan_type'  => $rDetail['suratjalan_type'],
            'suratjalan_at'    => formatDateSA($rDetail['suratjalan_at'], 'd/m/Y H:i'),
            'suratjalan_by'    => trim($rDetail['suratjalan_by'] ?? ''),
            'is_published'   => (bool)$rDetail['is_published'],
            'is_viewed'      => (bool)$rDetail['is_viewed'],
            'published_by'   => trim($rDetail['published_by'] ?? ''),
            'published_at'   => formatDateSA($rDetail['published_at'], 'd/m/Y H:i'),
            'viewed_by'      => trim($rDetail['viewed_by'] ?? ''),
            'viewed_at'      => formatDateSA($rDetail['viewed_at'], 'd/m/Y H:i'),
        ];

        echo json_encode(['status' => 'ok', 'data' => $detail]);
        break;

    // =====================================================================
    // DELETE DATA (soft delete)
    // =====================================================================
    case 'delete_data':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel sa_titipan belum tersedia.']);
            exit;
        }

        $delId = (int)($_POST['id'] ?? 0);
        if ($delId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
            exit;
        }

        $stmtDel = sqlsrv_query($conn,
            "UPDATE dbo.sa_titipan SET is_deleted = 1, updated_by = ?, updated_at = GETDATE() WHERE id = ? AND is_deleted = 0",
            [$currentUser['user'], $delId]);

        if ($stmtDel && sqlsrv_rows_affected($stmtDel) > 0) {
            echo json_encode(['status' => 'ok', 'message' => 'Data berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
        }
        break;

    // =====================================================================
    // DEFAULT
    // =====================================================================
    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali: ' . htmlspecialchars($action)]);
        break;
}
