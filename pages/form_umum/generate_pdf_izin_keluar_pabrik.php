<?php
/**
 * PDF Generator â€“ Surat Izin Keluar Sementara (IKS / IKP)
 * Mengikuti pola generate_pdf_buka_tanggal_closingan.php:
 * - Border rapi: outer table + inner td menggunakan pola konsisten
 * - Jarak spacing sebelum TTD
 * - QR Code untuk verifikasi tiket
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require '../../vendor/autoload.php';
use Dompdf\Dompdf;

date_default_timezone_set('Asia/Jakarta');
require '../../koneksi.php';

// â”€â”€ Validasi session â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!isset($_SESSION['UserName'])) {
    ob_end_clean();
    http_response_code(403);
    die('Silakan login terlebih dahulu.');
}

// â”€â”€ Validasi parameter ticket â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (empty($ticket)) {
    ob_end_clean();
    http_response_code(400);
    die('Parameter ticket diperlukan');
}

// â”€â”€ Query data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sql = "SELECT * FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    ob_end_clean();
    http_response_code(404);
    die('Tiket tidak ditemukan');
}
sqlsrv_free_stmt($stmt);

$employees = [];
$detailStmt = sqlsrv_query($conn, "SELECT nik, nama_pemohon, departemen, bagian, jabatan, no_hp FROM Form_Umum_Izin_Keluar_Pabrik_Detail WHERE ticket = ? ORDER BY id", [$ticket]);
if ($detailStmt) {
    while ($employee = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) $employees[] = $employee;
    sqlsrv_free_stmt($detailStmt);
}
if (!$employees) {
    $employees[] = [
        'nik' => $row['nik'] ?? '', 'nama_pemohon' => $row['nama_pemohon'] ?? '',
        'departemen' => $row['departemen'] ?? '', 'bagian' => $row['bagian'] ?? '',
        'jabatan' => $row['jabatan'] ?? '', 'no_hp' => $row['no_hp'] ?? '',
    ];
}

// â”€â”€ Deteksi IKS vs IKP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$isIKS = str_starts_with($ticket, 'IKS-');
function iksRequiresKadeptIT($ticket, $tglPengajuan)
{
    if (stripos($ticket, 'IKS-') !== 0) return false;
    if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
    $time = strtotime((string)$tglPengajuan);
    return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}
$iksNeedsKadeptIT = iksRequiresKadeptIT($ticket, $row['tgl_pengajuan'] ?? null);

// â”€â”€ Ambil TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$ttd = [];
$ttdStmt = sqlsrv_query(
    $conn,
    "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Umum_TTD WHERE Ticket = ?",
    [$ticket]
);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
    sqlsrv_free_stmt($ttdStmt);
}

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function iksFmtDate($d)
{
    if ($d instanceof DateTime)
        return $d->format('d-m-Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
        $p = explode('-', substr($d, 0, 10));
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    return $d ?: '-';
}

function iksVal($v)
{
    $v = trim((string) $v);
    return ($v === '' || $v === '-') ? '-' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// â”€â”€ Ambil gambar TTD sebagai base64 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function iksSigCell($ttd, $role)
{
    if ($role === 'Kadept') {
        foreach (['Kadept', 'Kadept IT', 'Kadept ACC'] as $kadeptRole) {
            if (isset($ttd[$kadeptRole]) && !empty($ttd[$kadeptRole]['SignaturePath'])) {
                $role = $kadeptRole;
                break;
            }
        }
    }
    if (!isset($ttd[$role]) || empty($ttd[$role]['SignaturePath']))
        return '&nbsp;';

    $path = $ttd[$role]['SignaturePath'];
    $signed = htmlspecialchars($ttd[$role]['SignedByUserName'] ?? '');
    $abs = null;

    if (file_exists($path))
        $abs = $path;
    elseif (isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $path))
        $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
    elseif (file_exists(__DIR__ . '/../../' . ltrim($path, '/')))
        $abs = __DIR__ . '/../../' . ltrim($path, '/');
    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . ltrim($path, '/')))
        $abs = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . ltrim($path, '/');

    if ($abs) {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        $img = base64_encode(file_get_contents($abs));
        return "<img src='data:{$mime};base64,{$img}'
                     style='max-height:55px;display:block;margin:0 auto;' alt='TTD'>
                <br><small>{$signed}</small>";
    }
    return "<small>{$signed}</small>";
}

// â”€â”€ Logo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$logoPath = __DIR__ . '/../../dist/img/sumlogo.png';
$logoTag = '';
if (file_exists($logoPath)) {
    $logoTag = "<img src='data:image/png;base64," . base64_encode(file_get_contents($logoPath)) . "' width='60'>";
}

// â”€â”€ QR Code (chillerlan/php-qrcode v6, SVG â€“ no GD required) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$qrTag = '';
try {
    if (class_exists('\chillerlan\QRCode\QRCode')) {
        $qrUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . '/gg_app/pages/form_umum/scan_action.php?ticket=' . urlencode($ticket);

        $options = new \chillerlan\QRCode\QROptions;
        $options->outputType = \chillerlan\QRCode\Output\QRMarkupSVG::class;
        $options->imageBase64 = true;  // returns "data:image/svg+xml;base64,..."

        $svgDataUri = (new \chillerlan\QRCode\QRCode($options))->render($qrUrl);
        $qrTag = "<img src='{$svgDataUri}' style='width:70px;height:70px;display:block;margin:0 auto;'>";
    }
} catch (\Throwable $e) {
    // QR tidak tersedia â€“ lanjut tanpa QR
}

// â”€â”€ Kolom TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// IKS lama: 3 kolom; IKS baru: 4 kolom (Pemohon, Atasan, Kadept IT, HRD)
// IKP : 4 kolom (Pemohon, Atasan, Personalia, DanRu Satpam)
$ttdCols = $isIKS
    ? ($iksNeedsKadeptIT
        ? [
            ['role' => 'Pemohon', 'header' => 'Pemohon', 'label' => 'Pemohon'],
            ['role' => 'Atasan Pemohon', 'header' => 'Mengetahui', 'label' => 'Atasan Pemohon'],
            ['role' => 'Kadept', 'header' => 'Mengetahui', 'label' => 'Kadept'],
            ['role' => 'HRD', 'header' => 'Menyetujui', 'label' => 'HRD'],
        ]
        : [
            ['role' => 'Pemohon', 'header' => 'Pemohon', 'label' => 'Pemohon'],
            ['role' => 'Atasan Pemohon', 'header' => 'Mengetahui', 'label' => 'Atasan Pemohon'],
            ['role' => 'HRD', 'header' => 'Menyetujui', 'label' => 'HRD'],
        ])
    : [
        ['role' => 'Pemohon', 'header' => 'Pemohon', 'label' => 'Pemohon'],
        ['role' => 'Atasan Pemohon', 'header' => 'Mengetahui', 'label' => 'Atasan Pemohon'],
        ['role' => 'Personalia', 'header' => 'Mengetahui', 'label' => 'Personalia'],
        ['role' => 'DanRu Satpam', 'header' => 'Menyetujui', 'label' => 'DanRu SATPAM'],
    ];

$totalTtdCols = count($ttdCols);
$colWidthTtd = round(100 / $totalTtdCols, 2);

// â”€â”€ Build HTML â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$html = "<html><head><style>
body  { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 0; }
table { border-collapse: collapse; width: 100%; }
td    { vertical-align: top; padding: 2px 4px; }
.border { border: 1px solid #000; }
.center { text-align: center; }
.bold   { font-weight: bold; }
.data-row td { padding: 2px 2px; line-height: 1.4; }
.data-row td.label-cell { padding: 2px 4px 2px 10px; }
.data-row td.data-cell { padding: 2px 0 2px 0; }
.sign td { height: 80px; vertical-align: middle; text-align: center; }
</style></head><body>";

// â”€â”€ Kop Surat â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Kop: border PENUH (termasuk bawah). Section berikut pakai border-top:none
// agar tidak double. Ini berbeda dengan closingan yg kop-nya 2 baris.
$html .= "<table class='border'><tr>";
$html .= "<table class='border'><tr>";
$html .= "  <td style='width:90px;border-right:1px solid #000;text-align:center;padding:8px;vertical-align:middle;'>{$logoTag}</td>";
$html .= "  <td style='padding:6px 10px;vertical-align:middle;text-align:center;'>";
$html .= "    <div style='font-size:10pt;'>PT. SURYA USAHA MANDIRI</div>";
$html .= "    <div style='font-size:9pt;'>FORMULIR</div>";
$html .= "    <div style='font-size:13pt;font-weight:bold;line-height:1.3;margin-top:2px;'>SURAT IZIN KELUAR SEMENTARA</div>";
$html .= "  </td>";
$html .= "  <td style='width:50px;border-left:1px solid #000;padding:4px;text-align:center;vertical-align:middle;'>{$qrTag}</td>";
$html .= "</tr></table>";

// â”€â”€ Header Info: Nomor & Tanggal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// border-top:none (kop sudah punya border-bottom), border-bottom:none (data lanjutkan)
$html .= "<table style='width:100%;border-collapse:collapse;border-left:1px solid #000;border-right:1px solid #000;'>";
$html .= "<tr>";
$html .= "  <td style='padding:6px 10px;font-size:10pt;'><b>Nomor Pengajuan:</b> " . iksVal($row['ticket']) . "</td>";
$html .= "  <td style='padding:6px 10px;text-align:right;font-size:10pt;'><b>Tanggal Pengajuan:</b> " . iksVal(iksFmtDate($row['tgl_pengajuan'] ?? '')) . "</td>";
$html .= "</tr>";
$html .= "</table>";

// â”€â”€ Data Pemohon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// border kiri/kanan saja; TTD table yg tutup border bawah
$html .= "<table style='width:100%;border-collapse:collapse;border-left:1px solid #000;border-right:1px solid #000;table-layout:fixed;'>";

$html .= "<tr><td colspan='4' style='padding:6px 10px;font-size:10pt;font-style:italic;'>";
$html .= "<u>Diberikan kepada tersebut di bawah ini:</u>";
$html .= "</td></tr>";

// Pre-calc keperluan & kendaraan
$kep = $row['keperluan'] ?? '';
$kepLain = $row['keperluan_lain'] ?? '';
$kepDisplay = ($kep === 'Lainnya' && $kepLain) ? "Lainnya: $kepLain" : ($kep ?: '-');
$kend = $row['kendaraan'] ?? '';
$kendLain = $row['kendaraan_lain'] ?? '';
$kendDisplay = ($kend === 'Lainnya' && $kendLain) ? "Lainnya: $kendLain" : ($kend ?: '-');

// Employee table spans full form width to prevent Dompdf column collapse.
$employeeHtml = "<table style='width:100%;border-collapse:collapse;table-layout:fixed;font-size:9pt;'>";
$employeeHtml .= "<tr style='background:#f1f3f5;'>";
$employeeHtml .= "<th style='width:7%;border:1px solid #adb5bd;padding:3px;text-align:center;'>No</th>";
$employeeHtml .= "<th style='width:35%;border:1px solid #adb5bd;padding:3px;text-align:left;'>Nama Karyawan</th>";
$employeeHtml .= "<th style='width:21%;border:1px solid #adb5bd;padding:3px;text-align:left;'>NIK</th>";
$employeeHtml .= "<th style='width:17%;border:1px solid #adb5bd;padding:3px;text-align:left;'>Jabatan</th>";
$employeeHtml .= "<th style='width:20%;border:1px solid #adb5bd;padding:3px;text-align:left;'>No. HP</th></tr>";
foreach ($employees as $employeeIndex => $employee) {
    $employeeHtml .= "<tr>";
    $employeeHtml .= "<td style='border:1px solid #adb5bd;padding:3px;text-align:center;'>" . ($employeeIndex + 1) . "</td>";
    $employeeHtml .= "<td style='border:1px solid #adb5bd;padding:3px;'>" . iksVal($employee['nama_pemohon'] ?? '-') . "</td>";
    $employeeHtml .= "<td style='border:1px solid #adb5bd;padding:3px;white-space:nowrap;'>" . iksVal($employee['nik'] ?? '-') . "</td>";
    $employeeHtml .= "<td style='border:1px solid #adb5bd;padding:3px;'>" . iksVal($employee['jabatan'] ?? '-') . "</td>";
    $employeeHtml .= "<td style='border:1px solid #adb5bd;padding:3px;white-space:nowrap;'>" . iksVal($employee['no_hp'] ?? '-') . "</td></tr>";
}
$employeeHtml .= "</table>";
$html .= "<tr class='data-row'><td colspan='4' style='padding:4px 8px 8px;'><b>Daftar Karyawan (" . count($employees) . " orang)</b><br>" . $employeeHtml . "</td></tr>";

// Shared trip details; employee-specific Jabatan and No HP stay in employee table.
$html .= "<tr class='data-row'>";
$html .= "  <td class='label-cell' style='width:20%;'><b>Departemen</b></td>";
$html .= "  <td class='data-cell' style='width:30%;'>: " . iksVal($row['departemen']) . "</td>";
$html .= "  <td class='label-cell' style='width:20%;'><b>Estimasi Kembali</b></td>";
$html .= "  <td class='data-cell' style='width:30%;'>: " . iksVal($row['estimasi_kembali']) . "</td>";
$html .= "</tr>";
$html .= "<tr class='data-row'>";
$html .= "  <td class='label-cell'><b>Bagian</b></td>";
$html .= "  <td class='data-cell'>: " . iksVal($row['bagian']) . "</td>";
$html .= "  <td class='label-cell'><b>Tujuan</b></td>";
$html .= "  <td class='data-cell'>: " . nl2br(iksVal($row['tujuan'])) . "</td>";
$html .= "</tr>";
$html .= "<tr class='data-row'>";
$html .= "  <td class='label-cell'><b>Tanggal Keluar</b></td>";
$html .= "  <td class='data-cell'>: " . iksVal(iksFmtDate($row['tgl_keluar'])) . "</td>";
$html .= "  <td class='label-cell'><b>Keperluan</b></td>";
$html .= "  <td class='data-cell'>: " . iksVal($kepDisplay) . "</td>";
$html .= "</tr>";
$html .= "<tr class='data-row'>";
$html .= "  <td class='label-cell' style='padding-bottom:15px;'><b>Jam Keluar</b></td>";
$html .= "  <td class='data-cell' style='padding-bottom:15px;'>: " . iksVal($row['jam_keluar']) . "</td>";
$html .= "  <td class='label-cell' style='padding-bottom:15px;'><b>Kendaraan</b></td>";
$html .= "  <td class='data-cell' style='padding-bottom:15px;'>: " . iksVal($kendDisplay) . "</td>";
$html .= "</tr>";

$html .= "</table>";

// â”€â”€ Tabel TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Pola sama persis dengan closingan: outer table class='border center',
// setiap td punya border:1px solid #000, border-top:none pada outer
$html .= "<table class='border center' style='border-top:none;border-collapse:collapse;table-layout:fixed;width:100%;'>";
$html .= "<colgroup>";
for ($c = 0; $c < $totalTtdCols; $c++) {
    $html .= "<col style='width:{$colWidthTtd}%;'/>";
}
$html .= "</colgroup>";

// Header row
$html .= "<tr class='bold' style='font-size:9pt;'>";
if ($iksNeedsKadeptIT) {
    $html .= "<td style='border:1px solid #000;padding:4px;'>Pemohon</td>";
    $html .= "<td colspan='2' style='border:1px solid #000;padding:4px;'>Mengetahui</td>";
    $html .= "<td style='border:1px solid #000;padding:4px;'>Menyetujui</td>";
} else {
    foreach ($ttdCols as $col) {
        $html .= "<td style='border:1px solid #000;padding:4px;'>{$col['header']}</td>";
    }
}
$html .= "</tr>";

// Signature row
$html .= "<tr class='sign'>";
foreach ($ttdCols as $col) {
    $html .= "<td style='border:1px solid #000;'>" . iksSigCell($ttd, $col['role']) . "</td>";
}
$html .= "</tr>";

// Label row
$html .= "<tr class='bold' style='font-size:9pt;'>";
foreach ($ttdCols as $col) {
    $html .= "<td style='border:1px solid #000;padding:4px;'>{$col['label']}</td>";
}
$html .= "</tr>";

$html .= "</table>";

// â”€â”€ Footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Sama persis dengan pola closingan
$html .= "<table cellspacing='0' cellpadding='0' style='width:100%;border-collapse:collapse;table-layout:fixed;font-size:9pt;'>";
$html .= "<tr>";
$html .= "  <td style='border-left:1px solid #000;border-bottom:1px solid #000;border-right:1px solid #000;padding:4px;text-align:center;font-weight:bold;'>SUM-FM-HRD-011</td>";
$html .= "  <td style='border-right:1px solid #000;border-bottom:1px solid #000;padding:4px;text-align:center;font-weight:bold;'>ERP</td>";
$html .= "</tr>";
$html .= "</table>";

// â”€â”€ QR Code section (pojok bawah, posisi absolut) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($qrTag) {
    $html .= "<div style='position:fixed;bottom:20px;right:20px;text-align:center;font-size:7pt;color:#555;'>";
    $html .= $qrTag;
    $html .= "<br>Scan untuk verifikasi";
    $html .= "</div>";
}

$html .= "</body></html>";

// â”€â”€ Render PDF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$dompdf = new Dompdf([
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => false,
]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

// â”€â”€ Download atau Preview â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_GET['download']) && $_GET['download'] == '1') {
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Surat_Izin_Keluar_Sementara_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $ticket) . '.pdf"');
    echo $pdf;
    exit;
}

ob_end_clean();

$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon'] ?? '');
$deptEsc = htmlspecialchars($row['departemen'] ?? '');

echo "<!DOCTYPE html>
<html><head>
<meta charset='UTF-8'>
<title>Preview PDF â€“ Surat Izin Keluar Sementara</title>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css'>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css'>
<style>
body { background:#f4f4f4; padding:20px; }
.container { background:#fff; padding:20px; border-radius:8px;
             box-shadow:0 2px 6px rgba(0,0,0,.1); max-width:1200px; margin:0 auto; }
.btn { display:inline-block; padding:8px 16px; border-radius:4px; text-decoration:none; color:#fff; cursor:pointer; }
.btn-dl   { background:#28a745; }
.btn-back { background:#6c757d; margin-left:8px; }
object { width:100%; height:800px; border:1px solid #ccc; }
.ticket-info { background-color:#e7f3ff; border-left:4px solid #2196F3; padding:10px 15px; margin-bottom:20px; }
.ticket-info p { margin:5px 0; }
</style>
<script>function downloadPDF(){ window.location='?ticket={$ticketEsc}&download=1'; }</script>
</head>
<body>
<div class='container'>
  <h3><i class='fas fa-file-pdf text-danger'></i> Preview Surat Izin Keluar Sementara</h3>
  <div class='ticket-info'>
    <p><strong>No. Pengajuan:</strong> {$ticketEsc}</p>
    <p><strong>Pemohon:</strong> {$namaEsc}</p>
    <p><strong>Departemen:</strong> {$deptEsc}</p>
  </div>
  <object type='application/pdf' data='data:application/pdf;base64,{$b64}'></object>
  <div style='margin-top:15px;'>
    <a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a>
    <a href='list_form.php' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a>
  </div>
</div>
</body></html>";

