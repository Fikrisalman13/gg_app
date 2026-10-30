<?php
/**
 * Halaman Pengingat Jadwal Pengiriman Purchase Order (Reminder PO Delivery)
 * Mengambil data secara READ-ONLY dari basis data PostgreSQL ERP_Crystal_SUM via koneksi3.php
 * Desain dan antarmuka disamakan dengan /form_purchasing/list_form.php
 * Dilengkapi 4 Card Filter interaktif di bagian atas:
 * 1. Belum Datang
 * 2. Parsial (Diterima Sebagian)
 * 3. Selesai
 * 4. PO Overdue (Terlambat)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Pengaturan parameter filter tanggal (rentang), status, & PIC
$todayStr = date('Y-m-d');
$startDate = isset($_GET['tgl_mulai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai'])
    ? $_GET['tgl_mulai']
    : (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']) ? $_GET['tanggal'] : $todayStr);

$endDate = isset($_GET['tgl_selesai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_selesai'])
    ? $_GET['tgl_selesai']
    : (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']) ? $_GET['tanggal'] : $todayStr);

// Pastikan urutan tanggal valid jika tanggal mulai lebih besar dari tanggal selesai
if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$picFilter = isset($_GET['pic']) ? trim($_GET['pic']) : '';
$paymentStatusFilter = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$dikirimKeFilter = isset($_GET['dikirim_ke']) ? trim($_GET['dikirim_ke']) : '';
$includeRange = isset($_GET['range']) && $_GET['range'] === '1' ? 1 : 0;

$errorMessage = null;
$picOptions = [];
$countBelumDatang = 0;
$countParsial = 0;
$countSelesai = 0;
$countOverdue = 0;
$poGroups = [];

// Format angka tanpa trailing zero
if (!function_exists('formatQty')) {
    function formatQty($val)
    {
        if ($val === null || $val === '') return '0';
        $floatVal = (float) $val;
        if (floor($floatVal) == $floatVal) {
            return number_format($floatVal, 0, ',', '.');
        }
        return rtrim(rtrim(number_format($floatVal, 4, ',', '.'), '0'), ',');
    }
}

// Evaluasi status delivery PO berdasarkan fgstatus prpohd
// Belum Datang : fgstatus V
// Parsial      : fgstatus U
// Selesai      : fgstatus X
// PO Overdue   : fgstatus V, tapi melewati rentang delivery-nya
if (!function_exists('evaluatePoDeliveryStatus')) {
    function evaluatePoDeliveryStatus(&$group, $targetDate = '')
    {
        $poStatus = strtoupper(trim($group['po_status'] ?? ''));

        // Cek apakah pengiriman sudah melewati batas waktu (Overdue)
        // Syarat: PO berstatus V dan batas akhir delivery < targetDate
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
            $group['status_badge'] = 'badge-success';
        } elseif ($poStatus === 'U') {
            $group['status_label'] = 'Parsial (Diterima Sebagian)';
            $group['status_badge'] = 'badge-info';
        } elseif ($poStatus === 'V') {
            $group['status_label'] = 'Belum Datang';
            $group['status_badge'] = 'badge-warning';
        } else {
            $group['status_label'] = 'Lainnya (' . $poStatus . ')';
            $group['status_badge'] = 'badge-secondary';
        }
    }
}

if (!isset($conn3) || !$conn3) {
    $errorMessage = "Koneksi ke database ERP (koneksi3.php) tidak tersedia. Silakan hubungi administrator sistem.";
} else {
    try {
        // 1. Hitung jumlah PO Overdue untuk card Overdue (ikut terfilter jika PIC dipilih)
        $overduePicCondition = "";
        $overdueParams = [':cur_date' => $todayStr];
        if ($picFilter !== '') {
            $overduePicCondition = " AND u.username = :pic_filter ";
            $overdueParams[':pic_filter'] = $picFilter;
        }

        $sqlOverdueCount = "
            SELECT COUNT(DISTINCT h.ponmbr) AS cnt
            FROM prpodt d
            JOIN prpohd h ON d.pohdid = h.pohdid
            LEFT JOIN msuser u ON h.poinitiatorid = u.initiatorid
            WHERE COALESCE(DATE(d.podlvrdate2), DATE(d.podlvrdate1)) < :cur_date
              AND TRIM(h.fgstatus) = 'V'
              $overduePicCondition
        ";
        $stmtOverdueCount = $conn3->prepare($sqlOverdueCount);
        $stmtOverdueCount->execute($overdueParams);
        $countOverdue = (int) ($stmtOverdueCount->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        // 2. Ambil data PO rentang tanggal terpilih untuk menghitung angka card (Belum Datang, Parsial, Selesai)
        $rangeCondition = ($includeRange === 1)
            ? "((DATE(d.podlvrdate1) BETWEEN :start_date AND :end_date OR DATE(d.podlvrdate2) BETWEEN :start_date AND :end_date) OR (DATE(d.podlvrdate1) <= :end_date AND DATE(d.podlvrdate2) >= :start_date))"
            : "(DATE(d.podlvrdate1) BETWEEN :start_date AND :end_date OR DATE(d.podlvrdate2) BETWEEN :start_date AND :end_date)";

        $sqlNormal = "
            SELECT 
                h.pohdid,
                h.ponmbr,
                h.podate,
                h.povendorcode,
                h.povendorname,
                h.poinitiatorid,
                u.username AS pic_pembuat,
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
                s.structname AS struct_name,
                (
                    SELECT STRING_AGG(gh.grnnmbr || ':' || sub.total_grnqty, '|' ORDER BY sub.min_date ASC, gh.grnnmbr ASC)
                    FROM (
                        SELECT gd.grnhdid, SUM(gd.grnqty) as total_grnqty, MIN(gh2.grndate) as min_date
                        FROM prgrndt gd
                        JOIN prgrnhd gh2 ON gd.grnhdid = gh2.grnhdid
                        WHERE gd.podtid = d.podtid
                        GROUP BY gd.grnhdid
                    ) sub
                    JOIN prgrnhd gh ON sub.grnhdid = gh.grnhdid
                ) AS grn_details
            FROM prpodt d
            JOIN prpohd h ON d.pohdid = h.pohdid
            LEFT JOIN msuser u ON h.poinitiatorid = u.initiatorid
            LEFT JOIN smproduct p ON TRIM(d.poprodcode) = TRIM(p.prodcode)
            LEFT JOIN smprodstruct s ON p.prodstructid = s.prodstructid
            WHERE $rangeCondition
              AND TRIM(h.fgstatus) IN ('V', 'U', 'X')
            ORDER BY h.ponmbr ASC, d.poseq ASC
        ";
        $stmtNormal = $conn3->prepare($sqlNormal);
        $stmtNormal->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
        $normalRows = $stmtNormal->fetchAll(PDO::FETCH_ASSOC);

        // Kelompokkan data normal per No PO
        $normalPoGroups = [];
        foreach ($normalRows as $row) {
            $pohdid = (int) $row['pohdid'];
            if (!isset($normalPoGroups[$pohdid])) {
                $picPembuat = !empty($row['pic_pembuat']) ? trim($row['pic_pembuat']) : '-';

                $normalPoGroups[$pohdid] = [
                    'pohdid'       => $pohdid,
                    'ponmbr'       => trim($row['ponmbr'] ?? '-'),
                    'pic_pembuat'  => $picPembuat,
                    'podate'       => $row['podate'] ? date('d/m/Y', strtotime($row['podate'])) : '-',
                    'povendorcode' => trim($row['povendorcode'] ?? ''),
                    'povendorname' => trim($row['povendorname'] ?? '-'),
                    'po_status' => trim($row['po_status'] ?? ''),
                    'min_d1' => null,
                    'max_d2' => null,
                    'items' => [],
                    'po_grns' => [],
                    'has_open' => false,
                    'has_partial' => false,
                    'has_closed' => false,
                    'all_closed' => true,
                ];
            }

            $itemStatus = trim($row['item_status'] ?? '');
            if ($itemStatus === 'O') {
                $normalPoGroups[$pohdid]['has_open'] = true;
                $normalPoGroups[$pohdid]['all_closed'] = false;
            } elseif ($itemStatus === 'U') {
                $normalPoGroups[$pohdid]['has_partial'] = true;
                $normalPoGroups[$pohdid]['all_closed'] = false;
            } elseif ($itemStatus === 'X') {
                $normalPoGroups[$pohdid]['has_closed'] = true;
            } else {
                $normalPoGroups[$pohdid]['all_closed'] = false;
            }

            $d1 = !empty($row['podlvrdate1']) ? date('Y-m-d', strtotime($row['podlvrdate1'])) : null;
            $d2 = !empty($row['podlvrdate2']) ? date('Y-m-d', strtotime($row['podlvrdate2'])) : null;
            if ($d1 && (!$normalPoGroups[$pohdid]['min_d1'] || $d1 < $normalPoGroups[$pohdid]['min_d1'])) {
                $normalPoGroups[$pohdid]['min_d1'] = $d1;
            }
            if ($d2 && (!$normalPoGroups[$pohdid]['max_d2'] || $d2 > $normalPoGroups[$pohdid]['max_d2'])) {
                $normalPoGroups[$pohdid]['max_d2'] = $d2;
            }

            $grnDetailsStr = !empty($row['grn_details']) ? trim($row['grn_details']) : '';
            $grnList = [];
            if ($grnDetailsStr !== '') {
                foreach (explode('|', $grnDetailsStr) as $entry) {
                    $entry = trim($entry);
                    if ($entry === '') continue;
                    $parts = explode(':', $entry);
                    $gNum = trim($parts[0] ?? '');
                    $gQty = isset($parts[1]) ? (float) $parts[1] : 0;
                    if ($gNum !== '') {
                        $grnList[] = [
                            'grn_number' => $gNum,
                            'grn_qty' => formatQty($gQty),
                            'grn_qty_raw' => $gQty
                        ];
                        if (!in_array($gNum, $normalPoGroups[$pohdid]['po_grns'])) {
                            $normalPoGroups[$pohdid]['po_grns'][] = $gNum;
                        }
                    }
                }
            }

            $normalPoGroups[$pohdid]['items'][] = [
                'poseq'       => (int) $row['poseq'],
                'poprodcode'  => trim($row['poprodcode'] ?? '-'),
                'poprodname'  => trim($row['poprodname'] ?? '-'),
                'struct_name' => trim($row['struct_name'] ?? '-'),
                'poqty'       => formatQty($row['poqty']),
                'grnqty'      => formatQty($row['grnqty']),
                'grn_list'    => $grnList,
                'grn_numbers' => !empty($grnList) ? implode(', ', array_column($grnList, 'grn_number')) : '-',
                'sisa_qty'    => formatQty($row['sisa_qty']),
                'podlvrdate1' => $row['podlvrdate1'] ? date('d/m/Y', strtotime($row['podlvrdate1'])) : '-',
                'podlvrdate2' => $row['podlvrdate2'] ? date('d/m/Y', strtotime($row['podlvrdate2'])) : '-',
                'item_status' => $itemStatus,
            ];
        }

        // Kumpulkan daftar opsi PIC secara dinamis HANYA dari data PO yang muncul pada jadwal delivery terpilih
        $picOptions = [];
        foreach ($normalPoGroups as $g) {
            $pName = trim($g['pic_pembuat'] ?? '');
            if ($pName !== '' && $pName !== '-' && !in_array($pName, $picOptions)) {
                $picOptions[] = $pName;
            }
        }
        sort($picOptions, SORT_STRING | SORT_FLAG_CASE);

        // Jika filter PIC aktif tetapi tidak ada di data tanggal terpilih, reset filter PIC
        if ($picFilter !== '' && !in_array($picFilter, $picOptions)) {
            $picFilter = '';
        }

        // Filter normal PO berdasarkan PIC Pembuat PO jika dipilih
        if ($picFilter !== '') {
            $normalPoGroups = array_filter($normalPoGroups, function ($g) use ($picFilter) {
                return ($g['pic_pembuat'] ?? '') === $picFilter;
            });
        }

        // Tentukan status ringkasan per PO dan hitung agregat untuk masing-masing card
        foreach ($normalPoGroups as $pohdid => &$group) {
            evaluatePoDeliveryStatus($group, $todayStr);
            if ($group['status_label'] === 'Selesai') {
                $countSelesai++;
            } elseif (strpos($group['status_label'], 'Parsial') !== false) {
                $countParsial++;
            } elseif ($group['status_label'] === 'Belum Datang') {
                $countBelumDatang++;
            }
        }
        unset($group);

        // 3. Tentukan data yang akan ditampilkan di tabel berdasarkan statusFilter
        if ($statusFilter === 'Overdue') {
            // Ambil data PO Overdue
            $sqlOverdueData = "
                SELECT 
                    h.pohdid,
                    h.ponmbr,
                    h.podate,
                    h.povendorcode,
                    h.povendorname,
                    h.poinitiatorid,
                    u.username AS pic_pembuat,
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
                    s.structname AS struct_name,
                    (
                        SELECT STRING_AGG(gh.grnnmbr || ':' || sub.total_grnqty, '|' ORDER BY sub.min_date ASC, gh.grnnmbr ASC)
                        FROM (
                            SELECT gd.grnhdid, SUM(gd.grnqty) as total_grnqty, MIN(gh2.grndate) as min_date
                            FROM prgrndt gd
                            JOIN prgrnhd gh2 ON gd.grnhdid = gh2.grnhdid
                            WHERE gd.podtid = d.podtid
                            GROUP BY gd.grnhdid
                        ) sub
                        JOIN prgrnhd gh ON sub.grnhdid = gh.grnhdid
                    ) AS grn_details
                FROM prpodt d
                JOIN prpohd h ON d.pohdid = h.pohdid
                LEFT JOIN msuser u ON h.poinitiatorid = u.initiatorid
                LEFT JOIN smproduct p ON TRIM(d.poprodcode) = TRIM(p.prodcode)
                LEFT JOIN smprodstruct s ON p.prodstructid = s.prodstructid
                WHERE COALESCE(DATE(d.podlvrdate2), DATE(d.podlvrdate1)) < :target_date
                  AND TRIM(h.fgstatus) = 'V'
                ORDER BY COALESCE(d.podlvrdate2, d.podlvrdate1) DESC, h.ponmbr ASC, d.poseq ASC
                LIMIT 500
            ";
            $stmtOverdueData = $conn3->prepare($sqlOverdueData);
            $stmtOverdueData->execute([':target_date' => $todayStr]);
            $overdueRows = $stmtOverdueData->fetchAll(PDO::FETCH_ASSOC);

            $overduePoGroups = [];
            foreach ($overdueRows as $row) {
                $pohdid = (int) $row['pohdid'];
                if (!isset($overduePoGroups[$pohdid])) {
                    $picPembuat = !empty($row['pic_pembuat']) ? trim($row['pic_pembuat']) : '-';

                    $overduePoGroups[$pohdid] = [
                        'pohdid'      => $pohdid,
                        'ponmbr'      => trim($row['ponmbr'] ?? '-'),
                        'pic_pembuat' => $picPembuat,
                        'podate' => $row['podate'] ? date('d/m/Y', strtotime($row['podate'])) : '-',
                        'povendorcode' => trim($row['povendorcode'] ?? ''),
                        'povendorname' => trim($row['povendorname'] ?? '-'),
                        'po_status' => trim($row['po_status'] ?? ''),
                        'min_d1' => null,
                        'max_d2' => null,
                        'items' => [],
                        'po_grns' => [],
                    ];
                }

                $d1 = !empty($row['podlvrdate1']) ? date('Y-m-d', strtotime($row['podlvrdate1'])) : null;
                $d2 = !empty($row['podlvrdate2']) ? date('Y-m-d', strtotime($row['podlvrdate2'])) : null;
                if ($d1 && (!$overduePoGroups[$pohdid]['min_d1'] || $d1 < $overduePoGroups[$pohdid]['min_d1'])) {
                    $overduePoGroups[$pohdid]['min_d1'] = $d1;
                }
                if ($d2 && (!$overduePoGroups[$pohdid]['max_d2'] || $d2 > $overduePoGroups[$pohdid]['max_d2'])) {
                    $overduePoGroups[$pohdid]['max_d2'] = $d2;
                }

                $grnDetailsStr = !empty($row['grn_details']) ? trim($row['grn_details']) : '';
                $grnList = [];
                if ($grnDetailsStr !== '') {
                    foreach (explode('|', $grnDetailsStr) as $entry) {
                        $entry = trim($entry);
                        if ($entry === '') continue;
                        $parts = explode(':', $entry);
                        $gNum = trim($parts[0] ?? '');
                        $gQty = isset($parts[1]) ? (float) $parts[1] : 0;
                        if ($gNum !== '') {
                            $grnList[] = [
                                'grn_number' => $gNum,
                                'grn_qty' => formatQty($gQty),
                                'grn_qty_raw' => $gQty
                            ];
                            if (!in_array($gNum, $overduePoGroups[$pohdid]['po_grns'])) {
                                $overduePoGroups[$pohdid]['po_grns'][] = $gNum;
                            }
                        }
                    }
                }

                $overduePoGroups[$pohdid]['items'][] = [
                    'poseq'       => (int) $row['poseq'],
                    'poprodcode'  => trim($row['poprodcode'] ?? '-'),
                    'poprodname'  => trim($row['poprodname'] ?? '-'),
                    'struct_name' => trim($row['struct_name'] ?? '-'),
                    'poqty'       => formatQty($row['poqty']),
                    'grnqty'      => formatQty($row['grnqty']),
                    'grn_list'    => $grnList,
                    'grn_numbers' => !empty($grnList) ? implode(', ', array_column($grnList, 'grn_number')) : '-',
                    'sisa_qty'    => formatQty($row['sisa_qty']),
                    'podlvrdate1' => $row['podlvrdate1'] ? date('d/m/Y', strtotime($row['podlvrdate1'])) : '-',
                    'podlvrdate2' => $row['podlvrdate2'] ? date('d/m/Y', strtotime($row['podlvrdate2'])) : '-',
                    'item_status' => trim($row['item_status'] ?? ''),
                ];
            }

            // Kumpulkan daftar opsi PIC secara dinamis HANYA dari data PO Overdue yang muncul
            $picOptions = [];
            foreach ($overduePoGroups as $g) {
                $pName = trim($g['pic_pembuat'] ?? '');
                if ($pName !== '' && $pName !== '-' && !in_array($pName, $picOptions)) {
                    $picOptions[] = $pName;
                }
            }
            sort($picOptions, SORT_STRING | SORT_FLAG_CASE);

            if ($picFilter !== '' && !in_array($picFilter, $picOptions)) {
                $picFilter = '';
            }

            // Filter PO Overdue berdasarkan PIC Pembuat PO jika dipilih
            if ($picFilter !== '') {
                $overduePoGroups = array_filter($overduePoGroups, function ($g) use ($picFilter) {
                    return ($g['pic_pembuat'] ?? '') === $picFilter;
                });
            }

            foreach ($overduePoGroups as $pohdid => &$group) {
                evaluatePoDeliveryStatus($group, $todayStr);
            }
            unset($group);

            $poGroups = $overduePoGroups;
        } else {
            // Data normal dengan filter status jika dipilih
            if (!empty($statusFilter)) {
                $poGroups = array_filter($normalPoGroups, function ($g) use ($statusFilter) {
                    if ($statusFilter === 'Parsial') {
                        return strpos($g['status_label'], 'Parsial') !== false;
                    }
                    return $g['status_label'] === $statusFilter;
                });
            } else {
                $poGroups = $normalPoGroups;
            }
        }

        // 4. Ambil data status pembayaran (Cash Payment / CPNmbr dengan fgstatus = 'V')
        // Sesuai kriteria: CPNmbr terisi dan di tabel cmcphd kolom fgstatus = 'V'
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
                    -- 1. Pembayaran AP Invoice setelah penerimaan barang (GRN -> APPosting -> CMCP)
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

                    -- 2. Pembayaran Uang Muka / Langsung PO (CMTTTAP -> CMCP)
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

                    -- 3. Pembayaran via Settlement Uang Muka Impor (PASettlement -> CMCP)
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
            $payRows = $stmtPayment->fetchAll(PDO::FETCH_ASSOC);
            foreach ($payRows as $pr) {
                $pId = (int)$pr['pohdid'];
                $paymentLookup[$pId] = [
                    'cp_numbers' => trim($pr['cp_numbers'] ?? ''),
                    'payments' => []
                ];
                if (!empty($pr['cp_details'])) {
                    foreach (explode('|', $pr['cp_details']) as $part) {
                        if (trim($part) === '') continue;
                        $p = explode('~', $part);
                        $cNum = trim($p[0] ?? '');
                        $cDate = trim($p[1] ?? '');
                        $cDateFmt = $cDate ? date('d/m/Y', strtotime($cDate)) : '-';
                        if ($cNum !== '') {
                            $paymentLookup[$pId]['payments'][] = [
                                'cp_number' => $cNum,
                                'cp_date' => $cDateFmt
                            ];
                        }
                    }
                }
            }
        }

        foreach ($poGroups as $pohdid => &$group) {
            $hasPay = isset($paymentLookup[$pohdid]) && !empty($paymentLookup[$pohdid]['cp_numbers']);
            $group['is_paid'] = $hasPay;
            $group['cp_numbers'] = $hasPay ? $paymentLookup[$pohdid]['cp_numbers'] : '';
            $group['payments'] = $hasPay ? $paymentLookup[$pohdid]['payments'] : [];
            $group['payment_label'] = $hasPay ? 'Sudah Dibayar' : 'Belum Dibayar';
            $group['payment_badge'] = $hasPay ? 'badge-success' : 'badge-secondary';
        }
        unset($group);

        // 5. Filter berdasarkan status pembayaran jika dipilih oleh user
        if ($paymentStatusFilter === 'paid') {
            $poGroups = array_filter($poGroups, function ($g) {
                return !empty($g['is_paid']);
            });
        } elseif ($paymentStatusFilter === 'unpaid') {
            $poGroups = array_filter($poGroups, function ($g) {
                return empty($g['is_paid']);
            });
        }

        // 6. Ambil data tujuan pengiriman dari SQL Server dbo.tracking_po_detail berdasarkan No PO (ponmbr)
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

        // Tempelkan data dikirim_ke dan alamat_kirim ke masing-masing group PO
        foreach ($poGroups as $pohdid => &$group) {
            $pNum = trim($group['ponmbr'] ?? '');
            $track = $trackingLookup[$pNum] ?? null;
            $group['dikirim_ke'] = $track['dikirim_ke'] ?? null;
            $group['alamat_kirim'] = $track['alamat_kirim'] ?? null;
        }
        unset($group);

        // 7. Filter berdasarkan Tujuan Pengiriman (Dikirim Ke) jika dipilih oleh user
        if ($dikirimKeFilter !== '') {
            $poGroups = array_filter($poGroups, function ($g) use ($dikirimKeFilter) {
                if ($dikirimKeFilter === 'belum_ditracking') {
                    return empty($g['dikirim_ke']);
                }
                return ($g['dikirim_ke'] ?? '') === $dikirimKeFilter;
            });
        }
    } catch (PDOException $e) {
        $errorMessage = "Gagal mengambil data dari database ERP: " . htmlspecialchars($e->getMessage());
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .action-btn {
        margin-right: 6px;
    }

    /* Style Card Filter Interaktif */
    .card-filter-stat {
        cursor: pointer;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        position: relative;
        overflow: hidden;
        user-select: none;
    }

    .card-filter-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
    }

    .card-filter-stat .small-box-footer {
        font-size: 0.78rem;
        padding: 4px 10px;
        display: block;
        text-align: center;
        text-decoration: none;
        transition: background-color 0.15s ease;
    }

    /* Highlight Card Aktif Sesuai Warna Masing-masing */
    .card-filter-stat.active-filter {
        border-width: 2px !important;
    }

    .card-filter-stat.active-filter[data-status="Belum Datang"] {
        border-color: #ffc107 !important;
        box-shadow: 0 4px 14px rgba(255, 193, 7, 0.35);
    }

    .card-filter-stat.active-filter[data-status="Parsial"] {
        border-color: #17a2b8 !important;
        box-shadow: 0 4px 14px rgba(23, 162, 184, 0.35);
    }

    .card-filter-stat.active-filter[data-status="Selesai"] {
        border-color: #28a745 !important;
        box-shadow: 0 4px 14px rgba(40, 167, 69, 0.35);
    }

    .card-filter-stat.active-filter[data-status="Overdue"] {
        border-color: #dc3545 !important;
        box-shadow: 0 4px 14px rgba(220, 53, 69, 0.35);
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1em;
        top: 50%;
        transform: translateY(-50%);
    }

    table.dataTable>tbody>tr.child ul.dtr-details {
        display: block;
        width: 100%;
        padding: 0;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li {
        border-bottom: 1px solid #efefef;
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li:last-child {
        border-bottom: none;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-title {
        font-weight: 600;
        color: #555;
        min-width: 120px;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-data {
        text-align: right;
        flex: 1;
        word-break: break-word;
    }

    /* ─── Modal Detail PO Enhanced Styling ───────────────────────────── */
    #modalDetailPo > .modal-dialog {
        max-width: 1500px !important;
        width: 98% !important;
    }

    #modalDetailPo .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    #modalDetailPo .modal-header {
        padding: 14px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .modal-po-info-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }

    .info-segment {
        padding: 10px 14px;
        border-right: 1px solid #f1f5f9;
    }

    @media (max-width: 991px) {
        .info-segment {
            border-right: none;
            border-bottom: 1px solid #f1f5f9;
        }
    }

    .info-segment:last-child {
        border-right: none;
    }

    .info-icon-wrapper {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    /* Modern Table Styling */
    #tableModalItems {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }

    #tableModalItems thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 2px solid #e2e8f0;
        vertical-align: middle;
        padding: 10px 8px;
    }

    #tableModalItems tbody td {
        padding: 10px 8px;
        font-size: 0.86rem;
        vertical-align: middle;
        border-top: 1px solid #f1f5f9;
    }

    #tableModalItems tbody tr:hover {
        background-color: #f8fafc;
    }

    #tableModalItems tfoot td {
        background-color: #f8fafc;
        font-weight: 700;
        border-top: 2px solid #cbd5e1;
        font-size: 0.85rem;
        padding: 9px 8px;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">

        <section class="content-header">
            <div class="container-fluid">
                <h1>Reminder PO Delivery</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Gagal Memuat Data!</h5>
                        <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- ============================================================== -->
                <!-- 4 KARTU FILTER STATUS SESUAI PERMINTAAN USER (INTERAKTIF)      -->
                <!-- ============================================================== -->
                <div class="row mb-3">
                    <!-- 1. Belum Datang -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border card-filter-stat <?= ($statusFilter === 'Belum Datang') ? 'active-filter' : '' ?>"
                             data-status="Belum Datang" title="Klik untuk memfilter PO Belum Datang">
                            <div class="inner">
                                <h3 class="text-warning font-weight-bold"><?= number_format($countBelumDatang, 0, ',', '.') ?></h3>
                                <p class="font-weight-bold text-dark mb-0">Belum Datang</p>
                                <span class="text-xs text-muted">Barang belum tiba (GRN = 0)</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hourglass-half text-warning" style="opacity: 0.35;"></i>
                            </div>
                            <div class="small-box-footer <?= ($statusFilter === 'Belum Datang') ? 'bg-warning text-dark font-weight-bold' : 'text-muted bg-light' ?>">
                                <?= ($statusFilter === 'Belum Datang') ? '<i class="fas fa-check-circle mr-1"></i> Filter Aktif (Klik Reset)' : 'Klik untuk filter <i class="fas fa-filter ml-1"></i>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Parsial (Diterima Sebagian) -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border card-filter-stat <?= ($statusFilter === 'Parsial') ? 'active-filter' : '' ?>"
                             data-status="Parsial" title="Klik untuk memfilter PO Parsial">
                            <div class="inner">
                                <h3 class="text-info font-weight-bold"><?= number_format($countParsial, 0, ',', '.') ?></h3>
                                <p class="font-weight-bold text-dark mb-0">Parsial (Diterima Sebagian)</p>
                                <span class="text-xs text-muted">Barang baru datang sebagian</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-box-open text-info" style="opacity: 0.35;"></i>
                            </div>
                            <div class="small-box-footer <?= ($statusFilter === 'Parsial') ? 'bg-info text-white font-weight-bold' : 'text-muted bg-light' ?>">
                                <?= ($statusFilter === 'Parsial') ? '<i class="fas fa-check-circle mr-1"></i> Filter Aktif (Klik Reset)' : 'Klik untuk filter <i class="fas fa-filter ml-1"></i>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Selesai -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border card-filter-stat <?= ($statusFilter === 'Selesai') ? 'active-filter' : '' ?>"
                             data-status="Selesai" title="Klik untuk memfilter PO Selesai">
                            <div class="inner">
                                <h3 class="text-success font-weight-bold"><?= number_format($countSelesai, 0, ',', '.') ?></h3>
                                <p class="font-weight-bold text-dark mb-0">Selesai</p>
                                <span class="text-xs text-muted">Penerimaan 100% lengkap</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle text-success" style="opacity: 0.35;"></i>
                            </div>
                            <div class="small-box-footer <?= ($statusFilter === 'Selesai') ? 'bg-success text-white font-weight-bold' : 'text-muted bg-light' ?>">
                                <?= ($statusFilter === 'Selesai') ? '<i class="fas fa-check-circle mr-1"></i> Filter Aktif (Klik Reset)' : 'Klik untuk filter <i class="fas fa-filter ml-1"></i>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- 4. PO Overdue (Terlambat) -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border card-filter-stat <?= ($statusFilter === 'Overdue') ? 'active-filter' : '' ?>"
                             data-status="Overdue" title="Klik untuk memfilter PO Overdue">
                            <div class="inner">
                                <h3 class="text-danger font-weight-bold"><?= number_format($countOverdue, 0, ',', '.') ?></h3>
                                <p class="font-weight-bold text-dark mb-0">PO Overdue (Terlambat)</p>
                                <span class="text-xs text-muted">Lewat batas tanggal delivery</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-exclamation-circle text-danger" style="opacity: 0.35;"></i>
                            </div>
                            <div class="small-box-footer <?= ($statusFilter === 'Overdue') ? 'bg-danger text-white font-weight-bold' : 'text-muted bg-light' ?>">
                                <?= ($statusFilter === 'Overdue') ? '<i class="fas fa-check-circle mr-1"></i> Filter Aktif (Klik Reset)' : 'Klik untuk filter <i class="fas fa-filter ml-1"></i>' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title">
                            <i class="fas fa-truck-loading mr-1"></i> List Pengingat Delivery PO
                            <?php 
                                $formattedPeriod = ($startDate === $endDate)
                                    ? date('d/m/Y', strtotime($startDate))
                                    : (date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)));
                            ?>
                            <span class="badge badge-light text-primary font-weight-bold ml-2 shadow-xs" style="font-size: 0.8rem;">
                                <i class="far fa-calendar-alt mr-1"></i>Periode: <?= htmlspecialchars($formattedPeriod) ?>
                            </span>
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-light text-dark font-weight-bold px-2 py-1">
                                <i class="fas fa-database text-success mr-1"></i> ERP Crystal SUM (Read-Only)
                            </span>
                        </div>
                    </div>

                    <div class="card-body table-responsive">
                        <!-- Section Filter Rentang Tanggal Delivery -->
                        <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                            <form method="GET" action="" id="filterForm">
                                <div class="row align-items-end">
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterTanggalMulai">
                                            <i class="far fa-calendar-alt text-primary mr-1"></i>Dari Tanggal
                                        </label>
                                        <input type="date" id="filterTanggalMulai" name="tgl_mulai" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($startDate); ?>">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterTanggalSelesai">
                                            <i class="far fa-calendar-check text-primary mr-1"></i>Sampai Tanggal
                                        </label>
                                        <input type="date" id="filterTanggalSelesai" name="tgl_selesai" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($endDate); ?>">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterStatus">Status Pengiriman</label>
                                        <select class="form-control form-control-sm" id="filterStatus" name="status">
                                            <option value="">-- Semua Status --</option>
                                            <option value="Belum Datang" <?php echo $statusFilter === 'Belum Datang' ? 'selected' : ''; ?>>Belum Datang</option>
                                            <option value="Parsial" <?php echo $statusFilter === 'Parsial' ? 'selected' : ''; ?>>Parsial (Diterima Sebagian)</option>
                                            <option value="Selesai" <?php echo $statusFilter === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                                            <option value="Overdue" <?php echo $statusFilter === 'Overdue' ? 'selected' : ''; ?>>PO Overdue (Terlambat)</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterPaymentStatus">
                                            <i class="fas fa-money-bill-wave text-success mr-1"></i>Status Bayar
                                        </label>
                                        <select class="form-control form-control-sm" id="filterPaymentStatus" name="payment_status">
                                            <option value="">-- Semua Status Bayar --</option>
                                            <option value="paid" <?php echo $paymentStatusFilter === 'paid' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                            <option value="unpaid" <?php echo $paymentStatusFilter === 'unpaid' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterDikirimKe">
                                            <i class="fas fa-truck text-info mr-1"></i>Dikirim Ke
                                        </label>
                                        <select class="form-control form-control-sm" id="filterDikirimKe" name="dikirim_ke">
                                            <option value="">-- Semua Dikirim Ke --</option>
                                            <option value="PT.SUM" <?php echo $dikirimKeFilter === 'PT.SUM' ? 'selected' : ''; ?>>PT.SUM</option>
                                            <option value="Sanso Berjaya Texindo/Toko Sumber SERAGAM" <?php echo $dikirimKeFilter === 'Sanso Berjaya Texindo/Toko Sumber SERAGAM' ? 'selected' : ''; ?>>Sanso Berjaya Texindo/Toko Sumber SERAGAM</option>
                                            <option value="Diambil ke Supplier" <?php echo $dikirimKeFilter === 'Diambil ke Supplier' ? 'selected' : ''; ?>>Diambil ke Supplier</option>
                                            <option value="belum_ditracking" <?php echo $dikirimKeFilter === 'belum_ditracking' ? 'selected' : ''; ?>>Belum Ditracking</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                                        <label class="small mb-1 font-weight-bold text-dark" for="filterPic">PIC Pembuat PO</label>
                                        <select class="form-control form-control-sm" id="filterPic" name="pic">
                                            <option value="">-- Semua PIC --</option>
                                            <?php foreach ($picOptions as $pName): ?>
                                                <option value="<?php echo htmlspecialchars($pName); ?>" <?php echo $picFilter === $pName ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($pName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-2 pt-2 border-top d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px;">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="switchRange" name="range" value="1"
                                                <?php echo $includeRange === 1 ? 'checked' : ''; ?> <?php echo $statusFilter === 'Overdue' ? 'disabled' : ''; ?>>
                                            <label class="custom-control-label small font-weight-bold" for="switchRange" title="Sertakan PO yang jadwal pengirimannya sedang berjalan pada rentang ini">
                                                Rentang Berjalan
                                            </label>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="submit" class="btn btn-primary font-weight-bold px-3" title="Terapkan Filter">
                                                <i class="fas fa-filter mr-1"></i> Filter
                                            </button>
                                            <button type="button" id="btnToday" class="btn btn-outline-primary font-weight-bold" title="Set Tanggal Hari Ini">
                                                Hari Ini
                                            </button>
                                            <button type="button" id="btnReset" class="btn btn-secondary font-weight-bold" title="Reset Filter">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Tabel Pengingat PO -->
                        <table id="reminderTable" class="table table-hover table-sm nowrap" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">No</th>
                                    <th>No PO</th>
                                    <th>PIC Pembuat PO</th>
                                    <th>Tanggal PO</th>
                                    <th>Vendor / Supplier</th>
                                    <th class="text-center">Jumlah Item</th>
                                    <th class="text-center">Rentang Delivery</th>
                                    <th class="text-center">Status Delivery</th>
                                    <th class="text-center">Status Bayar</th>
                                    <th style="width: 85px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($poGroups as $pohdid => $group): 
                                    $d1Format = $group['min_d1'] ? date('d/m/Y', strtotime($group['min_d1'])) : '-';
                                    $d2Format = $group['max_d2'] ? date('d/m/Y', strtotime($group['max_d2'])) : '-';
                                    $deliveryRange = ($d1Format === $d2Format) ? $d1Format : ($d1Format . ' s/d ' . $d2Format);

                                    $noPoHtml = '<span class="badge badge-light border font-weight-bold text-dark px-2 py-1">'
                                              . '<i class="fas fa-file-invoice mr-1 text-primary"></i>' 
                                              . htmlspecialchars($group['ponmbr']) . '</span>';

                                    $aksi = '<div class="btn-group">';
                                    $aksi .= '<button type="button" class="btn btn-info btn-sm action-btn btn-view-po" data-pohdid="' . $pohdid . '" title="Lihat Detail Product">'
                                           . '<i class="fas fa-eye"></i>'
                                           . '</button>';
                                    $aksi .= '<button type="button" class="btn btn-warning btn-sm action-btn btn-add-issue ml-1" data-pohdid="' . $pohdid . '" title="Tambahkan ke Issue Purchasing (Follow Up Pengiriman)">'
                                           . '<i class="fas fa-clipboard-check"></i>'
                                           . '</button>';
                                    $aksi .= '</div>';
                                ?>
                                    <tr>
                                        <td class="text-center align-middle font-weight-bold text-muted"><?= $no++ ?></td>
                                        <td class="align-middle"><?= $noPoHtml ?></td>
                                        <td class="align-middle font-weight-bold text-dark">
                                            <?= htmlspecialchars($group['pic_pembuat'] ?? '-') ?>
                                        </td>
                                        <td class="align-middle text-muted"><?= htmlspecialchars($group['podate']) ?></td>
                                        <td class="align-middle font-weight-bold text-dark">
                                            <?= htmlspecialchars($group['povendorname']) ?>
                                            <?php if (!empty($group['povendorcode'])): ?>
                                                <span class="badge badge-light border text-muted ml-1"><?= htmlspecialchars($group['povendorcode']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($group['dikirim_ke'])): ?>
                                                <div class="mt-1">
                                                    <span class="badge badge-light border text-primary" style="font-size: 11px; font-weight: 600;" title="<?= htmlspecialchars($group['alamat_kirim'] ?? '') ?>">
                                                        <i class="fas fa-truck text-info mr-1"></i><?= htmlspecialchars($group['dikirim_ke']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge badge-pill badge-light border px-2"><?= count($group['items']) ?> Item</span>
                                        </td>
                                        <td class="text-center align-middle text-secondary font-weight-bold small">
                                            <i class="far fa-calendar-alt text-info mr-1"></i><?= htmlspecialchars($deliveryRange) ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge <?= htmlspecialchars($group['status_badge']) ?> px-2 py-1">
                                                <?= htmlspecialchars($group['status_label']) ?>
                                            </span>
                                            <?php if (!empty($group['is_overdue'])): ?>
                                                <span class="badge badge-danger px-2 py-1 ml-1" title="Tanggal delivery sudah lewat">
                                                    <i class="fas fa-clock mr-1"></i>Overdue
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if (!empty($group['is_paid'])): ?>
                                                <span class="badge badge-success px-2 py-1 shadow-xs font-weight-bold" title="No. CP: <?= htmlspecialchars($group['cp_numbers']) ?>">
                                                    <i class="fas fa-check-circle mr-1"></i>Sudah Dibayar
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary px-2 py-1 text-white font-weight-normal">
                                                    <i class="fas fa-clock mr-1"></i>Belum Dibayar
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle"><?= $aksi ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Detail PO & Product (Pengganti tombol expand [+]) persis modalDetailTiket di list_form.php -->
                <div class="modal fade" id="modalDetailPo" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                                    <i class="fas fa-boxes mr-2"></i>Detail Purchase Order : <span id="modalPoNumber" class="ml-2 badge badge-light text-dark font-family-monospace px-2 py-1 shadow-xs"></span>
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-3">
                                <!-- Ringkasan Informasi Header PO -->
                                <div class="modal-po-info-box p-3 mb-3">
                                    <div class="row no-gutters">
                                        <!-- 1. Nomor PO -->
                                        <div class="col-lg-3 col-md-6 col-12 info-segment">
                                            <div class="d-flex align-items-center">
                                                <div class="info-icon-wrapper bg-primary text-white mr-3 shadow-sm">
                                                    <i class="fas fa-file-invoice"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Nomor PO</small>
                                                    <span id="modalPoTitle" class="font-weight-bold text-primary text-truncate d-block" style="font-size: 1.05rem;"></span>
                                                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                                                        <i class="far fa-calendar-alt text-secondary mr-1"></i><span id="modalPoDate"></span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2. Vendor / Supplier -->
                                        <div class="col-lg-3 col-md-6 col-12 info-segment">
                                            <div class="d-flex align-items-center">
                                                <div class="info-icon-wrapper bg-info text-white mr-3 shadow-sm">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Vendor / Supplier</small>
                                                    <span id="modalPoVendor" class="font-weight-bold text-dark text-truncate d-block" style="font-size: 0.95rem;"></span>
                                                    <small class="text-muted d-block" style="font-size: 0.78rem;">Mitra Penyedia Barang</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 3. PIC Pembuat PO -->
                                        <div class="col-lg-3 col-md-6 col-12 info-segment">
                                            <div class="d-flex align-items-center">
                                                <div class="info-icon-wrapper text-white mr-3 shadow-sm" style="background-color: #6f42c1;">
                                                    <i class="fas fa-user-edit"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">PIC Pembuat PO</small>
                                                    <span id="modalPoPic" class="font-weight-bold text-dark text-truncate d-block" style="font-size: 0.95rem;"></span>
                                                    <small class="text-muted d-block" style="font-size: 0.78rem;"></small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 4. Status Delivery -->
                                        <div class="col-lg-3 col-md-6 col-12 info-segment">
                                            <div class="d-flex align-items-center">
                                                <div class="info-icon-wrapper bg-warning text-dark mr-3 shadow-sm">
                                                    <i class="fas fa-truck-loading"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <small class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Status Delivery</small>
                                                    <div id="modalPoStatusBadge" class="mt-1"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Baris No. GRN Penerimaan -->
                                    <div class="mt-3 pt-2 border-top d-flex align-items-center flex-wrap" style="border-top: 1px solid #e2e8f0 !important;">
                                        <span class="badge badge-light border text-muted px-2 py-1 mr-2 font-weight-bold" style="font-size: 0.78rem;">
                                            <i class="fas fa-receipt text-success mr-1"></i>No. GRN Penerimaan :
                                        </span>
                                        <div id="modalPoGrnList" class="d-inline-flex align-items-center flex-wrap"></div>
                                    </div>

                                    <!-- Baris Status Pembayaran, Tanggal CP, & No. CP -->
                                    <div class="mt-2 pt-2 border-top d-flex align-items-center flex-wrap" style="border-top: 1px dashed #e2e8f0 !important; gap: 8px;">
                                        <span class="badge badge-light border text-muted px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">
                                            <i class="fas fa-money-bill-wave text-success mr-1"></i>Status Pembayaran :
                                        </span>
                                        <div id="modalPoPaymentBadge" class="d-inline-block"></div>
                                        <div id="modalPoPaymentList" class="d-inline-flex align-items-center flex-wrap" style="gap: 8px;"></div>
                                    </div>

                                    <!-- Baris Tujuan Pengiriman (Dikirim Ke) & Alamat -->
                                    <div id="modalPoShippingBox" class="mt-2 pt-2 border-top d-flex align-items-center flex-wrap" style="border-top: 1px dashed #e2e8f0 !important; gap: 8px; display: none;">
                                        <span class="badge badge-light border text-muted px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">
                                            <i class="fas fa-truck text-primary mr-1"></i>Tujuan Pengiriman (Dikirim Ke) :
                                        </span>
                                        <span id="modalPoDikirimKe" class="badge badge-primary px-2 py-1 font-weight-bold"></span>
                                        <span class="small text-muted ml-2" id="modalPoAlamatKirimWrap">
                                            <i class="fas fa-map-marker-alt text-danger mr-1"></i><span id="modalPoAlamatKirim"></span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Tabel Detail Item / Product -->
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0" id="tableModalItems">
                                        <thead>
                                            <tr>
                                                <th style="width: 35px;" class="text-center">No</th>
                                                <th style="width: 130px;">Kode Barang</th>
                                                <th style="min-width: 220px;">Nama Barang / Produk</th>
                                                <th style="min-width: 160px;">Struct Name</th>
                                                <th style="width: 80px;" class="text-right">Qty PO</th>
                                                <th style="width: 110px;" class="text-right">Diterima</th>
                                                <th style="width: 160px;" class="text-center">No GRN</th>
                                                <th style="width: 80px;" class="text-right">Sisa Qty</th>
                                                <th style="width: 125px;" class="text-center">Jadwal Delivery</th>
                                                <th style="width: 110px;" class="text-center">Status Item</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalItemsBody">
                                            <!-- Data dimuat secara instan melalui JavaScript -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-right text-uppercase small text-muted">Total Ringkasan:</td>
                                                <td class="text-right text-dark" id="modalFooterTotalPoQty">0</td>
                                                <td class="text-right text-success" id="modalFooterTotalGrnQty">0</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-right text-danger" id="modalFooterTotalSisaQty">0</td>
                                                <td colspan="2" class="text-center text-muted small" id="modalFooterTotalItems">0 Item</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer bg-light px-3 py-2 border-top">
                                <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">
                                    <i class="fas fa-times mr-1"></i> Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Tambah Issue Follow Up PO ke Issues Purchasing -->
                <div class="modal fade" id="modalAddIssueFollowUp" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content shadow-lg border-0">
                            <div class="modal-header bg-warning text-dark px-3 py-2 d-flex align-items-center">
                                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" style="font-size: 1.1rem;">
                                    <i class="fas fa-clipboard-check mr-2 text-dark"></i> Tambahkan ke Issue Purchasing (Follow Up Pengiriman)
                                </h5>
                                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-3">
                                <input type="hidden" id="issueFollowUpPoNumber" value="">
                                
                                <!-- Info Singkat Akun & PO -->
                                <div class="card bg-light border-0 mb-3 shadow-none">
                                    <div class="card-body p-2 d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
                                        <div>
                                            <span class="text-muted small d-block">Nomor Purchase Order (PO):</span>
                                            <span class="badge badge-light border font-weight-bold text-dark px-2 py-1" style="font-size: 0.95rem;">
                                                <i class="fas fa-file-invoice mr-1 text-primary"></i><span id="issueFollowUpBadgePo">-</span>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-muted small d-block">Vendor / Supplier:</span>
                                            <span class="font-weight-bold text-dark" id="issueFollowUpBadgeVendor">-</span>
                                        </div>
                                        <div>
                                            <span class="text-muted small d-block">Akun Pembuat Issue:</span>
                                            <span class="badge badge-info px-2 py-1 font-weight-bold">
                                                <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Issue Name -->
                                    <div class="col-12 mb-3">
                                        <label class="font-weight-bold text-dark mb-1" for="issueFollowUpName">
                                            Issue Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control font-weight-bold bg-light" id="issueFollowUpName" readonly>
                                    </div>

                                    <!-- Kategori & Subkategori -->
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Kategori</label>
                                        <input type="text" class="form-control bg-light" value="Vendor" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Subkategori</label>
                                        <input type="text" class="form-control bg-light" value="Follow UP Pengiriman" readonly>
                                    </div>

                                    <!-- Type, Status, Priority, Due Date -->
                                    <div class="col-6 col-md-3 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Type</label>
                                        <input type="text" class="form-control bg-light" value="Task" readonly>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Status</label>
                                        <div class="form-control bg-light d-flex align-items-center">
                                            <span class="badge badge-success px-2 py-1 font-weight-bold">
                                                <i class="fas fa-check-circle mr-1"></i>Done
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Priority</label>
                                        <div class="form-control bg-light d-flex align-items-center">
                                            <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">
                                                <i class="fas fa-flag mr-1"></i>Medium
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <label class="font-weight-bold text-dark mb-1">Due Date</label>
                                        <input type="text" class="form-control bg-light" value="<?php echo date('d/m/Y'); ?>" readonly>
                                    </div>

                                    <!-- Deskripsi & Catatan Follow Up -->
                                    <div class="col-12 mb-2">
                                        <label class="font-weight-bold text-dark mb-1" for="issueFollowUpDescription">
                                            <i class="fas fa-align-left mr-1 text-primary"></i>Deskripsi & Catatan Hasil Follow Up
                                        </label>
                                        <textarea class="form-control" id="issueFollowUpDescription" rows="8" style="font-family: inherit; font-size: 0.9rem;" placeholder="Tuliskan catatan hasil follow-up..."></textarea>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle mr-1 text-info"></i>Ringkasan data PO telah terisi otomatis di atas. Anda dapat menambahkan catatan hasil komunikasi/follow-up dengan vendor di bawah garis pemisah.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light px-3 py-2 border-top d-flex justify-content-between align-items-center">
                                <span class="text-muted small">
                                    <i class="fas fa-history mr-1"></i>Data akan tercatat pada <b>Riwayat Issues Purchasing</b>.
                                </span>
                                <div>
                                    <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Batal
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm px-4 font-weight-bold" id="btnSubmitIssueFollowUp">
                                        <i class="fas fa-save mr-1"></i> Simpan ke Issue
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<!-- DataTables CSS persis list_form.php -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS & SweetAlert persis list_form.php -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    // Data detail PO dan item barang diserialisasi untuk akses instan tanpa reload
    var poDataLookup = <?php echo json_encode($poGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    $(document).ready(function () {
        // ─── Inisialisasi DataTable persis list_form.php ─────────────────────────────
        var table = $('#reminderTable').DataTable({
            order: [[1, 'asc']],
            responsive: true,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data pengiriman PO ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
            }
        });

        // ─── Klik Card Filter Status Interaktif ───────────────────────────────────
        $('.card-filter-stat').on('click', function () {
            var clickedStatus = $(this).data('status');
            var currentStatus = $('#filterStatus').val();

            // Jika kartu yang sama diklik lagi, batalkan filter (reset ke Semua)
            if (currentStatus === clickedStatus) {
                $('#filterStatus').val('');
            } else {
                $('#filterStatus').val(clickedStatus);
            }

            $('#filterForm').submit();
        });

        // ─── Filter Form Event Listeners ──────────────────────────────────────────
        $('#filterStatus, #filterPic, #filterPaymentStatus, #filterDikirimKe, #switchRange').on('change', function () {
            $('#filterForm').submit();
        });

        // Sinkronisasi batas tanggal agar tgl_mulai <= tgl_selesai
        $('#filterTanggalMulai').on('change', function () {
            var startVal = $(this).val();
            var endVal = $('#filterTanggalSelesai').val();
            if (startVal && endVal && startVal > endVal) {
                $('#filterTanggalSelesai').val(startVal);
            }
        });
        $('#filterTanggalSelesai').on('change', function () {
            var startVal = $('#filterTanggalMulai').val();
            var endVal = $(this).val();
            if (startVal && endVal && endVal < startVal) {
                $('#filterTanggalMulai').val(endVal);
            }
        });

        // Tombol Set Hari Ini
        $('#btnToday').on('click', function () {
            var today = '<?php echo $todayStr; ?>';
            $('#filterTanggalMulai').val(today);
            $('#filterTanggalSelesai').val(today);
            $('#filterForm').submit();
        });

        // Tombol Reset Filter
        $('#btnReset').on('click', function () {
            window.location.href = 'remainder_po.php';
        });

        // ─── Modal Detail PO (Pengganti Tombol +) ──────────────────────────────────
        $(document).on('click', '.btn-view-po', function () {
            var btn = $(this);
            var pohdid = btn.data('pohdid');
            var po = poDataLookup[pohdid];

            if (!po) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Data rincian PO tidak ditemukan.'
                });
                return;
            }

            // Render Header Info pada Modal
            $('#modalPoNumber').text(po.ponmbr);
            $('#modalPoTitle').text(po.ponmbr);
            $('#modalPoDate').text(po.podate);
            $('#modalPoPic').text(po.pic_pembuat || '-');
            $('#modalPoVendor').text(po.povendorname + (po.povendorcode ? ' (' + po.povendorcode + ')' : '')).attr('title', po.povendorname);
            
            var statusHtml = '<span class="badge ' + po.status_badge + ' px-2 py-1">' + po.status_label + '</span>';
            if (po.is_overdue) {
                statusHtml += ' <span class="badge badge-danger px-2 py-1 ml-1" title="Tanggal delivery sudah lewat"><i class="fas fa-clock mr-1"></i>Overdue</span>';
            }
            $('#modalPoStatusBadge').html(statusHtml);

            // Render Daftar No GRN pada Header Modal
            if (po.po_grns && po.po_grns.length > 0) {
                var grnBadges = [];
                $.each(po.po_grns, function (i, gn) {
                    grnBadges.push('<span class="badge badge-success font-weight-bold px-2 py-1 mr-1 mb-1 shadow-xs"><i class="fas fa-receipt mr-1"></i>' + gn + '</span>');
                });
                $('#modalPoGrnList').html(grnBadges.join(' '));
            } else {
                $('#modalPoGrnList').html('<span class="badge badge-light border text-muted px-2 py-1 font-weight-normal"><i class="fas fa-info-circle mr-1"></i>Belum ada penerimaan barang (GRN = 0)</span>');
            }

            // Render Status Pembayaran, Tanggal CP, & No CP pada Header Modal
            if (po.is_paid) {
                $('#modalPoPaymentBadge').html('<span class="badge badge-success px-2 py-1 font-weight-bold shadow-xs"><i class="fas fa-check-circle mr-1"></i>Sudah Dibayar</span>');
                if (po.payments && po.payments.length > 0) {
                    var payItems = [];
                    $.each(po.payments, function (i, pay) {
                        payItems.push(
                            '<div class="d-inline-flex align-items-center bg-white border rounded px-2 py-1 shadow-xs" style="gap: 6px;">' +
                                '<span class="badge badge-light border text-muted px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">' +
                                    '<i class="far fa-calendar-alt text-info mr-1"></i>Tanggal CP :' +
                                '</span>' +
                                '<span class="badge badge-light border text-dark font-weight-bold px-2 py-1" style="font-size: 0.8rem;">' + (pay.cp_date || '-') + '</span>' +
                                '<span class="badge badge-light border text-muted px-2 py-1 ml-1 font-weight-bold" style="font-size: 0.78rem;">' +
                                    '<i class="fas fa-receipt text-primary mr-1"></i>No. Cash Payment (CP) :' +
                                '</span>' +
                                '<span class="badge badge-primary font-weight-bold px-2 py-1 shadow-xs" style="font-size: 0.8rem;">' + pay.cp_number + '</span>' +
                            '</div>'
                        );
                    });
                    $('#modalPoPaymentList').html(payItems.join(' '));
                } else if (po.cp_numbers && po.cp_numbers.trim() !== '') {
                    $('#modalPoPaymentList').html(
                        '<div class="d-inline-flex align-items-center bg-white border rounded px-2 py-1 shadow-xs" style="gap: 6px;">' +
                            '<span class="badge badge-light border text-muted px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">' +
                                '<i class="fas fa-receipt text-primary mr-1"></i>No. Cash Payment (CP) :' +
                            '</span>' +
                            '<span class="badge badge-primary font-weight-bold px-2 py-1 shadow-xs">' + po.cp_numbers + '</span>' +
                        '</div>'
                    );
                } else {
                    $('#modalPoPaymentList').html('<span class="badge badge-light border text-muted px-2 py-1 font-weight-normal">-</span>');
                }
            } else {
                $('#modalPoPaymentBadge').html('<span class="badge badge-secondary px-2 py-1 text-white font-weight-normal"><i class="fas fa-clock mr-1"></i>Belum Dibayar</span>');
                $('#modalPoPaymentList').html('<span class="badge badge-light border text-muted px-2 py-1 font-weight-normal"><i class="fas fa-info-circle mr-1"></i>Belum ada bukti pembayaran (CP)</span>');
            }

            // Render Tujuan Pengiriman (Dikirim Ke) pada Header Modal
            if (po.dikirim_ke) {
                $('#modalPoDikirimKe').text(po.dikirim_ke);
                $('#modalPoAlamatKirim').text(po.alamat_kirim || '-');
                $('#modalPoShippingBox').show();
            } else {
                $('#modalPoShippingBox').hide();
            }

            // Render Baris Item Barang
            var itemsHtml = '';
            var totalPoQty = 0;
            var totalGrnQty = 0;
            var totalSisaQty = 0;

            function formatNumberId(num) {
                if (Math.floor(num) === num) {
                    return num.toLocaleString('id-ID');
                }
                return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            }

            if (po.items && po.items.length > 0) {
                $.each(po.items, function (idx, item) {
                    var statusBadge = '';
                    var st = (item.item_status || '').toUpperCase().trim();
                    var poQtyNum = parseFloat(String(item.poqty).replace(/\./g, '').replace(',', '.')) || 0;
                    var grnQtyNum = parseFloat(String(item.grnqty).replace(/\./g, '').replace(',', '.')) || 0;
                    var sisaQtyNum = parseFloat(String(item.sisa_qty).replace(/\./g, '').replace(',', '.')) || 0;

                    totalPoQty += poQtyNum;
                    totalGrnQty += grnQtyNum;
                    totalSisaQty += sisaQtyNum;

                    if (st === 'U') {
                        statusBadge = '<span class="badge badge-info"><i class="fas fa-box-open mr-1"></i>Parsial</span>';
                    } else if (st === 'V' || st === 'O') {
                        statusBadge = '<span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i>Belum Datang</span>';
                    } else if (st === 'X') {
                        statusBadge = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Selesai</span>';
                    } else if (st === 'C') {
                        statusBadge = '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Batal</span>';
                    } else {
                        statusBadge = '<span class="badge badge-secondary">' + (st || '-') + '</span>';
                    }

                    // Format kolom Jadwal Delivery
                    var deliveryDateHtml = '';
                    if (item.podlvrdate1 === item.podlvrdate2 || !item.podlvrdate2 || item.podlvrdate2 === '-') {
                        deliveryDateHtml = '<i class="far fa-calendar-alt text-info mr-1"></i><span class="font-weight-bold text-dark">' + item.podlvrdate1 + '</span>';
                    } else {
                        deliveryDateHtml = '<i class="far fa-calendar-alt text-info mr-1"></i><span class="font-weight-bold text-dark">' + item.podlvrdate1 + '</span>' +
                            '<span class="d-block text-xs text-muted font-weight-bold">s/d ' + item.podlvrdate2 + '</span>';
                    }

                    // Format Diterima (GRN Qty) & No GRN
                    var grnQtyHtml = '';
                    var itemGrnHtml = '';

                    if (item.grn_list && item.grn_list.length > 0) {
                        var qtyLines = [];
                        var grnLines = [];

                        $.each(item.grn_list, function (k, g) {
                            qtyLines.push('<div class="d-flex align-items-center justify-content-end mb-1" style="min-height: 26px;">' +
                                '<span class="text-success font-weight-bold" style="font-size: 0.88rem;">+' + g.grn_qty + '</span>' +
                                '</div>');

                            grnLines.push('<div class="d-flex align-items-center justify-content-center mb-1" style="min-height: 26px;">' +
                                '<span class="badge badge-success px-2 py-1 font-weight-bold shadow-xs" style="font-size: 0.78rem;">' +
                                '<i class="fas fa-receipt mr-1"></i>' + g.grn_number + '</span>' +
                                '</div>');
                        });

                        if (item.grn_list.length > 1) {
                            qtyLines.push('<div class="border-top pt-1 mt-1 text-right" title="Total Qty Diterima">' +
                                '<span class="badge badge-light border text-success font-weight-bold">Total: +' + item.grnqty + '</span></div>');
                            grnLines.push('<div class="border-top pt-1 mt-1 text-muted text-center font-weight-bold" style="font-size: 0.72rem; line-height: 20px;">' +
                                item.grn_list.length + ' Penerimaan</div>');
                        }

                        grnQtyHtml = qtyLines.join('');
                        itemGrnHtml = grnLines.join('');
                    } else {
                        var grnQtyNum = parseFloat(String(item.grnqty).replace(/\./g, '').replace(',', '.')) || 0;
                        grnQtyHtml = grnQtyNum > 0 
                            ? '<span class="text-success font-weight-bold">+' + item.grnqty + '</span>'
                            : '<span class="text-muted">0</span>';
                        itemGrnHtml = '<span class="text-muted">-</span>';
                    }

                    // Format Sisa Qty
                    var sisaQtyHtml = sisaQtyNum > 0 
                        ? '<span class="text-danger font-weight-bold">' + item.sisa_qty + '</span>' 
                        : '<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1 text-xs"></i>0</span>';

                    itemsHtml += '<tr>' +
                        '<td class="text-center align-middle font-weight-bold text-muted">' + (item.poseq || (idx + 1)) + '</td>' +
                        '<td class="align-middle"><code class="text-secondary font-weight-bold px-1 py-0 rounded" style="background:#f1f5f9; font-size:0.83rem;">' + item.poprodcode + '</code></td>' +
                        '<td class="align-middle font-weight-bold text-dark text-break" style="line-height: 1.35;">' + item.poprodname + '</td>' +
                        '<td class="align-middle text-secondary small" style="line-height: 1.3;">' +
                            (item.struct_name && item.struct_name !== '-'
                                ? '<span class="badge badge-light border text-secondary px-1" style="font-size:0.78rem; font-weight:600;">' +
                                  '<i class="fas fa-layer-group mr-1" style="opacity:0.6;"></i>' + item.struct_name + '</span>'
                                : '<span class="text-muted">-</span>') +
                        '</td>' +
                        '<td class="text-right align-middle font-weight-bold text-dark">' + item.poqty + '</td>' +
                        '<td class="text-right align-middle">' + grnQtyHtml + '</td>' +
                        '<td class="text-center align-middle">' + itemGrnHtml + '</td>' +
                        '<td class="text-right align-middle">' + sisaQtyHtml + '</td>' +
                        '<td class="text-center align-middle small">' + deliveryDateHtml + '</td>' +
                        '<td class="text-center align-middle">' + statusBadge + '</td>' +
                    '</tr>';
                });
            } else {
                itemsHtml = '<tr><td colspan="10" class="text-center py-3 text-muted">Tidak ada rincian item barang.</td></tr>';
            }

            // Update footer totals
            $('#modalFooterTotalItems').text(po.items.length + ' Item Barang');
            $('#modalFooterTotalPoQty').text(formatNumberId(totalPoQty));
            $('#modalFooterTotalGrnQty').text(formatNumberId(totalGrnQty));
            $('#modalFooterTotalSisaQty').text(formatNumberId(totalSisaQty));

            $('#modalItemsBody').html(itemsHtml);
            $('#modalDetailPo').modal('show');
        });

        // ─── Modal & Aksi Tambah Issue Follow Up PO ke Issues Purchasing ─────────
        function openModalIssueFollowUp(po, issueTitle) {
            $('#issueFollowUpPoNumber').val(po.ponmbr);
            $('#issueFollowUpName').val(issueTitle);
            $('#issueFollowUpBadgePo').text(po.ponmbr);
            $('#issueFollowUpBadgeVendor').text((po.povendorname || '-') + (po.povendorcode ? ' (' + po.povendorcode + ')' : ''));

            var d1Format = po.min_d1 ? po.min_d1 : '-';
            var d2Format = po.max_d2 ? po.max_d2 : '-';
            if (d1Format !== '-' && d1Format.indexOf('-') !== -1) {
                var p1 = d1Format.split('-');
                d1Format = p1[2] + '/' + p1[1] + '/' + p1[0];
            }
            if (d2Format !== '-' && d2Format.indexOf('-') !== -1) {
                var p2 = d2Format.split('-');
                d2Format = p2[2] + '/' + p2[1] + '/' + p2[0];
            }
            var deliveryRange = (d1Format === d2Format) ? d1Format : (d1Format + ' s/d ' + d2Format);

            var descTemplate = 'Vendor: ' + (po.povendorname || '-') + '\n' +
                               'Tanggal PO: ' + (po.podate || '-') + '\n' +
                               'Jadwal Delivery: ' + deliveryRange + '\n' +
                               'Status Delivery: ' + (po.status_label || '-') + '\n' +
                               'Jumlah Item: ' + (po.items ? po.items.length : 0) + ' Item\n' +
                               '--------------------------------------------------\n' +
                               'Catatan Follow Up:\n';

            $('#issueFollowUpDescription').val(descTemplate);
            $('#modalAddIssueFollowUp').modal('show');
        }

        $(document).on('click', '.btn-add-issue', function () {
            var btn = $(this);
            var pohdid = btn.data('pohdid');
            var po = poDataLookup[pohdid];

            if (!po) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Data PO tidak ditemukan.'
                });
                return;
            }

            var noPo = po.ponmbr;
            var issueTitle = 'Follow Up Pengiriman PO ' + noPo;
            var originalBtnHtml = btn.html();

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            // Cek apakah nomor PO ini sudah pernah dibuatkan issue hari ini
            $.ajax({
                url: 'remainder_po_to_issue.php?action=check_today',
                type: 'GET',
                data: { ponmbr: noPo },
                dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html(originalBtnHtml);
                    if (res && res.exists) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan Follow Up',
                            html: 'PO <b>' + noPo + '</b> sudah pernah dibuatkan Issue Follow Up sebanyak <b>' + res.count + 'x</b> pada hari ini.<br><br>Apakah Anda ingin tetap menambahkan issue follow-up baru?',
                            showCancelButton: true,
                            confirmButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<span class="text-dark font-weight-bold"><i class="fas fa-plus-circle mr-1"></i> Ya, Tetap Tambahkan</span>',
                            cancelButtonText: '<i class="fas fa-times mr-1"></i> Batal'
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                openModalIssueFollowUp(po, issueTitle);
                            }
                        });
                    } else {
                        openModalIssueFollowUp(po, issueTitle);
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html(originalBtnHtml);
                    // Jika gagal cek koneksi, tetap izinkan user membuka modal
                    openModalIssueFollowUp(po, issueTitle);
                }
            });
        });

        $('#btnSubmitIssueFollowUp').on('click', function () {
            var btn = $(this);
            var noPo = $('#issueFollowUpPoNumber').val();
            var description = $('#issueFollowUpDescription').val();

            if (!noPo) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Nomor PO tidak valid.'
                });
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

            $.ajax({
                url: 'remainder_po_to_issue.php?action=add',
                type: 'POST',
                data: {
                    ponmbr: noPo,
                    description: description
                },
                dataType: 'json',
                success: function (res) {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan ke Issue');
                    if (res && res.success) {
                        $('#modalAddIssueFollowUp').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Ditambahkan!',
                            html: res.message + '<br><small class="text-muted">ID Issue: #' + res.issue_id + ' | Nama: ' + res.issue_name + '</small>',
                            confirmButtonText: '<i class="fas fa-check mr-1"></i> OK'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: (res && res.message) ? res.message : 'Gagal menyimpan issue follow-up.'
                        });
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan ke Issue');
                    var errMsg = 'Terjadi kesalahan saat menyimpan data ke server.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json && json.message) {
                            errMsg = json.message;
                        }
                    } catch (e) {}
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: errMsg
                    });
                }
            });
        });
    });
</script>
