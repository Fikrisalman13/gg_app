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
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Check permissions (assuming MenuId 53 is for Asset Management)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 53);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/pages/asset/dashboard.php');
    exit;
}

// Get asset statistics
$assetStats = getAssetStatistics($conn);

// Get recent activities
$recentActivities = getRecentActivities($conn);

/**
 * Check user permissions
 */
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $permissions;
}

/**
 * Get asset statistics
 */
function getAssetStatistics($conn) {
    $stats = [];
    
    // Total assets
    $sql = "SELECT COUNT(*) as total FROM dbo.m_asset";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['total'] = $row['total'];
    sqlsrv_free_stmt($stmt);
    
    // By status
    $sql = "SELECT s.nama_status, COUNT(a.id_asset) as count 
            FROM dbo.m_status s
            LEFT JOIN dbo.m_asset a ON s.id_status = a.id_status
            GROUP BY s.nama_status";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_status'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_status'][$row['nama_status']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    // By category
    $sql = "SELECT k.nama_kategori, COUNT(a.id_asset) as count 
            FROM dbo.m_kategori k
            LEFT JOIN dbo.m_asset a ON k.id_kategori = a.id_kategori
            GROUP BY k.nama_kategori
            ORDER BY count DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_category'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_category'][$row['nama_kategori']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    // By location
    $sql = "SELECT l.nama_lokasi, COUNT(a.id_asset) as count 
            FROM dbo.m_lokasi l
            LEFT JOIN dbo.m_asset a ON l.id_lokasi = a.id_lokasi
            GROUP BY l.nama_lokasi
            ORDER BY count DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_location'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_location'][$row['nama_lokasi']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    return $stats;
}

/**
 * Get recent activities
 */
function getRecentActivities($conn) {
    $activities = [];
    
    $sql = "SELECT TOP 5 a.kode_asset_seq, a.serial_number, k.nama_kategori, 
                   s.nama_status, l.nama_lokasi, e.nama_lengkap, a.upddate
            FROM dbo.m_asset a
            LEFT JOIN dbo.m_kategori k ON a.id_kategori = k.id_kategori
            LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
            LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
            LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
            ORDER BY a.upddate DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $activities[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $activities;
}

// Include header
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Manajemen Asset</title>
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
        .summary-card.available { border-left-color: #28a745; }
        .summary-card.borrowed { border-left-color: #ffc107; }
        .summary-card.maintenance { border-left-color: #dc3545; }
        
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
        
        .bg-status-available { background-color: #28a745; }
        .bg-status-borrowed { background-color: #ffc107; color: #212529; }
        .bg-status-maintenance { background-color: #dc3545; }
        .bg-status-disposed { background-color: #6c757d; }
        
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
                        <h1>Dashboard Asset IT</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item active">Dashboard Asset IT</li>
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
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card total h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Asset</h5>
                                        <h2 class="mb-0"><?= number_format($assetStats['total']) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-boxes fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Semua asset perusahaan
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card available h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Digunakan</h5>
                                        <h2 class="mb-0"><?= number_format($assetStats['by_status']['Used'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Sedang digunakan
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card maintenance h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Maintenance</h5>
                                        <h2 class="mb-0"><?= number_format($assetStats['by_status']['Maintenance'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-tools fa-2x text-danger"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Dalam perbaikan
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card borrowed h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Rusak</h5>
                                        <h2 class="mb-0"><?= number_format($assetStats['by_status']['Broken'] ?? 0) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-times-circle fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Asset tidak dapat digunakan
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Distribusi Asset per Kategori</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">Update terakhir: <?= date('d M Y H:i') ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i>Distribusi per Lokasi</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="locationChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">Update terakhir: <?= date('d M Y H:i') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Status Distribution -->
                <div class="row">
                    <div class="col-lg-6 mb-3">
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
                                                    <strong><?= htmlspecialchars($activity['kode_asset_seq']) ?></strong>
                                                    <small class="text-muted">
                                                        <?= $activity['upddate'] ? $activity['upddate']->format('d M Y H:i') : '' ?>
                                                    </small>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="text-muted"><?= htmlspecialchars($activity['nama_kategori']) ?></span> • 
                                                    <span class="badge status-badge bg-status-<?= strtolower($activity['nama_status']) ?>">
                                                        <?= htmlspecialchars($activity['nama_status']) ?>
                                                    </span>
                                                </div>
                                                <div class="mt-1">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> <?= htmlspecialchars($activity['nama_lokasi']) ?>
                                                    <?php if (!empty($activity['nama_lengkap'])): ?>
                                                        • <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($activity['nama_lengkap']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="/gg_app/pages/asset/asset.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list mr-1"></i> Lihat Semua Asset
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Status Asset</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="row text-center">
                                    <?php foreach ($assetStats['by_status'] as $status => $count): ?>
                                        <div class="col-4 col-md-3">
                                            <div class="mb-1">
                                                <span class="badge status-badge bg-status-<?= strtolower($status) ?>">
                                                    <?= $status ?>
                                                </span>
                                            </div>
                                            <div class="font-weight-bold"><?= $count ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($assetStats['by_category'])) ?>,
            datasets: [{
                data: <?= json_encode(array_values($assetStats['by_category'])) ?>,
                backgroundColor: [
                    '#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', 
                    '#17a2b8', '#6610f2', '#fd7e14', '#20c997', '#e83e8c'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right',
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const dataset = data.datasets[tooltipItem.datasetIndex];
                        const total = dataset.data.reduce((a, b) => a + b, 0);
                        const currentValue = dataset.data[tooltipItem.index];
                        const percentage = Math.floor((currentValue / total) * 100);
                        return `${data.labels[tooltipItem.index]}: ${currentValue} (${percentage}%)`;
                    }
                }
            }
        }
    });

    // Location Chart
    const locationCtx = document.getElementById('locationChart').getContext('2d');
    const locationChart = new Chart(locationCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($assetStats['by_location'])) ?>,
            datasets: [{
                label: 'Jumlah Asset',
                data: <?= json_encode(array_values($assetStats['by_location'])) ?>,
                backgroundColor: '#007bff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            legend: {
                display: false
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem) {
                        return tooltipItem.yLabel + ' asset';
                    }
                }
            }
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($assetStats['by_status'])) ?>,
            datasets: [{
                data: <?= json_encode(array_values($assetStats['by_status'])) ?>,
                backgroundColor: [
                    '#28a745', // Digunakan
                    '#ffc107', // Rusak
                    '#dc3545', // Maintenance
                    '#6c757d'  // Lainnya
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right',
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const dataset = data.datasets[tooltipItem.datasetIndex];
                        const total = dataset.data.reduce((a, b) => a + b, 0);
                        const currentValue = dataset.data[tooltipItem.index];
                        const percentage = Math.floor((currentValue / total) * 100);
                        return `${data.labels[tooltipItem.index]}: ${currentValue} (${percentage}%)`;
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