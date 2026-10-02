<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
require '../../vendor/autoload.php';
use Dompdf\Dompdf;
require '../../koneksi.php';
require_once __DIR__ . '/items_parser.php';
require_once __DIR__ . '/approval_helper.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (!$ticket)
    die('Ticket tidak diberikan');

$sql = "SELECT * FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)))
    die('Data tidak ditemukan');

// --- Validasi: PDF hanya bisa diexport jika status Approve (khusus GroupId 1 / Admin tetap diizinkan walau TTD belum komplit) ---
$status = strtolower(trim($row['status_ticket'] ?? ''));
$isAdmin = (isset($_SESSION['GroupId']) && (int) $_SESSION['GroupId'] === 1);
if (!$isAdmin && !in_array($status, ['approved', 'unclosing', 'closed'], true)) {
    $approvalFlow = closinganApprovalFlow($row);
    $ttdCount = closinganSignedSlotCount($conn, $ticket, $approvalFlow['required_roles']);
    $req = count($approvalFlow['required_roles']);

    if ($ttdCount < $req) {
        die('PDF hanya dapat diunduh setelah seluruh approval dan tanda tangan lengkap (Status: Approve).');
    }
}

function fmtDate($d)
{
    if ($d instanceof DateTime)
        return $d->format('d-m-Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        $p = explode('-', $d);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (is_string($d) && !empty($d))
        return date('d-m-Y', strtotime($d));
    return '-';
}
function clean($t, $len = 40)
{
    $t = trim($t ?? '');
    return $t === "" ? "<span style='display:inline-block;border-bottom:0.5px solid #000;width:{$len}ch;'>&nbsp;</span>" : htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}
function parseGudangTransaksi($text)
{
    $result = ['gudang' => '-', 'transaksi' => '-', 'nomor_transaksi' => '-', 'jenis_transaksi' => '-'];
    $text = trim((string) $text);
    if ($text === '')
        return $result;

    $parts = array_map('trim', explode(',', $text));
    $gudang = [];
    $transaksi = [];
    foreach ($parts as $part) {
        if ($part === '')
            continue;
        if (preg_match('/^(Procurement|Sales|Cash Management)\s*[:\-]\s*(.*)$/i', $part, $mPart)) {
            $result['jenis_transaksi'] = trim($mPart[1]);
            $trxText = trim($mPart[2]);
            if (preg_match('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', $trxText, $mNo)) {
                $result['nomor_transaksi'] = trim($mNo[1]);
                $trxText = trim(preg_replace('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', '', $trxText));
            }
            $transaksi[] = $trxText;
        } else {
            $gudang[] = $part;
        }
    }

    if (!empty($gudang))
        $result['gudang'] = implode(', ', $gudang);
    if (!empty($transaksi))
        $result['transaksi'] = implode(', ', $transaksi);
    return $result;
}

// Ambil TTD dari Form_Umum_TTD
$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Umum_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
}

$approvalFlow = closinganApprovalFlow($row);
$roleMengetahui = $approvalFlow['mengetahui'];
$roleDisetujui = $approvalFlow['disetujui'];
$totalCols = 2 + count($roleMengetahui) + count($roleDisetujui);
$colWidth = $totalCols > 0 ? round(100 / $totalCols, 2) : 25;
$requestFlags = closinganRequestFlags($row);
$showGudang = $requestFlags['has_gudang'];
$showTransaksi = $requestFlags['has_transaksi'];
$itemInfoColspan = 1 + ($showGudang ? 1 : 0) + ($showTransaksi ? 4 : 0);

function sigCell($ttd, $role)
{
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

    if ($abs) {
        $img = base64_encode(file_get_contents($abs));
        return "<img src='data:image/png;base64,$img' style='max-height:55px;display:block;margin:0 auto;'><br><small>$signed</small>";
    }
    return "<img src='" . htmlspecialchars($path) . "' style='max-height:55px;display:block;margin:0 auto;'><br><small>$signed</small>";
}

$logoPath = "../../dist/img/sumlogo.png";
$logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
$logoTag = $logoB64 ? "<img src='data:image/png;base64,$logoB64' width='60'>" : "";

$html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:2px 6px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.sign td{height:80px;vertical-align:middle;text-align:center;}
</style></head><body>";

// Header
$html .= "<table class='border' style='border-bottom:none;'><tr>";
$html .= "<td width='15%' style='border-right:1px solid #000;text-align:center;padding:10px;'>" . $logoTag . "</td>";
$html .= "<td style='padding-left:5px; line-height:1.3; font-size:9pt;'><b>PT. SURYA USAHA MANDIRI</b><br>Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>Banjaran – Kab. Bandung<br>40377 Telp. (022) 594-0313</td></tr>";
$html .= "<tr><td colspan='2' class='center bold' style='border-top:1px solid #000;border-bottom:1px solid #000;padding:8px;'>FORM KOMUNIKASI BUKA TANGGAL CLOSINGAN</td></tr></table>";

