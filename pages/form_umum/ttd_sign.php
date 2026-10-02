<?php
session_start();
require_once '../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Session habis, silakan login ulang.']);
    exit;
}

$ticket   = $_POST['ticket'] ?? '';
$roleCode = $_POST['role_code'] ?? '';

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
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT GroupRole FROM User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1", [$userId]);
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

// Ambil template TTD role Atasan aktif (Kabag/Kasie/Ka.*)
function getAtasanTemplatePath($conn, $userId) {
    $sql = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum
            WHERE UserId = ? AND IsActive = 1
              AND (GroupRole LIKE '%Kabag%' OR GroupRole LIKE '%Kasie%' OR GroupRole LIKE 'Ka.%' OR GroupRole LIKE 'Kas%')
            ORDER BY Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row['SignaturePath'] ?? null;
    }
    return null;
}

$groupRole = mapRoleCode($roleCode);
if ($groupRole === null) {
    echo json_encode(['success' => false, 'message' => 'Role tidak dikenal.']);
    exit;
}

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

// â”€â”€ Cek template TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$signaturePath = null;

// Kolom atasan_pemohon & personalia: multi-role Ka* atau Atasan Pemohon langsung
if ($groupRole === 'atasan_pemohon' || $groupRole === 'personalia') {
    // Verifikasi dulu user punya role Ka* ATAU role Atasan Pemohon langsung
    $kaRoles = getUserKaRoles($conn, $userId);

    // Cek juga apakah user punya role "Atasan Pemohon" langsung
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
        $message = $isKadeptCol
            ? 'Anda tidak memiliki template TTD Kadept aktif.'
            : 'Anda tidak memiliki template TTD Atasan aktif (Kabag/Kasie/Atasan Pemohon).';
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    // Ambil signature path: role Atasan, fallback ke template Atasan Pemohon
    $signaturePath = getAtasanTemplatePath($conn, $userId);
    if (!$signaturePath && $hasAtasanRole) {
        $sqlTpl = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole = 'Atasan Pemohon' AND IsActive = 1 ORDER BY Id DESC";
        $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId]);
        if ($stmtTpl && sqlsrv_has_rows($stmtTpl)) {
            $rowTpl = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC);
            $signaturePath = $rowTpl['SignaturePath'] ?? null;
        }
        if ($stmtTpl) sqlsrv_free_stmt($stmtTpl);
    }
    if (!$signaturePath) {
        echo json_encode(['success' => false, 'need_template' => true]);
        exit;
    }
    // Simpan GroupRole aktual agar terlacak
    $actualGroupRole = $groupRole === 'atasan_pemohon' ? 'Atasan Pemohon' : 'Personalia';
} elseif ($groupRole === 'Kadept') {
    // Semua role Kadept mengisi satu slot workflow canonical: Kadept.
    $actualGroupRole = 'Kadept';
    $sqlTpl = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum
               WHERE UserId = ? AND GroupRole LIKE '%Kadept%' AND IsActive = 1
               ORDER BY Id DESC";
    $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId]);
    if (!$stmtTpl || !sqlsrv_has_rows($stmtTpl)) {
        echo json_encode(['success' => false, 'need_template' => true]);
        exit;
    }
    $rowTpl = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC);
    $signaturePath = $rowTpl['SignaturePath'];
    sqlsrv_free_stmt($stmtTpl);
} elseif ($groupRole === 'HRD') {
    $actualGroupRole = 'HRD';
    $sqlTpl  = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole IN ('HRD', 'Personalia') AND IsActive = 1 ORDER BY Id DESC";
    $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId]);
    if (!$stmtTpl || !sqlsrv_has_rows($stmtTpl)) {
        echo json_encode(['success' => false, 'need_template' => true]);
        exit;
    }
    $rowTpl        = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC);
    $signaturePath = $rowTpl['SignaturePath'];
    sqlsrv_free_stmt($stmtTpl);
} else {
    // Role spesifik exact-match
    $actualGroupRole = $groupRole;
    if ($groupRole === 'Kadept ACC') {
        $sqlTpl  = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole IN (?, ?) AND IsActive = 1 ORDER BY Id DESC";
        $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId, 'Kadept ACC', 'Acc Audit']);
    } else {
        $sqlTpl  = "SELECT TOP 1 SignaturePath FROM User_TTD_Template_Umum WHERE UserId = ? AND GroupRole = ? AND IsActive = 1 ORDER BY Id DESC";
        $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId, $groupRole]);
    }
    if (!$stmtTpl || !sqlsrv_has_rows($stmtTpl)) {
        echo json_encode(['success' => false, 'need_template' => true]);
        exit;
    }
    $rowTpl        = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC);
    $signaturePath = $rowTpl['SignaturePath'];
}

