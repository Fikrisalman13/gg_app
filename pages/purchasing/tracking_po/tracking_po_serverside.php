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

        if (!empty($filterDateFrom) && !empty($filterDateTo)) {
            $where .= " AND h.tanggal_kirim BETWEEN ? AND ?";
            $params[] = $filterDateFrom;
            $params[] = $filterDateTo;
        } elseif (!empty($filterDateFrom)) {
            $where .= " AND h.tanggal_kirim >= ?";
            $params[] = $filterDateFrom;
        } elseif (!empty($filterDateTo)) {
            $where .= " AND h.tanggal_kirim <= ?";
            $params[] = $filterDateTo;
        }

        // Status filter - "Belum Kembali" is virtual (Diserahkan + tanggal < today)
        if ($filterStatus === 'Belum Kembali') {
            $where .= " AND d.status = 'Diserahkan' AND h.tanggal_kirim < CAST(GETDATE() AS DATE)";
        } elseif ($filterStatus === 'Diserahkan') {
            $where .= " AND d.status = 'Diserahkan' AND h.tanggal_kirim >= CAST(GETDATE() AS DATE)";
        } elseif (!empty($filterStatus)) {
            $where .= " AND d.status = ?";
            $params[] = $filterStatus;
        }

        $sql = "SELECT d.id AS detail_id, d.header_id, d.ponmbr, d.pohdid, d.povendorname, 
                       d.podate, d.status, d.is_signed, d.tanggal_kembali, 
                       d.konfirmasi_oleh, d.konfirmasi_at, d.approved_by, d.approved_at, d.catatan,
                       h.tanggal_kirim, h.diserahkan_oleh, h.created_at
                FROM dbo.tracking_po_detail d
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
                WHERE {$where}
                ORDER BY h.tanggal_kirim DESC, d.id DESC";

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
                    if ($tanggalKirim < $todayStr) {
                        $displayStatus = 'Belum Kembali';
                    }
                }

                // Format dates
                $tglKirim = $row['tanggal_kirim'] instanceof DateTimeInterface
                    ? $row['tanggal_kirim']->format('d/m/Y')
                    : (!empty($row['tanggal_kirim']) ? date('d/m/Y', strtotime($row['tanggal_kirim'])) : '-');

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
    // GET SUMMARY - Hitung angka untuk summary cards
    // =====================================================================
    case 'get_summary':
        if (!$tableReady) {
            echo json_encode(['status' => 'ok', 'summary' => ['diserahkan' => 0, 'belum_kembali' => 0, 'sudah_ttd' => 0, 'approved' => 0]]);
            exit;
        }

        $todayStr = date('Y-m-d');
        $summary = ['diserahkan' => 0, 'belum_kembali' => 0, 'sudah_ttd' => 0, 'approved' => 0];

        // Diserahkan hari ini (status Diserahkan + tanggal_kirim = hari ini)
        $q1 = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE h.is_deleted = 0 AND d.status = 'Diserahkan' AND h.tanggal_kirim = CAST(GETDATE() AS DATE)");
        if ($q1 && ($r1 = sqlsrv_fetch_array($q1, SQLSRV_FETCH_ASSOC))) {
            $summary['diserahkan'] = (int)$r1['cnt'];
        }

        // Belum Kembali (status Diserahkan + tanggal_kirim < hari ini)
        $q2 = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE h.is_deleted = 0 AND d.status = 'Diserahkan' AND h.tanggal_kirim < CAST(GETDATE() AS DATE)");
        if ($q2 && ($r2 = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC))) {
            $summary['belum_kembali'] = (int)$r2['cnt'];
        }

        // Sudah TTD
        $q3 = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE h.is_deleted = 0 AND d.status = 'Sudah TTD'");
        if ($q3 && ($r3 = sqlsrv_fetch_array($q3, SQLSRV_FETCH_ASSOC))) {
            $summary['sudah_ttd'] = (int)$r3['cnt'];
        }

        // Approved
        $q4 = sqlsrv_query($conn, "SELECT COUNT(*) AS cnt FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE h.is_deleted = 0 AND d.status = 'Approved'");
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
                    TRIM(h.fgstatus) AS po_status
                FROM prpohd h
                LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
                LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
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

        $tanggalKirim = trim($_POST['tanggal_kirim'] ?? date('Y-m-d'));
        $poItems = json_decode($_POST['po_items'] ?? '[]', true);

        if (empty($poItems) || !is_array($poItems)) {
            echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 PO untuk diserahkan.']);
            exit;
        }

        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalKirim)) {
            echo json_encode(['status' => 'error', 'message' => 'Format tanggal kirim tidak valid.']);
            exit;
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
        $sqlHeader = "INSERT INTO dbo.tracking_po_header (tanggal_kirim, diserahkan_oleh, created_by, created_at)
                      OUTPUT INSERTED.id
                      VALUES (?, ?, ?, GETDATE())";
        $stmtHeader = sqlsrv_query($conn, $sqlHeader, [
            $tanggalKirim,
            $currentUser['formatted'],
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

            $sqlDetail = "INSERT INTO dbo.tracking_po_detail (header_id, ponmbr, pohdid, povendorname, podate, status, created_at)
                          OUTPUT INSERTED.id
                          VALUES (?, ?, ?, ?, ?, 'Diserahkan', GETDATE())";
            $stmtDetail = sqlsrv_query($conn, $sqlDetail, [$headerId, $ponmbr, $pohdid, $vendor, $podate]);

            if ($stmtDetail) {
                // Get detail ID for log
                $detailId = 0;
                if ($rDetId = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
                    $detailId = (int)$rDetId['id'];
                }

                // Insert log
                if ($detailId > 0) {
                    sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
                        VALUES (?, 'Diserahkan', NULL, 'Diserahkan', ?, GETDATE(), ?)", [
                        $detailId,
                        $currentUser['formatted'],
                        'PO diserahkan ke direktur pada ' . $tanggalKirim
                    ]);
                }
                $insertedCount++;
            }
        }

        echo json_encode([
            'status' => 'ok',
            'message' => "{$insertedCount} PO berhasil dicatat sebagai diserahkan.",
            'header_id' => $headerId
        ]);
        break;

    // =====================================================================
    // KONFIRMASI KEMBALI - Purchasing konfirmasi PO sudah kembali + TTD
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
        $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE d.id = ? AND h.is_deleted = 0", [$detailId]);
        if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan.']);
            exit;
        }

        $oldStatus = trim($rCurrent['status']);
        $ponmbr = trim($rCurrent['ponmbr']);

        if ($oldStatus !== 'Diserahkan') {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} tidak dalam status Diserahkan (status saat ini: {$oldStatus})."]);
            exit;
        }

        $newStatus = $isSigned ? 'Sudah TTD' : 'Sudah Kembali';
        $today = date('Y-m-d');

        $sqlUpdate = "UPDATE dbo.tracking_po_detail 
                      SET status = ?, is_signed = ?, tanggal_kembali = ?, 
                          konfirmasi_oleh = ?, konfirmasi_at = GETDATE(), catatan = ?
                      WHERE id = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [
            $newStatus, $isSigned, $today,
            $currentUser['formatted'], $catatan,
            $detailId
        ]);

        if (!$stmtUpdate) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate status: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        // Insert log
        $ketLog = $isSigned
            ? "Dokumen PO {$ponmbr} sudah kembali dan SUDAH ditandatangani."
            : "Dokumen PO {$ponmbr} sudah kembali tapi BELUM ditandatangani.";
        if (!empty($catatan)) $ketLog .= " Catatan: {$catatan}";

        sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
            VALUES (?, 'Konfirmasi Kembali', ?, ?, ?, GETDATE(), ?)", [
            $detailId, $oldStatus, $newStatus, $currentUser['formatted'], $ketLog
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => "PO {$ponmbr} berhasil dikonfirmasi: {$newStatus}."
        ]);
        break;

    // =====================================================================
    // APPROVE - IT approve PO yang sudah TTD (UI only, logika lintas DB nanti)
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

        $detailId = (int)($_POST['detail_id'] ?? 0);
        if ($detailId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID detail tidak valid.']);
            exit;
        }

        // Cek status saat ini
        $qCurrent = sqlsrv_query($conn, "SELECT d.status, d.ponmbr FROM dbo.tracking_po_detail d 
            INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id 
            WHERE d.id = ? AND h.is_deleted = 0", [$detailId]);
        if (!$qCurrent || !($rCurrent = sqlsrv_fetch_array($qCurrent, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan.']);
            exit;
        }

        $oldStatus = trim($rCurrent['status']);
        $ponmbr = trim($rCurrent['ponmbr']);

        if ($oldStatus !== 'Sudah TTD') {
            echo json_encode(['status' => 'error', 'message' => "PO {$ponmbr} harus berstatus 'Sudah TTD' untuk di-approve (status saat ini: {$oldStatus})."]);
            exit;
        }

        // Update status ke Approved
        $sqlApprove = "UPDATE dbo.tracking_po_detail 
                       SET status = 'Approved', approved_by = ?, approved_at = GETDATE()
                       WHERE id = ?";
        $stmtApprove = sqlsrv_query($conn, $sqlApprove, [$currentUser['formatted'], $detailId]);

        if (!$stmtApprove) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal meng-approve: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }

        // Insert log
        sqlsrv_query($conn, "INSERT INTO dbo.tracking_po_log (detail_id, action, old_status, new_status, performed_by, performed_at, keterangan)
            VALUES (?, 'Approve', ?, 'Approved', ?, GETDATE(), ?)", [
            $detailId, $oldStatus, $currentUser['formatted'],
            "PO {$ponmbr} di-approve oleh IT."
        ]);

        // TODO: Logika lintas database akan ditambahkan di sini nanti
        // Placeholder untuk proses approval ke sistem lain

        echo json_encode([
            'status' => 'ok',
            'message' => "PO {$ponmbr} berhasil di-approve."
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
                    ];
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
