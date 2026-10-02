<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanAdd']) && (int)$permissions['CanAdd'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah catatan.']);
    exit;
}

if (!kwhl2_table_exists($conn)) {
    echo json_encode(['success' => false, 'message' => 'Tabel ' . kwhl2_table_display_name($conn) . ' belum tersedia.']);
    exit;
}
$tableName = kwhl2_table_full_name($conn);
if ($tableName === '') {
    echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik2 belum tersedia.']);
    exit;
}
$tableLabel = kwhl2_table_display_name($conn);

$tanggal = kwhl2_normalize_date($_POST['tanggal'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));
if ($tanggal === '' || $note === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal dan catatan wajib diisi.']);
    exit;
}

$tableCols = kwhl2_get_table_columns($conn);
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
    // Jika belum ada row untuk tanggal tsb, insert row dengan catatan
    $insertCols = ['[tanggal]', '[note]'];
    $insertVals = ['?', '?'];
    $params = [$tanggal, $note];
    if (isset($tableCols['creatby'])) {
        $insertCols[] = '[creatby]';
        $insertVals[] = '?';
        $params[] = $_SESSION['UserName'];
    }
    if (isset($tableCols['createby'])) {
        $insertCols[] = '[createby]';
        $insertVals[] = '?';
        $params[] = $_SESSION['UserName'];
    }
    if (isset($tableCols['createat'])) {
        $insertCols[] = '[createat]';
        $insertVals[] = 'GETDATE()';
    }
    $sql = "INSERT INTO {$tableName} (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    echo json_encode(['success' => true, 'message' => 'Catatan berhasil disimpan.']);
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