// Request options
$selectedOptions = [];
if ($showGudang)
    $selectedOptions[] = "Closingan Gudang";
if ($showTransaksi)
    $selectedOptions[] = "Closingan Transaksi";
$requestText = empty($selectedOptions) ? '-' : implode(' Dan ', $selectedOptions);
$items = parseItemsFromGudangTransaksi($row['gudang_transaksi'] ?? '');
$parsedGT = !empty($items) ? $items[0] : ['gudang' => '-', 'transaksi' => '-', 'nomor_transaksi' => '-', 'jenis_transaksi' => '-'];
$roleKadeptAcc = isset($ttd['Kadept ACC']) ? 'Kadept ACC' : 'Acc Audit';

// Details
$html .= "<table class='border' style='border-top:none; border-bottom:none; table-layout:fixed; width:100%;'>";
$html .= "<tr><td width='25%'>Nama Pemohon</td><td width='2%'>:</td><td colspan='2'>" . clean($row['nama_pemohon']) . "</td></tr>";
$html .= "<tr><td>Jabatan</td><td>:</td><td width='35%'>" . clean($row['jabatan']) . "</td><td width='38%' style='text-align:right;'>Tgl Pengajuan &nbsp;&nbsp; : &nbsp; " . fmtDate($row['tgl_pengajuan']) . "</td></tr>";
$html .= "<tr><td>Departemen</td><td>:</td><td colspan='2'>" . clean($row['departemen']) . "</td></tr>";
$html .= "<tr><td>Mengajukan permintaan</td><td>:</td><td colspan='2'>" . $requestText . "</td></tr>";
if (count($items) >= 2) {
    $html .= "<tr><td colspan='4' style='padding:4px 6px; padding-bottom:30px;'>";
    $html .= "<table style='width:100%; border-collapse:collapse; border:1px solid #ccc; font-size:8pt;'>";
    $html .= "<thead><tr style='background:#f5f5f5; font-weight:bold;'>";
    $html .= "<td style='border:1px solid #ccc; padding:3px 5px; text-align:center; width:20px;'>No</td>";
    $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>Buka Tgl</td>";
    if ($showGudang) {
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>Gudang</td>";
    }
    if ($showTransaksi) {
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>Jenis Transaksi</td>";
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>Transaksi</td>";
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>No. Transaksi</td>";
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>Vendor/Cust</td>";
    }
    $html .= "</tr></thead><tbody>";
    foreach ($items as $idx => $item) {
        $html .= "<tr style='background:#fff;'>";
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px; text-align:center; vertical-align:middle;' rowspan='2'>" . ($idx + 1) . "</td>";
        $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['buka_tgl'] ? fmtDate($item['buka_tgl']) : '-') . "</td>";
        if ($showGudang) {
            $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['gudang'] ?: '-') . "</td>";
        }
        if ($showTransaksi) {
            $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['jenis_transaksi'] ?: '-') . "</td>";
            $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['transaksi'] ?: '-') . "</td>";
            $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['nomor_transaksi'] ?: '-') . "</td>";
            $html .= "<td style='border:1px solid #ccc; padding:3px 5px;'>" . htmlspecialchars($item['vendor_cust'] ?: '-') . "</td>";
        }
        $html .= "</tr>";
        $html .= "<tr style='background:#fafafa;'>";
        $html .= "<td colspan='{$itemInfoColspan}' style='border:1px solid #ccc; padding:3px 5px; font-size:7.5pt; color:#555;'><b>Keterangan/Alasan:</b> " . nl2br(htmlspecialchars($item['keterangan'] ?: '-')) . "</td>";
        $html .= "</tr>";
    }
    $html .= "</tbody></table></td></tr>";
} else {
    $html .= "<tr><td>Buka Tgl</td><td>:</td><td colspan='2'>" . fmtDate($row['buka_tgl']) . "</td></tr>";
    if ($showGudang) {
        $html .= "<tr><td>Gudang</td><td>:</td><td colspan='2'>" . clean($parsedGT['gudang'] ?? '-') . "</td></tr>";
    }
    if ($showTransaksi) {
        $html .= "<tr><td>Jenis Transaksi</td><td>:</td><td colspan='2'>" . clean($parsedGT['jenis_transaksi'] ?? '-') . "</td></tr>";
        $html .= "<tr><td>Transaksi</td><td>:</td><td colspan='2'>" . clean($parsedGT['transaksi'] ?? '-') . "</td></tr>";
        $html .= "<tr><td>No. Transaksi</td><td>:</td><td colspan='2'>" . clean($parsedGT['nomor_transaksi'] ?? '-') . "</td></tr>";
        $html .= "<tr><td>Vendor/Cust</td><td>:</td><td colspan='2'>" . clean($parsedGT['vendor_cust'] ?? '-') . "</td></tr>";
    }
    $html .= "<tr><td>Keterangan/Alasan</td><td>:</td><td colspan='2' style='padding-bottom:15px;'>" . nl2br(clean($row['keterangan'], 80)) . "</td></tr>";

}
$html .= "</table>";



