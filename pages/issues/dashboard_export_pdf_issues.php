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
require_once __DIR__ . '/../../koneksi3.php';
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

/**
 * Memastikan teks tidak meluap dari batas lebar sel (FPDF).
 */
function fitCellText($pdf, string $text, float $maxWidth): string {
    if ($pdf->GetStringWidth($text) <= $maxWidth) {
        return $text;
    }
    while ($text !== '' && $pdf->GetStringWidth($text . '...') > $maxWidth) {
        $text = mb_substr($text, 0, -1);
    }
    return $text . '...';
}

/**
 * Mengambil ringkasan approval dari tabel PostgreSQL (insohd / prpohd).
 */
function fetchApprovalSummary($conn3, string $tableName, string $startRange, string $endRange, array $userMap, array $allowedNames = []): array {
    if (!$conn3) {
        return [];
    }

    // Bangun lookup map case-insensitive untuk daftar nama yang diizinkan
    $allowedMap = [];
    foreach ($allowedNames as $name) {
        $allowedMap[mb_strtoupper(trim($name))] = trim($name);
    }
    // Dukung variasi ejaan Yusup Sam / Yusuf Sam
    if (isset($allowedMap['YUSUP SAM'])) {
        $allowedMap['YUSUF SAM'] = $allowedMap['YUSUP SAM'];
    }
    if (isset($allowedMap['YUSUF SAM'])) {
        $allowedMap['YUSUP SAM'] = $allowedMap['YUSUF SAM'];
    }
    
    $sql = "SELECT LTRIM(RTRIM(approveby)) AS approve_by, COUNT(*) AS count
            FROM {$tableName}
            WHERE approvedate BETWEEN :start_date AND :end_date
              AND fgstatus = 'V'
              AND approveby IS NOT NULL
              AND LTRIM(RTRIM(approveby)) <> ''
            GROUP BY LTRIM(RTRIM(approveby))";
            
    $stmt = $conn3->prepare($sql);
    $stmt->execute([
        ':start_date' => $startRange,
        ':end_date'   => $endRange
    ]);
    
    $aggregated = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rawId = trim((string)$r['approve_by']);
        $picName = $userMap[strtoupper($rawId)] ?? $rawId;
        $cnt = (int)$r['count'];

        $normKey = mb_strtoupper(trim($picName));
        // Jika terdapat whitelist, hanya proses yang ada dalam daftar
        if (!empty($allowedMap)) {
            if (!isset($allowedMap[$normKey])) {
                continue; // Lewati approver yang tidak terdaftar
            }
            $picName = $allowedMap[$normKey]; // Gunakan nama kanonikal yang rapi
        }
        
        if (!isset($aggregated[$picName])) {
            $aggregated[$picName] = 0;
        }
        $aggregated[$picName] += $cnt;
    }
    
    $rows = [];
    foreach ($aggregated as $picName => $cnt) {
        if ($cnt > 0) {
            $rows[] = [
                'pic_name' => $picName,
                'count'    => $cnt
            ];
        }
    }
    
    // Urutkan berdasarkan abjad Nama PIC (A - Z)
    usort($rows, function($a, $b) {
        return strcasecmp($a['pic_name'], $b['pic_name']);
    });
    
    return $rows;
}

/**
 * Merender tabel ringkasan approval ke dalam FPDF.
 */
