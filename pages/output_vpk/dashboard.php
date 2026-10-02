<?php
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Date range functions
function getStartAndEndDate($month = 0, $year = null) {
    $year = $year ?: date('Y');
    $firstDay = strtotime("$year-$month-01");
    $lastDay  = strtotime("last day of $year-$month-01");
    return [
        'start' => date('Y-m-01', $firstDay),
        'end'   => date('Y-m-t', $lastDay)
    ];
}

// Month and year selection
$monthOffset = isset($_GET['month']) ? (int) $_GET['month'] : date('m');
$year        = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$datesRange  = getStartAndEndDate($monthOffset, $year);
$startDate   = $datesRange['start'];
$endDate     = $datesRange['end'];

// Database query
$sql = "
    SELECT tanggal, SUM(qty) AS total_qty, SUM(qty_a1) AS total_a1
    FROM packing_output
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY tanggal
    ORDER BY tanggal
";
$params = [$startDate, $endDate];
$stmt = sqlsrv_query($conn, $sql, $params);

$data = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['tanggal'] instanceof DateTime) {
            $row['tanggal'] = $row['tanggal']->format('Y-m-d');
        }
        $data[] = $row;
    }
} else {
    die(print_r(sqlsrv_errors(), true));
}

// Calculate cumulative values
$cumulativeQty        = [];
$cumulativeA1         = [];
$cumulativeTargetMax  = [];
$cumulativeSumQty     = 0;
$cumulativeSumA1      = 0;

$daysInMonth = date('t', strtotime($startDate));
$targetMin   = 200000;
$targetMax   = 5000000;

foreach ($data as $index => $row) {
    $cumulativeSumQty += $row['total_qty'];
    $cumulativeSumA1  += $row['total_a1'];

    $cumulativeQty[] = $cumulativeSumQty;
    $cumulativeA1[]  = $cumulativeSumA1;

    $cumulativeTargetMax[] = $targetMin + (($targetMax - $targetMin) * (($index + 1) / $daysInMonth));
}

// Totals and averages
$totalQty    = $cumulativeSumQty;
$totalA1     = $cumulativeSumA1;
$averageQty  = count($data) > 0 ? $totalQty / count($data) : 0;
$averageA1   = count($data) > 0 ? $totalA1 / count($data) : 0;
$totalPercentageA1 = ($cumulativeSumQty != 0) ? ($totalA1 / $cumulativeSumQty) * 100 : 0;

