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

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanAdd', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
}

$editExisting = isset($_POST['edit_existing']) && (string)$_POST['edit_existing'] === '1';
if ($editExisting) {
    weaving_require($conn, 'CanEdit', true);
}

if (!dryer_weaving_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.dryer_weaving belum tersedia. Jalankan create_table_dryer_weaving.sql terlebih dahulu.']);
}

$tanggal = trim($_POST['tanggal'] ?? '');
$dryerNo = trim($_POST['dryer_no'] ?? '');
$ctNo = trim($_POST['ct_no'] ?? '');
$petugas = trim($_POST['petugas_default'] ?? '');
$shift = trim($_POST['shift_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$itemMap = dryer_weaving_item_map();
$allowedHours = dryer_weaving_hours();
$allowedNos = dryer_weaving_no_options();

if ($tanggal === '' || $dryerNo === '' || $ctNo === '') {
    json_response(['success' => false, 'message' => 'Tanggal, Dryer No, dan CT No wajib diisi.']);
}
if (!in_array($dryerNo, $allowedNos, true) || !in_array($ctNo, $allowedNos, true)) {
    json_response(['success' => false, 'message' => 'Dryer No dan CT No harus 01 atau 02.']);
}
if (!is_array($rows)) {
    json_response(['success' => false, 'message' => 'Format data input tidak valid.']);
}

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

$userName = $_SESSION['UserName'];

if (count($rowsToSave) === 0) {
    // Jika tidak ada baris pengukuran baru/diubah, cek apakah user memperbarui keterangan pada data yang sudah ada
    if ($tanggal !== '' && $dryerNo !== '' && $ctNo !== '') {
        $checkExistSql = "SELECT COUNT(*) AS total FROM dbo.dryer_weaving WHERE CAST(Tanggal AS DATE) = ? AND Dryer_No = ? AND Ct_No = ?";
        $checkExistStmt = sqlsrv_query($conn, $checkExistSql, [$tanggal, $dryerNo, $ctNo]);
        $existRow = $checkExistStmt ? sqlsrv_fetch_array($checkExistStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($checkExistStmt) sqlsrv_free_stmt($checkExistStmt);

        if (!empty($existRow['total']) && (int)$existRow['total'] > 0) {
            $updKetSql = "UPDATE dbo.dryer_weaving SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE CAST(Tanggal AS DATE) = ? AND Dryer_No = ? AND Ct_No = ?";
            $updKetStmt = sqlsrv_query($conn, $updKetSql, [$keterangan !== '' ? $keterangan : null, $userName, $tanggal, $dryerNo, $ctNo]);
            if ($updKetStmt === false) {
                json_response(['success' => false, 'message' => 'Gagal memperbarui keterangan.']);
            }
            if ($updKetStmt) sqlsrv_free_stmt($updKetStmt);
            json_response(['success' => true, 'message' => 'Keterangan berhasil disimpan.']);
        }
    }
    json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
}

// Cek apakah ada baris baru yang akan diinsert (belum ada di database)
$hasNewRowsToInsert = false;
foreach ($rowsToSave as $r) {
    $chkSql = "SELECT TOP 1 Id FROM dbo.dryer_weaving
                WHERE CAST(Tanggal AS DATE) = ?
                  AND Dryer_No = ?
                  AND Ct_No = ?
                  AND Item_Key = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?";
    $chkStmt = sqlsrv_query($conn, $chkSql, [$tanggal, $dryerNo, $ctNo, $r['item_key'], $r['jam']]);
    $chkRow = $chkStmt ? sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC) : null;
    if ($chkStmt) sqlsrv_free_stmt($chkStmt);
    if (!$chkRow) {
        $hasNewRowsToInsert = true;
        break;
    }
}

if ($hasNewRowsToInsert) {
    if ($petugas === '' || !in_array($shift, dryer_weaving_shift_options(), true)) {
        json_response(['success' => false, 'message' => 'Petugas wajib diisi dan shift harus sesuai pilihan untuk data baru yang ditambahkan.']);
    }
} else {
    if ($shift !== '' && !in_array($shift, dryer_weaving_shift_options(), true)) {
        $shift = '';
    }
}

if (!sqlsrv_begin_transaction($conn)) {
    json_response(['success' => false, 'message' => 'Gagal memulai transaksi simpan data.']);
}

$saved = 0;
$updated = 0;
$skipped = 0;

foreach ($rowsToSave as $row) {
    $findSql = "SELECT TOP 1 Id, Nilai, Petugas, [Shift] AS ShiftName, Keterangan FROM dbo.dryer_weaving
                WHERE CAST(Tanggal AS DATE) = ?
                  AND Dryer_No = ?
                  AND Ct_No = ?
                  AND Item_Key = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?";
    $findStmt = sqlsrv_query($conn, $findSql, [$tanggal, $dryerNo, $ctNo, $row['item_key'], $row['jam']]);
    if ($findStmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal mengecek data existing.']);
    }
    $existing = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
    if ($findStmt) sqlsrv_free_stmt($findStmt);

    if ($existing && !empty($existing['Id'])) {
        if ($editExisting) {
            // Cek apakah nilai berubah
            if (dryer_weaving_values_equal($row['nilai'], $existing['Nilai'] ?? '')) {
                // Nilai tidak berubah, lewati agar shift & petugas tidak terganggu
                $skipped++;
                continue;
            }

            // PERTAHANKAN Shift asli jika sudah ada, JANGAN ditimpa dengan default shift dari modal
            $existingShift = trim((string)($existing['ShiftName'] ?? ''));
            $targetShift = ($existingShift !== '') ? $existingShift : ($shift !== '' ? $shift : null);

            // PERTAHANKAN Petugas asli jika sudah ada, JANGAN ditimpa dengan default petugas dari modal
            $existingPetugas = trim((string)($existing['Petugas'] ?? ''));
            $targetPetugas = ($existingPetugas !== '') ? $existingPetugas : ($petugas !== '' ? $petugas : null);

            $updateSql = "UPDATE dbo.dryer_weaving
                          SET Nilai = ?, Petugas = ?, [Shift] = ?, Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE()
                          WHERE Id = ?";
            $updateStmt = sqlsrv_query($conn, $updateSql, [
                $row['nilai'],
                $targetPetugas,
                $targetShift,
                $keterangan !== '' ? $keterangan : ($existing['Keterangan'] ?? null),
                $userName,
                (int)$existing['Id']
            ]);
            if ($updateStmt === false) {
                sqlsrv_rollback($conn);
                json_response(['success' => false, 'message' => 'Gagal mengupdate data Dryer Weaving.']);
            }
            if ($updateStmt) sqlsrv_free_stmt($updateStmt);
            $updated++;
            continue;
        }
        $skipped++;
        continue;
    }

    $sql = "INSERT INTO dbo.dryer_weaving
            (Tanggal, Dryer_No, Ct_No, Jam, Kategori, Item_Key, Item_Check, Standar,
             Nilai, Petugas, Shift, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $params = [
        $tanggal, $dryerNo, $ctNo, $row['jam'], $row['kategori'], $row['item_key'],
        $row['item_check'], $row['standar'], $row['nilai'],
        $petugas !== '' ? $petugas : null,
        $shift !== '' ? $shift : null,
        $keterangan !== '' ? $keterangan : null,
        $userName
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data Dryer Weaving.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    $saved++;
}

// Sinkronkan keterangan ke seluruh baris tanggal, dryer_no, ct_no tersebut jika diisi
if ($keterangan !== '') {
    sqlsrv_query($conn, "UPDATE dbo.dryer_weaving SET Keterangan = ? WHERE CAST(Tanggal AS DATE) = ? AND Dryer_No = ? AND Ct_No = ?", [$keterangan, $tanggal, $dryerNo, $ctNo]);
}

sqlsrv_commit($conn);

$message = $saved . ' data berhasil disimpan.';
if ($updated > 0) $message .= ' ' . $updated . ' data existing berhasil diupdate.';
if ($skipped > 0) $message .= ' ' . $skipped . ' data dilewati karena sudah ada.';
json_response(['success' => true, 'message' => $message]);
