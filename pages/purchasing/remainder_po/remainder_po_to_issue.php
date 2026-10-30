<?php
/**
 * Endpoint AJAX untuk membuat data Issue Follow Up Pengiriman Purchase Order
 * dari modul Remainder PO ke modul Issues Purchasing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/pages/Issues_purchasing/issue_crud.php';

if (!isset($_SESSION['UserName']) || trim((string) $_SESSION['UserName']) === '') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi ke database SQL Server tidak tersedia.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string) ($_GET['action'] ?? ($_POST['action'] ?? 'add')));

// ─── 1. AKSI CHECK_TODAY: Cek apakah PO sudah pernah dibuatkan issue hari ini ───
if ($action === 'check_today') {
    $ponmbr = trim((string) ($_GET['ponmbr'] ?? ($_POST['ponmbr'] ?? '')));
    if ($ponmbr === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nomor PO tidak valid.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $issueName = 'Follow Up Pengiriman PO ' . $ponmbr;
    $today = date('Y-m-d');

    $sqlCheck = "SELECT COUNT(*) as cnt 
                 FROM dbo.purchasing_issues 
                 WHERE issue_name = ? 
                   AND CAST(created_at AS DATE) = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$issueName, $today]);
    $count = 0;
    if ($stmtCheck && $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        $count = (int) $row['cnt'];
    }
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }

    echo json_encode([
        'success' => true,
        'exists'  => ($count > 0),
        'count'   => $count,
        'ponmbr'  => $ponmbr
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 2. AKSI ADD: Simpan Issue baru ke dbo.purchasing_issues ──────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode permintaan tidak didukung. Gunakan POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ponmbr = trim((string) ($_POST['ponmbr'] ?? ''));
if ($ponmbr === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nomor Purchase Order (PO) wajib diisi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$description = trim((string) ($_POST['description'] ?? ''));
if (mb_strlen($description) > 4000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Panjang deskripsi tidak boleh melebihi 4.000 karakter.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Lookup Category 'Vendor' dan Subkategori 'Follow UP Pengiriman' aktif
$sqlCat = "SELECT c.category_id, s.sub_id 
           FROM dbo.purchasing_issue_categories c 
           INNER JOIN dbo.purchasing_issue_sub_categories s ON s.category_id = c.category_id 
           WHERE UPPER(LTRIM(RTRIM(c.category_name))) = 'VENDOR' 
             AND UPPER(LTRIM(RTRIM(s.sub_name))) = 'FOLLOW UP PENGIRIMAN'
             AND c.is_active = 1 
             AND s.is_active = 1";
$stmtCat = sqlsrv_query($conn, $sqlCat);
$mapping = $stmtCat ? sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC) : false;
if ($stmtCat) {
    sqlsrv_free_stmt($stmtCat);
}

if (!is_array($mapping) || empty($mapping['category_id']) || empty($mapping['sub_id'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Kategori "Vendor" atau Subkategori "Follow UP Pengiriman" tidak aktif/ditemukan di master data.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$categoryId = (int) $mapping['category_id'];
$subId      = (int) $mapping['sub_id'];

// 2. Snapshot Profil Karyawan Aktif
$username = trim((string) $_SESSION['UserName']);
$profile  = purchasingIssueCurrentProfile($conn, $username);

// 3. Transaksi Simpan Issue
if (!sqlsrv_begin_transaction($conn)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memulai transaksi database.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$issueName = 'Follow Up Pengiriman PO ' . $ponmbr;
$today     = date('Y-m-d');
$requestId = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$sqlInsert = "INSERT INTO dbo.purchasing_issues (
        issue_name, issue_type, category_id, sub_id, sub_category_owner_id,
        status, priority, due_date, description, created_at, created_by,
        departemen, bagian, jabatan, request_id, tanggal_selesai
    )
    OUTPUT INSERTED.issue_id
    VALUES (?, 'Task', ?, ?, ?, 'Done', 'Medium', ?, ?, GETDATE(), ?, ?, ?, ?, ?, GETDATE())";

$paramsInsert = [
    $issueName,
    $categoryId,
    $subId,
    $categoryId,
    $today,
    $description !== '' ? $description : null,
    $profile['name'],
    $profile['departemen'] !== '' ? $profile['departemen'] : null,
    $profile['bagian'] !== '' ? $profile['bagian'] : null,
    $profile['jabatan'] !== '' ? $profile['jabatan'] : null,
    $requestId
];

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
$inserted   = $stmtInsert ? sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC) : false;
if ($stmtInsert) {
    sqlsrv_free_stmt($stmtInsert);
}

if (!is_array($inserted) || empty($inserted['issue_id'])) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan Issue ke database.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal melakukan commit transaksi data issue.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'    => true,
    'message'    => 'Issue Follow Up berhasil dicatat di Issues Purchasing (Status: Done)!',
    'issue_id'   => (int) $inserted['issue_id'],
    'issue_name' => $issueName
], JSON_UNESCAPED_UNICODE);
exit;

