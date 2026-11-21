<?php
/**
 * Backend Handler PR Online (Purchase Request Online)
 * Mengintegrasikan data ERP PostgreSQL (koneksi3.php) ke SQL Server GG (koneksi.php)
 * Mendukung status pengerjaan, penugasan (assign) PIC, dan deteksi otomatis No PO dari ERP
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/koneksi3.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

// Cek autentikasi sesi login
if (empty($_SESSION['UserName'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi login tidak valid atau telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

$currentUserName   = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? $currentUserName);
$currentGroupId    = (int)($_SESSION['GroupId'] ?? 0);
$action            = trim($_GET['action'] ?? $_POST['action'] ?? '');

/**
 * Helper function untuk sinkronisasi No PO dari ERP PostgreSQL ke SQL Server
 */
function syncPoFromErp($conn, $conn3, $specificPphdids = []) {
    $pphdids = $specificPphdids;
    if (empty($pphdids)) {
        // Ambil pphdid dari PR yang belum selesai atau belum memiliki no_po
        $sql = "SELECT pphdid FROM dbo.pr_online_header WHERE status_pengerjaan != 'Selesai' OR no_po IS NULL OR no_po = ''";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $pphdids[] = (int)$r['pphdid'];
            }
        }
    }

    if (empty($pphdids)) {
        return 0;
    }

    $pphdids = array_unique(array_filter($pphdids));
    if (empty($pphdids)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($pphdids), '?'));
    $sqlPg = "SELECT DISTINCT 
                d.popphdid, 
                p.ponmbr, 
                p.podate 
              FROM prpodt d 
              JOIN prpohd p ON d.pohdid = p.pohdid 
              WHERE d.popphdid IN ($placeholders) 
                AND p.ponmbr IS NOT NULL 
                AND TRIM(p.ponmbr) != ''";

    $stmtPg = $conn3->prepare($sqlPg);
    $stmtPg->execute(array_values($pphdids));
    $foundPos = $stmtPg->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;
    foreach ($foundPos as $po) {
        $poPphdid = (int)$po['popphdid'];
        $poNmbr   = trim($po['ponmbr']);
        $poDate   = !empty($po['podate']) ? substr($po['podate'], 0, 10) : null;

        $updateSql = "UPDATE dbo.pr_online_header 
                      SET no_po = ?, po_date = ?, status_pengerjaan = 'Selesai' 
                      WHERE pphdid = ? AND (no_po IS NULL OR no_po = '' OR status_pengerjaan != 'Selesai')";
        $updateStmt = sqlsrv_query($conn, $updateSql, [$poNmbr, $poDate, $poPphdid]);
        if ($updateStmt !== false && sqlsrv_rows_affected($updateStmt) > 0) {
            $updatedCount++;
        }
    }

    return $updatedCount;
}

