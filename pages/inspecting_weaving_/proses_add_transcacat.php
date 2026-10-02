<?php
session_start();
// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');
ob_start();
include '../../koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil data dari form dengan validasi dasar
$noCP = $_POST['noCP'] ?? null;
$noDetail = $_POST['noDetail'] ?? null;
$cacatId = $_POST['cacatId'] ?? null;
$meterKe = $_POST['meterKe'] ?? null;
$sMeterKe = $_POST['sMeterKe'] ?? null;
$pointCacat = $_POST['pointCacat'] ?? null;
$shiftId = $_POST['shiftId'] ?? null;

// Validasi input tidak boleh kosong
if (
    trim($noCP) === '' || trim($noDetail) === '' || trim($cacatId) === '' ||
    trim($meterKe) === '' || trim($sMeterKe) === '' || trim($pointCacat) === '' || trim($shiftId) === ''
) {
    $_SESSION['error'] = "Semua field harus diisi!";
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

// Validasi angka agar hanya menerima nilai numerik
if (!is_numeric($noDetail) || !is_numeric($meterKe) || !is_numeric($sMeterKe) || !is_numeric($pointCacat)) {
    $_SESSION['error'] = "Harap masukkan angka yang valid!";
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

// Pastikan ShiftId sebagai string
$shiftId = strval($shiftId);

// Tambahkan UpdDate dan UpdUser
$updDate = date('Y-m-d H:i:s'); // Format yang sesuai untuk SQL Server
$updUser = $_SESSION['UserName'] ?? 'system';

// Perbaiki Query dengan tambahan UpdDate dan UpdUser
$sql = "INSERT INTO dbo.SMCacatDetail 
        (NoCP, NoDetail, CacatId, MeterKe, SMeterKe, PointCacat, ShiftId, UpdDate, UpdUser) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$params = [$noCP, $noDetail, $cacatId, $meterKe, $sMeterKe, $pointCacat, $shiftId, $updDate, $updUser];

if (isset($_POST['lastNoDetail']) && $_POST['lastNoDetail'] == "1") {
    $sqlUpdate = "UPDATE dbo.FormInspectHd SET FgCacat = 1 WHERE NoCP = ?";
    $paramsUpdate = [$noCP];
    sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
}

// Jalankan Query
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    sqlsrv_free_stmt($stmt); // Bebaskan resource statement
    $_SESSION['success'] = "Data cacat berhasil ditambahkan!";

    // Cek apakah checkbox 'lastNoDetail' dicentang
    if (isset($_POST['lastNoDetail']) && $_POST['lastNoDetail'] == "1") {
        // Jika dicentang, redirect ke datacacat_inspecting_weaving.php
        header('Location: datacacat_inspecting_weaving.php');
    } else {
        // Jika tidak dicentang, redirect ke add_transcacat.php dengan parameter noCP
        header('Location: add_transcacat.php?noCP=' . urlencode($noCP));
    }
    exit;
} else {
    $errors = sqlsrv_errors();
    $_SESSION['error'] = "Gagal menambahkan data cacat. Error: " . ($errors ? print_r($errors, true) : "Tidak diketahui");
    header("Location: add_transcacat.php?noCP=" . urlencode($noCP));
    exit;
}

ob_end_flush();
?>
