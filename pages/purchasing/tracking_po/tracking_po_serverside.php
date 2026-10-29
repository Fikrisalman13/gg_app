<?php
/**
 * API Backend Tracking PO - Pemantauan Dokumen PO ke Direktur
 * Menangani semua operasi AJAX untuk modul tracking PO.
 * 
 * Aksi yang didukung:
 *  - load_data          : Load data tracking dengan filter (GET)
 *  - search_po          : Search PO dari ERP PostgreSQL fgstatus='O' (GET)
 *  - submit_serah       : Input batch PO yang diserahkan (POST)
 *  - konfirmasi_kembali : Konfirmasi PO sudah kembali + status TTD (POST)
 *  - approve            : Approve PO yang sudah TTD - UI only (POST)
 *  - get_summary        : Hitung summary cards (GET)
 *  - get_log            : Ambil log history per PO (GET)
 *  - view_detail_po     : Ambil detail barang PO dari ERP prpodt (GET)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';   // SQL Server GG
require_once $rootPath . '/koneksi3.php';  // PostgreSQL ERP (READ-ONLY)

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// HELPER: MENDAPATKAN IDENTITAS USER LOGIN LENGKAP
// =========================================================================
if (!function_exists('getTrackingUserIdentity')) {
function getTrackingUserIdentity($conn) {
    $user = trim($_SESSION['UserName'] ?? '');
    $nama = trim($_SESSION['NamaLengkap'] ?? '');
    $dept = '';
    $idDept = null;
    $groupId = $_SESSION['GroupId'] ?? null;
    $groupName = $_SESSION['GroupName'] ?? '';

    if (!empty($conn) && !empty($user)) {
        $q = sqlsrv_query($conn, "SELECT u.GroupId, g.GroupName, e.nama_lengkap, d.id_dept, d.dept 
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
            if (isset($row['id_dept'])) $idDept = (int)$row['id_dept'];
            if (!empty($row['GroupId'])) $groupId = $row['GroupId'];
            if (!empty($row['GroupName'])) $groupName = trim($row['GroupName']);
        }
    }
    if (empty($nama)) $nama = $user;
    $formatted = !empty($dept) ? "{$nama} ({$dept})" : $nama;

    // Evaluasi Hak Akses:
    // 1. Administrator
    $isAdmin = (
        (!empty($groupName) && strcasecmp(trim($groupName), 'Administrator') === 0) ||
        (int)$groupId === 1
    );

    // 2. Purchasing (bisa input & konfirmasi kembali)
    $isPurchasing = (
        stripos($dept, 'Purchasing') !== false ||
        stripos($dept, 'Pembelian') !== false
    );

    // 3. IT (bisa approve)
    $isIT = (
        stripos($dept, 'Information Technology') !== false
    );

    return [
        'user'          => $user,
        'nama'          => $nama,
        'dept'          => $dept,
        'id_dept'       => $idDept,
        'group_id'      => $groupId,
        'group_name'    => $groupName,
        'is_admin'      => $isAdmin,
        'is_purchasing' => $isPurchasing,
        'is_it'         => $isIT,
        'can_input'     => ($isAdmin || $isPurchasing),
        'can_confirm'   => ($isAdmin || $isPurchasing),
        'can_approve'   => ($isAdmin || $isIT),
        'can_delete'    => ($isAdmin || $isPurchasing),
        'formatted'     => $formatted
    ];
}
}

$currentUser = getTrackingUserIdentity($conn);

// Cek login
if (empty($currentUser['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi login tidak valid.']);
    exit;
}

// =========================================================================
// CEK APAKAH TABEL DATABASE SUDAH TERSEDIA
// =========================================================================
$tableReady = false;
if (!empty($conn)) {
    $qCheck = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = 'tracking_po_header'");
    if ($qCheck && sqlsrv_has_rows($qCheck)) {
        $tableReady = true;
    }
}

// =========================================================================
// ROUTER AKSI
// =========================================================================
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {

    // =====================================================================
    // LOAD DATA - Ambil data tracking dengan filter
    // =====================================================================
    case 'load_data':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia. Jalankan schema SQL terlebih dahulu.']);
            exit;
        }

        $filterDateFrom = trim($_GET['date_from'] ?? '');
        $filterDateTo = trim($_GET['date_to'] ?? '');
        $filterStatus = trim($_GET['filter_status'] ?? '');
        $todayStr = date('Y-m-d');

        // Build WHERE clause
        $where = "h.is_deleted = 0";
        $params = [];
        $tglExpr = "ISNULL(d.tanggal_kirim, h.tanggal_kirim)";
        $effDateExpr = "CASE WHEN d.status = 'Tunggu Tanggal Penyerahan' THEN CAST(h.created_at AS DATE) ELSE ISNULL(d.tanggal_kirim, h.tanggal_kirim) END";

        if (!empty($filterDateFrom) && !empty($filterDateTo)) {
            $where .= " AND {$effDateExpr} BETWEEN ? AND ?";
            $params[] = $filterDateFrom;
            $params[] = $filterDateTo;
        } elseif (!empty($filterDateFrom)) {
            $where .= " AND {$effDateExpr} >= ?";
            $params[] = $filterDateFrom;
        } elseif (!empty($filterDateTo)) {
            $where .= " AND {$effDateExpr} <= ?";
            $params[] = $filterDateTo;
        }

        // Status filter - "Belum Kembali" is virtual (Diserahkan + tanggal < today)
        if ($filterStatus === 'Belum Kembali') {
            $where .= " AND d.status = 'Diserahkan' AND {$tglExpr} < CAST(GETDATE() AS DATE)";
        } elseif ($filterStatus === 'Diserahkan') {
            $where .= " AND d.status = 'Diserahkan' AND ({$tglExpr} >= CAST(GETDATE() AS DATE) OR {$tglExpr} IS NULL)";
        } elseif ($filterStatus === 'Tunggu Tanggal Penyerahan') {
            $where .= " AND d.status = 'Tunggu Tanggal Penyerahan'";
        } elseif (!empty($filterStatus)) {
            $where .= " AND d.status = ?";
            $params[] = $filterStatus;
        }

        $sql = "SELECT d.id AS detail_id, d.header_id, d.ponmbr, d.pohdid, d.povendorname, 
                       d.podate, d.status, d.is_signed, d.tanggal_kembali, 
                       d.konfirmasi_oleh, d.konfirmasi_at, d.approved_by, d.approved_at, d.catatan,
                       d.dikirim_ke, d.alamat_kirim,
                       {$tglExpr} AS tanggal_kirim, h.diserahkan_oleh, h.created_at
                FROM dbo.tracking_po_detail d
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
                WHERE {$where}
                ORDER BY {$effDateExpr} DESC, d.id DESC";

        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        $no = 1;

        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Determine display status
                $dbStatus = trim($row['status']);
                $displayStatus = $dbStatus;

                // Auto "Belum Kembali": Diserahkan + tanggal_kirim < hari ini
                if ($dbStatus === 'Diserahkan') {
                    $tanggalKirim = $row['tanggal_kirim'] instanceof DateTimeInterface
                        ? $row['tanggal_kirim']->format('Y-m-d')
                        : (string)$row['tanggal_kirim'];
                    if (!empty($tanggalKirim) && $tanggalKirim < $todayStr) {
                        $displayStatus = 'Belum Kembali';
                    }
                }

                // Format dates
                $tglKirim = '-';
                if (!empty($row['tanggal_kirim'])) {
                    $tglKirim = $row['tanggal_kirim'] instanceof DateTimeInterface
                        ? $row['tanggal_kirim']->format('d/m/Y')
                        : date('d/m/Y', strtotime($row['tanggal_kirim']));
                }

                $tglPo = $row['podate'] instanceof DateTimeInterface
                    ? $row['podate']->format('d/m/Y')
                    : (!empty($row['podate']) ? date('d/m/Y', strtotime($row['podate'])) : '-');

                $tglKembali = null;
                if (!empty($row['tanggal_kembali'])) {
                    $tglKembali = $row['tanggal_kembali'] instanceof DateTimeInterface
                        ? $row['tanggal_kembali']->format('d/m/Y')
                        : date('d/m/Y', strtotime($row['tanggal_kembali']));
                }

                $data[] = [
                    'no'               => $no++,
                    'detail_id'        => (int)$row['detail_id'],
                    'header_id'        => (int)$row['header_id'],
                    'ponmbr'           => trim($row['ponmbr']),
                    'pohdid'           => $row['pohdid'] ? (int)$row['pohdid'] : null,
                    'povendorname'     => trim($row['povendorname'] ?? '-'),
                    'podate'           => $tglPo,
                    'tanggal_kirim'    => $tglKirim,
                    'diserahkan_oleh'  => trim($row['diserahkan_oleh']),
                    'status'           => $displayStatus,
                    'db_status'        => $dbStatus,
                    'is_signed'        => (bool)$row['is_signed'],
                    'tanggal_kembali'  => $tglKembali,
                    'konfirmasi_oleh'  => $row['konfirmasi_oleh'] ? trim($row['konfirmasi_oleh']) : null,
                    'approved_by'      => $row['approved_by'] ? trim($row['approved_by']) : null,
                    'is_approved_erp'  => !empty($row['approved_by']),
                    'dikirim_ke'       => !empty($row['dikirim_ke']) ? trim($row['dikirim_ke']) : null,
                    'alamat_kirim'     => !empty($row['alamat_kirim']) ? trim($row['alamat_kirim']) : null,
                    'catatan'          => $row['catatan'] ? trim($row['catatan']) : null,
                ];
            }
        }

        echo json_encode([
            'status' => 'ok',
            'data' => $data,
            'user' => [
                'can_input'   => $currentUser['can_input'],
                'can_confirm' => $currentUser['can_confirm'],
                'can_approve' => $currentUser['can_approve'],
                'can_delete'  => $currentUser['can_delete'],
                'is_admin'    => $currentUser['is_admin'],
            ]
        ]);
        break;

    // =====================================================================
    // GET SUMMARY - Hitung angka untuk summary cards (sesuai filter tanggal)
    // =====================================================================
    case 'get_summary':
        if (!$tableReady) {
            echo json_encode(['status' => 'ok', 'summary' => ['tunggu_penyerahan' => 0, 'diserahkan' => 0, 'belum_kembali' => 0, 'sudah_ttd' => 0, 'approved' => 0]]);
            exit;
        }

        $filterDateFrom = trim($_GET['date_from'] ?? '');
        $filterDateTo = trim($_GET['date_to'] ?? '');

        $tglExpr = "ISNULL(d.tanggal_kirim, h.tanggal_kirim)";
        $tglCreated = "CAST(h.created_at AS DATE)";

        $hasDateFilter = false;
        $dateCondDetail = "";
        $dateCondTunggu = "";
        $dateParams = [];

        if (!empty($filterDateFrom) && !empty($filterDateTo)) {
            $hasDateFilter = true;
            $dateCondDetail = " AND {$tglExpr} BETWEEN ? AND ?";
            $dateCondTunggu = " AND {$tglCreated} BETWEEN ? AND ?";
            $dateParams = [$filterDateFrom, $filterDateTo];
        } elseif (!empty($filterDateFrom)) {
            $hasDateFilter = true;
            $dateCondDetail = " AND {$tglExpr} >= ?";
            $dateCondTunggu = " AND {$tglCreated} >= ?";
            $dateParams = [$filterDateFrom];
        } elseif (!empty($filterDateTo)) {
            $hasDateFilter = true;
            $dateCondDetail = " AND {$tglExpr} <= ?";
            $dateCondTunggu = " AND {$tglCreated} <= ?";
            $dateParams = [$filterDateTo];
        }

        $summary = ['tunggu_penyerahan' => 0, 'diserahkan' => 0, 'belum_kembali' => 0, 'sudah_kembali' => 0, 'sudah_ttd' => 0, 'approved' => 0];

        // 1. Tunggu Tanggal Penyerahan (pakai tanggal dibuat)
        $sql0 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                 INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                 WHERE h.is_deleted = 0 AND d.status = 'Tunggu Tanggal Penyerahan'" . $dateCondTunggu;
        $q0 = sqlsrv_query($conn, $sql0, $dateParams);
        if ($q0 && ($r0 = sqlsrv_fetch_array($q0, SQLSRV_FETCH_ASSOC))) {
            $summary['tunggu_penyerahan'] = (int)$r0['cnt'];
        }

        // 2. Diserahkan (konsisten: diserahkan yang tanggal kirim >= hari ini)
        if ($hasDateFilter) {
            $sql1 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                     INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                     WHERE h.is_deleted = 0 AND d.status = 'Diserahkan' AND ({$tglExpr} >= CAST(GETDATE() AS DATE) OR {$tglExpr} IS NULL)" . $dateCondDetail;
            $q1 = sqlsrv_query($conn, $sql1, $dateParams);
        } else {
            $sql1 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                     INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                     WHERE h.is_deleted = 0 AND d.status = 'Diserahkan' AND {$tglExpr} = CAST(GETDATE() AS DATE)";
            $q1 = sqlsrv_query($conn, $sql1);
        }
        if ($q1 && ($r1 = sqlsrv_fetch_array($q1, SQLSRV_FETCH_ASSOC))) {
            $summary['diserahkan'] = (int)$r1['cnt'];
        }

        // 3. Belum Kembali (status Diserahkan + tgl_kirim < hari ini)
        $sql2 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                 INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                 WHERE h.is_deleted = 0 AND d.status = 'Diserahkan' AND {$tglExpr} < CAST(GETDATE() AS DATE)" . $dateCondDetail;
        $q2 = sqlsrv_query($conn, $sql2, $dateParams);
        if ($q2 && ($r2 = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC))) {
            $summary['belum_kembali'] = (int)$r2['cnt'];
        }

        // 4. Sudah Kembali (Belum TTD)
        $sql3 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                 INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                 WHERE h.is_deleted = 0 AND d.status = 'Sudah Kembali'" . $dateCondDetail;
        $q3 = sqlsrv_query($conn, $sql3, $dateParams);
        if ($q3 && ($r3 = sqlsrv_fetch_array($q3, SQLSRV_FETCH_ASSOC))) {
            $summary['sudah_kembali'] = (int)$r3['cnt'];
            $summary['sudah_ttd'] = (int)$r3['cnt']; // fallback alias
        }

        // 5. Approved
        $sql4 = "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
                 INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                 WHERE h.is_deleted = 0 AND d.status = 'Approved'" . $dateCondDetail;
        $q4 = sqlsrv_query($conn, $sql4, $dateParams);
        if ($q4 && ($r4 = sqlsrv_fetch_array($q4, SQLSRV_FETCH_ASSOC))) {
            $summary['approved'] = (int)$r4['cnt'];
        }

        echo json_encode(['status' => 'ok', 'summary' => $summary]);
        break;

    // =====================================================================
    // SEARCH PO GRID - Pencarian PO aktif berpaginasi untuk Modal Search PO
    // =====================================================================
    case 'search_po_grid':
        if (!$currentUser['can_input']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk fitur ini.']);
            exit;
        }
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP tidak tersedia.']);
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $searchField = trim($_GET['search_field'] ?? 'all');
        $keyword = trim($_GET['keyword'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        // Cek PO yang saat ini sedang aktif dalam tracking
        $activeTrackedPos = [];
        if ($tableReady) {
            $qAct = sqlsrv_query($conn, "SELECT DISTINCT d.ponmbr FROM dbo.tracking_po_detail d 
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                WHERE d.status != 'Approved' AND h.is_deleted = 0");
            if ($qAct) {
                while ($rAct = sqlsrv_fetch_array($qAct, SQLSRV_FETCH_ASSOC)) {
                    $activeTrackedPos[trim($rAct['ponmbr'])] = true;
                }
            }
        }

        try {
            $where = " WHERE TRIM(h.fgstatus) = 'O' ";
            $params = [];

            if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $where .= " AND DATE(h.podate) >= :date_from ";
                $params[':date_from'] = $dateFrom;
            }
            if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $where .= " AND DATE(h.podate) <= :date_to ";
                $params[':date_to'] = $dateTo;
            }

            if ($keyword !== '') {
                $term = '%' . $keyword . '%';
                if ($searchField === 'ponmbr') {
                    $where .= " AND h.ponmbr ILIKE :term ";
                    $params[':term'] = $term;
                } elseif ($searchField === 'povendorname') {
                    $where .= " AND (h.povendorname ILIKE :term OR h.povendorcode ILIKE :term) ";
                    $params[':term'] = $term;
                } elseif ($searchField === 'requested_by') {
                    $where .= " AND (e.empname ILIKE :term OR e.empnik ILIKE :term) ";
                    $params[':term'] = $term;
                } elseif ($searchField === 'pic_pembuat') {
                    $where .= " AND (u.username ILIKE :term OR h.createdby ILIKE :term) ";
                    $params[':term'] = $term;
                } elseif ($searchField === 'podesc') {
                    $where .= " AND h.podesc ILIKE :term ";
                    $params[':term'] = $term;
                } else {
                    $where .= " AND (
                        h.ponmbr ILIKE :term1 OR 
                        h.povendorname ILIKE :term2 OR 
                        h.povendorcode ILIKE :term3 OR 
                        e.empname ILIKE :term4 OR 
                        e.empnik ILIKE :term5 OR 
                        u.username ILIKE :term6 OR 
                        h.podesc ILIKE :term7
                    ) ";
                    for ($ti = 1; $ti <= 7; $ti++) {
                        $params[':term' . $ti] = $term;
                    }
                }
            }

            // Hitung total data
            $sqlCount = "
                SELECT COUNT(*) AS total
                FROM prpohd h
                LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
                LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
                $where
            ";
            $stmtCount = $conn3->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalFiltered = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Query data berpaginasi
            $sqlData = "
                SELECT 
                    h.pohdid,
                    TRIM(h.ponmbr) AS ponmbr,
                    h.podate,
                    TRIM(h.povendorcode) AS povendorcode,
                    TRIM(h.povendorname) AS povendorname,
                    TRIM(h.podesc) AS podesc,
                    u.username AS pic_pembuat,
                    h.createdby,
                    e.empnik AS requested_by_id,
                    e.empname AS requested_by_name,
                    TRIM(v.vendoraddr) AS vendoraddr,
                    TRIM(h.fgstatus) AS po_status
                FROM prpohd h
                LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
                LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
                LEFT JOIN smvendor v ON h.povendorid = v.vendorid
                $where
                ORDER BY h.podate DESC, h.pohdid DESC
                OFFSET :offset LIMIT :limit
            ";
            $stmtData = $conn3->prepare($sqlData);
            foreach ($params as $k => $v) {
                $stmtData->bindValue($k, $v);
            }
            $stmtData->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmtData->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmtData->execute();

            $data = [];
            $seq = $offset + 1;
            while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                $ponmbr = trim($row['ponmbr']);
                $isAlreadyTracked = isset($activeTrackedPos[$ponmbr]);
                $picPembuat = !empty($row['pic_pembuat']) ? trim($row['pic_pembuat']) : (!empty($row['createdby']) ? trim($row['createdby']) : '-');
                $reqName = !empty($row['requested_by_name']) ? trim($row['requested_by_name']) : '-';
                $reqNik = !empty($row['requested_by_id']) ? trim($row['requested_by_id']) : '';

                $data[] = [
                    'seq'               => $seq++,
                    'pohdid'            => (int)$row['pohdid'],
                    'ponmbr'            => $ponmbr,
                    'podate'            => !empty($row['podate']) ? date('d/m/Y', strtotime($row['podate'])) : '-',
                    'podate_raw'        => !empty($row['podate']) ? date('Y-m-d', strtotime($row['podate'])) : null,
                    'povendorcode'      => trim($row['povendorcode'] ?? ''),
                    'povendorname'      => trim($row['povendorname'] ?? '-'),
                    'vendoraddr'        => trim($row['vendoraddr'] ?? ''),
                    'requested_by_name' => $reqName,
                    'requested_by_id'   => $reqNik,
                    'pic_pembuat'       => $picPembuat,
                    'podesc'            => trim($row['podesc'] ?? '-'),
                    'po_status'         => trim($row['po_status'] ?? 'O'),
                    'already_tracked'   => $isAlreadyTracked,
                ];
            }

            $totalPages = $totalFiltered > 0 ? (int)ceil($totalFiltered / $limit) : 1;

            echo json_encode([
                'status'         => 'ok',
                'page'           => $page,
                'limit'          => $limit,
                'total_rows'     => $totalFiltered,
                'total_pages'    => $totalPages,
                'data'           => $data,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memuat daftar PO: ' . $e->getMessage()]);
        }
        break;

    // =====================================================================
    // SEARCH PO - Cari PO aktif dari ERP PostgreSQL (fgstatus = 'O')
    // =====================================================================
    case 'search_po':
        if (!$currentUser['can_input']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk fitur ini.']);
            exit;
        }
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP tidak tersedia.']);
            exit;
        }

        $keyword = trim($_GET['q'] ?? '');
        if (strlen($keyword) < 2) {
            echo json_encode(['status' => 'ok', 'results' => []]);
            exit;
        }

        $searchTerm = '%' . $keyword . '%';

        try {
            $sqlSearch = "SELECT DISTINCT h.pohdid, TRIM(h.ponmbr) AS ponmbr, h.podate, 
                                 TRIM(h.povendorcode) AS povendorcode, TRIM(h.povendorname) AS povendorname,
                                 u.username AS pic_pembuat
                          FROM prpohd h
                          LEFT JOIN msuser u ON h.poinitiatorid = u.initiatorid
                          WHERE TRIM(h.fgstatus) = 'O'
                            AND (h.ponmbr ILIKE :search1 OR h.povendorname ILIKE :search2)
                          ORDER BY h.podate DESC
                          LIMIT 50";
            $stmtSearch = $conn3->prepare($sqlSearch);
            $stmtSearch->execute([':search1' => $searchTerm, ':search2' => $searchTerm]);
            $results = [];

            while ($row = $stmtSearch->fetch(PDO::FETCH_ASSOC)) {
                $poDate = !empty($row['podate']) ? date('d/m/Y', strtotime($row['podate'])) : '-';
                $results[] = [
                    'id'           => (int)$row['pohdid'],
                    'pohdid'       => (int)$row['pohdid'],
                    'ponmbr'       => trim($row['ponmbr']),
                    'povendorname' => trim($row['povendorname'] ?? '-'),
                    'povendorcode' => trim($row['povendorcode'] ?? ''),
                    'podate'       => $poDate,
                    'podate_raw'   => !empty($row['podate']) ? date('Y-m-d', strtotime($row['podate'])) : null,
                    'pic_pembuat'  => trim($row['pic_pembuat'] ?? '-'),
                    'text'         => trim($row['ponmbr']) . ' — ' . trim($row['povendorname'] ?? '-'),
                ];
            }

            echo json_encode(['status' => 'ok', 'results' => $results]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mencari PO: ' . $e->getMessage()]);
        }
        break;

    // =====================================================================
    // SUBMIT SERAH - Input batch PO yang diserahkan ke direktur
    // =====================================================================
    case 'submit_serah':
        if (!$currentUser['can_input']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk input pengiriman PO.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $tanggalKirim = trim($_POST['tanggal_kirim'] ?? '');
        $dikirimKe = trim($_POST['dikirim_ke'] ?? '');
        $alamatKirim = trim($_POST['alamat_kirim'] ?? '');
        $poItems = json_decode($_POST['po_items'] ?? '[]', true);

        if (empty($dikirimKe)) {
            $dikirimKe = 'PT.SUM';
        }
        if (empty($alamatKirim)) {
            if ($dikirimKe === 'PT.SUM') {
                $alamatKirim = 'Jl. Desa Tarajusari, No.8, Tarajusari, Banjaran, Kab.Bandung';
            } elseif ($dikirimKe === 'Sanso Berjaya Texindo/Toko Sumber SERAGAM') {
                $alamatKirim = 'Jl. Waas No.5B, Batununggal, Sebelah JNT, UP Ibu Verty, Telp : 022 87313631';
            } elseif ($dikirimKe === 'Diambil ke Supplier') {
                $alamatKirim = 'Diambil langsung ke supplier masing-masing';
            }
        }

        if (empty($poItems) || !is_array($poItems)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk diserahkan.']);
            exit;
        }

        // Tentukan apakah ada tanggal kirim atau status Tunggu Tanggal Penyerahan
        $hasTanggal = false;
        $dbTanggalKirim = null;
        $initialStatus = 'Tunggu Tanggal Penyerahan';
        $logKeterangan = 'PO dicatat dalam tracking (Tunggu Tanggal Penyerahan)';

        if (!empty($tanggalKirim)) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalKirim)) {
                echo json_encode(['status' => 'error', 'message' => 'Format tanggal kirim tidak valid (YYYY-MM-DD).']);
                exit;
            }
            $hasTanggal = true;
            $dbTanggalKirim = $tanggalKirim;
            $initialStatus = 'Diserahkan';
            $logKeterangan = 'PO diserahkan ke direktur pada ' . $tanggalKirim;
        }

        // Cek duplikat: apakah ada PO yang sudah pernah diserahkan dan belum Approved
        $duplikat = [];
        foreach ($poItems as $item) {
            $ponmbr = trim($item['ponmbr'] ?? '');
            if (empty($ponmbr)) continue;
            $qDup = sqlsrv_query($conn, "SELECT TOP 1 d.ponmbr FROM dbo.tracking_po_detail d 
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
                WHERE d.ponmbr = ? AND d.status != 'Approved' AND h.is_deleted = 0", [$ponmbr]);
            if ($qDup && sqlsrv_fetch_array($qDup, SQLSRV_FETCH_ASSOC)) {
                $duplikat[] = $ponmbr;
            }
        }
        if (!empty($duplikat)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'PO berikut sudah ada dalam tracking dan belum selesai: ' . implode(', ', $duplikat)
            ]);
            exit;
        }

        // Insert header
        $sqlHeader = "INSERT INTO dbo.tracking_po_header (tanggal_kirim, diserahkan_oleh, dikirim_ke, alamat_kirim, created_by, created_at)
                      OUTPUT INSERTED.id
                      VALUES (?, ?, ?, ?, ?, GETDATE())";
        $stmtHeader = sqlsrv_query($conn, $sqlHeader, [
            $dbTanggalKirim,
            $currentUser['formatted'],
            $dikirimKe,
            $alamatKirim,
            $currentUser['user']
        ]);

        if (!$stmtHeader) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan header: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        // Get inserted header ID
        $headerId = 0;
        if ($rId = sqlsrv_fetch_array($stmtHeader, SQLSRV_FETCH_ASSOC)) {
            $headerId = (int)$rId['id'];
        }

        if ($headerId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mendapatkan ID header.']);
            exit;
        }

        // Insert details
        $insertedCount = 0;
        foreach ($poItems as $item) {
            $ponmbr = trim($item['ponmbr'] ?? '');
            if (empty($ponmbr)) continue;

            $pohdid = !empty($item['pohdid']) ? (int)$item['pohdid'] : null;
            $vendor = trim($item['povendorname'] ?? '-');
            $podate = !empty($item['podate_raw']) ? $item['podate_raw'] : null;

            // Khusus opsi Diambil ke Supplier, ambil alamat dari master vendor
            $itemAlamat = $alamatKirim;
            if ($dikirimKe === 'Diambil ke Supplier') {
                $itemAlamat = !empty($item['vendoraddr']) ? trim($item['vendoraddr']) : '';
                if (empty($itemAlamat) && isset($conn3) && $conn3 && !empty($pohdid)) {
                    try {
                        $qAddr = $conn3->prepare("SELECT TRIM(v.vendoraddr) AS vendoraddr FROM prpohd h LEFT JOIN smvendor v ON h.povendorid = v.vendorid WHERE h.pohdid = :pohdid LIMIT 1");
                        $qAddr->execute([':pohdid' => $pohdid]);
                        $rAddr = $qAddr->fetch(PDO::FETCH_ASSOC);
                        if (!empty($rAddr['vendoraddr'])) {
                            $itemAlamat = trim($rAddr['vendoraddr']);
                        }
                    } catch (Exception $e) {}
                }
                if (empty($itemAlamat)) {
                    $itemAlamat = !empty($alamatKirim) ? $alamatKirim : 'Alamat vendor belum tercatat di ERP';
                }
            }

            $sqlDetail = "INSERT INTO dbo.tracking_po_detail (header_id, ponmbr, pohdid, povendorname, podate, tanggal_kirim, dikirim_ke, alamat_kirim, status, created_at)
                          OUTPUT INSERTED.id
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
            $stmtDetail = sqlsrv_query($conn, $sqlDetail, [$headerId, $ponmbr, $pohdid, $vendor, $podate, $dbTanggalKirim, $dikirimKe, $itemAlamat, $initialStatus]);

            if ($stmtDetail) {
                // Get detail ID for log
                $detailId = 0;
                if ($rDetId = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
                    $detailId = (int)$rDetId['id'];
                }

                // Insert log
                if ($detailId > 0) {
                    $itemLogKeterangan = $logKeterangan . (!empty($dikirimKe) ? " [Dikirim ke: {$dikirimKe}]" : "");
                    sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                        VALUES (?, ?, NULL, ?, ?, GETDATE(), ?)", [
                        $detailId,
                        ($hasTanggal ? 'Diserahkan' : 'Catat Tracking'),
                        $initialStatus,
                        $currentUser['formatted'],
                        $itemLogKeterangan
                    ]);
                }
                $insertedCount++;
            }
        }

        $suksesMsg = $hasTanggal 
            ? "{$insertedCount} PO berhasil dicatat sebagai diserahkan."
            : "{$insertedCount} PO berhasil dicatat dengan status Tunggu Tanggal Penyerahan.";

        echo json_encode([
            'status' => 'ok',
            'message' => $suksesMsg,
            'header_id' => $headerId
        ]);
        break;

    // =====================================================================
    // SET TANGGAL PENYERAHAN - Input tanggal penyerahan menyusul
    // =====================================================================
    case 'set_tanggal_penyerahan':
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $tanggalPenyerahan = trim($_POST['tanggal_penyerahan'] ?? $_POST['tanggal_kirim'] ?? '');
        if (empty($tanggalPenyerahan) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPenyerahan)) {
            echo json_encode(['status' => 'error', 'message' => 'Format tanggal penyerahan tidak valid (YYYY-MM-DD).']);
            exit;
        }

        $detailIds = $_POST['detail_ids'] ?? [];
        if (empty($detailIds) && !empty($_POST['detail_id'])) {
            $detailIds = [(int)$_POST['detail_id']];
        }
        if (is_string($detailIds)) {
            $detailIds = json_decode($detailIds, true) ?: explode(',', $detailIds);
        }
        $detailIds = array_values(array_unique(array_filter(array_map('intval', (array)$detailIds), function($v) { return $v > 0; })));

        if (empty($detailIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk diinput tanggal penyerahannya.']);
            exit;
        }

        $successCount = 0;
        foreach ($detailIds as $dId) {
            $qCurrent = sqlsrv_query($conn, "SELECT d.id, d.header_id, d.status, d.ponmbr, h.tanggal_kirim AS header_tgl 
                FROM dbo.tracking_po_detail d 
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                WHERE d.id = ? AND h.is_deleted = 0", [$dId]);
            if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
                continue;
            }

            $oldStatus = trim($rCurrent['status']);
            $ponmbr = trim($rCurrent['ponmbr']);
            $headerId = (int)$rCurrent['header_id'];

            // Update tanggal_kirim pada detail dan ubah status selalu menjadi Diserahkan
            $sqlUpd = "UPDATE dbo.tracking_po_detail 
                       SET tanggal_kirim = ?, status = 'Diserahkan'
                       WHERE id = ?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpd, [$tanggalPenyerahan, $dId]);

            if ($stmtUpd) {
                // Update header jika header tanggal_kirim masih NULL
                if (empty($rCurrent['header_tgl'])) {
                    sqlsrv_query($conn, "UPDATE dbo.tracking_po_header SET tanggal_kirim = ? WHERE id = ? AND tanggal_kirim IS NULL", [$tanggalPenyerahan, $headerId]);
                }

                // Log aktivitas
                sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                    VALUES (?, 'Set Tanggal Penyerahan', ?, 'Diserahkan', ?, GETDATE(), ?)", [
                    $dId, $oldStatus, $currentUser['formatted'],
                    "Tanggal penyerahan diinput ({$tanggalPenyerahan}), status berubah menjadi Diserahkan."
                ]);

                $successCount++;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'message' => "{$successCount} PO berhasil diupdate tanggal penyerahannya dan statusnya menjadi Diserahkan.",
            'count' => $successCount
        ]);
        break;

    // =====================================================================
    // KONFIRMASI KEMBALI - Purchasing konfirmasi PO sudah kembali
    // Jika is_signed = 1: AUTO APPROVE ke ERP PostgreSQL & status -> Approved
    // Jika is_signed = 0: status -> Sudah Kembali (bisa diserahkan kembali)
    // =====================================================================
    case 'konfirmasi_kembali':
        if (!$currentUser['can_confirm']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk konfirmasi.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailId = (int)($_POST['detail_id'] ?? 0);
        $isSigned = (int)($_POST['is_signed'] ?? 0); // 1 = sudah TTD, 0 = belum TTD
        $catatan = trim($_POST['catatan'] ?? '');

        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID detail tidak valid.']);
            exit;
        }

        // Cek status saat ini
        $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.approved_by FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE d.id = ? AND h.is_deleted = 0", [$detailId]);
        if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan.']);
            exit;
        }

        $oldStatus = trim($rCurrent['status']);
        $ponmbr = trim($rCurrent['ponmbr']);
        $today = date('Y-m-d');

        if ($oldStatus !== 'Diserahkan') {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak dalam status Diserahkan (status saat ini: {$oldStatus})."]);
            exit;
        }

        if ($isSigned) {
            // ─── AUTO APPROVE: Dokumen kembali & sudah TTD ───────────────────
            if (!isset($conn3) || !$conn3) {
                echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP (PostgreSQL) tidak tersedia untuk Auto Approve.']);
                exit;
            }

            date_default_timezone_set('Asia/Jakarta');
            $approveDate = date('Y-m-d H:i:s');
            $approveBy = 'DAVID';

            // 1. Update ke database ERP PostgreSQL (prpohd)
            try {
                $conn3->beginTransaction();

                $sqlErp = "UPDATE prpohd 
                              SET fgstatus    = 'V', 
                                  approvedate = :approvedate, 
                                  approveby   = :approveby 
                            WHERE TRIM(ponmbr) = :ponmbr";
                $stmtErp = $conn3->prepare($sqlErp);
                $stmtErp->execute([
                    ':approvedate' => $approveDate,
                    ':approveby'   => $approveBy,
                    ':ponmbr'      => $ponmbr
                ]);

                if ($stmtErp->rowCount() === 0) {
                    $conn3->rollBack();
                    echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak ditemukan atau gagal di-approve di database ERP."]);
                    exit;
                }

                $conn3->commit();
            } catch (Exception $e) {
                if ($conn3->inTransaction()) {
                    $conn3->rollBack();
                }
                echo json_encode(['status' => 'error', 'message' => 'Gagal Auto Approve ke database ERP: ' . $e->getMessage()]);
                exit;
            }

            // 2. Update status ke Approved di database SQL Server lokal
            $sqlUpdate = "UPDATE dbo.tracking_po_detail 
                          SET status = 'Approved', is_signed = 1, tanggal_kembali = ?, 
                              konfirmasi_oleh = ?, konfirmasi_at = GETDATE(), 
                              approved_by = ?, approved_at = GETDATE(), catatan = ?
                          WHERE id = ?";
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [
                $today,
                $currentUser['formatted'],
                $currentUser['formatted'],
                $catatan,
                $detailId
            ]);

            if (!$stmtUpdate) {
                // Rollback ERP jika update SQL Server lokal gagal
                try {
                    $conn3->beginTransaction();
                    $rollbackErp = $conn3->prepare("UPDATE prpohd SET fgstatus = 'O', approvedate = NULL, approveby = NULL WHERE TRIM(ponmbr) = :ponmbr");
                    $rollbackErp->execute([':ponmbr' => $ponmbr]);
                    $conn3->commit();
                } catch (Exception $ex) {}

                echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate status tracking: ' . print_r(sqlsrv_errors(), true)]);
                exit;
            }

            // 3. Insert Log
            $ketLog = "Dokumen PO {$ponmbr} sudah kembali dan ditandatangani. Auto Approved di ERP (fgstatus='V', approveby='{$approveBy}').";
            if (!empty($catatan)) $ketLog .= " Catatan: {$catatan}";

            sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                VALUES (?, 'Konfirmasi Kembali', ?, 'Approved', ?, GETDATE(), ?)", [
                $detailId, $oldStatus, $currentUser['formatted'], $ketLog
            ]);

            echo json_encode([
                'status' => 'ok',
                'message' => "PO {$ponmbr} berhasil dikonfirmasi dan Auto Approved di ERP."
            ]);
        } else {
            // ─── BELUM DITANDATANGANI: Status menjadi 'Sudah Kembali' ────────
            $sqlUpdate = "UPDATE dbo.tracking_po_detail 
                          SET status = 'Sudah Kembali', is_signed = 0, tanggal_kembali = ?, 
                              konfirmasi_oleh = ?, konfirmasi_at = GETDATE(), catatan = ?
                          WHERE id = ?";
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [
                $today,
                $currentUser['formatted'],
                $catatan,
                $detailId
            ]);

            if (!$stmtUpdate) {
                echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
                exit;
            }

            // Insert Log
            $ketLog = "Dokumen PO {$ponmbr} sudah kembali tapi BELUM ditandatangani.";
            if (!empty($catatan)) $ketLog .= " Catatan: {$catatan}";

            sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                VALUES (?, 'Konfirmasi Kembali', ?, 'Sudah Kembali', ?, GETDATE(), ?)", [
                $detailId, $oldStatus, $currentUser['formatted'], $ketLog
            ]);

            echo json_encode([
                'status' => 'ok',
                'message' => "PO {$ponmbr} berhasil dikonfirmasi: Sudah Kembali (Belum TTD)."
            ]);
        }
        break;

    // =====================================================================
    // BATCH KONFIRMASI KEMBALI - Konfirmasi banyak PO sekaligus
    // =====================================================================
    case 'batch_konfirmasi_kembali':
        if (!$currentUser['can_confirm']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk konfirmasi.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailIds = $_POST['detail_ids'] ?? [];
        if (is_string($detailIds)) {
            $detailIds = json_decode($detailIds, true) ?: explode(',', $detailIds);
        }
        $detailIds = array_values(array_unique(array_filter(array_map('intval', (array)$detailIds), function($v) { return $v > 0; })));

        if (empty($detailIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk dikonfirmasi.']);
            exit;
        }

        $isSigned = (int)($_POST['is_signed'] ?? 0); // 1 = sudah TTD, 0 = belum TTD
        $today = date('Y-m-d');
        $successCount = 0;

        if ($isSigned) {
            // Auto Approve Massal ke ERP PostgreSQL
            if (!isset($conn3) || !$conn3) {
                echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP tidak tersedia untuk Auto Approve.']);
                exit;
            }

            date_default_timezone_set('Asia/Jakarta');
            $approveDate = date('Y-m-d H:i:s');
            $approveBy = 'DAVID';

            foreach ($detailIds as $dId) {
                $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.approved_by FROM dbo.tracking_po_detail d 
                    INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                    WHERE d.id = ? AND h.is_deleted = 0", [$dId]);
                if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
                    continue;
                }

                $oldStatus = trim($rCurrent['status']);
                $ponmbr = trim($rCurrent['ponmbr']);

                if ($oldStatus !== 'Diserahkan') {
                    continue;
                }

                // Update ERP
                try {
                    $conn3->beginTransaction();
                    $stmtErp = $conn3->prepare("UPDATE prpohd SET fgstatus = 'V', approvedate = :approvedate, approveby = :approveby WHERE TRIM(ponmbr) = :ponmbr");
                    $stmtErp->execute([
                        ':approvedate' => $approveDate,
                        ':approveby'   => $approveBy,
                        ':ponmbr'      => $ponmbr
                    ]);
                    if ($stmtErp->rowCount() === 0) {
                        $conn3->rollBack();
                        continue;
                    }
                    $conn3->commit();
                } catch (Exception $e) {
                    if ($conn3->inTransaction()) {
                        $conn3->rollBack();
                    }
                    continue;
                }

                $sqlUpdate = "UPDATE dbo.tracking_po_detail 
                              SET status = 'Approved', is_signed = 1, tanggal_kembali = ?, 
                                  konfirmasi_oleh = ?, konfirmasi_at = GETDATE(), 
                                  approved_by = ?, approved_at = GETDATE()
                              WHERE id = ?";
                $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [
                    $today,
                    $currentUser['formatted'],
                    $currentUser['formatted'],
                    $dId
                ]);

                if ($stmtUpdate) {
                    $ketLog = "Konfirmasi massal: Dokumen PO {$ponmbr} sudah kembali dan ditandatangani. Auto Approved di ERP (fgstatus='V', approveby='{$approveBy}').";
                    sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                        VALUES (?, 'Konfirmasi Kembali', ?, 'Approved', ?, GETDATE(), ?)", [
                        $dId, $oldStatus, $currentUser['formatted'], $ketLog
                    ]);
                    $successCount++;
                } else {
                    // Rollback ERP jika update SQL Server gagal
                    try {
                        $conn3->beginTransaction();
                        $rollbackErp = $conn3->prepare("UPDATE prpohd SET fgstatus = 'O', approvedate = NULL, approveby = NULL WHERE TRIM(ponmbr) = :ponmbr");
                        $rollbackErp->execute([':ponmbr' => $ponmbr]);
                        $conn3->commit();
                    } catch (Exception $ex) {}
                }
            }

            echo json_encode([
                'status' => 'ok',
                'message' => "{$successCount} PO berhasil dikonfirmasi dan Auto Approved di ERP.",
                'count' => $successCount
            ]);
        } else {
            // Konfirmasi massal belum TTD -> status menjadi 'Sudah Kembali'
            foreach ($detailIds as $dId) {
                $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr FROM dbo.tracking_po_detail d 
                    INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                    WHERE d.id = ? AND h.is_deleted = 0", [$dId]);
                if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
                    continue;
                }

                $oldStatus = trim($rCurrent['status']);
                $ponmbr = trim($rCurrent['ponmbr']);

                if ($oldStatus !== 'Diserahkan') {
                    continue;
                }

                $sqlUpdate = "UPDATE dbo.tracking_po_detail 
                              SET status = 'Sudah Kembali', is_signed = 0, tanggal_kembali = ?, 
                                  konfirmasi_oleh = ?, konfirmasi_at = GETDATE()
                              WHERE id = ?";
                $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [
                    $today,
                    $currentUser['formatted'],
                    $dId
                ]);

                if ($stmtUpdate) {
                    $ketLog = "Konfirmasi massal: Dokumen PO {$ponmbr} sudah kembali tapi BELUM ditandatangani.";
                    sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                        VALUES (?, 'Konfirmasi Kembali', ?, 'Sudah Kembali', ?, GETDATE(), ?)", [
                        $dId, $oldStatus, $currentUser['formatted'], $ketLog
                    ]);
                    $successCount++;
                }
            }

            echo json_encode([
                'status' => 'ok',
                'message' => "{$successCount} PO berhasil dikonfirmasi sebagai Sudah Kembali.",
                'count' => $successCount
            ]);
        }
        break;

    // =====================================================================
    // SERAHKAN KEMBALI - Purchasing serahkan kembali PO ke Direktur
    // Khusus PO berstatus 'Sudah Kembali' (kembali belum ditandatangani)
    // =====================================================================
    case 'serahkan_kembali':
        if (!$currentUser['can_confirm']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menyerahkan kembali PO.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailId = (int)($_POST['detail_id'] ?? 0);
        $tanggalPenyerahan = trim($_POST['tanggal_penyerahan'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID detail tidak valid.']);
            exit;
        }

        if (empty($tanggalPenyerahan)) {
            $tanggalPenyerahan = date('Y-m-d');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPenyerahan)) {
            echo json_encode(['status' => 'error', 'message' => 'Format tanggal penyerahan tidak valid (harus YYYY-MM-DD).']);
            exit;
        }

        // Cek status saat ini
        $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.catatan FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE d.id = ? AND h.is_deleted = 0", [$detailId]);
        if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan.']);
            exit;
        }

        $oldStatus = trim($rCurrent['status']);
        $ponmbr = trim($rCurrent['ponmbr']);

        if ($oldStatus !== 'Sudah Kembali') {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak dalam status 'Sudah Kembali' (status saat ini: {$oldStatus})."]);
            exit;
        }

        $catatanUpdate = !empty($catatan) ? $catatan : $rCurrent['catatan'];

        $sqlUpd = "UPDATE dbo.tracking_po_detail 
                   SET status = 'Diserahkan', 
                       tanggal_kirim = ?, 
                       tanggal_kembali = NULL, 
                       konfirmasi_oleh = NULL, 
                       konfirmasi_at = NULL, 
                       is_signed = 0,
                       catatan = ?
                   WHERE id = ?";
        $stmtUpd = sqlsrv_query($conn, $sqlUpd, [$tanggalPenyerahan, $catatanUpdate, $detailId]);

        if (!$stmtUpd) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyerahkan kembali PO: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        $tglFmt = date('d/m/Y', strtotime($tanggalPenyerahan));
        $ketLog = "PO {$ponmbr} diserahkan kembali ke Direktur pada tanggal {$tglFmt}.";
        if (!empty($catatan)) $ketLog .= " Catatan: {$catatan}";

        sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
            VALUES (?, 'Serahkan Kembali', 'Sudah Kembali', 'Diserahkan', ?, GETDATE(), ?)", [
            $detailId, $currentUser['formatted'], $ketLog
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => "PO {$ponmbr} berhasil diserahkan kembali ke Direktur (status menjadi Diserahkan)."
        ]);
        break;

    // =====================================================================
    // BATCH SERAHKAN KEMBALI - Serahkan kembali banyak PO sekaligus
    // =====================================================================
    case 'batch_serahkan_kembali':
        if (!$currentUser['can_confirm']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk aksi ini.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailIds = $_POST['detail_ids'] ?? [];
        if (is_string($detailIds)) {
            $detailIds = json_decode($detailIds, true) ?: explode(',', $detailIds);
        }
        $detailIds = array_values(array_unique(array_filter(array_map('intval', (array)$detailIds), function($v) { return $v > 0; })));

        if (empty($detailIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk diserahkan kembali.']);
            exit;
        }

        $tanggalPenyerahan = trim($_POST['tanggal_penyerahan'] ?? '');
        if (empty($tanggalPenyerahan)) {
            $tanggalPenyerahan = date('Y-m-d');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPenyerahan)) {
            echo json_encode(['status' => 'error', 'message' => 'Format tanggal penyerahan tidak valid (harus YYYY-MM-DD).']);
            exit;
        }

        $catatan = trim($_POST['catatan'] ?? '');
        $tglFmt = date('d/m/Y', strtotime($tanggalPenyerahan));
        $successCount = 0;

        foreach ($detailIds as $dId) {
            $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.catatan FROM dbo.tracking_po_detail d 
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                WHERE d.id = ? AND h.is_deleted = 0", [$dId]);
            if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
                continue;
            }

            $oldStatus = trim($rCurrent['status']);
            $ponmbr = trim($rCurrent['ponmbr']);

            if ($oldStatus !== 'Sudah Kembali') {
                continue;
            }

            $catatanUpdate = !empty($catatan) ? $catatan : $rCurrent['catatan'];

            $sqlUpd = "UPDATE dbo.tracking_po_detail 
                       SET status = 'Diserahkan', 
                           tanggal_kirim = ?, 
                           tanggal_kembali = NULL, 
                           konfirmasi_oleh = NULL, 
                           konfirmasi_at = NULL, 
                           is_signed = 0,
                           catatan = ?
                       WHERE id = ?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpd, [$tanggalPenyerahan, $catatanUpdate, $dId]);

            if ($stmtUpd) {
                $ketLog = "Penyerahan kembali massal: PO {$ponmbr} diserahkan kembali ke Direktur pada tanggal {$tglFmt}.";
                if (!empty($catatan)) $ketLog .= " Catatan: {$catatan}";

                sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                    VALUES (?, 'Serahkan Kembali', 'Sudah Kembali', 'Diserahkan', ?, GETDATE(), ?)", [
                    $dId, $currentUser['formatted'], $ketLog
                ]);
                $successCount++;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'message' => "{$successCount} PO berhasil diserahkan kembali ke Direktur (status menjadi Diserahkan).",
            'count' => $successCount
        ]);
        break;

    // =====================================================================
    // APPROVE - IT approve PO yang sudah TTD atau Tunggu Tanggal Penyerahan
    // =====================================================================
    case 'approve':
        if (!$currentUser['can_approve']) {
            echo json_encode(['status' => 'error', 'message' => 'Hanya IT dan Admin yang dapat meng-approve.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP (PostgreSQL) tidak tersedia.']);
            exit;
        }

        $detailId = (int)($_POST['detail_id'] ?? 0);
        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID detail tidak valid.']);
            exit;
        }

        // Cek status saat ini
        $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.approved_by FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE d.id = ? AND h.is_deleted = 0", [$detailId]);
        if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan.']);
            exit;
        }

        $oldStatus = trim($rCurrent['status']);
        $ponmbr = trim($rCurrent['ponmbr']);

        if (!empty($rCurrent['approved_by'])) {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} sudah pernah di-approve."]);
            exit;
        }

        if (!in_array($oldStatus, ['Sudah TTD', 'Tunggu Tanggal Penyerahan'])) {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} harus berstatus 'Sudah TTD' atau 'Tunggu Tanggal Penyerahan' untuk di-approve (status saat ini: {$oldStatus})."]);
            exit;
        }

        date_default_timezone_set('Asia/Jakarta');
        $approveDate = date('Y-m-d H:i:s');
        $approveBy = 'DAVID';

        // 1. Update ke database ERP PostgreSQL (prpohd)
        try {
            $conn3->beginTransaction();

            $sqlErp = "UPDATE prpohd 
                          SET fgstatus    = 'V', 
                              approvedate = :approvedate, 
                              approveby   = :approveby 
                        WHERE TRIM(ponmbr) = :ponmbr";
            $stmtErp = $conn3->prepare($sqlErp);
            $stmtErp->execute([
                ':approvedate' => $approveDate,
                ':approveby'   => $approveBy,
                ':ponmbr'      => $ponmbr
            ]);

            if ($stmtErp->rowCount() === 0) {
                $conn3->rollBack();
                echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak ditemukan atau gagal diupdate di database ERP."]);
                exit;
            }

            $conn3->commit();
        } catch (Exception $e) {
            if ($conn3->inTransaction()) {
                $conn3->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Gagal update database ERP: ' . $e->getMessage()]);
            exit;
        }

        // 2. Update status di database SQL Server lokal
        // Jika Sudah TTD -> status jadi Approved. Jika Tunggu Tanggal Penyerahan -> status tetap Tunggu Tanggal Penyerahan (namun tercatat approved_by)
        if ($oldStatus === 'Sudah TTD') {
            $sqlApprove = "UPDATE dbo.tracking_po_detail 
                           SET status = 'Approved', approved_by = ?, approved_at = GETDATE()
                           WHERE id = ?";
            $targetStatus = 'Approved';
        } else {
            $sqlApprove = "UPDATE dbo.tracking_po_detail 
                           SET approved_by = ?, approved_at = GETDATE()
                           WHERE id = ?";
            $targetStatus = 'Tunggu Tanggal Penyerahan';
        }

        $stmtApprove = sqlsrv_query($conn, $sqlApprove, [$currentUser['formatted'], $detailId]);

        if (!$stmtApprove) {
            // Rollback ERP jika update SQL Server gagal
            try {
                $conn3->beginTransaction();
                $rollbackErp = $conn3->prepare("UPDATE prpohd SET fgstatus = 'O', approvedate = NULL, approveby = NULL WHERE TRIM(ponmbr) = :ponmbr");
                $rollbackErp->execute([':ponmbr' => $ponmbr]);
                $conn3->commit();
            } catch (Exception $ex) {}

            echo json_encode(['status' => 'error', 'message' => 'Gagal meng-approve di database tracking: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        // 3. Insert log
        $logKet = ($oldStatus === 'Sudah TTD')
            ? "PO {$ponmbr} di-approve oleh IT (ERP diupdate fgstatus='V', approvedate={$approveDate}, approveby={$approveBy})."
            : "PO {$ponmbr} berstatus Tunggu Tanggal Penyerahan telah di-approve oleh IT di ERP (fgstatus='V'). Status tetap Tunggu Tanggal Penyerahan hingga diserahkan fisik.";

        sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
            VALUES (?, 'Approve', ?, ?, ?, GETDATE(), ?)", [
            $detailId, $oldStatus, $targetStatus, $currentUser['formatted'],
            $logKet
        ]);

        $respMsg = ($oldStatus === 'Sudah TTD')
            ? "PO {$ponmbr} berhasil di-approve di sistem Tracking & ERP."
            : "PO {$ponmbr} berhasil di-approve di sistem ERP (status tetap Tunggu Tanggal Penyerahan sampai dokumen fisik diserahkan).";

        echo json_encode([
            'status' => 'ok',
            'message' => $respMsg
        ]);
        break;

    // =====================================================================
    // BATCH APPROVE - IT approve banyak PO sekaligus (Sinkronisasi ke ERP PostgreSQL & SQL Server)
    // =====================================================================
    case 'batch_approve':
        if (!$currentUser['can_approve']) {
            echo json_encode(['status' => 'error', 'message' => 'Hanya IT dan Admin yang dapat meng-approve PO.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP (PostgreSQL) tidak tersedia.']);
            exit;
        }

        $detailIds = $_POST['detail_ids'] ?? [];
        if (is_string($detailIds)) {
            $detailIds = json_decode($detailIds, true) ?: explode(',', $detailIds);
        }
        $detailIds = array_values(array_unique(array_filter(array_map('intval', (array)$detailIds), function($v) { return $v > 0; })));

        if (empty($detailIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk di-approve.']);
            exit;
        }

        date_default_timezone_set('Asia/Jakarta');
        $approveDate = date('Y-m-d H:i:s');
        $approveBy = 'DAVID';

        $successCount = 0;
        $failedPos = [];

        // Prepare ERP statement once
        $sqlErp = "UPDATE prpohd 
                      SET fgstatus    = 'V', 
                          approvedate = :approvedate, 
                          approveby   = :approveby 
                    WHERE TRIM(ponmbr) = :ponmbr";
        $stmtErp = $conn3->prepare($sqlErp);

        foreach ($detailIds as $dId) {
            $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr, d.approved_by FROM dbo.tracking_po_detail d 
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
                WHERE d.id = ? AND h.is_deleted = 0", [$dId]);
            if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
                continue;
            }

            $oldStatus = trim($rCurrent['status']);
            $ponmbr = trim($rCurrent['ponmbr']);

            // Hanya proses yang statusnya 'Sudah TTD' atau 'Tunggu Tanggal Penyerahan' dan belum pernah di-approve
            if (!in_array($oldStatus, ['Sudah TTD', 'Tunggu Tanggal Penyerahan'])) {
                continue;
            }
            if (!empty($rCurrent['approved_by'])) {
                continue;
            }

            // 1. Update ke ERP
            $erpSuccess = false;
            try {
                $stmtErp->execute([
                    ':approvedate' => $approveDate,
                    ':approveby'   => $approveBy,
                    ':ponmbr'      => $ponmbr
                ]);
                if ($stmtErp->rowCount() > 0) {
                    $erpSuccess = true;
                } else {
                    $failedPos[] = "PO {$ponmbr} (tidak ditemukan di ERP)";
                }
            } catch (Exception $e) {
                $failedPos[] = "PO {$ponmbr} (error ERP: " . $e->getMessage() . ")";
            }

            if (!$erpSuccess) {
                continue;
            }

            // 2. Update ke SQL Server lokal
            if ($oldStatus === 'Sudah TTD') {
                $sqlApprove = "UPDATE dbo.tracking_po_detail 
                               SET status = 'Approved', approved_by = ?, approved_at = GETDATE()
                               WHERE id = ?";
                $targetStatus = 'Approved';
            } else {
                $sqlApprove = "UPDATE dbo.tracking_po_detail 
                               SET approved_by = ?, approved_at = GETDATE()
                               WHERE id = ?";
                $targetStatus = 'Tunggu Tanggal Penyerahan';
            }

            $stmtApprove = sqlsrv_query($conn, $sqlApprove, [$currentUser['formatted'], $dId]);

            if ($stmtApprove) {
                $logKet = ($oldStatus === 'Sudah TTD')
                    ? "Approve massal: PO {$ponmbr} di-approve oleh IT (ERP fgstatus='V', approvedate={$approveDate}, approveby={$approveBy})."
                    : "Approve massal: PO {$ponmbr} (Tunggu Tanggal Penyerahan) di-approve oleh IT di ERP fgstatus='V'.";

                sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                    VALUES (?, 'Approve', ?, ?, ?, GETDATE(), ?)", [
                    $dId, $oldStatus, $targetStatus, $currentUser['formatted'],
                    $logKet
                ]);
                $successCount++;
            } else {
                // Rollback ERP jika update SQL Server lokal gagal
                try {
                    $rollbackErp = $conn3->prepare("UPDATE prpohd SET fgstatus = 'O', approvedate = NULL, approveby = NULL WHERE TRIM(ponmbr) = :ponmbr");
                    $rollbackErp->execute([':ponmbr' => $ponmbr]);
                } catch (Exception $ex) {}
                $failedPos[] = "PO {$ponmbr} (gagal simpan status lokal)";
            }
        }

        $msg = "{$successCount} PO berhasil di-approve di sistem Tracking & ERP.";
        if (!empty($failedPos)) {
            $msg .= " Catatan: " . count($failedPos) . " PO gagal diproses: " . implode(', ', array_slice($failedPos, 0, 5));
            if (count($failedPos) > 5) {
                $msg .= ", dll.";
            }
        }

        echo json_encode([
            'status' => 'ok',
            'message' => $msg,
            'count' => $successCount
        ]);
        break;

    // =====================================================================
    // DELETE TRACKING - Hapus data tracking PO (Purchasing & Admin)
    // =====================================================================
    case 'delete_tracking':
        if (!$currentUser['can_delete']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menghapus data tracking PO.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailId = (int)($_POST['detail_id'] ?? 0);
        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid.']);
            exit;
        }

        // Cek data tracking detail
        $qDetail = sqlsrv_query($conn, "SELECT d.id, d.header_id, d.ponmbr, d.status 
            FROM dbo.tracking_po_detail d 
            WHERE d.id = ?", [$detailId]);
        if (!$qDetail || !($rDetail = sqlsrv_fetch_array($qDetail, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data tracking PO tidak ditemukan.']);
            exit;
        }

        $ponmbr = trim($rDetail['ponmbr']);
        $status = trim($rDetail['status']);
        $headerId = (int)$rDetail['header_id'];

        // Jika status sudah Approved, hanya Admin yang boleh menghapus
        if ($status === 'Approved' && !$currentUser['is_admin']) {
            echo json_encode([
                'status' => 'error',
                'message' => "PO {$ponmbr} sudah berstatus 'Approved' dan tidak dapat dihapus kecuali oleh Administrator."
            ]);
            exit;
        }

        // Hapus record detail (log terkait akan terhapus otomatis via ON DELETE CASCADE)
        $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.tracking_po_detail WHERE id = ?", [$detailId]);
        if (!$stmtDel) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data tracking: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        // Cek apakah masih ada detail lain pada header_id yang sama
        if ($headerId > 0) {
            $qRemain = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.tracking_po_detail WHERE header_id = ?", [$headerId]);
            if ($qRemain && ($rRemain = sqlsrv_fetch_array($qRemain, SQLSRV_FETCH_ASSOC))) {
                if ((int)$rRemain['total'] === 0) {
                    // Bersihkan header kosong agar tidak meninggalkan orphaned record
                    sqlsrv_query($conn, "DELETE FROM dbo.tracking_po_header WHERE id = ?", [$headerId]);
                }
            }
        }

        echo json_encode([
            'status' => 'ok',
            'message' => "Data tracking untuk PO {$ponmbr} berhasil dihapus."
        ]);
        break;

    // =====================================================================
    // BATCH DELETE TRACKING - Hapus banyak data tracking PO sekaligus
    // =====================================================================
    case 'batch_delete_tracking':
        if (!$currentUser['can_delete']) {
            echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menghapus data tracking PO.']);
            exit;
        }
        if (!$tableReady) {
            echo json_encode(['status' => 'error', 'message' => 'Tabel tracking_po belum tersedia.']);
            exit;
        }

        $detailIds = $_POST['detail_ids'] ?? [];
        if (is_string($detailIds)) {
            $detailIds = json_decode($detailIds, true) ?: explode(',', $detailIds);
        }
        $detailIds = array_values(array_unique(array_filter(array_map('intval', (array)$detailIds), function($v) { return $v > 0; })));

        if (empty($detailIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 data tracking PO untuk dihapus.']);
            exit;
        }

        $deletedCount = 0;
        $skippedApprovedCount = 0;
        $affectedHeaderIds = [];

        foreach ($detailIds as $dId) {
            $qDetail = sqlsrv_query($conn, "SELECT d.id, d.header_id, d.ponmbr, d.status 
                FROM dbo.tracking_po_detail d 
                WHERE d.id = ?", [$dId]);
            if (!$qDetail || !($rDetail = sqlsrv_fetch_array($qDetail, SQLSRV_FETCH_ASSOC))) {
                continue;
            }

            $status = trim($rDetail['status']);
            $headerId = (int)$rDetail['header_id'];

            // Jika status sudah Approved dan bukan Admin, lewati
            if ($status === 'Approved' && !$currentUser['is_admin']) {
                $skippedApprovedCount++;
                continue;
            }

            $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.tracking_po_detail WHERE id = ?", [$dId]);
            if ($stmtDel) {
                $deletedCount++;
                if ($headerId > 0) {
                    $affectedHeaderIds[$headerId] = true;
                }
            }
        }

        // Bersihkan header yang sekarang sudah tidak memiliki detail
        foreach (array_keys($affectedHeaderIds) as $hId) {
            $qRemain = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.tracking_po_detail WHERE header_id = ?", [$hId]);
            if ($qRemain && ($rRemain = sqlsrv_fetch_array($qRemain, SQLSRV_FETCH_ASSOC))) {
                if ((int)$rRemain['total'] === 0) {
                    sqlsrv_query($conn, "DELETE FROM dbo.tracking_po_header WHERE id = ?", [$hId]);
                }
            }
        }

        $msg = "{$deletedCount} data tracking PO berhasil dihapus.";
        if ($skippedApprovedCount > 0) {
            $msg .= " ({$skippedApprovedCount} data berstatus 'Approved' dilewati karena hanya Administrator yang dapat menghapusnya).";
        }

        echo json_encode([
            'status' => 'ok',
            'message' => $msg,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedApprovedCount
        ]);
        break;

    // =====================================================================
    // GET LOG - Ambil log history per detail PO
    // =====================================================================
    case 'get_log':
        if (!$tableReady) {
            echo json_encode(['status' => 'ok', 'logs' => []]);
            exit;
        }

        $detailId = (int)($_GET['detail_id'] ?? 0);
        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID detail tidak valid.']);
            exit;
        }

        $sqlLog = "SELECT l.action, l.old_status, l.new_status, l.performed_by, l.performed_at, l.keterangan
                   FROM dbo.tracking_po_log l
                   WHERE l.detail_id = ?
                   ORDER BY l.performed_at ASC";
        $stmtLog = sqlsrv_query($conn, $sqlLog, [$detailId]);
        $logs = [];

        if ($stmtLog) {
            while ($row = sqlsrv_fetch_array($stmtLog, SQLSRV_FETCH_ASSOC)) {
                $performedAt = $row['performed_at'] instanceof DateTimeInterface
                    ? $row['performed_at']->format('d/m/Y H:i')
                    : (!empty($row['performed_at']) ? date('d/m/Y H:i', strtotime($row['performed_at'])) : '-');

                $logs[] = [
                    'action'       => trim($row['action']),
                    'old_status'   => $row['old_status'] ? trim($row['old_status']) : null,
                    'new_status'   => trim($row['new_status']),
                    'performed_by' => trim($row['performed_by']),
                    'performed_at' => $performedAt,
                    'keterangan'   => $row['keterangan'] ? trim($row['keterangan']) : null,
                ];
            }
        }

        echo json_encode(['status' => 'ok', 'logs' => $logs]);
        break;

    // =====================================================================
    // VIEW DETAIL PO - Ambil detail barang PO dari ERP (prpodt) READ-ONLY
    // =====================================================================
    case 'view_detail_po':
        if (!isset($conn3) || !$conn3) {
            echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database ERP tidak tersedia.']);
            exit;
        }

        $ponmbr = trim($_GET['ponmbr'] ?? '');
        if (empty($ponmbr)) {
            echo json_encode(['status' => 'error', 'message' => 'Nomor PO tidak boleh kosong.']);
            exit;
        }

        try {
            $sqlDetail = "
                SELECT 
                    h.pohdid,
                    TRIM(h.ponmbr) AS ponmbr,
                    h.podate,
                    TRIM(h.povendorcode) AS povendorcode,
                    TRIM(h.povendorname) AS povendorname,
                    h.poinitiatorid,
                    u.username AS pic_pembuat,
                    d.podtid,
                    d.poseq,
                    TRIM(d.poprodcode) AS poprodcode,
                    TRIM(d.poprodname) AS poprodname,
                    d.poqty,
                    s.structname AS struct_name
                FROM prpodt d
                JOIN prpohd h ON d.pohdid = h.pohdid
                LEFT JOIN msuser u ON h.poinitiatorid = u.initiatorid
                LEFT JOIN smproduct p ON TRIM(d.poprodcode) = TRIM(p.prodcode)
                LEFT JOIN smprodstruct s ON p.prodstructid = s.prodstructid
                WHERE TRIM(h.ponmbr) = :ponmbr
                ORDER BY d.poseq ASC
            ";
            $stmtDetail = $conn3->prepare($sqlDetail);
            $stmtDetail->execute([':ponmbr' => $ponmbr]);
            $rows = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

            $header = null;
            $items = [];
            $totalQty = 0;

            foreach ($rows as $row) {
                // Build header once
                if ($header === null) {
                    $poDate = !empty($row['podate']) ? date('d/m/Y', strtotime($row['podate'])) : '-';
                    $header = [
                        'pohdid'       => (int)$row['pohdid'],
                        'ponmbr'       => trim($row['ponmbr']),
                        'podate'       => $poDate,
                        'povendorcode' => trim($row['povendorcode'] ?? ''),
                        'povendorname' => trim($row['povendorname'] ?? '-'),
                        'pic_pembuat'  => trim($row['pic_pembuat'] ?? '-'),
                        'dikirim_ke'   => null,
                        'alamat_kirim' => null,
                    ];

                    if ($tableReady) {
                        $qTrack = sqlsrv_query($conn, "SELECT TOP 1 dikirim_ke, alamat_kirim FROM dbo.tracking_po_detail WHERE ponmbr = ? ORDER BY id DESC", [$ponmbr]);
                        if ($qTrack && $rTrack = sqlsrv_fetch_array($qTrack, SQLSRV_FETCH_ASSOC)) {
                            $header['dikirim_ke'] = !empty($rTrack['dikirim_ke']) ? trim($rTrack['dikirim_ke']) : null;
                            $header['alamat_kirim'] = !empty($rTrack['alamat_kirim']) ? trim($rTrack['alamat_kirim']) : null;
                        }
                    }
                }

                $qty = (float)($row['poqty'] ?? 0);
                $totalQty += $qty;

                // Format qty tanpa trailing zero
                $qtyFormatted = (floor($qty) == $qty)
                    ? number_format($qty, 0, ',', '.')
                    : rtrim(rtrim(number_format($qty, 4, ',', '.'), '0'), ',');

                $items[] = [
                    'poseq'       => (int)($row['poseq'] ?? 0),
                    'poprodcode'  => trim($row['poprodcode'] ?? '-'),
                    'poprodname'  => trim($row['poprodname'] ?? '-'),
                    'struct_name' => trim($row['struct_name'] ?? '-'),
                    'poqty'       => $qtyFormatted,
                    'poqty_raw'   => $qty,
                ];
            }

            if ($header === null) {
                echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak ditemukan di database ERP."]);
                exit;
            }

            // Format total qty
            $totalFormatted = (floor($totalQty) == $totalQty)
                ? number_format($totalQty, 0, ',', '.')
                : rtrim(rtrim(number_format($totalQty, 4, ',', '.'), '0'), ',');

            echo json_encode([
                'status'    => 'ok',
                'header'    => $header,
                'items'     => $items,
                'total_qty' => $totalFormatted,
                'total_items' => count($items)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil detail PO: ' . $e->getMessage()]);
        }
        break;

    // =====================================================================
    // DEFAULT - Aksi tidak dikenali
    // =====================================================================
    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali: ' . htmlspecialchars($action)]);
        break;
}
