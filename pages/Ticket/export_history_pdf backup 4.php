<?php
/**
 * Export PDF Riwayat Ticket Selesai
 * Dengan header PT. SURYA USAHA MANDIRI dan kode dokumen SUM-BLP-IT-004
 */
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Autoload Composer (mPDF)
$autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
if (!$autoload || !is_file($autoload)) {
    http_response_code(500);
    die("Autoload Composer tidak ditemukan. Pastikan vendor/autoload.php ada.");
}
require_once $autoload;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

// Check authentication
if (!isset($_SESSION['UserId'])) {
    http_response_code(403);
    die('Unauthorized');
}

// Get filter parameters
$tanggalMulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggalAkhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';

// Visibility filter: non-admins should only export tickets they created or assigned to
$baseWhere = " WHERE t.closed_at IS NOT NULL";
$params = [];

// Determine user role & emp id
$userId = intval($_SESSION['UserId'] ?? 0);
$userEmpId = 0;
$isAdmin = false;
if ($userId > 0) {
  $sqlRole = "SELECT u.EmpId, g.GroupName FROM dbo.SMUserMs u LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId WHERE u.UserId = ?";
  $stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);
  $roleRow = $stmtRole ? sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC) : null;
  if ($stmtRole) sqlsrv_free_stmt($stmtRole);
  $userEmpId = intval($roleRow['EmpId'] ?? 0);
  $userGroup = trim($roleRow['GroupName'] ?? '');
  $isAdmin = (strcasecmp($userGroup, 'Administrator') === 0);
}

if (!$isAdmin) {
  $clauses = [];
  if ($userEmpId > 0) {
    $clauses[] = '(t.creator_id = ? OR t.assigned_to = ?)';
    $params[] = $userEmpId;
    $params[] = $userEmpId;
  }
  // fallback to username match if available in session
  $userFullname = $_SESSION['NamaLengkap'] ?? '';
  if ($userFullname !== '') {
    $clauses[] = 't.creator_name = ?';
    $params[] = $userFullname;
  }

  if ($clauses) {
    // Replace base WHERE which currently only has closed_at condition
    $baseWhere = ' WHERE (' . implode(' OR ', $clauses) . ') AND t.closed_at IS NOT NULL';
  } else {
    // no identity info available - return empty result
    $baseWhere = ' WHERE 1=0';
    $params = [];
  }
}

// Build SQL query
// Build main SQL with visibility baseWhere and optional date filters
$sql = "SELECT 
            t.ticket_id,
            t.ticket_no,
            t.subject,
            t.priority,
            t.created_at,
            t.closed_at,
            t.creator_name,
            t.creator_dept,
            tech.nama_lengkap as assigned_name,
            ts.status_name
        FROM dbo.tickets t
        LEFT JOIN dbo.m_emp tech ON t.assigned_to = tech.id_emp
  LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id";
$sql .= $baseWhere;

if ($tanggalMulai !== '') {
  $sql .= " AND CAST(t.created_at AS DATE) >= ?";
  $params[] = $tanggalMulai;
}

if ($tanggalAkhir !== '') {
  $sql .= " AND CAST(t.created_at AS DATE) <= ?";
  $params[] = $tanggalAkhir;
}

$sql .= " ORDER BY t.created_at ASC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    die('Database query failed: ' . print_r(sqlsrv_errors(), true));
}

$tickets = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tickets[] = $row;
}
sqlsrv_free_stmt($stmt);

// Helper functions
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function indoDate($dateObj) {
    if (!$dateObj || !($dateObj instanceof DateTime)) return '-';
    
    static $bln = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    
    return $dateObj->format('d') . ' ' . $bln[(int)$dateObj->format('m')] . ' ' . $dateObj->format('Y');
}

function getPriorityText($p) {
    if ($p == 1) return 'Low';
    if ($p == 3) return 'High';
    return 'Normal';
}

// Logo URL
$logo_url = 'http://192.168.7.184:8080/gg_app/dist/img/sumlogo.png';

// Period text for title
$periodText = '';
if ($tanggalMulai !== '' && $tanggalAkhir !== '') {
    $periodText = 'Periode: ' . date('d/m/Y', strtotime($tanggalMulai)) . ' - ' . date('d/m/Y', strtotime($tanggalAkhir));
} elseif ($tanggalMulai !== '') {
    $periodText = 'Dari: ' . date('d/m/Y', strtotime($tanggalMulai));
} elseif ($tanggalAkhir !== '') {
    $periodText = 'Sampai: ' . date('d/m/Y', strtotime($tanggalAkhir));
} else {
    $periodText = 'Semua Data';
}

// CSS for PDF
$css = <<<CSS
@page { margin: 18mm 16mm 18mm 16mm; }
body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9pt;
  color:#111;
}

