<?php
// list_form_serverside.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Include database connection
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['error' => 'File koneksi database tidak ditemukan']);
    exit;
}
require_once __DIR__ . '/approval_helper.php';

// --- Basic checks ---
if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

// Samakan dengan MenuId yang dipakai di list_form.php
define('FORM_UMUM_MENU_ID', 1293);

// --- Helper: Check Permissions ---
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1]; // Fallback to 1 for out of box experience
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false)
        sqlsrv_free_stmt($stmt);

    return $permissions;
}

function iksTimeToMinutes($timeValue)
{
    if ($timeValue instanceof DateTime) {
        return ((int) $timeValue->format('H')) * 60 + (int) $timeValue->format('i');
    }

    $timeValue = trim((string) $timeValue);
    if ($timeValue === '') {
        return null;
    }

    if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?/', $timeValue, $m)) {
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    return null;
}

function iksIsLateReturn($jamKembaliReal, $estimasiKembali)
{
    $actualMinutes = iksTimeToMinutes($jamKembaliReal);
    $estimateMinutes = iksTimeToMinutes($estimasiKembali);

    if ($actualMinutes === null || $estimateMinutes === null) {
        return false;
    }

    return $actualMinutes > $estimateMinutes;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], FORM_UMUM_MENU_ID);

// --- Role-based Visibility Checks ---
$userRoles = [];
$isApprover = false;
$isAdmin = ($_SESSION['GroupId'] == 1);

