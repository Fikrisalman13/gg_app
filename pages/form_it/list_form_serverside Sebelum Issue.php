<?php
// list_form_serverside.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../koneksi.php';

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

// --- Helper: Check Permissions ---
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false)
        sqlsrv_free_stmt($stmt);

    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 134); // MenuId 134 for Form IT

// --- Role-based Visibility Checks ---
$userRoles = [];
$isApprover = false;
$isAdmin = ($_SESSION['GroupId'] == 1);

if (!$isAdmin) {
    $sqlRoles = "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND IsActive = 1";
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
// Secondary sort for consistency
$orderSql = "ORDER BY $orderColumn $columnDir, x.created_at DESC";

// --- Filters ---
$filterKategori = $_POST['kategori'] ?? '';
$filterTanggal = $_POST['tanggal'] ?? '';

// --- Build Query ---
// Base Subquery (UNION ALL)
$baseQuery = "
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori,'Perangkat IT') AS kategori,
        created_at,
        created_by
    FROM Form_Pengajuan_Barang
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori,'CCTV') AS kategori,
        created_at,
        created_by
    FROM Form_Pengajuan_CCTV
    UNION ALL
    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Pemindahan Kamera CCTV') AS kategori,
        created_at,
        created_by
    FROM Form_Pemindahan_CCTV
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori,'Akses Internet') AS kategori,
        created_at,
        created_by
    FROM Form_Pengajuan_Akses_Internet
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori,'Email Account') AS kategori,
        created_at,
        created_by
    FROM Form_Pengajuan_Email_Account
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori,'Grant/Revoke Trustee') AS kategori,
        created_at,
        created_by
    FROM Form_Grant_Revoke_Trustee
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Perubahan Data Database') AS kategori,
        created_at,
        created_by
    FROM Form_Perubahan_Data_Database
    UNION ALL
    SELECT 
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Buka Tanggal Closingan') AS kategori,
        created_at,
        created_by
    FROM Form_Buka_Tanggal_Closingan
    UNION ALL
    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Pembuatan Aplikasi') AS kategori,
        created_at,
        created_by
    FROM Form_Pengajuan_Aplikasi
    UNION ALL
    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Penambahan Gudang Baru ERP') AS kategori,
        created_at,
        created_by
    FROM Form_Penambahan_Gudang_Baru_ERP
";

// Wrapper for filtering
$whereClauses = [];
$params = [];

// --- Apply Visibility Filter ---
if (!$isAdmin) {
    $visibilityClauses = [];
    $visibilityParams = [];

    // 1. Owner can always see their own forms
    $visibilityClauses[] = "x.created_by = ?";
    $visibilityParams[] = $_SESSION['NamaLengkap'];

    // 2. Approver can see categories they are authorized for
    $authorizedCategories = [];
    $globalRoles = ['Atasan Pemohon', 'Kabag IT', 'Kadept IT', 'Direksi'];
    $hasGlobalRole = false;
    foreach ($globalRoles as $gr) {
        if (in_array($gr, $userRoles)) {
            $hasGlobalRole = true;
            break;
        }
    }

    if ($hasGlobalRole) {
        // Can see everything
        $authorizedCategories = [
            'Perangkat IT', 'CCTV', 'Pemindahan Kamera CCTV', 'Akses Internet', 'Email Account',
            'Grant/Revoke Trustee', 'Perubahan Data Database', 'Buka Tanggal Closingan',
            'Pembuatan Aplikasi', 'Penambahan Gudang Baru ERP',
        ];
    } else {
        if (in_array('Petugas IT', $userRoles)) {
            $authorizedCategories = array_merge($authorizedCategories, [
                'Perangkat IT', 'Akses Internet', 'Email Account', 'Grant/Revoke Trustee',
                'Perubahan Data Database', 'Pembuatan Aplikasi', 'Penambahan Gudang Baru ERP',
            ]);
        }
        if (in_array('Petugas CCTV', $userRoles)) {
            $authorizedCategories = array_merge($authorizedCategories, ['CCTV', 'Pemindahan Kamera CCTV']);
        }

    }

    if (!empty($authorizedCategories)) {
        $authorizedCategories = array_unique($authorizedCategories);
        $placeholders = implode(',', array_fill(0, count($authorizedCategories), '?'));
        $visibilityClauses[] = "x.kategori IN ($placeholders)";
        $visibilityParams = array_merge($visibilityParams, $authorizedCategories);
    }

    $whereClauses[] = "(" . implode(" OR ", $visibilityClauses) . ")";
    $params = array_merge($params, $visibilityParams);
}

if ($filterKategori !== '') {
    $whereClauses[] = "x.kategori = ?";
    $params[] = $filterKategori;
}

