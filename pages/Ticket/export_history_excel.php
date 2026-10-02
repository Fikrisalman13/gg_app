<?php
/**
 * Export Excel Riwayat Ticket Selesai
 * Menggunakan metode Native HTML Table (Tanpa Library)
 */
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Check authentication
if (!isset($_SESSION['UserId'])) {
    http_response_code(403);
    die('Unauthorized');
}

// Get filter parameters
$tanggalMulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggalAkhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';

// Function to format date indo
function indoDate($dateObj) {
    if (!$dateObj || !($dateObj instanceof DateTime)) return '-';
    
    static $bln = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    
    return $dateObj->format('d') . ' ' . $bln[(int)$dateObj->format('m')] . ' ' . $dateObj->format('Y');
}

// Function to get Priority Text
function getPriorityText($p) {
    if ($p == 1) return 'Low';
    if ($p == 3) return 'High';
    return 'Normal';
}

// Visibility filter
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
    $baseWhere = ' WHERE (' . implode(' OR ', $clauses) . ') AND t.closed_at IS NOT NULL';
  } else {
    $baseWhere = ' WHERE 1=0';
    $params = [];
  }
}

// Build SQL query
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

// Output filename
$filename = 'Riwayat_Ticket_' . date('Y-m-d_His') . '.xls';

// Set Headers for Excel (Native HTML Method)
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Transfer-Encoding: binary");

// Start HTML output
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
/* CSS for Excel Table */
body { font-family: Arial, sans-serif; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #000; padding: 5px; vertical-align: top; }
th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
.title { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
.center { text-align: center; }
</style>
</head>
<body>


<div class="title">RIWAYAT TICKET SELESAI</div>
<?php
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
?>
<div class="center" style="margin-bottom:10px; font-weight: bold;"><?php echo $periodText; ?></div>

<table>
  <thead>
    <tr>
      <th style="width:50px; background-color: #f2f2f2;">No</th>
      <th style="width:120px; background-color: #f2f2f2;">Ticket No</th>
      <th style="width:200px; background-color: #f2f2f2;">Pemohon</th>
      <th style="width:150px; background-color: #f2f2f2;">Departemen</th>
      <th style="width:300px; background-color: #f2f2f2;">Subject</th>
      <th style="width:100px; background-color: #f2f2f2;">Priority</th>
      <th style="width:150px; background-color: #f2f2f2;">Assign To</th>
      <th style="width:150px; background-color: #f2f2f2;">Tanggal Dibuat</th>
      <th style="width:150px; background-color: #f2f2f2;">Tanggal Selesai</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($tickets)): ?>
    <tr>
        <td colspan="9" class="center">Tidak ada data</td>
    </tr>
    <?php else: ?>
        <?php $no = 1; foreach ($tickets as $ticket): 
            $priority = getPriorityText($ticket['priority']);
            $createdDate = $ticket['created_at'] ? indoDate($ticket['created_at']) : '-';
            $closedDate = $ticket['closed_at'] ? indoDate($ticket['closed_at']) : '-';
            
            // Background color for priority
            $priorityStyle = '';
            if ($ticket['priority'] == 1) $priorityStyle = 'background-color: #d4edda; color: #155724;'; // Low
            if ($ticket['priority'] == 3) $priorityStyle = 'background-color: #f8d7da; color: #721c24; font-weight:bold;'; // High
            
        ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td class="center" style="mso-number-format:'\@';"><?php echo htmlspecialchars($ticket['ticket_no']); ?></td>
            <td><?php echo htmlspecialchars($ticket['creator_name']); ?></td>
            <td><?php echo htmlspecialchars($ticket['creator_dept']); ?></td>
            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
            <td class="center" style="<?php echo $priorityStyle; ?>"><?php echo $priority; ?></td>
            <td><?php echo htmlspecialchars($ticket['assigned_name'] ?? '-'); ?></td>
            <td><?php echo $createdDate; ?></td>
            <td><?php echo $closedDate; ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>
