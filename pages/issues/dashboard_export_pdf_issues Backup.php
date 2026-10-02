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
require_once __DIR__ . '/../ticket/theme_helper.php';

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

    $startRange = $startInput . ' 00:00:00';
    $endRange = $endInput . ' 23:59:59';

    $whereClause = ' WHERE i.created_at BETWEEN ? AND ? ';
    $params = [$startRange, $endRange];

    // Stats
    $total = fetchCount($conn, "SELECT COUNT(*) AS cnt FROM dbo.issues i $whereClause", $params);
    $done = fetchCount($conn, "SELECT COUNT(*) AS cnt FROM dbo.issues i $whereClause AND UPPER(LTRIM(RTRIM(i.status))) IN ('DONE', 'CLOSED')", $params);
    
    $statusSql = "SELECT 
            SUM(CASE WHEN UPPER(LTRIM(RTRIM(i.status))) IN ('OPEN', 'NEW', 'TO DO') OR i.status IS NULL THEN 1 ELSE 0 END) AS open_cnt,
            SUM(CASE WHEN UPPER(LTRIM(RTRIM(i.status))) IN ('PROSES', 'IN PROGRESS', 'PENDING') THEN 1 ELSE 0 END) AS progress_cnt
        FROM dbo.issues i
        $whereClause";
    $stmtStatus = sqlsrv_query($conn, $statusSql, $params);
    if (!$stmtStatus) {
        throw new RuntimeException('Status split query failed: ' . print_r(sqlsrv_errors(), true));
    }
    $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC) ?: [];
    $todo = (int)($rowStatus['open_cnt'] ?? 0);
    $inProgress = (int)($rowStatus['progress_cnt'] ?? 0);
    sqlsrv_free_stmt($stmtStatus);

    // Leaderboard data (top 10 based on issues recorded)
    $leaderboardSql = "SELECT TOP 10 i.created_by, COUNT(*) AS count
                       FROM dbo.issues i
                       $whereClause
                       GROUP BY i.created_by
                       ORDER BY count DESC";

    $stmtLb = sqlsrv_query($conn, $leaderboardSql, $params);
    if (!$stmtLb) {
        throw new RuntimeException('Leaderboard query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $leaderboard = [];
    while ($row = sqlsrv_fetch_array($stmtLb, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = [
            'name' => $row['created_by'],
            'count' => (int) $row['count']
        ];
    }

    // PDF Rendering
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle('Issues Analytics Dashboard');
    $pdf->SetAuthor('GG App');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Issues Analytics Dashboard', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Periode: ' . formatDisplayDate($startInput) . ' s/d ' . formatDisplayDate($endInput), 0, 1, 'L');
    $pdf->Ln(3);

    // Summary Stats Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Ringkasan Issue', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(44, 8, 'Total Issue', 1, 0, 'C');
    $pdf->Cell(44, 8, 'Done', 1, 0, 'C');
    $pdf->Cell(44, 8, 'To Do', 1, 0, 'C');
    $pdf->Cell(43, 8, 'In Progress', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(44, 10, number_format($total), 1, 0, 'C');
    $pdf->Cell(44, 10, number_format($done), 1, 0, 'C');
    $pdf->Cell(44, 10, number_format($todo), 1, 0, 'C');
    $pdf->Cell(43, 10, number_format($inProgress), 1, 1, 'C');

    $pdf->Ln(6);

    // Leaderboard Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Leaderboard Teknisi', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(15, 8, 'No', 1, 0, 'C');
    $pdf->Cell(115, 8, 'Teknisi', 1, 0, 'C');
    $pdf->Cell(45, 8, 'Jumlah Issue', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    if (empty($leaderboard)) {
        $pdf->Cell(175, 10, 'Belum ada issue pada periode ini.', 1, 1, 'C');
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

    $filename = 'dashboard_issues_' . date('Ymd_His') . '.pdf';
    $pdf->Output('I', $filename);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Gagal membuat PDF Dashboard: ' . $e->getMessage();
}
