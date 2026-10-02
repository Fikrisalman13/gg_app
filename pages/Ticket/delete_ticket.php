<?php
// ===================================================
// 1. INISIALISASI & VALIDASI AKSES
// ===================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserId']) || !$conn) {
  echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
  exit;
}

$sessionUserName = $_SESSION['UserName'] ?? ($_SESSION['NamaLengkap'] ?? 'system');

$ticketNoInput = trim($_POST['ticket_no'] ?? '');
$ticketIdInput = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if ($ticketNoInput === '' && $ticketIdInput <= 0) {
  echo json_encode(['ok'=>false,'error'=>'Bad ticket reference']);
  exit;
}

// ===================================================
// 2. PENCARIAN DATA TICKET
// ===================================================
$ticketRow = null;
if ($ticketNoInput !== '') {
  $stmt = sqlsrv_query($conn, "SELECT ticket_id, ticket_no FROM dbo.tickets WHERE ticket_no = ?", [ $ticketNoInput ]);
  if ($stmt) { $ticketRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC); sqlsrv_free_stmt($stmt); }
} else {
  $stmt = sqlsrv_query($conn, "SELECT ticket_id, ticket_no FROM dbo.tickets WHERE ticket_id = ?", [ $ticketIdInput ]);
  if ($stmt) { $ticketRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC); sqlsrv_free_stmt($stmt); }
}

if (!$ticketRow) {
  echo json_encode(['ok'=>false,'error'=>'Ticket not found']);
  exit;
}

$ticketId = intval($ticketRow['ticket_id']);
$ticketNo = trim((string)$ticketRow['ticket_no']);

if ($ticketId <= 0 || $ticketNo === '') {
  echo json_encode(['ok'=>false,'error'=>'Ticket data invalid']);
  exit;
}

// Slug folder untuk menghapus file lampiran
$ticketFolderSlug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ticketNo);
if ($ticketFolderSlug === '') { $ticketFolderSlug = 'ticket_' . $ticketId; }

// ===================================================
// 3. HELPER: HAPUS DIREKTORI REKURSIF
// ===================================================
/**
 * Menghapus folder ticket beserta seluruh isi lampiran secara aman.
 */
function ticket_rrmdir($dirPath) {
  if (!is_dir($dirPath)) { return; }
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($files as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    @$todo($fileinfo->getRealPath());
  }
  @rmdir($dirPath);
}

// ===================================================
// 4. EKSEKUSI PENGHAPUSAN
// ===================================================
try {
  sqlsrv_begin_transaction($conn);

  // 1. Recursive Delete Folders (new slug + legacy id directory)
  $baseUpload = __DIR__ . '/../../uploads/tickets/';
  ticket_rrmdir($baseUpload . $ticketFolderSlug);
  if ($ticketFolderSlug !== (string)$ticketId) {
    ticket_rrmdir($baseUpload . $ticketId);
  }

  // 2. Revert asset status if ticket linked to any asset
  $assetIds = [];
  $stmtAssets = sqlsrv_query($conn, "SELECT id_asset FROM dbo.ticket_assets WHERE ticket_id = ?", [ $ticketId ]);
  if ($stmtAssets) {
    while ($rowAsset = sqlsrv_fetch_array($stmtAssets, SQLSRV_FETCH_ASSOC)) {
      $assetId = intval($rowAsset['id_asset'] ?? 0);
      if ($assetId > 0) { $assetIds[] = $assetId; }
    }
    sqlsrv_free_stmt($stmtAssets);
  }

  foreach ($assetIds as $assetId) {
    $revert = sqlsrv_query($conn, "UPDATE dbo.m_asset SET id_status = 1, upddate = GETDATE(), upduser = ? WHERE id_asset = ?", [ $sessionUserName, $assetId ]);
    if ($revert === false) {
      throw new Exception('Gagal mengembalikan status asset');
    }

    $historyNote = 'Ticket ' . $ticketNo . ' dihapus, status asset dikembalikan ke Used';
    $hist = sqlsrv_query($conn, "INSERT INTO dbo.asset_history (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at) VALUES (?, 'Maintenance', 'Used', ?, 'status asset', ?, GETDATE())", [ $assetId, $historyNote, $sessionUserName ]);
    if ($hist === false) {
      throw new Exception('Gagal mencatat riwayat asset');
    }
  }

  // 3. Delete from DB
  sqlsrv_query($conn, "DELETE FROM dbo.ticket_messages WHERE ticket_id = ?", [ $ticketId ]);
  sqlsrv_query($conn, "DELETE FROM dbo.ticket_attachments WHERE ticket_id = ?", [ $ticketId ]);
  sqlsrv_query($conn, "DELETE FROM dbo.ticket_assets WHERE ticket_id = ?", [ $ticketId ]);
  
  $del = sqlsrv_query($conn, "DELETE FROM dbo.tickets WHERE ticket_id = ?", [ $ticketId ]);
  if ($del === false) { sqlsrv_rollback($conn); echo json_encode(['ok'=>false,'error'=>'Delete failed']); exit; }
  
  sqlsrv_commit($conn);
  echo json_encode(['ok'=>true]);
} catch(Exception $e) {
  if ($conn) { sqlsrv_rollback($conn); }
  echo json_encode(['ok'=>false,'error'=>'Exception']);
}
?>