if (!$isAdmin) {
    $sqlRoles = "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
    $stmtRoles = sqlsrv_query($conn, $sqlRoles, [$_SESSION['UserId']]);
    if ($stmtRoles) {
        while ($rowR = sqlsrv_fetch_array($stmtRoles, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole']);
            $userRoles[] = $role;
            if ($role !== 'Pemohon') {
                $isApprover = true;
            }
        }
        sqlsrv_free_stmt($stmtRoles);
    }
}

// --- Read DataTables params ---
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

// Search value
$searchValue = '';
if (!empty($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);
} elseif (!empty($_POST['search']['value'])) {
    $searchValue = trim($_POST['search']['value']);
}

// Ordering
$columnIndex = $_POST['order'][0]['column'] ?? 0;
$columnName = $_POST['columns'][$columnIndex]['data'] ?? 'tgl_pengajuan';
$columnDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

// Map column names to SQL columns
$validColumns = [
    "ticket" => "x.ticket",
    "kategori" => "x.kategori",
    "nama_pemohon" => "x.nama_pemohon",
    "departemen" => "x.departemen",
    "tgl_pengajuan" => "x.tgl_pengajuan",
    "status_ticket" => "x.status_ticket"
];

$orderColumn = $validColumns[$columnName] ?? "x.tgl_pengajuan";
$orderSql = "ORDER BY $orderColumn $columnDir, x.created_at DESC";

// --- Filters ---
$filterKategori = $_POST['kategori'] ?? '';
$filterTanggal = $_POST['tanggal'] ?? '';
$filterTanggalSampai = $_POST['tanggal_sampai'] ?? '';
$filterStatus = $_POST['status_filter'] ?? '';
$filterReqGudang = $_POST['request_gudang'] ?? '';
$filterReqTransaksi = $_POST['request_transaksi'] ?? '';
$filterReportType = $_POST['report_type'] ?? '';

// --- Build Query ---
// Base Subquery (UNION ALL) - easily expandable for future Form Umum!
$baseQuery = "
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        jenis_pengajuan,
        is_revisi_harga,
        request_gudang,
        request_transaksi,
        gudang_transaksi,
        ISNULL(kategori,'Buka Tanggal Closingan') AS kategori,
        created_at,
        created_by
    FROM Form_Umum_Buka_Tanggal_Closingan

    UNION ALL

    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        NULL         AS jenis_pengajuan,
        0            AS is_revisi_harga,
        0            AS request_gudang,
        0            AS request_transaksi,
        NULL         AS gudang_transaksi,
        'Izin Keluar Pabrik' AS kategori,
        created_at,
        created_by
    FROM Form_Umum_Izin_Keluar_Pabrik

    UNION ALL

    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        NULL         AS jenis_pengajuan,
        0            AS is_revisi_harga,
        0            AS request_gudang,
        0            AS request_transaksi,
        NULL         AS gudang_transaksi,
        'Izin Pulang Cepat' AS kategori,
        created_at,
        created_by
    FROM Form_Umum_Izin_Pulang_Cepat
";

$whereClauses = [];
$params = [];

// --- Apply Visibility Filter ---
//   Aturan (berlaku untuk SEMUA jenis form: Closing, IKP legacy, IKS):
//     • Admin (GroupId=1)                                   → lihat semua
//     • User adalah Pemohon (created_by = current user)     → lihat form miliknya
//     • User sudah TTD form (SignedByUserId = current user) → lihat form tsb
//     • User punya role R yang masih PENDING di form tsb
//       (tidak ada baris Form_Umum_TTD dgn GroupRole=R dan
//        SignaturePath terisi) → lihat form tsb (supaya bs di-TTD)
//   Setelah satu user dgn role R men-TTD form, user lain dgn role R
//   yang sama TIDAK lagi melihat form tsb (isolasi per-penandatangan).
if (!$isAdmin) {
    $visibilityClauses = [];
    $visibilityParams = [];

    // 1. Owner dapat melihat form miliknya sendiri
    $visibilityClauses[] = "x.created_by = ?";
    $visibilityParams[] = $_SESSION['NamaLengkap'];

    // 2. Per-role category map (pembatas kategori)
    //    'HRD' hanya melihat IKP/IKS; 'Kabag ICS' hanya Closing, dst.
    $roleCategoryMap = [
        // Roles dengan akses ke kategori pengajuan karyawan
        'Atasan Pemohon' => ['Buka Tanggal Closingan', 'Izin Keluar Pabrik', 'Izin Pulang Cepat'],

        // Roles dengan akses ke Closing saja
        'Kabag ICS' => ['Buka Tanggal Closingan'],
        'Kabag Purchasing' => ['Buka Tanggal Closingan'],
        'Kadept ACC' => ['Buka Tanggal Closingan'],
        'Kadept Purchasing' => ['Buka Tanggal Closingan'],
        'Direksi' => ['Buka Tanggal Closingan'],
        // Roles dengan akses ke IKP/IPC
        'HRD' => ['Izin Keluar Pabrik', 'Izin Pulang Cepat'],
        'Personalia' => ['Izin Keluar Pabrik'],
        'DanRu SATPAM' => ['Izin Keluar Pabrik'],
    ];

    $authorizedCategories = [];
    foreach ($userRoles as $ur) {
        if (isset($roleCategoryMap[$ur])) {
            $authorizedCategories = array_merge($authorizedCategories, $roleCategoryMap[$ur]);
        }
    }
    $authorizedCategories = array_values(array_unique($authorizedCategories));

    // 3. Per-user TTD-based filter
    if (!empty($authorizedCategories)) {
        $currentUserId = (int) $_SESSION['UserId'];
        $placeholders = implode(',', array_fill(0, count($authorizedCategories), '?'));

        $pairPlaceholders = [];
        $pairParams = [];
        foreach ($roleCategoryMap as $mapRole => $mapCats) {
            foreach ($mapCats as $mapCat) {
                $pairPlaceholders[] = '(?, ?)';
                $pairParams[] = $mapCat;
                $pairParams[] = $mapRole;
            }
        }
        $pairValuesSql = implode(', ', $pairPlaceholders);

        $ttdSubquery = "
            (
                EXISTS (
                    SELECT 1
                    FROM Form_Umum_TTD t
                    WHERE t.Ticket = x.ticket
                      AND t.SignedByUserId = ?
                )
                OR EXISTS (
                    SELECT 1
                    FROM dbo.User_TTD_Template_Umum u
                    INNER JOIN (VALUES $pairValuesSql) AS rc(Kategori, GroupRole)
                        ON rc.GroupRole = u.GroupRole
                       AND rc.Kategori = x.kategori
                    WHERE u.UserId = ?
                      AND u.IsActive = 1
                      -- Cek apakah role ini memang diperlukan untuk form spesifik ini
                      AND (
                          -- Untuk non-Closingan, semua role dalam kategori map dianggap relevan
                          rc.Kategori != 'Buka Tanggal Closingan'
                          -- Untuk Closingan, cek requirement spesifik berdasarkan approval flow:
                          OR (
                              rc.Kategori = 'Buka Tanggal Closingan'
                              AND (
                                  -- Direksi hanya untuk form dengan is_revisi_harga
                                  (u.GroupRole = 'Direksi' AND x.is_revisi_harga = 1)
                                  -- Kabag ICS diperlukan untuk semua flow closingan gudang/transaksi
                                  OR (u.GroupRole = 'Kabag ICS' AND (
                                      x.is_revisi_harga = 1
                                      OR ISNULL(x.request_gudang, 0) = 1
                                      OR ISNULL(x.request_transaksi, 0) = 1
                                  ))
                                  -- Kadept ACC diperlukan untuk semua flow closingan gudang/transaksi
                                  OR (u.GroupRole IN ('Kadept ACC', 'Acc Audit') AND (
                                      x.is_revisi_harga = 1
                                      OR ISNULL(x.request_gudang, 0) = 1
                                      OR ISNULL(x.request_transaksi, 0) = 1
                                  ))
                                  -- Roles lain (Kabag/Kadept Purchasing) - untuk backward compatibility
                                  OR u.GroupRole NOT IN ('Direksi', 'Kabag ICS', 'Kadept ACC', 'Acc Audit')
                              )
                          )
                      )
                      AND NOT EXISTS (
                          SELECT 1
                          FROM Form_Umum_TTD t2
                          WHERE t2.Ticket = x.ticket
                            AND t2.GroupRole = u.GroupRole
                            AND t2.SignaturePath IS NOT NULL
                            AND t2.SignaturePath != ''
                      )
                )
            )
        ";

        $visibilityClauses[] = "(x.kategori IN ($placeholders) AND $ttdSubquery)";
        $visibilityParams = array_merge($visibilityParams, $authorizedCategories);
        $visibilityParams[] = $currentUserId;
        $visibilityParams = array_merge($visibilityParams, $pairParams);
        $visibilityParams[] = $currentUserId;
    }

    $whereClauses[] = "(" . implode(" OR ", $visibilityClauses) . ")";
    $params = array_merge($params, $visibilityParams);
}

if ($filterKategori !== '') {
    $whereClauses[] = "x.kategori = ?";
    $params[] = $filterKategori;
}

if ($filterTanggal !== '' && $filterTanggalSampai !== '') {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) >= ?";
    $params[] = $filterTanggal;
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) <= ?";
    $params[] = $filterTanggalSampai;
} elseif ($filterTanggal !== '') {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) >= ?";
    $params[] = $filterTanggal;
} elseif ($filterTanggalSampai !== '') {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) <= ?";
    $params[] = $filterTanggalSampai;
}

