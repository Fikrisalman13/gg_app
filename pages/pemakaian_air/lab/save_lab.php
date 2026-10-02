<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && (int)$permissions['CanAdd'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah data.']);
    exit;
}

function normalize_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    if (strpos($value, '.') !== false) {
        $v = str_replace(',', '', $value);
        if (is_numeric($v)) return $v;
    }

    if (strpos($value, '.') !== false && strpos($value, ',') === false) {
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $int = implode('', $parts);
            $normalized = $int . '.' . $dec;
            if (is_numeric($normalized)) return $normalized;
        }
    }

    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$meterAwal = normalize_decimal($_POST['meter_awal'] ?? '');
$meterAkhir = normalize_decimal($_POST['meter_akhir'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');

if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi.']);
    exit;
}
if ($meterAwal === null || $meterAkhir === null) {
    echo json_encode(['success' => false, 'message' => 'Meter awal dan meter akhir wajib diisi dengan angka valid.']);
    exit;
}
if ((float)$meterAkhir < (float)$meterAwal) {
    echo json_encode(['success' => false, 'message' => 'Meter akhir tidak boleh lebih kecil dari meter awal.']);
    exit;
}

$meterAwal = round((float)$meterAwal, 3);
$meterAkhir = round((float)$meterAkhir, 3);
$totalPemakaian = round($meterAkhir - $meterAwal, 2);

$findSql = "SELECT TOP 1 Id FROM dbo.lab_air WHERE CAST(Tanggal AS DATE) = ? ORDER BY Id DESC";
$findStmt = sqlsrv_query($conn, $findSql, [$tanggal]);
if ($findStmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengecek data existing.']);
    exit;
}
$existing = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
if ($findStmt) sqlsrv_free_stmt($findStmt);

if ($existing && !empty($existing['Id'])) {
    $sql = "UPDATE dbo.lab_air
            SET Meter_Awal = ?, Meter_Akhir = ?, Total_Pemakaian = ?, Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE()
            WHERE Id = ?";
    $params = [$meterAwal, $meterAkhir, $totalPemakaian, $keterangan, $_SESSION['UserName'], (int)$existing['Id']];
    $msg = 'Data berhasil diperbarui.';
} else {
    $sql = "INSERT INTO dbo.lab_air (Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian, Keterangan, CreatBy)
            VALUES (?, ?, ?, ?, ?, ?)";
    $params = [$tanggal, $meterAwal, $meterAkhir, $totalPemakaian, $keterangan, $_SESSION['UserName']];
    $msg = 'Data berhasil disimpan.';
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => $msg]);

