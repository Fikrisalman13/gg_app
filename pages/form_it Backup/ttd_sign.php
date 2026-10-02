<?php
session_start();
require_once '../../koneksi.php';

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
        case 'pemohon': return 'Pemohon';
        case 'atasan_pemohon': return 'Atasan Pemohon';
        case 'petugas_it': return 'Petugas IT';
        case 'petugas_cctv': return 'Petugas CCTV';
        case 'kabag_it': return 'Kabag IT';
        case 'kadept_it': return 'Kadept IT';
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

// Cek template TTD user
$sqlTpl = "SELECT TOP 1 SignaturePath FROM User_TTD_Template WHERE UserId = ? AND GroupRole = ? AND IsActive = 1 ORDER BY Id DESC";
$stmtTpl = sqlsrv_query($conn, $sqlTpl, [$userId, $groupRole]);

if (!$stmtTpl || !sqlsrv_has_rows($stmtTpl)) {
    echo json_encode(['success' => false, 'need_template' => true]);
    exit;
}

$rowTpl = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC);
$signaturePath = $rowTpl['SignaturePath'];

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
    $signaturePath, $userId, $userName, $ticket, $groupRole,
    $ticket, $groupRole, $signaturePath, $userId, $userName
];

$stmtUp = sqlsrv_query($conn, $sqlUp, $params);

if ($stmtUp === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan tanda tangan.']);
    exit;
}

// Kembalikan URL absolut/relatif
$signatureUrl = $signaturePath;

echo json_encode([
    'success'            => true,
    'signature_url'      => $signatureUrl,
    'signed_by'          => $userName,
    'signed_by_user_id'  => $userId
]);
