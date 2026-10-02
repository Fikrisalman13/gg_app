<?php
// Start session and output buffering at the VERY BEGINNING
session_start();
ob_start();

// Load configuration and dependencies
include '../../koneksi.php';

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

// Check permissions (assuming MenuId for e-dokumen is 71)
$groupId = $_SESSION['GroupId'];
$menuId = 73;

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

if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Get document statistics
$docStats = getDocumentStatistics($conn);

// Get recent documents
$recentDocuments = getRecentDocuments($conn);

/**
 * Get document statistics
 */
function getDocumentStatistics($conn) {
    $stats = [];
    
    // Total documents
    $sql = "SELECT COUNT(*) as total FROM dbo.dokumen";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['total'] = $row['total'];
    sqlsrv_free_stmt($stmt);
    
    // By category
    $sql = "SELECT k.nama_kategori, COUNT(d.id_dok) as count 
            FROM dbo.m_kategori_dok k
            LEFT JOIN dbo.dokumen d ON k.id_kategori = d.id_kategori
            GROUP BY k.nama_kategori
            ORDER BY count DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_category'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_category'][$row['nama_kategori']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    // By department
    $sql = "SELECT m_dept.dept, COUNT(d.id_dok) as count 
            FROM dbo.m_dept
            LEFT JOIN dbo.dokumen d ON m_dept.id_dept = d.id_dept
            GROUP BY m_dept.dept
            ORDER BY count DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $stats['by_department'] = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $stats['by_department'][$row['dept']] = $row['count'];
    }
    sqlsrv_free_stmt($stmt);
    
    // By revision status
    $sql = "SELECT 
                SUM(CASE WHEN revisi = '00' THEN 1 ELSE 0 END) as initial,
                SUM(CASE WHEN revisi > '00' THEN 1 ELSE 0 END) as revised
            FROM dbo.dokumen";
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $stats['by_revision'] = [
        'Initial' => $row['initial'],
        'Revised' => $row['revised']
    ];
    sqlsrv_free_stmt($stmt);
    
    return $stats;
}

/**
 * Get recent documents
 */
function getRecentDocuments($conn) {
    $documents = [];
    
    $sql = "SELECT TOP 5 
                d.kode_dok_seq, d.nama_dokumen, d.revisi, 
                k.nama_kategori, m_dept.dept, d.upddate, d.upduser
            FROM dbo.dokumen d
            LEFT JOIN dbo.m_kategori_dok k ON d.id_kategori = k.id_kategori
            LEFT JOIN dbo.m_dept ON d.id_dept = m_dept.id_dept
            ORDER BY d.upddate DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $documents[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $documents;
}

// Include header
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>


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
        .summary-card.initial { border-left-color: #28a745; }
        .summary-card.revised { border-left-color: #ffc107; }
        .summary-card.categories { border-left-color: #dc3545; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .document-item {
            border-left: 3px solid #dee2e6;
            padding: 10px 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .document-item:hover {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .revision-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .bg-revision-initial { background-color: #28a745; }
        .bg-revision-revised { background-color: #ffc107; color: #212529; }
        
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

<div class="wrapper">

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Dashboard Dokumen ISO</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item active">Dashboard Dokumen ISO</li>
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
                                        <h5 class="card-title">Total Dokumen</h5>
                                        <h2 class="mb-0"><?= number_format($docStats['total']) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-alt fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Semua dokumen ISO
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card initial h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Dokumen Awal</h5>
                                        <h2 class="mb-0"><?= number_format($docStats['by_revision']['Initial']) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file fa-2x text-success"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Revisi awal (00)
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card revised h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Dokumen Direvisi</h5>
                                        <h2 class="mb-0"><?= number_format($docStats['by_revision']['Revised']) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-file-export fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Telah mengalami revisi
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card summary-card categories h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Kategori</h5>
                                        <h2 class="mb-0"><?= number_format(count($docStats['by_category'])) ?></h2>
                                    </div>
                                    <div class="icon">
                                        <i class="fas fa-folder fa-2x text-danger"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1 info-icon"></i> Jumlah kategori dokumen
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
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Distribusi Dokumen per Kategori</h3>
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
                                <h3 class="card-title"><i class="fas fa-building mr-2"></i>Distribusi per Departemen</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">Update terakhir: <?= date('d M Y H:i') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Documents and Revision Distribution -->
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history mr-2"></i>Dokumen Terbaru</h3>
                                <div class="card-tools">
                                    <span class="badge badge-primary">5 Terbaru</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="p-3">
                                    <?php if (empty($recentDocuments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">Tidak ada dokumen terbaru</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentDocuments as $document): ?>
                                            <div class="document-item">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?= htmlspecialchars($document['kode_dok_seq']) ?></strong>
                                                    <small class="text-muted">
                                                        <?= $document['upddate'] ? $document['upddate']->format('d M Y H:i') : '' ?>
                                                    </small>
                                                </div>
                                                <div class="mt-1">
                                                    <?= htmlspecialchars($document['nama_dokumen']) ?>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="text-muted"><?= htmlspecialchars($document['nama_kategori']) ?></span> • 
                                                    <span class="badge revision-badge <?= $document['revisi'] == '00' ? 'bg-revision-initial' : 'bg-revision-revised' ?>">
                                                        Revisi: <?= htmlspecialchars($document['revisi']) ?>
                                                    </span>
                                                </div>
                                                <div class="mt-1">
                                                    <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($document['dept']) ?>
                                                    <?php if (!empty($document['upduser'])): ?>
                                                        • <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($document['upduser']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="/gg_app/pages/e_dok/e_dokumen.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list mr-1"></i> Lihat Semua Dokumen
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Status Revisi</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revisionChart"></canvas>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="mb-1">
                                            <span class="badge revision-badge bg-revision-initial">
                                                Revisi Awal
                                            </span>
                                        </div>
                                        <div class="font-weight-bold"><?= $docStats['by_revision']['Initial'] ?></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-1">
                                            <span class="badge revision-badge bg-revision-revised">
                                                Telah Direvisi
                                            </span>
                                        </div>
                                        <div class="font-weight-bold"><?= $docStats['by_revision']['Revised'] ?></div>
                                    </div>
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

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Chart JS -->
 <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>

<script>
$(document).ready(function() {
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($docStats['by_category'])) ?>,
            datasets: [{
                data: <?= json_encode(array_values($docStats['by_category'])) ?>,
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

    // Department Chart
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    const departmentChart = new Chart(departmentCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($docStats['by_department'])) ?>,
            datasets: [{
                label: 'Jumlah Dokumen',
                data: <?= json_encode(array_values($docStats['by_department'])) ?>,
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
                        return tooltipItem.yLabel + ' dokumen';
                    }
                }
            }
        }
    });

    // Revision Chart
    const revisionCtx = document.getElementById('revisionChart').getContext('2d');
    const revisionChart = new Chart(revisionCtx, {
        type: 'pie',
        data: {
            labels: ['Revisi Awal', 'Telah Direvisi'],
            datasets: [{
                data: [<?= $docStats['by_revision']['Initial'] ?>, <?= $docStats['by_revision']['Revised'] ?>],
                backgroundColor: [
                    '#28a745', // Revisi awal
                    '#ffc107'  // Telah direvisi
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

<?php 
ob_end_flush();
?>