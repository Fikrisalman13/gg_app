<?php
// ===================================================
// 1. INISIALISASI & VALIDASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

$response = [
    'success' => false,
    'has_new' => false,
    'has_update' => false,
    'latest_ticket_id' => 0,
    'latest_ticket_update' => null,
    'latest_ticket' => null
];

if (!isset($_SESSION['UserId']) || !$conn) {
    echo json_encode($response);
    exit;
}

// ===================================================
// 2. TENTUKAN ROLE DAN VISIBILITAS USER
// ===================================================
// Tentukan role & visibility sama seperti di ticket_serverside.php
$userId = intval($_SESSION['UserId']);
$sqlRole = "SELECT u.EmpId, g.GroupId, g.GroupName, d.dept AS department_name
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            WHERE u.UserId = ?";
$stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);
$roleRow  = $stmtRole ? sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC) : null;
if ($stmtRole) sqlsrv_free_stmt($stmtRole);

$userEmpId    = intval($roleRow['EmpId'] ?? 0);
$userGroupId = intval($roleRow['GroupId'] ?? 0);
$userGroup    = trim($roleRow['GroupName'] ?? '');
$userDept    = trim($roleRow['department_name'] ?? '');
$userFullname = $_SESSION['NamaLengkap'] ?? '';
$isAdmin     = ($userGroupId === 1);
$isITStaff   = (stripos($userDept, 'Information Technology') !== false) || (strtoupper($userDept) === 'IT');

// Fetch Auto Assign Mode configuration
$autoAssignMode = false;
$sqlConfig = "SELECT config_value FROM dbo.ticket_config WHERE config_key = 'auto_assign_mode'";
$stmtConfig = sqlsrv_query($conn, $sqlConfig);
$configRow = $stmtConfig ? sqlsrv_fetch_array($stmtConfig, SQLSRV_FETCH_ASSOC) : null;
if ($stmtConfig) sqlsrv_free_stmt($stmtConfig);
$autoAssignMode = ($configRow && $configRow['config_value'] === '1');

// Base visibility: non-admin hanya lihat ticket yang dia buat / di-assign
$baseParams = [];
$whereParts = [];
if (!$isAdmin) {
    $clauses = [];
    if ($userEmpId > 0) {
        $clauses[]   = '(t.creator_id = ? OR t.assigned_to = ?)';
        $baseParams[] = $userEmpId;     // Fix: creator_id is UserId, but assuming original logic meant userEmpId. 
                                        // Wait, ticket_serverside.php logic says: 
                                        // $clauses[] = '(t.creator_id = ? OR t.assigned_to = ?)';
                                        // $baseParams[] = $userId;
                                        // $baseParams[] = $userEmpId;
                                        // Let's match ticket_serverside.php logic exactly!
        // Actually, let's look at check_new_tickets.php original again. 
        // It used $userEmpId for creator_id. This might be wrong if creator_id stores UserId elsewhere.
        // But let's stick to what works in ticket_serverside.php
    }
    
    // REFRESH with ticket_serverside.php logic:
    // 1. Own tickets (creator_id=UserId OR assigned_to=EmpId)
    if ($userEmpId > 0) {
         $clauses[]   = '(t.creator_id = ? OR t.assigned_to = ?)';
         $baseParams[] = $userId;    // Corrected to userId for creator_id matching ticket_serverside
         $baseParams[] = $userEmpId;
    } else {
         $clauses[]    = 't.creator_id = ?';
         $baseParams[] = $userId;
    }

    if ($userFullname !== '') {
        $clauses[]   = 't.creator_name = ?';
        $baseParams[] = $userFullname;
    }
    
    // AUTO ASSIGN MODE: IT staff (non-admin) can see unassigned tickets
    if ($autoAssignMode && $isITStaff && !$isAdmin) {
        $clauses[] = '(t.assigned_to IS NULL OR t.assigned_to = 0)';
    }

    if ($clauses) {
        $whereParts[] = '(' . implode(' OR ', $clauses) . ')';
    } else {
        $whereParts[] = '1=0';
    }
}

// Batasi hanya ticket aktif (belum selesai/cancel) agar notifikasi tidak muncul untuk ticket selesai
$statusJoin = " LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id ";
$activeCondition = "(t.closed_at IS NULL AND (ts.status_name IS NULL OR ts.status_name NOT IN ('Selesai','Done','Closed','Complete','Cancel','Rejected')))";
$whereParts[] = $activeCondition;
$baseWhere = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';

