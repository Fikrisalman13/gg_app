<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId padsteam di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: padsteam.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid.";
    header('Location: padsteam.php');
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

$id = $_POST['id'] ?? '';
$tanggal = $_POST['tanggal'] ?? '';
$meterAwalRaw = $_POST['meter_awal'] ?? '';
$meterAkhirRaw = $_POST['meter_akhir'] ?? '';
$oprMesinRaw = $_POST['oprasional_mesin'] ?? '';
$keterangan = $_POST['keterangan'] ?? null;
$keteranganNorm = strtolower(trim((string)$keterangan));
$isOff = ($keteranganNorm === 'off');

if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: padsteam.php');
    exit;
}

if ($tanggal === '' || $meterAwalRaw === '' || $meterAkhirRaw === '') {
    $_SESSION['error'] = "Tanggal, Meter Awal, dan Meter Akhir wajib diisi.";
    header('Location: edit_padsteam.php?id=' . urlencode((string)$id));
    exit;
}

$meterAwal = normalize_decimal($meterAwalRaw);
$meterAkhir = normalize_decimal($meterAkhirRaw);
$oprMesin = $oprMesinRaw === '' ? null : normalize_decimal($oprMesinRaw);

if ($meterAwal === null || $meterAkhir === null) {
    $_SESSION['error'] = "Format angka tidak valid.";
    header('Location: edit_padsteam.php?id=' . urlencode((string)$id));
    exit;
}
if (!$isOff && $oprMesinRaw !== '' && $oprMesin === null) {
    $_SESSION['error'] = "Format angka operasional mesin tidak valid.";
    header('Location: edit_padsteam.php?id=' . urlencode((string)$id));
    exit;
}

$pemakaianRata2 = null;
if (!$isOff && $oprMesin !== null && (float)$oprMesin > 0) {
    $totalPemakaian = (float)$meterAkhir - (float)$meterAwal;
    $pemakaianRata2 = round(($totalPemakaian / (float)$oprMesin), 2);
}

$updateSql = "UPDATE dbo.padsteam_air
              SET Tanggal = ?, Meter_Awal = ?, Meter_Ahir = ?, Oprasional_Mesin = ?,
                  Pemakaian_Rata2perjam = ?, Keterangan = ?, UpdateAt = GETDATE()
              WHERE Id = ?";
$params = [$tanggal, $meterAwal, $meterAkhir, $isOff ? null : $oprMesin, $pemakaianRata2, $keterangan, (int)$id];
$updateStmt = sqlsrv_query($conn, $updateSql, $params);

if ($updateStmt === false) {
    $_SESSION['error'] = "Gagal mengupdate data.";
    header('Location: edit_padsteam.php?id=' . urlencode((string)$id));
    exit;
}

if ($updateStmt) {
    sqlsrv_free_stmt($updateStmt);
}

$_SESSION['success'] = "Data berhasil diupdate.";
header('Location: padsteam.php');
exit;

