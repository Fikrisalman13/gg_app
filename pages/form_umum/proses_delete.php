<?php
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket is required for delete!']);
    exit;
}

// Include database connection
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'message' => 'File koneksi database tidak ditemukan!']);
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi ke database gagal!']);
    exit;
}

// Start transaction
sqlsrv_begin_transaction($conn);

// 1. Delete signatures in Form_Umum_TTD
$sqlDeleteTTD = "DELETE FROM Form_Umum_TTD WHERE Ticket = ?";
$stmtTTD = sqlsrv_query($conn, $sqlDeleteTTD, [$ticket]);
if ($stmtTTD === false) {
    $error = sqlsrv_errors();
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data TTD: ' . print_r($error, true)]);
    exit;
}

// Verifikasi izin CanDelete server-side (Requirement 10.6)
$groupId = $_SESSION['GroupId'] ?? 0;
$menuId  = 1293; // FORM_UMUM_MENU_ID
$sqlPerm  = "SELECT CanDelete FROM SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmtPerm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
if ($stmtPerm === false) {
    sqlsrv_rollback($conn);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Gagal memeriksa izin akses.']);
    exit;
}
$rowPerm = sqlsrv_fetch_array($stmtPerm, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtPerm);
// Administrator (GroupId = 1) selalu diizinkan
if ($groupId != 1 && (!$rowPerm || !$rowPerm['CanDelete'])) {
    sqlsrv_rollback($conn);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus data ini.']);
    exit;
}

// 2. Delete the ticket from the appropriate table
$isClosing = stripos($ticket, 'CLS-') === 0 || stripos($ticket, 'UCLS-') === 0;
$isIzinKeluar = stripos($ticket, 'IKP-') === 0 || stripos($ticket, 'IKS-') === 0;
$isIPC = stripos($ticket, 'IPC-') === 0;

if ($isClosing) {
    $sql = "DELETE FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
} elseif ($isIzinKeluar) {
    $sqlDetail = "DELETE FROM Form_Umum_Izin_Keluar_Pabrik_Detail WHERE ticket = ?";
    $stmtDetail = sqlsrv_query($conn, $sqlDetail, [$ticket]);
    if ($stmtDetail === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data karyawan.']);
        exit;
    }
    sqlsrv_free_stmt($stmtDetail);
    $sql = "DELETE FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
} elseif ($isIPC) {
    $sql = "DELETE FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
} else {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Jenis tiket tidak dikenali!']);
    exit;
}

$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if ($stmt === false) {
    $error = sqlsrv_errors();
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data tiket: ' . print_r($error, true)]);
    exit;
}

$rowsAffected = sqlsrv_rows_affected($stmt);
if ($rowsAffected === 0) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan atau sudah dihapus!']);
    exit;
}

// Commit transaction
sqlsrv_commit($conn);

sqlsrv_free_stmt($stmtTTD);
sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus!', 'ticket' => $ticket]);
?>
