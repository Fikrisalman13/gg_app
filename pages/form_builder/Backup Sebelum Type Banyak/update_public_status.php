<?php
session_start();
header('Content-Type: application/json');

require_once '../../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi berakhir. Silakan login ulang.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Metode tidak diizinkan.'
    ]);
    exit();
}

function formBuilderSupportsPublicColumn($conn) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $sqlCheck = "SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Form_Dynamic_Templates') AND name = 'is_public'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    $hasColumn = ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) ? true : false;
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }

    return $hasColumn;
}

if (!formBuilderSupportsPublicColumn($conn)) {
    echo json_encode([
        'success' => false,
        'message' => 'Kolom publik belum tersedia pada database.'
    ]);
    exit();
}

$templateId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
$isPublic = isset($_POST['is_public']) ? (int) ($_POST['is_public'] ? 1 : 0) : 0;

if ($templateId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Template tidak valid.'
    ]);
    exit();
}

$sqlCheck = "SELECT id, created_by FROM Form_Dynamic_Templates WHERE id = ? AND is_active = 1";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$templateId]);
$template = $stmtCheck ? sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) : null;

if (!$template) {
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Template tidak ditemukan atau sudah tidak aktif.'
    ]);
    exit();
}
if ($stmtCheck) {
    sqlsrv_free_stmt($stmtCheck);
}

// Permission Check: Admin (GroupId 1) or Creator
$currentUserName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
$isAdmin = ($_SESSION['GroupId'] == 1);
$isCreator = ($template['created_by'] === $currentUserName);

if (!$isAdmin && !$isCreator) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda tidak memiliki hak untuk mengubah status publik form ini.'
    ]);
    exit();
}

$sqlUpdate = "UPDATE Form_Dynamic_Templates SET is_public = ? WHERE id = ?";
$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$isPublic, $templateId]);

if ($stmtUpdate === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memperbarui status publik.'
    ]);
    exit();
}

sqlsrv_free_stmt($stmtUpdate);

echo json_encode([
    'success' => true,
    'message' => 'Status publik berhasil diperbarui.',
    'is_public' => $isPublic
]);
exit();
