<?php
// ============================================================
// export_issues_pdf.php  —  mPDF-based PDF export
// Menggunakan mPDF (bukan DomPDF) karena mendukung WriteHTML chunked
// sehingga tidak perlu buffer seluruh tabel sekaligus di RAM.
// ============================================================
ini_set('memory_limit', '512M');
set_time_limit(300);

session_start();
date_default_timezone_set('Asia/Jakarta');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $permissions = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 162);
if ($permissions['CanView'] != 1) {
    die('Anda tidak memiliki hak untuk mengekspor data ini.');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/gg_app/vendor/autoload.php');

use Mpdf\Mpdf;

// ---- Filter parameters ----
$scope              = strtolower($_GET['scope'] ?? 'active');
$filterType         = trim((string)($_GET['filterType'] ?? ''));
$filterKategori     = trim((string)($_GET['filterKategori'] ?? ''));
$filterSubKategori  = trim((string)($_GET['filterSubKategori'] ?? ''));
$filterTanggalStart = trim((string)($_GET['filterTanggalStart'] ?? ''));
$filterTanggalEnd   = trim((string)($_GET['filterTanggalEnd'] ?? ''));

// ---- Build WHERE clause ----
$conditions = [];
$params     = [];

if ($scope === 'done') {
    $conditions[] = "UPPER(LTRIM(RTRIM(status))) IN ('DONE','CLOSED')";
} elseif ($scope === 'active') {
    $conditions[] = "(status IS NULL OR UPPER(LTRIM(RTRIM(status))) NOT IN ('DONE','CLOSED'))";
}

if ($filterType !== '') {
    $conditions[] = "UPPER(issue_type) = UPPER(?)";
    $params[] = $filterType;
}
if ($filterKategori !== '') {
    $conditions[] = "kategori = ?";
    $params[] = $filterKategori;
}
if ($filterSubKategori !== '') {
    $conditions[] = "sub_kategori = ?";
    $params[] = $filterSubKategori;
}

if ($filterTanggalStart !== '' && $filterTanggalEnd !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($s && $e) {
        $conditions[] = "CAST(created_at AS DATE) BETWEEN ? AND ?";
        $params[] = $s->format('Y-m-d');
        $params[] = $e->format('Y-m-d');
    }
} elseif ($filterTanggalStart !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    if ($s) { $conditions[] = "CAST(created_at AS DATE) >= ?"; $params[] = $s->format('Y-m-d'); }
} elseif ($filterTanggalEnd !== '') {
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($e) { $conditions[] = "CAST(created_at AS DATE) <= ?"; $params[] = $e->format('Y-m-d'); }
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ---- Date range label ----
$dateRangeLabel = 'Semua Data';
if ($filterTanggalStart !== '' && $filterTanggalEnd !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($s && $e) $dateRangeLabel = 'Periode: ' . $s->format('d/m/Y') . ' - ' . $e->format('d/m/Y');
} elseif ($filterTanggalStart !== '') {
    $s = DateTime::createFromFormat('Y-m-d', $filterTanggalStart);
    if ($s) $dateRangeLabel = 'Dari: ' . $s->format('d/m/Y');
} elseif ($filterTanggalEnd !== '') {
    $e = DateTime::createFromFormat('Y-m-d', $filterTanggalEnd);
    if ($e) $dateRangeLabel = 'Sampai: ' . $e->format('d/m/Y');
}

$reportTitle = ($scope === 'active') ? 'Laporan Issues Active' : 'Laporan Issues';

// ---- Query ----
$sql = "SELECT issue_id, issue_name, issue_type, kategori, sub_kategori,
               created_by, status, priority, due_date, tanggal_selesai, created_at
        FROM dbo.issues $whereClause
        ORDER BY created_at ASC";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die('Gagal mengambil data issues: ' . print_r(sqlsrv_errors(), true));
}

// ---- mPDF setup ----
$mpdf = new Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4-L',   // Landscape
    'margin_left'   => 8,
    'margin_right'  => 8,
    'margin_top'    => 15,
    'margin_bottom' => 12,
    'margin_header' => 8,
    'margin_footer' => 8,
]);

$mpdf->SetTitle($reportTitle);
$mpdf->SetAuthor('GG App');
$mpdf->showImageErrors = false;



