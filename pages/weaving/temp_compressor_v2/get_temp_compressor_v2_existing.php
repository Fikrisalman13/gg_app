<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.', 'data' => []]);
weaving_require($conn, 'CanAdd', true);

$tanggal = trim($_GET['tanggal'] ?? '');
$weaving = intval($_GET['weaving'] ?? 0);
$compressorNo = intval($_GET['compressor_no'] ?? 0);

if ($tanggal === '' || !in_array($weaving, [1, 2], true) || !in_array($compressorNo, [1, 2, 3], true)) {
    json_response(['success' => false, 'message' => 'Parameter tanggal, weaving, atau no compressor tidak valid.', 'data' => [], 'keterangan' => '']);
}

if (!temp_compressor_v2_table_exists($conn)) {
    json_response(['success' => true, 'data' => [], 'keterangan' => '']);
}

$sql = "SELECT Id, Tanggal, Weaving, Compressor_No, Jam,
               PressureBar_P1, PressureBar_P2,
               Temperature_T1, Temperature_T2, Temperature_T3,
               Dryer_C, ArusListrik_A,
               AirCooling_PressIn, AirCooling_PressOut,
               AirCooling_TempIn, AirCooling_TempOut,
               Pelaksana, Keterangan
        FROM dbo.temp_compressor_v2
        WHERE CAST(Tanggal AS DATE) = ?
          AND Weaving = ?
          AND Compressor_No = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $weaving, $compressorNo]);
if ($stmt === false) json_response(['success' => false, 'message' => 'Gagal mengambil data existing.', 'data' => [], 'keterangan' => '']);

$data = [];
$latestKeterangan = '';
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $jam = temp_compressor_v2_fmt_time($row['Jam'] ?? null);
    $data[$jam] = [
        'id'          => $row['Id'] ?? 0,
        'jam'         => $jam,
        'pressure_p1' => temp_compressor_v2_fmt_num($row['PressureBar_P1'] ?? null, 1),
        'pressure_p2' => temp_compressor_v2_fmt_num($row['PressureBar_P2'] ?? null, 1),
        'temp_t1'     => temp_compressor_v2_fmt_num($row['Temperature_T1'] ?? null, 1),
        'temp_t2'     => temp_compressor_v2_fmt_num($row['Temperature_T2'] ?? null, 1),
        'temp_t3'     => temp_compressor_v2_fmt_num($row['Temperature_T3'] ?? null, 1),
        'dryer_c'     => temp_compressor_v2_fmt_num($row['Dryer_C'] ?? null, 0),
        'arus_a'      => temp_compressor_v2_fmt_num($row['ArusListrik_A'] ?? null, 0),
        'press_in'    => temp_compressor_v2_fmt_num($row['AirCooling_PressIn'] ?? null, 1),
        'press_out'   => temp_compressor_v2_fmt_num($row['AirCooling_PressOut'] ?? null, 1),
        'temp_in'     => temp_compressor_v2_fmt_num($row['AirCooling_TempIn'] ?? null, 0),
        'temp_out'    => temp_compressor_v2_fmt_num($row['AirCooling_TempOut'] ?? null, 0),
        'pelaksana'   => $row['Pelaksana'] ?? '',
        'keterangan'  => $row['Keterangan'] ?? '',
    ];
    if (!empty($row['Keterangan'])) {
        $latestKeterangan = trim((string)$row['Keterangan']);
    }
}
if ($stmt) sqlsrv_free_stmt($stmt);

if ($latestKeterangan === '') {
    $latestKeterangan = temp_compressor_v2_keterangan_for_sheet_query($conn, $tanggal, $weaving, $compressorNo);
}

json_response(['success' => true, 'data' => $data, 'keterangan' => $latestKeterangan]);
