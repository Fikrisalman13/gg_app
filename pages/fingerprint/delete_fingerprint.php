<?php
session_start();
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo "Akses ditolak: Anda harus login.";
    exit;
}

// Pastikan hanya bisa diakses via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Metode tidak diizinkan.";
    exit;
}

// Ambil ID dari request
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "ID tidak valid.";
    exit;
}

// Validasi koneksi
if (!$conn) {
    http_response_code(500);
    echo "Koneksi database gagal: " . print_r(sqlsrv_errors(), true);
    exit;
}

// Cek apakah user punya hak delete
define('FINGERPRINT_MENU_ID', 96);

function checkDeletePermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $canDelete = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canDelete = $row['CanDelete'];
        sqlsrv_free_stmt($stmt);
    }
    return $canDelete;
}

if (checkDeletePermission($conn, $_SESSION['GroupId'], FINGERPRINT_MENU_ID) != 1) {
    http_response_code(403);
    echo "Anda tidak memiliki hak untuk menghapus data.";
    exit;
}

// Eksekusi delete
$sql = "DELETE FROM dbo.m_fingerprint WHERE id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
}
