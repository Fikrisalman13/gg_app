<?php
// ===================================================
// 1. INISIALISASI SERVER-SIDE DATATABLE TICKETS
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/theme_helper.php';

if (!function_exists('checkPermissions')) {
  /**
   * Ambil hak akses untuk menu tertentu.
   */
  function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $permissions = $row;
    }
    if ($stmt !== false) { sqlsrv_free_stmt($stmt); }

    return $permissions;
  }
}

// DataTables server-side for tickets
$draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start  = isset($_POST['start'])  ? intval($_POST['start'])  : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
$viewMode = isset($_POST['view_mode']) ? $_POST['view_mode'] : 'active'; // 'active' or 'history'

// Default ordering
$orderColIdx = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir    = isset($_POST['order'][0]['dir'])    ? strtolower($_POST['order'][0]['dir']) : 'asc';
$orderDir    = ($orderDir === 'desc') ? 'DESC' : 'ASC';

// Columns mapping: align with DataTables columns (leading index is numbering column)
$columns = [
  null,
  't.ticket_id',
  't.creator_name',
  't.creator_dept',
  't.subject',
  't.priority',
  't.assigned_to',
  't.status_id',
  't.created_at'
];
$orderBy = 't.created_at DESC';
if ($orderColIdx >= 0 && $orderColIdx < count($columns) && !empty($columns[$orderColIdx])) {
  $orderBy = $columns[$orderColIdx] . ' ' . $orderDir;
}

/**
 * Menghasilkan badge prioritas agar konsisten di DataTable.
 */
function priority_label($p){
  if ($p == 1) return '<span class="badge bg-success">Low</span>';
  if ($p == 3) return '<span class="badge bg-danger">High</span>';
  return '<span class="badge bg-warning">Normal</span>';
}

/**
 * Membuat badge status berdasarkan nama status ticket.
 */
function get_status_badge($statusName) {
  $n = trim((string)$statusName);
  if ($n === '') $n = 'Open';
  $cls = 'bg-secondary';
  if (strcasecmp($n,'Open')===0) $cls = 'bg-info';
  else if (strcasecmp($n,'Proses')===0 || strcasecmp($n,'In Progress')===0 || strcasecmp($n,'Process')===0) $cls = 'bg-warning text-dark';
  else if (strcasecmp($n,'Selesai')===0 || strcasecmp($n,'Done')===0 || strcasecmp($n,'Closed')===0 || strcasecmp($n,'Complete')===0) $cls = 'bg-success';
  else if (strcasecmp($n,'Cancel')===0 || strcasecmp($n,'Rejected')===0) $cls = 'bg-danger';
  return '<span class="badge '.$cls.'">'.htmlspecialchars($n).'</span>';
}

/**
 * Menentukan label status akhir berdasarkan kolom penting ticket.
 */
function derive_status_label($ticketRow, $conn) {
  if (!empty($ticketRow['closed_at'])) {
    return '<span class="badge bg-success">Selesai</span>';
  }

  $statusName = trim($ticketRow['ticket_status_name'] ?? '');
  if ($statusName !== '') {
    return get_status_badge($statusName);
  }

  if (empty($ticketRow['assigned_to'])) {
    return '<span class="badge bg-info">Open</span>';
  }

  return '<span class="badge bg-warning text-dark">Proses</span>';
}

/**
 * Sanitasi nama tema badge agar hanya menggunakan kelas Bootstrap yang valid.
 */
$result = [
  'draw' => $draw,
  'recordsTotal' => 0,
  'recordsFiltered' => 0,
  'data' => []
];

$latestTicketId = 0;
$latestTicketUpdate = '';

if (!isset($_SESSION['UserId']) || !$conn) {
  echo json_encode($result); exit;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 148);
$canEdit = !empty($permissions['CanEdit']);
$canDelete = !empty($permissions['CanDelete']);

// Determine user role & employee id
$userId = intval($_SESSION['UserId']);
$sqlRole = "SELECT u.EmpId, g.GroupId, g.GroupName, d.dept AS department_name
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            WHERE u.UserId = ?";
$stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);
$roleRow  = $stmtRole ? sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC) : null;
if ($stmtRole) sqlsrv_free_stmt($stmtRole);

