<?php
session_start();
require_once '../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Session habis, silakan login ulang.']);
    exit;
}

$userId     = $_SESSION['UserId'];
$ticket     = $_POST['ticket']     ?? '';
$roleCode   = $_POST['role_code']  ?? '';
$canvasData = $_POST['canvas_data'] ?? '';
$saveTpl    = isset($_POST['save_template']) && $_POST['save_template'] == '1';
$signatureSource = $_POST['signature_source'] ?? 'account';
$manualTemplateId = intval($_POST['manual_template_id'] ?? 0);

if ($ticket === '' || $roleCode === '') {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

function mapRoleCode($code) {
    switch ($code) {
        case 'pemohon':        return 'Pemohon';
        case 'atasan_pemohon': return 'atasan_pemohon'; // multi-role Ka*
        case 'personalia':     return 'personalia';     // multi-role Ka*
        case 'kadept_it':      return 'Kadept';
        case 'kadept':         return 'Kadept';
        case 'hrd':            return 'HRD';            // HRD role (IKS)
        case 'danru_satpam':   return 'DanRu SATPAM';
        case 'kabag_ics':      return 'Kabag ICS';
        case 'acc_audit':      return 'Kadept ACC';
        case 'direksi':        return 'Direksi';
        default:               return null;
    }
}

function iksRequiresKadeptIT($ticket, $tglPengajuan) {
    if (stripos($ticket, 'IKS-') !== 0) return false;
    if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
    $time = strtotime((string)$tglPengajuan);
    return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}

function isKadeptRole($role): bool {
    return stripos(trim((string) $role), 'kadept') !== false;
}

function isAtasanRole($role): bool {
    $role = trim((string) $role);
    return stripos($role, 'kabag') !== false
        || stripos($role, 'kasie') !== false
        || preg_match('/^ka\./i', $role) === 1
        || preg_match('/^ka[sd]/i', $role) === 1;
}

function getUserRoles($conn, $userId, callable $matcher): array {
    $sql  = "SELECT DISTINCT GroupRole FROM User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    $roles = [];
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($matcher($r['GroupRole'] ?? '')) $roles[] = $r['GroupRole'];
        }
        sqlsrv_free_stmt($stmt);
    }
    return $roles;
}

function getUserKaRoles($conn, $userId) {
    return getUserRoles($conn, $userId, 'isAtasanRole');
}

function getUserKadeptRoles($conn, $userId) {
    return getUserRoles($conn, $userId, 'isKadeptRole');
}

$groupRole = mapRoleCode($roleCode);
if ($groupRole === null) {
    echo json_encode(['success' => false, 'message' => 'Role tidak dikenal.']);
    exit;
}

$isKadeptCol = ($groupRole === 'Kadept');
$isKaCol = ($groupRole === 'atasan_pemohon' || $groupRole === 'personalia');
$isHrdCol = ($groupRole === 'HRD');
$actualGroupRole = $isKadeptCol
    ? 'Kadept'
    : ($isKaCol ? ($groupRole === 'atasan_pemohon' ? 'Atasan Pemohon' : 'Personalia') : $groupRole);

$isAdminManualTemplate = ((int)($_SESSION['GroupId'] ?? 0) === 1 && $signatureSource === 'manual_template');

if (($isKaCol || $isKadeptCol) && !$isAdminManualTemplate) {
    $kaRoles = $isKadeptCol
        ? getUserKadeptRoles($conn, $userId)
        : getUserKaRoles($conn, $userId);

    // Atasan Pemohon may also use direct role for Atasan column.
    $hasAtasanRole = false;
    if ($groupRole === 'atasan_pemohon') {
        $sqlAtasan = "SELECT 1 FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole = 'Atasan Pemohon' AND IsActive = 1";
        $stmtAtasan = sqlsrv_query($conn, $sqlAtasan, [$userId]);
        if ($stmtAtasan && sqlsrv_has_rows($stmtAtasan)) {
            $hasAtasanRole = true;
        }
        if ($stmtAtasan) sqlsrv_free_stmt($stmtAtasan);
    }

    if (empty($kaRoles) && !$hasAtasanRole) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki role yang sesuai (Ka.Sie/Ka.Bag/Ka.Dept/Atasan Pemohon) untuk kolom ini.']);
        exit;
    }
}
// HRD role requires HRD or Personalia group role in User_TTD_Template_Umum
// (validation deferred to template lookup below)

