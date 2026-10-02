<?php
// ===================================================
// 1. INISIALISASI DAN VALIDASI
// ===================================================
session_start();

if (!isset($_SESSION['UserId'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// ===================================================
// 2. DEPENDENSI & FPDF
// ===================================================
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../libs/fpdf.php';

/**
 * Memastikan format tanggal valid (YYYY-MM-DD) dan menyediakan fallback.
 */
function sanitizeDate(string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

/**
 * Menjalankan query counter sederhana dengan error handling terpusat.
 */
function fetchCount($conn, string $sql, array $params): int {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        throw new RuntimeException('Query failed: ' . print_r(sqlsrv_errors(), true));
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Mengubah tanggal ISO menjadi format yang ramah dibaca pada PDF.
 */
function formatDisplayDate(string $date): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d M Y') : $date;
}

// ===================================================
// 3. PROSES EKSPOR PDF
// ===================================================
try {
    $startInput = sanitizeDate($_GET['start_date'] ?? date('Y-m-01'), date('Y-m-01'));
    $endInput = sanitizeDate($_GET['end_date'] ?? date('Y-m-d'), date('Y-m-d'));
    $groupId = (int)($_GET['group_id'] ?? 0);

    $startRange = $startInput . ' 00:00:00';
    $endRange = $endInput . ' 23:59:59';

    $whereParts = ['t.created_at BETWEEN ? AND ?'];
    $params = [$startRange, $endRange];
    $joinGroup = '';

    if ($groupId > 0) {
        $joinGroup = ' JOIN dbo.ticket_tech_members tm ON t.assigned_to = tm.id_emp ';
        $whereParts[] = 'tm.group_id = ?';
        $params[] = $groupId;
    }

    $whereClause = ' WHERE ' . implode(' AND ', $whereParts);

    // Stats
    $total = fetchCount($conn, "SELECT COUNT(*) AS cnt FROM dbo.tickets t $joinGroup $whereClause", $params);
    $completed = fetchCount($conn, "SELECT COUNT(*) AS cnt FROM dbo.tickets t $joinGroup $whereClause AND t.closed_at IS NOT NULL", $params);
    $statusSql = "SELECT 
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
        $whereClause";
    $stmtStatus = sqlsrv_query($conn, $statusSql, $params);
    if (!$stmtStatus) {
        throw new RuntimeException('Status split query failed: ' . print_r(sqlsrv_errors(), true));
    }
    $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC) ?: [];
    $open = (int)($rowStatus['open_cnt'] ?? 0);
    $inProgress = (int)($rowStatus['progress_cnt'] ?? 0);
    sqlsrv_free_stmt($stmtStatus);

    // Leaderboard data (top 10 based on closed tickets)
    $lbParams = [$startRange, $endRange];
    if ($groupId > 0) {
        $lbParams[] = $groupId;
    }

        $leaderboardSql = "SELECT TOP 10 t.assigned_to, e.nama_lengkap, COUNT(*) AS completed_count
                                             FROM dbo.tickets t
                                             JOIN dbo.m_emp e ON t.assigned_to = e.id_emp
                                             $joinGroup
                                             WHERE t.closed_at BETWEEN ? AND ?
                                                 AND t.assigned_to IS NOT NULL" . ($groupId > 0 ? " AND tm.group_id = ?" : "") . "
                                             GROUP BY t.assigned_to, e.nama_lengkap
                                             ORDER BY completed_count DESC";

    $stmtLb = sqlsrv_query($conn, $leaderboardSql, $lbParams);
    if (!$stmtLb) {
        throw new RuntimeException('Leaderboard query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $leaderboard = [];
    while ($row = sqlsrv_fetch_array($stmtLb, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = [
            'name' => $row['nama_lengkap'],
            'count' => (int) $row['completed_count']
        ];
    }

    // Group Label
    $groupLabel = 'All Groups';
    if ($groupId > 0) {
        $stmtGroup = sqlsrv_query($conn, 'SELECT group_name FROM dbo.ticket_tech_groups WHERE id = ?', [$groupId]);
        if ($stmtGroup && $row = sqlsrv_fetch_array($stmtGroup, SQLSRV_FETCH_ASSOC)) {
            $groupLabel = $row['group_name'];
        } else {
            $groupLabel = 'Group #' . $groupId;
        }
    }

    // PDF Rendering
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle('IT Performance Dashboard');
    $pdf->SetAuthor('GG App');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'IT Performance Dashboard', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Periode: ' . formatDisplayDate($startInput) . ' s/d ' . formatDisplayDate($endInput), 0, 1, 'L');
    $pdf->Cell(0, 7, 'Group: ' . $groupLabel, 0, 1, 'L');
    $pdf->Ln(3);

    // Summary Stats Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Ringkasan Ticket', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(44, 8, 'Total Ticket', 1, 0, 'C');
    $pdf->Cell(44, 8, 'Selesai', 1, 0, 'C');
    $pdf->Cell(44, 8, 'Open', 1, 0, 'C');
    $pdf->Cell(43, 8, 'In Progress', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(44, 10, number_format($total), 1, 0, 'C');
    $pdf->Cell(44, 10, number_format($completed), 1, 0, 'C');
    $pdf->Cell(44, 10, number_format($open), 1, 0, 'C');
    $pdf->Cell(43, 10, number_format($inProgress), 1, 1, 'C');

    $pdf->Ln(6);

    // Leaderboard Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Leaderboard Teknisi (Ticket Selesai)', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(15, 8, 'No', 1, 0, 'C');
    $pdf->Cell(115, 8, 'Teknisi', 1, 0, 'C');
    $pdf->Cell(45, 8, 'Ticket Selesai', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    if (empty($leaderboard)) {
        $pdf->Cell(175, 10, 'Belum ada ticket selesai pada periode ini.', 1, 1, 'C');
    } else {
        $rank = 1;
        foreach ($leaderboard as $row) {
            $pdf->Cell(15, 8, $rank++, 1, 0, 'C');
            $pdf->Cell(115, 8, $row['name'], 1, 0, 'L');
            $pdf->Cell(45, 8, number_format($row['count']), 1, 1, 'C');
        }
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, 'Generated on ' . date('d M Y H:i') . ' by ' . ($_SESSION['UserName'] ?? 'System'), 0, 1, 'R');

    $filename = 'dashboard_it_' . date('Ymd_His') . '.pdf';
    $pdf->Output('I', $filename);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Gagal membuat PDF Dashboard: ' . $e->getMessage();
}
