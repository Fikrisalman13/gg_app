<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require '../../vendor/autoload.php';
use Dompdf\Dompdf;
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}

$ticket = trim((string)($_GET['ticket'] ?? ''));
if ($ticket === '') {
    die('Ticket tidak diberikan');
}

$isPemindahan = stripos($ticket, 'CCTV-P-') === 0;
$table = $isPemindahan ? 'dbo.Form_Pemindahan_CCTV' : 'dbo.Form_Pengajuan_CCTV';

$stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM {$table} WHERE ticket = ?", [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die('Data CCTV tidak ditemukan');
}

function pe($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function pdate($value)
{
    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return '-';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $parts = explode('-', $value);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return pe($value);
}

function presolve($path)
{
    $path = trim((string)($path ?? ''));
    if ($path === '') {
        return '';
    }
    $candidates = [$path];
    $trimmed = ltrim(str_replace('\\', '/', $path), '/');
    $candidates[] = __DIR__ . '/../../' . $trimmed;
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/' . $trimmed;
    }
    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function pdataUri($path)
{
    $file = presolve($path);
    if ($file === '') {
        return '';
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'gif') $mime = 'image/gif';
    elseif ($ext === 'webp') $mime = 'image/webp';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
}

function psig($rows, $role)
{
    if (empty($rows[$role]['SignaturePath'])) {
        return '&nbsp;';
    }
    $src = pdataUri($rows[$role]['SignaturePath']);
    $signed = pe($rows[$role]['SignedByUserName'] ?? '');
    if ($src === '') {
        return '<small>' . $signed . '</small>';
    }
    return '<img src="' . $src . '" style="max-height:55px;display:block;margin:0 auto;" alt="TTD"><br><small>' . $signed . '</small>';
}

function pimg($path)
{
    $src = pdataUri($path);
    if ($src === '') {
        return '<div style="color:#6c757d;">-</div>';
    }
    return '<img src="' . $src . '" style="max-width:100%;max-height:180px;object-fit:contain;display:block;margin:0 auto;" alt="lampiran">';
}

$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
    sqlsrv_free_stmt($ttdStmt);
}