// ── Simpan ke Form_Umum_TTD ──────────────────────────────────────────
if ($actualGroupRole === 'Kadept') {
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept', 'Kadept IT'))
              UPDATE Form_Umum_TTD
              SET GroupRole = 'Kadept', SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole IN ('Kadept', 'Kadept IT')
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, 'Kadept', ?, ?, ?, GETDATE())";
    $params = [
        $ticket, $signaturePath, $userId, $userName,
        $ticket, $ticket, $signaturePath, $userId, $userName
    ];
} elseif ($actualGroupRole === 'Kadept ACC') {
    $sqlUp = "IF EXISTS (SELECT 1 FROM Form_Umum_TTD WHERE Ticket = ? AND GroupRole IN ('Kadept ACC', 'Acc Audit'))
              UPDATE Form_Umum_TTD
              SET GroupRole = 'Kadept ACC', SignaturePath = ?, SignedByUserId = ?, SignedByUserName = ?, SignedAt = GETDATE()
              WHERE Ticket = ? AND GroupRole IN ('Kadept ACC', 'Acc Audit')
              ELSE
              INSERT INTO Form_Umum_TTD (Ticket, GroupRole, SignaturePath, SignedByUserId, SignedByUserName, SignedAt)
              VALUES (?, 'Kadept ACC', ?, ?, ?, GETDATE())";
    $params = [
        $ticket, $signaturePath, $userId, $userName,
        $ticket, $ticket, $signaturePath, $userId, $userName
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
        $signaturePath, $userId, $userName, $ticket, $actualGroupRole,
        $ticket, $actualGroupRole, $signaturePath, $userId, $userName
    ];
}

$stmtUp = sqlsrv_query($conn, $sqlUp, $params);

if ($stmtUp === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan tanda tangan.']);
    exit;
}

$sync = closinganSyncApprovalStatus($conn, $ticket);

// Auto-approve IKS setelah TTD ke-3 (HRD), IPC setelah ke-3, IKP lama setelah ke-4 (DanRu SATPAM)
$isIKS = stripos($ticket, 'IKS-') === 0;
$isIKP = stripos($ticket, 'IKP-') === 0;
$isIPC = stripos($ticket, 'IPC-') === 0;
if ($isIKS || $isIKP || $isIPC) {
    $sqlCount = "SELECT COUNT(*) AS cnt FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL";
    $stmtCnt  = sqlsrv_query($conn, $sqlCount, [$ticket]);
    $ttdCount = 0;
    if ($stmtCnt && $rowCnt = sqlsrv_fetch_array($stmtCnt, SQLSRV_FETCH_ASSOC)) {
        $ttdCount = (int)$rowCnt['cnt'];
    }
    $requiredTtd = $isIKP ? 4 : 3;
    if ($isIKS) {
        $stmtFlow = sqlsrv_query($conn, "SELECT TOP 1 tgl_pengajuan FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?", [$ticket]);
        $rowFlow = $stmtFlow ? sqlsrv_fetch_array($stmtFlow, SQLSRV_FETCH_ASSOC) : null;
        if ($stmtFlow) sqlsrv_free_stmt($stmtFlow);
        if (iksRequiresKadeptIT($ticket, $rowFlow['tgl_pengajuan'] ?? null)) {
            $requiredTtd = 4;
        }
    }
    if ($ttdCount >= $requiredTtd) {
        $approveTable = $isIPC ? 'Form_Umum_Izin_Pulang_Cepat' : 'Form_Umum_Izin_Keluar_Pabrik';
        sqlsrv_query($conn,
            "UPDATE {$approveTable} SET status_ticket = 'Approved' WHERE ticket = ? AND status_ticket = 'Pending'",
            [$ticket]
        );
    }
    echo json_encode([
        'success'           => true,
        'signature_url'     => $signaturePath,
        'signed_by'         => $userName,
        'signed_by_user_id' => $userId,
        'approval_status'   => $ttdCount >= $requiredTtd ? 'Approved' : ($ttdCount . '/' . $requiredTtd . ' Disetujui')
    ]);
} else {
    echo json_encode([
        'success'           => true,
        'signature_url'     => $signaturePath,
        'signed_by'         => $userName,
        'signed_by_user_id' => $userId,
        'approval_status'   => $sync['status']
    ]);
}

