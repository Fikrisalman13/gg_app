<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId washing di database
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

    if (strpos($value, '.') !== false) {
        $value = str_replace(',', '', $value);
        if (is_numeric($value)) return $value;
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
$operasionalMesin = normalize_decimal($_POST['operasional_mesin'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$isOff = preg_match('/^\s*off\s*$/i', $keterangan) === 1;

if ($tanggal === '' || $meterAwal === null || $meterAkhir === null) {
    echo json_encode(['success' => false, 'message' => 'Tanggal, Meter Awal, dan Meter Akhir wajib diisi.']);
    exit;
}

$totalPemakaian = null;
$pemakaianRata2 = null;
if (!$isOff) {
    $totalPemakaian = round(((float)$meterAkhir - (float)$meterAwal), 2);
    if ($operasionalMesin !== null && (float)$operasionalMesin > 0) {
        $pemakaianRata2 = round($totalPemakaian / (float)$operasionalMesin, 2);
    }
} else {
    $operasionalMesin = null;
}

$sql = "INSERT INTO dbo.washing_air
        (Tanggal, CreatBy, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$params = [
    $tanggal,
    $_SESSION['UserName'],
    $meterAwal,
    $meterAkhir,
    $totalPemakaian,
    $operasionalMesin,
    $pemakaianRata2,
    $keterangan
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}

if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);
