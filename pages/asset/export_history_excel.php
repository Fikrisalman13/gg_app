<?php
// export_history_excel.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Judul Header
    $sheet->setCellValue('A1', 'LAPORAN HISTORY PERUBAHAN ASSET IT');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Tanggal Cetak
    $sheet->setCellValue('A2', 'Tanggal Cetak: ' . date('d F Y H:i:s'));
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Filter Info
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $filterText = 'Filter Tanggal: ' . ($startDate ? date('d-m-Y', strtotime($startDate)) : 'Awal') . ' s/d ' . ($endDate ? date('d-m-Y', strtotime($endDate)) : 'Akhir');
    
    $sheet->setCellValue('A3', $filterText);
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->getFont()->setItalic(true);
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Kolom Tabel
    $headers = [
        'A' => 'No',
        'B' => 'Tanggal Perubahan',
        'C' => 'User',
        'D' => 'Kode Asset',
        'E' => 'Merk / Tipe',
        'F' => 'Perubahan Status',
        'G' => 'Jenis Perubahan',
        'H' => 'Keterangan'
    ];

    $rowNum = 5;
    foreach ($headers as $col => $title) {
        $sheet->setCellValue($col . $rowNum, $title);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        // Style Header
        $sheet->getStyle($col . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle($col . $rowNum)->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($col . $rowNum)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFD9D9D9');
        $sheet->getStyle($col . $rowNum)->getBorders()
              ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // Ambil Data
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

    $rowNum = 6;
    $no = 1;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        
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

        $sheet->setCellValueExplicit('A' . $rowNum, $no++, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('B' . $rowNum, $createdAt, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $rowNum, $row['created_by'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $rowNum, $row['kode_asset_seq'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E' . $rowNum, $merkTipe, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F' . $rowNum, $perubahanStatus, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G' . $rowNum, $row['jenis_perubahan'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H' . $rowNum, $row['note'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Styling data cells
        for ($col = 'A'; $col !== 'I'; $col++) {
            $sheet->getStyle($col . $rowNum)->getBorders()
                  ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            // Kolom A-G Center Text, Kolom H (Note) Left align text  
            if ($col !== 'H') {
                $sheet->getStyle($col . $rowNum)->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                      ->setVertical(Alignment::VERTICAL_CENTER);
            } else {
                $sheet->getStyle($col . $rowNum)->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                      ->setVertical(Alignment::VERTICAL_CENTER)
                      ->setWrapText(true);
            }
        }
        $rowNum++;
    }
    
    sqlsrv_free_stmt($stmt);

    // Apply alignment fix for specific rows/cells globally if missing
    $sheet->getColumnDimension('H')->setWidth(50);

    // Output file
    ob_end_clean(); // Kosongkan output buffer sebelum mengirim file
    
    $filename = "Export_History_Asset_" . date('Ymd_His') . ".xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Untuk IE 9
    header('Cache-Control: max-age=1');
    
    // Untuk IE melalui SSL
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate'); 
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die("Error Exception: " . $e->getMessage());
}