function renderApprovalTable($pdf, string $title, array $data, float $topSpacing = 6): void {
    // Pastikan ruang cukup sebelum mencetak judul & header tabel
    if ($pdf->GetY() + 35 > $pdf->GetPageHeight() - 20) {
        $pdf->AddPage();
    } elseif ($topSpacing > 0) {
        $pdf->Ln($topSpacing);
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $title, 0, 1, 'L');

    // Header Tabel (15mm + 120mm + 45mm = 180mm)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(15, 8, 'No', 1, 0, 'C');
    $pdf->Cell(120, 8, 'Nama PIC', 1, 0, 'C');
    $pdf->Cell(45, 8, 'Jumlah Approve', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    if (empty($data)) {
        $pdf->Cell(180, 8, 'Tidak ada data approval pada periode ini.', 1, 1, 'C');
    } else {
        $no = 1;
        $totalApprove = 0;
        foreach ($data as $item) {
            if ($pdf->GetY() + 8 > $pdf->GetPageHeight() - 20) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(15, 8, 'No', 1, 0, 'C');
                $pdf->Cell(120, 8, 'Nama PIC', 1, 0, 'C');
                $pdf->Cell(45, 8, 'Jumlah Approve', 1, 1, 'C');
                $pdf->SetFont('Arial', '', 10);
            }
            $pdf->Cell(15, 8, (string)$no++, 1, 0, 'C');
            $pdf->Cell(120, 8, fitCellText($pdf, $item['pic_name'], 118), 1, 0, 'L');
            $pdf->Cell(45, 8, number_format($item['count']), 1, 1, 'C');
            $totalApprove += $item['count'];
        }
        
        // Baris Total
        if ($pdf->GetY() + 8 > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(15, 8, 'No', 1, 0, 'C');
            $pdf->Cell(120, 8, 'Nama PIC', 1, 0, 'C');
            $pdf->Cell(45, 8, 'Jumlah Approve', 1, 1, 'C');
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(135, 8, 'Total', 1, 0, 'C');
        $pdf->Cell(45, 8, number_format($totalApprove), 1, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
    }
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

    // Category & Sub Category breakdown data
    $leaderboardSql = "SELECT 
                           COALESCE(NULLIF(LTRIM(RTRIM(i.kategori)), ''), 'Uncategorized') AS kategori,
                           COALESCE(NULLIF(LTRIM(RTRIM(i.sub_kategori)), ''), '-') AS sub_kategori,
                           COUNT(*) AS count
                       FROM dbo.issues i
                       $whereClause
                       GROUP BY 
                           COALESCE(NULLIF(LTRIM(RTRIM(i.kategori)), ''), 'Uncategorized'),
                           COALESCE(NULLIF(LTRIM(RTRIM(i.sub_kategori)), ''), '-')
                       ORDER BY kategori ASC, count DESC, sub_kategori ASC";

    $stmtLb = sqlsrv_query($conn, $leaderboardSql, $params);
    if (!$stmtLb) {
        throw new RuntimeException('Category query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $leaderboard = [];
    while ($row = sqlsrv_fetch_array($stmtLb, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = [
            'kategori' => $row['kategori'] ?: 'Uncategorized',
            'sub_kategori' => $row['sub_kategori'] ?: '-',
            'count' => (int) $row['count']
        ];
    }

    // User ID to Nama Lengkap Mapping from SQL Server
    $userMap = [];
    $userMapSql = "SELECT LTRIM(RTRIM(u.UserName)) AS user_id, LTRIM(RTRIM(e.nama_lengkap)) AS nama_lengkap
                   FROM dbo.SMUserMs u
                   INNER JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                   WHERE u.UserName IS NOT NULL AND e.nama_lengkap IS NOT NULL";
    $stmtUserMap = sqlsrv_query($conn, $userMapSql);
    if ($stmtUserMap) {
        while ($rMap = sqlsrv_fetch_array($stmtUserMap, SQLSRV_FETCH_ASSOC)) {
            if (!empty($rMap['user_id'])) {
                $userMap[strtoupper($rMap['user_id'])] = $rMap['nama_lengkap'];
            }
        }
        sqlsrv_free_stmt($stmtUserMap);
    }

    // Daftar PIC yang diizinkan (whitelist filter)
    $allowedPics = [
        'Derri Muhammad Ramdani',
        'Dika Pratama',
        'Fikri Salman Ramadhan',
        'Komara Sidik Permana',
        'Rivaldi Arya Setia',
        'Rismayanti',
        'Dade Syahrul Ramdani',
        'Mulyadi Adi Saputra',
        'Tina Martina',
        'Yusup Sam',
        'Muhammad Zhafran Irfan'
    ];

    // Sales Order & Purchase Order Approvals from PostgreSQL (koneksi3.php)
    $soApprovals = [];
    $poApprovals = [];
    if (isset($conn3)) {
        $soApprovals = fetchApprovalSummary($conn3, 'insohd', $startRange, $endRange, $userMap, $allowedPics);
        $poApprovals = fetchApprovalSummary($conn3, 'prpohd', $startRange, $endRange, $userMap, $allowedPics);
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
    $pdf->Cell(45, 8, 'Total Issue', 1, 0, 'C');
    $pdf->Cell(45, 8, 'Done', 1, 0, 'C');
    $pdf->Cell(45, 8, 'To Do', 1, 0, 'C');
    $pdf->Cell(45, 8, 'In Progress', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(45, 10, number_format($total), 1, 0, 'C');
    $pdf->Cell(45, 10, number_format($done), 1, 0, 'C');
    $pdf->Cell(45, 10, number_format($todo), 1, 0, 'C');
    $pdf->Cell(45, 10, number_format($inProgress), 1, 1, 'C');

    $pdf->Ln(6);

    // Category Breakdown Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Kategori Kegiatan', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(12, 8, 'No', 1, 0, 'C');
    $pdf->Cell(55, 8, 'Kategori', 1, 0, 'C');
    $pdf->Cell(75, 8, 'Sub Kategori', 1, 0, 'C');
    $pdf->Cell(38, 8, 'Jumlah Issue', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    if (empty($leaderboard)) {
        $pdf->Cell(180, 10, 'Belum ada issue pada periode ini.', 1, 1, 'C');
    } else {
        $rank = 1;
        foreach ($leaderboard as $row) {
            if ($pdf->GetY() + 8 > $pdf->GetPageHeight() - 20) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(12, 8, 'No', 1, 0, 'C');
                $pdf->Cell(55, 8, 'Kategori', 1, 0, 'C');
                $pdf->Cell(75, 8, 'Sub Kategori', 1, 0, 'C');
                $pdf->Cell(38, 8, 'Jumlah Issue', 1, 1, 'C');
                $pdf->SetFont('Arial', '', 10);
            }
            $pdf->Cell(12, 8, (string)$rank++, 1, 0, 'C');
            $pdf->Cell(55, 8, fitCellText($pdf, $row['kategori'], 53), 1, 0, 'L');
            $pdf->Cell(75, 8, fitCellText($pdf, $row['sub_kategori'], 73), 1, 0, 'L');
            $pdf->Cell(38, 8, number_format($row['count']), 1, 1, 'C');
        }
    }

    // IT Approval Activity Report Section
    if ($pdf->GetY() + 45 > $pdf->GetPageHeight() - 20) {
        $pdf->AddPage();
    } else {
        $pdf->Ln(8);
    }
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'IT Approval Activity Report', 0, 1, 'L');

    // Approval Tables (Sales Order & Purchase Order)
    renderApprovalTable($pdf, 'Sales Order', $soApprovals, 2);
    renderApprovalTable($pdf, 'Purchase Order', $poApprovals, 6);

    if ($pdf->GetY() + 15 > $pdf->GetPageHeight() - 15) {
        $pdf->AddPage();
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
