<?php
session_start();
header('Content-Type: application/json');
// Set timezone
date_default_timezone_set('Asia/Jakarta');
// include koneksi - try common locations
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__FILE__) . '/../../../../koneksi.php';
}
if (file_exists($koneksiPath)) require_once $koneksiPath;

$username = $_SESSION['NamaLengkap'] ?? '';
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'User tidak dikenali.']);
    exit;
}

$canvasData = $_POST['image'] ?? '';
if (!$canvasData || strpos($canvasData, 'data:image') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Data gambar tidak valid.']);
    exit;
}

try {
    if (!isset($conn) || $conn === false) throw new Exception('Koneksi database tidak tersedia.');

    // Get EmpId from m_emp
    $sqlEmp = "SELECT id_emp FROM dbo.m_emp WHERE nama_lengkap = ?";
    $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$username]);
    $empId = null;
    if ($stmtEmp && $row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
        $empId = $row['id_emp'];
    }
    if ($stmtEmp) sqlsrv_free_stmt($stmtEmp);
    if (!$empId) throw new Exception('Profil user tidak ditemukan.');

    // Get UserId from SMUserMs
    $sqlUser = "SELECT UserId FROM dbo.SMUserMs WHERE EmpId = ?";
    $stmtUser = sqlsrv_query($conn, $sqlUser, [$empId]);
    $userId = null;
    if ($stmtUser && $row = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
        $userId = $row['UserId'];
    }
    if ($stmtUser) sqlsrv_free_stmt($stmtUser);
    if (!$userId) throw new Exception('User ID tidak ditemukan.');

    // Parse and save canvas data
    if (!preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $canvasData, $m)) {
        throw new Exception('Format canvas data tidak valid.');
    }
    
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $imageData = base64_decode($m[2]);
    if ($imageData === false) {
        throw new Exception('Gagal decode canvas data.');
    }

    // Create upload directory
    $baseDir = realpath($_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads');
    if ($baseDir === false) {
        $uploadBase = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads';
        if (!is_dir($uploadBase)) @mkdir($uploadBase, 0777, true);
        $baseDir = $uploadBase;
    }

    $ttdDir = $baseDir . DIRECTORY_SEPARATOR . 'ttd';
    if (!is_dir($ttdDir)) {
        @mkdir($ttdDir, 0777, true);
    }

    if (!is_dir($ttdDir) || !is_writable($ttdDir)) {
        throw new Exception('Folder ttd tidak bisa diakses/tulis. Path: ' . $ttdDir);
    }

    // Build human-friendly filename: "FullName - Role - DD-MM-YY.ext"
    $labelName = trim($username);
    $labelName = preg_replace('/[\/\\:\*\?"<>\|]+/', '', $labelName);
    $labelName = preg_replace('/\s+/', ' ', $labelName);
    $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
    $labelName = str_replace(' ', '_', $labelName);
    $safeRole = 'Pemohon';
    $safeRole = preg_replace('/[^A-Za-z0-9 _-]/', '', $safeRole);
    $safeRole = str_replace(' ', '_', $safeRole);
    $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $ext;
    $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

    if (file_put_contents($fullPath, $imageData) === false) {
        throw new Exception('Gagal menyimpan file gambar.');
    }

    $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;

    // Check if user already has a TTD template with role 'Pemohon'
    $checkSql = "SELECT TOP 1 Id FROM dbo.User_TTD_Template WHERE UserId = ? AND GroupRole = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$userId, 'Pemohon']);
    $existingId = null;
    if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        $existingId = $row['Id'];
    }
    if ($checkStmt) sqlsrv_free_stmt($checkStmt);

    if ($existingId) {
        // Update existing record (and delete old file)
        $oldSql = "SELECT SignaturePath FROM dbo.User_TTD_Template WHERE Id = ?";
        $oldStmt = sqlsrv_query($conn, $oldSql, [$existingId]);
        if ($oldStmt && $oldRow = sqlsrv_fetch_array($oldStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($oldRow['SignaturePath'])) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $oldRow['SignaturePath'];
                if (file_exists($oldFile)) @unlink($oldFile);
            }
        }
        if ($oldStmt) sqlsrv_free_stmt($oldStmt);

        $updateSql = "UPDATE dbo.User_TTD_Template SET SignaturePath = ?, UpdatedAt = GETDATE() WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, [$imgPathRel, $existingId]);
        if ($updateStmt === false) throw new Exception('Gagal update TTD template.');
    } else {
        // Insert new record
        $insertSql = "INSERT INTO dbo.User_TTD_Template (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt) VALUES (?, ?, ?, ?, ?, GETDATE())";
        $insertStmt = sqlsrv_query($conn, $insertSql, [$userId, $username, 'Pemohon', $imgPathRel, 1]);
        if ($insertStmt === false) throw new Exception('Gagal insert TTD template.');
    }

    echo json_encode(['success' => true]);
} catch (Exception $ex) {
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
}
exit;