// ---- CSS & table header (ditulis SEKALI ke mPDF) ----
$css = '
body        { font-family: sans-serif; font-size: 9px; color: #111; }
h2          { text-align: center; font-size: 13px; margin: 0 0 3px 0; }
.meta       { text-align: center; font-size: 9px; color: #555; margin-bottom: 10px; }
table       { width: 100%; border-collapse: collapse; }
th, td      { border: 0.3mm solid #555; padding: 4px 5px; vertical-align: top; }
th          { background-color: #e8e8e8; font-weight: bold; text-align: center; }
tr.even     { background-color: #f7f7f7; }
tr.odd      { background-color: #ffffff; }
';

$headerHtml  = '<style>' . $css . '</style>';
$headerHtml .= '<h2>' . htmlspecialchars($reportTitle, ENT_QUOTES) . '</h2>';
$headerHtml .= '<div class="meta">' . htmlspecialchars($dateRangeLabel, ENT_QUOTES) . '</div>';
$headerHtml .= '<table>';
$headerHtml .= '<thead><tr>
    <th style="width:5%">No</th>
    <th style="width:9%">Type</th>
    <th style="width:10%">Kategori</th>
    <th style="width:10%">Sub Kategori</th>
    <th style="width:22%">Issue Name</th>
    <th style="width:10%">Nama</th>
    <th style="width:7%">Status</th>
    <th style="width:7%">Priority</th>
    <th style="width:8%">Tgl Dibuat</th>
    <th style="width:7%">Due Date</th>
    <th style="width:5%">Tgl Selesai</th>
</tr></thead><tbody>';

// Tulis header tabel ke mPDF (SATU kali, tidak buffer seluruh data)
$mpdf->WriteHTML($headerHtml);

// ---- Helper: parse date ----
function parseSqlDate($value, string $outFormat = 'd/m/Y H:i'): string {
    if (empty($value)) return '-';
    if ($value instanceof DateTime) return $value->format($outFormat);
    $str = (string)$value;
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $str);
        if ($dt !== false) return $dt->format($outFormat);
    }
    $cleaned = preg_replace('/[^0-9\-:\/\s]/', '', $str);
    $dt = DateTime::createFromFormat('Y-m-d H:i', trim(substr($cleaned, 0, 16)));
    if ($dt !== false) return $dt->format($outFormat);
    return htmlspecialchars($str, ENT_QUOTES);
}

// ---- Dynamic date strings (dihitung SEKALI di luar loop) ----
$indoMonths = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$today     = new DateTime();
$strToday  = $today->format('d') . ' ' . $indoMonths[(int)$today->format('n')] . ' ' . $today->format('Y');
$yesterday = clone $today;
$yesterday->modify('-1 day');
$strYesterday = $yesterday->format('d') . ' ' . $indoMonths[(int)$yesterday->format('n')] . ' ' . $yesterday->format('Y');

// ---- Stream rows dalam batch kecil (HEMAT RAM) ----
$counter   = 1;
$hasData   = false;
$batchSize = 50;   // tulis ke mPDF setiap 50 baris
$batchHtml = '';

while ($record = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $hasData  = true;
    $rowClass = ($counter % 2 === 0) ? 'even' : 'odd';

    $issueName = str_replace(
        ['{DATE}', '{DATE-1}'],
        [$strToday, $strYesterday],
        $record['issue_name'] ?? '-'
    );

    $dueDate = '-';
    if (!empty($record['due_date'])) {
        $dueDate = parseSqlDate($record['due_date'], 'd/m/Y');
    }

    $batchHtml .= '<tr class="' . $rowClass . '">'
        . '<td style="text-align:center">' . $counter . '</td>'
        . '<td>' . htmlspecialchars($record['issue_type'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td>' . htmlspecialchars($record['kategori'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td>' . htmlspecialchars($record['sub_kategori'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td>' . htmlspecialchars($issueName, ENT_QUOTES) . '</td>'
        . '<td>' . htmlspecialchars($record['created_by'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td style="text-align:center">' . htmlspecialchars($record['status'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td style="text-align:center">' . htmlspecialchars($record['priority'] ?? '-', ENT_QUOTES) . '</td>'
        . '<td>' . parseSqlDate($record['created_at']) . '</td>'
        . '<td style="text-align:center">' . $dueDate . '</td>'
        . '<td>' . parseSqlDate($record['tanggal_selesai']) . '</td>'
        . '</tr>';

    $counter++;

    // Flush batch ke mPDF, bebaskan RAM
    if (($counter % $batchSize) === 0) {
        $mpdf->WriteHTML($batchHtml);
        $batchHtml = '';
        gc_collect_cycles();
    }
}

// Flush sisa batch
if ($batchHtml !== '') {
    $mpdf->WriteHTML($batchHtml);
    $batchHtml = '';
}

if (!$hasData) {
    $mpdf->WriteHTML('<tr><td colspan="11" style="text-align:center;padding:10px;">Tidak ada data sesuai filter.</td></tr>');
}

$mpdf->WriteHTML('</tbody></table>');

// Bebaskan resource DB sebelum generate PDF
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
gc_collect_cycles();

// ---- Output PDF ----
$filename = 'issues_' . $scope . '_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
exit;
?>