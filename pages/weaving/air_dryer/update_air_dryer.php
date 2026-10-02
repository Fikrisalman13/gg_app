<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanEdit', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode request tidak valid.']);
}

if (!air_dryer_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.air_dryer belum tersedia.']);
}

$id = $_POST['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    json_response(['success' => false, 'message' => 'ID tidak valid.']);
}

$sheet = air_dryer_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$petugas = trim($_POST['petugas_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$allowedHours = air_dryer_hours();

if (!is_array($rows)) {
    json_response(['success' => false, 'message' => 'Format data input tidak valid.']);
}

$existingCells = air_dryer_get_sheet_cells($conn, $tanggal);

$rowsToSave = [];
foreach ($allowedHours as $hour) {
    $rowAd1TempIn = $rows[$hour]['ad1_temp_in'] ?? '';
    $rowAd1TempOut = $rows[$hour]['ad1_temp_out'] ?? '';
    $rowAd1PressIn = $rows[$hour]['ad1_press_in'] ?? '';
    $rowAd1PressOut = $rows[$hour]['ad1_press_out'] ?? '';

    $rowAd2TempIn = $rows[$hour]['ad2_temp_in'] ?? '';
    $rowAd2TempOut = $rows[$hour]['ad2_temp_out'] ?? '';
    $rowAd2PressIn = $rows[$hour]['ad2_press_in'] ?? '';
    $rowAd2PressOut = $rows[$hour]['ad2_press_out'] ?? '';

    $rowPetugas = trim($rows[$hour]['petugas'] ?? '');

    $ad1TempIn = air_dryer_normalize_decimal($rowAd1TempIn);
    $ad1TempOut = air_dryer_normalize_decimal($rowAd1TempOut);
    $ad1PressIn = air_dryer_normalize_decimal($rowAd1PressIn);
    $ad1PressOut = air_dryer_normalize_decimal($rowAd1PressOut);

    $ad2TempIn = air_dryer_normalize_decimal($rowAd2TempIn);
    $ad2TempOut = air_dryer_normalize_decimal($rowAd2TempOut);
    $ad2PressIn = air_dryer_normalize_decimal($rowAd2PressIn);
    $ad2PressOut = air_dryer_normalize_decimal($rowAd2PressOut);

    if (
        $ad1TempIn === null && $ad1TempOut === null && $ad1PressIn === null && $ad1PressOut === null &&
        $ad2TempIn === null && $ad2TempOut === null && $ad2PressIn === null && $ad2PressOut === null
    ) {
        continue;
    }

    $existingCell = $existingCells[$hour] ?? [];
    if ($rowPetugas !== '') {
        $finalPetugas = $rowPetugas;
    } elseif (!empty($existingCell['petugas'])) {
        $finalPetugas = $existingCell['petugas'];
    } else {
        $finalPetugas = $petugas;
    }

    $creatBy = !empty($existingCell['creat_by']) ? $existingCell['creat_by'] : ($sheet['CreatBy'] ?? $_SESSION['UserName']);
    $creatAt = !empty($existingCell['creat_at']) ? $existingCell['creat_at'] : null;

    $rowsToSave[] = [
        'jam' => $hour,
        'ad1_temp_in' => $ad1TempIn !== null ? round((float)$ad1TempIn, 2) : null,
        'ad1_temp_out' => $ad1TempOut !== null ? round((float)$ad1TempOut, 2) : null,
        'ad1_press_in' => $ad1PressIn !== null ? round((float)$ad1PressIn, 2) : null,
        'ad1_press_out' => $ad1PressOut !== null ? round((float)$ad1PressOut, 2) : null,
        'ad2_temp_in' => $ad2TempIn !== null ? round((float)$ad2TempIn, 2) : null,
        'ad2_temp_out' => $ad2TempOut !== null ? round((float)$ad2TempOut, 2) : null,
        'ad2_press_in' => $ad2PressIn !== null ? round((float)$ad2PressIn, 2) : null,
        'ad2_press_out' => $ad2PressOut !== null ? round((float)$ad2PressOut, 2) : null,
        'petugas' => $finalPetugas !== '' ? $finalPetugas : null,
        'creat_by' => $creatBy,
        'creat_at' => $creatAt,
    ];
}

if (count($rowsToSave) === 0) {
    json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
}

if (!sqlsrv_begin_transaction($conn)) {
    json_response(['success' => false, 'message' => 'Gagal memulai transaksi update data.']);
}

$deleteSql = "DELETE FROM dbo.air_dryer WHERE CAST(Tanggal AS DATE) = ?";
$deleteStmt = sqlsrv_query($conn, $deleteSql, [$tanggal]);
if ($deleteStmt === false) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menghapus data lama.']);
}
if ($deleteStmt) sqlsrv_free_stmt($deleteStmt);

$insertSql = "INSERT INTO dbo.air_dryer (
                Tanggal, Jam_Pengecekan,
                AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar,
                Petugas, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt
            ) VALUES (
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, COALESCE(?, GETDATE()), ?, GETDATE()
            )";

foreach ($rowsToSave as $r) {
    $creatAtParam = ($r['creat_at'] instanceof DateTimeInterface)
        ? $r['creat_at']->format('Y-m-d H:i:s')
        : (!empty($r['creat_at']) ? (string)$r['creat_at'] : null);

    $insertParams = [
        $tanggal,
        $r['jam'] . ':00',
        $r['ad1_temp_in'],
        $r['ad1_temp_out'],
        $r['ad1_press_in'],
        $r['ad1_press_out'],
        $r['ad2_temp_in'],
        $r['ad2_temp_out'],
        $r['ad2_press_in'],
        $r['ad2_press_out'],
        $r['petugas'],
        $keterangan !== '' ? $keterangan : null,
        $r['creat_by'],
        $creatAtParam,
        $_SESSION['UserName'],
    ];

    $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    if ($insertStmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan data baru.']);
    }
    if ($insertStmt) sqlsrv_free_stmt($insertStmt);
}

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menyelesaikan transaksi data.']);
}

json_response([
    'success' => true,
    'message' => 'Data Air Dryer berhasil diperbarui.'
]);
