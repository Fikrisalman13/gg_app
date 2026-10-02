<?php

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require '../../vendor/autoload.php';
require '../../koneksi.php';
require_once __DIR__ . '/includes/pengajuan_aplikasi_logging.php';

use Dompdf\Dompdf;

$requestId = pengajuanAplikasiRequestId();
if (!isset($_SESSION['UserName'])) {
    sendPdfError(401, 'Silakan login kembali.', $requestId);
}

$ticket = trim((string) ($_GET['ticket'] ?? ''));
if (!preg_match('/^APP-\d{8}-\d{3}$/', $ticket)) {
    sendPdfError(400, 'Nomor pengajuan tidak valid.', $requestId);
}

$detailStatement = sqlsrv_query(
    $conn,
    "SELECT * FROM Form_Pengajuan_Aplikasi WHERE ticket = ?",
    [$ticket]
);
if ($detailStatement === false) {
    pengajuanAplikasiLogError($requestId, 'pdf', 'Query data PDF gagal.');
    sendPdfError(500, 'PDF gagal diproses.', $requestId);
}

$data = sqlsrv_fetch_array($detailStatement, SQLSRV_FETCH_ASSOC);
if (!$data) {
    sendPdfError(404, 'Pengajuan tidak ditemukan.', $requestId);
}

$signatures = [];
$signatureSql = "SELECT GroupRole, SignaturePath, SignedByUserName
                 FROM Form_Pengajuan_Barang_TTD
                 WHERE Ticket = ?";
$signatureStatement = sqlsrv_query($conn, $signatureSql, [$ticket]);
if ($signatureStatement === false) {
    pengajuanAplikasiLogError($requestId, 'pdf', 'Query tanda tangan PDF gagal.');
    sendPdfError(500, 'PDF gagal diproses.', $requestId);
}
while ($signatureRow = sqlsrv_fetch_array($signatureStatement, SQLSRV_FETCH_ASSOC)) {
    $signatures[$signatureRow['GroupRole']] = $signatureRow;
}

$requestFields = [
    'nama_aplikasi' => 'Nama Aplikasi',
    'latar_belakang_kendala' => 'Latar Belakang dan Kendala Saat Ini',
    'tujuan_pembuatan' => 'Tujuan Pembuatan Aplikasi',
    'gambaran_proses' => 'Gambaran Proses yang Diharapkan',
    'fitur_utama' => 'Kebutuhan atau Fitur Utama',
    'manfaat_diharapkan' => 'Manfaat yang Diharapkan',
];
$signatureRoles = [
    'Pemohon',
    'Atasan Pemohon',
    'Petugas IT',
    'Kabag IT',
    'Kadept IT',
];
$logoHtml = pdfAppImage('../../dist/img/sumlogo.png', 60);
$requestFieldsHtml = buildRequestFieldsHtml($requestFields, $data);
$signatureRows = buildSignatureRows($signatureRoles, $signatures);
$attachmentRowHtml = buildAttachmentRowHtml($data['lampiran_nama_asli'] ?? null);

$html = buildPdfHtml(
    $ticket,
    $data,
    $logoHtml,
    $requestFieldsHtml,
    $signatureRows,
    $attachmentRowHtml
);

try {
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();
} catch (Throwable $exception) {
    pengajuanAplikasiLogError($requestId, 'pdf', 'Render PDF gagal.');
    sendPdfError(500, 'PDF gagal dibuat.', $requestId);
}

$pdfFileName = 'PENGAJUAN APLIKASI - ' . $ticket . '.pdf';
if (($_GET['download'] ?? '') === '1') {
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFileName . '"');
    echo $pdfContent;
    exit;
}

ob_end_clean();
$pdfBase64 = base64_encode($pdfContent);

/** Escape nilai untuk HTML PDF dan preview. */
function pdfAppEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format tanggal PDF menjadi DD-MM-YYYY. */
function pdfAppDate($value)
{
    return $value instanceof DateTime
        ? $value->format('d-m-Y')
        : pdfAppEsc($value ?: '-');
}

/**
 * Mengubah file gambar lokal menjadi data URI untuk Dompdf.
 *
 * @param string $path Path relatif atau absolut gambar.
 * @param int $maxHeight Tinggi maksimum gambar dalam piksel.
 * @return string Elemen gambar atau string kosong.
 */