if ($filterStatus !== '') {
    $whereClauses[] = "x.status_ticket = ?";
    $params[] = $filterStatus;
}

// Filter berdasarkan request_gudang / request_transaksi (checkbox sub-filter)
// "Closingan Gudang" = HANYA gudang saja (request_gudang=1 AND request_transaksi=0)
// "Closingan Transaksi" = HANYA transaksi saja (request_transaksi=1 AND request_gudang=0)
if ($filterReqGudang === '1' && $filterReqTransaksi !== '1') {
    // Tiket yang request_gudang=1 DAN request_transaksi=0 (gudang saja)
    $whereClauses[] = "(x.request_gudang = 1 AND x.request_transaksi = 0)";
} elseif ($filterReqTransaksi === '1' && $filterReqGudang !== '1') {
    // Tiket yang request_transaksi=1 DAN request_gudang=0 (transaksi saja)
    $whereClauses[] = "(x.request_transaksi = 1 AND x.request_gudang = 0)";
} elseif ($filterReqGudang === '1' && $filterReqTransaksi === '1') {
    // Keduanya: tampilkan semua closingan (tidak tambah filter)
    // no-op
}

// Filter berdasarkan Jenis Report (kategori dari dropdown)
if ($filterReportType === 'rekap_closingan') {
    $whereClauses[] = "x.kategori = 'Buka Tanggal Closingan'";
} elseif ($filterReportType === 'rekap_izin_keluar') {
    $whereClauses[] = "x.kategori = 'Izin Keluar Pabrik'";
} elseif ($filterReportType === 'rekap_izin_pulang_cepat') {
    $whereClauses[] = "x.kategori = 'Izin Pulang Cepat'";
}

