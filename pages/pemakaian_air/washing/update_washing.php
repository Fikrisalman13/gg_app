<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId washing di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: washing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid.";
    header('Location: washing.php');
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

$id = $_POST['id'] ?? '';
$tanggal = trim($_POST['tanggal'] ?? '');
$meterAwal = normalize_decimal($_POST['meter_awal'] ?? '');
$meterAkhir = normalize_decimal($_POST['meter_akhir'] ?? '');
$operasionalMesin = normalize_decimal($_POST['operasional_mesin'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
$isOff = preg_match('/^\s*off\s*$/i', $keterangan) === 1;

if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: washing.php');
    exit;
}

if ($tanggal === '' || $meterAwal === null || $meterAkhir === null) {
    $_SESSION['error'] = "Tanggal, Meter Awal, dan Meter Akhir wajib diisi.";
    header('Location: edit_washing.php?id=' . urlencode((string)$id));
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

$updateSql = "UPDATE dbo.washing_air
              SET Tanggal = ?, Meter_Awal = ?, Meter_Ahir = ?, Total_Pemakaian = ?,
                  Operasional_Mesin = ?, Pemakaian_rata2perjam = ?, Keterangan = ?, Catatan = ?,
                  UpdateBy = ?, UpdateAt = GETDATE()
              WHERE Id = ?";
$params = [$tanggal, $meterAwal, $meterAkhir, $totalPemakaian, $operasionalMesin, $pemakaianRata2, $keterangan, $catatan, $_SESSION['UserName'], (int)$id];
$updateStmt = sqlsrv_query($conn, $updateSql, $params);

if ($updateStmt === false) {
    $_SESSION['error'] = "Gagal mengupdate data.";
    header('Location: edit_washing.php?id=' . urlencode((string)$id));
    exit;
}

if ($updateStmt) sqlsrv_free_stmt($updateStmt);

$_SESSION['success'] = "Data berhasil diupdate.";
header('Location: washing.php');
exit;
