<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId padsteam di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && $permissions['CanAdd'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah data.']);
    exit;
}

function normalize_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    // Primary format: 77,942.2956 (comma thousands, dot decimal)
    if (strpos($value, '.') !== false) {
        $value = str_replace(',', '', $value);
        if (is_numeric($value)) return $value;
    }

    // Backward compatibility for old input like 77.942.2956
    if (strpos($value, '.') !== false && strpos($value, ',') === false) {
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $int = implode('', $parts);
            $normalized = $int . '.' . $dec;
            if (is_numeric($normalized)) return $normalized;
        }
    }

    // Fallback: comma decimal
    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

$tanggal = $_POST['tanggal'] ?? '';
$meterAwalRaw = $_POST['meter_awal'] ?? '';
$meterAkhirRaw = $_POST['meter_akhir'] ?? '';
$oprMesinRaw = $_POST['oprasional_mesin'] ?? '';
$keterangan = $_POST['keterangan'] ?? null;
$keteranganNorm = strtolower(trim((string)$keterangan));
$isOff = ($keteranganNorm === 'off');

if ($tanggal === '' || $meterAwalRaw === '' || $meterAkhirRaw === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal, Meter Awal, dan Meter Akhir wajib diisi.']);
    exit;
}

$meterAwal = normalize_decimal($meterAwalRaw);
$meterAkhir = normalize_decimal($meterAkhirRaw);
$oprMesin = $oprMesinRaw === '' ? null : normalize_decimal($oprMesinRaw);

if ($meterAwal === null || $meterAkhir === null) {
    echo json_encode(['success' => false, 'message' => 'Format angka tidak valid.']);
    exit;
}
if (!$isOff && $oprMesinRaw !== '' && $oprMesin === null) {
    echo json_encode(['success' => false, 'message' => 'Format angka operasional mesin tidak valid.']);
    exit;
}

$pemakaianRata2 = null;
if (!$isOff && $meterAwal !== null && $meterAkhir !== null && $oprMesin !== null && (float)$oprMesin > 0) {
    $totalPemakaian = (float)$meterAkhir - (float)$meterAwal;
    $pemakaianRata2 = round(($totalPemakaian / (float)$oprMesin), 2);
}

$sql = "INSERT INTO dbo.padsteam_air
        (Tanggal, CreatBy, Meter_Awal, Meter_Ahir, Oprasional_Mesin, Pemakaian_Rata2perjam, Keterangan)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$params = [
    $tanggal,
    $_SESSION['UserName'],
    $meterAwal,
    $meterAkhir,
    $isOff ? null : $oprMesin,
    $pemakaianRata2,
    $keterangan
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);