$userId = $_SESSION['UserId'];

// Ambil nama lengkap user
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
if ($stmtUser) sqlsrv_free_stmt($stmtUser);

$imgPathRel = '';

if ($signatureSource === 'manual_template') {
    if ((int)($_SESSION['GroupId'] ?? 0) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Hanya administrator yang dapat memakai TTD manual.']);
        exit;
    }
    if ($manualTemplateId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Template TTD manual belum dipilih.']);
        exit;
    }

    $sqlManual = "SELECT TOP 1 Id, UserName, GroupRole, SignaturePath
                  FROM dbo.User_TTD_Template_Umum
                  WHERE Id = ?
                    AND IsActive = 1
                    AND ISNULL(UserId, 0) = 0
                    AND GroupRole = ?
                    AND ISNULL(SignaturePath, '') <> ''";
    $stmtManual = sqlsrv_query($conn, $sqlManual, [$manualTemplateId, $actualGroupRole]);
    if (!$stmtManual || !($manualRow = sqlsrv_fetch_array($stmtManual, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'message' => 'Template TTD manual tidak valid untuk role ini.']);
        exit;
    }
    if ($stmtManual) sqlsrv_free_stmt($stmtManual);

    $userId = 0;
    $userName = $manualRow['UserName'];
    $imgPathRel = $manualRow['SignaturePath'];
}

// Pastikan folder upload tersedia jika tanda tangan berasal dari akun/canvas/upload.
if ($signatureSource !== 'manual_template') {
$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
    $tryDir = __DIR__ . '/../../uploads';
    if (!is_dir($tryDir)) @mkdir($tryDir, 0777, true);
    $baseDir = realpath($tryDir);
}

if ($baseDir === false) {
    echo json_encode(['success' => false, 'message' => 'Folder upload tidak tersedia.']);
    exit;
}

$ttdDir = $baseDir . DIRECTORY_SEPARATOR . 'ttd';
if (!is_dir($ttdDir)) @mkdir($ttdDir, 0777, true);

if (!is_dir($ttdDir) || !is_writable($ttdDir)) {
    echo json_encode(['success' => false, 'message' => 'Folder ttd tidak bisa diakses/tulis.']);
    exit;
}
}

// â”€â”€ Canvas data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($signatureSource !== 'manual_template' && !empty($canvasData) && strpos($canvasData, 'data:image') === 0) {
    if (preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $canvasData, $m)) {
        $data = base64_decode($m[2]);
        if ($data === false) {
            echo json_encode(['success' => false, 'message' => 'Data canvas tidak valid.']);
            exit;
        }

        $labelName = preg_replace('/[\/\\:*?"<>|]+/', '', trim($userName));
        $labelName = preg_replace('/\s+/', ' ', $labelName);
        $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
        $labelName = str_replace(' ', '_', $labelName);
        $safeRole  = str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole));

        // Proses transparansi background putih via GD
        $src = @imagecreatefromstring($data);
        if ($src !== false) {
            $w   = imagesx($src);
            $h   = imagesy($src);
            $dst = imagecreatetruecolor($w, $h);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($dst, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8)  & 0xFF;
                    $b = $rgb         & 0xFF;
                    if ($r > 210 && $g > 210 && $b > 210) {
                        imagesetpixel($dst, $x, $y, $transparent);
                    }
                }
            }

            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.png';
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
            imagepng($dst, $fullPath, 0);

            if (file_exists($fullPath)) {
                $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
            }
        }

        if ($imgPathRel === '') {
            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.png';
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_put_contents($fullPath, $data) === false) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file canvas.']);
                exit;
            }
            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
        }
    }
}

// â”€â”€ File upload â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($signatureSource !== 'manual_template' && $imgPathRel === '' && isset($_FILES['ttd_file']) && $_FILES['ttd_file']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['ttd_file']['tmp_name'];
    $name    = $_FILES['ttd_file']['name'];
    $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
        echo json_encode(['success' => false, 'message' => 'Format file harus PNG/JPG.']);
        exit;
    }

    $labelName = preg_replace('/[\/\\:*?"<>|]+/', '', trim($userName));
    $labelName = preg_replace('/\s+/', ' ', $labelName);
    $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
    $labelName = str_replace(' ', '_', $labelName);
    $safeRole  = str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole));
    $fileName  = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $ext;
    $fullPath  = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

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

