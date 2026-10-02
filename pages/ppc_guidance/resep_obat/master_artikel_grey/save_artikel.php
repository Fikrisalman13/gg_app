<?php
// pages/resep_obat/master_artikel_grey/save_artikel.php
require_once __DIR__ . '/../../../../koneksi.php';

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

$padry = $_POST['padry'] ?? '';

if (empty($kode_gray) || empty($nama_artikel)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode dan Nama wajib diisi']);
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
        $params = [$kode_gray, $nama_artikel, $gramasi, $pickup, $padry, $now, $user];
    } else {
        $sql = "UPDATE dbo.master_artikel_grey SET kode_gray = ?, nama_artikel = ?, gramasi = ?, pickup = ?, padry = ?, updated_at = ?, updated_by = ? WHERE id = ?";
        $params = [$kode_gray, $nama_artikel, $gramasi, $pickup, $padry, $now, $user, $id];
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
