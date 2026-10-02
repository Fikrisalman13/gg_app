<?php
// dashboard_finance.php - Summary for Finance/MT
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

// 1. Total Balance across all accounts
$totalBalance = 0;
$balSql = "SELECT SUM(CurrentBalance) as Total FROM fin_cash_accounts";
$balStmt = sqlsrv_query($conn, $balSql);
if ($balStmt) {
    if ($row = sqlsrv_fetch_array($balStmt, SQLSRV_FETCH_ASSOC)) {
        $totalBalance = $row['Total'] ?? 0;
    }
    sqlsrv_free_stmt($balStmt);
}

// 2. Pending Approvals Count (Total in system)
$pendingCount = 0;
$pendingSql = "SELECT COUNT(*) as Total FROM fin_requests WHERE Status = 'Pending'";
$pendingStmt = sqlsrv_query($conn, $pendingSql);
if ($pendingStmt) {
    if ($row = sqlsrv_fetch_array($pendingStmt, SQLSRV_FETCH_ASSOC)) {
        $pendingCount = $row['Total'] ?? 0;
    }
    sqlsrv_free_stmt($pendingStmt);
}

// 3. Ready to Pay Count
$readyCount = 0;
$readySql = "SELECT COUNT(*) as Total FROM fin_requests WHERE Status = 'Approved'";
$readyStmt = sqlsrv_query($conn, $readySql);
if ($readyStmt) {
    if ($row = sqlsrv_fetch_array($readyStmt, SQLSRV_FETCH_ASSOC)) {
        $readyCount = $row['Total'] ?? 0;
    }
    sqlsrv_free_stmt($readyStmt);
}

// 4. Recent Transactions (Ledger)
$ledgerSql = "SELECT TOP 10 l.*, a.AccountName 
              FROM fin_ledger l
              JOIN fin_cash_accounts a ON l.AccountId = a.AccountId
              ORDER BY l.TransTimestamp DESC";
$ledgerStmt = sqlsrv_query($conn, $ledgerSql);
$recentTrans = [];
if ($ledgerStmt) {
    while ($row = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
        $recentTrans[] = $row;
    }
    sqlsrv_free_stmt($ledgerStmt);
}

// --- ANALYTICS QUERIES ---

// A. Spending Trends (Dynamic Range)
$range = isset($_GET['range']) ? (int)$_GET['range'] : 6;
if ($range < 1) $range = 1;
if ($range > 12) $range = 12;

$trendData = [];
$trendSql = "SELECT FORMAT(TransTimestamp, 'MMM yyyy') as MonthLabel, SUM(Amount) as TotalSpent, FORMAT(TransTimestamp, 'yyyy-MM') as SortKey
             FROM fin_ledger WHERE Type = 'OUT' AND Description NOT LIKE '%Transfer%'
             AND TransTimestamp >= DATEADD(month, ?, CAST(GETDATE() AS DATE))
             GROUP BY FORMAT(TransTimestamp, 'MMM yyyy'), FORMAT(TransTimestamp, 'yyyy-MM')
             ORDER BY SortKey ASC";
// Note: DATEADD(month, -X, ...) so we negate the range
$negRange = -1 * ($range - 1); 
$trendParams = [$negRange];
$trendStmt = sqlsrv_query($conn, $trendSql, $trendParams);

if ($trendStmt) {
    while ($row = sqlsrv_fetch_array($trendStmt, SQLSRV_FETCH_ASSOC)) { $trendData[] = $row; }
} else {
     // Debug if query fails
     // print_r(sqlsrv_errors());
}

// B. Category Distribution
$catData = [];
$catSql = "SELECT c.CategoryName, SUM(l.Amount) as Total 
           FROM fin_ledger l 
           JOIN fin_requests r ON l.RequestId = r.RequestId 
           JOIN fin_categories c ON r.CategoryId = c.CategoryId
           WHERE l.Type = 'OUT' GROUP BY c.CategoryName";
$catStmt = sqlsrv_query($conn, $catSql);
if ($catStmt) {
    while ($row = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) { $catData[] = $row; }
}

// C. Budget Comparison (Current Period)
$budgetComp = ['Allocated' => 0, 'Spent' => 0, 'Label' => date('Y-m')];
$bcSql = "SELECT SUM(bp.TotalAllocated) as TotalAllocated,
          ISNULL((SELECT SUM(r.Amount) FROM fin_requests r 
                  JOIN fin_budget_plans bp2 ON r.BudgetId = bp2.BudgetId 
                  WHERE bp2.Period = FORMAT(GETDATE(), 'yyyy-MM') AND r.Status = 'Paid'), 0) as TotalSpent
          FROM fin_budget_plans bp WHERE bp.Period = FORMAT(GETDATE(), 'yyyy-MM')";
