<?php
// ===================================================
// AUTO ASSIGN MODE TOGGLE
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

// Check authentication
if (!isset($_SESSION['UserId'])) {
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

// Check if user is Administrator
$userId = intval($_SESSION['UserId']);
$sqlRole = "SELECT g.GroupId, g.GroupName
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            WHERE u.UserId = ?";
$stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);
$roleRow = $stmtRole ? sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC) : null;
if ($stmtRole) sqlsrv_free_stmt($stmtRole);

$userGroupId = intval($roleRow['GroupId'] ?? 0);
$isAdmin = ($userGroupId === 1); // GroupId 1 = Administrator

if (!$isAdmin) {
  echo json_encode(['success' => false, 'error' => 'Only administrators can toggle Auto Assign mode']);
  exit;
}

// Get the new state
$enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
$enabled = ($enabled === 1) ? 1 : 0;

try {
  // Create table if not exists
  $sqlCreateTable = "
    IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ticket_config')
    BEGIN
      CREATE TABLE dbo.ticket_config (
        config_key NVARCHAR(100) PRIMARY KEY,
        config_value NVARCHAR(255),
        updated_at DATETIME DEFAULT GETDATE(),
        updated_by INT
      )
    END
  ";
  sqlsrv_query($conn, $sqlCreateTable);

  // Check if key exists
  $sqlCheck = "SELECT config_value FROM dbo.ticket_config WHERE config_key = 'auto_assign_mode'";
  $stmtCheck = sqlsrv_query($conn, $sqlCheck);
  $exists = $stmtCheck && sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
  if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);

  if ($exists) {
    // Update existing
    $sqlUpdate = "UPDATE dbo.ticket_config 
                  SET config_value = ?, updated_at = GETDATE(), updated_by = ?
                  WHERE config_key = 'auto_assign_mode'";
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [(string)$enabled, $userId]);
    if (!$stmtUpdate) {
      throw new Exception('Failed to update auto_assign_mode');
    }
  } else {
    // Insert new
    $sqlInsert = "INSERT INTO dbo.ticket_config (config_key, config_value, updated_by) 
                  VALUES ('auto_assign_mode', ?, ?)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, [(string)$enabled, $userId]);
    if (!$stmtInsert) {
      throw new Exception('Failed to insert auto_assign_mode');
    }
  }

  echo json_encode([
    'success' => true,
    'enabled' => $enabled,
    'message' => $enabled ? 'Auto Assign mode enabled' : 'Auto Assign mode disabled'
  ]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
