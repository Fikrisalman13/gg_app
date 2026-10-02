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

if (!isset($_SESSION['UserName'])) json_response(['success' => false, 'message' => 'Silakan login terlebih dahulu.', 'data' => []]);
weaving_require($conn, 'CanAdd', true);

$tanggal = trim($_GET['tanggal'] ?? '');
$dryerNo = trim($_GET['dryer_no'] ?? '');
$ctNo = trim($_GET['ct_no'] ?? '');

if ($tanggal === '' || $dryerNo === '' || $ctNo === '') {
    json_response(['success' => false, 'message' => 'Parameter tidak valid.', 'data' => []]);
}

if (!dryer_weaving_table_exists($conn)) {
    json_response(['success' => true, 'data' => []]);
}

$sql = "SELECT Id, Item_Key, Jam, Nilai, Petugas, [Shift] AS ShiftName, Keterangan
        FROM dbo.dryer_weaving
        WHERE CAST(Tanggal AS DATE) = ?
          AND Dryer_No = ?
          AND Ct_No = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $dryerNo, $ctNo]);
if ($stmt === false) {
    json_response(['success' => false, 'message' => 'Gagal mengambil data existing.', 'data' => [], 'keterangan' => '']);
}

$data = [];
$latestKeterangan = '';
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $key = ($row['Item_Key'] ?? '') . '|' . dryer_weaving_fmt_time($row['Jam'] ?? null);
    $data[$key] = [
        'id' => $row['Id'] ?? 0,
        'nilai' => $row['Nilai'] ?? '',
        'petugas' => $row['Petugas'] ?? '',
        'shift' => $row['ShiftName'] ?? '',
    ];
    if (!empty($row['Keterangan'])) {
        $latestKeterangan = $row['Keterangan'];
    }
}
if ($stmt) sqlsrv_free_stmt($stmt);

json_response(['success' => true, 'data' => $data, 'keterangan' => $latestKeterangan]);
