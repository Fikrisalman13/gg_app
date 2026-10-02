<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ac_weaving_helper.php');

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

if (!ac_weaving_table_exists($conn)) {
    json_response(['success' => false, 'message' => 'Tabel dbo.ac_weaving belum tersedia.']);
}

$id = $_POST['id'] ?? '';
$tanggalParam = trim($_POST['tanggal'] ?? '');
$mesinParam = trim($_POST['mesin'] ?? '');

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = ac_weaving_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $mesinParam !== '') {
    $sheet = ac_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $mesinParam);
}
if (!$sheet) {
    json_response(['success' => false, 'message' => 'Data tidak ditemukan.']);
}

$tanggal = ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$mesin = $sheet['Mesin'] ?? '';
$petugas = trim($_POST['petugas_default'] ?? '');
$shift = trim($_POST['shift_default'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$rows = $_POST['rows'] ?? [];
$allowedHours = ac_weaving_hours();

if ($shift !== '' && !in_array($shift, ac_weaving_shift_options(), true)) {
    $shift = '';
}
if (!is_array($rows)) {
    json_response(['success' => false, 'message' => 'Format data input tidak valid.']);
}

$existingCells = ac_weaving_get_sheet_cells($conn, $tanggal, $mesin);

$rowsToSave = [];
foreach ($allowedHours as $hour) {
    $rowDew = $rows[$hour]['dew_point'] ?? $rows['dew_point'][$hour] ?? '';
    $rowHum = $rows[$hour]['humidity'] ?? $rows['humidity'][$hour] ?? '';
    $rowAmp = $rows[$hour]['amper'] ?? $rows['amper'][$hour] ?? '';
    $rowDiff = $rows[$hour]['differential'] ?? $rows['differential'][$hour] ?? '';
    $rowPetugas = trim($rows[$hour]['petugas'] ?? '');
    $rowShift = trim($rows[$hour]['shift'] ?? '');

    $dewPoint = ac_weaving_normalize_decimal($rowDew);
    $humidity = ac_weaving_normalize_decimal($rowHum);
    $amper = ac_weaving_normalize_decimal($rowAmp);
    $differential = ac_weaving_normalize_decimal($rowDiff);

    if ($dewPoint === null && $humidity === null && $amper === null && $differential === null) {
        continue;
    }

    $existingCell = $existingCells[$hour] ?? [];
    $existingPetugas = trim($existingCell['petugas'] ?? '');
    $existingShift = trim($existingCell['shift'] ?? '');

    if ($rowPetugas !== '') {
        $finalPetugas = $rowPetugas;
    } elseif ($existingPetugas !== '') {
        $finalPetugas = $existingPetugas;
    } else {
        $finalPetugas = $petugas;
    }

    if ($rowShift !== '' && in_array($rowShift, ac_weaving_shift_options(), true)) {
        $finalShift = $rowShift;
    } elseif ($existingShift !== '' && in_array($existingShift, ac_weaving_shift_options(), true)) {
        $finalShift = $existingShift;
    } else {
        $finalShift = $shift;
    }

    $rowsToSave[] = [
        'jam' => $hour,
        'aktual_check' => !empty($existingCell['aktual_check']) ? $existingCell['aktual_check'] : $hour,
        'dew_point' => $dewPoint !== null ? round((float)$dewPoint, 2) : null,
        'humidity' => $humidity !== null ? round((float)$humidity, 2) : null,
        'amper' => $amper !== null ? round((float)$amper, 2) : null,
        'differential' => $differential !== null ? round((float)$differential, 2) : null,
        'petugas' => $finalPetugas !== '' ? $finalPetugas : null,
        'shift' => $finalShift !== '' ? $finalShift : null,
        'creat_by' => !empty($existingCell['creat_by']) ? $existingCell['creat_by'] : ($sheet['CreatBy'] ?? ($_SESSION['UserName'] ?? '')),
        'creat_at' => !empty($existingCell['creat_at']) ? $existingCell['creat_at'] : ($sheet['CreatAt'] ?? null),
    ];
}

if (count($rowsToSave) === 0) {
    json_response(['success' => false, 'message' => 'Isi minimal satu data pengecekan.']);
}

if (!sqlsrv_begin_transaction($conn)) {
    json_response(['success' => false, 'message' => 'Gagal memulai transaksi update data.']);
}

$deleteSql = "DELETE FROM dbo.ac_weaving
              WHERE CAST(Tanggal AS DATE) = ?
                AND Mesin = ?";
$deleteStmt = sqlsrv_query($conn, $deleteSql, [$tanggal, $mesin]);
if ($deleteStmt === false) {
    sqlsrv_rollback($conn);
    json_response(['success' => false, 'message' => 'Gagal menghapus data lama.']);
}
if ($deleteStmt) sqlsrv_free_stmt($deleteStmt);

$userName = $_SESSION['UserName'];

foreach ($rowsToSave as $row) {
    $creatAtParam = ($row['creat_at'] instanceof DateTimeInterface)
        ? $row['creat_at']->format('Y-m-d H:i:s')
        : (!empty($row['creat_at']) ? (string)$row['creat_at'] : null);

    $sql = "INSERT INTO dbo.ac_weaving
            (Mesin, Tanggal, Jam, Aktual_Check, Pb1_Dew_Point, Humidity, Amper,
             Differential_Best_Air, Petugas, Shift, Keterangan, CreatBy, CreatAt, UpdateBy, UpdateAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, GETDATE()), ?, GETDATE())";
    $params = [
        $mesin,
        $tanggal,
        $row['jam'],
        $row['aktual_check'],
        $row['dew_point'],
        $row['humidity'],
        $row['amper'],
        $row['differential'],
        $row['petugas'],
        $row['shift'],
        $keterangan !== '' ? $keterangan : null,
        $row['creat_by'],
        $creatAtParam,
        $userName
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan perubahan data.']);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
}

sqlsrv_commit($conn);
json_response(['success' => true, 'message' => 'Data AC Weaving berhasil diperbarui.']);
