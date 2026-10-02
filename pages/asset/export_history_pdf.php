<?php
// export_history_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak: Silakan login terlebih dahulu.");
}

// Check permissions
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 56);
if ($permissions['CanView'] != 1) {
    die("Akses ditolak: Anda tidak memiliki hak akses.");
}

date_default_timezone_set('Asia/Jakarta');

try {
    // Create PDF instance with landscape orientation
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GG System');
    $pdf->SetTitle('Laporan History Asset IT');
    $pdf->SetSubject('History Asset');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Judul
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'LAPORAN HISTORY PERUBAHAN ASSET IT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Info filter
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $filterText = 'Filter Tanggal: ' . ($startDate ? date('d-m-Y', strtotime($startDate)) : 'Awal') . ' s/d ' . ($endDate ? date('d-m-Y', strtotime($endDate)) : 'Akhir');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Filter Data:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $filterText, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Tanggal Cetak:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, date('d F Y H:i:s'), 0, 1);
    $pdf->Ln(10);
    
    // --- Ambil Data ---
    $whereSql = "WHERE 1=1";
    $params = [];

    if ($startDate !== '') {
        $whereSql .= " AND CAST(h.created_at AS DATE) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $whereSql .= " AND CAST(h.created_at AS DATE) <= ?";
        $params[] = $endDate;
    }

    $sql = "
        SELECT h.old_status, h.new_status, h.note, h.jenis_perubahan, 
               h.created_by, h.created_at,
               a.kode_asset_seq, m.nama_merk, t.nama_tipe
        FROM dbo.asset_history h
        LEFT JOIN dbo.m_asset a ON h.id_asset = a.id_asset
        LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
        LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
        $whereSql
        ORDER BY h.created_at DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception("Database query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $historyData = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $historyData[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    // Header tabel utama
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'DETAIL HISTORY ASSET', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Header tabel
    $header = ['No', 'Tanggal', 'User', 'Kode Asset', 'Merk / Tipe', 'Perubahan Status', 'Jenis Perubahan', 'Keterangan'];
    $w = [10, 28, 20, 22, 40, 35, 30, 82]; // Lebar kolom disesuaikan (Total 267)
    
    // Set header table
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('helvetica', '', 8);
    $fill = false;
    
    foreach ($historyData as $index => $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 170) { 
            $pdf->AddPage('L');
            // Redraw header
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }
        
        $createdAt = '';
        if (isset($row['created_at'])) {
            if ($row['created_at'] instanceof DateTime) {
                $createdAt = $row['created_at']->format('d-m-Y H:i');
            } else {
                $createdAt = date('d-m-Y H:i', strtotime($row['created_at']));
            }
        }
        
        $oldStatus = $row['old_status'] ?? '-';
        $newStatus = $row['new_status'] ?? '-';
        $perubahanStatus = $oldStatus . ' -> ' . $newStatus;

        $merk = $row['nama_merk'] ?? '';
        $tipe = $row['nama_tipe'] ?? '';
        $merkTipe = trim($merk . ($merk !== '' && $tipe !== '' ? ' / ' : '') . $tipe);
        
        // Output rows using Cell for single lines and MultiCell for notes if needed.
        // For simplicity and better alignment, we'll use Cell since we truncated Note in Asset.
        // Since History note can be long, we use a custom method for Keterangan.
        
        // Menentukan tinggi baris (mengambil yang terbesar)
        $h1 = $pdf->getStringHeight($w[4], $merkTipe); // Merk / Tipe
        $h2 = $pdf->getStringHeight($w[5], $perubahanStatus); // Perubahan Status
        $h3 = $pdf->getStringHeight($w[6], $row['jenis_perubahan'] ?? '-'); // Jenis
        $h4 = $pdf->getStringHeight($w[7], $row['note'] ?? '-'); // Note
        
        // Cari height maximum
        $rowHeight = max(max($h1, $h2), max($h3, $h4));
        // Set minimum height
        if ($rowHeight < 7) {
            $rowHeight = 7;
        }
        
        // Simpan cursor position
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        // Print Cells MultiCell pattern
        
        // No 
        $pdf->MultiCell($w[0], $rowHeight, $index + 1, 'LR', 'C', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[0];
        
        // Tanggal
        $pdf->MultiCell($w[1], $rowHeight, $createdAt, 'LR', 'C', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[1];
        
        // User
        $pdf->MultiCell($w[2], $rowHeight, $row['created_by'], 'LR', 'C', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[2];
        
        // Kode Asset
        $pdf->MultiCell($w[3], $rowHeight, $row['kode_asset_seq'] ?? '-', 'LR', 'L', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[3];
        
        // Merk / Tipe
        $pdf->MultiCell($w[4], $rowHeight, $merkTipe, 'LR', 'L', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[4];
        
        // Perubahan Status
        $pdf->MultiCell($w[5], $rowHeight, $perubahanStatus, 'LR', 'C', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[5];
        
        // Jenis Perubahan
        $pdf->MultiCell($w[6], $rowHeight, $row['jenis_perubahan'] ?? '-', 'LR', 'C', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        $startX += $w[6];
        
        // Keterangan
        $pdf->MultiCell($w[7], $rowHeight, $row['note'] ?? '-', 'LR', 'L', false, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');
        
        // Pindah baris
        $pdf->Ln($rowHeight);
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Membersihkan buffer sebelum output untuk mencegah konflik "Some data has already been output"
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Output PDF
    $pdf->Output('Laporan History Asset IT_' . date('dmY') . '.pdf', 'I'); // I = Inline, D = Download
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}
