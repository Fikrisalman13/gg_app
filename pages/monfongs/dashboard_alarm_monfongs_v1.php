<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi4.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Query untuk data distribusi waktu operasi 24 jam
$sql_operasi = "
    SELECT 
        DATEPART(HOUR, LogTimeStamp) AS Jam,
        COUNT(*) AS JumlahAlarm,
        CASE 
            WHEN COUNT(*) = 0 THEN 'Normal'
            WHEN COUNT(*) <= 3 THEN 'Ringan'
            WHEN COUNT(*) <= 10 THEN 'Sedang'
            ELSE 'Berat'
        END AS StatusOperasi
    FROM dbo.machineprotocol 
    WHERE Machine IN ('MON2', 'MON3', 'MON4', 'MON5')
      AND LogTimeStamp >= DATEADD(DAY, -1, GETDATE())
    GROUP BY DATEPART(HOUR, LogTimeStamp)
    ORDER BY Jam
";

$stmt_operasi = sqlsrv_query($conn4, $sql_operasi);
$data_operasi = [];
$total_alarm = 0;

if ($stmt_operasi) {
    while ($row = sqlsrv_fetch_array($stmt_operasi, SQLSRV_FETCH_ASSOC)) {
        $data_operasi[] = $row;
        $total_alarm += $row['JumlahAlarm'];
    }
}

// Data untuk donut chart
$jam_operasi = [];
foreach ($data_operasi as $item) {
    $jam_operasi[] = $item['Jam'] . ':00';
}

$jumlah_alarm = [];
foreach ($data_operasi as $item) {
    $jumlah_alarm[] = $item['JumlahAlarm'];
}

// Kategori status operasi
$status_count = [
    'Normal' => 0,
    'Ringan' => 0,
    'Sedang' => 0,
    'Berat' => 0
];

foreach ($data_operasi as $item) {
    $status_count[$item['StatusOperasi']]++;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Alarm Monfongs</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <script src="/gg_app/plugins/js/chart.js"></script>
</head>
<body>
<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Dashboard Alarm Monfongs</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Dashboard Alarm</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Statistik Ringkas -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $total_alarm ?></h3>
                            <p>Total Alarm (24 Jam)</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $status_count['Normal'] ?></h3>
                            <p>Jam Normal</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $status_count['Ringan'] + $status_count['Sedang'] ?></h3>
                            <p>Jam Perhatian</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $status_count['Berat'] ?></h3>
                            <p>Jam Kritis</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-skull-crossbones"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafik Donut Chart -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title">Distribusi Alarm per Jam (24 Jam Terakhir)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="donutChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title">Status Operasi Mesin</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Data Detail -->
            <div class="card">
                <div class="card-header bg-<?= $themeColor ?> text-white">
                    <h3 class="card-title">Detail Alarm per Jam</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Jam</th>
                                    <th>Jumlah Alarm</th>
                                    <th>Status Operasi</th>
                                    <th>Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_operasi as $item): ?>
                                <tr>
                                    <td><?= $item['Jam'] ?>:00 - <?= $item['Jam']+1 ?>:00</td>
                                    <td><?= $item['JumlahAlarm'] ?></td>
                                    <td>
                                        <?php 
                                        $badge_color = [
                                            'Normal' => 'success',
                                            'Ringan' => 'info',
                                            'Sedang' => 'warning',
                                            'Berat' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?= $badge_color[$item['StatusOperasi']] ?>">
                                            <?= $item['StatusOperasi'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $total_alarm > 0 ? number_format(($item['JumlahAlarm'] / $total_alarm) * 100, 1) : 0 ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Donut Chart - Distribusi per Jam
    var donutChartCanvas = document.getElementById('donutChart').getContext('2d');
    var donutChart = new Chart(donutChartCanvas, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($jam_operasi) ?>,
            datasets: [{
                data: <?= json_encode($jumlah_alarm) ?>,
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#36A2EB',
                    '#FFCE56', '#9966FF', '#FF9F40', '#FF6384', '#36A2EB',
                    '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF',
                    '#4BC0C0', '#36A2EB', '#FFCE56', '#9966FF'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ': ' + value + ' alarm (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Pie Chart - Status Operasi
    var statusChartCanvas = document.getElementById('statusChart').getContext('2d');
    var statusChart = new Chart(statusChartCanvas, {
        type: 'pie',
        data: {
            labels: ['Normal', 'Ringan', 'Sedang', 'Berat'],
            datasets: [{
                data: [
                    <?= $status_count['Normal'] ?>,
                    <?= $status_count['Ringan'] ?>,
                    <?= $status_count['Sedang'] ?>,
                    <?= $status_count['Berat'] ?>
                ],
                backgroundColor: [
                    '#28a745', '#17a2b8', '#ffc107', '#dc3545'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ': ' + value + ' jam (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
</body>
</html>