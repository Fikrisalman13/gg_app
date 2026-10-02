<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
header('Content-Type: application/json');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
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

function format_date_value($value)
{
    if ($value instanceof DateTime) {
        return $value->format('Y-m-d');
    }
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : $value;
    }
    return '';
}

function format_time_value($value)
{
    if ($value instanceof DateTime) {
        return $value->format('H:i');
    }
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : substr($value, 0, 5);
    }
    return '';
}

if (!isset($_SESSION['UserName'])) {
    json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
}
weaving_require($conn, 'CanAdd', true);

$mesin = trim($_GET['mesin'] ?? '');
$tanggal = trim($_GET['tanggal'] ?? '');
$allowedMesin = ['AC WEAVING 1', 'AC WEAVING 2'];

if (!in_array($mesin, $allowedMesin, true) || $tanggal === '') {
    json_response(['success' => false, 'message' => 'Parameter tidak valid.', 'data' => []]);
}

if (!table_exists($conn)) {
    json_response(['success' => true, 'data' => []]);
}

$endDate = date('Y-m-d', strtotime($tanggal . ' +1 day'));
$sql = "SELECT Id, Mesin, Tanggal, Jam, Aktual_Check, Pb1_Dew_Point, Humidity, Amper,
            Differential_Best_Air, Petugas, Shift, Keterangan, CreatBy
        FROM dbo.ac_weaving
        WHERE Mesin = ?
          AND (
              CAST(Tanggal AS DATE) = ?
              OR (CAST(Tanggal AS DATE) = ? AND CAST(Jam AS TIME) <= '05:30:00')
          )";
$stmt = sqlsrv_query($conn, $sql, [$mesin, $tanggal, $endDate]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal mengambil data existing.', 'data' => []]);
}

$data = [];
$existingKeterangan = '';
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($existingKeterangan === '' && !empty($row['Keterangan'])) {
        $existingKeterangan = trim((string)$row['Keterangan']);
    }
    $rowDate = format_date_value($row['Tanggal'] ?? null);
    $rowTime = format_time_value($row['Jam'] ?? null);
    $key = $rowDate . '|' . $rowTime;
    $item = [
        'id' => $row['Id'] ?? 0,
        'tanggal' => $rowDate,
        'jam' => $rowTime,
        'aktual_check' => format_time_value($row['Aktual_Check'] ?? null),
        'dew_point' => $row['Pb1_Dew_Point'] ?? null,
        'humidity' => $row['Humidity'] ?? null,
        'amper' => $row['Amper'] ?? null,
        'differential' => $row['Differential_Best_Air'] ?? null,
        'petugas' => $row['Petugas'] ?? '',
        'shift' => $row['Shift'] ?? '',
    ];
    $data[$key] = $item;

    // Backward compatibility: if data was previously saved under the next day for early morning hours,
    // also map to the requested date key so it populates into the form
    if ($rowDate === $endDate && strtotime($rowTime) <= strtotime('05:30')) {
        $legacyKey = $tanggal . '|' . $rowTime;
        if (!isset($data[$legacyKey])) {
            $data[$legacyKey] = $item;
        }
    }
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['success' => true, 'data' => $data, 'keterangan' => $existingKeterangan]);