/* ===== KOP SURAT ===== */
.header-table {
  width: 100%;
  border-bottom: 1px solid #000;
  padding-bottom: 2mm;
  margin-bottom: 4mm;
}
.header-table td {
  vertical-align: top;
}
.logo-img {
  width: 18mm;
  height: auto;
}
.kop-surat {
  text-align: left;
  padding-left: 0.5mm;
}
.kop-surat h2 {
  font-size: 14pt;
  font-weight: bold;
  margin: 0;
  padding: 0;
}
.kop-surat p {
  font-size: 10pt;
  margin: 1mm 0 0 0;
  padding: 0;
  line-height: 1.4;
}

/* ===== KODE DOKUMEN ===== */
.doc-code-container {
  position: absolute;
  top: 12mm;
  right: 16mm;
  font-size: 9pt;
  font-weight: bold;
  color: #000;
}

h3.title {
  font-size: 12pt;
  font-weight: bold;
  margin: 2mm 0 2mm 0;
  text-align: center;
}

.period {
  font-size: 9pt;
  text-align: center;
  margin-bottom: 4mm;
  color: #555;
}

table { border-collapse: collapse; }

.data-table {
  width: 100%;
  border: 0.6pt solid #888;
  font-size: 8pt;
}
.data-table th {
  background: #f2f2f2;
  border: 0.6pt solid #888;
  padding: 4pt;
  font-weight: bold;
  text-align: left;
}
.data-table td {
  border: 0.6pt solid #888;
  padding: 4pt;
  vertical-align: top;
}
.t-center { text-align: center; }
.t-right { text-align: right; }

.badge {
  display: inline-block;
  padding: 2pt 4pt;
  border-radius: 2pt;
  font-size: 7pt;
  font-weight: bold;
}
.badge-low { background: #d4edda; color: #155724; }
.badge-normal { background: #fff3cd; color: #856404; }
.badge-high { background: #f8d7da; color: #721c24; }
.badge-success { background: #d4edda; color: #155724; }
CSS;

// Build HTML
$html = '
<table class="header-table">
  <tr>
    <td style="width: 20%;">
      <img src="'.h($logo_url).'" class="logo-img" alt="Logo" />
    </td>
    <td style="width: 80%;" class="kop-surat">
      <h2>PT. SURYA USAHA MANDIRI</h2>
      <p>
        Jl. Tarajusari No. 9 Kp. Cipeundeuy RT 001 RW 007<br>
        Banjaran - Kab. Bandung<br>
        40377 Telp. (022) 594-0813
      </p>
    </td>
  </tr>
</table>';

// Kode dokumen
$html .= '<div class="doc-code-container"></div>';

$html .= '<h3 class="title">Riwayat Ticket Selesai</h3>';
$html .= '<div class="period">'.h($periodText).'</div>';

// Data table
$html .= '<table class="data-table">
  <thead>
    <tr>
      <th style="width:5%">No</th>
      <th style="width:12%">Ticket No</th>
      <th style="width:15%">Pemohon</th>
      <th style="width:12%">Departemen</th>
      <th style="width:18%">Subject</th>
      <th style="width:8%">Priority</th>
      <th style="width:10%">Assign To</th>
      <th style="width:10%">Tanggal Dibuat</th>
      <th style="width:10%">Tanggal Selesai</th>
    </tr>
  </thead>
  <tbody>';

if (empty($tickets)) {
  $html .= '<tr><td colspan="9" class="t-center">Tidak ada data</td></tr>';
} else {
    $no = 1;
    foreach ($tickets as $ticket) {
        $priority = getPriorityText($ticket['priority']);
        $priorityClass = 'badge-normal';
        if ($ticket['priority'] == 1) $priorityClass = 'badge-low';
        if ($ticket['priority'] == 3) $priorityClass = 'badge-high';
        
    $createdDate = $ticket['created_at'] ? indoDate($ticket['created_at']) : '-';
        $closedDate = $ticket['closed_at'] ? indoDate($ticket['closed_at']) : '-';
        
        $html .= '<tr>
          <td class="t-center">'.h($no).'</td>
          <td>'.h($ticket['ticket_no']).'</td>
          <td>'.h($ticket['creator_name']).'</td>
          <td>'.h($ticket['creator_dept']).'</td>
          <td>'.h($ticket['subject']).'</td>
          <td class="t-center"><span class="badge '.$priorityClass.'">'.h($priority).'</span></td>
          <td>'.h($ticket['assigned_name'] ?? '-').'</td>
          <td>'.h($createdDate).'</td>
          <td>'.h($closedDate).'</td>
        </tr>';
        $no++;
    }
}

$html .= '</tbody></table>';

// Generate PDF
try {
    $mpdf = new Mpdf([
      'format'            => 'A4',
      'orientation'       => 'L', // Landscape for better table fit
      'tempDir'           => sys_get_temp_dir(),
      'default_font'      => 'Arial',
      'default_font_size' => 9,
      'allow_remote_images' => true,
    ]);
} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo '<h2>Gagal inisialisasi mPDF</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$mpdf->WriteHTML('<style>'.$css.'</style>', HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

// Output PDF
$filename = 'Riwayat_Ticket_' . date('Y-m-d_His') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
exit;
