<?php
session_start();
require_once '../../koneksi.php';
require_once __DIR__ . '/approval_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserId'])) {
    echo json_encode(['success' => false, 'message' => 'Session habis, silakan login ulang.', 'data' => []]);
    exit;
}

if ((int)($_SESSION['GroupId'] ?? 0) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Hanya administrator yang dapat memilih TTD manual.', 'data' => []]);
    exit;
}

function mapManualTtdRoleCode($code) {
    switch ($code) {
        case 'pemohon':        return 'Pemohon';
        case 'atasan_pemohon': return 'Atasan Pemohon';
        case 'personalia':     return 'Personalia';
        case 'hrd':            return 'HRD';
        case 'danru_satpam':   return 'DanRu SATPAM';
        case 'kabag_ics':      return 'Kabag ICS';
        case 'acc_audit':      return 'Kadept ACC';
        case 'direksi':        return 'Direksi';
        default:               return trim((string)$code);
    }
}

$roleCode = $_GET['role_code'] ?? $_POST['role_code'] ?? '';
$groupRole = $_GET['group_role'] ?? $_POST['group_role'] ?? '';
$role = $groupRole !== '' ? trim($groupRole) : mapManualTtdRoleCode($roleCode);

if ($role === '') {
    echo json_encode(['success' => false, 'message' => 'Role tidak lengkap.', 'data' => []]);
    exit;
}

$sql = "SELECT Id, UserName, GroupRole, SignaturePath
        FROM dbo.User_TTD_Template_Umum
        WHERE IsActive = 1
          AND ISNULL(UserId, 0) = 0
          AND GroupRole = ?
          AND ISNULL(SignaturePath, '') <> ''
        ORDER BY UserName";
$stmt = sqlsrv_query($conn, $sql, [$role]);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil template TTD manual.', 'data' => []]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = [
        'id' => (int)$row['Id'],
        'user_name' => $row['UserName'],
        'group_role' => $row['GroupRole'],
        'signature_path' => $row['SignaturePath'],
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'role' => $role, 'data' => $data]);
