<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/kwh_listrik_common.php');

header('Content-Type: application/json');

error_log('=== START SAVE KWH 21T ===');
error_log('POST: ' . json_encode($_POST));

// Validasi data
if (!isset($_POST['tanggal_21t'])) {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak ada.']);
    exit;
}

if (!isset($_POST['kwh_hari_ini'])) {
    echo json_encode(['success' => false, 'message' => 'KWH Hari Ini tidak ada.']);
    exit;
}

if (!isset($_POST['kwh_kemarin'])) {
    echo json_encode(['success' => false, 'message' => 'KWH Kemarin tidak ada.']);
    exit;
}

if (!isset($_POST['total_pemakaian'])) {
    echo json_encode(['success' => false, 'message' => 'Total Pemakaian tidak ada.']);
    exit;
}

if (!isset($_POST['total_biaya_21t'])) {
    echo json_encode(['success' => false, 'message' => 'Total Biaya tidak ada.']);
    exit;
}

$tanggal = kwhl_normalize_date($_POST['tanggal_21t']);
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$kwhHariIni = (float)kwhl_parse_number($_POST['kwh_hari_ini']);
$kwhKemarin = (float)kwhl_parse_number($_POST['kwh_kemarin']);
$tarif21t = (float)kwhl_parse_number($_POST['tarif_per_kwh_21t'] ?? kwhl_default_tarif());
if ($tarif21t <= 0) {
    $tarif21t = (float)kwhl_default_tarif();
}

// Hitung ulang di server agar tidak terpengaruh format tampilan input.
$kwhPemakaian = $kwhHariIni - $kwhKemarin;
if ($kwhPemakaian < 0) {
    $kwhPemakaian = 0.0;
}
$biayaPemakaian = $kwhPemakaian * $tarif21t;

error_log('Tanggal: ' . $tanggal);
error_log('KWH Hari Ini: ' . $kwhHariIni);
error_log('KWH Kemarin: ' . $kwhKemarin);
error_log('Tarif 21T: ' . $tarif21t);
error_log('Pemakaian: ' . $kwhPemakaian);
error_log('Biaya: ' . $biayaPemakaian);

$userId = $_SESSION['user_id'] ?? $_SESSION['NIK'] ?? 'SYSTEM';
$tableName = kwhl_table_full_name($conn);

error_log('User: ' . $userId);
error_log('Table: ' . $tableName);

if (!$tableName) {
    echo json_encode(['success' => false, 'message' => 'Tabel KWH Listrik tidak ditemukan di database.']);
    exit;
}

try {
    // Cek apakah data untuk tanggal ini sudah ada
    $checkSql = "SELECT TOP 1 id FROM " . $tableName . " WHERE CAST(tanggal AS DATE) = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$tanggal]);
    
    if ($checkStmt === false) {
        $err = sqlsrv_errors();
        error_log('Check Query Error: ' . json_encode($err));
        throw new Exception('Gagal mengecek data: ' . $err[0]['message']);
    }
    
    $existingRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($checkStmt);
    
    if (!$existingRow) {
        // Jika record belum ada, minta user input via Tambah Data terlebih dahulu
        throw new Exception('Silakan input data melalui form "Tambah Data" terlebih dahulu untuk tanggal ' . $tanggal . '. Form "Tambah Data 2 (21T Actom)" hanya mengupdate data yang sudah ada.');
    }
    
    // UPDATE hanya kolom 21ton actom, tanpa mengubah timestamp
    error_log('Record found, will UPDATE');
    $updateSql = "UPDATE " . $tableName . " 
                  SET kwh_hari_ini_21t = ?, 
                      kwh_kemarin_21t = ?, 
                      kwh_21ton_actom = ?, 
                      biaya_21ton_actom = ?
                  WHERE CAST(tanggal AS DATE) = ?";
    
    $updateParams = [$kwhHariIni, $kwhKemarin, $kwhPemakaian, $biayaPemakaian, $tanggal];
    
    error_log('Update SQL: ' . $updateSql);
    error_log('Update Params: ' . json_encode($updateParams));
    
    $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
    
    if ($updateStmt === false) {
        $err = sqlsrv_errors();
        error_log('Update Error: ' . json_encode($err));
        throw new Exception('Gagal mengupdate data: ' . $err[0]['message']);
    }
    sqlsrv_free_stmt($updateStmt);
    
    error_log('Update successful');
    error_log('=== END SAVE KWH 21T - SUCCESS ===');
    echo json_encode([
        'success' => true,
        'message' => 'Data 21 Ton Actom berhasil disimpan.'
    ]);

} catch (Exception $e) {
    error_log('Exception in save: ' . $e->getMessage());
    error_log('=== END SAVE KWH 21T - ERROR ===');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