// â”€â”€ Simpan template ke User_TTD_Template_Umum (opsional) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($saveTpl && $signatureSource !== 'manual_template') {
    $sqlTpl  = "INSERT INTO User_TTD_Template_Umum (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt) VALUES (?, ?, ?, ?, 1, GETDATE())";
    sqlsrv_query($conn, $sqlTpl, [$userId, $userName, $actualGroupRole, $imgPathRel]);
}

// â”€â”€ Simpan ke Form_Umum_TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($actualGroupRole === 'Kadept') {
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept', 'Kadept IT'))
              UPDATE Form_Umum_TTD
              SET GroupRole = 'Kadept', SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole IN ('Kadept', 'Kadept IT')
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, 'Kadept', ?, ?, ?, GETDATE())";
    $params = [
        $ticket, $imgPathRel, $userId, $userName,
        $ticket, $ticket, $imgPathRel, $userId, $userName
    ];
} elseif ($actualGroupRole === 'Kadept ACC') {
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept ACC', 'Acc Audit', 'Kadept'))
              UPDATE Form_Umum_TTD
              SET GroupRole = 'Kadept ACC', SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole IN ('Kadept ACC', 'Acc Audit', 'Kadept')
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, 'Kadept ACC', ?, ?, ?, GETDATE())";
    $params = [
        $ticket, $imgPathRel, $userId, $userName,
        $ticket, $ticket, $imgPathRel, $userId, $userName
    ];
} else {
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole = ?)
              UPDATE Form_Umum_TTD
              SET SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole = ?
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, ?, ?, ?, ?, GETDATE())";
    $params = [
        $ticket, $actualGroupRole,
        $imgPathRel, $userId, $userName, $ticket, $actualGroupRole,
        $ticket, $actualGroupRole, $imgPathRel, $userId, $userName
    ];
}

$stmtUp = sqlsrv_query($conn, $sqlUp, $params);

if ($stmtUp === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan tanda tangan ke tiket.']);
    exit;
}

$sync = closinganSyncApprovalStatus($conn, $ticket);

$isIKS = stripos($ticket, 'IKS-') === 0;
$isIKP = stripos($ticket, 'IKP-') === 0;
$isIPC = stripos($ticket, 'IPC-') === 0;
$required = $isIKP ? 4 : 3;
if ($isIKS) {
    $stmtFlow = sqlsrv_query($conn, "SELECT TOP 1 tgl_pengajuan FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?", [$ticket]);
    $rowFlow = $stmtFlow ? sqlsrv_fetch_array($stmtFlow, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtFlow) sqlsrv_free_stmt($stmtFlow);
    if (iksRequiresKadeptIT($ticket, $rowFlow['tgl_pengajuan'] ?? null)) {
        $required = 4;
    }
}
$ttdCount = 0;
if ($isIKS || $isIKP || $isIPC) {
    $stmtCnt = sqlsrv_query($conn,
        "SELECT COUNT(*) AS cnt FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL",
        [$ticket]);
    if ($stmtCnt && $rowCnt = sqlsrv_fetch_array($stmtCnt, SQLSRV_FETCH_ASSOC)) {
        $ttdCount = (int)$rowCnt['cnt'];
    }
    $requiredTtd = $required;
    if ($ttdCount >= $requiredTtd) {
        $approveTable = $isIPC ? 'Form_Umum_Izin_Pulang_Cepat' : 'Form_Umum_Izin_Keluar_Pabrik';
        sqlsrv_query($conn,
            "UPDATE {$approveTable} SET status_ticket = 'Approved' WHERE ticket = ? AND status_ticket = 'Pending'",
            [$ticket]);
    }
}

echo json_encode([
    'success'           => true,
    'signature_url'     => $imgPathRel,
    'signed_by'         => $userName,
    'signed_by_user_id' => $userId,
    'approval_status'   => ($isIKS || $isIKP || $isIPC)
        ? ($ttdCount >= (($isIKS || $isIKP) ? 4 : 3) ? 'Approved' : $ttdCount . '/' . (($isIKS || $isIKP) ? 4 : 3) . ' Disetujui')
        : $sync['status']
]);