if ($filterTanggal !== '') {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) = ?";
    $params[] = $filterTanggal;
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

// --- 1. Count Total Records (Without Filter) ---
// Note: Counting UNION ALL can be heavy, but necessary. 
// Optimization: If no filters, we can just sum counts of tables, but for simplicity/correctness with potential future WHEREs in base, we wrap.
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
// --- 3. Get Data ---
// Add ttd_count subquery
$dataSql = "SELECT x.*, 
            (SELECT COUNT(*) FROM Form_Pengajuan_Barang_TTD WHERE Ticket = x.ticket AND SignaturePath IS NOT NULL AND SignaturePath != '') AS ttd_count
            FROM ($baseQuery) AS x $whereSql $orderSql OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
// Add pagination params
$dataParams = array_merge($params, [$start, $length]);

$stmt = sqlsrv_query($conn, $dataSql, $dataParams);
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error', 'detail' => sqlsrv_errors()]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format Date
    $tgl = '-';
    if ($row['tgl_pengajuan'] instanceof DateTime) {
        $tgl = $row['tgl_pengajuan']->format('d-m-Y');
    }

    // Determine Required Signatures based on Ticket Type
    $ticket = $row['ticket'];
    $required = 5; // Default IT
    if (stripos($ticket, 'CCTV-') === 0) {
        $required = 5;
    } elseif (stripos($ticket, 'DB-') === 0) {
        $required = 4;
    } elseif (stripos($ticket, 'CLS-') === 0) {
        $required = 4;
    } elseif (stripos($ticket, 'APP-') === 0) {
        $required = 5;
    }

    // Badge Status Logic
    $st = strtolower(trim($row['status_ticket'] ?? ''));
    $ttdCount = (int) $row['ttd_count'];

    if ($st == 'approved') {
        $statusBadge = "<span class='badge badge-success'>Approved</span>";
    } elseif ($st == 'rejected' || $st == 'ditolak') {
        $statusBadge = "<span class='badge badge-danger'>Ditolak</span>";
    } else {
        // Logic for "X/Y Disetujui"
        if ($ttdCount >= $required) {
            // Should be approved/finished, but if status is not 'approved' yet, maybe 'Selesai' or 'Approved'
            // If it's fully signed but not marked approved in DB, it's effectively waiting finalization or is done.
            // Let's stick to "Approved" if count met, or "Menunggu Persetujuan" if 0.
            // Actually user wants "1/5 Disetujui".
            $statusBadge = "<span class='badge badge-success'>Approved</span>";
        } elseif ($ttdCount > 0) {
            $statusBadge = "<span class='badge badge-info'>{$ttdCount}/{$required} Disetujui</span>";
        } else {
            $statusBadge = "<span class='badge badge-warning'>Menunggu Persetujuan</span>";
        }
    }

    // Action Buttons
    $kategori = $row['kategori'];
    $btns = "<div class='btn-group' role='group'>";

    // Detail
    $btns .= "<button type='button' class='btn btn-info btn-sm btn-detail action-btn' data-ticket='{$ticket}' data-kategori='{$kategori}' title='Detail'><i class='fas fa-eye'></i></button>";

    // PDF & Edit (Only if not rejected)
    if ($st !== 'ditolak') {
        // PDF
        $pdfScript = 'generate_pdf.php';
        if (stripos($ticket, 'CCTV-') === 0)
            $pdfScript = 'generate_pdf_cctv.php';
        elseif (stripos($ticket, 'INET-') === 0)
            $pdfScript = 'generate_pdf_akses_internet.php';
        elseif (stripos($ticket, 'EMAIL-') === 0)
            $pdfScript = 'generate_pdf_email_account.php';
        elseif (stripos($ticket, 'GRT-') === 0)
            $pdfScript = 'generate_pdf_grant_revoke.php';
        elseif (stripos($ticket, 'DB-') === 0)
            $pdfScript = 'generate_pdf_perubahan_data_database.php';
        elseif (stripos($ticket, 'CLS-') === 0)
            $pdfScript = 'generate_pdf_buka_tanggal_closingan.php';
        elseif (stripos($ticket, 'APP-') === 0)
            $pdfScript = 'generate_pdf_pengajuan_aplikasi.php';
        elseif (stripos($ticket, 'GDG-') === 0)
            $pdfScript = 'generate_pdf_penambahan_gudang_baru_erp.php';

        $btns .= "<a href='./$pdfScript?ticket={$ticket}' class='btn btn-danger btn-sm action-btn' target='_blank' title='PDF'><i class='fas fa-file-pdf'></i></a>";

        // Edit
        if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) {
            $btns .= "<button type='button' class='btn btn-warning btn-sm btn-edit action-btn' data-ticket='{$ticket}' title='Edit'><i class='fas fa-edit'></i></button>";
        }
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
