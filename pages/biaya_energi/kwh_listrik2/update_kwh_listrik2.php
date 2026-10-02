<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: kwh_listrik2.php');
    exit;
}

$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak mengubah data.';
    header('Location: kwh_listrik2.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$tanggal = kwhl2_normalize_date($_POST['tanggal'] ?? '');
if ($id <= 0 || $tanggal === '') {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: kwh_listrik2.php');
    exit;
}

if (!kwhl2_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel ' . kwhl2_table_display_name($conn) . ' belum tersedia.';
    header('Location: kwh_listrik2.php');
    exit;
}
$tableName = kwhl2_table_full_name($conn);
if ($tableName === '') {
    $_SESSION['error'] = 'Tabel kwh_listrik2 belum tersedia.';
    header('Location: kwh_listrik2.php');
    exit;
}

$computed = kwhl2_compute_row($_POST);
$ket = trim((string)($_POST['ket'] ?? ''));

$valueMap = [
    'tanggal' => $tanggal,
    'tarif_per_kwh' => $computed['tarif_per_kwh'],
    'total_kwh' => $computed['total_kwh'],
    'total_biaya' => $computed['total_biaya'],
    'ket' => $ket,
];

foreach (kwhl2_machine_defs() as $code => $def) {
    $valueMap[$def['hi_key']] = $computed[$def['hi_key']] ?? 0;
    $valueMap[$def['km_key']] = $computed[$def['km_key']] ?? 0;
    $valueMap[$def['kwh_key']] = $computed[$def['kwh_key']] ?? 0;
    $valueMap[$def['biaya_key']] = $computed[$def['biaya_key']] ?? 0;

    if (isset($def['alt_kwh_key'])) {
        $valueMap[$def['alt_kwh_key']] = $computed[$def['kwh_key']] ?? 0;
    }
    if (isset($def['alt_biaya_key'])) {
        $valueMap[$def['alt_biaya_key']] = $computed[$def['biaya_key']] ?? 0;
    }
    if (isset($def['alt_hi_key'])) {
        $valueMap[$def['alt_hi_key']] = $computed[$def['hi_key']] ?? 0;
    }
    if (isset($def['alt_km_key'])) {
        $valueMap[$def['alt_km_key']] = $computed[$def['km_key']] ?? 0;
    }
}

$tableCols = kwhl2_get_table_columns($conn);
$setParts = [];
$params = [];
foreach ($valueMap as $col => $val) {
    if (isset($tableCols[strtolower($col)])) {
        $setParts[] = '[' . $col . ']=?';
        $params[] = $val;
    }
}
if (isset($tableCols['updateby'])) {
    $setParts[] = '[updateby]=?';
    $params[] = $_SESSION['UserName'];
}
if (isset($tableCols['updateat'])) {
    $setParts[] = '[updateat]=GETDATE()';
}

if (empty($setParts)) {
    $_SESSION['error'] = 'Tidak ada kolom yang bisa diperbarui.';
    header('Location: edit_kwh_listrik2.php?id=' . urlencode((string)$id));
    exit;
}

$params[] = $id;
$sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal memperbarui data.';
    header('Location: edit_kwh_listrik2.php?id=' . urlencode((string)$id));
    exit;
}
sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil diperbarui.';
header('Location: kwh_listrik2.php');
exit;
