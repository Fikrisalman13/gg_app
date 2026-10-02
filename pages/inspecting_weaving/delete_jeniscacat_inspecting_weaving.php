<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Session expired. Please login again."
    ]);
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error (DB connection)."
    ]);
    exit;
}

// ===================================================
// 4. IMPORT GLOBAL MENU & PERMISSIONS
// ===================================================
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 5. VALIDASI IZIN AKSES (DELETE)
// ===================================================
requireDelete($conn, MENU_JENISCACAT_INSPECT);

// ===================================================
// 6. VALIDASI REQUEST METHOD
// ===================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit;
}

// ===================================================
// 7. VALIDASI INPUT
// ===================================================
$typeId = $_POST['id'] ?? null;

if (!$typeId) {
    echo json_encode([
        "status" => "error",
        "message" => "Jenis Cacat ID not found!"
    ]);
    exit;
}

// ===================================================
// 8. UPDATE UpdDate & UpdUser SEBELUM DELETE
// ===================================================
$updUser = $_SESSION['UserName'];
$updDate = date('Y-m-d H:i:s');

$sqlUpdate = "
    UPDATE dbo.SMCacatType
    SET UpdDate = ?, UpdUser = ?
    WHERE TypeId = ?
";

$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$updDate, $updUser, $typeId]);

if ($stmtUpdate === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update log before delete: " . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

// ===================================================
// 9. EKSEKUSI DELETE DATA
// ===================================================
$sqlDelete = "DELETE FROM dbo.SMCacatType WHERE TypeId = ?";
$stmtDelete = sqlsrv_query($conn, $sqlDelete, [$typeId]);

if ($stmtDelete === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete record: " . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

// ===================================================
// 10. RESPONSE SUKSES
// ===================================================
echo json_encode([
    "status" => "success",
    "message" => "Jenis Cacat successfully deleted."
]);
exit;
?>
