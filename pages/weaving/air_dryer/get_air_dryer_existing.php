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

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.', 'data' => []]);
weaving_require($conn, 'CanAdd', true);

$tanggal = trim($_GET['tanggal'] ?? '');
if ($tanggal === '') json_response(['success' => false, 'message' => 'Tanggal tidak valid.', 'data' => [], 'keterangan' => '']);
if (!air_dryer_table_exists($conn)) json_response(['success' => true, 'data' => [], 'keterangan' => '']);

$sql = "SELECT Id, Jam_Pengecekan, AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
            AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
            AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
            AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar,
            Petugas, Keterangan
        FROM dbo.air_dryer
        WHERE CAST(Tanggal AS DATE) = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) json_response(['success' => false, 'message' => 'Gagal mengambil data existing.', 'data' => [], 'keterangan' => '']);

$data = [];
$latestKeterangan = '';
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $jam = air_dryer_fmt_time($row['Jam_Pengecekan'] ?? null);
    $data[$jam] = [
        'id' => $row['Id'] ?? 0,
        'ad1_temp_in' => air_dryer_fmt_num($row['AirDryer1_Temp_In_C'] ?? null, 1),
        'ad1_temp_out' => air_dryer_fmt_num($row['AirDryer1_Temp_Out_C'] ?? null, 1),
        'ad1_press_in' => air_dryer_fmt_num($row['AirDryer1_Tekanan_In_Bar'] ?? null, 1),
        'ad1_press_out' => air_dryer_fmt_num($row['AirDryer1_Tekanan_Out_Bar'] ?? null, 1),
        'ad2_temp_in' => air_dryer_fmt_num($row['AirDryer2_Temp_In_C'] ?? null, 1),
        'ad2_temp_out' => air_dryer_fmt_num($row['AirDryer2_Temp_Out_C'] ?? null, 1),
        'ad2_press_in' => air_dryer_fmt_num($row['AirDryer2_Tekanan_In_Bar'] ?? null, 1),
        'ad2_press_out' => air_dryer_fmt_num($row['AirDryer2_Tekanan_Out_Bar'] ?? null, 1),
        'petugas' => $row['Petugas'] ?? '',
        'keterangan' => $row['Keterangan'] ?? '',
    ];
    if (!empty($row['Keterangan'])) {
        $latestKeterangan = trim((string)$row['Keterangan']);
    }
}
if ($stmt) sqlsrv_free_stmt($stmt);

if ($latestKeterangan === '') {
    $latestKeterangan = air_dryer_keterangan_for_sheet_query($conn, $tanggal);
}

json_response(['success' => true, 'data' => $data, 'keterangan' => $latestKeterangan]);