// Month names
$bulan = [
    1  => 'Januari',  2 => 'Februari', 3 => 'Maret',
    4  => 'April',    5 => 'Mei',      6 => 'Juni',
    7  => 'Juli',     8 => 'Agustus',  9 => 'September',
    10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$selectedMonthName = $bulan[(int)$monthOffset];
?>

   
        <style>
        /* CSS yang sudah ada */
        .card-statistic {
            border-left: 4px solid #4e73df;
            border-radius: 0.35rem;
            transition: transform 0.3s;
        }
        .card-statistic:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .statistic-icon {
            font-size: 1.5rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .progress-thin {
            height: 5px;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .info-box-icon {
            height: 70px;
            width: 70px;
            text-align: center;
            font-size: 30px;
            line-height: 70px;
            border-radius: 50%;
        }
    
    </style>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0 text-dark">Packing Output Dashboard</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#">Production</a></li>
                                <li class="breadcrumb-item active">Packing Output</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- Period Selection -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                    <h3 class="card-title">Select Period</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form method="get" class="form-inline">
                                        <div class="form-group mr-3">
                                            <label for="month" class="mr-2">Month:</label>
                                            <select name="month" class="form-control" onchange="this.form.submit()">
                                                <?php foreach ($bulan as $num => $name): ?>
                                                    <option value="<?= $num ?>" <?= $monthOffset == $num ? 'selected' : '' ?>><?= $name ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="year" class="mr-2">Year:</label>
                                            <select name="year" class="form-control" onchange="this.form.submit()">
                                                <?php for ($y = 2023; $y <= date('Y') + 1; $y++): ?>
                                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row">
                        <!-- Total VPK -->
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?= number_format($totalQty, 0) ?></h3>
                                    <p>Total VPK Output</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-ruler-combined"></i>
                                </div>
                                <a href="#" class="small-box-footer">
                                    <?= $selectedMonthName ?> <?= $year ?> <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Total A1 -->
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?= number_format($totalA1, 0) ?></h3>
                                    <p>Total A1 Output</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-ruler-horizontal"></i>
                                </div>
                                <a href="#" class="small-box-footer">
                                    <?= $selectedMonthName ?> <?= $year ?> <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>

                        <!-- A1 Percentage -->
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?= number_format($totalPercentageA1, 2) ?>%</h3>
                                    <p>A1 Percentage</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-percent"></i>
                                </div>
                                <a href="#" class="small-box-footer">
                                    Of Total VPK <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Daily Average -->
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?= number_format($averageQty, 0) ?></h3>
                                    <p>Daily VPK Average</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <a href="#" class="small-box-footer">
                                    <?= $selectedMonthName ?> <?= $year ?> <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Statistics -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Monthly Summary</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Total VPK Output</td>
                                                    <td><?= number_format($totalQty, 2) ?> meters</td>
                                                </tr>
                                                <tr>
                                                    <td>Total A1 Output</td>
                                                    <td><?= number_format($totalA1, 2) ?> meters</td>
                                                </tr>
                                                <tr>
                                                    <td>A1 Percentage</td>
                                                    <td><?= number_format($totalPercentageA1, 2) ?>%</td>
                                                </tr>
                                                <tr>
                                                    <td>Daily VPK Average</td>
                                                    <td><?= number_format($averageQty, 2) ?> meters</td>
                                                </tr>
                                                <tr>
                                                    <td>Daily A1 Average</td>
                                                    <td><?= number_format($averageA1, 2) ?> meters</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Performance Indicators</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>VPK Output Progress</span>
                                            <span><?= number_format(($totalQty/$targetMax)*100, 1) ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-info" style="width: <?= min(100, ($totalQty/$targetMax)*100) ?>%"></div>
                                        </div>
                                        <small class="text-muted">Target: <?= number_format($targetMax) ?> meters</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>A1 Output Progress</span>
                                            <span><?= number_format(($totalA1/$targetMax)*100, 1) ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-success" style="width: <?= min(100, ($totalA1/$targetMax)*100) ?>%"></div>
                                        </div>
                                        <small class="text-muted">Target: <?= number_format($targetMax) ?> meters</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Month Completion</span>
                                            <span><?= date('d') > 1 ? number_format((date('d')/date('t'))*100, 1) : '0' ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-primary" style="width: <?= date('d') > 1 ? (date('d')/date('t'))*100 : '0' ?>%"></div>
                                        </div>
                                        <small class="text-muted">Day <?= date('d') ?> of <?= date('t') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Chart -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Cumulative Output Trend</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                            <i class="fas fa-expand"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="packingChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
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
 <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('packingChart').getContext('2d');
            const packingChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($data, 'tanggal')) ?>,
                    datasets: [
                        {
                            label: 'Cumulative VPK Output',
                            data: <?= json_encode($cumulativeQty) ?>,
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#4e73df',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#4e73df',
                            pointHoverBorderColor: '#fff',
                            pointHitRadius: 10,
                            pointBorderWidth: 2,
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Cumulative A1 Output',
                            data: <?= json_encode($cumulativeA1) ?>,
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.05)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#1cc88a',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#1cc88a',
                            pointHoverBorderColor: '#fff',
                            pointHitRadius: 10,
                            pointBorderWidth: 2,
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Target Maximum',
                            data: <?= json_encode($cumulativeTargetMax) ?>,
                            borderColor: '#e74a3b',
                            backgroundColor: 'rgba(231, 74, 59, 0)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: { 
                            title: { 
                                display: true, 
                                text: 'Date',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        },
                        y: { 
                            title: { 
                                display: true, 
                                text: 'Output (meters)',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleFontColor: '#6e707e',
                            titleFontSize: 14,
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y.toLocaleString() + ' meters';
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        });
    </script>
