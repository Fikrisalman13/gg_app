<?php
// Start session and output buffering at the VERY BEGINNING
session_start();
ob_start();

// Load configuration and dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

/**
 * Get padder statistics
 */
function getPadderStatistics($conn) {
    $stats = [];
    
    // Total padder
    $sql = "SELECT COUNT(*) as total FROM pad_m_padder";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['total'] = $row['total'];
    sqlsrv_free_stmt($stmt);
    
    // By status
    $sql = "SELECT status, COUNT(*) as count 
            FROM pad_m_padder 
            GROUP BY status";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_status'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_status'][$row['status']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    return $stats;
}

/**
 * Get recent activities
 */
function getRecentActivities($conn) {
    $activities = [];
    
    $sql = "SELECT TOP 5 
              pl.padder_id, pl.status, pl.changed_at, pl.changed_by, pl.remarks,
              p.padder_name
            FROM pad_status_log pl
            JOIN pad_m_padder p ON pl.padder_id = p.padder_id
            ORDER BY pl.changed_at DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $activities[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $activities;
}

// Get statistics and activities
$padderStats = getPadderStatistics($conn);
$recentActivities = getRecentActivities($conn);

// Include header
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.css">
    <style>
        .summary-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .summary-card.total { border-left-color: #007bff; }
        .summary-card.ready { border-left-color: #28a745; }
        .summary-card.in-use { border-left-color: #17a2b8; }
        .summary-card.maintenance { border-left-color: #ffc107; }
        .summary-card.repair { border-left-color: #fd7e14; }
        .summary-card.scrap { border-left-color: #dc3545; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .activity-item {
            border-left: 3px solid #dee2e6;
            padding: 10px 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .bg-status-ready { background-color: #28a745; }
        .bg-status-in_use { background-color: #17a2b8; }
        .bg-status-maintenance { background-color: #ffc107; color: #212529; }
        .bg-status-repair_vendor { background-color: #fd7e14; }
        .bg-status-scrap { background-color: #dc3545; }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,.125);
            background-color: #f8f9fa;
        }
        
        .card-title {
            font-weight: 600;
            color: #343a40;
        }
        
        .info-icon {
            font-size: 1.2rem;
            vertical-align: middle;
        }
        
        .quick-action-btn {
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            border: none;
            transition: all 0.3s ease;
            font-weight: 600;
            text-align: left;
        }
        
        .quick-action-btn:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .quick-action-btn i {
            font-size: 1.1rem;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            
            .summary-card .icon {
                font-size: 1.5rem !important;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Dashboard Monitoring Padder</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item active">Dashboard Padder</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card total h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Padder</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['total']) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-boxes fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Semua padder
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card ready h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Ready</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['by_status']['READY'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Siap digunakan
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card in-use h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">In Use</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['by_status']['IN_USE'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-cogs fa-2x text-info"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Sedang digunakan
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card maintenance h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Maintenance</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['by_status']['MAINTENANCE'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-tools fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Dalam perawatan
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card repair h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Repair Vendor</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['by_status']['REPAIR_VENDOR'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-truck-loading fa-2x" style="color: #fd7e14;"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Diperbaiki vendor
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card scrap h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Scrap</h5>
                                        <h2 class="mb-0"><?= number_format($padderStats['by_status']['SCRAP'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-trash fa-2x text-danger"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Rusak total
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Quick Actions Row -->
                <div class="row mb-4">
                    <!-- Status Distribution Chart -->
                    <div class="col-lg-8 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Distribusi Status Padder</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">Update terakhir: <?= date('d M Y H:i') ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <a href="master_padder.php" class="btn btn-primary btn-block quick-action-btn">
                                    <i class="fas fa-plus-circle"></i> Registrasi Padder Baru
                                </a>
                                <a href="purchase.php" class="btn btn-success btn-block quick-action-btn">
                                    <i class="fas fa-shopping-cart"></i> Buat Purchase Order
                                </a>
                                <a href="usage.php" class="btn btn-info btn-block quick-action-btn">
                                    <i class="fas fa-cogs"></i> Assign Padder ke Mesin
                                </a>
                                <a href="maintenance.php" class="btn btn-warning btn-block quick-action-btn">
                                    <i class="fas fa-tools"></i> Input Maintenance
                                </a>
                                <a href="repair.php" class="btn btn-secondary btn-block quick-action-btn">
                                    <i class="fas fa-toolbox"></i> Kirim Perbaikan
                                </a>
                                <a href="scrap.php" class="btn btn-danger btn-block quick-action-btn">
                                    <i class="fas fa-trash"></i> Input Scrap
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history mr-2"></i>Aktivitas Terkini</h3>
                                <div class="card-tools">
                                    <span class="badge badge-primary">5 Terbaru</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="p-3">
                                    <?php if (empty($recentActivities)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">Tidak ada aktivitas terbaru</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?= htmlspecialchars($activity['padder_id']) ?></strong>
                                                    <small class="text-muted">
                                                        <?= $activity['changed_at'] ? $activity['changed_at']->format('d M Y H:i') : '' ?>
                                                    </small>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="text-muted"><?= htmlspecialchars($activity['padder_name']) ?></span> • 
                                                    <span class="badge status-badge bg-status-<?= strtolower($activity['status']) ?>">
                                                        <?= htmlspecialchars($activity['status']) ?>
                                                    </span>
                                                </div>
                                                <div class="mt-1">
                                                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($activity['changed_by']) ?>
                                                    <?php if (!empty($activity['remarks'])): ?>
                                                        • <i class="fas fa-comment mr-1"></i> <?= htmlspecialchars($activity['remarks']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="status_log.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list mr-1"></i> Lihat Semua Aktivitas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</div>

<!-- JavaScript Libraries -->
<script src="/gg_app/plugins/js/jquery.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/Chart.min.js"></script>
<script src="/gg_app/plugins/js/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Ready', 'In Use', 'Maintenance', 'Repair Vendor', 'Scrap'],
            datasets: [{
                data: [
                    <?= $padderStats['by_status']['READY'] ?? 0 ?>,
                    <?= $padderStats['by_status']['IN_USE'] ?? 0 ?>,
                    <?= $padderStats['by_status']['MAINTENANCE'] ?? 0 ?>,
                    <?= $padderStats['by_status']['REPAIR_VENDOR'] ?? 0 ?>,
                    <?= $padderStats['by_status']['SCRAP'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#28a745', // Ready
                    '#17a2b8', // In Use
                    '#ffc107', // Maintenance
                    '#fd7e14', // Repair Vendor
                    '#dc3545'  // Scrap
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Show notifications
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: '<?= $_SESSION['success'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= $_SESSION['error'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>