try {
    // ===================================================
    // 3. CARI TICKET TERBARU SESUAI VISIBILITAS
    // ===================================================
    $lastKnown = intval($_POST['last_ticket_id'] ?? 0);
    $lastUpdateClient = trim($_POST['last_ticket_update'] ?? '');
    $lastUpdateClientTs = $lastUpdateClient ? strtotime($lastUpdateClient) : 0;

    // Cari ticket terbaru yang memang boleh dilihat user
        $sql = "SELECT MAX(t.ticket_id) AS latest_id,
               MAX(ISNULL(t.updated_at, t.created_at)) AS latest_update
            FROM dbo.tickets t" . $statusJoin . $baseWhere;
        $stmt = sqlsrv_query($conn, $sql, $baseParams);
    $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : ['latest_id' => 0, 'latest_update' => null];
    if ($stmt) sqlsrv_free_stmt($stmt);
    $latest = intval($row['latest_id'] ?? 0);
    $latestUpdate = $row['latest_update'] ?? null;
    if ($latestUpdate instanceof DateTimeInterface) {
        $latestUpdateStr = $latestUpdate->format('Y-m-d H:i:s');
        $latestUpdateTs = strtotime($latestUpdateStr);
    } else if ($latestUpdate) {
        $latestUpdateStr = (string)$latestUpdate;
        $latestUpdateTs = strtotime($latestUpdateStr) ?: 0;
    } else {
        $latestUpdateStr = null;
        $latestUpdateTs = 0;
    }

    // ===================================================
    // 4. CEK TICKET YANG BARU DI-CLOSE (PINDAH KE HISTORY)
    // ===================================================
    // Check if any ticket has been closed since last check
    $hasClosedTicket = false;
    if ($lastUpdateClient && $lastUpdateClientTs > 0) {
        // Check for tickets that were recently closed (closed_at is NOT NULL and updated recently)
        $sqlClosed = "SELECT COUNT(*) AS cnt FROM dbo.tickets t
                      WHERE t.closed_at IS NOT NULL 
                      AND ISNULL(t.updated_at, t.created_at) > ?";
        $closedParams = [$lastUpdateClient];
        
        // Add visibility filter for non-admin
        if (!$isAdmin) {
            $closedClauses = [];
            if ($userEmpId > 0) {
                $sqlClosed .= " AND (t.creator_id = ? OR t.assigned_to = ?)";
                $closedParams[] = $userEmpId;
                $closedParams[] = $userEmpId;
            }
            if ($userFullname !== '') {
                $sqlClosed .= " AND t.creator_name = ?";
                $closedParams[] = $userFullname;
            }
        }
        
        $stmtClosed = sqlsrv_query($conn, $sqlClosed, $closedParams);
        if ($stmtClosed) {
            $rowClosed = sqlsrv_fetch_array($stmtClosed, SQLSRV_FETCH_ASSOC);
            $closedCount = intval($rowClosed['cnt'] ?? 0);
            $hasClosedTicket = ($closedCount > 0);
            sqlsrv_free_stmt($stmtClosed);
        }
    }

    // ===================================================
    // 5. AMBIL DETAIL TICKET TERKINI (OPSIONAL)
    // ===================================================
    $latestTicket = null;
    if ($latest > 0) {
        // Detail ticket terbaru yang juga masih sesuai visibility user
        $detailWhere = $baseWhere ? $baseWhere . ' AND t.ticket_id = ?' : ' WHERE t.ticket_id = ?';
        $sqlDetail = "SELECT TOP 1 t.ticket_id, t.ticket_no, t.subject, t.creator_name, t.creator_dept
                      FROM dbo.tickets t" . $statusJoin . $detailWhere;
        $detailParams = array_merge($baseParams, [$latest]);
        $stmtDetail = sqlsrv_query($conn, $sqlDetail, $detailParams);
        if ($stmtDetail) {
            $rowDetail = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC);
            if ($rowDetail) {
                $latestTicket = [
                    'ticket_id' => intval($rowDetail['ticket_id'] ?? 0),
                    'ticket_no' => (string)($rowDetail['ticket_no'] ?? ''),
                    'subject' => (string)($rowDetail['subject'] ?? ''),
                    'creator_name' => (string)($rowDetail['creator_name'] ?? ''),
                    'creator_dept' => (string)($rowDetail['creator_dept'] ?? '')
                ];
            }
            sqlsrv_free_stmt($stmtDetail);
        }
    }

    // ===================================================
    // 6. COUNT ASSIGNED TICKETS (FOR TECH REFRESH SIGNAL)
    // ===================================================
    $assignedCount = 0;
    if ($userEmpId > 0 && !$isAdmin) {
        // Count active tickets assigned to this user
        $sqlCount = "SELECT COUNT(*) AS cnt FROM dbo.tickets t 
                     LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
                     WHERE t.assigned_to = ? 
                     AND (t.closed_at IS NULL AND (ts.status_name IS NULL OR ts.status_name NOT IN ('Selesai','Done','Closed','Complete','Cancel','Rejected')))";
        $stmtCount = sqlsrv_query($conn, $sqlCount, [$userEmpId]);
        if ($stmtCount && $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
            $assignedCount = intval($rowCount['cnt']);
        }
        if ($stmtCount) sqlsrv_free_stmt($stmtCount);
    }

    // ===================================================
    // 7. COUNT UNASSIGNED TICKETS (FOR POOL SYNC)
    // ===================================================
    $unassignedCount = 0;
    if ($isITStaff && !$isAdmin) {
        $sqlUnassigned = "SELECT COUNT(*) AS cnt FROM dbo.tickets t
                          LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
                          WHERE (t.assigned_to IS NULL OR t.assigned_to = 0)
                          AND (t.closed_at IS NULL AND (ts.status_name IS NULL OR ts.status_name NOT IN ('Selesai','Done','Closed','Complete','Cancel','Rejected')))";
        $stmtUnassigned = sqlsrv_query($conn, $sqlUnassigned);
        if ($stmtUnassigned && $rowUnassigned = sqlsrv_fetch_array($stmtUnassigned, SQLSRV_FETCH_ASSOC)) {
            $unassignedCount = intval($rowUnassigned['cnt']);
        }
        if ($stmtUnassigned) sqlsrv_free_stmt($stmtUnassigned);
    }

    $response['success'] = true;
    $response['latest_ticket_id'] = $latest;
    $response['latest_ticket_update'] = $latestUpdateStr;
    $response['has_new'] = ($latest > $lastKnown);
    $response['has_update'] = ($latestUpdateTs > $lastUpdateClientTs) || $hasClosedTicket;
    $response['latest_ticket'] = $latestTicket;
    $response['auto_assign_mode'] = $autoAssignMode;
    $response['assigned_count'] = $assignedCount;
    $response['unassigned_count'] = $unassignedCount;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