try {
    // -------------------------------------------------------------------------
    // ACTION 1: Mengambil PR dari PostgreSQL (Hanya status 'V')
    // -------------------------------------------------------------------------
    if ($action === 'get_available_pr') {
        // Ambil hitungan item yang sudah tersimpan di SQL Server per pphdid
        $savedItemCounts = [];
        $sqlSaved = "SELECT pphdid, COUNT(id) AS saved_count FROM dbo.pr_online_detail GROUP BY pphdid";
        $stmtSaved = sqlsrv_query($conn, $sqlSaved);
        if ($stmtSaved !== false) {
            while ($r = sqlsrv_fetch_array($stmtSaved, SQLSRV_FETCH_ASSOC)) {
                $savedItemCounts[(int)$r['pphdid']] = (int)$r['saved_count'];
            }
        }

        // Ambil data PR dari PostgreSQL prpphd hanya dengan status 'V' (Approve)
        $sqlPg = "SELECT 
                    h.pphdid, 
                    h.ppnmbr, 
                    h.ppdate, 
                    h.ppdesc, 
                    h.fgstatus, 
                    h.createdby,
                    h.ppprorgid,
                    o.prorgname,
                    COUNT(d.ppdtid) AS total_items
                  FROM prpphd h
                  LEFT JOIN prprorg o ON h.ppprorgid = o.prorgid
                  LEFT JOIN prppdt d ON h.pphdid = d.pphdid
                  WHERE h.fgstatus = 'V'
                  GROUP BY 
                    h.pphdid, 
                    h.ppnmbr, 
                    h.ppdate, 
                    h.ppdesc, 
                    h.fgstatus, 
                    h.createdby,
                    h.ppprorgid,
                    o.prorgname
                  ORDER BY h.ppdate DESC, h.ppnmbr DESC";

        $stmtPg = $conn3->query($sqlPg);
        $pgRows = $stmtPg->fetchAll(PDO::FETCH_ASSOC);

        $availablePrs = [];
        $no = 1;
        foreach ($pgRows as $row) {
            $pphdid = (int)$row['pphdid'];
            $ppnmbr = trim($row['ppnmbr'] ?? '');
            $totalItems = (int)($row['total_items'] ?? 0);
            $savedCount = $savedItemCounts[$pphdid] ?? 0;
            $remainingItems = $totalItems - $savedCount;

            // Jika semua item produk sudah pernah diimpor, lewati PR ini
            if ($totalItems > 0 && $remainingItems <= 0) {
                continue;
            }

            $tglFormatted = !empty($row['ppdate']) ? date('d/m/Y', strtotime($row['ppdate'])) : '-';
            $statusLabel = 'Approve';
            $statusBadge = 'badge-success';

            $itemInfoBadge = $savedCount > 0
                ? "<span class=\"badge badge-info\">Sisa {$remainingItems} dari {$totalItems} Item</span>"
                : "<span class=\"badge badge-secondary\">{$totalItems} Item</span>";

            $availablePrs[] = [
                'no'              => $no++,
                'pphdid'          => $pphdid,
                'ppnmbr'          => $ppnmbr,
                'ppdate'          => $tglFormatted,
                'ppdesc'          => trim($row['ppdesc'] ?? '-'),
                'fgstatus'        => $row['fgstatus'],
                'status_badge'    => "<span class=\"badge {$statusBadge}\">{$statusLabel}</span>",
                'prorgname'       => trim($row['prorgname'] ?? '-'),
                'createdby'       => trim($row['createdby'] ?? '-'),
                'total_items'     => $totalItems,
                'remaining_items' => $remainingItems,
                'items_badge'     => $itemInfoBadge
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'data'   => $availablePrs,
            'total'  => count($availablePrs)
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 2: Mengambil rincian produk untuk PR terpilih di Step 2 Wizard
    // -------------------------------------------------------------------------
    if ($action === 'get_pr_products') {
        $ppnmbrs = $_POST['ppnmbrs'] ?? $_GET['ppnmbrs'] ?? [];
        if (!is_array($ppnmbrs) || empty($ppnmbrs)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Silakan pilih minimal satu Purchase Request terlebih dahulu.'
            ]);
            exit;
        }

        // Ambil daftar ppdtid yang sudah ada di SQL Server
        $savedDtIds = [];
        $stmtSavedDt = sqlsrv_query($conn, "SELECT ppdtid FROM dbo.pr_online_detail");
        if ($stmtSavedDt !== false) {
            while ($r = sqlsrv_fetch_array($stmtSavedDt, SQLSRV_FETCH_ASSOC)) {
                $savedDtIds[(int)$r['ppdtid']] = true;
            }
        }

        $stmtHeader = $conn3->prepare("
            SELECT 
                h.pphdid, 
                h.ppnmbr, 
                h.ppdate, 
                h.ppdesc, 
                h.fgstatus, 
                o.prorgname
            FROM prpphd h
            LEFT JOIN prprorg o ON h.ppprorgid = o.prorgid
            WHERE h.ppnmbr = :ppnmbr
            LIMIT 1
        ");

        $stmtDetails = $conn3->prepare("
            SELECT 
                d.ppdtid,
                d.pphdid,
                d.ppdtseq,
                d.ppprodid,
                d.ppprodcode,
                d.ppprodname,
                d.ppproddesc,
                d.ppqty,
                d.ppuomid,
                u.uomname,
                d.ppprice,
                d.pptotalprice
            FROM prppdt d
            LEFT JOIN smuom u ON d.ppuomid = u.uomid
            WHERE d.pphdid = :pphdid
            ORDER BY d.ppdtseq ASC, d.ppdtid ASC
        ");

        $groups = [];
        $totalAvailableProducts = 0;

        foreach ($ppnmbrs as $ppnmbr) {
            $ppnmbr = trim($ppnmbr);
            if ($ppnmbr === '') continue;

            $stmtHeader->execute([':ppnmbr' => $ppnmbr]);
            $hdr = $stmtHeader->fetch(PDO::FETCH_ASSOC);
            if (!$hdr) continue;

            $pphdid = (int)$hdr['pphdid'];
            $stmtDetails->execute([':pphdid' => $pphdid]);
            $rawDetails = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            $availableItems = [];
            $itemNo = 1;
            foreach ($rawDetails as $dt) {
                $ppdtid = (int)$dt['ppdtid'];
                if (isset($savedDtIds[$ppdtid])) {
                    continue;
                }

                $qty = (float)($dt['ppqty'] ?? 0);
                $qtyFormatted = (floor($qty) == $qty) ? number_format($qty, 0, ',', '.') : number_format($qty, 2, ',', '.');
                $price = (float)($dt['ppprice'] ?? 0);
                $totalPrice = (float)($dt['pptotalprice'] ?? 0);

                $availableItems[] = [
                    'no'          => $itemNo++,
                    'ppdtid'      => $ppdtid,
                    'ppdtseq'     => $dt['ppdtseq'] !== null ? (int)$dt['ppdtseq'] : null,
                    'ppprodid'    => $dt['ppprodid'] !== null ? (int)$dt['ppprodid'] : null,
                    'ppprodcode'  => trim($dt['ppprodcode'] ?? '-'),
                    'ppprodname'  => trim($dt['ppprodname'] ?? '-'),
                    'ppproddesc'  => trim($dt['ppproddesc'] ?? '-'),
                    'ppqty'       => $qtyFormatted,
                    'uomname'     => trim($dt['uomname'] ?? '-'),
                    'ppprice'     => $price > 0 ? 'Rp ' . number_format($price, 0, ',', '.') : '-',
                    'pptotalprice'=> $totalPrice > 0 ? 'Rp ' . number_format($totalPrice, 0, ',', '.') : '-'
                ];
            }

            if (!empty($availableItems)) {
                $groups[] = [
                    'pphdid'     => $pphdid,
                    'ppnmbr'     => $hdr['ppnmbr'],
                    'ppdate'     => !empty($hdr['ppdate']) ? date('d/m/Y', strtotime($hdr['ppdate'])) : '-',
                    'prorgname'  => trim($hdr['prorgname'] ?? '-'),
                    'ppdesc'     => trim($hdr['ppdesc'] ?? '-'),
                    'items'      => $availableItems,
                    'item_count' => count($availableItems)
                ];
                $totalAvailableProducts += count($availableItems);
            }
        }

        echo json_encode([
            'status'                 => 'ok',
            'data'                   => $groups,
            'total_groups'           => count($groups),
            'total_products'         => $totalAvailableProducts
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 3: Simpan produk terpilih ke SQL Server
    // -------------------------------------------------------------------------
    if ($action === 'save_selected_products') {
        $selectedItems = $_POST['selected_items'] ?? [];
        if (!is_array($selectedItems) || empty($selectedItems)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Tidak ada item produk yang dipilih untuk disimpan.'
            ]);
            exit;
        }

        $itemsByPr = [];
        foreach ($selectedItems as $item) {
            $ppnmbr = trim($item['ppnmbr'] ?? '');
            $ppdtid = isset($item['ppdtid']) ? (int)$item['ppdtid'] : 0;
            if ($ppnmbr !== '' && $ppdtid > 0) {
                $itemsByPr[$ppnmbr][] = $ppdtid;
            }
        }

        if (empty($itemsByPr)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Format data produk yang dipilih tidak valid.'
            ]);
            exit;
        }

        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception("Gagal memulai transaksi database: " . print_r(sqlsrv_errors(), true));
        }

        $stmtPgHeader = $conn3->prepare("
            SELECT 
                h.pphdid, 
                h.ppnmbr, 
                h.ppdate, 
                h.ppdesc, 
                h.fgstatus, 
                h.ppprorgid,
                o.prorgname
            FROM prpphd h
            LEFT JOIN prprorg o ON h.ppprorgid = o.prorgid
            WHERE h.ppnmbr = :ppnmbr
            LIMIT 1
        ");

        $stmtPgSingleDetail = $conn3->prepare("
            SELECT 
                d.ppdtid,
                d.pphdid,
                d.ppdtseq,
                d.ppprodid,
                d.ppprodcode,
                d.ppprodname,
                d.ppproddesc,
                d.ppqty,
                d.ppuomid,
                u.uomname,
                d.ppprice,
                d.pptotalprice
            FROM prppdt d
            LEFT JOIN smuom u ON d.ppuomid = u.uomid
            WHERE d.ppdtid = :ppdtid
            LIMIT 1
        ");

        $totalSavedProducts = 0;
        $insertedPphdids = [];

        foreach ($itemsByPr as $ppnmbr => $dtIds) {
            $checkHdrSql = "SELECT id, pphdid FROM dbo.pr_online_header WHERE ppnmbr = ?";
            $checkHdrStmt = sqlsrv_query($conn, $checkHdrSql, [$ppnmbr]);
            $existingHdr = $checkHdrStmt ? sqlsrv_fetch_array($checkHdrStmt, SQLSRV_FETCH_ASSOC) : null;

            if ($existingHdr) {
                $headerId = (int)$existingHdr['id'];
                $pphdid   = (int)$existingHdr['pphdid'];
            } else {
                $stmtPgHeader->execute([':ppnmbr' => $ppnmbr]);
                $header = $stmtPgHeader->fetch(PDO::FETCH_ASSOC);
                if (!$header) continue;

                $pphdid = (int)$header['pphdid'];
                $insertedPphdids[] = $pphdid;

                $insertHdrSql = "INSERT INTO dbo.pr_online_header 
                    (pphdid, ppnmbr, ppdate, ppdesc, fgstatus, ppprorgid, prorgname, created_by, created_at, status_po, status_pengerjaan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), 'Pending', 'Belum Dikerjakan');
                    SELECT SCOPE_IDENTITY() AS id;";

                $hdrParams = [
                    $pphdid,
                    $header['ppnmbr'],
                    !empty($header['ppdate']) ? substr($header['ppdate'], 0, 10) : null,
                    $header['ppdesc'] ?? '',
                    $header['fgstatus'] ?? 'V',
                    $header['ppprorgid'] ? (int)$header['ppprorgid'] : null,
                    $header['prorgname'] ?? '',
                    $currentUserName
                ];

                $stmtInsertHdr = sqlsrv_query($conn, $insertHdrSql, $hdrParams);
                if ($stmtInsertHdr === false) {
                    sqlsrv_rollback($conn);
                    throw new Exception("Gagal menyimpan header PR {$ppnmbr}: " . print_r(sqlsrv_errors(), true));
                }

                sqlsrv_next_result($stmtInsertHdr);
                $rowId = sqlsrv_fetch_array($stmtInsertHdr, SQLSRV_FETCH_ASSOC);
                $headerId = (int)($rowId['id'] ?? 0);
                if ($headerId <= 0) {
                    sqlsrv_rollback($conn);
                    throw new Exception("Gagal mendapatkan ID Header untuk PR {$ppnmbr}.");
                }
            }

            $insertDtSql = "INSERT INTO dbo.pr_online_detail
                (header_id, pphdid, ppdtid, ppdtseq, ppprodid, ppprodcode, ppprodname, ppproddesc, ppqty, ppuomid, uomname, ppprice, pptotalprice, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

            foreach ($dtIds as $ppdtid) {
                $checkDtSql = "SELECT id FROM dbo.pr_online_detail WHERE ppdtid = ?";
                $checkDtStmt = sqlsrv_query($conn, $checkDtSql, [$ppdtid]);
                if ($checkDtStmt && sqlsrv_fetch_array($checkDtStmt, SQLSRV_FETCH_ASSOC)) {
                    continue;
                }

                $stmtPgSingleDetail->execute([':ppdtid' => $ppdtid]);
                $d = $stmtPgSingleDetail->fetch(PDO::FETCH_ASSOC);
                if (!$d) continue;

                $dtParams = [
                    $headerId,
                    $pphdid,
                    (int)$d['ppdtid'],
                    $d['ppdtseq'] !== null ? (int)$d['ppdtseq'] : null,
                    $d['ppprodid'] !== null ? (int)$d['ppprodid'] : null,
                    $d['ppprodcode'] ?? '',
                    $d['ppprodname'] ?? '',
                    $d['ppproddesc'] ?? '',
                    $d['ppqty'] !== null ? (float)$d['ppqty'] : 0,
                    $d['ppuomid'] !== null ? (int)$d['ppuomid'] : null,
                    $d['uomname'] ?? '',
                    $d['ppprice'] !== null ? (float)$d['ppprice'] : 0,
                    $d['pptotalprice'] !== null ? (float)$d['pptotalprice'] : 0
                ];

                $stmtInsertDt = sqlsrv_query($conn, $insertDtSql, $dtParams);
                if ($stmtInsertDt === false) {
                    sqlsrv_rollback($conn);
                    throw new Exception("Gagal menyimpan item produk (ID: {$ppdtid}): " . print_r(sqlsrv_errors(), true));
                }

                $totalSavedProducts++;
            }
        }

        sqlsrv_commit($conn);

        // Sinkronisasi otomatis No PO dari ERP jika sudah terbentuk
        if (!empty($insertedPphdids)) {
            syncPoFromErp($conn, $conn3, $insertedPphdids);
        }

        echo json_encode([
            'status'          => 'success',
            'message'         => "Berhasil menyimpan {$totalSavedProducts} produk dari " . count($itemsByPr) . " Purchase Request ke PR Online.",
            'total_products'  => $totalSavedProducts,
            'total_prs'       => count($itemsByPr)
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 4: Ambil rincian detail produk dari suatu PR Online
    // -------------------------------------------------------------------------
    if ($action === 'get_pr_detail') {
        $headerId = isset($_GET['header_id']) ? (int)$_GET['header_id'] : 0;
        if ($headerId <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parameter header_id tidak valid.'
            ]);
            exit;
        }

        // Ambil header
        $sqlHdr = "SELECT * FROM dbo.pr_online_header WHERE id = ?";
        $stmtHdr = sqlsrv_query($conn, $sqlHdr, [$headerId]);
        if (!$stmtHdr || !($header = sqlsrv_fetch_array($stmtHdr, SQLSRV_FETCH_ASSOC))) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data PR Online tidak ditemukan di database.'
            ]);
            exit;
        }

        // Cek sinkronisasi No PO ERP terbaru untuk PR ini
        if (!empty($header['pphdid']) && (empty($header['no_po']) || $header['status_pengerjaan'] !== 'Selesai')) {
            syncPoFromErp($conn, $conn3, [(int)$header['pphdid']]);
            // Refresh data header
            $stmtHdr2 = sqlsrv_query($conn, $sqlHdr, [$headerId]);
            if ($stmtHdr2 && ($hdrRefreshed = sqlsrv_fetch_array($stmtHdr2, SQLSRV_FETCH_ASSOC))) {
                $header = $hdrRefreshed;
            }
        }

        if (!empty($header['ppdate'])) {
            $header['ppdate_formatted'] = $header['ppdate'] instanceof DateTime 
                ? $header['ppdate']->format('d/m/Y') 
                : date('d/m/Y', strtotime($header['ppdate']));
        } else {
            $header['ppdate_formatted'] = '-';
        }

        if (!empty($header['created_at'])) {
            $header['created_at_formatted'] = $header['created_at'] instanceof DateTime 
                ? $header['created_at']->format('d/m/Y H:i') 
                : date('d/m/Y H:i', strtotime($header['created_at']));
        } else {
            $header['created_at_formatted'] = '-';
        }

        if (!empty($header['assigned_at'])) {
            $header['assigned_at_formatted'] = $header['assigned_at'] instanceof DateTime 
                ? $header['assigned_at']->format('d/m/Y H:i') 
                : date('d/m/Y H:i', strtotime($header['assigned_at']));
        } else {
            $header['assigned_at_formatted'] = '-';
        }

        if (!empty($header['po_date'])) {
            $header['po_date_formatted'] = $header['po_date'] instanceof DateTime 
                ? $header['po_date']->format('d/m/Y') 
                : date('d/m/Y', strtotime($header['po_date']));
        } else {
            $header['po_date_formatted'] = '-';
        }

        $header['status_pengerjaan'] = trim($header['status_pengerjaan'] ?? 'Belum Dikerjakan');
        $header['pic_name'] = trim($header['pic_name'] ?? '');
        $header['no_po'] = trim($header['no_po'] ?? '');

        // Ambil details
        $sqlDt = "SELECT * FROM dbo.pr_online_detail WHERE header_id = ? ORDER BY ppdtseq ASC, id ASC";
        $stmtDt = sqlsrv_query($conn, $sqlDt, [$headerId]);
        $details = [];
        $no = 1;
        if ($stmtDt !== false) {
            while ($row = sqlsrv_fetch_array($stmtDt, SQLSRV_FETCH_ASSOC)) {
                $qty = (float)($row['ppqty'] ?? 0);
                $qtyFormatted = (floor($qty) == $qty) ? number_format($qty, 0, ',', '.') : number_format($qty, 2, ',', '.');
                $price = (float)($row['ppprice'] ?? 0);
                $totalPrice = (float)($row['pptotalprice'] ?? 0);

                $details[] = [
                    'id'          => (int)$row['id'],
                    'no'          => $no++,
                    'ppdtid'      => (int)$row['ppdtid'],
                    'ppprodcode'  => trim($row['ppprodcode'] ?? '-'),
                    'ppprodname'  => trim($row['ppprodname'] ?? '-'),
                    'ppproddesc'  => trim($row['ppproddesc'] ?? '-'),
                    'ppqty'       => $qtyFormatted,
                    'uomname'     => trim($row['uomname'] ?? '-'),
                    'ppprice'     => $price > 0 ? 'Rp ' . number_format($price, 0, ',', '.') : '-',
                    'pptotalprice'=> $totalPrice > 0 ? 'Rp ' . number_format($totalPrice, 0, ',', '.') : '-'
                ];
            }
        }

        echo json_encode([
            'status'      => 'success',
            'header'      => $header,
            'details'     => $details,
            'total_items' => count($details)
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 5: Assign PIC (Ambil PR)
    // -------------------------------------------------------------------------
    if ($action === 'assign_pr') {
        $headerId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($headerId <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parameter ID PR tidak valid.'
            ]);
            exit;
        }

        // Cek data saat ini
        $checkSql = "SELECT ppnmbr, status_pengerjaan, pic_name, no_po FROM dbo.pr_online_header WHERE id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$headerId]);
        $pr = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if (!$pr) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data PR tidak ditemukan.'
            ]);
            exit;
        }

        if (trim($pr['status_pengerjaan'] ?? '') === 'Selesai' || !empty($pr['no_po'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'PR ini sudah berstatus Selesai (sudah memiliki No PO).'
            ]);
            exit;
        }

        // Lakukan Assign
        $updateSql = "UPDATE dbo.pr_online_header 
                      SET status_pengerjaan = 'Sedang Dikerjakan', 
                          pic_name = ?, 
                          pic_username = ?, 
                          assigned_at = GETDATE() 
                      WHERE id = ?";
        $updateParams = [$currentNamaLengkap, $currentUserName, $headerId];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal meng-assign PR: ' . print_r(sqlsrv_errors(), true)
            ]);
            exit;
        }

        echo json_encode([
            'status'   => 'success',
            'message'  => "PR {$pr['ppnmbr']} berhasil diambil dan ditugaskan kepada {$currentNamaLengkap}.",
            'pic_name' => $currentNamaLengkap
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 6: Unassign PIC (Batal Ambil / Lepas Tugas)
    // -------------------------------------------------------------------------
    if ($action === 'unassign_pr') {
        $headerId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($headerId <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parameter ID PR tidak valid.'
            ]);
            exit;
        }

        $checkSql = "SELECT ppnmbr, status_pengerjaan, pic_username, no_po FROM dbo.pr_online_header WHERE id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$headerId]);
        $pr = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if (!$pr) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data PR tidak ditemukan.'
            ]);
            exit;
        }

        if (trim($pr['status_pengerjaan'] ?? '') === 'Selesai' || !empty($pr['no_po'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'PR ini sudah berstatus Selesai dan tidak dapat dibatalkan penugasannya.'
            ]);
            exit;
        }

        // Validasi hak: hanya PIC yang bersangkutan atau Admin (GroupId 1)
        $picUser = trim($pr['pic_username'] ?? '');
        if ($currentGroupId !== 1 && strcasecmp($picUser, $currentUserName) !== 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Anda tidak memiliki wewenang untuk melepas penugasan PIC lain.'
            ]);
            exit;
        }

        $updateSql = "UPDATE dbo.pr_online_header 
                      SET status_pengerjaan = 'Belum Dikerjakan', 
                          pic_name = NULL, 
                          pic_username = NULL, 
                          assigned_at = NULL 
                      WHERE id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, [$headerId]);

        if ($updateStmt === false) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal membatalkan penugasan: ' . print_r(sqlsrv_errors(), true)
            ]);
            exit;
        }

        echo json_encode([
            'status'  => 'success',
            'message' => "Penugasan PR {$pr['ppnmbr']} berhasil dibatalkan. Status kembali 'Belum Dikerjakan'."
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 7: Sinkronisasi Manual No PO dari ERP
    // -------------------------------------------------------------------------
    if ($action === 'sync_po_erp') {
        $updatedCount = syncPoFromErp($conn, $conn3);
        echo json_encode([
            'status'        => 'success',
            'message'       => "Sinkronisasi selesai. {$updatedCount} data PR telah diperbarui dengan No PO dari ERP.",
            'updated_count' => $updatedCount
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 8: Hapus satu item produk dari PR Online
    // -------------------------------------------------------------------------
    if ($action === 'delete_single_item') {
        $detailId = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
        if ($detailId <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parameter detail_id tidak valid.'
            ]);
            exit;
        }

        $checkSql = "SELECT header_id, ppprodname FROM dbo.pr_online_detail WHERE id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$detailId]);
        $detailRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if (!$detailRow) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Item produk tidak ditemukan atau sudah dihapus.'
            ]);
            exit;
        }

        $headerId = (int)$detailRow['header_id'];
        $prodName = trim($detailRow['ppprodname'] ?? 'Item');

        $delSql = "DELETE FROM dbo.pr_online_detail WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$detailId]);
        if ($delStmt === false) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menghapus item produk: ' . print_r(sqlsrv_errors(), true)
            ]);
            exit;
        }

        $countSql = "SELECT COUNT(*) AS remaining_count FROM dbo.pr_online_detail WHERE header_id = ?";
        $countStmt = sqlsrv_query($conn, $countSql, [$headerId]);
        $countRow = $countStmt ? sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC) : ['remaining_count' => 0];
        $remainingCount = (int)($countRow['remaining_count'] ?? 0);

        $headerDeleted = false;
        if ($remainingCount <= 0) {
            sqlsrv_query($conn, "DELETE FROM dbo.pr_online_header WHERE id = ?", [$headerId]);
            $headerDeleted = true;
        }

        echo json_encode([
            'status'          => 'success',
            'message'         => "Produk '{$prodName}' berhasil dihapus dari PR Online." .
                                 ($headerDeleted ? " Karena tidak ada item produk tersisa, PR ini telah dibersihkan dan dapat dipilih kembali." : ""),
            'remaining_count' => $remainingCount,
            'header_deleted'  => $headerDeleted
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // ACTION 9: Hapus seluruh PR Online beserta seluruh itemnya
    // -------------------------------------------------------------------------
    if ($action === 'delete_pr') {
        $headerId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($headerId <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Parameter ID PR tidak valid.'
            ]);
            exit;
        }

        $checkSql = "SELECT ppnmbr FROM dbo.pr_online_header WHERE id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$headerId]);
        $prRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if (!$prRow) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data PR tidak ditemukan atau sudah dihapus.'
            ]);
            exit;
        }

        $ppnmbr = trim($prRow['ppnmbr']);

        $delSql = "DELETE FROM dbo.pr_online_header WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$headerId]);

        if ($delStmt === false) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menghapus PR Online: ' . print_r(sqlsrv_errors(), true)
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Purchase Request {$ppnmbr} berhasil dihapus dari daftar PR Online."
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Aksi tidak dikenal.'
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
    exit;
}
