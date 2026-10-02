<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
}

$ticket = $_GET['ticket'] ?? '';
$data = null;

if (!empty($ticket) && isset($conn) && $conn !== false) {
    $sql = "SELECT * FROM dbo.Form_Purchasing_COD WHERE ticket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data = $row;
    }
}

if (!$data) {
    echo '<div style="padding:30px; text-align:center; font-family:sans-serif;"><h3>Data pengajuan tidak ditemukan</h3></div>';
    exit;
}

// Ambil Nama Lengkap user yang sedang login untuk penandatanganan Dicek Oleh (KABAG)
$reviewerName = !empty($_SESSION['NamaLengkap']) ? trim($_SESSION['NamaLengkap']) : (!empty($_SESSION['UserName']) ? trim($_SESSION['UserName']) : 'KABAG');
if (isset($conn) && $conn !== false && !empty($_SESSION['UserName'])) {
    $qUser = sqlsrv_query($conn, "SELECT nama_lengkap FROM dbo.m_emp WHERE username = ? OR nama_lengkap = ?", [$_SESSION['UserName'], $_SESSION['UserName']]);
    if ($qUser && $rUser = sqlsrv_fetch_array($qUser, SQLSRV_FETCH_ASSOC)) {
        if (!empty($rUser['nama_lengkap'])) {
            $reviewerName = trim($rUser['nama_lengkap']);
        }
    }
}

$tglReceipt = '';
if (!empty($data['tgl_receipt'])) {
    $tglReceipt = $data['tgl_receipt'] instanceof DateTime ? $data['tgl_receipt']->format('d/m/Y') : date('d/m/Y', strtotime($data['tgl_receipt']));
}

$tglPengajuan = '';
if (!empty($data['tgl_pengajuan'])) {
    $tglPengajuan = $data['tgl_pengajuan'] instanceof DateTime ? $data['tgl_pengajuan']->format('d/m/Y') : date('d/m/Y', strtotime($data['tgl_pengajuan']));
}

$dueDate = '';
if (!empty($data['due_date'])) {
    $dueDate = $data['due_date'] instanceof DateTime ? $data['due_date']->format('d/m/Y') : date('d/m/Y', strtotime($data['due_date']));
}

$items = [];
if (!empty($data['items_json'])) {
    $parsed = json_decode($data['items_json'], true);
    if (is_array($parsed)) {
        $items = $parsed;
    }
}

$subtotal = floatval($data['subtotal'] ?? 0);
$currency = htmlspecialchars($data['currency'] ?? 'IDR');

