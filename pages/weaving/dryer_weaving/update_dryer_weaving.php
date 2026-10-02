<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
weaving_require($conn, 'CanEdit', true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
if (!dryer_weaving_table_exists($conn)) json_response(['success' => false, 'message' => 'Tabel dbo.dryer_weaving belum tersedia.']);

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');
$dryerNoParam = trim($_POST['dryer_no'] ?? '');
$ctNoParam = trim($_POST['ct_no'] ?? '');

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = dryer_weaving_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $dryerNoParam !== '' && $ctNoParam !== '') {
    $sheet = dryer_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $dryerNoParam, $ctNoParam);
}
if (!$sheet) json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);

$tanggal = dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$dryerNo = $sheet['Dryer_No'] ?? '';
$ctNo = $sheet['Ct_No'] ?? '';
$petugas = trim($_POST['petugas_default'] ?? '');
$shift = trim($_POST['shift_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$itemMap = dryer_weaving_item_map();
$allowedHours = dryer_weaving_hours();

if ($shift !== '' && !in_array($shift, dryer_weaving_shift_options(), true)) {
    $shift = '';
}
if (!is_array($rows)) json_response(['success' => false, 'message' => 'Format data input tidak valid.']);

$rowsToSave = [];
foreach ($rows as $itemKey => $hourValues) {
    if (!isset($itemMap[$itemKey]) || !is_array($hourValues)) continue;
    foreach ($hourValues as $hour => $value) {
        $hour = trim((string)$hour);
        $value = trim((string)$value);
        if ($value === '' || !in_array($hour, $allowedHours, true)) continue;
        $rowsToSave[] = [
            'item_key' => $itemKey,
            'item_check' => $itemMap[$itemKey]['plain'],
            'kategori' => $itemMap[$itemKey]['category'],
            'standar' => $itemMap[$itemKey]['standard'],
            'jam' => $hour,
            'nilai' => $value,
        ];
    }
}

if (count($rowsToSave) === 0) json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);

$existingCells = dryer_weaving_get_sheet_cells($conn, $tanggal, $dryerNo, $ctNo);

if (!sqlsrv_begin_transaction($conn)) json_response(['success' => false, 'message' => 'Gagal memulai transaksi update data.']);

$userName = $_SESSION['UserName'];
$processedIds = [];

foreach ($rowsToSave as $row) {
    $cellKey = $row['item_key'] . '|' . $row['jam'];
    $existingCell = $existingCells[$cellKey] ?? null;

    // Shift: jika cell ini sudah ada sebelumnya dan punya shift, pertahankan shift aslinya!
    if ($existingCell && !empty($existingCell['shift'])) {
        $rowShift = $existingCell['shift'];
    } else {
        $rowShift = $shift !== '' ? $shift : null;
    }

    // Petugas: jika cell ini sudah ada sebelumnya dan punya petugas, pertahankan petugas aslinya!
    if ($existingCell && !empty($existingCell['petugas'])) {
        $rowPetugas = $existingCell['petugas'];
    } else {
        $rowPetugas = $petugas !== '' ? $petugas : null;
    }

    if ($existingCell && !empty($existingCell['id'])) {
        // In-place UPDATE, ID stays identical!
        $updateSql = "UPDATE dbo.dryer_weaving
                      SET Kategori = ?, Item_Check = ?, Standar = ?, Nilai = ?,
                          Petugas = ?, Shift = ?, Keterangan = ?,
                          UpdateBy = ?, UpdateAt = GETDATE()
                      WHERE Id = ?";
        $params = [
            $row['kategori'], $row['item_check'], $row['standar'], $row['nilai'],
            $rowPetugas, $rowShift, $keterangan !== '' ? $keterangan : null,
            $userName, (int)$existingCell['id']
        ];
        $stmt = sqlsrv_query($conn, $updateSql, $params);
        if ($stmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal memperbarui data.']);
        }
        if ($stmt) sqlsrv_free_stmt($stmt);
        $processedIds[] = (int)$existingCell['id'];
    } else {
        // INSERT new cell
        $insertSql = "INSERT INTO dbo.dryer_weaving
                (Tanggal, Dryer_No, Ct_No, Jam, Kategori, Item_Key, Item_Check, Standar,
                 Nilai, Petugas, Shift, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, GETDATE())";
        $params = [
            $tanggal, $dryerNo, $ctNo, $row['jam'], $row['kategori'], $row['item_key'],
            $row['item_check'], $row['standar'], $row['nilai'],
            $rowPetugas, $rowShift, $keterangan !== '' ? $keterangan : null,
            $userName, $userName
        ];
        $stmt = sqlsrv_query($conn, $insertSql, $params);
        if ($stmt === false) {
            sqlsrv_rollback($conn);
            json_response(['success' => false, 'message' => 'Gagal menyimpan perubahan data.']);
        }
        if ($stmt) sqlsrv_free_stmt($stmt);
    }
}

// Delete cells that were previously in DB for this sheet but now removed from form
foreach ($existingCells as $exCell) {
    if (!empty($exCell['id']) && !in_array((int)$exCell['id'], $processedIds, true)) {
        $delStmt = sqlsrv_query($conn, "DELETE FROM dbo.dryer_weaving WHERE Id = ?", [(int)$exCell['id']]);
        if ($delStmt) sqlsrv_free_stmt($delStmt);
    }
}

// Sync Keterangan across sheet
if ($keterangan !== '') {
    $syncKetSql = "UPDATE dbo.dryer_weaving SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ? AND Dryer_No = ? AND Ct_No = ?";
    $syncKetStmt = sqlsrv_query($conn, $syncKetSql, [$keterangan, $tanggal, $dryerNo, $ctNo]);
    if ($syncKetStmt) sqlsrv_free_stmt($syncKetStmt);
}

sqlsrv_commit($conn);

$currentSheet = dryer_weaving_resolve_sheet_by_keys($conn, $tanggal, $dryerNo, $ctNo);
$newId = $currentSheet['Id'] ?? $id;

json_response([
    'success' => true,
    'message' => 'Data Dryer Weaving berhasil diperbarui.',
    'new_id' => $newId
]);

