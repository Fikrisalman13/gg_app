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
    $sql = "SELECT * FROM dbo.Form_Purchasing_Pengembalian_Uang WHERE ticket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data = $row;
    }
}

if (!$data) {
    echo '<div style="padding:30px; text-align:center; font-family:sans-serif;"><h3>Data Bukti Pengembalian Uang tidak ditemukan</h3></div>';
    exit;
}

// Format Tanggal
$tanggalPengajuan = '';
if (!empty($data['tanggal'])) {
    $tanggalPengajuan = $data['tanggal'] instanceof DateTime ? $data['tanggal']->format('d/m/Y') : date('d/m/Y', strtotime($data['tanggal']));
}

$tglRfp = '';
if (!empty($data['tgl_rfp'])) {
    $tglRfp = $data['tgl_rfp'] instanceof DateTime ? $data['tgl_rfp']->format('d/m/Y') : date('d/m/Y', strtotime($data['tgl_rfp']));
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
$displayTerbilang = ($subtotal > 0) ? getTerbilangLengkapPHP($subtotal, $currency) : (!empty($data['terbilang']) ? $data['terbilang'] : '-');

if (!function_exists('formatMataUangBPU')) {
    function formatMataUangBPU($num, $curr = 'IDR') {
        $num = floatval($num);
        $c = strtoupper(trim($curr ?: 'IDR'));
        if ($c === 'IDR' || $c === 'RP') {
            $hasDec = fmod($num, 1) != 0;
            if ($hasDec) {
                return 'Rp ' . number_format($num, 2, ',', '.');
            } else {
                return 'Rp ' . number_format($num, 0, ',', '.') . ',-';
            }
        } else {
            return $c . ' ' . number_format($num, 2, '.', ',');
        }
    }
}

// Cek file lampiran
$lampiranUrl = '';
$lampiranExt = '';
if (!empty($data['lampiran_path'])) {
    $rawPath = $data['lampiran_path'];
    $lampiranUrl = '/gg_app/' . ltrim($rawPath, '/\\');
    $lampiranExt = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pengembalian Uang - <?= htmlspecialchars($data['ticket']) ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #525659;
            margin: 0;
            padding: 20px 0;
            font-size: 9.5pt;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print-bar {
            width: 210mm;
            max-width: 95%;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #343a40;
            padding: 10px 18px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .btn-action {
            color: #fff;
            border: none;
            padding: 7px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-print {
            background: #28a745;
        }
        .btn-print:hover {
            background: #218838;
        }
        .btn-approve {
            background: #007bff;
        }
        .btn-approve:hover {
            background: #0056b3;
        }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 15mm 15mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            position: relative;
        }

        /* Styling sesuai format SUM Support App.docx */
        .doc-title {
            font-size: 15pt;
            font-weight: 800;
            text-align: center;
            margin: 0 0 4px 0;
            color: #000;
            letter-spacing: 0.5px;
        }
        .doc-rfp-line {
            font-size: 9.5pt;
            text-align: center;
            margin: 0 0 16px 0;
            color: #111;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5pt;
        }
        .meta-table td {
            vertical-align: top;
            padding: 2.5px 0;
        }
        .meta-table td.lbl {
            width: 160px;
            color: #111;
        }
        .meta-table td.sep {
            width: 15px;
            text-align: center;
        }
        .meta-table td.val {
            font-weight: bold;
            color: #000;
        }

        /* Tabel Rincian Item */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 5px 8px;
        }
        .items-table th {
            text-align: center;
            font-weight: 800;
            background: #f8f9fa;
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
            font-size: 9pt;
            margin-top: 8px;
            margin-bottom: 18px;
            line-height: 1.4;
            color: #000;
        }
        .terbilang-text strong {
            font-weight: bold;
        }
        .terbilang-text em {
            font-style: italic;
        }

        /* Tanda Tangan */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 15px;
            margin-bottom: 25px;
        }
        .sign-table td {
            width: 50%;
            vertical-align: top;
            padding: 4px;
        }
        .sign-space {
            min-height: 65px;
            height: 65px;
            position: relative;
        }
        .sign-img {
            max-height: 60px;
            max-width: 160px;
            display: block;
            margin: 0 auto;
        }

        /* Lampiran Gambar */
        .attachment-container {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #aaa;
            page-break-inside: avoid;
        }
        .attachment-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .attachment-preview {
            max-width: 100%;
            max-height: 480px;
            object-fit: contain;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 4px;
            background: #fff;
            display: block;
            margin: 0 auto;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
                max-height: 297mm !important;
            }
            .page-container {
                box-shadow: none !important;
                padding: 10mm 15mm 8mm 15mm !important;
                width: 210mm !important;
                max-width: 210mm !important;
                height: 297mm !important;
                max-height: 297mm !important;
                margin: 0 auto !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .attachment-container {
                margin-top: 10px !important;
                padding-top: 8px !important;
                page-break-inside: avoid !important;
            }
            .attachment-title {
                margin-bottom: 6px !important;
                font-size: 9pt !important;
            }
            .attachment-preview {
                max-height: 310px !important;
                width: auto !important;
                object-fit: contain !important;
            }
            .sign-table {
                margin-top: 10px !important;
                margin-bottom: 12px !important;
            }
            .sign-space {
                min-height: 50px !important;
                height: 50px !important;
            }
            .sign-img {
                max-height: 50px !important;
            }
            .terbilang-text {
                margin-bottom: 10px !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <?php
                $status = $data['status'] ?? 'Pending';
                $badgeBg = '#ffc107'; $badgeColor = '#000';
                if ($status === 'Approved') { $badgeBg = '#28a745'; $badgeColor = '#fff'; }
                elseif ($status === 'Ditolak') { $badgeBg = '#dc3545'; $badgeColor = '#fff'; }
            ?>
            <span class="badge" style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>; padding:5px 10px; border-radius:4px; font-weight:bold; font-size:11px;">
                Status: <?= htmlspecialchars($status) ?>
            </span>
            <span style="margin-left: 10px; color:#f8f9fa; font-size:12px;">
                Tiket: <strong><?= htmlspecialchars($data['ticket']) ?></strong>
            </span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($status === 'Pending'): ?>
                <button type="button" class="btn-action btn-approve" id="btnApproveKabag" title="Tanda Tangani / Approve Pengajuan">
                    <i class="fas fa-signature"></i> Approve (KABAG)
                </button>
            <?php endif; ?>
            <button type="button" class="btn-action btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Cetak Dokumen (A4 Portrait)
            </button>
        </div>
    </div>

    <div class="page-container">
        <!-- Judul Form Sesuai SUM Support App.docx -->
        <h1 class="doc-title">Bukti Pengembalian Uang</h1>
        <div class="doc-rfp-line">
            RFP NO: <strong><?= htmlspecialchars($data['rfp_no'] ?: '-') ?></strong>, 
            Tgl RFP: <strong><?= htmlspecialchars($tglRfp ?: '-') ?></strong>
        </div>

        <!-- Tabel Informasi Header -->
        <table class="meta-table">
            <tr>
                <td class="lbl">Tanggal</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($tanggalPengajuan ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Refferensi PO No</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['ref_po_no'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Suppier</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['supplier'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">A/C</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['rekening_ac'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Bank</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['bank'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Currency</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($currency) ?></td>
            </tr>
            <tr>
                <td class="lbl">Payment Method</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($data['payment_method'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Keterangan</td>
                <td class="sep">:</td>
                <td class="val"><?= nl2br(htmlspecialchars($data['keterangan'] ?: '-')) ?></td>
            </tr>
        </table>

        <!-- Tabel Rincian Item / Pengembalian -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 35px;">NO</th>
                    <th>NAMA PRODUCT / JASA</th>
                    <th style="width: 65px;">CURR</th>
                    <th style="width: 150px;">HARGA SATUAN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $idx => $it): ?>
                        <?php
                            $dppNum = floatval($it['dpp'] ?? 0);
                            $rowCurr = !empty($it['curr']) ? trim($it['curr']) : $currency;
                            $formattedDpp = formatMataUangBPU($dppNum, $rowCurr);
                        ?>
                        <tr>
                            <td class="center font-weight-bold"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($it['keterangan'] ?? '') ?></td>
                            <td class="center font-weight-bold"><?= htmlspecialchars($it['curr'] ?? $currency) ?></td>
                            <td class="right font-weight-bold"><?= $formattedDpp ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="center">1</td>
                        <td>-</td>
                        <td class="center"><?= $currency ?></td>
                        <td class="right font-weight-bold"><?= formatMataUangBPU(0, $currency) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="right font-weight-bold" style="border: 1px solid #000; padding-right: 15px;">Total:</td>
                    <td class="right font-weight-bold" style="border: 1px solid #000;">
                        <?= formatMataUangBPU($subtotal, $currency) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Terbilang -->
        <div class="terbilang-text">
            <strong>Terbilang:</strong> <em><?= htmlspecialchars($displayTerbilang) ?></em>
        </div>

        <!-- Tanda Tangan Sesuai SUM Support App.docx -->
        <table class="sign-table">
            <tr>
                <td>Dibuat Oleh</td>
                <td>Dicek Oleh</td>
            </tr>
            <tr>
                <td class="sign-space">
                    <?php if (!empty($data['ttd_dibuat_oleh'])): ?>
                        <img src="<?= htmlspecialchars($data['ttd_dibuat_oleh']) ?>" class="sign-img" alt="TTD Pembuat">
                    <?php else: ?>
                        <div style="height: 50px;"></div>
                    <?php endif; ?>
                </td>
                <td class="sign-space">
                    <?php if (!empty($data['ttd_dicek_oleh'])): ?>
                        <img src="<?= htmlspecialchars($data['ttd_dicek_oleh']) ?>" class="sign-img" alt="TTD KABAG">
                    <?php else: ?>
                        <div style="height: 50px;"></div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <div style="font-weight: bold; margin-top: 2px;">( <?= htmlspecialchars($data['dibuat_oleh'] ?: 'PEMBUAT') ?> )</div>
                </td>
                <td>
                    <div style="font-weight: bold; margin-top: 2px;">( <?= htmlspecialchars($data['dicek_oleh'] ?: 'KABAG') ?> )</div>
                </td>
            </tr>
        </table>

        <!-- Lampiran Gambar (Sesuai Permintaan User: Disimpan di C:\xampp\htdocs\gg_app\uploads\form_purchasing) -->
        <?php if (!empty($lampiranUrl)): ?>
            <div class="attachment-container">
                <div class="attachment-title">
                    <i class="fas fa-paperclip mr-1"></i> Lampiran Gambar / Bukti Pendukung:
                </div>
                <?php if (in_array($lampiranExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                    <img src="<?= htmlspecialchars($lampiranUrl) ?>" class="attachment-preview" alt="Lampiran Pengembalian Uang">
                <?php else: ?>
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px;">
                        <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                        <p class="mb-2 font-weight-bold">Dokumen Lampiran (<?= strtoupper($lampiranExt) ?>)</p>
                        <a href="<?= htmlspecialchars($lampiranUrl) ?>" target="_blank" class="btn btn-sm btn-primary" style="display:inline-block; padding: 6px 12px; text-decoration:none; background:#007bff; color:#fff; border-radius:4px;">
                            <i class="fas fa-external-link-alt"></i> Buka / Download Lampiran
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Script Approve KABAG -->
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
    <script>
    $('#btnApproveKabag').on('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Konfirmasi Approval',
            text: 'Apakah Anda yakin ingin menyetujui (Approve) pengajuan Bukti Pengembalian Uang ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Approve',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'proses_ttd_dicek.php',
                    type: 'POST',
                    data: {
                        ticket: '<?= htmlspecialchars($data['ticket']) ?>',
                        form_type: 'bukti_pengembalian_uang'
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res && res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message || 'Pengajuan berhasil disetujui.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: (res && res.message) ? res.message : 'Terjadi kesalahan saat approve.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Gagal menghubungi server.'
                        });
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