$bcStmt = sqlsrv_query($conn, $bcSql);
if ($bcStmt && $row = sqlsrv_fetch_array($bcStmt, SQLSRV_FETCH_ASSOC)) {
    $budgetComp['Allocated'] = $row['TotalAllocated'] ?? 0;
    $budgetComp['Spent'] = $row['TotalSpent'] ?? 0;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Finance Overview Dashboard</h1></div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Summary Widgets -->
            <div class="row">
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-info shadow">
                        <div class="inner">
                            <h3>Rp <?= number_format($totalBalance, 0, ',', '.') ?></h3>
                            <p>Total Saldo (All Accounts)</p>
                        </div>
                        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                        <a href="accounts.php" class="small-box-footer">Manajemen Kas <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-6">
                    <div class="small-box bg-warning shadow">
                        <div class="inner">
                            <h3><?= $pendingCount ?></h3>
                            <p>Pengajuan Menunggu Approval</p>
                        </div>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                        <a href="pending_approvals.php" class="small-box-footer">Verifikasi Sekarang <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-12">
                    <div class="small-box bg-success shadow">
                        <div class="inner">
                            <h3><?= $readyCount ?></h3>
                            <p>Siap Dicairkan (Disbursement)</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                        <a href="disbursements.php" class="small-box-footer">Proses Pembayaran <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <!-- Analytics Charts -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white d-flex align-items-center">
                            <h3 class="card-title font-weight-bold mr-auto"><i class="fas fa-chart-line mr-2"></i>Tren Pengeluaran</h3>
                            <div class="card-tools">
                                <select class="form-control form-control-sm" onchange="window.location.href='?range='+this.value">
                                    <option value="1" <?= $range == 1 ? 'selected' : '' ?>>1 Bulan Terakhir</option>
                                    <option value="3" <?= $range == 3 ? 'selected' : '' ?>>3 Bulan Terakhir</option>
                                    <option value="6" <?= $range == 6 ? 'selected' : '' ?>>6 Bulan Terakhir</option>
                                    <option value="9" <?= $range == 9 ? 'selected' : '' ?>>9 Bulan Terakhir</option>
                                    <option value="12" <?= $range == 12 ? 'selected' : '' ?>>1 Tahun Terakhir</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="spendingTrendChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie mr-2"></i>Berdasarkan Kategori</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent History -->
                <div class="col-md-9">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2"></i>Transaksi Terakhir</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-valign-middle table-sm">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Keterangan</th>
                                        <th>Akun</th>
                                        <th class="text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTrans as $l): ?>
                                    <tr>
                                        <td><small><?= date_format($l['TransTimestamp'], 'd/m H:i') ?></small></td>
                                        <td>
                                            <div class="text-dark font-weight-500"><?= htmlspecialchars($l['Description']) ?></div>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($l['AccountName']) ?></small></td>
                                        <td class="text-right text-<?= $l['Type'] == 'OUT' ? 'danger' : 'success' ?> font-weight-bold">
                                            <?= $l['Type'] == 'OUT' ? '-' : '+' ?><?= number_format($l['Amount'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-center bg-white">
                            <a href="accounts.php" class="text-xs">Detail Mutasi Kas <i class="fas fa-arrow-right ml-1"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title">Aksi Cepat</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="requests.php" class="list-group-item list-group-item-action py-3"><i class="fas fa-edit mr-2 text-primary"></i> Buat Pengajuan</a>
                            <a href="rules.php" class="list-group-item list-group-item-action py-3"><i class="fas fa-sliders-h mr-2 text-warning"></i> Rule Approval</a>
                            <a href="org_units.php" class="list-group-item list-group-item-action py-3"><i class="fas fa-sitemap mr-2 text-success"></i> Unit Org</a>
                            <a href="categories.php" class="list-group-item list-group-item-action py-3"><i class="fas fa-tags mr-2 text-info"></i> Kat. Biaya</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>

<script>
$(function () {
    // 1. Spending Trend Chart
    var trendCtx = document.getElementById('spendingTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: [<?php echo "'" . implode("','", array_column($trendData, 'MonthLabel')) . "'"; ?>],
            datasets: [{
                label: 'Total Pengeluaran (Rp)',
                data: [<?php echo implode(",", array_column($trendData, 'TotalSpent')); ?>],
                backgroundColor: 'rgba(60,141,188,0.1)',
                borderColor: 'rgba(60,141,188,0.8)',
                pointRadius: 5,
                pointBackgroundColor: 'rgba(60,141,188,1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            maintainAspectRatio: false,
            legend: { display: false },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
                        return 'Total: Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    }
                }
            },
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) { return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
                    }
                }]
            }
        }
    });

    // 2. Category Chart
    var catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo "'" . implode("','", array_column($catData, 'CategoryName')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(",", array_column($catData, 'Total')); ?>],
                backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#605ca8', '#ff851b']
            }]
        },
        options: {
            maintainAspectRatio: false,
            legend: { position: 'right' },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        var label = data.labels[tooltipItem.index] || '';
                        var value = data.datasets[0].data[tooltipItem.index];
                        return label + ': Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    }
                }
            }
        }
    });

    // 3. (Removed) Budget vs Actual Chart
});
</script>
