<?php
/**
 * Export Data Tracking PO Online ke Excel (.xlsx)
 * Menggunakan library PhpSpreadsheet
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/koneksi.php';  // SQL Server GG
require_once $rootPath . '/koneksi3.php'; // PostgreSQL ERP (READ-ONLY)

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

date_default_timezone_set('Asia/Jakarta');

// Cek autentikasi login
if (empty($_SESSION['UserName'])) {
    http_response_code(403);
    die("Akses ditolak: Silakan login terlebih dahulu.");
}

$currentUserName = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? $currentUserName);

// Parameter Filter
$dateFrom           = trim($_GET['date_from'] ?? $_POST['date_from'] ?? '');
$dateTo             = trim($_GET['date_to'] ?? $_POST['date_to'] ?? '');
$filterStatus       = trim($_GET['filter_status'] ?? $_POST['filter_status'] ?? '');
$filterStatusDatang = trim($_GET['filter_status_datang'] ?? $_POST['filter_status_datang'] ?? '');
$filterStatusBayar  = trim($_GET['filter_status_bayar'] ?? $_POST['filter_status_bayar'] ?? '');
$filterMetodeBayar  = trim($_GET['filter_metode_bayar'] ?? $_POST['filter_metode_bayar'] ?? '');

// Fallback untuk backward compatibility parameter lama filter_status
if (empty($filterStatusDatang) && empty($filterStatusBayar) && !empty($filterStatus)) {
    if ($filterStatus === 'Approved' || $filterStatus === 'approved') {
        $filterStatusBayar = 'Approved';
    } elseif ($filterStatus === 'Sudah Dibayar' || $filterStatus === 'paid') {
        $filterStatusBayar = 'Sudah Dibayar';
    } elseif ($filterStatus === 'Sudah Datang' || $filterStatus === 'grn') {
        $filterStatusDatang = 'Sudah Datang';
    } elseif ($filterStatus === 'Belum Datang' || $filterStatus === 'not_arrived') {
        $filterStatusDatang = 'Belum Datang';
    }
}

$whereClause = "WHERE prpohd.poorgid = '22'";
$params = [];

if (!empty($dateFrom)) {
    $whereClause .= " AND CAST(prpohd.podate AS DATE) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $whereClause .= " AND CAST(prpohd.podate AS DATE) <= :date_to";
    $params[':date_to'] = $dateTo;
}

// Filter Status Kedatangan (GRN)
if ($filterStatusDatang === 'Sudah Datang' || $filterStatusDatang === 'grn') {
    $whereClause .= " AND prgrnhd.grnnmbr IS NOT NULL AND TRIM(prgrnhd.grnnmbr) != ''";
} elseif ($filterStatusDatang === 'Belum Datang' || $filterStatusDatang === 'not_arrived') {
    $whereClause .= " AND (prgrnhd.grnnmbr IS NULL OR TRIM(prgrnhd.grnnmbr) = '')";
}

// Filter Status Pembayaran (CP / Kas Bank)
if ($filterStatusBayar === 'Sudah Dibayar' || $filterStatusBayar === 'paid' || $filterStatusBayar === 'Approved' || $filterStatusBayar === 'approved') {
    $whereClause .= " AND pay.cp_status = 'V'";
} elseif ($filterStatusBayar === 'Belum Dibayar' || $filterStatusBayar === 'unpaid') {
    $whereClause .= " AND (pay.cp_status IS NULL OR pay.cp_status != 'V')";
}

$sql = "SELECT
    prpohd.ponmbr AS \"No PO\", 
    prpohd.podate AS \"Tgl PO\", 
    smvendor.vendorname AS \"Nama Supplier\", 
    prpohd.poupto AS \"Nama Toko Online\", 
    prpphd.ppnmbr AS \"No PR\", 
    prpphd.ppdate AS \"Tgl PR\", 
    prpodt.poprodcode AS \"Kode Produk\", 
    prpodt.poprodname AS \"Nama Produk\", 
    prpodt.poqty AS \"Qty PO\", 
    smuom.uomname AS \"Satuan\", 
    prpodt.poprice AS \"Harga Satuan\", 
    prpodt.poqtyprice AS \"Total Harga\", 
    prpodt.pototalamounthc AS \"Total Amount\", 
    prpohd.podesc AS \"Keterangan\", 
    prpohd.fgstatus AS \"Status PO\", 
    prgrnhd.grnnmbr AS \"No. GRN\", 
    prgrnhd.grndate AS \"Tgl GRN\", 
    prgrndt.grnqty AS \"Qty GRN\", 
    whwrhs.wrhsname AS \"Nama Gudang\", 
    prpohd.createdby AS \"Pembuat\", 
    pay.reqnmbr AS \"No. RFP\", 
    pay.reqdate AS \"Tgl RFP\", 
    pay.cpnmbr AS \"No CP\", 
    pay.cpdate AS \"Tgl CP\", 
    pay.cp_status AS \"Status CP\"
FROM
    prpohd
    LEFT JOIN prpodt ON prpohd.pohdid = prpodt.pohdid
    LEFT JOIN smvendor ON prpohd.povendorid = smvendor.vendorid
    LEFT JOIN prpphd ON prpodt.popphdid = prpphd.pphdid
    LEFT JOIN prgrndt ON prpodt.podtid = prgrndt.podtid
    LEFT JOIN prgrnhd ON prgrndt.grnhdid = prgrnhd.grnhdid
    LEFT JOIN smuom ON prpodt.pouomid = smuom.uomid
    LEFT JOIN whwrhs ON prpohd.powrhsid = whwrhs.wrhsid
    LEFT JOIN LATERAL (
        SELECT 
            STRING_AGG(DISTINCT sub.cpnmbr, ', ') AS cpnmbr,
            MAX(sub.cpdate) AS cpdate,
            CASE 
                WHEN BOOL_OR(sub.cp_status = 'V') THEN 'V'
                WHEN BOOL_OR(sub.cp_status = 'O') THEN 'O'
                ELSE MAX(sub.cp_status)
            END AS cp_status,
            STRING_AGG(DISTINCT sub.reqnmbr, ', ') AS reqnmbr,
            MAX(sub.reqdate) AS reqdate
        FROM (
            -- 1. Pembayaran AP Invoice setelah penerimaan barang (GRN -> APPosting -> CMCP)
            SELECT 
                cp.cpnmbr,
                cp.cpdate,
                cp.fgstatus AS cp_status,
                NULL::varchar AS reqnmbr,
                NULL::timestamp AS reqdate
            FROM prgrndt gd
            JOIN apposting ap ON ap.grnhdid = gd.grnhdid
            JOIN cmcpdt cpd ON cpd.apid = ap.apid
            JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
            WHERE gd.pohdid = prpohd.pohdid
              AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''

            UNION ALL

            -- 2. Pembayaran Uang Muka / Langsung PO (CMTTTAP -> CMCP)
            SELECT 
                COALESCE(cp.cpnmbr, t.cpnmbr) AS cpnmbr,
                COALESCE(cp.cpdate, t.cpdate) AS cpdate,
                cp.fgstatus AS cp_status,
                t.reqnmbr,
                t.reqdate
            FROM cmtttap t
            LEFT JOIN cmcphd cp ON cp.cphdid = t.cphdid
            WHERE t.pohdid = prpohd.pohdid

            UNION ALL

            -- 3. Pembayaran via Settlement Uang Muka Impor (PASettlement -> CMCP)
            SELECT 
                cp.cpnmbr,
                cp.cpdate,
                cp.fgstatus AS cp_status,
                req.reqnmbr,
                req.reqdate
            FROM pasettlementdt sdt
            JOIN pasettlementhd sh ON sh.settleid = sdt.settleid
            JOIN cmrequesthd req ON req.reqhdid = sh.rfphdid
            JOIN cmcpdt cpd ON cpd.cphdid = req.cphdid
            JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
            WHERE sdt.pohdid = prpohd.pohdid
              AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> ''
        ) sub
    ) pay ON TRUE
$whereClause
ORDER BY
    prpohd.ponmbr ASC,
    prpodt.podtid ASC";

$stmt = $conn3->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil pemetaan Metode Pembayaran & Pembatalan dari SQL Server GG
$metodeMap = [];
$batalMap  = [];
if ($conn) {
    $qMetode = @sqlsrv_query($conn, "SELECT no_po, metode_bayar FROM dbo.tracking_po_online_metode_bayar");
    if ($qMetode !== false) {
        while ($rm = sqlsrv_fetch_array($qMetode, SQLSRV_FETCH_ASSOC)) {
            $metodeMap[trim($rm['no_po'])] = trim($rm['metode_bayar'] ?? '');
        }
    }

    $qBatal = @sqlsrv_query($conn, "SELECT no_po, alasan_batal, batal_by, CONVERT(VARCHAR(19), batal_at, 120) AS batal_at FROM dbo.tracking_po_online_batal");
    if ($qBatal !== false) {
        while ($rb = sqlsrv_fetch_array($qBatal, SQLSRV_FETCH_ASSOC)) {
            $batalMap[trim($rb['no_po'])] = [
                'alasan_batal' => trim($rb['alasan_batal'] ?? ''),
                'batal_by'     => trim($rb['batal_by'] ?? ''),
                'batal_at'     => trim($rb['batal_at'] ?? '')
            ];
        }
    }
}

// Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Tracking PO Online');

// 1. JUDUL & INFORMASI HEADER
$sheet->setCellValue('A1', 'MONITORING TRACKING PO ONLINE');
$sheet->mergeCells('A1:AB1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E293B'));

$periodeInfo = 'Periode: ' . (!empty($dateFrom) ? date('d/m/Y', strtotime($dateFrom)) : 'Semua') . ' s/d ' . (!empty($dateTo) ? date('d/m/Y', strtotime($dateTo)) : 'Semua');
$statusDatangInfo = !empty($filterStatusDatang) ? $filterStatusDatang : 'Semua';
$statusBayarInfo  = !empty($filterStatusBayar) ? $filterStatusBayar : 'Semua';
$metodeInfo       = !empty($filterMetodeBayar) ? $filterMetodeBayar : 'Semua';
$statusInfo = ' | Status Kedatangan: ' . $statusDatangInfo . ' | Status Pembayaran: ' . $statusBayarInfo . ' | Metode: ' . $metodeInfo;
$sheet->setCellValue('A2', $periodeInfo . $statusInfo);
$sheet->mergeCells('A2:AB2');
$sheet->getStyle('A2')->getFont()->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF475569'));

$sheet->setCellValue('A3', 'Diekspor pada: ' . date('d/m/Y H:i:s') . ' WIB oleh ' . $currentNamaLengkap);
$sheet->mergeCells('A3:AB3');
$sheet->getStyle('A3')->getFont()->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));

// 2. HEADER KOLOM TABEL (Row 5)
$headers = [
    'No',
    'No PO',
    'Tgl PO',
    'Nama Supplier',
    'Nama Toko Online',
    'No PR',
    'Tgl PR',
    'Kode Produk',
    'Nama Produk',
    'Qty PO',
    'Satuan',
    'Harga Satuan',
    'Total Harga',
    'Total Amount',
    'Keterangan',
    'Metode Pembayaran',
    'Status Bayar',
    'Alasan Batal',
    'No. GRN',
    'Tgl GRN',
    'Qty GRN',
    'Nama Gudang',
    'Pembuat',
    'No. RFP',
    'Tgl RFP',
    'No CP',
    'Tgl CP',
    'Status CP'
];

$headerRow = 5;
foreach ($headers as $idx => $headerTitle) {
    $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
    $cellCoord = "{$colLetter}{$headerRow}";
    $sheet->setCellValue($cellCoord, $headerTitle);
}

// Styling Header Tabel
$headerStyle = [
    'font' => [
        'bold'  => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size'  => 10,
    ],
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1E40AF'], // Indigo-800
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['rgb' => '93C5FD'],
        ],
    ],
];
$sheet->getStyle("A{$headerRow}:AB{$headerRow}")->applyFromArray($headerStyle);
$sheet->getRowDimension($headerRow)->setRowHeight(28);

// 3. PENGISIAN DATA (Row 6 dst)
$dataRow = 6;
$no = 1;

foreach ($rows as $r) {
    $noPo = trim($r['No PO'] ?? '');
    $metodeBayar = $metodeMap[$noPo] ?? '';
    $isBatal = isset($batalMap[$noPo]);
    $batalInfo = $isBatal ? $batalMap[$noPo] : null;

    // Filter Status Bayar
    if ($filterStatusBayar === 'Batal') {
        if (!$isBatal) continue;
    } elseif ($filterStatusBayar === 'Sudah Dibayar' || $filterStatusBayar === 'paid' || $filterStatusBayar === 'Approved' || $filterStatusBayar === 'approved') {
        if ($isBatal) continue;
    } elseif ($filterStatusBayar === 'Belum Dibayar' || $filterStatusBayar === 'unpaid') {
        if ($isBatal) continue;
    }

    // Filter Metode Pembayaran jika ditentukan
    if ($filterMetodeBayar === 'Kosong' || $filterMetodeBayar === 'belum_diset') {
        if ($metodeBayar !== '') continue;
    } elseif (!empty($filterMetodeBayar)) {
        if ($metodeBayar !== $filterMetodeBayar) continue;
    }

    $tglPo = !empty($r['Tgl PO']) ? date('d/m/Y', strtotime($r['Tgl PO'])) : '-';
    $tglPr = !empty($r['Tgl PR']) ? date('d/m/Y', strtotime($r['Tgl PR'])) : '-';
    $tglGrn = !empty($r['Tgl GRN']) ? date('d/m/Y', strtotime($r['Tgl GRN'])) : '-';
    $tglRfp = !empty($r['Tgl RFP']) ? date('d/m/Y', strtotime($r['Tgl RFP'])) : '-';
    $tglCp = !empty($r['Tgl CP']) ? date('d/m/Y', strtotime($r['Tgl CP'])) : '-';

    $qtyPo = (float)($r['Qty PO'] ?? 0);
    $hargaSatuan = (float)($r['Harga Satuan'] ?? 0);
    $totalHarga = (float)($r['Total Harga'] ?? 0);
    $totalAmount = (float)($r['Total Amount'] ?? 0);
    $qtyGrn = !empty($r['Qty GRN']) ? (float)$r['Qty GRN'] : null;

    $statusCp = trim($r['Status CP'] ?? '');
    if ($isBatal) {
        $statusBayarLabel = 'Batal';
    } else {
        $statusBayarLabel = ($statusCp === 'V') ? 'Sudah Dibayar' : 'Belum Dibayar';
    }

    $rowValues = [
        1  => ['val' => $no++, 'type' => DataType::TYPE_NUMERIC],
        2  => ['val' => $noPo, 'type' => DataType::TYPE_STRING],
        3  => ['val' => $tglPo, 'type' => DataType::TYPE_STRING],
        4  => ['val' => trim($r['Nama Supplier'] ?? '-'), 'type' => DataType::TYPE_STRING],
        5  => ['val' => trim($r['Nama Toko Online'] ?? '-'), 'type' => DataType::TYPE_STRING],
        6  => ['val' => trim($r['No PR'] ?? '-'), 'type' => DataType::TYPE_STRING],
        7  => ['val' => $tglPr, 'type' => DataType::TYPE_STRING],
        8  => ['val' => trim($r['Kode Produk'] ?? '-'), 'type' => DataType::TYPE_STRING],
        9  => ['val' => trim($r['Nama Produk'] ?? '-'), 'type' => DataType::TYPE_STRING],
        10 => ['val' => $qtyPo, 'type' => DataType::TYPE_NUMERIC],
        11 => ['val' => trim($r['Satuan'] ?? '-'), 'type' => DataType::TYPE_STRING],
        12 => ['val' => $hargaSatuan, 'type' => DataType::TYPE_NUMERIC],
        13 => ['val' => $totalHarga, 'type' => DataType::TYPE_NUMERIC],
        14 => ['val' => $totalAmount, 'type' => DataType::TYPE_NUMERIC],
        15 => ['val' => trim($r['Keterangan'] ?? '-'), 'type' => DataType::TYPE_STRING],
        16 => ['val' => (!empty($metodeBayar) ? $metodeBayar : '-'), 'type' => DataType::TYPE_STRING],
        17 => ['val' => $statusBayarLabel, 'type' => DataType::TYPE_STRING],
        18 => ['val' => ($isBatal && $batalInfo ? $batalInfo['alasan_batal'] : '-'), 'type' => DataType::TYPE_STRING],
        19 => ['val' => trim($r['No. GRN'] ?? '-'), 'type' => DataType::TYPE_STRING],
        20 => ['val' => $tglGrn, 'type' => DataType::TYPE_STRING],
        21 => ['val' => ($qtyGrn !== null ? $qtyGrn : '-'), 'type' => ($qtyGrn !== null ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING)],
        22 => ['val' => trim($r['Nama Gudang'] ?? '-'), 'type' => DataType::TYPE_STRING],
        23 => ['val' => trim($r['Pembuat'] ?? '-'), 'type' => DataType::TYPE_STRING],
        24 => ['val' => trim($r['No. RFP'] ?? '-'), 'type' => DataType::TYPE_STRING],
        25 => ['val' => $tglRfp, 'type' => DataType::TYPE_STRING],
        26 => ['val' => trim($r['No CP'] ?? '-'), 'type' => DataType::TYPE_STRING],
        27 => ['val' => $tglCp, 'type' => DataType::TYPE_STRING],
        28 => ['val' => trim($r['Status CP'] ?? '-'), 'type' => DataType::TYPE_STRING],
    ];

    foreach ($rowValues as $colIdx => $cell) {
        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
        $coord = "{$colLetter}{$dataRow}";
        if ($cell['type'] === DataType::TYPE_STRING) {
            $sheet->setCellValueExplicit($coord, (string)$cell['val'], DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($coord, $cell['val']);
        }
    }

    $dataRow++;
}

$lastRow = $dataRow - 1;
if ($lastRow >= 6) {
    // Styling baris data
    $dataBorder = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'CBD5E1'],
            ],
        ],
        'font' => [
            'size' => 9.5,
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle("A6:AB{$lastRow}")->applyFromArray($dataBorder);

    // Number formatting
    $sheet->getStyle("L6:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("J6:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');
    $sheet->getStyle("U6:U{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');

    // Alignments
    $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F6:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("H6:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("K6:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("P6:T{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("W6:AB{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Zebra striping
    for ($rIdx = 6; $rIdx <= $lastRow; $rIdx++) {
        if ($rIdx % 2 === 0) {
            $sheet->getStyle("A{$rIdx}:AB{$rIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        }
    }
}

// Auto size semua kolom
for ($cIdx = 1; $cIdx <= count($headers); $cIdx++) {
    $colLetter = Coordinate::stringFromColumnIndex($cIdx);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Set freeze pane pada C6 (sehingga kolom A dan B tetap beku/sticky)
$sheet->freezePane('C6');

// Output ke Browser
$filename = 'Tracking_PO_Online_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // IE9
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
