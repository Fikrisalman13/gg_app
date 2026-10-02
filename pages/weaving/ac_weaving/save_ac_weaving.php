<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

function normalize_decimal($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    if (strpos($value, '.') !== false) {
        $v = str_replace(',', '', $value);
        if (is_numeric($v)) return $v;
    }

    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

function table_exists($conn)
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo.ac_weaving', 'U') AS table_id");
    if ($stmt === false) {
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return !empty($row['table_id']);
}

function merge_petugas_names($existing, $new)
{
    $names = [];
    foreach (explode(',', (string)$existing) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    foreach (explode(',', (string)$new) as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($names[$key])) $names[$key] = $name;
    }
    return implode(', ', array_values($names));
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

if (!table_exists($conn)) {
    json_response([
        'success' => false,
        'message' => 'Tabel dbo.ac_weaving belum tersedia. Jalankan migration create_table_ac_weaving.sql terlebih dahulu.'
    ]);
}

$allowedMesin = ['AC WEAVING 1', 'AC WEAVING 2'];
$allowedShifts = ['NON SHIFT', 'PAGI', 'SIANG', 'MALAM'];

$mesin = trim($_POST['mesin'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$petugasDefault = trim($_POST['petugas_default'] ?? '');
$shiftDefault = trim($_POST['shift_default'] ?? '');

$rawRows = $_POST['rows'] ?? [];
if (!is_array($rawRows) || count($rawRows) === 0) {
    $rawRows = [[
        'tanggal' => $_POST['tanggal'] ?? '',
        'jam' => $_POST['jam'] ?? '',
        'aktual_check' => $_POST['aktual_check'] ?? '',
        'dew_point' => $_POST['dew_point'] ?? '',
        'humidity' => $_POST['humidity'] ?? '',
        'amper' => $_POST['amper'] ?? '',
        'differential' => $_POST['differential'] ?? '',
    ]];
}

// Cek apakah ada baris baru yang akan diinsert (belum ada di database)
$hasNewRowsToInsert = false;
foreach ($rawRows as $r) {
    if (!is_array($r) || !row_has_measurement($r)) continue;
    $checkDate = trim((string)($r['tanggal'] ?? ''));
    $checkJam = trim((string)($r['jam'] ?? ''));
    if ($checkDate !== '' && $checkJam !== '') {
        $chkSql = "SELECT TOP 1 Id FROM dbo.ac_weaving WHERE Mesin = ? AND CAST(Tanggal AS DATE) = ? AND CONVERT(VARCHAR(5), Jam, 108) = ?";
        $chkStmt = sqlsrv_query($conn, $chkSql, [$mesin, $checkDate, $checkJam]);
        $chkRow = $chkStmt ? sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($chkStmt) sqlsrv_free_stmt($chkStmt);
        if (!$chkRow) {
            $hasNewRowsToInsert = true;
            break;
        }
    }
}

if ($hasNewRowsToInsert) {
    if ($petugasDefault === '' || !in_array($shiftDefault, $allowedShifts, true)) {
        json_response(['success' => false, 'message' => 'Petugas wajib diisi dan shift harus sesuai pilihan untuk data baru yang ditambahkan.']);
    }
} else {
    if ($shiftDefault !== '' && !in_array($shiftDefault, $allowedShifts, true)) {
        $shiftDefault = 'NON SHIFT';
    }
}

$userName = $_SESSION['UserName'];

function row_has_measurement($row)
{
    foreach (['dew_point', 'humidity', 'amper', 'differential'] as $field) {
        if (trim((string)($row[$field] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function normalize_row($row, $petugasDefault, $shiftDefault)
{
    $tanggal = trim((string)($row['tanggal'] ?? ''));
    $jam = trim((string)($row['jam'] ?? ''));
    $aktualCheck = trim((string)($row['aktual_check'] ?? ''));
    $dewPoint = normalize_decimal($row['dew_point'] ?? '');
    $humidity = normalize_decimal($row['humidity'] ?? '');
    $amper = normalize_decimal($row['amper'] ?? '');
    $differential = normalize_decimal($row['differential'] ?? '');

    if ($tanggal === '' || $jam === '') {
        return [false, 'Tanggal dan jam wajib diisi untuk setiap baris yang diisi.', null];
    }

    if ($aktualCheck === '') {
        $aktualCheck = date('H:i');
    }

    return [true, '', [
        'tanggal' => $tanggal,
        'jam' => $jam,
        'aktual_check' => $aktualCheck,
        'dew_point' => $dewPoint === null ? null : round((float)$dewPoint, 2),
        'humidity' => $humidity === null ? null : round((float)$humidity, 2),
        'amper' => $amper === null ? null : round((float)$amper, 2),
        'differential' => $differential === null ? null : round((float)$differential, 2),
        'petugas' => $petugasDefault,
        'shift' => $shiftDefault,
    ]];
}

function ac_values_equal($val1, $val2)
{
    $empty1 = ($val1 === null || trim((string)$val1) === '');
    $empty2 = ($val2 === null || trim((string)$val2) === '');
    if ($empty1 && $empty2) return true;
    if ($empty1 || $empty2) return false;
    return abs((float)$val1 - (float)$val2) < 0.0001;
}

function save_row($conn, $mesin, $row, $keterangan, $userName, $editExisting = false)
{
    $fieldColumns = [
        'dew_point' => 'Pb1_Dew_Point',
        'humidity' => 'Humidity',
        'amper' => 'Amper',
        'differential' => 'Differential_Best_Air',
    ];

    $findSql = "SELECT TOP 1 Id, Pb1_Dew_Point, Humidity, Amper, Differential_Best_Air, Petugas, [Shift], Aktual_Check
                FROM dbo.ac_weaving
                WHERE Mesin = ?
                  AND CAST(Tanggal AS DATE) = ?
                  AND CONVERT(VARCHAR(5), Jam, 108) = ?
                ORDER BY Id DESC";
    $findStmt = sqlsrv_query($conn, $findSql, [$mesin, $row['tanggal'], $row['jam']]);
    if ($findStmt === false) {
        return false;
    }
    $existing = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC);
    if ($findStmt) sqlsrv_free_stmt($findStmt);

    if ($existing && !empty($existing['Id'])) {
        $setParts = [];
        $updateParams = [];
        $hasMeasurementChange = false;

        foreach ($fieldColumns as $field => $column) {
            $existingValue = $existing[$column] ?? null;
            $newVal = $row[$field];

            if ($newVal !== null) {
                $isOldEmpty = ($existingValue === null || trim((string)$existingValue) === '');
                if ($isOldEmpty) {
                    $setParts[] = $column . ' = ?';
                    $updateParams[] = $newVal;
                    $hasMeasurementChange = true;
                } elseif ($editExisting && !ac_values_equal($newVal, $existingValue)) {
                    $setParts[] = $column . ' = ?';
                    $updateParams[] = $newVal;
                    $hasMeasurementChange = true;
                }
            }
        }

        // Jika tidak ada pengukuran yang baru diisi atau berubah nilainya, lewati baris ini (jangan update apapun)
        if (!$hasMeasurementChange) {
            return 'skipped';
        }

        // Untuk baris existing yang diubah pengukurannya:
        // PERTAHANKAN Petugas asli jika sudah ada, JANGAN ditimpa dengan default dari modal
        $existingPetugas = trim((string)($existing['Petugas'] ?? ''));
        if ($existingPetugas === '' && !empty($row['petugas'])) {
            $setParts[] = 'Petugas = ?';
            $updateParams[] = $row['petugas'];
        }

        // PERTAHANKAN Shift asli jika sudah ada, JANGAN ditimpa dengan default dari modal
        $existingShift = trim((string)($existing['Shift'] ?? ''));
        if ($existingShift === '' && !empty($row['shift'])) {
            $setParts[] = '[Shift] = ?';
            $updateParams[] = $row['shift'];
        }

        // PERTAHANKAN Aktual_Check asli jika sudah ada
        $existingAktual = $existing['Aktual_Check'] ?? null;
        $isAktualEmpty = ($existingAktual === null || (is_string($existingAktual) && trim($existingAktual) === ''));
        if ($isAktualEmpty && !empty($row['aktual_check'])) {
            $setParts[] = 'Aktual_Check = ?';
            $updateParams[] = $row['aktual_check'];
        }

        if ($keterangan !== '') {
            $setParts[] = 'Keterangan = ?';
            $updateParams[] = $keterangan;
        }
        $setParts[] = 'UpdateBy = ?';
        $updateParams[] = $userName;
        $setParts[] = 'UpdateAt = GETDATE()';
        $updateParams[] = (int)$existing['Id'];

        $updateSql = "UPDATE dbo.ac_weaving SET " . implode(', ', $setParts) . " WHERE Id = ?";
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        if ($updateStmt === false) {
            return false;
        }
        if ($updateStmt) sqlsrv_free_stmt($updateStmt);
        return 'updated';
    }

    $sql = "INSERT INTO dbo.ac_weaving
            (Mesin, Tanggal, Jam, Aktual_Check, Pb1_Dew_Point, Humidity, Amper,
             Differential_Best_Air, Petugas, Shift, Keterangan, CreatBy, CreatAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $params = [
        $mesin, $row['tanggal'], $row['jam'], $row['aktual_check'], $row['dew_point'], $row['humidity'],
        $row['amper'], $row['differential'], $row['petugas'], $row['shift'], $keterangan, $userName
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return false;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return true;
}

$rowsToSave = [];
foreach ($rawRows as $row) {
    if (!is_array($row) || !row_has_measurement($row)) {
        continue;
    }

    [$valid, $message, $normalizedRow] = normalize_row($row, $petugasDefault, $shiftDefault);
    if (!$valid) {
        json_response(['success' => false, 'message' => $message]);
    }
    $rowsToSave[] = $normalizedRow;
}

if (count($rowsToSave) === 0) {
    // Jika tidak ada baris pengukuran baru/diubah, cek apakah user memperbarui keterangan pada data yang sudah ada
    $targetDate = trim((string)($_POST['tanggal'] ?? ''));
    if ($targetDate !== '') {
        $checkExistSql = "SELECT COUNT(*) AS total FROM dbo.ac_weaving WHERE Mesin = ? AND CAST(Tanggal AS DATE) = ?";
        $checkExistStmt = sqlsrv_query($conn, $checkExistSql, [$mesin, $targetDate]);
        $existRow = $checkExistStmt ? sqlsrv_fetch_array($checkExistStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($checkExistStmt) sqlsrv_free_stmt($checkExistStmt);

        if (!empty($existRow['total']) && (int)$existRow['total'] > 0) {
            $updKetSql = "UPDATE dbo.ac_weaving SET Keterangan = ?, UpdateBy = ?, UpdateAt = GETDATE() WHERE Mesin = ? AND CAST(Tanggal AS DATE) = ?";
            $updKetStmt = sqlsrv_query($conn, $updKetSql, [$keterangan !== '' ? $keterangan : null, $userName, $mesin, $targetDate]);
            if ($updKetStmt === false) {
                json_response(['success' => false, 'message' => 'Gagal memperbarui keterangan.']);
            }
            if ($updKetStmt) sqlsrv_free_stmt($updKetStmt);
            json_response(['success' => true, 'message' => 'Keterangan berhasil disimpan.']);
        }
    }
    json_response(['success' => false, 'message' => 'Isi minimal satu baris data pengecekan.']);
}

if (!sqlsrv_begin_transaction($conn)) {
    json_response(['success' => false, 'message' => 'Gagal memulai transaksi simpan data.']);
}

$saved = 0;
$updated = 0;
$skipped = 0;
foreach ($rowsToSave as $row) {
    $result = save_row($conn, $mesin, $row, $keterangan, $userName, $editExisting);
    if ($result === false) {
        sqlsrv_rollback($conn);
        json_response(['success' => false, 'message' => 'Gagal menyimpan salah satu baris data AC Weaving.']);
    }
    if ($result === 'skipped') {
        $skipped++;
    } elseif ($result === 'updated') {
        $updated++;
    } else {
        $saved++;
    }
}

// Sinkronkan keterangan ke seluruh baris tanggal & mesin tersebut
$targetDate = $rowsToSave[0]['tanggal'] ?? trim((string)($_POST['tanggal'] ?? ''));
if ($targetDate !== '') {
    sqlsrv_query($conn, "UPDATE dbo.ac_weaving SET Keterangan = ? WHERE Mesin = ? AND CAST(Tanggal AS DATE) = ?", [$keterangan !== '' ? $keterangan : null, $mesin, $targetDate]);
}

sqlsrv_commit($conn);

$message = $saved . ' baris data berhasil disimpan.';
if ($updated > 0) {
    $message .= ' ' . $updated . ' baris existing berhasil ' . ($editExisting ? 'diupdate.' : 'dilengkapi.');
}
if ($skipped > 0) {
    $message .= ' ' . $skipped . ' baris dilewati karena sudah ada.';
}
json_response(['success' => true, 'message' => $message]);