$userEmpId   = intval($roleRow['EmpId'] ?? 0);
$userGroupId = intval($roleRow['GroupId'] ?? 0);
$userGroup   = trim($roleRow['GroupName'] ?? '');
$userDept    = trim($roleRow['department_name'] ?? '');
$userName    = $_SESSION['UserName'] ?? '';
$userFullname= $_SESSION['NamaLengkap'] ?? '';
$isAdmin     = ($userGroupId === 1); 
// Check for "Information Technology" or contains "IT" if naming varies, but "Information Technology" is confirmed from DB
$isITStaff   = (stripos($userDept, 'Information Technology') !== false) || (strtoupper($userDept) === 'IT');

// Fetch Auto Assign Mode configuration
$autoAssignMode = false;
$sqlConfig = "SELECT config_value FROM dbo.ticket_config WHERE config_key = 'auto_assign_mode'";
$stmtConfig = sqlsrv_query($conn, $sqlConfig);
$configRow = $stmtConfig ? sqlsrv_fetch_array($stmtConfig, SQLSRV_FETCH_ASSOC) : null;
if ($stmtConfig) sqlsrv_free_stmt($stmtConfig);
$autoAssignMode = ($configRow && $configRow['config_value'] === '1');

// Base visibility filter: non-admins only see tickets they created or are assigned to
$baseWhere  = '';
$baseParams = [];
if (!$isAdmin) {
  $clauses   = [];

  // Utama: berdasarkan EmpId jika ada
  // Utama: user bisa melihat ticket buatannya (UserId) atau yang ditugaskan kepadanya (EmpId)
  if ($userEmpId > 0) {
    $clauses[]   = '(t.creator_id = ? OR t.assigned_to = ?)';
    $baseParams[] = $userId;     // Fix: creator_id is UserId
    $baseParams[] = $userEmpId;  // Fix: assigned_to is EmpId
  } else {
    // Jika tidak punya EmpId, user tetap bisa melihat ticket buatannya sendiri
    $clauses[]    = 't.creator_id = ?';
    $baseParams[] = $userId;
  }

  // Fallback: berdasarkan nama pembuat di tickets jika EmpId kosong atau belum terisi di tiket lama
  if ($userFullname !== '') {
    $clauses[]   = 't.creator_name = ?';
    $baseParams[] = $userFullname;
  }

  // AUTO ASSIGN MODE: IT staff (non-admin) can see unassigned tickets
  if ($autoAssignMode && $isITStaff && !$isAdmin) {
    $clauses[] = '(t.assigned_to IS NULL OR t.assigned_to = 0)';
  }

  if ($clauses) {
    $baseWhere = ' WHERE (' . implode(' OR ', $clauses) . ')';
  } else {
    // Tidak ada info identitas yang bisa dipakai: jangan tampilkan apapun
    $baseWhere  = ' WHERE 1=0';
    $baseParams = [];
  }
}

// Define Status Filter
// We need to join ticket_statuses to filter by status name
$statusJoin = " LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id ";
// Join employee table early so we can search/filter by assigned person's name
$empJoin = " LEFT JOIN dbo.m_emp e ON t.assigned_to = e.id_emp ";
$statusCondition = "";

if ($viewMode === 'history') {
    // History: Closed/Selesai/Cancel
    $statusCondition = " (t.closed_at IS NOT NULL OR ts.status_name IN ('Selesai','Done','Closed','Complete','Cancel','Rejected')) ";
} else {
    // Active: Not Closed AND Not Cancelled
    $statusCondition = " (t.closed_at IS NULL AND (ts.status_name IS NULL OR ts.status_name NOT IN ('Selesai','Done','Closed','Complete','Cancel','Rejected'))) ";
}

// Append status condition to baseWhere
if ($baseWhere === '') {
    $baseWhere = " WHERE " . $statusCondition;
} else {
    $baseWhere .= " AND " . $statusCondition;
}

// Date range filter (for history mode)
$tanggalMulai = isset($_POST['tanggal_mulai']) ? trim($_POST['tanggal_mulai']) : '';
$tanggalAkhir = isset($_POST['tanggal_akhir']) ? trim($_POST['tanggal_akhir']) : '';

if ($viewMode === 'history') {
    if ($tanggalMulai !== '') {
        $baseWhere .= " AND CAST(t.created_at AS DATE) >= ?";
        $baseParams[] = $tanggalMulai;
    }
    if ($tanggalAkhir !== '') {
        $baseWhere .= " AND CAST(t.created_at AS DATE) <= ?";
        $baseParams[] = $tanggalAkhir;
    }
}


