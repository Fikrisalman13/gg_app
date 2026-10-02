<?php
session_start();
include '../../koneksi.php';
include '../../koneksi4.php';

date_default_timezone_set('Asia/Jakarta');

// Ambil parameter dari request
$start_date = $_GET['start_date'] ?? date('Y-m-d\T00:00');
$end_date   = $_GET['end_date'] ?? date('Y-m-d\TH:i');

// Konversi ke format SQL Server
$start_date_sql = str_replace("T", " ", $start_date) . ":00";
$end_date_sql   = str_replace("T", " ", $end_date) . ":00";

// Query data terbaru mesin Paddry 3
$sql = "
    SELECT
        b.Machine AS Mesin,
        a.LogTimeStamp AS Waktu,
        ROUND(a.Value01/10, 0) AS [Speed],
        a.Value27 AS [TD1],
        a.Value06 AS [EXT1],
        a.Value04 AS [EXT2],
        a.Value13 AS [NTA1],
        a.Value20 AS [NTA2],
        a.Value14 AS [NTB1],
        a.Value21 AS [NTB2],
        a.Value18 AS [HCT1],
        a.Value19 AS [HCT2]
    FROM dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    WHERE a.LogType_ID = '1311'
      AND a.LogTimeStamp BETWEEN ? AND ?
    ORDER BY a.LogTimeStamp ASC
";

$stmt = sqlsrv_prepare($conn4, $sql, [$start_date_sql, $end_date_sql]);
$data = [];
if ($stmt) {
    sqlsrv_execute($stmt);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'Mesin' => $row['Mesin'],
            'Waktu' => $row['Waktu'] instanceof DateTime ? $row['Waktu']->format('Y-m-d H:i:s') : '',
            'Speed' => $row['Speed'],
            'TD1' => $row['TD1'],
            'EXT1' => $row['EXT1'], 'EXT2' => $row['EXT2'],
            'NTA1' => $row['NTA1'], 'NTA2' => $row['NTA2'],
            'NTB1' => $row['NTB1'], 'NTB2' => $row['NTB2'],
            'HCT1' => $row['HCT1'], 'HCT2' => $row['HCT2']
        ];
    }
}

// Query status mesin
$status_sql = "
    SELECT 
        CASE 
            WHEN machinestatus.Machine = 'MON1' THEN 'Stenter'
            WHEN machinestatus.Machine = 'MON2' THEN 'Paddry 4'
            WHEN machinestatus.Machine = 'MON3' THEN 'Paddry 3'
            WHEN machinestatus.Machine = 'MON4' THEN 'Paddry 5'
            WHEN machinestatus.Machine = 'MON5' THEN 'Paddry 6'
            ELSE machinestatus.Machine
        END AS MachineName,
        CASE 
            WHEN machinestatus.OnlineState = 1 THEN 'Online'
            WHEN machinestatus.OnlineState = 0 THEN 'Offline'
            ELSE 'Unknown'
        END AS Status,
        machinestatus.[Timestamp]
    FROM 
        dbo.machinestatus
    WHERE
        machinestatus.Machine = 'MON3'
    ORDER BY
        machinestatus.[Timestamp] DESC
";

$status_stmt = sqlsrv_query($conn, $status_sql);
$machine_status = 'Unknown';
$last_status_update = '';

if ($status_stmt !== false) {
    if ($row = sqlsrv_fetch_array($status_stmt, SQLSRV_FETCH_ASSOC)) {
        $machine_status = $row['Status'];
        if ($row['Timestamp'] instanceof DateTime) {
            $last_status_update = $row['Timestamp']->format('Y-m-d H:i:s');
        } else {
            $last_status_update = date('Y-m-d H:i:s', strtotime($row['Timestamp']));
        }
    }
}

// Siapkan data untuk response JSON
$response = [
    'success' => true,
    'lastUpdate' => date('H:i:s'),
    'machineStatus' => $machine_status,
    'lastStatusUpdate' => $last_status_update,
    'avgSpeed' => 0,
    'avgTD1' => 0,
    'avgEXT1' => 0,
    'tableData' => [],
    'chartLabels' => [],
    'speedData' => [],
    'td1Data' => [],
    'ext1Data' => []
];

// Generate data
if (!empty($data)) {
    $response['avgSpeed'] = round(array_sum(array_column($data, 'Speed')) / max(1, count($data)), 2);
    $response['avgTD1'] = round(array_sum(array_column($data, 'TD1')) / max(1, count($data)), 2);
    $response['avgEXT1'] = round(array_sum(array_column($data, 'EXT1')) / max(1, count($data)), 2);
    
    // Data untuk tabel
    foreach ($data as $row) {
        $response['tableData'][] = [
            $row['Mesin'],
            $row['Waktu'],
            $row['Speed'],
            $row['TD1'],
            $row['EXT1'], $row['EXT2'],
            $row['NTA1'], $row['NTA2'],
            $row['NTB1'], $row['NTB2'],
            $row['HCT1'], $row['HCT2']
        ];
    }
    
    // Data untuk grafik
    $response['chartLabels'] = array_column($data, 'Waktu');
    $response['speedData'] = array_column($data, 'Speed');
    $response['td1Data'] = array_column($data, 'TD1');
    $response['ext1Data'] = array_column($data, 'EXT1');
}

// Keluarkan response sebagai JSON
header('Content-Type: application/json');
echo json_encode($response);
?>