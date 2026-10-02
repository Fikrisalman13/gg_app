<?php 
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi4.php';
include '../../includes/header.php';
include '../../includes/sidebar.php'; 

// Pastikan user login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

date_default_timezone_set('Asia/Jakarta');

// Ambil filter tanggal dari GET atau default 1 hari terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-d\T00:00');
$end_date   = $_GET['end_date'] ?? date('Y-m-d\TH:i');

// Konversi ke format SQL Server
$start_date_sql = str_replace("T", " ", $start_date) . ":00";
$end_date_sql   = str_replace("T", " ", $end_date) . ":00";

// ================= Query Data Mesin Stenter =================
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
} else {
    die(print_r(sqlsrv_errors(), true));
}

// ================= Query Status Mesin =================
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
} else {
    // Jika query gagal, tetap tampilkan status unknown
    $machine_status = 'Error';
}

ob_end_flush();
?>

    <style>
        .card-header h3 { font-weight: bold; }
        .summary-box { display:flex; gap:10px; flex-wrap:wrap; }
        .summary-item {
            flex:1; text-align:center; padding:10px; border:1px solid #ddd; border-radius:8px;
            background-color:#f8f9fa;
        }
        .summary-item h4 { margin:0; color:#007bff; }
        .refresh-indicator {
            display: inline-block;
            margin-left: 10px;
            font-size: 14px;
            color: #28a745;
        }
        .blink {
            animation: blinker 1s linear infinite;
        }
        @keyframes blinker {
            50% { opacity: 0; }
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 14px;
        }
        .status-online {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-offline {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-unknown {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        .machine-status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .machine-status-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .machine-status-info h3 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .machine-status-info p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Dashboard Mesin Monfongs 1 - Stenter 
                <span class="refresh-indicator" id="refreshIndicator">
                    <i class="fas fa-sync-alt blink"></i> Auto-refresh setiap 10 detik
                </span>
                <span id="lastUpdate" class="refresh-indicator"></span>
            </h1>
            
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Status Mesin -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="machine-status-card">
                        <div class="machine-status-content">
                            <div class="machine-status-info">
                                <h3>Monfong 1 - Stenter</h3>
                                <p>Status terkini: <span id="machineStatusText"><?= $machine_status ?></span></p>
                                <p>Terakhir update: <span id="lastStatusUpdate"><?= $last_status_update ?></span></p>
                            </div>
                            <div>
                                <span class="status-badge 
                                    <?= $machine_status == 'Online' ? 'status-online' : 
                                       ($machine_status == 'Offline' ? 'status-offline' : 'status-unknown') ?>" 
                                    id="machineStatusBadge">
                                    <i class="fas 
                                        <?= $machine_status == 'Online' ? 'fa-check-circle' : 
                                           ($machine_status == 'Offline' ? 'fa-times-circle' : 'fa-question-circle') ?>"></i>
                                    <?= $machine_status ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ringkasan Data AdminLTE -->
            <div class="row">
                <!-- Kecepatan Rata-rata -->
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner text-center">
                            <h3><?= round(array_sum(array_column($data, 'Speed')) / max(1,count($data)),2) ?> <sup style="font-size:20px">RPM</sup></h3>
                            <p>Kecepatan Rata-rata ⚡</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                    </div>
                </div>

                <!-- TC1 Rata-rata -->
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-success">
                        <div class="inner text-center">
                            <h3><?= round(array_sum(array_column($data, 'TC1')) / max(1,count($data)),2) ?> <sup style="font-size:20px">°C</sup></h3>
                            <p>TC1 Rata-rata 🌡️</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-thermometer-half"></i>
                        </div>
                    </div>
                </div>

                <!-- EX1 Rata-rata -->
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner text-center">
                            <h3><?= round(array_sum(array_column($data, 'EX1')) / max(1,count($data)),2) ?> <sup style="font-size:20px">°C</sup></h3>
                            <p>EX1 Rata-rata 🔥</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-fire"></i>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Info singkatan -->
            <div class="alert alert-info">
                    <strong>Penjelasan Singkatan:</strong>
                    <ul class="mb-0">
                        <li>TC = Temp Chamber</li>
                        <li>EX = Exhaust</li>
                    </ul>
                </div>

            <!-- Grafik Mesin -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                            <h3 class="card-title">Grafik Kecepatan & TC/EX</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" id="pauseChart">
                                    <i class="fas fa-pause"></i> Jeda Grafik
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="grafikMesin"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Data Mesin -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                            <h3 class="card-title">Data Mesin</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" id="refreshNow">
                                    <i class="fas fa-sync-alt"></i> Refresh Sekarang
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive">
                            <table id="dataMesin" class="table table-hover table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Mesin</th>
                                        <th>Waktu</th>
                                        <th>Speed</th>
                                        <th>TC1</th>
                                        <th>TC2</th>
                                        <th>TC3</th>
                                        <th>TC4</th>
                                        <th>TC5</th>
                                        <th>TC6</th>
                                        <th>TC7</th>
                                        <th>TC8</th>
                                        <th>TC9</th>
                                        <th>TC10</th>
                                        <th>TC11</th>
                                        <th>TC12</th>
                                        <th>EX1</th>
                                        <th>EX2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['Mesin']) ?></td>
                                        <td><?= htmlspecialchars($row['Waktu']) ?></td>
                                        <td><?= htmlspecialchars($row['Speed']) ?></td>
                                        <td><?= htmlspecialchars($row['TC1']) ?></td>
                                        <td><?= htmlspecialchars($row['TC2']) ?></td>
                                        <td><?= htmlspecialchars($row['TC3']) ?></td>
                                        <td><?= htmlspecialchars($row['TC4']) ?></td>
                                        <td><?= htmlspecialchars($row['TC5']) ?></td>
                                        <td><?= htmlspecialchars($row['TC6']) ?></td>
                                        <td><?= htmlspecialchars($row['TC7']) ?></td>
                                        <td><?= htmlspecialchars($row['TC8']) ?></td>
                                        <td><?= htmlspecialchars($row['TC9']) ?></td>
                                        <td><?= htmlspecialchars($row['TC10']) ?></td>
                                        <td><?= htmlspecialchars($row['TC11']) ?></td>
                                        <td><?= htmlspecialchars($row['TC12']) ?></td>
                                        <td><?= htmlspecialchars($row['EX1']) ?></td>
                                        <td><?= htmlspecialchars($row['EX2']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- Chart CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Chart JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/chart.js"></script>


<script>
$(document).ready(function(){
    let autoRefresh = true;
    let chartPaused = false;
    let myChart;
    let dataTable;
    
    // Inisialisasi DataTable
    dataTable = $('#dataMesin').DataTable({ 
        responsive: true, 
        autoWidth: false, 
        pageLength: 25,
        order: [[1, 'desc']] // Urutkan berdasarkan waktu terbaru
    });
    
    // Inisialisasi Grafik
    const labels = <?= json_encode(array_column($data, 'Waktu')) ?>;
    const speedData = <?= json_encode(array_column($data, 'Speed')) ?>;
    const tc1Data = <?= json_encode(array_column($data, 'TC1')) ?>;
    const ex1Data = <?= json_encode(array_column($data, 'EX1')) ?>;
    
    const ctx = document.getElementById('grafikMesin').getContext('2d');
    myChart = new Chart(ctx, {
        type:'line',
        data:{
            labels: labels,
            datasets:[
                { 
                    label:'Speed', 
                    data:speedData, 
                    borderColor:'rgba(75,192,192,1)', 
                    backgroundColor:'rgba(75,192,192,0.2)', 
                    fill:true,
                    tension: 0.4
                },
                { 
                    label:'TC1', 
                    data:tc1Data, 
                    borderColor:'rgba(255,99,132,1)', 
                    backgroundColor:'rgba(255,99,132,0.2)', 
                    fill:true,
                    tension: 0.4
                },
                { 
                    label:'EX1', 
                    data:ex1Data, 
                    borderColor:'rgba(255,206,86,1)', 
                    backgroundColor:'rgba(255,206,86,0.2)', 
                    fill:true,
                    tension: 0.4
                }
            ]
        },
        options:{ 
            responsive:true, 
            plugins:{ 
                legend:{ 
                    position:'top' 
                } 
            }, 
            scales:{ 
                x:{ 
                    title:{ 
                        display:true, 
                        text:'Waktu' 
                    } 
                }, 
                y:{ 
                    title:{ 
                        display:true, 
                        text:'Nilai' 
                    } 
                }
            },
            animation: {
                duration: 1000
            }
        }
    });
    
    // Fungsi untuk memperbarui data
    function updateData() {
        if (!autoRefresh) return;
        
        $.ajax({
            url: 'get_realtime_data_mon1.php', // File PHP baru untuk mengambil data terbaru
            type: 'GET',
            data: {
                start_date: '<?= $start_date ?>',
                end_date: '<?= $end_date ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Perbarui status mesin
                    $('#machineStatusText').text(response.machineStatus);
                    $('#lastStatusUpdate').text(response.lastStatusUpdate);
                    
                    // Update status badge
                    let badge = $('#machineStatusBadge');
                    badge.removeClass('status-online status-offline status-unknown');
                    badge.find('i').removeClass('fa-check-circle fa-times-circle fa-question-circle');
                    
                    if (response.machineStatus === 'Online') {
                        badge.addClass('status-online');
                        badge.find('i').addClass('fa-check-circle');
                    } else if (response.machineStatus === 'Offline') {
                        badge.addClass('status-offline');
                        badge.find('i').addClass('fa-times-circle');
                    } else {
                        badge.addClass('status-unknown');
                        badge.find('i').addClass('fa-question-circle');
                    }
                    badge.text(response.machineStatus);
                    
                    // Perbarui ringkasan
                    $('.small-box.bg-primary .inner h3').html(response.avgSpeed + ' <sup style="font-size:20px">RPM</sup>');
                    $('.small-box.bg-success .inner h3').html(response.avgTC1 + ' <sup style="font-size:20px">°C</sup>');
                    $('.small-box.bg-warning .inner h3').html(response.avgEX1 + ' <sup style="font-size:20px">°C</sup>');
                    
                    // Perbarui tabel
                    dataTable.clear();
                    dataTable.rows.add(response.tableData).draw();
                    
                    // Perbarui grafik jika tidak dijeda
                    if (!chartPaused) {
                        myChart.data.labels = response.chartLabels;
                        myChart.data.datasets[0].data = response.speedData;
                        myChart.data.datasets[1].data = response.tc1Data;
                        myChart.data.datasets[2].data = response.ex1Data;
                        myChart.update('none'); // Update tanpa animasi
                    }
                    
                    // Perbarui waktu terakhir
                    $('#lastUpdate').html('<i class="fas fa-clock"></i> Terakhir update: ' + response.lastUpdate);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching data:', error);
            }
        });
    }
    
    // Set interval untuk pembaruan otomatis setiap 10 detik
    setInterval(updateData, 10000);
    
    // Event untuk tombol refresh manual
    $('#refreshNow').click(function() {
        updateData();
    });
    
    // Event untuk tombol jeda grafik
    $('#pauseChart').click(function() {
        chartPaused = !chartPaused;
        if (chartPaused) {
            $(this).html('<i class="fas fa-play"></i> Lanjutkan Grafik');
            $(this).removeClass('btn-default').addClass('btn-warning');
        } else {
            $(this).html('<i class="fas fa-pause"></i> Jeda Grafik');
            $(this).removeClass('btn-warning').addClass('btn-default');
        }
    });
    
    // Tampilkan waktu terakhir update
    $('#lastUpdate').html('<i class="fas fa-clock"></i> Terakhir update: <?= date("H:i:s") ?>');
});
</script>
