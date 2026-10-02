<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: kwh_listrik.php');
    exit;
}

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
    $_SESSION['error'] = 'Anda tidak memiliki hak mengubah data.';
    header('Location: kwh_listrik.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$tanggal = kwhl_normalize_date($_POST['tanggal'] ?? '');
if ($id <= 0 || $tanggal === '') {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: kwh_listrik.php');
    exit;
}

if (!kwhl_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    $_SESSION['error'] = 'Tabel kwh_listrik belum tersedia.';
    header('Location: kwh_listrik.php');
    exit;
}

$computed = kwhl_compute_row($_POST);
$ket = trim((string)($_POST['ket'] ?? ''));
$valueMap = ['tanggal' => $tanggal];
foreach (kwhl_amp_columns() as $col) {
    $valueMap[$col] = $computed[$col] ?? 0;
}
foreach (kwhl_kwh_columns() as $col) {
    $valueMap[$col] = $computed[$col] ?? 0;
}
foreach (kwhl_biaya_columns() as $col) {
    $valueMap[$col] = $computed[$col] ?? 0;
}
$valueMap['faktor_konversi'] = $computed['faktor_konversi'];
$valueMap['tarif_per_kwh'] = $computed['tarif_per_kwh'];
$valueMap['total_kwh'] = $computed['total_kwh'];
$valueMap['total_biaya'] = $computed['total_biaya'];
$valueMap['ket'] = $ket;

$tableCols = kwhl_get_table_columns($conn);
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
    header('Location: edit_kwh_listrik.php?id=' . urlencode((string)$id));
    exit;
}

$params[] = $id;
$sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $_SESSION['error'] = 'Gagal memperbarui data.';
    header('Location: edit_kwh_listrik.php?id=' . urlencode((string)$id));
    exit;
}
sqlsrv_free_stmt($stmt);

$_SESSION['success'] = 'Data berhasil diperbarui.';
header('Location: kwh_listrik.php');
exit;
