<?php
session_start();
require_once '../../koneksi.php';

header('Content-Type: application/json');
// Set timezone
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Session habis, silakan login ulang.']);
    exit;
}

$ticket     = $_POST['ticket']     ?? '';
$roleCode   = $_POST['role_code']  ?? '';
$canvasData = $_POST['canvas_data'] ?? '';
$saveTpl    = isset($_POST['save_template']) && $_POST['save_template'] == '1';

if ($ticket === '' || $roleCode === '') {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

function mapRoleCode($code) {
    switch ($code) {
        case 'pemohon': return 'Pemohon';
        case 'atasan_pemohon': return 'Atasan Pemohon';
        case 'petugas_it': return 'Petugas IT';
        case 'petugas_cctv': return 'Petugas CCTV';
        case 'kabag_it': return 'Kabag IT';
        case 'kadept_it': return 'Kadept IT';
        case 'direksi': return 'Direksi';
        default: return null;
    }
}

$groupRole = mapRoleCode($roleCode);
if ($groupRole === null) {
    echo json_encode(['success' => false, 'message' => 'Role tidak dikenal.']);
    exit;
}

$userId = $_SESSION['UserId'];

// Ambil nama lengkap user (jika ada)
$sqlUser = "SELECT TOP 1 a.UserName AS LoginName,
                   ISNULL(c.nama_lengkap, a.UserName) AS FullName
            FROM dbo.SMUserMs a
            LEFT JOIN dbo.m_emp c ON a.EmpId = c.id_emp
            WHERE a.UserId = ?";
$stmtUser = sqlsrv_query($conn, $sqlUser, [$userId]);
$userName = 'User';
if ($stmtUser && $rowU = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
    $userName = $rowU['FullName'];
}

// Pastikan folder upload ada
$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
    // coba buat folder uploads
    $tryDir = __DIR__ . '/../../uploads';
    if (!is_dir($tryDir)) {
        @mkdir($tryDir, 0777, true);
    }
    $baseDir = realpath($tryDir);
}

if ($baseDir === false) {
    echo json_encode(['success' => false, 'message' => 'Folder upload tidak tersedia.']);
    exit;
}

$ttdDir = $baseDir . DIRECTORY_SEPARATOR . 'ttd';
if (!is_dir($ttdDir)) {
    @mkdir($ttdDir, 0777, true);
}

if (!is_dir($ttdDir) || !is_writable($ttdDir)) {
    echo json_encode(['success' => false, 'message' => 'Folder ttd tidak bisa diakses/tulis.']);
    exit;
}

// Tentukan sumber gambar: canvas base64 atau file upload
$imgPathRel = '';

if (!empty($canvasData) && strpos($canvasData, 'data:image') === 0) {
    // Format: data:image/png;base64,xxxx
    if (preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $canvasData, $m)) {
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $data = base64_decode($m[2]);
        if ($data === false) {
            echo json_encode(['success' => false, 'message' => 'Data canvas tidak valid.']);
            exit;
        }

        // Build human-friendly filename: "FullName - Role - DD-MM-YY.ext"
        $labelName = trim($userName);
        $labelName = preg_replace('/[\/\\:\*\?"<>\|]+/', '', $labelName);
        $labelName = preg_replace('/\s+/', ' ', $labelName);
        $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
        $labelName = str_replace(' ', '_', $labelName);
        $safeRole = preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole);
        $safeRole = str_replace(' ', '_', trim($safeRole));
        if (!function_exists('imagecreatefromstring')) {
            $saveExt = 'png';
            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $saveExt;
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_put_contents($fullPath, $data) === false) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file canvas.']);
                exit;
            }
            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
        } else {
            // Process Transparency (Remove White Background) using GD
            $src = @imagecreatefromstring($data);
            if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            $dst = imagecreatetruecolor($w, $h);
            
            // Set up transparency for the new image
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $transparent);
            
            // Draw original onto transparent canvas
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            
            // Remove white/light background pixels
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($dst, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Threshold: if pixel is "almost white", make it transparent
                    if ($r > 210 && $g > 210 && $b > 210) {
                        imagesetpixel($dst, $x, $y, $transparent);
                    }
                }
            }
            
            // Force save as PNG to preserve transparency
            $saveExt = 'png';
            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $saveExt;
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
            
            imagepng($dst, $fullPath, 0);
            imagedestroy($src);
            imagedestroy($dst);
            
            if (file_exists($fullPath)) {
                $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
            }
            }
        }

        if ($imgPathRel === '') {
            // Fallback to original save if GD fails or src is false
            if (file_put_contents($fullPath, $data) === false) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file canvas.']);
                exit;
            }
            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
        }
    }
}

// Jika tidak ada dari canvas, cek upload file
if ($imgPathRel === '' && isset($_FILES['ttd_file']) && $_FILES['ttd_file']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['ttd_file']['tmp_name'];
    $name    = $_FILES['ttd_file']['name'];

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
        echo json_encode(['success' => false, 'message' => 'Format file harus PNG/JPG.']);
        exit;
    }

    $labelName = trim($userName);
    $labelName = preg_replace('/[\/\\:\*\?"<>\|]+/', '', $labelName);
    $labelName = preg_replace('/\s+/', ' ', $labelName);
    $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
    $labelName = str_replace(' ', '_', $labelName);
    $safeRole = preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole);
    $safeRole = str_replace(' ', '_', trim($safeRole));
    $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $ext;
    $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $fullPath)) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file upload.']);
        exit;
    }

    $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
}

if ($imgPathRel === '') {
    echo json_encode(['success' => false, 'message' => 'Tidak ada gambar yang dikirim (canvas atau upload).']);
    exit;
}

// Simpan template (opsional)
if ($saveTpl) {
    $sqlTpl = "INSERT INTO User_TTD_Template (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt) VALUES (?, ?, ?, ?, 1, GETDATE())";
    $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId, $userName, $groupRole, $imgPathRel]);
}

// Simpan ke tabel TTD per tiket
$sqlUp = "IF EXISTS (SELECT 1 FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ? AND GroupRole = ?)
          UPDATE Form_Pengajuan_Barang_TTD
          SET SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
          WHERE Ticket = ? AND GroupRole = ?
          ELSE
          INSERT INTO Form_Pengajuan_Barang_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
          VALUES (?, ?, ?, ?, ?, GETDATE())";

$params = [
    $ticket, $groupRole,
    $imgPathRel, $userId, $userName, $ticket, $groupRole,
    $ticket, $groupRole, $imgPathRel, $userId, $userName
];

$stmtUp = sqlsrv_query($conn, $sqlUp, $params);

if ($stmtUp === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan tanda tangan ke tiket.']);
    exit;
}

echo json_encode([
    'success'            => true,
    'signature_url'      => $imgPathRel,
    'signed_by'          => $userName,
    'signed_by_user_id'  => $userId
]);