try {
  $sqlGlobalMax = "SELECT MAX(t.ticket_id) AS latest_id, MAX(ISNULL(t.updated_at, t.created_at)) AS latest_update
                  FROM dbo.tickets t " . $statusJoin . $empJoin . $baseWhere;
  $stmtGlobalMax = sqlsrv_query($conn, $sqlGlobalMax, $baseParams);
  $rowGlobalMax = $stmtGlobalMax ? sqlsrv_fetch_array($stmtGlobalMax, SQLSRV_FETCH_ASSOC) : ['latest_id'=>0, 'latest_update'=>null];
  if ($stmtGlobalMax) sqlsrv_free_stmt($stmtGlobalMax);

  $latestTicketId = intval($rowGlobalMax['latest_id'] ?? 0);
  if (!empty($rowGlobalMax['latest_update'])) {
    if ($rowGlobalMax['latest_update'] instanceof DateTimeInterface) {
      $latestTicketUpdate = $rowGlobalMax['latest_update']->format('Y-m-d H:i:s');
    } else {
      $latestTicketUpdate = (string)$rowGlobalMax['latest_update'];
    }
  }

  // Total count (respect base visibility + status filter)
  // Note: Must include statusJoin because baseWhere now refers to ts.status_name
  // Note: We don't need latest_id from here anymore for polling purposes
  $sqlTotal = "SELECT COUNT(*) AS cnt FROM dbo.tickets t " . $statusJoin . $empJoin . $baseWhere;
  
  $stmtTotal = sqlsrv_query($conn, $sqlTotal, $baseParams);
  $rowTotal = $stmtTotal ? sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC) : ['cnt'=>0];
  if ($stmtTotal) sqlsrv_free_stmt($stmtTotal);
  
  // Calculate assigned_count for polling sync (Active tickets assigned to this user)
  $assignedCount = 0;
  if ($userEmpId > 0 && !$isAdmin) {
      $sqlCount = "SELECT COUNT(*) AS cnt FROM dbo.tickets t 
                   LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
                   WHERE t.assigned_to = ? 
                   AND (t.closed_at IS NULL AND (ts.status_name IS NULL OR ts.status_name NOT IN ('Selesai','Done','Closed','Complete','Cancel','Rejected')))";
      $stmtCount = sqlsrv_query($conn, $sqlCount, [$userEmpId]);
      if ($stmtCount && $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
          $assignedCount = intval($rowCount['cnt']);
      }
      if ($stmtCount) sqlsrv_free_stmt($stmtCount);
  }
  $recordsTotal = intval($rowTotal['cnt'] ?? 0);
  
  // Filtered count and page data
  $where  = $baseWhere;
  $params = $baseParams;
  if ($search !== '') {
    $searchClause = "(t.ticket_no LIKE ? OR t.subject LIKE ? OR t.creator_name LIKE ? OR t.creator_dept LIKE ? OR e.nama_lengkap LIKE ? OR CAST(t.priority AS VARCHAR(3)) LIKE ? OR LOWER(CASE WHEN t.priority=1 THEN 'low' WHEN t.priority=3 THEN 'high' ELSE 'normal' END) LIKE ? )";
    $like = '%' . $search . '%';
    $searchParams = [ $like, $like, $like, $like, $like, $like, $like ];

    // $where already has content (at least status filter)
    $where .= ' AND ' . $searchClause;
    $params = array_merge($baseParams, $searchParams);
  }

  // Filtered count
  $sqlFiltered = "SELECT COUNT(*) AS cnt FROM dbo.tickets t " . $statusJoin . $empJoin . $where;
  $stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $params);
  $rowFiltered = $stmtFiltered ? sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC) : ['cnt'=>0];
  if ($stmtFiltered) sqlsrv_free_stmt($stmtFiltered);
  $recordsFiltered = intval($rowFiltered['cnt'] ?? 0);

  // Data query with assigned technician and ticket status
  // $sqlData already has the LEFT JOIN for ticket_statuses with alias ts.
  // We can just use $where directly.
  $sqlData = "SELECT t.ticket_id, t.ticket_no, t.subject, t.priority, t.status_id, 
                     t.creator_name, t.creator_dept, t.assigned_to, t.created_at, t.closed_at,
                     e.nama_lengkap as assigned_name,
                     COALESCE(NULLIF(us.Theme, ''), 'secondary') AS assigned_theme,
                     ts.status_name as ticket_status_name
        FROM dbo.tickets t " . $statusJoin . $empJoin . "
      LEFT JOIN dbo.SMUserMs us ON us.EmpId = t.assigned_to" . $where . "
        ORDER BY " . $orderBy . "
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
  $dataParams = $params;
  $dataParams[] = $start;
  $dataParams[] = $length;

  $stmtData = sqlsrv_query($conn, $sqlData, $dataParams);
  $rows = [];
  while ($stmtData && ($r = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC))) {
    $id  = intval($r['ticket_id']);
    $no  = $r['ticket_no'] ?? '';
    $sub = $r['subject'] ?? '';
    $prio= intval($r['priority'] ?? 2);
    $creator = $r['creator_name'] ?? '';
    $creatorDept = $r['creator_dept'] ?? '-';
    $assignedTo = $r['assigned_to'];
    $assignedName = $r['assigned_name'] ?? '-';
    $assignedTheme = ticket_normalize_theme($r['assigned_theme'] ?? '', 'secondary');
    
    $statusLabel = derive_status_label($r, $conn);

    // Actions HTML - detail, edit, delete (keep layout consistent with list_form.php)
    $actionButtons = [];
    $isClosed = !empty($r['closed_at']); // Move definition up
    
    // AUTO ASSIGN MODE: Show "Claim" button for unassigned tickets (IT staff only, not admin)
    $isUnassigned = empty($assignedTo) || intval($assignedTo) === 0;
    if ($autoAssignMode && $isITStaff && !$isAdmin && $isUnassigned && !$isClosed && $viewMode !== 'history') {
      $actionButtons[] = '<button type="button" class="btn btn-success btn-sm js-claim-ticket action-btn" data-id="' . htmlspecialchars((string)$id) . '" title="Ambil"><i class="fas fa-hand-paper"></i> Ambil</button>';
    } else {
      $actionButtons[] = '<a href="detail.php?id=' . htmlspecialchars((string)$id) . '" class="btn btn-info btn-sm action-btn" title="Detail"><i class="fas fa-eye"></i></a>';
    }
    if ($canEdit && !$isClosed && $viewMode !== 'history') {
      $actionButtons[] = '<button type="button" class="btn btn-warning btn-sm js-edit-ticket action-btn" data-id="' . htmlspecialchars((string)$id) . '" title="Edit"><i class="fas fa-edit"></i></button>';
    }
    if ($canDelete) {
      $actionButtons[] = '<button class="btn btn-danger btn-sm js-del action-btn" data-ticket="' . htmlspecialchars($no) . '" data-id="' . htmlspecialchars((string)$id) . '" title="Delete"><i class="fas fa-trash"></i></button>';
    }

    $aksi = '<div class="btn-group btn-group-sm action-button-group" role="group">' . implode('', $actionButtons) . '</div>';

    $rows[] = [
      'ticket_no' => $no,
      'creator_name' => $creator,
      'creator_dept' => $creatorDept,
      'subject' => $sub,
      'priority_label' => priority_label($prio),
      'priority_raw' => $prio,
      'assigned_name' => $assignedName && $assignedName !== '-' 
        ? '<span class="badge bg-' . htmlspecialchars($assignedTheme, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($assignedName, ENT_QUOTES, 'UTF-8') . '</span>'
        : '<span class="badge bg-secondary">-</span>',
      'status_label' => $statusLabel,
      'aksi' => $aksi
    ];
  }
  if ($stmtData) sqlsrv_free_stmt($stmtData);

  $result['recordsTotal'] = $recordsTotal;
  $result['recordsFiltered'] = $recordsFiltered;
  $result['data'] = $rows;
  $result['latest_ticket_id'] = $latestTicketId;
  $result['latest_ticket_update'] = $latestTicketUpdate;
  $result['is_auto_assign_mode'] = $autoAssignMode;
  $result['is_it_staff'] = $isITStaff;
  $result['is_admin'] = $isAdmin;
  $result['user_empid'] = $userEmpId;
  $result['assigned_count_initial'] = $assignedCount;
} catch (Exception $e) {
  // return empty result on error
}

echo json_encode($result);
