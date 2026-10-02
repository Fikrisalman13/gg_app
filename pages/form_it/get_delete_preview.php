<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../koneksi.php';

function respond($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Metode tidak diizinkan.']);
}
if (!isset($_SESSION['UserName'], $_SESSION['GroupId'])) {
    respond(401, ['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
if (!$conn) {
    respond(500, ['success' => false, 'message' => 'Layanan database tidak tersedia.']);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['form_it_csrf_token']) || !hash_equals($_SESSION['form_it_csrf_token'], $csrfToken)) {
    respond(403, ['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
}

$ticket = trim((string) ($_POST['ticket'] ?? ''));
if ($ticket === '' || strlen($ticket) > 100) {
    respond(422, ['success' => false, 'message' => 'Nomor pengajuan tidak valid.']);
}

$stmtPermission = sqlsrv_query($conn, "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = 134", [(int) $_SESSION['GroupId']]);
$permission = $stmtPermission ? sqlsrv_fetch_array($stmtPermission, SQLSRV_FETCH_ASSOC) : null;
if ($stmtPermission) {
    sqlsrv_free_stmt($stmtPermission);
}
if (!$permission || (int) $permission['CanDelete'] !== 1) {
    respond(403, ['success' => false, 'message' => 'Anda tidak memiliki hak untuk menghapus form.']);
}

$sql = "SELECT TOP 1 a.issue_id, e.nama_lengkap AS technician_name, i.status AS issue_status
        FROM dbo.Form_IT_Assignees a
        LEFT JOIN dbo.m_emp e ON e.id_emp = a.assigned_to
        LEFT JOIN dbo.issues i ON i.issue_id = a.issue_id
        WHERE a.ticket_no = ?
        ORDER BY a.id DESC";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if ($stmt === false) {
    respond(500, ['success' => false, 'message' => 'Gagal memeriksa keterkaitan pengajuan.']);
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$row) {
    respond(200, ['success' => true, 'data' => ['state' => 'unassigned', 'technician_name' => null, 'issue_status' => null]]);
}

$issueStatus = trim((string) ($row['issue_status'] ?? ''));
$state = $issueStatus === '' ? 'orphan' : 'assigned';
if (strcasecmp($issueStatus, 'Done') === 0) {
    $state = 'done';
}

respond(200, [
    'success' => true,
    'data' => [
        'state' => $state,
        'technician_name' => $row['technician_name'] ?? null,
        'issue_status' => $issueStatus !== '' ? $issueStatus : null,
    ],
]);
