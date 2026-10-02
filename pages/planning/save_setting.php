<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

$username = $_POST['username'] ?? '';
$rtg_name = $_POST['rtg_name'] ?? '';
$createdBy = $_SESSION['UserName'];

if (empty($username) || empty($rtg_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Username dan Routing tidak boleh kosong.']);
    exit;
}

// Cek apakah kombinasi akun dan routing ini sudah ada
$sqlCek = "SELECT id FROM planning_setting WHERE username = ? AND rtg_name = ?";
$stmtCek = sqlsrv_query($conn, $sqlCek, [$username, $rtg_name]);
if ($stmtCek && sqlsrv_has_rows($stmtCek)) {
    echo json_encode(['status' => 'error', 'message' => 'Akses Routing ini sudah terdaftar untuk pengguna tersebut.']);
    exit;
}

// Insert Baru
$sqlInsert = "
    INSERT INTO planning_setting (username, rtg_name, created_at, created_by)
    VALUES (?, ?, GETDATE(), ?)
";
$params = [$username, $rtg_name, $createdBy];
$stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);

if ($stmtInsert) {
    echo json_encode(['status' => 'success', 'message' => 'Berhasil disimpan']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database.']);
}
?>
