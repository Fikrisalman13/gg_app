<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

// ===================================================
// 2. RESPON DEFAULT
// ===================================================
$resp = ['success' => false, 'data' => []];

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserId'])) {
    echo json_encode($resp);
    exit;
}

// ===================================================
// 4. PROSES PEMBENTUKAN DATA DASHBOARD
// ===================================================
try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default first day of month
    $end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default today
    $group_id = intval($_GET['group_id'] ?? 0);

    // Ensure dates are valid datetime strings for SQL Server
    $start_date .= ' 00:00:00';
    $end_date .= ' 23:59:59';

    // Base WHERE clause
    $where = " WHERE t.created_at BETWEEN ? AND ? ";
    $params = [$start_date, $end_date];

    // Group Filter Logic
    $joinGroup = "";
    if ($group_id > 0) {
        $joinGroup = " JOIN dbo.ticket_tech_members tm ON t.assigned_to = tm.id_emp ";
        $where .= " AND tm.group_id = ? ";
        $params[] = $group_id;
    }

    // 1. Stats Cards
    // Total Tickets
    $sqlTotal = "SELECT COUNT(*) as cnt FROM dbo.tickets t $joinGroup $where";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal, $params);
    $total = ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

    // Completed (closed_at IS NOT NULL)
    $whereCompleted = $where . " AND t.closed_at IS NOT NULL ";
    $sqlCompleted = "SELECT COUNT(*) as cnt FROM dbo.tickets t $joinGroup $whereCompleted";
    $stmtCompleted = sqlsrv_query($conn, $sqlCompleted, $params);
    $completed = ($stmtCompleted && $row = sqlsrv_fetch_array($stmtCompleted, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

    // Open vs In Progress split counts (based on ticket_statuses)
    $statusSplitSql = "SELECT 
            SUM(CASE 
                    WHEN t.closed_at IS NULL AND (
                        (norm_status.status_slug IS NOT NULL AND norm_status.status_slug IN ('open','new'))
                        OR (norm_status.status_slug IS NULL AND t.assigned_to IS NULL)
                    ) THEN 1 ELSE 0 END
            ) AS open_cnt,
            SUM(CASE 
                    WHEN t.closed_at IS NULL AND (
                        (norm_status.status_slug IS NOT NULL AND norm_status.status_slug IN ('proses','process','progress','in progress','on progress','sedang proses','ongoing'))
                        OR (norm_status.status_slug IS NULL AND t.assigned_to IS NOT NULL)
                    ) THEN 1 ELSE 0 END
            ) AS progress_cnt
        FROM dbo.tickets t
        $joinGroup
        LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
        OUTER APPLY (SELECT LOWER(LTRIM(RTRIM(ts.status_name))) AS status_slug) AS norm_status
        $where";
    $stmtSplit = sqlsrv_query($conn, $statusSplitSql, $params);
    $open = 0;
    $inProgress = 0;
    if ($stmtSplit && $rowSplit = sqlsrv_fetch_array($stmtSplit, SQLSRV_FETCH_ASSOC)) {
        $open = intval($rowSplit['open_cnt'] ?? 0);
        $inProgress = intval($rowSplit['progress_cnt'] ?? 0);
    }
    if ($stmtSplit) sqlsrv_free_stmt($stmtSplit);

    // 2. Leaderboard (Top Performers based on COMPLETED tickets)
    // Use assigned_to as the technician
    $sqlLeaderboard = "SELECT TOP 10 t.assigned_to, e.nama_lengkap, COUNT(*) as completed_count,
                              COALESCE(NULLIF(us.Theme, ''), 'primary') AS theme_code
                       FROM dbo.tickets t
                       JOIN dbo.m_emp e ON t.assigned_to = e.id_emp
                       LEFT JOIN dbo.SMUserMs us ON us.EmpId = t.assigned_to
                       $joinGroup
                       WHERE t.closed_at BETWEEN ? AND ?
                       AND t.assigned_to IS NOT NULL
                       " . ($group_id > 0 ? " AND tm.group_id = ? " : "") . "
                       GROUP BY t.assigned_to, e.nama_lengkap, COALESCE(NULLIF(us.Theme, ''), 'primary')
                       ORDER BY completed_count DESC";
    
    // Params for leaderboard (same as base params but for closed_at range)
    $lbParams = [$start_date, $end_date];
    if ($group_id > 0) $lbParams[] = $group_id;

    $stmtLb = sqlsrv_query($conn, $sqlLeaderboard, $lbParams);
    $leaderboard = [];
    while ($stmtLb && $row = sqlsrv_fetch_array($stmtLb, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = [
            'name' => $row['nama_lengkap'],
            'count' => $row['completed_count'],
            'theme' => ticket_normalize_theme($row['theme_code'] ?? 'primary')
        ];
    }

    // Identify Top Performers (All with the highest count)
    $topPerformers = [];
    if (!empty($leaderboard)) {
        $maxCount = $leaderboard[0]['count'];
        foreach ($leaderboard as $performer) {
            if ($performer['count'] == $maxCount) {
                $topPerformers[] = $performer;
            } else {
                break; // Stop when count is lower
            }
        }
    }

    // 3. Recent Activities (10 latest ticket activities)
    // Show: ticket created, assigned, or closed
    $sqlActivities = "SELECT TOP 10 
                        t.ticket_id,
                        t.ticket_no,
                        t.subject,
                        t.creator_name,
                        t.created_at,
                        t.assigned_to,
                        e.nama_lengkap as assigned_name,
                        t.closed_at,
                        t.updated_at,
                        ts.status_name
                      FROM dbo.tickets t
                      LEFT JOIN dbo.m_emp e ON t.assigned_to = e.id_emp
                      LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
                      $joinGroup
                      $where
                      ORDER BY COALESCE(t.updated_at, t.created_at) DESC";
    
    $stmtAct = sqlsrv_query($conn, $sqlActivities, $params);
    $activities = [];
    while ($stmtAct && $row = sqlsrv_fetch_array($stmtAct, SQLSRV_FETCH_ASSOC)) {
        // Determine activity type and time
        $activityType = 'created';
        $activityTime = $row['created_at'];
        $activityIcon = 'fa-plus-circle';
        $activityColor = 'info';
        $activityText = 'Ticket dibuat oleh ' . htmlspecialchars($row['creator_name']);
        $statusDisplay = $row['status_name'] ?? 'Open';

        if ($row['closed_at']) {
            $activityType = 'closed';
            $activityTime = $row['closed_at'];
            $activityIcon = 'fa-check-circle';
            $activityColor = 'success';
            $activityText = 'Ticket diselesaikan';
            $statusDisplay = 'Selesai'; // Force status to 'Selesai' for closed tickets
        } elseif ($row['assigned_to'] && $row['updated_at'] && $row['updated_at'] != $row['created_at']) {
            $activityType = 'updated';
            $activityTime = $row['updated_at'];
            $activityIcon = 'fa-sync-alt';
            $activityColor = 'warning';
            $activityText = 'Ticket diupdate - Assigned to ' . htmlspecialchars($row['assigned_name'] ?? 'Unknown');
        }

        // Format time
        $timeFormatted = '';
        if ($activityTime instanceof DateTime) {
            $timeFormatted = $activityTime->format('Y-m-d H:i:s');
        } else {
            $timeFormatted = (string)$activityTime;
        }

        $activities[] = [
            'ticket_id' => $row['ticket_id'],
            'ticket_no' => $row['ticket_no'],
            'subject' => $row['subject'],
            'type' => $activityType,
            'text' => $activityText,
            'icon' => $activityIcon,
            'color' => $activityColor,
            'time' => $timeFormatted,
            'status' => $statusDisplay
        ];
    }
    if ($stmtAct) sqlsrv_free_stmt($stmtAct);

    $resp['success'] = true;
    $resp['data'] = [
        'stats' => [
            'total' => $total,
            'completed' => $completed,
            'open' => $open,
            'in_progress' => $inProgress
        ],
        'leaderboard' => $leaderboard,
        'top_performers' => $topPerformers,
        'recent_activities' => $activities
    ];

} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
