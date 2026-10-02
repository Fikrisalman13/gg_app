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
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak menambah data.']);
    exit;
}

if (!kwhl_table_exists($conn)) {
    echo json_encode(['success' => false, 'message' => 'Tabel ' . kwhl_table_display_name($conn) . ' belum tersedia. Silakan buat tabel database terlebih dahulu.']);
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    echo json_encode(['success' => false, 'message' => 'Tabel kwh_listrik belum tersedia.']);
    exit;
}
$tableLabel = kwhl_table_display_name($conn);

$tanggal = kwhl_normalize_date($_POST['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal wajib diisi dengan format valid.']);
    exit;
}

$ket = trim((string)($_POST['ket'] ?? ''));
$computed = kwhl_compute_row($_POST);

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
if (empty($tableCols)) {
    echo json_encode(['success' => false, 'message' => 'Kolom tabel ' . $tableLabel . ' tidak dapat dibaca.']);
    exit;
}

$cekStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM {$tableName} WHERE CAST(tanggal AS DATE)=?", [$tanggal]);
$existing = $cekStmt ? sqlsrv_fetch_array($cekStmt, SQLSRV_FETCH_ASSOC) : null;
if ($cekStmt) {
    sqlsrv_free_stmt($cekStmt);
}

$username = $_SESSION['UserName'] ?? '';
if ($existing && !empty($existing['id'])) {
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
        $params[] = $username;
    }
    if (isset($tableCols['updateat'])) {
        $setParts[] = '[updateat]=GETDATE()';
    }
    if (empty($setParts)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada kolom yang bisa diperbarui.']);
        exit;
    }

    $params[] = (int)$existing['id'];
    $sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE id=?";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui.']);
    exit;
}

$insertCols = [];
$insertVals = [];
$params = [];
foreach ($valueMap as $col => $val) {
    if (isset($tableCols[strtolower($col)])) {
        $insertCols[] = '[' . $col . ']';
        $insertVals[] = '?';
        $params[] = $val;
    }
}
if (isset($tableCols['creatby'])) {
    $insertCols[] = '[creatby]';
    $insertVals[] = '?';
    $params[] = $username;
}
if (isset($tableCols['createby'])) {
    $insertCols[] = '[createby]';
    $insertVals[] = '?';
    $params[] = $username;
}
if (isset($tableCols['created_by'])) {
    $insertCols[] = '[created_by]';
    $insertVals[] = '?';
    $params[] = $username;
}
if (isset($tableCols['creatat'])) {
    $insertCols[] = '[creatat]';
    $insertVals[] = 'GETDATE()';
}
if (isset($tableCols['createat'])) {
    $insertCols[] = '[createat]';
    $insertVals[] = 'GETDATE()';
}
if (isset($tableCols['created_at'])) {
    $insertCols[] = '[created_at]';
    $insertVals[] = 'GETDATE()';
}

if (empty($insertCols)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada kolom yang bisa disimpan.']);
    exit;
}

$sql = "INSERT INTO {$tableName} (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
    exit;
}
sqlsrv_free_stmt($stmt);
echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);
