<?php
// ===================================================
// SELF-CLAIM TICKET (CONCURRENCY-SAFE)
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

// Check authentication
if (!isset($_SESSION['UserId'])) {
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$userId = intval($_SESSION['UserId']);
$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if ($ticketId <= 0) {
  echo json_encode(['success' => false, 'error' => 'Invalid ticket ID']);
  exit;
}

// Get user's EmpId and Department
$sqlUser = "SELECT u.EmpId, g.GroupId, d.dept AS department_name
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            WHERE u.UserId = ?";
$stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
$userRow = $stmtUser ? sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC) : null;
if ($stmtUser) sqlsrv_free_stmt($stmtUser);

if (!$userRow) {
  echo json_encode(['success' => false, 'error' => 'User not found']);
  exit;
}

$userEmpId = intval($userRow['EmpId'] ?? 0);
$userGroupId = intval($userRow['GroupId'] ?? 0);
$userDept = trim($userRow['department_name'] ?? '');

// Check if user is IT staff (Department = 'Information Technology' or GroupId = 1 for Admin)
$isITStaff = (stripos($userDept, 'Information Technology') !== false) || (strtoupper($userDept) === 'IT') || ($userGroupId === 1);

if (!$isITStaff) {
  echo json_encode(['success' => false, 'error' => 'Only IT staff can claim tickets']);
  exit;
}

if ($userEmpId <= 0) {
  echo json_encode(['success' => false, 'error' => 'Employee ID not found']);
  exit;
}

// Check if Auto Assign mode is enabled
$sqlConfig = "SELECT config_value FROM dbo.ticket_config WHERE config_key = 'auto_assign_mode'";
$stmtConfig = sqlsrv_query($conn, $sqlConfig);
$configRow = $stmtConfig ? sqlsrv_fetch_array($stmtConfig, SQLSRV_FETCH_ASSOC) : null;
if ($stmtConfig) sqlsrv_free_stmt($stmtConfig);

$autoAssignEnabled = ($configRow && $configRow['config_value'] === '1');

if (!$autoAssignEnabled) {
  echo json_encode(['success' => false, 'error' => 'Auto Assign mode is not enabled']);
  exit;
}

try {
  // CONCURRENCY-SAFE UPDATE: Only update if ticket is still unassigned
  $sqlUpdate = "UPDATE dbo.tickets 
                SET assigned_to = ?, updated_at = GETDATE()
                WHERE ticket_id = ? 
                AND (assigned_to IS NULL OR assigned_to = 0)";
  
  $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$userEmpId, $ticketId]);
  
  if (!$stmtUpdate) {
    throw new Exception('Database error during claim');
  }

  // Check how many rows were affected
  $rowsAffected = sqlsrv_rows_affected($stmtUpdate);

  if ($rowsAffected === 0) {
    // Ticket was already claimed by someone else
    echo json_encode([
      'success' => false,
      'error' => 'Ticket sudah diambil teknisi lain'
    ]);
  } else {
    // Successfully claimed
    echo json_encode([
      'success' => true,
      'message' => 'Ticket berhasil diambil'
    ]);
  }

} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
