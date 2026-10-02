<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: lab.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid.";
    header('Location: lab.php');
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

$id = $_POST['id'] ?? '';
$tanggal = trim($_POST['tanggal'] ?? '');
$meterAwal = normalize_decimal($_POST['meter_awal'] ?? '');
$meterAkhir = normalize_decimal($_POST['meter_akhir'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');

if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: lab.php');
    exit;
}
if ($tanggal === '') {
    $_SESSION['error'] = "Tanggal wajib diisi.";
    header('Location: edit_lab.php?id=' . urlencode((string)$id));
    exit;
}
if ($meterAwal === null || $meterAkhir === null) {
    $_SESSION['error'] = "Meter awal dan meter akhir wajib diisi dengan angka valid.";
    header('Location: edit_lab.php?id=' . urlencode((string)$id));
    exit;
}
if ((float)$meterAkhir < (float)$meterAwal) {
    $_SESSION['error'] = "Meter akhir tidak boleh lebih kecil dari meter awal.";
    header('Location: edit_lab.php?id=' . urlencode((string)$id));
    exit;
}

$meterAwal = round((float)$meterAwal, 3);
$meterAkhir = round((float)$meterAkhir, 3);
$totalPemakaian = round($meterAkhir - $meterAwal, 2);

$sql = "UPDATE dbo.lab_air
        SET Tanggal = ?,
            Meter_Awal = ?,
            Meter_Akhir = ?,
            Total_Pemakaian = ?,
            Keterangan = ?,
            Catatan = ?,
            UpdateBy = ?,
            UpdateAt = GETDATE()
        WHERE Id = ?";
$params = [$tanggal, $meterAwal, $meterAkhir, $totalPemakaian, $keterangan, $catatan, $_SESSION['UserName'], (int)$id];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengupdate data.";
    header('Location: edit_lab.php?id=' . urlencode((string)$id));
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$_SESSION['success'] = "Data berhasil diupdate.";
header('Location: lab.php');
exit;

