<?php
session_start();
header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Jakarta');
// include koneksi
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__FILE__) . '/../../koneksi.php';
}
if (file_exists($koneksiPath)) require_once $koneksiPath;

$ticket = $_POST['ticket'] ?? '';
$canvasData = $_POST['image'] ?? '';

if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket tidak ditemukan.']);
    exit;
}

if (!$canvasData || strpos($canvasData, 'data:image') !== 0) {
    echo json_encode(['success' => false, 'message' => 'Data gambar tidak valid.']);
    exit;
}

try {
    if (!isset($conn) || $conn === false) throw new Exception('Koneksi database tidak tersedia.');

    // Check for specific employee name from parameter, otherwise use session user
    $username = isset($_POST['nama_pemohon']) && !empty(trim($_POST['nama_pemohon'])) 
                ? trim($_POST['nama_pemohon']) 
                : ($_SESSION['NamaLengkap'] ?? '');
    if (!$username) throw new Exception('User tidak dikenali.');

    // Get UserId
    $sqlEmp = "SELECT id_emp FROM dbo.m_emp WHERE nama_lengkap = ?";
    $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$username]);
    $empId = null;
    if ($stmtEmp && $row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
        $empId = $row['id_emp'];
    }
    if ($stmtEmp) sqlsrv_free_stmt($stmtEmp);

    $userId = null;
    if ($empId) {
        $sqlUser = "SELECT UserId FROM dbo.SMUserMs WHERE EmpId = ?";
        $stmtUser = sqlsrv_query($conn, $sqlUser, [$empId]);
        if ($stmtUser && $row = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
            $userId = $row['UserId'];
        }
        if ($stmtUser) sqlsrv_free_stmt($stmtUser);
    }

    if (!$userId) throw new Exception('User ID tidak ditemukan.');

    // Prevent overwrite: if Pemohon signature already exists by another user, do nothing
    $stmtExisting = sqlsrv_query(
        $conn,
        "SELECT TOP 1 SignaturePath, SignedByUserId FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ?",
        [$ticket, 'Pemohon']
    );
    if ($stmtExisting && ($rowEx = sqlsrv_fetch_array($stmtExisting, SQLSRV_FETCH_ASSOC))) {
        $existingPath = trim((string)($rowEx['SignaturePath'] ?? ''));
        $existingSigner = $rowEx['SignedByUserId'] ?? null;
        if ($existingPath !== '' && $existingSigner !== null && (int)$existingSigner !== (int)$userId) {
            sqlsrv_free_stmt($stmtExisting);
            echo json_encode(['success' => true, 'signature_url' => $existingPath, 'message' => 'TTD Pemohon sudah ada.']);
            exit;
        }
    }
    if ($stmtExisting) sqlsrv_free_stmt($stmtExisting);

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
        throw new Exception('Folder ttd tidak bisa diakses/tulis.');
    }

    // Generate human-friendly filename: "FullName - Role - DD-MM-YY.ext"
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

    // Save to Form_Umum_TTD with ticket reference
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ?)
              UPDATE Form_Umum_TTD
              SET SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole = ?
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, ?, ?, ?, ?, GETDATE())";

    $params = [
        $ticket, 'Pemohon',
        $imgPathRel, $userId, $username, $ticket, 'Pemohon',
        $ticket, 'Pemohon', $imgPathRel, $userId, $username
    ];

    $stmt = sqlsrv_query($conn, $sqlUp, $params);
    if ($stmt === false) {
        throw new Exception('Gagal menyimpan tanda tangan ke tiket: ' . print_r(sqlsrv_errors(), true));
    }

    // Also save to User_TTD_Template_Umum as a template for future use
    $checkTpl = "SELECT TOP 1 id FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole = ?";
    $stmtChk = sqlsrv_query($conn, $checkTpl, [$userId, 'Pemohon']);
    $tplExists = $stmtChk && sqlsrv_fetch_array($stmtChk, SQLSRV_FETCH_ASSOC);
    if ($stmtChk) sqlsrv_free_stmt($stmtChk);

    if ($tplExists) {
        $sqlTplUp = "UPDATE User_TTD_Template_Umum SET SignaturePath = ?, UpdatedAt = GETDATE() WHERE UserId = ? AND GroupRole = ?";
        sqlsrv_query($conn, $sqlTplUp, [$imgPathRel, $userId, 'Pemohon']);
    } else {
        $sqlTplIns = "INSERT INTO User_TTD_Template_Umum (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt) VALUES (?, ?, ?, ?, 1, GETDATE())";
        sqlsrv_query($conn, $sqlTplIns, [$userId, $username, 'Pemohon', $imgPathRel]);
    }

    echo json_encode(['success' => true, 'signature_url' => $imgPathRel]);
} catch (Exception $ex) {
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
}
exit;
