<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../ticket/theme_helper.php';

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

    // Ensure dates are valid datetime strings for SQL Server
    $start_date_full = $start_date . ' 00:00:00';
    $end_date_full = $end_date . ' 23:59:59';

    // Base WHERE clause
    $where = " WHERE i.created_at BETWEEN ? AND ? ";
    $params = [$start_date_full, $end_date_full];

    // 1. Stats Cards
    // Total Issues
    $sqlTotal = "SELECT COUNT(*) as cnt FROM dbo.issues i $where";
    $stmtTotal = sqlsrv_query($conn, $sqlTotal, $params);
    $total = ($stmtTotal && $row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

    // Completed (DONE or CLOSED)
    $whereCompleted = $where . " AND UPPER(LTRIM(RTRIM(i.status))) IN ('DONE', 'CLOSED') ";
    $sqlCompleted = "SELECT COUNT(*) as cnt FROM dbo.issues i $whereCompleted";
    $stmtCompleted = sqlsrv_query($conn, $sqlCompleted, $params);
    $completed = ($stmtCompleted && $row = sqlsrv_fetch_array($stmtCompleted, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

    // Open vs In Progress split
    $statusSplitSql = "SELECT 
            SUM(CASE WHEN UPPER(LTRIM(RTRIM(i.status))) IN ('OPEN', 'NEW', 'TO DO') OR i.status IS NULL THEN 1 ELSE 0 END) AS open_cnt,
            SUM(CASE WHEN UPPER(LTRIM(RTRIM(i.status))) IN ('PROSES', 'IN PROGRESS', 'PENDING') THEN 1 ELSE 0 END) AS progress_cnt
        FROM dbo.issues i
        $where";
    $stmtSplit = sqlsrv_query($conn, $statusSplitSql, $params);
    $open = 0;
    $inProgress = 0;
    if ($stmtSplit && $rowSplit = sqlsrv_fetch_array($stmtSplit, SQLSRV_FETCH_ASSOC)) {
        $open = intval($rowSplit['open_cnt'] ?? 0);
        $inProgress = intval($rowSplit['progress_cnt'] ?? 0);
    }


    // 3. Type Distribution (Doughnut Chart)
    $sqlType = "SELECT issue_type, COUNT(*) as cnt FROM dbo.issues i $where GROUP BY issue_type ORDER BY cnt DESC";
    $stmtType = sqlsrv_query($conn, $sqlType, $params);
    $typeDist = [];
    while ($stmtType && $row = sqlsrv_fetch_array($stmtType, SQLSRV_FETCH_ASSOC)) {
        $typeDist[] = [
            'label' => $row['issue_type'] ?? 'Unknown',
            'value' => $row['cnt']
        ];
    }

    // 4. Category Breakdown (Main Bar Chart)
    $sqlCategory = "SELECT TOP 10 i.kategori, COUNT(*) as count
                    FROM dbo.issues i
                    $where
                    GROUP BY i.kategori
                    ORDER BY count DESC";
    $stmtCat = sqlsrv_query($conn, $sqlCategory, $params);
    $categoryBreakdown = [];
    while ($stmtCat && $row = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
        $categoryBreakdown[] = [
            'name' => ($row['kategori'] ?: 'Uncategorized'),
            'count' => $row['count']
        ];
    }

    // 5. Leaderboard (Technicians solely for Top Performer section)
    // Count issue rows before joining employee data: duplicate employee names must not duplicate issue counts.
    $sqlLeaderboard = "WITH issue_counts AS (
                            SELECT LTRIM(RTRIM(i.created_by)) AS created_by, COUNT(*) AS count
                            FROM dbo.issues i
                            $where
                            GROUP BY LTRIM(RTRIM(i.created_by))
                        ), employee_themes AS (
                            SELECT LTRIM(RTRIM(e.nama_lengkap)) AS employee_name,
                                   COALESCE(NULLIF(us.Theme, ''), 'primary') AS theme_code,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY LTRIM(RTRIM(e.nama_lengkap))
                                       ORDER BY e.id_emp ASC
                                   ) AS theme_rank
                            FROM dbo.m_emp e
                            LEFT JOIN dbo.SMUserMs us ON us.EmpId = e.id_emp
                        )
                        SELECT TOP 10 ic.created_by, ic.count,
                               COALESCE(et.theme_code, 'primary') AS theme_code
                        FROM issue_counts ic
                        LEFT JOIN employee_themes et
                            ON et.employee_name = ic.created_by
                           AND et.theme_rank = 1
                        ORDER BY ic.count DESC, ic.created_by ASC";
    $stmtLb = sqlsrv_query($conn, $sqlLeaderboard, $params);
    $leaderboard = [];
    while ($stmtLb && $row = sqlsrv_fetch_array($stmtLb, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = [
            'name' => $row['created_by'],
            'count' => $row['count'],
            'theme' => ticket_normalize_theme($row['theme_code'] ?? 'primary')
        ];
    }

    // Identify Top Performers (Technicians)
    $topPerformers = [];
    if (!empty($leaderboard)) {
        $maxCount = $leaderboard[0]['count'];
        foreach ($leaderboard as $performer) {
            if ($performer['count'] == $maxCount) {
                $topPerformers[] = $performer;
            } else {
                break;
            }
        }
    }

    $resp['success'] = true;
    $resp['data'] = [
        'stats' => [
            'total' => $total,
            'completed' => $completed,
            'open' => $open,
            'in_progress' => $inProgress
        ],
        'type_distribution' => $typeDist,
        'category_breakdown' => $categoryBreakdown,
        'leaderboard' => $leaderboard,
        'top_performers' => $topPerformers
    ];

} catch (Exception $e) {
    $resp['message'] = $e->getMessage();
}

echo json_encode($resp);
?>
