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
$sqlRole = "SELECT u.EmpId, g.GroupName
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            WHERE u.UserId = ?";
$stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);
$roleRow  = $stmtRole ? sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC) : null;
if ($stmtRole) sqlsrv_free_stmt($stmtRole);

$userEmpId    = intval($roleRow['EmpId'] ?? 0);
$userGroup    = trim($roleRow['GroupName'] ?? '');
$userFullname = $_SESSION['NamaLengkap'] ?? '';
$isAdmin      = (strcasecmp($userGroup, 'Administrator') === 0);

// Base visibility: non-admin hanya lihat ticket yang dia buat / di-assign
$baseParams = [];
$whereParts = [];
if (!$isAdmin) {
    $clauses = [];
    if ($userEmpId > 0) {
        $clauses[]   = '(t.creator_id = ? OR t.assigned_to = ?)';
        $baseParams[] = $userEmpId;
        $baseParams[] = $userEmpId;
    }
    if ($userFullname !== '') {
        $clauses[]   = 't.creator_name = ?';
        $baseParams[] = $userFullname;
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
    // 4. AMBIL DETAIL TICKET TERKINI (OPSIONAL)
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

    $response['success'] = true;
    $response['latest_ticket_id'] = $latest;
    $response['latest_ticket_update'] = $latestUpdateStr;
    $response['has_new'] = ($latest > $lastKnown);
    $response['has_update'] = ($latestUpdateTs > $lastUpdateClientTs);
    $response['latest_ticket'] = $latestTicket;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