require_once __DIR__ . '/helper_terbilang.php';
$displayTerbilang = ($subtotal > 0) ? getTerbilangLengkapPHP($subtotal) : (!empty($data['terbilang']) ? $data['terbilang'] : '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Cash On Delivery - <?= htmlspecialchars($data['ticket']) ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <style>
        @page {
            size: A5 landscape;
            margin: 5mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #525659;
            margin: 0;
            padding: 15px 0;
            font-size: 8.5pt;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print-bar {
            width: 210mm;
            max-width: 95%;
            margin: 0 auto 10px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #343a40;
            padding: 8px 16px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .btn-print {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 11.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-print:hover {
            background: #0056b3;
        }
        .page-container {
            width: 210mm;
            min-height: 148mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 6mm 10mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            position: relative;
        }
        .doc-box {
            position: absolute;
            top: 6mm;
            right: 10mm;
            border: 1.5px solid #000;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8pt;
            letter-spacing: 0.5px;
            background: #fff;
        }
        .doc-title-container {
            text-align: center;
            margin-top: 3mm;
            margin-bottom: 10px;
        }
        .doc-title {
            font-size: 13pt;
            font-weight: 800;
            margin: 0 0 2px 0;
            color: #000;
        }
        .doc-subtitle {
            font-size: 7.5pt;
            font-weight: bold;
            margin: 0;
            color: #000;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7.8pt;
        }
        .meta-table td {
            vertical-align: top;
            padding: 1.5px 0;
        }
        .meta-table td.lbl {
            width: 135px;
            font-weight: bold;
            color: #111;
        }
        .meta-table td.sep {
            width: 14px;
            font-weight: bold;
            text-align: center;
        }
        .meta-table td.val {
            font-weight: bold;
            color: #000;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7.5pt;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 3px 6px;
        }
        .items-table th {
            text-align: center;
            font-weight: 800;
            background: #fff;
            color: #000;
        }
        .items-table td.center {
            text-align: center;
        }
        .items-table td.right {
            text-align: right;
            white-space: nowrap;
        }
        .terbilang-text {
            font-size: 7.5pt;
            margin-top: 4px;
            margin-bottom: 8px;
            line-height: 1.3;
            color: #000;
        }
        .terbilang-text strong {
            font-weight: bold;
        }
        .terbilang-text em {
            font-style: italic;
        }
        .sign-section {
            width: 100%;
            margin-top: 6px;
            font-size: 8pt;
        }
        .sign-company {
            text-align: right;
            font-weight: bold;
            margin-bottom: 8px;
            padding-right: 35px;
            font-size: 8pt;
        }
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .sign-table td {
            width: 50%;
            vertical-align: top;
        }
        .sign-space {
            min-height: 50px;
            height: 50px;
        }

        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 210mm !important;
                height: 148mm !important;
            }
            .page-container {
                box-shadow: none !important;
                padding: 5mm 8mm !important;
                width: 100% !important;
                min-height: 100% !important;
                height: 100% !important;
                margin: 0 !important;
            }
            .no-print, .no-print-bar {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .doc-box {
                top: 5mm !important;
                right: 8mm !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <span class="badge" style="background:#28a745; color:#fff; padding:5px 10px; border-radius:4px; font-weight:bold; font-size:11px;">
                Status: <?= htmlspecialchars($data['status'] ?? 'Pending') ?>
            </span>
            <span style="margin-left: 10px; color:#f8f9fa; font-size:11.5px;">
                Tiket: <strong><?= htmlspecialchars($data['ticket']) ?></strong>
            </span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Cetak Dokumen (A5 Landscape)
            </button>
        </div>
    </div>

    <div class="page-container">
        <!-- Box Kode Dokumen -->
        <div class="doc-box">
            SUM-FM-PB-004
        </div>

        <!-- Judul Form -->
        <div class="doc-title-container">
            <h2 class="doc-title">Pengajuan Cash On Delivery</h2>
            <div class="doc-subtitle">
                No Receipt: <?= htmlspecialchars($data['no_receipt'] ?: '-') ?>, 
                Tgl Receipt: <?= htmlspecialchars($tglReceipt ?: '-') ?>
            </div>
        </div>

        <!-- Tabel Informasi Header -->
        <table class="meta-table">
            <tr>
                <td class="lbl">Cash/Bank Account</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['cash_bank_account'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Supplier</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['supplier'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Dibayar kepada</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['dibayar_kepada'] ?: '') ?></td>
            </tr>
            <tr>
                <td class="lbl">A/C</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['rekening_ac'] ?: '') ?></td>
            </tr>
            <tr>
                <td class="lbl">Bank</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['bank'] ?: '') ?></td>
            </tr>
            <tr>
                <td class="lbl">Currency</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($currency) ?></td>
            </tr>
            <tr>
                <td class="lbl">Keterangan</td>
                <td class="sep">:</td>
                <td class="val"><?= nl2br(htmlspecialchars($data['keterangan'] ?: '-')) ?></td>
            </tr>
            <tr>
                <td class="lbl">Refferensi PO No.</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['ref_po_no'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Tgl Pengajuan</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($tglPengajuan ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Due date</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($dueDate ?: '-') ?></td>
            </tr>
        </table>

        <!-- Tabel Item Rincian -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 30px;">NO</th>
                    <th>KETERANGAN</th>
                    <th style="width: 40px;">C/D</th>
                    <th style="width: 55px;">CURR</th>
                    <th style="width: 130px;">DPP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $idx => $it): ?>
                        <?php
                            $dppNum = floatval($it['dpp'] ?? 0);
                            $formattedDpp = 'Rp ' . number_format($dppNum, 2, ',', '.') . ',-';
                            if (strpos($formattedDpp, ',00,-') !== false) {
                                $formattedDpp = str_replace(',00,-', ',-', $formattedDpp);
                            }
                        ?>
                        <tr>
                            <td class="center font-weight-bold"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($it['keterangan'] ?? '') ?></td>
                            <td class="center font-weight-bold"><?= htmlspecialchars($it['cd'] ?? 'D') ?></td>
                            <td class="center font-weight-bold"><?= htmlspecialchars($it['curr'] ?? $currency) ?></td>
                            <td class="right font-weight-bold"><?= $formattedDpp ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="center">1</td>
                        <td>-</td>
                        <td class="center">D</td>
                        <td class="center"><?= $currency ?></td>
                        <td class="right">Rp 0,-</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="right font-weight-bold" style="border: 1px solid #000; padding-right: 15px;">Total:</td>
                    <td class="right font-weight-bold" style="border: 1px solid #000;">
                        Rp <?= number_format($subtotal, 2, ',', '.') ?>,-
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Terbilang -->
        <div class="terbilang-text">
            <strong>Terbilang:</strong> <em><?= htmlspecialchars($displayTerbilang) ?></em>
        </div>

        <!-- Tanda Tangan -->
        <div class="sign-section">
            <table class="sign-table">
                <tr>
                    <td>Dibuat Oleh</td>
                    <td>Dicek Oleh</td>
                </tr>
                <tr>
                    <td class="sign-space">
                        <div style="height: 50px;"></div>
                    </td>
                    <td class="sign-space">
                        <div style="height: 50px;"></div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div style="font-weight: bold; margin-top: 2px;">( <?= htmlspecialchars($data['dibuat_oleh'] ?: 'RIA') ?> )</div>
                    </td>
                    <td>
                        <div style="font-weight: bold; margin-top: 2px;">( <?= htmlspecialchars($data['dicek_oleh'] ?: 'KABAG') ?> )</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
