<?php
// ===================================================
// 1. VALIDASI LOGIN DAN SESSION
// ===================================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Session expired. Silakan login kembali."
    ]);
    exit;
}

// ===================================================
// 2. LOAD KONEKSI DATABASE
// ===================================================
require '../koneksi.php';

if (!$conn) {
    echo json_encode([
        "status" => "error",
        "message" => "Koneksi database gagal."
    ]);
    exit;
}

// ===================================================
// 3. HANYA TERIMA POST
// ===================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Metode request tidak valid."
    ]);
    exit;
}

// ===================================================
// 4. IMPORT FILE PERMISSION DAN CONSTANTS
// ===================================================
require '../includes/menu_constants.php';
require '../includes/permissions.php';

// ===================================================
// 5. CEK PERMISSION DELETE
// ===================================================
$groupId = $_SESSION['GroupId'] ?? 0;
$permissions = userPermissions($conn, MENU_GROUP_ACCESS, $groupId);

if (!$permissions['CanDelete']) {
    echo json_encode([
        "status" => "error",
        "message" => "Anda tidak memiliki izin untuk menghapus data."
    ]);
    exit;
}

// ===================================================
// 6. AMBIL DAN VALIDASI ID
// ===================================================
$trusteeId = $_POST['id'] ?? null;

if (!$trusteeId || !is_numeric($trusteeId)) {
    echo json_encode([
        "status" => "error",
        "message" => "ID Trustee tidak valid."
    ]);
    exit;
}

$trusteeId = intval($trusteeId);

try {
    // ===================================================
    // 7. CEK DATA EXISTING
    // ===================================================
    $sqlCheck = "
        SELECT 
            gt.TrusteeId, 
            ug.GroupName, 
            m.MenuName 
        FROM dbo.SMGroupTrustee gt
        LEFT JOIN dbo.SMUserGroup ug ON gt.GroupId = ug.GroupId
        LEFT JOIN dbo.SMMenu m ON gt.MenuId = m.MenuId
        WHERE gt.TrusteeId = ?
    ";
    
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$trusteeId]);
    
    if ($stmtCheck === false) {
        $errors = sqlsrv_errors();
        error_log("Error checking data: " . print_r($errors, true));
        echo json_encode([
            "status" => "error",
            "message" => "Terjadi kesalahan saat memeriksa data."
        ]);
        exit;
    }
    
    $record = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCheck);
    
    if (!$record) {
        echo json_encode([
            "status" => "error",
            "message" => "Data tidak ditemukan."
        ]);
        exit;
    }
    
    // ===================================================
    // 8. DELETE DATA
    // ===================================================
    $sqlDelete = "DELETE FROM dbo.SMGroupTrustee WHERE TrusteeId = ?";
    $stmtDelete = sqlsrv_query($conn, $sqlDelete, [$trusteeId]);
    
    if ($stmtDelete === false) {
        $errors = sqlsrv_errors();
        error_log("Error deleting data: " . print_r($errors, true));
        echo json_encode([
            "status" => "error",
            "message" => $errors[0]['message'] ?? "Terjadi kesalahan saat menghapus data."
        ]);
        exit;
    }
    
    // Periksa apakah ada baris yang terhapus
    $rowsAffected = sqlsrv_rows_affected($stmtDelete);
    sqlsrv_free_stmt($stmtDelete);
    
    if ($rowsAffected === false || $rowsAffected === 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Tidak ada data yang terhapus. Data mungkin sudah dihapus sebelumnya."
        ]);
        exit;
    }
    
    // ===================================================
    // 9. RESPONSE SUKSES
    // ===================================================
    echo json_encode([
        "status" => "success",
        "message" => "Hak akses untuk group \"{$record['GroupName']}\" pada menu \"{$record['MenuName']}\" berhasil dihapus.",
        "data" => [
            'groupName' => $record['GroupName'],
            'menuName' => $record['MenuName']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Exception in delete_hakaksesgroup.php: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Terjadi kesalahan: " . $e->getMessage()
    ]);
}