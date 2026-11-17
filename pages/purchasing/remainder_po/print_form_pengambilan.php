<?php
/**
 * Halaman Cetak Form Pengambilan Barang ke Supplier
 * Format dokumen mengacu persis pada Form_Pengambilan_Barang_Supplier_POLD_2611_0001.pdf
 * Data dibaca secara dinamis dari ERP Crystal SUM (PostgreSQL) dan database tracking (SQL Server).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    die('Akses ditolak. Silakan login terlebih dahulu ke sistem.');
}

$pohdid = isset($_GET['pohdid']) ? (int)$_GET['pohdid'] : 0;
$ponmbr = isset($_GET['ponmbr']) ? trim($_GET['ponmbr']) : '';

if ($pohdid <= 0 && empty($ponmbr)) {
    die('Parameter pohdid atau ponmbr harus ditentukan.');
}

// Format angka desimal / integer tanpa trailing zero
function fmtQty($val) {
    $floatVal = (float) $val;
    if (floor($floatVal) == $floatVal) {
        return number_format($floatVal, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($floatVal, 4, ',', '.'), '0'), ',');
}

// 1. Ambil data Header PO & Detail Item dari ERP PostgreSQL
$whereClause = ($pohdid > 0) ? "h.pohdid = :param" : "h.ponmbr = :param";
$paramVal = ($pohdid > 0) ? $pohdid : $ponmbr;

$sql = "
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
        COALESCE(um.uomcode, um.uomname, '-') AS satuan
    FROM prpohd h
    JOIN prpodt d ON d.pohdid = h.pohdid
    LEFT JOIN msuser u ON TRIM(h.createdby) = TRIM(u.userid)
    LEFT JOIN smemployee e ON h.poinitiatorid = e.empid
    LEFT JOIN smuom um ON d.pouomid = um.uomid
    WHERE $whereClause
    ORDER BY d.poseq ASC
";

try {
    $stmt = $conn3->prepare($sql);
    $stmt->execute([':param' => $paramVal]);
    $itemRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Terjadi kesalahan saat membaca data PO: ' . htmlspecialchars($e->getMessage()));
}

if (empty($itemRows)) {
    die('Data Purchase Order tidak ditemukan.');
}

$first = $itemRows[0];
$pohdid = (int)$first['pohdid'];
$ponmbr = trim($first['ponmbr']);
$podate = !empty($first['podate']) ? date('d/m/Y', strtotime($first['podate'])) : '-';
$vendorCode = trim($first['povendorcode'] ?? '');
$vendorName = trim($first['povendorname'] ?? '-');
$vendorDisplay = $vendorName . ($vendorCode !== '' ? ' (' . $vendorCode . ')' : '');
$picPembuat = !empty($first['pic_pembuat']) ? trim($first['pic_pembuat']) : '-';

// 2. Evaluasi status delivery & overdue
$minD1 = null;
$maxD2 = null;
$totalPoQty = 0;
foreach ($itemRows as $row) {
    $d1 = !empty($row['podlvrdate1']) ? date('Y-m-d', strtotime($row['podlvrdate1'])) : null;
    $d2 = !empty($row['podlvrdate2']) ? date('Y-m-d', strtotime($row['podlvrdate2'])) : null;
    if ($d1 && (!$minD1 || $d1 < $minD1)) $minD1 = $d1;
    if ($d2 && (!$maxD2 || $d2 > $maxD2)) $maxD2 = $d2;
    $totalPoQty += (float)$row['poqty'];
}

$poStatus = strtoupper(trim($first['po_status'] ?? ''));
$todayStr = date('Y-m-d');
$maxDelivery = $maxD2 ?: $minD1;
$isOverdue = ($maxDelivery && $maxDelivery < $todayStr && $poStatus === 'V');

if ($poStatus === 'X') {
    $statusLabel = 'Selesai';
} elseif ($poStatus === 'U') {
    $statusLabel = 'Parsial';
} elseif ($poStatus === 'V') {
    $statusLabel = 'Belum Datang';
} else {
    $statusLabel = $poStatus ?: '-';
}

$statusDisplay = $statusLabel;
if ($isOverdue) {
    $statusDisplay .= ' / Overdue';
}

// 3. Ambil data Status Pembayaran & No. Cash Payment (CP)
$sqlPay = "
    SELECT 
        STRING_AGG(DISTINCT u.cpnmbr || '~' || COALESCE(TO_CHAR(u.cpdate, 'YYYY-MM-DD'), ''), '|') AS cp_details,
        STRING_AGG(DISTINCT u.cpnmbr, ', ') AS cp_numbers
    FROM (
        SELECT gd.pohdid, cp.cpnmbr, cp.cpdate
        FROM prgrndt gd
        JOIN apposting ap ON ap.grnhdid = gd.grnhdid
        JOIN cmcpdt cpd ON cpd.apid = ap.apid
        JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
        WHERE gd.pohdid = :pohdid AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> '' AND TRIM(cp.fgstatus) = 'V'
        UNION
        SELECT t.pohdid, cp.cpnmbr, cp.cpdate
        FROM cmtttap t
        JOIN cmcphd cp ON cp.cphdid = t.cphdid
        WHERE t.pohdid = :pohdid AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> '' AND TRIM(cp.fgstatus) = 'V'
        UNION
        SELECT sdt.pohdid, cp.cpnmbr, cp.cpdate
        FROM pasettlementdt sdt
        JOIN pasettlementhd sh ON sh.settleid = sdt.settleid
        JOIN cmrequesthd req ON req.reqhdid = sh.rfphdid
        JOIN cmcpdt cpd ON cpd.cphdid = req.cphdid
        JOIN cmcphd cp ON cp.cphdid = cpd.cphdid
        WHERE sdt.pohdid = :pohdid AND cp.cpnmbr IS NOT NULL AND TRIM(cp.cpnmbr) <> '' AND TRIM(cp.fgstatus) = 'V'
    ) u
";

$paymentLabel = 'Belum Dibayar';
$cpDisplay = '-';

try {
    $stmtPay = $conn3->prepare($sqlPay);
    $stmtPay->execute([':pohdid' => $pohdid]);
    $payRow = $stmtPay->fetch(PDO::FETCH_ASSOC);

    if (!empty($payRow['cp_numbers'])) {
        $paymentLabel = 'Sudah Dibayar';
        $cpNumbers = trim($payRow['cp_numbers']);
        
        $cpDates = [];
        if (!empty($payRow['cp_details'])) {
            foreach (explode('|', $payRow['cp_details']) as $part) {
                $sub = explode('~', $part);
                if (!empty($sub[1])) {
                    $cpDates[] = date('d/m/Y', strtotime($sub[1]));
                }
            }
        }
        $cpDateStr = !empty($cpDates) ? implode(', ', array_unique($cpDates)) : '';
        if (!empty($cpDateStr)) {
            $cpDisplay = $cpDateStr . ' / ' . $cpNumbers;
        } else {
            $cpDisplay = $cpNumbers;
        }
    }
} catch (Exception $e) {
    // Abaikan jika query pembayaran gagal, default ke Belum Dibayar
}

// 4. Ambil data Tujuan Pengiriman & Alamat Kirim dari database tracking (SQL Server)
$dikirimKe = '-';
$alamatKirim = '-';

if (isset($conn) && $conn) {
    $sqlTrack = "
        SELECT TOP 1 d.ponmbr, d.dikirim_ke, d.alamat_kirim, d.status
        FROM dbo.tracking_po_detail d
        INNER JOIN dbo.tracking_po_header h ON d.header_id = h.id
        WHERE h.is_deleted = 0 AND d.ponmbr = ?
        ORDER BY d.id DESC
    ";
    $stmtTrack = sqlsrv_query($conn, $sqlTrack, [$ponmbr]);
    if ($stmtTrack && ($trackInfo = sqlsrv_fetch_array($stmtTrack, SQLSRV_FETCH_ASSOC))) {
        if (!empty($trackInfo['dikirim_ke'])) $dikirimKe = trim($trackInfo['dikirim_ke']);
        if (!empty($trackInfo['alamat_kirim'])) $alamatKirim = trim($trackInfo['alamat_kirim']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pengambilan Barang - <?= htmlspecialchars($ponmbr) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* ─── RESET & BASE STYLES ─────────────────────────────────────────── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ─── ACTION TOOLBAR (SCREEN ONLY) ────────────────────────────────── */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 9999;
        }
        .toolbar-btn {
            background-color: #2563eb;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .toolbar-btn:hover {
            background-color: #1d4ed8;
        }
        .toolbar-btn-secondary {
            background-color: #64748b;
        }
        .toolbar-btn-secondary:hover {
            background-color: #475569;
        }

        /* ─── PAGE CONTAINER (A4 DIMENSIONS) ─────────────────────────────── */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 15px auto;
            background: #ffffff;
            padding: 12mm 15mm 10mm 15mm;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* ─── HEADER SECTION ─────────────────────────────────────────────── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .doc-header-left {
            flex: 1;
        }
        .doc-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .doc-subtitle {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* Kotak Info Nomor PO di Pojok Kanan Atas */
        .po-box {
            border: 1.5px solid #2563eb;
            border-radius: 6px;
            padding: 6px 12px;
            background-color: #f8fafc;
            min-width: 210px;
            text-align: left;
        }
        .po-box-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 10.5px;
        }
        .po-box-row:last-child {
            margin-bottom: 0;
        }
        .po-box-label {
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
        }
        .po-box-value {
            font-weight: 800;
            color: #0f172a;
            text-align: right;
        }
        .po-box-number {
            font-size: 13px;
            color: #1e40af;
            letter-spacing: 0.3px;
        }
        .po-status-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            color: #b91c1c;
            background-color: #fee2e2;
            padding: 1px 6px;
            border-radius: 3px;
        }

        /* ─── SECTION TITLES ─────────────────────────────────────────────── */
        .section-header {
            font-size: 11.5px;
            font-weight: 800;
            color: #1e3a8a;
            background-color: #f1f5f9;
            border-left: 4px solid #2563eb;
            padding: 4px 8px;
            margin-bottom: 8px;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ─── SECTION A: INFORMASI PEMBELIAN ──────────────────────────────── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .info-table td {
            vertical-align: top;
            padding: 2.5px 4px;
            font-size: 11px;
        }
        .info-label {
            width: 135px;
            color: #475569;
            font-weight: 700;
            white-space: nowrap;
        }
        .info-colon {
            width: 8px;
            font-weight: bold;
            color: #64748b;
        }
        .info-value {
            color: #0f172a;
            font-weight: 600;
        }

        /* ─── SECTION B: TABEL DETAIL BARANG ──────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
        }
        .items-table th {
            background-color: #f1f5f9;
            border: 1px solid #94a3b8;
            padding: 5px 6px;
            font-size: 10.5px;
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            text-transform: uppercase;
        }
        .items-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 11px;
            vertical-align: middle;
        }
        .items-table tfoot td {
            background-color: #f8fafc;
            border: 1px solid #94a3b8;
            font-weight: 800;
            padding: 5px 6px;
        }
        .underline-blank {
            display: inline-block;
            width: 65px;
            border-bottom: 1.5px solid #475569;
            height: 13px;
            vertical-align: middle;
        }

        /* ─── SECTION C: INFORMASI PENGAMBILAN ────────────────────────────── */
        .form-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            background-color: #ffffff;
            margin-bottom: 4px;
        }
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            font-size: 11px;
        }
        .form-row:last-child {
            margin-bottom: 0;
        }
        .form-col {
            flex: 1;
            display: flex;
            align-items: center;
        }
        .fill-underline {
            display: inline-block;
            border-bottom: 1px dotted #475569;
            margin: 0 4px;
            height: 14px;
        }

        /* ─── SECTION D: KONDISI & KELENGKAPAN ─────────────────────────────── */
        .check-box-square {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid #334155;
            border-radius: 2px;
            margin-right: 5px;
            vertical-align: -1px;
        }
        .check-item {
            display: inline-flex;
            align-items: center;
            margin-right: 18px;
            font-weight: 600;
            color: #334155;
        }

        /* ─── SECTION E: TANDA TANGAN (SERAH TERIMA 4 PIHAK) ──────────────── */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }
        .sign-table th {
            width: 25%;
            background-color: #edf2f7;
            border: 1px solid #cbd5e1;
            padding: 7px 6px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
        }
        .sign-table td {
            width: 25%;
            border: 1px solid #cbd5e1;
            height: 75px;
            background-color: #ffffff;
        }

        /* ─── FOOTER NOTE ─────────────────────────────────────────────────── */
        .doc-footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9.5px;
            color: #64748b;
            font-style: italic;
            line-height: 1.4;
        }

        /* ─── PRINT SPECIFIC STYLING ──────────────────────────────────────── */
        @media print {
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .page {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 4mm 6mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar Atas (Hanya tampil di layar, tersembunyi saat cetak) -->
    <div class="no-print-toolbar no-print">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-print fa-lg text-primary"></i>
            <div>
                <strong style="font-size: 14px;">Preview Cetak Form Pengambilan Barang</strong>
                <span style="font-size: 12px; color: #94a3b8; margin-left: 8px;">(PO: <?= htmlspecialchars($ponmbr) ?>)</span>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="toolbar-btn" onclick="window.print();">
                <i class="fas fa-print"></i> Cetak / Simpan PDF
            </button>
            <button type="button" class="toolbar-btn toolbar-btn-secondary" onclick="window.close();">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>
    </div>

    <!-- Halaman Dokumen A4 -->
    <div class="page">
        <!-- 1. Header Dokumen -->
        <div class="doc-header">
            <div class="doc-header-left">
                <div class="doc-title">Form Pengambilan Barang ke Supplier</div>
                <div class="doc-subtitle">Formulir Pengambilan Barang Hasil Pembelian</div>
            </div>
            <div class="po-box">
                <div class="po-box-row">
                    <span class="po-box-label">NO. PO :</span>
                    <span class="po-box-value po-box-number"><?= htmlspecialchars($ponmbr) ?></span>
                </div>
                <div class="po-box-row">
                    <span class="po-box-label">TANGGAL PO :</span>
                    <span class="po-box-value"><?= htmlspecialchars($podate) ?></span>
                </div>
                <div class="po-box-row">
                    <span class="po-box-label">STATUS :</span>
                    <span class="po-box-value">
                        <span class="po-status-badge"><?= htmlspecialchars($statusDisplay) ?></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Bagian A: Informasi Pembelian -->
        <div class="section-header">A. Informasi Pembelian</div>
        <table class="info-table">
            <tr>
                <td style="width: 52%; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="info-label">Vendor / Supplier</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($vendorDisplay) ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">PIC Pembuat PO</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($picPembuat) ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Alamat Pengambilan</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($alamatKirim) ?></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 48%; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="info-label" style="width: 120px;">Tujuan</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($dikirimKe) ?></td>
                        </tr>
                        <tr>
                            <td class="info-label" style="width: 120px;">Status Pembayaran</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($paymentLabel) ?></td>
                        </tr>
                        <tr>
                            <td class="info-label" style="width: 120px;">Tanggal CP / No. CP</td>
                            <td class="info-colon">:</td>
                            <td class="info-value"><?= htmlspecialchars($cpDisplay) ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- 3. Bagian B: Detail Barang Yang Diambil -->
        <div class="section-header">B. Detail Barang yang Diambil</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 35px;">No.</th>
                    <th style="width: 135px;">Kode Barang</th>
                    <th>Nama Barang / Produk</th>
                    <th style="width: 75px;">Qty PO</th>
                    <th style="width: 100px;">Qty Diambil</th>
                    <th style="width: 65px;">Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($itemRows as $item): 
                ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?= $no++ ?></td>
                    <td style="font-family: monospace; font-size: 10.5px;"><?= htmlspecialchars($item['poprodcode']) ?></td>
                    <td style="font-weight: 600;"><?= htmlspecialchars($item['poprodname']) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= fmtQty($item['poqty']) ?></td>
                    <td style="text-align: center;">
                        <span class="underline-blank"></span>
                    </td>
                    <td style="text-align: center; font-weight: 600;"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; text-transform: uppercase;">Total :</td>
                    <td style="text-align: right;"><?= fmtQty($totalPoQty) ?></td>
                    <td style="text-align: center;">
                        <span class="underline-blank"></span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <!-- 4. Bagian C: Informasi Pengambilan (Diisi Saat Barang Diambil) -->
        <div class="section-header">C. Informasi Pengambilan (Diisi Saat Barang Diambil)</div>
        <div class="form-box">
            <div class="form-row">
                <span style="font-weight: 700; width: 140px;">Tanggal Pengambilan :</span>
                <span>Hari</span>
                <span class="fill-underline" style="width: 120px;"></span>
                <span style="margin-left: 10px;">Tanggal</span>
                <span class="fill-underline" style="width: 35px; text-align: center;"></span> /
                <span class="fill-underline" style="width: 35px; text-align: center;"></span> /
                <span class="fill-underline" style="width: 55px; text-align: center;"></span>
                <span style="margin-left: 15px;">Jam</span>
                <span class="fill-underline" style="width: 80px;"></span>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <span style="font-weight: 700; width: 140px;">PIC Pengambil :</span>
                    <span class="fill-underline" style="flex: 1;"></span>
                </div>
                <div class="form-col" style="flex: 0.8; margin-left: 20px;">
                    <span style="font-weight: 700; width: 60px;">No. HP :</span>
                    <span class="fill-underline" style="flex: 1;"></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <span style="font-weight: 700; width: 140px;">No. Kendaraan :</span>
                    <span class="fill-underline" style="flex: 1;"></span>
                </div>
                <div class="form-col" style="flex: 0.8; margin-left: 20px;">
                    <span style="font-weight: 700; width: 110px;">Jenis Kendaraan :</span>
                    <span class="fill-underline" style="flex: 1;"></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <span style="font-weight: 700; width: 140px;">Nama Penyerah Supplier :</span>
                    <span class="fill-underline" style="flex: 1;"></span>
                </div>
            </div>
        </div>

        <!-- 5. Bagian D: Kondisi & Kelengkapan Barang -->
        <div class="section-header">D. Kondisi & Kelengkapan Barang</div>
        <div class="form-box">
            <div class="form-row" style="margin-bottom: 8px;">
                <span style="font-weight: 700; width: 175px;">Kondisi barang saat diterima :</span>
                <span class="check-item"><span class="check-box-square"></span> Baik</span>
                <span class="check-item"><span class="check-box-square"></span> Rusak</span>
                <span class="check-item"><span class="check-box-square"></span> Tidak sesuai</span>
                <span class="check-item">
                    <span class="check-box-square"></span> Lainnya:
                    <span class="fill-underline" style="width: 140px;"></span>
                </span>
            </div>
            <div class="form-row" style="margin-bottom: 8px;">
                <span style="font-weight: 700; width: 175px;">Kelengkapan :</span>
                <span class="check-item"><span class="check-box-square"></span> Lengkap</span>
                <span class="check-item">
                    <span class="check-box-square"></span> Tidak lengkap &nbsp;&nbsp; Keterangan:
                    <span class="fill-underline" style="width: 250px;"></span>
                </span>
            </div>
            <div class="form-row">
                <span style="font-weight: 700; width: 70px;">Catatan :</span>
                <span class="fill-underline" style="flex: 1;"></span>
            </div>
        </div>

        <!-- 6. Bagian E: Persetujuan & Serah Terima -->
        <div class="section-header">E. Persetujuan & Serah Terima</div>
        <table class="sign-table">
            <thead>
                <tr>
                    <th>Disiapkan oleh</th>
                    <th>Pengambil Barang</th>
                    <th>Diserahkan oleh Supplier</th>
                    <th>Diperiksa / Diterima</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <!-- 7. Catatan Kaki (Footer Note) -->
        <div class="doc-footer">
            <strong>Catatan:</strong> Form ini dibawa saat pengambilan barang dan dikembalikan ke bagian terkait sebagai bukti pengambilan/serah terima. Pastikan jumlah dan kondisi barang sesuai sebelum meninggalkan lokasi supplier.
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', function () {
            // Otomatis memicu dialog print setelah halaman termuat
            setTimeout(function () {
                window.print();
            }, 600);
        });
    </script>
</body>
</html>
