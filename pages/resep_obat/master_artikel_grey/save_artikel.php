<?php
// pages/resep_obat/master_artikel_grey/save_artikel.php
require_once __DIR__ . '/../../../koneksi.php';

session_start();
$user = $_SESSION['UserName'] ?? 'SYSTEM';
$now = date('Y-m-d H:i:s');

header('Content-Type: application/json');

$mode = $_POST['mode'] ?? 'add';
$id = $_POST['id'] ?? '';
$kode_gray = $_POST['kode_gray'] ?? '';
$nama_artikel = $_POST['nama_artikel'] ?? '';

// Handle Comma to Dot
$gramasiStr = $_POST['gramasi'] ?? '0';
$pickupStr = $_POST['pickup'] ?? '0';

$gramasi = floatval(str_replace(',', '.', $gramasiStr));
$pickup = floatval(str_replace(',', '.', $pickupStr));

$machineCode = trim((string) ($_POST['machine_code'] ?? ''));

if (empty($kode_gray) || empty($nama_artikel) || $machineCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Kode, nama artikel, dan mesin wajib diisi']);
    exit;
}

$machineStmt = sqlsrv_query(
    $conn,
    "SELECT kode_mesin FROM dbo.master_mesin_lab WHERE kode_mesin = ? AND status = 'Active'",
    [$machineCode]
);
if ($machineStmt === false || !sqlsrv_fetch_array($machineStmt, SQLSRV_FETCH_ASSOC)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Mesin tidak valid atau tidak aktif']);
    exit;
}

try {
    if ($mode === 'add') {
        // Check Duplicate -> DISABLED
        /*
        $sqlCheck = "SELECT COUNT(*) as cnt FROM dbo.master_artikel_grey WHERE kode_gray = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$kode_gray]);
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Kode Gray sudah ada']);
            exit;
        }
        */

        $sql = "INSERT INTO dbo.master_artikel_grey (kode_gray, nama_artikel, gramasi, pickup, padry, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [$kode_gray, $nama_artikel, $gramasi, $pickup, $machineCode, $now, $user];
    } else {
        $sql = "UPDATE dbo.master_artikel_grey SET kode_gray = ?, nama_artikel = ?, gramasi = ?, pickup = ?, padry = ?, updated_at = ?, updated_by = ? WHERE id = ?";
        $params = [$kode_gray, $nama_artikel, $gramasi, $pickup, $machineCode, $now, $user, $id];
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception(print_r(sqlsrv_errors(), true));
    }

    echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
