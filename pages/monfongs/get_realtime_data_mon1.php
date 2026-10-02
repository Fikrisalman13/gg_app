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

// Query data terbaru mesin Stenter
$sql = "
    SELECT
        b.Machine AS Mesin,
        a.LogTimeStamp AS Waktu,
        ROUND(c.Value21/10, 0) AS [Speed],
        a.Value30 AS TC1,
        a.Value31 AS TC2,
        a.Value32 AS TC3,
        a.Value33 AS TC4,
        a.Value34 AS TC5,
        a.Value35 AS TC6,
        a.Value36 AS TC7,
        a.Value37 AS TC8,
        a.Value38 AS TC9,
        a.Value27 AS TC10,
        a.Value28 AS TC11,
        a.Value29 AS TC12,
        a.Value39 AS EX1,
        a.Value40 AS EX2
    FROM dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    LEFT JOIN dbo.logvaluefloat AS c ON a.LogTimeStamp = c.LogTimeStamp AND c.LogType_ID = '1112'
    LEFT JOIN dbo.logtype AS d ON c.LogType_ID = d.ID
    WHERE a.LogType_ID = '1111'
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
            'TC1' => $row['TC1'], 'TC2' => $row['TC2'], 'TC3' => $row['TC3'], 'TC4' => $row['TC4'],
            'TC5' => $row['TC5'], 'TC6' => $row['TC6'], 'TC7' => $row['TC7'], 'TC8' => $row['TC8'],
            'TC9' => $row['TC9'], 'TC10' => $row['TC10'], 'TC11' => $row['TC11'], 'TC12' => $row['TC12'],
            'EX1' => $row['EX1'], 'EX2' => $row['EX2']
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
        machinestatus.Machine = 'MON1'
    ORDER BY
        machinestatus.[Timestamp] DESC
";

$status_stmt = sqlsrv_query($conn4, $status_sql);
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
    'avgTC1' => 0,
    'avgEX1' => 0,
    'tableData' => [],
    'chartLabels' => [],
    'speedData' => [],
    'tc1Data' => [],
    'ex1Data' => []
];

// Generate data
if (!empty($data)) {
    $response['avgSpeed'] = round(array_sum(array_column($data, 'Speed')) / max(1, count($data)), 2);
    $response['avgTC1'] = round(array_sum(array_column($data, 'TC1')) / max(1, count($data)), 2);
    $response['avgEX1'] = round(array_sum(array_column($data, 'EX1')) / max(1, count($data)), 2);
    
    // Data untuk tabel
    foreach ($data as $row) {
        $response['tableData'][] = [
            $row['Mesin'],
            $row['Waktu'],
            $row['Speed'],
            $row['TC1'], $row['TC2'], $row['TC3'], $row['TC4'],
            $row['TC5'], $row['TC6'], $row['TC7'], $row['TC8'],
            $row['TC9'], $row['TC10'], $row['TC11'], $row['TC12'],
            $row['EX1'], $row['EX2']
        ];
    }
    
    // Data untuk grafik
    $response['chartLabels'] = array_column($data, 'Waktu');
    $response['speedData'] = array_column($data, 'Speed');
    $response['tc1Data'] = array_column($data, 'TC1');
    $response['ex1Data'] = array_column($data, 'EX1');
}

// Keluarkan response sebagai JSON
header('Content-Type: application/json');
echo json_encode($response);
?>