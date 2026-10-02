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

// Query data terbaru mesin Paddry 5
$sql = "
    SELECT
        b.Machine AS Mesin,
        a.LogTimeStamp AS Waktu,
        ROUND(a.Value37/10, 0) AS [Speed],
        a.Value49 AS [TD1],
        a.Value50 AS [TD2],
        a.Value09 AS [NTA1],
        a.Value10 AS [NTA2],
        a.Value11 AS [NTA3],
        a.Value12 AS [NTB1],
        a.Value13 AS [NTB2],
        a.Value14 AS [NTB3],
        a.Value15 AS [NTB4],
        c.Value07 AS [HCT1],
        c.Value08 AS [HCT2],
        a.Value30 AS [HCT3],
        a.Value31 AS [HCT4]
    FROM dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    LEFT JOIN dbo.logvaluefloat AS c ON c.LogTimeStamp = a.LogTimeStamp AND c.LogType_ID = '1411'
    LEFT JOIN dbo.logtype AS d ON d.ID = c.LogType_ID
    WHERE a.LogType_ID = '1401'
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
            'TD1' => $row['TD1'], 'TD2' => $row['TD2'],
            'NTA1' => $row['NTA1'], 'NTA2' => $row['NTA2'], 'NTA3' => $row['NTA3'],
            'NTB1' => $row['NTB1'], 'NTB2' => $row['NTB2'], 'NTB3' => $row['NTB3'], 'NTB4' => $row['NTB4'],
            'HCT1' => $row['HCT1'], 'HCT2' => $row['HCT2'], 'HCT3' => $row['HCT3'], 'HCT4' => $row['HCT4']
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
        machinestatus.Machine = 'MON4'
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
    'avgTD1' => 0,
    'avgNTA1' => 0,
    'tableData' => [],
    'chartLabels' => [],
    'speedData' => [],
    'td1Data' => [],
    'nta1Data' => []
];

// Generate data
if (!empty($data)) {
    $response['avgSpeed'] = round(array_sum(array_column($data, 'Speed')) / max(1, count($data)), 2);
    $response['avgTD1'] = round(array_sum(array_column($data, 'TD1')) / max(1, count($data)), 2);
    $response['avgNTA1'] = round(array_sum(array_column($data, 'NTA1')) / max(1, count($data)), 2);
    
    // Data untuk tabel
    foreach ($data as $row) {
        $response['tableData'][] = [
            $row['Mesin'],
            $row['Waktu'],
            $row['Speed'],
            $row['TD1'], $row['TD2'],
            $row['NTA1'], $row['NTA2'], $row['NTA3'],
            $row['NTB1'], $row['NTB2'], $row['NTB3'], $row['NTB4'],
            $row['HCT1'], $row['HCT2'], $row['HCT3'], $row['HCT4']
        ];
    }
    
    // Data untuk grafik
    $response['chartLabels'] = array_column($data, 'Waktu');
    $response['speedData'] = array_column($data, 'Speed');
    $response['td1Data'] = array_column($data, 'TD1');
    $response['nta1Data'] = array_column($data, 'NTA1');
}

// Keluarkan response sebagai JSON
header('Content-Type: application/json');
echo json_encode($response);
?>