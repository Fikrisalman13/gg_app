<?php
/**
 * Export Tracking PO ke Excel (.xlsx) menggunakan PhpSpreadsheet
 * Menyediakan 2 tipe ekspor:
 *  1. 'summary' : Ringkasan Tracking PO (seperti tampilan tabel utama)
 *  2. 'detail'  : Tracking PO digabung dengan rincian per-item barang PO dari ERP
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/koneksi.php';   // SQL Server GG
require_once $rootPath . '/koneksi3.php';  // PostgreSQL ERP (READ-ONLY)

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. AUTENTIKASI & IDENTITAS PENGGUNA
// =========================================================================
$currentUserName   = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? '');
$currentDeptName   = '';

if (empty($currentUserName)) {
    http_response_code(403);
    die("Akses ditolak: Silakan login terlebih dahulu.");
}

if (!empty($conn)) {
    $qUser = sqlsrv_query($conn, "SELECT u.GroupId, g.GroupName, e.nama_lengkap, d.dept
        FROM dbo.SMUserMs u
        LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
        LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
        LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
        LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
        WHERE u.UserName = ?", [$currentUserName]);
    if ($qUser && ($uRow = sqlsrv_fetch_array($qUser, SQLSRV_FETCH_ASSOC))) {
        if (!empty($uRow['nama_lengkap'])) $currentNamaLengkap = trim($uRow['nama_lengkap']);
        if (!empty($uRow['dept'])) $currentDeptName = trim($uRow['dept']);
    }
    if ($qUser) {
        sqlsrv_free_stmt($qUser);
    }
}
if (empty($currentNamaLengkap)) {
    $currentNamaLengkap = $currentUserName;
}

// =========================================================================
// 2. PARSE PARAMETER FILTER & SELEKSI
// =========================================================================
$exportType   = trim($_POST['export_type'] ?? $_GET['export_type'] ?? 'summary');
if ($exportType !== 'detail') {
    $exportType = 'summary';
}

$dateFrom     = trim($_POST['date_from'] ?? $_GET['date_from'] ?? '');
$dateTo       = trim($_POST['date_to'] ?? $_GET['date_to'] ?? '');
$filterStatus = trim($_POST['filter_status'] ?? $_GET['filter_status'] ?? '');
$selectedRaw  = trim($_POST['selected_ids'] ?? $_GET['selected_ids'] ?? '');

$selectedIds = [];
if (!empty($selectedRaw)) {
    $parts = explode(',', $selectedRaw);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && (ctype_digit($p) || is_numeric($p))) {
            $selectedIds[] = (int)$p;
        }
    }
}

// =========================================================================
// 3. QUERY DATA DARI SQL SERVER [GG]
// =========================================================================
$tglExpr = "ISNULL(d.tanggal_kirim, h.tanggal_kirim)";
$effDateExpr = "CASE WHEN d.status = 'Tunggu Tanggal Penyerahan' THEN CAST(h.created_at AS DATE) ELSE ISNULL(d.tanggal_kirim, h.tanggal_kirim) END";
$params = [];
$scopeLabel = "Semua data sesuai filter aktif";

if (!empty($selectedIds)) {
    // Mode Seleksi Baris Terpilih
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $where = "h.is_deleted = 0 AND d.id IN ($placeholders)";
    $params = $selectedIds;
    $scopeLabel = count($selectedIds) . " data PO terpilih (centang)";
} else {
    // Mode Filter Aktif
    $where = "h.is_deleted = 0";
    if (!empty($dateFrom) && !empty($dateTo)) {
        $where .= " AND {$effDateExpr} BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    } elseif (!empty($dateFrom)) {
        $where .= " AND {$effDateExpr} >= ?";
        $params[] = $dateFrom;
    } elseif (!empty($dateTo)) {
        $where .= " AND {$effDateExpr} <= ?";
        $params[] = $dateTo;
    }

    if ($filterStatus === 'Belum Kembali') {
        $where .= " AND d.status = 'Diserahkan' AND {$tglExpr} < CAST(GETDATE() AS DATE)";
    } elseif ($filterStatus === 'Diserahkan') {
        $where .= " AND d.status = 'Diserahkan' AND ({$tglExpr} >= CAST(GETDATE() AS DATE) OR {$tglExpr} IS NULL)";
    } elseif ($filterStatus === 'Tunggu Tanggal Penyerahan') {
        $where .= " AND d.status = 'Tunggu Tanggal Penyerahan'";
    } elseif (!empty($filterStatus)) {
        $where .= " AND d.status = ?";
        $params[] = $filterStatus;
    }
}

$sql = "SELECT d.id AS detail_id, d.header_id, d.ponmbr, d.pohdid, d.povendorname, 
               d.podate, d.status, d.is_signed, d.tanggal_kembali, 
               d.konfirmasi_oleh, d.konfirmasi_at, d.approved_by, d.approved_at, d.catatan,
               d.dikirim_ke, d.alamat_kirim,
               {$tglExpr} AS tanggal_kirim, h.diserahkan_oleh, h.created_at
        FROM dbo.tracking_po_detail d
        INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
        WHERE {$where}
        ORDER BY {$effDateExpr} DESC, d.id DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    die("Gagal mengeksekusi data tracking: " . print_r(sqlsrv_errors(), true));
}

$todayStr = date('Y-m-d');
$trackingRows = [];
$poNumbersMap = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dbStatus = trim($row['status'] ?? '');
    $displayStatus = $dbStatus;

    // Evaluasi status otomatis Belum Kembali
    if ($dbStatus === 'Diserahkan') {
        $tglKirimRaw = $row['tanggal_kirim'] instanceof DateTimeInterface
            ? $row['tanggal_kirim']->format('Y-m-d')
            : (string)($row['tanggal_kirim'] ?? '');
        if (!empty($tglKirimRaw) && $tglKirimRaw < $todayStr) {
            $displayStatus = 'Belum Kembali';
        }
    }

    $row['display_status'] = $displayStatus;
    $trackingRows[] = $row;

    $poNum = trim($row['ponmbr'] ?? '');
    if ($poNum !== '') {
        $poNumbersMap[$poNum] = $poNum;
    }
}
sqlsrv_free_stmt($stmt);

// =========================================================================
// 4. QUERY DETAIL ITEM DARI ERP POSTGRESQL (JIKA DETAIL MODE)
// =========================================================================
$poItemsMap = [];
if ($exportType === 'detail' && !empty($poNumbersMap) && isset($conn3) && $conn3) {
    $poChunkList = array_chunk(array_values($poNumbersMap), 400);
    foreach ($poChunkList as $chunk) {
        $inPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
        $sqlErp = "
            SELECT 
                TRIM(h.ponmbr) AS ponmbr,
                d.poseq,
                TRIM(d.poprodcode) AS poprodcode,
                TRIM(d.poprodname) AS poprodname,
                d.poqty,
                u.uomcode AS satuan,
                s.structname AS struct_name
            FROM prpodt d
            JOIN prpohd h ON d.pohdid = h.pohdid
            LEFT JOIN smuom u ON d.pouomid = u.uomid
            LEFT JOIN smproduct p ON TRIM(d.poprodcode) = TRIM(p.prodcode)
            LEFT JOIN smprodstruct s ON p.prodstructid = s.prodstructid
            WHERE TRIM(h.ponmbr) IN ($inPlaceholders)
            ORDER BY TRIM(h.ponmbr), d.poseq ASC
        ";
        try {
            $stmtErp = $conn3->prepare($sqlErp);
            $stmtErp->execute($chunk);
            while ($item = $stmtErp->fetch(PDO::FETCH_ASSOC)) {
                $pNum = trim($item['ponmbr'] ?? '');
                if (!isset($poItemsMap[$pNum])) {
                    $poItemsMap[$pNum] = [];
                }
                $poItemsMap[$pNum][] = $item;
            }
        } catch (Exception $e) {
            // Lanjutkan jika ERP query mengalami error tertentu agar export tidak macet total
        }
    }
}

// =========================================================================
// 5. MEMBUAT SPREADSHEET (PHPSPREADSHEET)
// =========================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setShowGridLines(true);

$sheetTitle = ($exportType === 'detail') ? 'Tracking PO Detail' : 'Tracking PO Summary';
$sheet->setTitle(substr($sheetTitle, 0, 31));

// Judul Utama Laporan
$mainTitle = ($exportType === 'detail') 
    ? 'LAPORAN TRACKING DOKUMEN PO - DETAIL ITEM BARANG' 
    : 'LAPORAN TRACKING DOKUMEN PURCHASE ORDER';

$sheet->setCellValue('A1', $mainTitle);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));

// Info Meta Laporan
$infoMeta = 'Tanggal Cetak: ' . date('d/m/Y H:i:s') . ' WIB | Dicetak Oleh: ' . $currentNamaLengkap . ($currentDeptName ? " ({$currentDeptName})" : '');
$sheet->setCellValue('A2', $infoMeta);
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));

// Info Filter
$filterDateText = ($dateFrom || $dateTo) 
    ? (($dateFrom ? date('d/m/Y', strtotime($dateFrom)) : 'Awal') . ' s/d ' . ($dateTo ? date('d/m/Y', strtotime($dateTo)) : 'Sekarang'))
    : 'Semua Tanggal';
$filterStatusText = !empty($filterStatus) ? $filterStatus : 'Semua Status';

$infoFilter = "Periode: {$filterDateText} | Status: {$filterStatusText} | Cakupan: {$scopeLabel} | Total PO: " . count($trackingRows);
$sheet->setCellValue('A3', $infoFilter);
$sheet->getStyle('A3')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF334155'));

// =========================================================================
// TABEL SUMMARY STATUS (BARIS 4 & 5)
// =========================================================================
$summaryCounts = [
    'total'             => count($trackingRows),
    'tunggu_penyerahan' => 0,
    'diserahkan'        => 0,
    'belum_kembali'     => 0,
    'sudah_kembali'     => 0,
    'approved'          => 0,
];

foreach ($trackingRows as $tRow) {
    $st = $tRow['display_status'] ?? '';
    if ($st === 'Tunggu Tanggal Penyerahan') {
        $summaryCounts['tunggu_penyerahan']++;
    } elseif ($st === 'Diserahkan') {
        $summaryCounts['diserahkan']++;
    } elseif ($st === 'Belum Kembali') {
        $summaryCounts['belum_kembali']++;
    } elseif ($st === 'Sudah Kembali') {
        $summaryCounts['sudah_kembali']++;
    } elseif ($st === 'Approved' || $st === 'Sudah TTD') {
        $summaryCounts['approved']++;
    }
}

$summaryCols = [
    'A' => ['label' => 'Jumlah PO',                 'val' => $summaryCounts['total']],
    'B' => ['label' => 'Tunggu Penyerahan',         'val' => $summaryCounts['tunggu_penyerahan']],
    'C' => ['label' => 'Diserahkan',                'val' => $summaryCounts['diserahkan']],
    'D' => ['label' => 'Belum Kembali',             'val' => $summaryCounts['belum_kembali']],
    'E' => ['label' => 'Sudah Kembali (Belum Ttd)', 'val' => $summaryCounts['sudah_kembali']],
    'F' => ['label' => 'Approved',                  'val' => $summaryCounts['approved']],
];

// Baris 4: Header Summary
foreach ($summaryCols as $sCol => $sDef) {
    $cellCoord = $sCol . '4';
    $sheet->setCellValue($cellCoord, $sDef['label']);
    $sheet->getStyle($cellCoord)->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E293B'));
    $sheet->getStyle($cellCoord)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle($cellCoord)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE2E8F0'); // Soft Slate Gray
}
$sheet->getRowDimension(4)->setRowHeight(26);

// Baris 5: Nilai Angka Summary
foreach ($summaryCols as $sCol => $sDef) {
    $cellCoord = $sCol . '5';
    $sheet->setCellValue($cellCoord, $sDef['val']);
    $sheet->getStyle($cellCoord)->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0F172A'));
    $sheet->getStyle($cellCoord)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle($cellCoord)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFFFFFF');
}
$sheet->getRowDimension(5)->setRowHeight(24);

// Border Garis Tabel Summary (A4:F5)
$sheet->getStyle('A4:F5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF94A3B8'));

// Baris 6: Baris Kosong Pemisah
$sheet->getRowDimension(6)->setRowHeight(14);

// Definisi Header Tabel
if ($exportType === 'detail') {
    $columns = [
        'A' => ['title' => 'No',                'align' => Alignment::HORIZONTAL_CENTER],
        'B' => ['title' => 'Tanggal Kirim',     'align' => Alignment::HORIZONTAL_CENTER],
        'C' => ['title' => 'No PO',             'align' => Alignment::HORIZONTAL_CENTER],
        'D' => ['title' => 'Vendor / Supplier', 'align' => Alignment::HORIZONTAL_LEFT],
        'E' => ['title' => 'Tanggal PO',        'align' => Alignment::HORIZONTAL_CENTER],
        'F' => ['title' => 'Status Tracking',   'align' => Alignment::HORIZONTAL_CENTER],
        'G' => ['title' => 'Seq Item',          'align' => Alignment::HORIZONTAL_CENTER],
        'H' => ['title' => 'Kode Barang',       'align' => Alignment::HORIZONTAL_LEFT],
        'I' => ['title' => 'Nama Barang',       'align' => Alignment::HORIZONTAL_LEFT],
        'J' => ['title' => 'Kategori / Struct', 'align' => Alignment::HORIZONTAL_LEFT],
        'K' => ['title' => 'Qty PO',            'align' => Alignment::HORIZONTAL_RIGHT],
        'L' => ['title' => 'Satuan',            'align' => Alignment::HORIZONTAL_CENTER],
        'M' => ['title' => 'Dikirim Ke',        'align' => Alignment::HORIZONTAL_LEFT],
        'N' => ['title' => 'Alamat Pengiriman', 'align' => Alignment::HORIZONTAL_LEFT],
        'O' => ['title' => 'Diserahkan Oleh',   'align' => Alignment::HORIZONTAL_LEFT],
        'P' => ['title' => 'Tanggal Kembali',   'align' => Alignment::HORIZONTAL_CENTER],
        'Q' => ['title' => 'Catatan',           'align' => Alignment::HORIZONTAL_LEFT],
    ];
} else {
    $columns = [
        'A' => ['title' => 'No',                 'align' => Alignment::HORIZONTAL_CENTER],
        'B' => ['title' => 'Tanggal Kirim',      'align' => Alignment::HORIZONTAL_CENTER],
        'C' => ['title' => 'No PO',              'align' => Alignment::HORIZONTAL_CENTER],
        'D' => ['title' => 'Vendor / Supplier',  'align' => Alignment::HORIZONTAL_LEFT],
        'E' => ['title' => 'Tanggal PO',         'align' => Alignment::HORIZONTAL_CENTER],
        'F' => ['title' => 'Diserahkan Oleh',    'align' => Alignment::HORIZONTAL_LEFT],
        'G' => ['title' => 'Status Tracking',    'align' => Alignment::HORIZONTAL_CENTER],
        'H' => ['title' => 'Tanggal Kembali',    'align' => Alignment::HORIZONTAL_CENTER],
        'I' => ['title' => 'Dikirim Ke',         'align' => Alignment::HORIZONTAL_LEFT],
        'J' => ['title' => 'Alamat Pengiriman',  'align' => Alignment::HORIZONTAL_LEFT],
        'K' => ['title' => 'Catatan',            'align' => Alignment::HORIZONTAL_LEFT],
        'L' => ['title' => 'Dikonfirmasi Oleh',  'align' => Alignment::HORIZONTAL_LEFT],
        'M' => ['title' => 'Waktu Konfirmasi',   'align' => Alignment::HORIZONTAL_CENTER],
        'N' => ['title' => 'Approved By (ERP)',  'align' => Alignment::HORIZONTAL_LEFT],
        'O' => ['title' => 'Waktu Approve (ERP)','align' => Alignment::HORIZONTAL_CENTER],
    ];
}

$headerRowNum = 7;
$lastColLetter = array_key_last($columns);

// Set Header Row
foreach ($columns as $col => $colDef) {
    $cellCoord = $col . $headerRowNum;
    $sheet->setCellValue($cellCoord, $colDef['title']);
    $sheet->getStyle($cellCoord)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
    $sheet->getStyle($cellCoord)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle($cellCoord)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF1E3A8A'); // Dark Navy Header
}
$sheet->getRowDimension($headerRowNum)->setRowHeight(28);

// Helper Format Tanggal
$formatDate = function ($dateVal, $includeTime = false) {
    if (empty($dateVal)) return '-';
    $format = $includeTime ? 'd/m/Y H:i' : 'd/m/Y';
    if ($dateVal instanceof DateTimeInterface) {
        return $dateVal->format($format);
    }
    $ts = strtotime((string)$dateVal);
    return ($ts !== false && $ts > 0) ? date($format, $ts) : '-';
};

// Mengisi Data Baris (Mulai Baris 8)
$currentRowNum = 8;
$no = 1;

if (empty($trackingRows)) {
    $sheet->setCellValue('A8', 'Tidak ada data tracking PO yang sesuai dengan kriteria filter.');
    $sheet->mergeCells("A8:{$lastColLetter}8");
    $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A8')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
    $sheet->getRowDimension(8)->setRowHeight(25);
    $currentRowNum = 9;
} else {
    foreach ($trackingRows as $tRow) {
        $ponmbr          = trim($tRow['ponmbr'] ?? '');
        $tglKirimStr     = $formatDate($tRow['tanggal_kirim']);
        $vendorStr       = trim($tRow['povendorname'] ?? '-');
        $podateStr       = $formatDate($tRow['podate']);
        $diserahkanStr   = trim($tRow['diserahkan_oleh'] ?? '-');
        $statusStr       = $tRow['display_status'];
        $tglKembaliStr   = $formatDate($tRow['tanggal_kembali']);
        $dikirimKeStr    = trim($tRow['dikirim_ke'] ?? '-');
        $alamatKirimStr  = trim($tRow['alamat_kirim'] ?? '-');
        $catatanStr      = trim($tRow['catatan'] ?? '-');
        $konfOlehStr     = trim($tRow['konfirmasi_oleh'] ?? '-');
        $konfAtStr       = $formatDate($tRow['konfirmasi_at'], true);
        $apprOlehStr     = trim($tRow['approved_by'] ?? '-');
        $apprAtStr       = $formatDate($tRow['approved_at'], true);

        if ($exportType === 'detail') {
            // Periksa apakah item barang tersedia di ERP untuk PO ini
            $items = $poItemsMap[$ponmbr] ?? [];
            if (empty($items)) {
                // Tulis satu baris dengan item kosong / '-'
                $fillColor = ($no % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';
                $rowCells = [
                    'A' => $no,
                    'B' => $tglKirimStr,
                    'C' => $ponmbr,
                    'D' => $vendorStr,
                    'E' => $podateStr,
                    'F' => $statusStr,
                    'G' => '-',
                    'H' => '-',
                    'I' => '(Tidak ada detail item di ERP)',
                    'J' => '-',
                    'K' => 0,
                    'L' => '-',
                    'M' => $dikirimKeStr,
                    'N' => $alamatKirimStr,
                    'O' => $diserahkanStr,
                    'P' => $tglKembaliStr,
                    'Q' => $catatanStr,
                ];

                foreach ($rowCells as $col => $val) {
                    $cellCoord = $col . $currentRowNum;
                    if ($col === 'C' || $col === 'H') {
                        $sheet->setCellValueExplicit($cellCoord, (string)$val, DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue($cellCoord, $val);
                    }
                    $sheet->getStyle($cellCoord)->getAlignment()->setHorizontal($columns[$col]['align'])->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);
                }
                $sheet->getRowDimension($currentRowNum)->setRowHeight(20);
                $currentRowNum++;
            } else {
                // Tulis satu baris per item barang
                foreach ($items as $idx => $it) {
                    $fillColor = ($no % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';
                    $qtyVal = (float)($it['poqty'] ?? 0);

                    $rowCells = [
                        'A' => ($idx === 0) ? $no : '', // Tampilkan no urut pada baris pertama PO
                        'B' => $tglKirimStr,
                        'C' => $ponmbr,
                        'D' => $vendorStr,
                        'E' => $podateStr,
                        'F' => $statusStr,
                        'G' => (int)($it['poseq'] ?? ($idx + 1)),
                        'H' => trim($it['poprodcode'] ?? '-'),
                        'I' => trim($it['poprodname'] ?? '-'),
                        'J' => trim($it['struct_name'] ?? '-'),
                        'K' => $qtyVal,
                        'L' => trim($it['satuan'] ?? '-'),
                        'M' => $dikirimKeStr,
                        'N' => $alamatKirimStr,
                        'O' => $diserahkanStr,
                        'P' => $tglKembaliStr,
                        'Q' => $catatanStr,
                    ];

                    foreach ($rowCells as $col => $val) {
                        $cellCoord = $col . $currentRowNum;
                        if ($col === 'C' || $col === 'H') {
                            $sheet->setCellValueExplicit($cellCoord, (string)$val, DataType::TYPE_STRING);
                        } elseif ($col === 'K') {
                            $sheet->setCellValue($cellCoord, $val);
                            $sheet->getStyle($cellCoord)->getNumberFormat()->setFormatCode('#,##0.00');
                        } else {
                            $sheet->setCellValue($cellCoord, $val);
                        }
                        $sheet->getStyle($cellCoord)->getAlignment()->setHorizontal($columns[$col]['align'])->setVertical(Alignment::VERTICAL_CENTER);
                        $sheet->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);
                    }
                    $sheet->getRowDimension($currentRowNum)->setRowHeight(20);
                    $currentRowNum++;
                }
            }
        } else {
            // Mode Ringkasan (Summary)
            $fillColor = ($no % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';
            $rowCells = [
                'A' => $no,
                'B' => $tglKirimStr,
                'C' => $ponmbr,
                'D' => $vendorStr,
                'E' => $podateStr,
                'F' => $diserahkanStr,
                'G' => $statusStr,
                'H' => $tglKembaliStr,
                'I' => $dikirimKeStr,
                'J' => $alamatKirimStr,
                'K' => $catatanStr,
                'L' => $konfOlehStr,
                'M' => $konfAtStr,
                'N' => $apprOlehStr,
                'O' => $apprAtStr,
            ];

            foreach ($rowCells as $col => $val) {
                $cellCoord = $col . $currentRowNum;
                if ($col === 'C') {
                    $sheet->setCellValueExplicit($cellCoord, (string)$val, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cellCoord, $val);
                }
                $sheet->getStyle($cellCoord)->getAlignment()->setHorizontal($columns[$col]['align'])->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);
            }
            $sheet->getRowDimension($currentRowNum)->setRowHeight(20);
            $currentRowNum++;
        }

        $no++;
    }
}

// Border Grid Seluruh Tabel
$lastDataRow = max(7, $currentRowNum - 1);
$tableRange = "A{$headerRowNum}:{$lastColLetter}{$lastDataRow}";
$sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFCBD5E1'));

// Auto-size lebar kolom untuk kenyamanan membaca
foreach ($columns as $col => $colDef) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set Judul Kolom Merge Header
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->mergeCells("A2:{$lastColLetter}2");
$sheet->mergeCells("A3:{$lastColLetter}3");

// =========================================================================
// 6. STREAMING FILE EXCEL KE BROWSER
// =========================================================================
$filePrefix = ($exportType === 'detail') ? 'Tracking_PO_Detail_Barang' : 'Tracking_PO_Ringkasan';
$filename = $filePrefix . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Access-Control-Expose-Headers: Content-Disposition');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