function pdfAppImage($path, $maxHeight = 55)
{
    $relativePath = ltrim((string) $path, '/\\');
    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $candidatePaths = [
        (string) $path,
        $documentRoot . DIRECTORY_SEPARATOR . $relativePath,
        __DIR__ . '/../../' . $relativePath,
    ];

    foreach ($candidatePaths as $candidatePath) {
        if ($candidatePath === '' || !is_file($candidatePath)) {
            continue;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($candidatePath) ?: 'image/png';
        $imageContent = file_get_contents($candidatePath);
        if ($imageContent === false) {
            continue;
        }

        return '<img src="data:' . pdfAppEsc($mimeType)
            . ';base64,' . base64_encode($imageContent)
            . '" style="max-height:' . (int) $maxHeight
            . 'px;max-width:100%;">';
    }

    return '';
}

/** Membentuk field kebutuhan sebagai baris form standar tanpa kotak per field. */
function buildRequestFieldsHtml($requestFields, $data)
{
    $fieldsHtml = '';
    foreach ($requestFields as $fieldName => $fieldLabel) {
        $fieldsHtml .= '<tr class="form-row"><td class="label">'
            . pdfAppEsc($fieldLabel)
            . '</td><td class="value">: '
            . nl2br(pdfAppEsc($data[$fieldName] ?: '-'))
            . '</td></tr>';
    }
    return $fieldsHtml;
}

/** Membentuk area gambar dan label lima role tanda tangan. */
function buildSignatureRows($signatureRoles, $signatures)
{
    $rows = ['images' => '', 'labels' => ''];
    foreach ($signatureRoles as $roleName) {
        $signature = $signatures[$roleName] ?? [];
        $signatureImage = pdfAppImage($signature['SignaturePath'] ?? '');
        $signerName = pdfAppEsc($signature['SignedByUserName'] ?? '');
        $signatureContent = $signatureImage;

        if ($signatureContent !== '' && $signerName !== '') {
            $signatureContent .= '<br><small>' . $signerName . '</small>';
        }

        $rows['images'] .= '<td>' . ($signatureContent ?: '&nbsp;') . '</td>';
        $rows['labels'] .= '<td><b>' . pdfAppEsc($roleName) . '</b></td>';
    }
    return $rows;
}

/** Membentuk baris lampiran hanya ketika lampiran tersedia. */
function buildAttachmentRowHtml($attachmentName)
{
    $attachmentName = trim((string) $attachmentName);
    if ($attachmentName === '') {
        return '';
    }

    return '<tr class="attachment"><td class="label"><b>Lampiran Pendukung</b></td>'
        . '<td colspan="3" class="value">: ' . pdfAppEsc($attachmentName) . '</td></tr>';
}

/** Membentuk dokumen HTML dengan struktur visual standar Form IT. */
function buildPdfHtml(
    $ticket,
    $data,
    $logoHtml,
    $requestFieldsHtml,
    $signatureRows,
    $attachmentRowHtml
) {
    $styles = '@page{margin:22px}'
        . 'body{font-family:Arial,sans-serif;font-size:10pt;color:#111}'
        . 'table{width:100%;border-collapse:collapse}'
        . 'td{vertical-align:top}'
        . '.border{border:1px solid #000}'
        . '.center{text-align:center}.bold{font-weight:bold}'
        . '.header-logo{width:15%;border-right:1px solid #000;text-align:center;padding:7px}'
        . '.company{padding:6px;line-height:1.3;font-size:9pt}'
        . '.title{border-top:1px solid #000;border-bottom:1px solid #000;padding:5px}'
        . '.body{border-top:none;border-bottom:none}'
        . '.form-row td{padding:3px 6px}'
        . '.label{width:27%;white-space:nowrap}.value{padding-left:3px;line-height:1.35}'
        . '.date-label{width:20%;text-align:right;white-space:nowrap}'
        . '.date-value{width:20%;text-align:right;white-space:nowrap}'
        . '.section-title td{padding:7px 6px 3px;font-weight:bold}'
        . '.attachment td{padding:5px 6px 8px}'
        . '.sign-table{table-layout:fixed;border:1px solid #000}'
        . '.sign-table td{border:1px solid #000;padding:4px 6px;text-align:center}'
        . '.sign-header td{height:24px;vertical-align:middle;font-weight:bold}'
        . '.sign-images td{height:76px;vertical-align:middle}'
        . '.sign-images img{max-height:55px;display:block;margin:0 auto}'
        . '.sign-labels td{height:20px;vertical-align:middle;font-size:9pt}'
        . '.note-cell{font-size:9pt;padding:6px 8px!important;text-align:left!important}'
        . '.footer-code td{padding:5px;font-weight:bold;vertical-align:middle}';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
        . $styles
        . '</style></head><body>'
        . '<table class="border" style="border-bottom:none"><tr>'
        . '<td class="header-logo">' . $logoHtml . '</td>'
        . '<td class="company"><b>PT. SURYA USAHA MANDIRI</b><br>'
        . 'Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>'
        . 'Banjaran – Kab. Bandung<br>40377 Telp. (022) 594-0313</td></tr>'
        . '<tr><td colspan="2" class="title center bold">'
        . 'FORM PENGAJUAN PEMBUATAN APLIKASI</td></tr></table>'
        . '<table class="border body"><tr class="form-row"><td class="label">Nama Pemohon</td>'
        . '<td colspan="3" class="value">: ' . pdfAppEsc($data['nama_pemohon']) . '</td></tr>'
        . '<tr class="form-row"><td class="label">Jabatan</td><td class="value">: '
        . pdfAppEsc($data['jabatan']) . '</td><td class="date-label">Tgl Pengajuan</td>'
        . '<td class="date-value">: ' . pdfAppDate($data['tgl_pengajuan']) . '</td></tr>'
        . '<tr class="form-row"><td class="label">Departemen</td>'
        . '<td colspan="3" class="value">: ' . pdfAppEsc($data['departemen']) . '</td></tr>'
        . '<tr class="form-row"><td class="label">Bagian</td>'
        . '<td colspan="3" class="value">: ' . pdfAppEsc($data['bagian']) . '</td></tr>'
        . '<tr class="section-title"><td colspan="4">Informasi Kebutuhan Aplikasi</td></tr>'
        . str_replace('<td class="value">', '<td colspan="3" class="value">', $requestFieldsHtml)
        . $attachmentRowHtml
        . '</table><table class="sign-table"><colgroup>'
        . '<col style="width:20%"><col style="width:20%"><col style="width:20%">'
        . '<col style="width:20%"><col style="width:20%"></colgroup>'
        . '<tr class="sign-header"><td>Diajukan Oleh</td><td>Mengetahui</td>'
        . '<td>Diketahui Oleh</td><td colspan="2">Disetujui Oleh</td></tr>'
        . '<tr class="sign-images">' . $signatureRows['images'] . '</tr>'
        . '<tr class="sign-labels">' . $signatureRows['labels'] . '</tr>'
        . '<tr><td colspan="5" class="note-cell"><b>Catatan:</b> Persetujuan form ini hanya '
        . 'menentukan pengajuan diterima atau ditolak. Proses pengembangan dan serah terima '
        . 'dicatat pada form terpisah.</td></tr>'
        . '<tr class="footer-code"><td colspan="4">FORM PENGAJUAN PEMBUATAN APLIKASI</td>'
        . '<td>SW</td></tr></table></body></html>';
}

/** Mengirim kegagalan PDF dengan ID korelasi. */
function sendPdfError($statusCode, $message, $requestId)
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Request-ID: ' . $requestId);
    exit($message . ' ID: ' . $requestId);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview PDF - <?= pdfAppEsc($ticket) ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link
        rel="stylesheet"
        href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css"
    >
    <style>
        body { background: #f4f4f4; padding: 20px; }
        .preview {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
        }
        .ticket-info {
            margin-bottom: 20px;
            padding: 10px 15px;
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
        }
        .ticket-info p { margin: 5px 0; }
        .pdf-viewer { width: 100%; height: 800px; border: 1px solid #ccc; }
    </style>
</head>
<body>
<main class="preview">
    <h1 class="h3">
        <i class="fas fa-file-pdf text-danger"></i>
        Preview Pengajuan Pembuatan Aplikasi
    </h1>
    <div class="ticket-info">
        <p><b>No. Pengajuan:</b> <?= pdfAppEsc($ticket) ?></p>
        <p><b>Pemohon:</b> <?= pdfAppEsc($data['nama_pemohon']) ?></p>
        <p><b>Departemen:</b> <?= pdfAppEsc($data['departemen']) ?></p>
    </div>
    <object
        class="pdf-viewer"
        type="application/pdf"
        data="data:application/pdf;base64,<?= $pdfBase64 ?>"
        aria-label="<?= pdfAppEsc($pdfFileName) ?>"
    ></object>
    <div class="mt-3">
        <a class="btn btn-success" href="?ticket=<?= urlencode($ticket) ?>&download=1">
            <i class="fas fa-download"></i> Download PDF
        </a>
        <a class="btn btn-secondary ml-2" href="javascript:history.back()">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</main>
</body>
</html>