$logoPath = __DIR__ . '/../../dist/img/sumlogo.png';
$logo = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 10mm 12mm 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }
        .border { border: 1px solid #000; }
        .pad td { padding: 4px 6px; }
        .head td { padding: 6px 8px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .titlebar { background: #1f4e79; color: #fff; text-align: center; font-weight: bold; padding: 6px 4px; }
        .section { background: #eef5ff; font-weight: bold; padding: 5px 8px; border: 1px solid #000; margin-top: 8px; }
        .label { width: 32%; white-space: nowrap; }
        .lampiran { width: 49%; border: 1px solid #000; padding: 8px; }
        .lampiran-title { font-weight: bold; margin-bottom: 6px; }
        .sign-table td { border: 1px solid #000; text-align: center; padding: 5px 6px; }
        .sign-row { height: 82px; }
        .footer td { border: 1px solid #000; padding: 5px 6px; }
    </style>
</head>
<body>
    <table class="border" style="border-bottom:none;">
        <tr class="head">
            <td style="width:90px;border-right:1px solid #000;text-align:center;">
                <?php if ($logo !== ''): ?><img src="<?= $logo ?>" width="60" alt="logo"><?php endif; ?>
            </td>
            <td style="line-height:1.35;">
                <div style="font-size:12pt;font-weight:bold;">PT. SURYA USAHA MANDIRI</div>
                Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                Banjaran &ndash; Kab. Bandung<br>
                40377 Telp. (022) 594-0313
            </td>
        </tr>
        <tr><td colspan="2" class="titlebar"><?= $isPemindahan ? 'PEMINDAHAN KAMERA CCTV' : 'PENGAJUAN CCTV' ?></td></tr>
    </table>

    <table class="border pad" style="border-top:none;table-layout:fixed;">
        <tr>
            <td class="label">Ticket</td>
            <td>: <?= pe($row['ticket']) ?></td>
            <td style="width:30%;text-align:right;">Tgl Pengajuan : <?= pdate($row['tgl_pengajuan'] ?? '') ?></td>
        </tr>
        <tr><td class="label">Nama Pemohon</td><td colspan="2">: <?= pe($row['nama_pemohon'] ?? '') ?></td></tr>
        <tr><td class="label">Jabatan</td><td colspan="2">: <?= pe($row['jabatan'] ?? '') ?></td></tr>
        <tr><td class="label">Departemen</td><td colspan="2">: <?= pe($row['departemen'] ?? '') ?></td></tr>
        <tr><td class="label">Area</td><td colspan="2">: <?= pe($row['area'] ?? '') ?></td></tr>
        <tr><td class="label">Deskripsi Permintaan</td><td colspan="2">: <?= nl2br(pe($row['deskripsi_user'] ?? '')) ?></td></tr>
    </table>

    <?php if ($isPemindahan): ?>
        <div class="section">Lampiran Pengaju</div>
        <table style="width:100%;table-layout:fixed;">
            <tr>
                <td class="lampiran" style="width:49%;"><div class="lampiran-title">Lampiran Sebelum</div><?= pimg($row['lampiran_sebelum'] ?? '') ?></td>
                <td style="width:2%;"></td>
                <td class="lampiran" style="width:49%;"><div class="lampiran-title">Lampiran Setelah</div><?= pimg($row['lampiran_setelah'] ?? '') ?></td>
            </tr>
        </table>

        <div class="section">Dokumen 1 - Diisi oleh Staf IT</div>
        <table class="border pad" style="border-top:none;table-layout:fixed;">
            <tr><td class="label">Tanggal Diterima</td><td colspan="2">: <?= pdate($row['tgl_pengajuan'] ?? '') ?></td></tr>
            <tr><td class="label">Tanggal Pengerjaan</td><td colspan="2">: <?= pdate($row['tanggal_pengerjaan'] ?? '') ?></td></tr>
            <tr><td class="label">Deskripsi Solusi</td><td colspan="2">: <?= nl2br(pe($row['Opsi_deskripsi_solusi'] ?? '')) ?></td></tr>
        </table>
    <?php else: ?>
        <div class="section">Dokumen Pengajuan</div>
        <table class="border pad" style="border-top:none;table-layout:fixed;">
            <tr><td class="label">Keterangan</td><td colspan="2">: <?= nl2br(pe($row['keterangan'] ?? '')) ?></td></tr>
        </table>
    <?php endif; ?>

    <div class="section">Tanda Tangan</div>
    <table class="sign-table" style="table-layout:fixed;">
        <tr class="bold"><td>Di Ajukan Oleh</td><td>Di Ketahui Oleh</td><td>Di Ketahui Oleh</td><td>Mengetahui</td><td>Di Setujui Oleh</td></tr>
        <tr class="sign-row"><td><?= psig($ttd, 'Pemohon') ?></td><td><?= psig($ttd, 'Atasan Pemohon') ?></td><td><?= psig($ttd, 'Petugas CCTV') ?></td><td><?= psig($ttd, 'Kabag IT') ?></td><td><?= psig($ttd, 'Kadept IT') ?></td></tr>
        <tr class="bold"><td>Pemohon</td><td>Atasan Pemohon</td><td>Petugas CCTV</td><td>Kabag IT</td><td>Kadept IT</td></tr>
    </table>

    <table class="border footer" style="border-top:none;font-size:9pt;">
        <tr><td style="width:70%;border-right:1px solid #000;">SUM-FM-IT-010</td><td style="width:30%;text-align:center;">CCTV</td></tr>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();
$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();
if (ob_get_length()) {
    ob_end_clean();
}
$download = isset($_GET['download']) && $_GET['download'] == '1';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="PENGAJUAN CCTV - ' . $ticket . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