// Global Search
if ($searchValue !== '') {
    $searchClauses = [];
    $searchCols = ["x.ticket", "x.nama_pemohon", "x.departemen", "x.kategori", "x.status_ticket"];
    foreach ($searchCols as $col) {
        $searchClauses[] = "$col LIKE ?";
        $params[] = '%' . $searchValue . '%';
    }
    if (!empty($searchClauses)) {
        $whereClauses[] = "(" . implode(" OR ", $searchClauses) . ")";
    }
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// --- 1. Count Total Records ---
$countTotalSql = "SELECT COUNT(*) AS total FROM ($baseQuery) AS x";
$countTotalStmt = sqlsrv_query($conn, $countTotalSql);
$totalRecords = 0;
if ($countTotalStmt !== false && $row = sqlsrv_fetch_array($countTotalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['total'];
}
if ($countTotalStmt)
    sqlsrv_free_stmt($countTotalStmt);

// --- 2. Count Filtered Records ---
$countFilteredSql = "SELECT COUNT(*) AS total FROM ($baseQuery) AS x $whereSql";
$countFilteredStmt = sqlsrv_query($conn, $countFilteredSql, $params);
$recordsFiltered = 0;
if ($countFilteredStmt !== false && $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
    $recordsFiltered = $row['total'];
}
if ($countFilteredStmt)
    sqlsrv_free_stmt($countFilteredStmt);

// --- 3. Get Data ---
// Using Form_Umum_TTD
$dataSql = "SELECT x.*, 
            (SELECT COUNT(*) FROM Form_Umum_TTD WHERE Ticket = x.ticket AND SignaturePath IS NOT NULL AND SignaturePath != '') AS ttd_count
            FROM ($baseQuery) AS x $whereSql $orderSql OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = array_merge($params, [$start, $length]);

$stmt = sqlsrv_query($conn, $dataSql, $dataParams);
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error', 'detail' => sqlsrv_errors()]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = '-';
    if ($row['tgl_pengajuan'] instanceof DateTime) {
        $tgl = $row['tgl_pengajuan']->format('d-m-Y');
    }

    $ticket = $row['ticket'];
    $kategori = $row['kategori'];
    $isIzinKeluar = ($kategori === 'Izin Keluar Pabrik'); // Covers both IKP and IKS
    $isIzinPulangCepat = ($kategori === 'Izin Pulang Cepat');

    // Izin Keluar: hitung TTD dan status secara langsung (tidak pakai closinganSyncApprovalStatus)
    if ($isIzinKeluar || $isIzinPulangCepat) {
        // IKS/IPC use 3 roles, IKP uses 4 roles - determine by ticket prefix/category
        $isIKP = stripos($ticket, 'IKP-') === 0;
        $required = $isIKP ? 4 : 3;
        $ttdCount = (int) $row['ttd_count'];
        $st = strtolower(trim($row['status_ticket'] ?? ''));
    } else {
        $flow = closinganApprovalFlow($row);
        $required = count($flow['required_roles']);
        $sync = closinganSyncApprovalStatus($conn, $ticket, $row);
        $row['status_ticket'] = $sync['status'];
        $required = $sync['required'];
        $st = strtolower(trim($row['status_ticket'] ?? ''));
        $ttdCount = $sync['count'];
    }

    // Status badge logic
    if ($st == 'unclosing') {
        $statusBadge = "<span class='badge badge-warning'>Unclosing</span>";
    } elseif ($st == 'closed') {
        $statusBadge = "<span class='badge badge-dark'>Closed</span>";
    } elseif ($st == 'rejected' || $st == 'ditolak') {
        $statusBadge = "<span class='badge badge-danger'>Ditolak</span>";
    } else {
        // For Izin Keluar, show attendance status after approval
        if (($isIzinKeluar || $isIzinPulangCepat) && $ttdCount >= $required) {
            // Query scan times to determine attendance/security status
            if ($isIzinPulangCepat) {
                $sqlScan = "SELECT jam_keluar_real FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
            } else {
                $sqlScan = "SELECT jam_keluar_real, jam_kembali_real, estimasi_kembali, tgl_keluar FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
            }
            $stmtScan = sqlsrv_query($conn, $sqlScan, [$ticket]);

            if ($stmtScan && $scanRow = sqlsrv_fetch_array($stmtScan, SQLSRV_FETCH_ASSOC)) {
                $jamKeluarReal = $scanRow['jam_keluar_real'] ?? null;

                if ($isIzinPulangCepat) {
                    if (!empty($jamKeluarReal)) {
                        $statusBadge = "<span class='badge badge-success'>Sudah Keluar</span>";
                    } else {
                        $statusBadge = "<span class='badge badge-success'>Approved</span>";
                    }
                } else {
                    $jamKembaliReal = $scanRow['jam_kembali_real'] ?? null;
                    $estimasiKembali = $scanRow['estimasi_kembali'] ?? null;
                    $tglKeluar = $scanRow['tgl_keluar'] ?? null;

                    // Only show attendance status if exit scan exists
                    if (!empty($jamKeluarReal) && empty($jamKembaliReal)) {
                        // Has exit scan, no return scan yet
                        $statusBadge = "<span class='badge badge-warning'>Sedang Keluar</span>";
                    } elseif (!empty($jamKeluarReal) && !empty($jamKembaliReal)) {
                        // Has both scans - compare time-only values. Do not parse
                        // "HH:MM" as DateTime because PHP would attach today's date.
                        if (iksIsLateReturn($jamKembaliReal, $estimasiKembali)) {
                            $statusBadge = "<span class='badge badge-danger'>Terlambat Kembali</span>";
                        } else {
                            $statusBadge = "<span class='badge badge-success'>Sudah Kembali</span>";
                        }
                    } else {
                        // No exit scan yet - show Approved
                        $statusBadge = "<span class='badge badge-success'>Approved</span>";
                    }
                }
            } else {
                // Fallback if query fails - show Approved
                $statusBadge = "<span class='badge badge-success'>Approved</span>";
            }
            if ($stmtScan)
                sqlsrv_free_stmt($stmtScan);
        }
        // For Closingan or pending signatures
        elseif ($ttdCount >= $required) {
            $statusBadge = "<span class='badge badge-success'>Approved</span>";
        } elseif ($ttdCount > 0) {
            $statusBadge = "<span class='badge badge-info'>{$ttdCount}/{$required} Disetujui</span>";
        } else {
            $statusBadge = "<span class='badge badge-warning'>Menunggu Persetujuan</span>";
        }
    }


    $isOwner = trim((string) ($row['created_by'] ?? '')) === trim((string) ($_SESSION['NamaLengkap'] ?? ''));

    // Logika tombol Unclosing/Closed hanya untuk Closingan (bukan Izin Keluar)
    $canUnclosing = false;

    // 1. Administrator selalu bisa unclosing (GroupId = 1)
    if ($isAdmin && !$isIzinKeluar && !$isIzinPulangCepat) {
        $canUnclosing = true;
    }
    // 2. Tim Terkait berdasarkan checkbox (BUKAN Pemohon, BUKAN Izin Keluar)
    elseif (!$isOwner && !$isIzinKeluar && !$isIzinPulangCepat) {
        $requestFlags = closinganRequestFlags($row);
        $hasGudang = $requestFlags['has_gudang'];
        $hasTransaksi = $requestFlags['has_transaksi'];

        // Kadept ACC bisa unclosing jika request_gudang = 1
        if ($hasGudang && in_array('Kadept ACC', $userRoles)) {
            $canUnclosing = true;
        }

        // Kabag ICS bisa unclosing jika request_transaksi = 1
        if ($hasTransaksi && in_array('Kabag ICS', $userRoles)) {
            $canUnclosing = true;
        }
    }

    // Logika tombol Closed (Unclosing → Closed)
    // Hanya Pemohon dan Administrator, dan hanya untuk Closingan (bukan Izin Keluar)
    $canClosed = !$isIzinKeluar && ($isAdmin || $isOwner);
    $btns = "<div class='btn-group' role='group'>";

    // Detail
    $btns .= "<button type='button' class='btn btn-info btn-sm btn-detail action-btn' data-ticket='{$ticket}' data-kategori='{$kategori}' title='Detail'><i class='fas fa-eye'></i></button>";

    // PDF tersedia setelah approval lengkap
    if (!in_array($st, ['ditolak', 'rejected'], true) && ($st === 'approved' || $st === 'unclosing' || $st === 'closed' || $ttdCount >= $required)) {
        // Pilih PDF generator sesuai kategori (Req 8.3)
        if ($kategori === 'Izin Keluar Pabrik') {
            $pdfScript = 'generate_pdf_izin_keluar_pabrik.php';
        } elseif ($kategori === 'Izin Pulang Cepat') {
            $pdfScript = 'generate_pdf_izin_pulang_cepat.php';
        } else {
            $pdfScript = 'generate_pdf_buka_tanggal_closingan.php';
        }
        $btns .= "<a href='./$pdfScript?ticket={$ticket}' class='btn btn-danger btn-sm action-btn btn-pdf' target='_blank' title='PDF'><i class='fas fa-file-pdf'></i></a>";

        // QR Code untuk form scan yang sudah approved (TTD lengkap)
        if (($isIzinKeluar || $isIzinPulangCepat) && $ttdCount >= $required) {
            $btns .= "<button type='button' class='btn btn-dark btn-sm action-btn btn-qr-izin' data-ticket='{$ticket}' title='Generate QR Code untuk Satpam'><i class='fas fa-qrcode'></i></button>";
        }
    }

    if (!in_array($st, ['ditolak', 'rejected'], true) && (!in_array($st, ['unclosing', 'closed'], true) || $isAdmin)) {
        if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) {
            $btns .= "<button type='button' class='btn btn-warning btn-sm btn-edit action-btn' data-ticket='{$ticket}' title='Edit'><i class='fas fa-edit'></i></button>";
        }
    }

    // Tombol Unclosing: Administrator, Kadept ACC (jika request_gudang=1), Kabag ICS (jika request_transaksi=1)
    if ($canUnclosing && $st === 'approved') {
        $btns .= "<button type='button' class='btn btn-success btn-sm btn-proses-closingan action-btn' data-ticket='{$ticket}' data-new-status='unclosing' data-target-label='Unclosing' title='Proses menjadi Unclosing'><i class='fas fa-play'></i></button>";
    }

    // Tombol Closed: Hanya Pemohon dan Administrator
    if ($canClosed && $st === 'unclosing') {
        $btns .= "<button type='button' class='btn btn-secondary btn-sm btn-proses-closingan action-btn' data-ticket='{$ticket}' data-new-status='closed' data-target-label='Closed' title='Proses menjadi Closed'><i class='fas fa-check'></i></button>";
    }

    // Delete
    if (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) {
        $btns .= "<button type='button' class='btn btn-danger btn-sm btn-delete action-btn' data-ticket='{$ticket}' title='Hapus'><i class='fas fa-trash'></i></button>";
    }

    $btns .= "</div>";

    $data[] = [
        "ticket" => $ticket,
        "kategori" => $kategori,
        "nama_pemohon" => $row['nama_pemohon'],
        "departemen" => $row['departemen'],
        "tgl_pengajuan" => $tgl,
        "status_ticket" => $statusBadge,
        "aksi" => $btns
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
]);