// Signature table - dynamic columns
$html .= "<table class='border center' style='width:100%; border-collapse:collapse; table-layout:fixed;'>";
$html .= "<colgroup>";
for ($c = 0; $c < $totalCols; $c++) {
    $html .= "<col style='width:{$colWidth}%;'/>";
}
$html .= "</colgroup>";

// Header row
$html .= "<tr class='bold' style='font-size:9pt;'>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Diajukan oleh,</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Diketahui oleh,</td>";
if (!empty($roleMengetahui)) {
    $colspan = count($roleMengetahui);
    $html .= "<td style='border:1px solid #000; padding:4px;' colspan='{$colspan}'>Mengetahui,</td>";
}
if (!empty($roleDisetujui)) {
    $colspan = count($roleDisetujui);
    $html .= "<td style='border:1px solid #000; padding:4px;' colspan='{$colspan}'>Disetujui oleh,</td>";
}
$html .= "</tr>";

// Signature row
$html .= "<tr class='sign'>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Pemohon') . "</td>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Atasan Pemohon') . "</td>";
foreach ($roleMengetahui as $rm) {
    // Map role name for sigCell - handle Kadept ACC fallback
    $sigRole = $rm;
    if ($rm === 'Kadept ACC' && !isset($ttd['Kadept ACC'])) {
        if (isset($ttd['Acc Audit'])) {
            $sigRole = 'Acc Audit';
        } elseif (isset($ttd['Kadept'])) {
            $sigRole = 'Kadept';
        }
    }
    $html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, $sigRole) . "</td>";
}
foreach ($roleDisetujui as $rd) {
    $sigRole = $rd;
    if ($rd === 'Kadept ACC' && !isset($ttd['Kadept ACC'])) {
        if (isset($ttd['Acc Audit'])) {
            $sigRole = 'Acc Audit';
        } elseif (isset($ttd['Kadept'])) {
            $sigRole = 'Kadept';
        }
    }
    $html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, $sigRole) . "</td>";
}
$html .= "</tr>";

// Label row
$html .= "<tr class='bold' style='font-size:9pt;'>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Pemohon</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Atasan Pemohon</td>";
foreach ($roleMengetahui as $rm) {
    $html .= "<td style='border:1px solid #000; padding:4px;'>" . htmlspecialchars($rm) . "</td>";
}
foreach ($roleDisetujui as $rd) {
    $html .= "<td style='border:1px solid #000; padding:4px;'>" . htmlspecialchars($rd) . "</td>";
}
$html .= "</tr></table>";

// Footer
$footerSumColspan = max(1, $totalCols - 1);
$html .= "<table cellspacing='0' cellpadding='0' style='width:100%; border-collapse:collapse; table-layout:fixed; font-size:9pt;'>";
$html .= "<colgroup>";
for ($c = 0; $c < $totalCols; $c++) {
    $html .= "<col style='width:{$colWidth}%;'>";
}
$html .= "</colgroup>";
$html .= "<tr>";
$html .= "<td colspan='{$footerSumColspan}' class='center bold' style='border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px solid #000; padding:4px;'>SUM-FM-UMU-001</td>";
$html .= "<td class='center bold' style='border-right:1px solid #000; border-bottom:1px solid #000; padding:4px;'>ERP</td>";
$html .= "</tr></table>";

$html .= "</body></html>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Form Closingan - ' . $ticket . '.pdf"');
    echo $pdf;
    exit;
}

ob_end_clean();
$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon'] ?? '');
$deptEsc = htmlspecialchars($row['departemen'] ?? '');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Preview PDF Buka Closingan</title>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css'>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css'>
<style>
body{background:#f4f4f4;padding:20px;}
.container{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1); max-width:1200px; margin:0 auto;}
.btn{display:inline-block;padding:8px 16px;border-radius:4px;text-decoration:none;color:#fff;}
.btn-dl{background:#28a745;}
.btn-back{background:#6c757d;margin-left:8px;}
object{width:100%;height:800px;border:1px solid #ccc;}
.ticket-info{background-color:#e7f3ff;border-left:4px solid #2196F3;padding:10px 15px;margin-bottom:20px;}
.ticket-info p{margin:5px 0;}
</style>
<script>function downloadPDF(){window.location='?ticket=$ticketEsc&download=1';}</script></head>
<body><div class='container'>
<h3><i class='fas fa-file-pdf text-danger'></i> Preview Form Buka Tanggal Closingan</h3>
<div class='ticket-info'>
  <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
  <p><strong>Pemohon:</strong> $namaEsc</p>
  <p><strong>Departemen:</strong> $deptEsc</p>
</div>
<object type='application/pdf' data='data:application/pdf;base64,$b64'></object>
<div style='margin-top:15px;'>
  <a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a>
  <a href='list_form.php' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a>
</div>
</div></body></html>";
?>