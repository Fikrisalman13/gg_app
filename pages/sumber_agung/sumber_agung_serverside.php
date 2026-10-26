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

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// HELPER: IDENTITAS USER
// =========================================================================
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
function formatDateSA($val, $format = 'd/m/Y') {
    if (empty($val)) return '-';
    if ($val instanceof DateTimeInterface) return $val->format($format);
    return date($format, strtotime($val));
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
            $where .= " AND tgl_titipan BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif (!empty($dateFrom)) {
            $where .= " AND tgl_titipan >= ?";
            $params[] = $dateFrom;
        } elseif (!empty($dateTo)) {
            $where .= " AND tgl_titipan <= ?";
            $params[] = $dateTo;
        }

        // Filter status dokumen
        if ($filterDoc === 'belum_komplit') {
            $where .= " AND ((packinglist_file IS NOT NULL AND suratjalan_file IS NULL) OR (packinglist_file IS NULL AND suratjalan_file IS NOT NULL))";
        } elseif ($filterDoc === 'komplit') {
            $where .= " AND packinglist_file IS NOT NULL AND suratjalan_file IS NOT NULL";
        } elseif ($filterDoc === 'tidak_ada') {
            $where .= " AND packinglist_file IS NULL AND suratjalan_file IS NULL";
        }

        // Filter status publish
        if ($filterPub === 'belum_publish') {
            $where .= " AND is_published = 0";
        } elseif ($filterPub === 'published') {
            $where .= " AND is_published = 1 AND is_viewed = 0";
        } elseif ($filterPub === 'sudah_dilihat') {
            $where .= " AND is_published = 1 AND is_viewed = 1";
        }

        $sql = "SELECT * FROM dbo.sa_titipan WHERE {$where} ORDER BY tgl_titipan DESC, id DESC";
        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        $no = 1;

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $hasPL = !empty($row['packinglist_file']);
                $hasSJ = !empty($row['suratjalan_file']);

                // Status dokumen
                if ($hasPL && $hasSJ) {
                    $statusDoc = 'Komplit';
                } elseif ($hasPL || $hasSJ) {
                    $statusDoc = 'Belum Komplit';
                } else {
                    $statusDoc = 'Tidak Ada Data';
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
            $dateCond = " AND tgl_titipan BETWEEN ? AND ?";
            $dateParams = [$dateFrom, $dateTo];
        } elseif (!empty($dateFrom)) {
            $dateCond = " AND tgl_titipan >= ?";
            $dateParams = [$dateFrom];
        } elseif (!empty($dateTo)) {
            $dateCond = " AND tgl_titipan <= ?";
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
    // SEARCH TITIPAN - Query data dari ERP PostgreSQL (READ ONLY)
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

        // Default: bulan berjalan
        if (empty($dateFrom)) $dateFrom = date('Y-m-01');
        if (empty($dateTo))   $dateTo   = date('Y-m-d');

        try {
            $where = " WHERE a.titipandate BETWEEN :date_from AND :date_to ";
            $params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

            if (!empty($keyword)) {
                $term = '%' . $keyword . '%';
                if ($searchField === 'no_titipan') {
                    $where .= " AND a.titipannmbr ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'no_do') {
                    $where .= " AND a.donmbr ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'cust_name') {
                    $where .= " AND (c.cusname ILIKE :kw OR c.cuscode ILIKE :kw) ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'prodcode') {
                    $where .= " AND d.prodcode ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'prodname') {
                    $where .= " AND (d.prodname ILIKE :kw OR d.prodcode ILIKE :kw) ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'label_jual') {
                    $where .= " AND tech.labeljual ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'cust_color') {
                    $where .= " AND tech.cuscolor ILIKE :kw ";
                    $params[':kw'] = $term;
                } elseif ($searchField === 'sonmbr') {
                    $where .= " AND d.sonmbr ILIKE :kw ";
                    $params[':kw'] = $term;
                } else {
                    $where .= " AND (
                        a.titipannmbr ILIKE :kw1 OR
                        a.donmbr ILIKE :kw2 OR
                        c.cusname ILIKE :kw3 OR
                        d.prodname ILIKE :kw4 OR
                        d.sonmbr ILIKE :kw5 OR
                        d.prodcode ILIKE :kw6 OR
                        tech.labeljual ILIKE :kw7 OR
                        tech.cuscolor ILIKE :kw8
                    )";
                    for ($i = 1; $i <= 8; $i++) {
                        $params[":kw{$i}"] = $term;
                    }
                }
            }

            // Count total
            $sqlCount = "
                SELECT COUNT(*) AS total
                FROM whdotitipan AS a
                INNER JOIN whdotitipandt AS b ON a.dotitipanid = b.dotitipanid
                INNER JOIN whdohd AS c ON a.dohdid = c.dohdid
                INNER JOIN whdodt AS d ON c.dohdid = d.dohdid
                INNER JOIN smuom AS e ON b.dlvrstduom = e.uomid AND d.douomid = e.uomid AND d.stduomid = e.uomid AND b.uomid = e.uomid
                LEFT JOIN smprodtechdata AS tech ON d.prodid = tech.prodid
                {$where}
            ";
            $stmtCount = $conn3->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRows = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Data query
            $sqlData = "
                SELECT
                    a.titipannmbr AS no_titipan,
                    a.titipandate AS tgl_titipan,
                    a.donmbr AS no_do,
                    a.dodate AS tgl_do,
                    c.cuscode AS cust_id,
                    c.cusname AS cust_name,
                    d.sonmbr AS no_sc,
                    f.sodate AS tgl_sc,
                    d.prodcode AS product_code,
                    d.prodname AS product_name,
                    tech.labeljual AS label_jual,
                    tech.cuscolor AS cust_color,
                    d.doqty AS do_qty,
                    b.dlvrqty AS qty_kirim,
                    e.uomname AS uom,
                    a.titipandesc AS ket_titipan_1,
                    b.dlvrdesc AS ket_titipan_2,
                    c.dodesc
                FROM whdotitipan AS a
                INNER JOIN whdotitipandt AS b ON a.dotitipanid = b.dotitipanid
                INNER JOIN whdohd AS c ON a.dohdid = c.dohdid
                INNER JOIN whdodt AS d ON c.dohdid = d.dohdid
                INNER JOIN smuom AS e ON b.dlvrstduom = e.uomid AND d.douomid = e.uomid AND d.stduomid = e.uomid AND b.uomid = e.uomid
                LEFT JOIN insohd AS f ON d.sonmbr = f.sonmbr
                LEFT JOIN smprodtechdata AS tech ON d.prodid = tech.prodid
                {$where}
                ORDER BY a.titipannmbr ASC
                OFFSET :offset LIMIT :limit
            ";
            $stmtData = $conn3->prepare($sqlData);
            foreach ($params as $k => $v) {
                $stmtData->bindValue($k, $v);
            }
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->execute();

            // Get existing no_titipan + no_sc combos already in sa_titipan
            $existingKeys = [];
            if ($tableReady) {
                $qExist = sqlsrv_query($conn, "SELECT no_titipan, no_sc, product_code FROM dbo.sa_titipan WHERE is_deleted = 0");
                if ($qExist) {
                    while ($rE = sqlsrv_fetch_array($qExist, SQLSRV_FETCH_ASSOC)) {
                        $key = trim($rE['no_titipan']) . '|' . trim($rE['no_sc']) . '|' . trim($rE['product_code']);
                        $existingKeys[$key] = true;
                    }
                }
            }

            $data = [];
            $seq = $offset + 1;
            while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                $noTitipan = trim($row['no_titipan'] ?? '');
                $noSC = trim($row['no_sc'] ?? '');
                $prodCode = trim($row['product_code'] ?? '');
                $key = "{$noTitipan}|{$noSC}|{$prodCode}";

                // Ambil sodate dari insohd (sudah di-LEFT JOIN di sqlData untuk efisiensi)
                $tglSC = !empty($row['tgl_sc']) ? date('Y-m-d', strtotime($row['tgl_sc'])) : null;

                $data[] = [
                    'seq'           => $seq++,
                    'no_titipan'    => $noTitipan,
                    'tgl_titipan'   => !empty($row['tgl_titipan']) ? date('d/m/Y', strtotime($row['tgl_titipan'])) : '-',
                    'tgl_titipan_raw' => !empty($row['tgl_titipan']) ? date('Y-m-d', strtotime($row['tgl_titipan'])) : null,
                    'no_do'         => trim($row['no_do'] ?? ''),
                    'tgl_do'        => !empty($row['tgl_do']) ? date('d/m/Y', strtotime($row['tgl_do'])) : '-',
                    'tgl_do_raw'    => !empty($row['tgl_do']) ? date('Y-m-d', strtotime($row['tgl_do'])) : null,
                    'cust_id'       => trim($row['cust_id'] ?? ''),
                    'cust_name'     => trim($row['cust_name'] ?? ''),
                    'no_sc'         => $noSC,
                    'tgl_sc'        => $tglSC ? date('d/m/Y', strtotime($tglSC)) : '-',
                    'tgl_sc_raw'    => $tglSC,
                    'product_code'  => $prodCode,
                    'product_name'  => trim($row['product_name'] ?? ''),
                    'label_jual'    => trim($row['label_jual'] ?? '-'),
                    'cust_color'    => trim($row['cust_color'] ?? '-'),
                    'do_qty'        => $row['do_qty'] !== null ? (float)$row['do_qty'] : null,
                    'qty_kirim'     => $row['qty_kirim'] !== null ? (float)$row['qty_kirim'] : null,
                    'uom'           => trim($row['uom'] ?? ''),
                    'ket_titipan_1' => trim($row['ket_titipan_1'] ?? ''),
                    'ket_titipan_2' => trim($row['ket_titipan_2'] ?? ''),
                    'dodesc'        => trim($row['dodesc'] ?? ''),
                    'already_saved' => isset($existingKeys[$key]),
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
            echo json_encode(['status' => 'error', 'message' => 'Gagal memuat data titipan: ' . $e->getMessage()]);
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

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($items as $item) {
            $noTitipan = trim($item['no_titipan'] ?? '');
            $noSC = trim($item['no_sc'] ?? '');
            $prodCode = trim($item['product_code'] ?? '');

            if (empty($noTitipan)) continue;

            // Cek duplikat
            $qDup = sqlsrv_query($conn,
                "SELECT TOP 1 id FROM dbo.sa_titipan WHERE no_titipan = ? AND no_sc = ? AND product_code = ? AND is_deleted = 0",
                [$noTitipan, $noSC, $prodCode]);
            if ($qDup && sqlsrv_fetch_array($qDup, SQLSRV_FETCH_ASSOC)) {
                $skippedCount++;
                continue;
            }

            $sqlInsert = "INSERT INTO dbo.sa_titipan
                (no_titipan, tgl_titipan, no_do, tgl_do, cust_id, cust_name,
                 no_sc, tgl_sc, product_code, product_name, label_jual, cust_color, do_qty, qty_kirim,
                 uom, ket_titipan_1, ket_titipan_2, dodesc, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

            $stmtInsert = sqlsrv_query($conn, $sqlInsert, [
                $noTitipan,
                !empty($item['tgl_titipan_raw']) ? $item['tgl_titipan_raw'] : null,
                trim($item['no_do'] ?? ''),
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
                $item['qty_kirim'] ?? null,
                trim($item['uom'] ?? ''),
                trim($item['ket_titipan_1'] ?? ''),
                trim($item['ket_titipan_2'] ?? ''),
                trim($item['dodesc'] ?? ''),
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
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 10MB.']);
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
