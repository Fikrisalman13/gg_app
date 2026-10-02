<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && (int)$permissions['CanAdd'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah catatan.']);
    exit;
}

if (!kwhl_table_exists($conn)) {
    echo json_encode(['success' => false, 'message' => 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia.']);
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik belum tersedia.']);
    exit;
}
$tableLabel = kwhl_table_display_name($conn);

$tanggal = kwhl_normalize_date($_POST['tanggal'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));
if ($tanggal === '' || $note === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

$tableCols = kwhl_get_table_columns($conn);
if (!isset($tableCols['note'])) {
    echo json_encode(['success' => false, 'message' => 'Kolom note belum tersedia pada tabel ' . $tableLabel . '.']);
    exit;
}

$cek = sqlsrv_query($conn, "SELECT TOP 1 id FROM {$tableName} WHERE CAST(tanggal AS DATE)=?", [$tanggal]);
$row = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;
if ($cek) {
    sqlsrv_free_stmt($cek);
}
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Data tanggal tersebut belum ada. Input data harian dulu.']);
    exit;
}

$setParts = ['[note]=?'];
$params = [$note];
if (isset($tableCols['updateby'])) {
    $setParts[] = '[updateby]=?';
    $params[] = $_SESSION['UserName'];
}
if (isset($tableCols['updateat'])) {
    $setParts[] = '[updateat]=GETDATE()';
}
$params[] = (int)$row['id'];

$sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
    exit;
}
sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);
