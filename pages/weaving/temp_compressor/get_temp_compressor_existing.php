<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');

function json_response($payload)
{
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.', 'data' => []]);
weaving_require($conn, 'CanAdd', true);

$tanggal = trim($_GET['tanggal'] ?? '');
if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal tidak valid.', 'data' => [], 'keterangan' => '']);
if (!temp_compressor_table_exists($conn)) json_response(['success' => true, 'data' => [], 'keterangan' => '']);

$sql = "SELECT Id, Tanggal, Jam, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
               Amper, PressureBar_P1, PressureBar_P2,
               Temperature_T1, Temperature_T2, Temperature_T3,
               Dryer_C, TekananAir_In, TekananAir_Out,
               Petugas, Keterangan
        FROM dbo.temp_compressor
        WHERE CAST(Tanggal AS DATE) = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) json_response(['success' => false, 'message' => 'Gagal mengambil data existing.', 'data' => [], 'keterangan' => '']);

$data = [];
$latestKeterangan = '';
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $jam = temp_compressor_fmt_time($row['Jam'] ?? null);
    $data[$jam] = [
        'id'          => $row['Id'] ?? 0,
        'jam'         => $jam,
        'c1_in'       => temp_compressor_fmt_num($row['Compressor1_In_C'] ?? null, 0),
        'c1_out'      => temp_compressor_fmt_num($row['Compressor1_Out_C'] ?? null, 0),
        'c2_in'       => temp_compressor_fmt_num($row['Compressor2_In_C'] ?? null, 0),
        'c2_out'      => temp_compressor_fmt_num($row['Compressor2_Out_C'] ?? null, 0),
        'amper'       => temp_compressor_fmt_num($row['Amper'] ?? null, 0),
        'pressure_p1' => temp_compressor_fmt_num($row['PressureBar_P1'] ?? null, 1),
        'pressure_p2' => temp_compressor_fmt_num($row['PressureBar_P2'] ?? null, 1),
        'temp_t1'     => temp_compressor_fmt_num($row['Temperature_T1'] ?? null, 1),
        'temp_t2'     => temp_compressor_fmt_num($row['Temperature_T2'] ?? null, 1),
        'temp_t3'     => temp_compressor_fmt_num($row['Temperature_T3'] ?? null, 1),
        'dryer_c'     => temp_compressor_fmt_num($row['Dryer_C'] ?? null, 0),
        'tekanan_in'  => temp_compressor_fmt_num($row['TekananAir_In'] ?? null, 1),
        'tekanan_out' => temp_compressor_fmt_num($row['TekananAir_Out'] ?? null, 1),
        'petugas'     => $row['Petugas'] ?? '',
        'keterangan'  => $row['Keterangan'] ?? '',
    ];
    if (!empty($row['Keterangan'])) {
        $latestKeterangan = trim((string)$row['Keterangan']);
    }
}
if ($stmt) sqlsrv_free_stmt($stmt);

if ($latestKeterangan === '') {
    $latestKeterangan = temp_compressor_keterangan_for_sheet_query($conn, $tanggal);
}

json_response(['success' => true, 'data' => $data, 'keterangan' => $latestKeterangan]);
