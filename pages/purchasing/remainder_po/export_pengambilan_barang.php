<?php
/**
 * Export Excel Pengambilan Barang ke Supplier
 * Menggunakan library PhpOffice\PhpSpreadsheet
 * Format dan tata letak disesuaikan dengan template pengambilan barang.xlsx
 * Data yang diekspor adalah data item PO sesuai filter aktif pada halaman remainder_po.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/koneksi3.php'; // PostgreSQL ERP (READ-ONLY)

// Sertakan koneksi SQL Server (tracking) jika tersedia
if (function_exists('sqlsrv_connect') && file_exists($rootPath . '/koneksi.php')) {
    @include_once $rootPath . '/koneksi.php';
}

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
    die("Akses ditolak: Silakan login terlebih dahulu ke sistem.");
}

if (!isset($conn3) || !$conn3) {
    die("Koneksi ke basis data ERP (koneksi3.php) tidak tersedia.");
}

// Format kuantitas (float / int)
if (!function_exists('fmtQty')) {
    function fmtQty($val) {
        $floatVal = (float)$val;
        return (floor($floatVal) == $floatVal) ? (int)$floatVal : round($floatVal, 4);
    }
}

// ─── Parameter Filter (Sama persis dengan remainder_po.php) ──────────────────
$todayStr = date('Y-m-d');
$startDate = isset($_GET['tgl_mulai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai'])
    ? $_GET['tgl_mulai']
    : (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']) ? $_GET['tanggal'] : $todayStr);

$endDate = isset($_GET['tgl_selesai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_selesai'])
    ? $_GET['tgl_selesai']
    : (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']) ? $_GET['tanggal'] : $todayStr);

if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

$statusFilter        = isset($_GET['status']) ? trim($_GET['status']) : '';
$picFilter           = isset($_GET['pic']) ? trim($_GET['pic']) : '';
$paymentStatusFilter = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$dikirimKeFilter     = isset($_GET['dikirim_ke']) ? trim($_GET['dikirim_ke']) : '';
$includeRange        = isset($_GET['range']) && $_GET['range'] === '1' ? 1 : 0;

// Evaluasi status delivery PO berdasarkan fgstatus prpohd
if (!function_exists('evaluatePoDeliveryStatusExport')) {
    function evaluatePoDeliveryStatusExport(&$group, $targetDate = '')
    {
        $poStatus = strtoupper(trim($group['po_status'] ?? ''));
        $isOverdue = false;
        $maxDelivery = !empty($group['max_d2']) ? $group['max_d2'] : $group['min_d1'];
        if (!empty($targetDate) && !empty($maxDelivery)) {
            if ($maxDelivery < $targetDate && $poStatus === 'V') {
                $isOverdue = true;
            }
        }
        $group['is_overdue'] = $isOverdue;

        if ($poStatus === 'X') {
            $group['status_label'] = 'Selesai';
        } elseif ($poStatus === 'U') {
            $group['status_label'] = 'Parsial';
        } elseif ($poStatus === 'V') {
            $group['status_label'] = 'Belum Datang';
        } else {
            $group['status_label'] = 'Lainnya (' . $poStatus . ')';
        }
    }
}

// ─── Query Data PO dari PostgreSQL ERP (conn3) ──────────────────────────────
$poGroups = [];

try {
    if ($statusFilter === 'Overdue') {
        $sqlData = "
            SELECT 
                h.pohdid,
                h.ponmbr,
                h.podate,
                h.povendorcode,
                h.povendorname,
                h.poinitiatorid,
                COALESCE(u.username, e.empname) AS pic_pembuat,
                h.fgstatus AS po_status,
                d.podtid,
                d.poseq,
                d.poprodcode,
                d.poprodname,
                d.poqty,
                COALESCE(d.grnqty, 0) AS grnqty,
                (d.poqty - COALESCE(d.grnqty, 0)) AS sisa_qty,
                d.podlvrdate1,
                d.podlvrdate2,
                d.fgstatus AS item_status,
                COALESCE(um.uomcode, um.uomname, ump.uomcode, ump.uomname, '-') AS satuan,
                COALESCE(v.vendoraddr, '') AS vendor_addr
            FROM prpodt d
            JOIN prpohd h ON d.pohdid = h.pohdid
            LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
            LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
            LEFT JOIN smuom um ON d.pouomid = um.uomid
            LEFT JOIN smproduct p ON d.poprodid = p.prodid
            LEFT JOIN smuom ump ON p.uomid = ump.uomid
            LEFT JOIN smvendor v ON h.povendorcode = v.vendorcode
            WHERE COALESCE(DATE(d.podlvrdate2), DATE(d.podlvrdate1)) < :target_date
              AND TRIM(h.fgstatus) = 'V'
            ORDER BY COALESCE(d.podlvrdate2, d.podlvrdate1) DESC, h.ponmbr ASC, d.poseq ASC
            LIMIT 500
        ";
        $stmt = $conn3->prepare($sqlData);
        $stmt->execute([':target_date' => $todayStr]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rangeCondition = ($includeRange === 1)
            ? "((DATE(d.podlvrdate1) BETWEEN :start_date AND :end_date OR DATE(d.podlvrdate2) BETWEEN :start_date AND :end_date) OR (DATE(d.podlvrdate1) <= :end_date AND DATE(d.podlvrdate2) >= :start_date))"
            : "(DATE(d.podlvrdate1) BETWEEN :start_date AND :end_date OR DATE(d.podlvrdate2) BETWEEN :start_date AND :end_date)";

        $sqlData = "
            SELECT 
                h.pohdid,
                h.ponmbr,
                h.podate,
                h.povendorcode,
                h.povendorname,
                h.poinitiatorid,
                COALESCE(u.username, e.empname) AS pic_pembuat,
                h.fgstatus AS po_status,
                d.podtid,
                d.poseq,
                d.poprodcode,
                d.poprodname,
                d.poqty,
                COALESCE(d.grnqty, 0) AS grnqty,
                (d.poqty - COALESCE(d.grnqty, 0)) AS sisa_qty,
                d.podlvrdate1,
                d.podlvrdate2,
                d.fgstatus AS item_status,
                COALESCE(um.uomcode, um.uomname, ump.uomcode, ump.uomname, '-') AS satuan,
                COALESCE(v.vendoraddr, '') AS vendor_addr
            FROM prpodt d
            JOIN prpohd h ON d.pohdid = h.pohdid
            LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
            LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
            LEFT JOIN smuom um ON d.pouomid = um.uomid
            LEFT JOIN smproduct p ON d.poprodid = p.prodid
            LEFT JOIN smuom ump ON p.uomid = ump.uomid
            LEFT JOIN smvendor v ON h.povendorcode = v.vendorcode
            WHERE $rangeCondition
              AND TRIM(h.fgstatus) IN ('V', 'U', 'X')
            ORDER BY h.ponmbr ASC, d.poseq ASC
        ";
        $stmt = $conn3->prepare($sqlData);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Kelompokkan data per No PO
    foreach ($rows as $row) {
        $pohdid = (int)$row['pohdid'];
        if (!isset($poGroups[$pohdid])) {
            $picPembuat = !empty($row['pic_pembuat']) ? trim($row['pic_pembuat']) : '-';
            $poGroups[$pohdid] = [
                'pohdid'       => $pohdid,
                'ponmbr'       => trim($row['ponmbr'] ?? '-'),
                'pic_pembuat'  => $picPembuat,
                'podate'       => $row['podate'] ? date('d/m/Y', strtotime($row['podate'])) : '-',
                'povendorcode' => trim($row['povendorcode'] ?? ''),
                'povendorname' => trim($row['povendorname'] ?? '-'),
                'vendor_addr'  => trim($row['vendor_addr'] ?? ''),
                'po_status'    => trim($row['po_status'] ?? ''),
                'min_d1'       => null,
                'max_d2'       => null,
                'items'        => [],
            ];
        }

        $d1 = !empty($row['podlvrdate1']) ? date('Y-m-d', strtotime($row['podlvrdate1'])) : null;
        $d2 = !empty($row['podlvrdate2']) ? date('Y-m-d', strtotime($row['podlvrdate2'])) : null;
        if ($d1 && (!$poGroups[$pohdid]['min_d1'] || $d1 < $poGroups[$pohdid]['min_d1'])) {
            $poGroups[$pohdid]['min_d1'] = $d1;
        }
        if ($d2 && (!$poGroups[$pohdid]['max_d2'] || $d2 > $poGroups[$pohdid]['max_d2'])) {
            $poGroups[$pohdid]['max_d2'] = $d2;
        }

        $poGroups[$pohdid]['items'][] = [
            'poseq'       => (int)$row['poseq'],
            'poprodcode'  => trim($row['poprodcode'] ?? '-'),
            'poprodname'  => trim($row['poprodname'] ?? '-'),
            'poqty'       => (float)$row['poqty'],
            'grnqty'      => (float)$row['grnqty'],
            'sisa_qty'    => (float)$row['sisa_qty'],
            'satuan'      => trim($row['satuan'] ?? '-'),
            'item_status' => trim($row['item_status'] ?? ''),
        ];
    }

    // Evaluasi status delivery
    foreach ($poGroups as $pohdid => &$group) {
        evaluatePoDeliveryStatusExport($group, $todayStr);
    }
    unset($group);

    // Filter berdasarkan Status Delivery jika dipilih (Belum Datang, Parsial, Selesai)
    if (!empty($statusFilter) && $statusFilter !== 'Overdue') {
        $poGroups = array_filter($poGroups, function ($g) use ($statusFilter) {
            if ($statusFilter === 'Parsial') {
                return strpos($g['status_label'], 'Parsial') !== false;
            }
            return $g['status_label'] === $statusFilter;
        });
    }

    // Filter berdasarkan PIC jika dipilih
    if ($picFilter !== '') {
        $poGroups = array_filter($poGroups, function ($g) use ($picFilter) {
            return ($g['pic_pembuat'] ?? '') === $picFilter;
        });
    }

    // Ambil data status pembayaran (Cash Payment / CPNmbr dengan fgstatus = 'V')
    $allPoHdids = array_keys($poGroups);
    $paymentLookup = [];
    if (!empty($allPoHdids)) {
        $inClause = implode(',', array_map('intval', $allPoHdids));
        $sqlPayment = "
            SELECT 
                u.pohdid,
                STRING_AGG(DISTINCT u.cpnmbr || '~' || COALESCE(TO_CHAR(u.cpdate, 'YYYY-MM-DD'), ''), '|') AS cp_details,
                STRING_AGG(DISTINCT u.cpnmbr, ', ') AS cp_numbers
            FROM (
                SELECT 
                    gd.pohdid,
                    cp.cpnmbr,
                    cp.cpdate
                FROM prgrndt gd
                JOIN apposting ap ON ap.grnhdid = gd.grnhdid
                JOIN cmcpdt cpd ON cpd.apid = ap.apid
                JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                WHERE gd.pohdid IN ($inClause)
                  AND cp.cpnmbr IS NOT NULL
                  AND TRIM(cp.cpnmbr) <> ''
                  AND TRIM(cp.fgstatus) = 'V'

                UNION

                SELECT 
                    t.pohdid,
                    cp.cpnmbr,
                    cp.cpdate
                FROM cmtttap t
                JOIN cmcphd cp ON cp.cphdid = t.cphdid
                WHERE t.pohdid IN ($inClause)
                  AND cp.cpnmbr IS NOT NULL
                  AND TRIM(cp.cpnmbr) <> ''
                  AND TRIM(cp.fgstatus) = 'V'

                UNION

                SELECT 
                    sdt.pohdid,
                    cp.cpnmbr,
                    cp.cpdate
                FROM pasettlementdt sdt
                JOIN pasettlementhd sh ON sh.settleid = sdt.settleid
                JOIN cmrequesthd req ON req.reqhdid = sh.rfphdid
                JOIN cmcpdt cpd ON cpd.cphdid = req.cphdid
                JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
                WHERE sdt.pohdid IN ($inClause)
                  AND cp.cpnmbr IS NOT NULL
                  AND TRIM(cp.cpnmbr) <> ''
                  AND TRIM(cp.fgstatus) = 'V'
            ) u
            GROUP BY u.pohdid
        ";
        $stmtPayment = $conn3->query($sqlPayment);
        if ($stmtPayment) {
            while ($rPay = $stmtPayment->fetch(PDO::FETCH_ASSOC)) {
                $pId = (int)$rPay['pohdid'];
                $cpNums = trim($rPay['cp_numbers'] ?? '');
                $paymentLookup[$pId] = [
                    'is_paid'    => !empty($cpNums),
                    'cp_numbers' => $cpNums
                ];
            }
        }
    }

    foreach ($poGroups as $pohdid => &$group) {
        $pay = $paymentLookup[$pohdid] ?? null;
        $group['is_paid'] = $pay ? $pay['is_paid'] : false;
        $group['cp_numbers'] = $pay ? $pay['cp_numbers'] : '-';
    }
    unset($group);

    // Filter berdasarkan Status Bayar jika dipilih
    if ($paymentStatusFilter === 'paid') {
        $poGroups = array_filter($poGroups, function ($g) {
            return !empty($g['is_paid']);
        });
    } elseif ($paymentStatusFilter === 'unpaid') {
        $poGroups = array_filter($poGroups, function ($g) {
            return empty($g['is_paid']);
        });
    }

    // Ambil data tujuan pengiriman & alamat kirim dari SQL Server dbo.tracking_po_detail
    $allPoNumbers = [];
    foreach ($poGroups as $g) {
        $pNum = trim($g['ponmbr'] ?? '');
        if ($pNum !== '' && $pNum !== '-') {
            $allPoNumbers[$pNum] = true;
        }
    }

    $trackingLookup = [];
    if (!empty($allPoNumbers) && isset($conn) && $conn) {
        $poListKeys = array_keys($allPoNumbers);
        foreach (array_chunk($poListKeys, 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sqlTrack = "
                SELECT d.ponmbr, d.dikirim_ke, d.alamat_kirim, d.status
                FROM dbo.tracking_po_detail d
                INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
                WHERE h.is_deleted = 0 AND d.ponmbr IN ($placeholders)
                ORDER BY d.id ASC
            ";
            $stmtTrack = sqlsrv_query($conn, $sqlTrack, $chunk);
            if ($stmtTrack) {
                while ($rTrack = sqlsrv_fetch_array($stmtTrack, SQLSRV_FETCH_ASSOC)) {
                    $pNum = trim($rTrack['ponmbr']);
                    $trackingLookup[$pNum] = [
                        'dikirim_ke'   => !empty($rTrack['dikirim_ke']) ? trim($rTrack['dikirim_ke']) : null,
                        'alamat_kirim' => !empty($rTrack['alamat_kirim']) ? trim($rTrack['alamat_kirim']) : null,
                    ];
                }
            }
        }
    }

    foreach ($poGroups as $pohdid => &$group) {
        $pNum = trim($group['ponmbr'] ?? '');
        $track = $trackingLookup[$pNum] ?? null;
        $group['dikirim_ke'] = $track['dikirim_ke'] ?? null;
        $group['alamat_kirim'] = $track['alamat_kirim'] ?? null;
    }
    unset($group);

    // Filter berdasarkan Tujuan Pengiriman (Dikirim Ke) jika dipilih
    if ($dikirimKeFilter !== '') {
        $poGroups = array_filter($poGroups, function ($g) use ($dikirimKeFilter) {
            if ($dikirimKeFilter === 'belum_ditracking') {
                return empty($g['dikirim_ke']);
            }
            return ($g['dikirim_ke'] ?? '') === $dikirimKeFilter;
        });
    }

} catch (Exception $e) {
    die("Terjadi kesalahan saat mengambil data: " . htmlspecialchars($e->getMessage()));
}

// ─── Agregasi & Konsolidasi Item untuk Export Excel ──────────────────────────
// Sesuai kesepakatan:
// 1. Kuantitas adalah Sisa QTY (poqty - grnqty).
// 2. Gabungkan QTY jika dalam satu PO terdapat produk yang sama.
// 3. Alamat Supplier mengutamakan smvendor.vendoraddr, fallback ke alamat_kirim tracking.
$exportRows = [];

foreach ($poGroups as $group) {
    $supplierName = !empty($group['povendorname']) ? trim($group['povendorname']) : '-';
    $supplierAddress = !empty($group['vendor_addr']) ? $group['vendor_addr'] : (!empty($group['alamat_kirim']) ? $group['alamat_kirim'] : '-');

    $poConsolidatedItems = [];
    foreach ($group['items'] as $item) {
        $prodKey = ($item['poprodcode'] !== '' && $item['poprodcode'] !== '-')
            ? $item['poprodcode']
            : $item['poprodname'];

        $qtyTarget = ($statusFilter === 'Selesai') ? (float)$item['poqty'] : (float)$item['sisa_qty'];
        if ($qtyTarget <= 0 && $statusFilter !== 'Selesai') {
            continue; // Lewati item yang sudah diterima 100%
        }

        if (!isset($poConsolidatedItems[$prodKey])) {
            $poConsolidatedItems[$prodKey] = [
                'supplier'    => $supplierName,
                'nama_barang' => $item['poprodname'],
                'qty'         => 0.0,
                'satuan'      => $item['satuan'],
                'alamat'      => $supplierAddress,
            ];
        }
        $poConsolidatedItems[$prodKey]['qty'] += $qtyTarget;
    }

    foreach ($poConsolidatedItems as $cItem) {
        $exportRows[] = $cItem;
    }
}

// ─── Konstruksi File Excel Menggunakan PhpSpreadsheet ────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pengambilan Barang');

// Aktifkan garis kisi tabel
$sheet->setShowGridLines(true);

// 1. Judul di Baris 1: PENGAMBILAN BARANG KE SUPPLIER (Merged A1:F1)
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'PENGAMBILAN BARANG KE SUPPLIER');
$sheet->getRowDimension(1)->setRowHeight(24);
$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'name'  => 'Calibri',
        'bold'  => true,
        'size'  => 11,
        'color' => ['argb' => 'FF000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
]);

// Baris 2 kosong
$sheet->getRowDimension(2)->setRowHeight(12);

// 2. Baris Header (Baris 3)
$headers = [
    'A3' => 'NO',
    'B3' => 'SUPPLIER',
    'C3' => 'NAMA BARANG',
    'D3' => 'QTY',
    'E3' => 'SATUAN / UOM NAME',
    'F3' => 'ALAMAT SUPLLIER ',
];

foreach ($headers as $coord => $title) {
    $sheet->setCellValue($coord, $title);
}
$sheet->getRowDimension(3)->setRowHeight(22);

$headerStyle = [
    'font' => [
        'name'  => 'Calibri',
        'bold'  => true,
        'size'  => 10,
        'color' => ['argb' => 'FF000000'],
    ],
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9D9D9'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
];
$sheet->getStyle('A3:F3')->applyFromArray($headerStyle);

// 3. Mengisi Baris Data (Mulai Baris 4)
$currRow = 4;
$no = 1;

foreach ($exportRows as $row) {
    $sheet->setCellValueExplicit("A{$currRow}", (string)$no, DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit("B{$currRow}", (string)$row['supplier'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C{$currRow}", (string)$row['nama_barang'], DataType::TYPE_STRING);
    $sheet->setCellValue("D{$currRow}", fmtQty($row['qty']));
    $sheet->setCellValueExplicit("E{$currRow}", (string)$row['satuan'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("F{$currRow}", (string)$row['alamat'], DataType::TYPE_STRING);

    $currRow++;
    $no++;
}

$lastRow = max(4, $currRow - 1);

// Styling baris data jika ada data
if (!empty($exportRows)) {
    // Border seluruh tabel data
    $sheet->getStyle("A4:F{$lastRow}")->applyFromArray([
        'font' => [
            'size' => 10,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => '000000'],
            ],
        ],
    ]);

    // Alignment spesifik per kolom
    $sheet->getStyle("A4:A{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getStyle("B4:B{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getStyle("C4:C{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getStyle("D4:D{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D4:D{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');

    $sheet->getStyle("E4:E{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getStyle("F4:F{$lastRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
} else {
    // Jika tidak ada data yang sesuai filter
    $sheet->mergeCells("A4:F4");
    $sheet->setCellValue("A4", "Tidak ada data PO yang sesuai dengan filter yang aktif.");
    $sheet->getStyle("A4:F4")->applyFromArray([
        'font' => ['italic' => true, 'color' => ['rgb' => '718096']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => '000000'],
            ],
        ],
    ]);
}

// 4. Lebar Kolom (Sesuai dengan template Excel)
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->getColumnDimension('C')->setWidth(50);
$sheet->getColumnDimension('D')->setWidth(8);
$sheet->getColumnDimension('E')->setWidth(26);
$sheet->getColumnDimension('F')->setWidth(45);

// 5. Output ke Browser untuk di-download
$filename = 'Pengambilan_Barang_Supplier_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
