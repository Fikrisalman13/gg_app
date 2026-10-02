<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['UserId'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$day = $_GET['day'] ?? date('d');

// Deletion is now handled securely via AJAX POST using delete_pemadaman.php

// Detailed report list is now retrieved server-side via rekap_pemadaman_serverside.php

// Function to get summary data with filters
function getSummaryData($conn, $filter, $month, $year, $day) {
    $sql = "SELECT 
                COUNT(*) as total_laporan,
                SUM(jumlah_dimatikan) as total_dimatikan,
                SUM(jumlah_aktif) as total_aktif,
                bagian as departemen
            FROM dbo.report_pemadaman";
    
    // Add WHERE clause based on filter
    $params = [];
    switch ($filter) {
        case 'daily':
            $sql .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$day, $month, $year];
            break;
        case 'monthly':
            $sql .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$month, $year];
            break;
        case 'yearly':
            $sql .= " WHERE YEAR(waktu) = ?";
            $params = [$year];
            break;
    }
    
    $sql .= " GROUP BY bagian
              ORDER BY COUNT(*) DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $summary = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $summary[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $summary;
}

// Function to get time period statistics
function getTimePeriodStats($conn, $filter, $month, $year, $day) {
    $stats = [
        'total_reports' => 0,
        'total_shutdown' => 0,
        'total_active' => 0,
        'top_department' => '',
        'most_active_day' => '',
        'avg_shutdown' => 0
    ];
    
    // Base query
    $sql = "SELECT 
                COUNT(*) as total_reports,
                SUM(jumlah_dimatikan) as total_shutdown,
                SUM(jumlah_aktif) as total_active,
                AVG(jumlah_dimatikan) as avg_shutdown
            FROM dbo.report_pemadaman";
    
    // Add WHERE clause based on filter
    $params = [];
    switch ($filter) {
        case 'daily':
            $sql .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$day, $month, $year];
            break;
        case 'monthly':
            $sql .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$month, $year];
            break;
        case 'yearly':
            $sql .= " WHERE YEAR(waktu) = ?";
            $params = [$year];
            break;
    }
    
    // Execute base query
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $stats['total_reports'] = $row['total_reports'] ?? 0;
            $stats['total_shutdown'] = $row['total_shutdown'] ?? 0;
            $stats['total_active'] = $row['total_active'] ?? 0;
            $stats['avg_shutdown'] = round($row['avg_shutdown'] ?? 0, 1);
        }
        sqlsrv_free_stmt($stmt);
    }
    
    // Get top department
    $sql_dept = "SELECT TOP 1 bagian as departemen, COUNT(*) as total
                 FROM dbo.report_pemadaman";
    
    // Add WHERE clause based on filter
    $params_dept = [];
    switch ($filter) {
        case 'daily':
            $sql_dept .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_dept = [$day, $month, $year];
            break;
        case 'monthly':
            $sql_dept .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_dept = [$month, $year];
            break;
        case 'yearly':
            $sql_dept .= " WHERE YEAR(waktu) = ?";
            $params_dept = [$year];
            break;
    }
    
    $sql_dept .= " GROUP BY bagian ORDER BY total DESC";
    
    $stmt_dept = sqlsrv_query($conn, $sql_dept, $params_dept);
    if ($stmt_dept !== false) {
        $row_dept = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC);
        if ($row_dept) {
            $stats['top_department'] = $row_dept['departemen'] ?? '';
        }
        sqlsrv_free_stmt($stmt_dept);
    }
    
    // Get most active day (only for monthly and yearly filters)
    if ($filter == 'monthly' || $filter == 'yearly') {
        $sql_day = "SELECT TOP 1 DAY(waktu) as day, COUNT(*) as total
                    FROM dbo.report_pemadaman";
        
        $params_day = [];
        if ($filter == 'monthly') {
            $sql_day .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_day = [$month, $year];
        } else {
            $sql_day .= " WHERE YEAR(waktu) = ?";
            $params_day = [$year];
        }
        
        $sql_day .= " GROUP BY DAY(waktu) ORDER BY total DESC";
        
        $stmt_day = sqlsrv_query($conn, $sql_day, $params_day);
        if ($stmt_day !== false) {
            $row_day = sqlsrv_fetch_array($stmt_day, SQLSRV_FETCH_ASSOC);
            if ($row_day) {
                $stats['most_active_day'] = $row_day['day'] ?? '';
            }
            sqlsrv_free_stmt($stmt_day);
        }
    }
    
    return $stats;
}

$summary = getSummaryData($conn, $filter, $month, $year, $day);
$stats = getTimePeriodStats($conn, $filter, $month, $year, $day);

// Get available years for dropdown
$sql_years = "SELECT DISTINCT YEAR(waktu) as year 
              FROM dbo.report_pemadaman 
              ORDER BY YEAR(waktu) DESC";
$stmt_years = sqlsrv_query($conn, $sql_years);
$years = [];
if ($stmt_years !== false) {
    while ($row = sqlsrv_fetch_array($stmt_years, SQLSRV_FETCH_ASSOC)) {
        $years[] = $row['year'];
    }
    sqlsrv_free_stmt($stmt_years);
}

// Get current year if years array is empty
if (empty($years)) {
    $years[] = date('Y');
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --success-color: #2ecc71;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
        --info-color: #1abc9c;
        --light-color: #ecf0f1;
        --dark-color: #34495e;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8f9fa;
    }
    
    .content-wrapper {
        background-color: #f8f9fa;
    }
    
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: none;
        margin-bottom: 20px;
    }
    
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        font-weight: 600;
        border-radius: 10px 10px 0 0 !important;
        padding: 15px 20px;
    }
    
    .header-section {
        margin-bottom: 25px;
        padding: 20px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary-color);
    }
    
    .header-section h3 {
        color: var(--dark-color);
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .header-section p {
        color: var(--secondary-color);
        margin-bottom: 0;
        opacity: 0.8;
    }
    
    /* Stats Card Styles */
    .info-box {
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .info-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .info-box-icon {
        border-radius: 8px 0 0 8px;
        font-size: 1.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .info-box-content {
        padding: 10px 15px;
    }
    
    .info-box-text {
        font-size: 0.9rem;
        color: var(--secondary-color);
        text-transform: uppercase;
        font-weight: 500;
        letter-spacing: 0.5px;
    }
    
    .info-box-number {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark-color);
        margin-top: 5px;
    }
    
    /* Photo Thumbnail */
    .photo-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        cursor: pointer;
        border: 2px solid #e0e6ed;
        border-radius: 6px;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
    }
    
    .photo-thumbnail:hover {
        transform: scale(1.05);
        border-color: var(--primary-color);
    }
    
    .photo-placeholder {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
        color: #6c757d;
        font-size: 0.875rem;
        cursor: default;
    }
    
    /* Modal Styles */
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        border-bottom: 1px solid #e0e6ed;
        background-color: #f8f9fa;
        border-radius: 12px 12px 0 0;
        padding: 15px 20px;
    }
    
    .modal-title {
        font-weight: 600;
        color: var(--dark-color);
    }
    
    .modal-photo {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Department Badge */
    .department-badge {
        font-size: 0.8em;
        padding: 5px 12px;
        border-radius: 20px;
        background-color: var(--primary-color);
        color: white;
        font-weight: 500;
    }
    
    /* Filter Section */
    .filter-section {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .filter-title {
        border-bottom: 1px solid #e0e6ed;
        padding-bottom: 10px;
        margin-bottom: 15px;
        color: var(--dark-color);
        font-weight: 600;
    }
    
    /* Time Period Info */
    .time-period-info {
        background-color: var(--light-color);
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 1rem;
        color: var(--dark-color);
        border-left: 4px solid var(--primary-color);
    }
    
    /* Action Buttons */
    .btn-action {
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    
    .btn-delete {
        background-color: var(--danger-color);
        color: white;
        border: none;
    }
    
    .btn-delete:hover {
        background-color: #c0392b;
        color: white;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .info-box-icon {
            font-size: 1.5rem;
        }
        
        .info-box-number {
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 768px) {
        .header-section {
            padding: 15px;
        }
        
        .photo-thumbnail, .photo-placeholder {
            width: 50px;
            height: 50px;
        }
        
        .table th, .table td {
            padding: 8px 10px;
            font-size: 0.9em;
        }
    }
    
    @media (max-width: 576px) {
        .info-box {
            margin-bottom: 15px;
        }
        
        .info-box-icon {
            font-size: 1.3rem;
        }
        
        .info-box-number {
            font-size: 1.1rem;
        }
        
        .department-badge {
            font-size: 0.7em;
            padding: 3px 8px;
        }
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
        animation: fadeIn 0.4s ease-out forwards;
    }
    
    .dataTables-empty {
        text-align: center !important;
        padding: 50px !important;
    }
</style>

<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Rekapitulasi Laporan Pemadaman</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Rekap Pemadaman</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="filter-section fade-in">
                <h4 class="filter-title"><i class="fas fa-filter mr-2"></i> Filter Laporan</h4>
                <form method="get" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter">Jenis Filter:</label>
                                <select class="form-control" id="filter" name="filter" onchange="updateFilterFields()">
                                    <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>Semua Data</option>
                                    <option value="daily" <?= $filter == 'daily' ? 'selected' : '' ?>>Harian</option>
                                    <option value="monthly" <?= $filter == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                    <option value="yearly" <?= $filter == 'yearly' ? 'selected' : '' ?>>Tahunan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3" id="monthField" style="<?= ($filter == 'daily' || $filter == 'monthly') ? '' : 'display: none;' ?>">
                            <div class="form-group">
                                <label for="month">Bulan:</label>
                                <select class="form-control" id="month" name="month">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= sprintf('%02d', $i) ?>" <?= $i == $month ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3" id="dayField" style="<?= $filter == 'daily' ? '' : 'display: none;' ?>">
                            <div class="form-group">
                                <label for="day">Tanggal:</label>
                                <select class="form-control" id="day" name="day">
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= sprintf('%02d', $i) ?>" <?= $i == $day ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="year">Tahun:</label>
                                <select class="form-control" id="year" name="year">
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-group w-100">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search mr-2"></i> Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Time Period Info -->
                <div class="time-period-info">
                    <i class="fas fa-calendar-alt mr-2"></i> 
                    <?php 
                    switch ($filter) {
                        case 'daily':
                            echo "Menampilkan data untuk tanggal " . $day . " " . date('F', mktime(0, 0, 0, $month, 1)) . " " . $year;
                            break;
                        case 'monthly':
                            echo "Menampilkan data untuk bulan " . date('F', mktime(0, 0, 0, $month, 1)) . " " . $year;
                            break;
                        case 'yearly':
                            echo "Menampilkan data untuk tahun " . $year;
                            break;
                        default:
                            echo "Menampilkan semua data";
                    }
                    ?>
                </div>
            </div>

            <!-- Export Button -->
            <div class="row mb-3 fade-in">
                <div class="col-md-12 text-right">
                    <a href="generate_pdf_pemadaman.php?<?= http_build_query($_GET) ?>" class="btn btn-danger">
                        <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                    </a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row fade-in">
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-file-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Laporan</span>
                            <span class="info-box-number"><?= $stats['total_reports'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-power-off"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Dimatikan</span>
                            <span class="info-box-number"><?= $stats['total_shutdown'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-desktop"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Aktif</span>
                            <span class="info-box-number"><?= $stats['total_active'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-building"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Bagian Teraktif</span>
                            <span class="info-box-number"><?= !empty($stats['top_department']) ? htmlspecialchars($stats['top_department']) : '-' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Summary -->
            <div class="row fade-in">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Rekap per Bagian/Lokasi</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="lokasiTable" class="table table-hover table-sm w-100">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Bagian/Lokasi</th>
                                            <th class="text-center">Jumlah Laporan</th>
                                            <th class="text-center">Total Dimatikan</th>
                                            <th class="text-center">Total Aktif</th>
                                            <th class="text-center">Rata-rata Dimatikan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($summary)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-info-circle mr-2"></i>Belum ada data laporan
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($summary as $index => $dept): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($dept['departemen']) ?></td>
                                                    <td class="text-center"><?= $dept['total_laporan'] ?></td>
                                                    <td class="text-center"><?= $dept['total_dimatikan'] ?></td>
                                                    <td class="text-center"><?= $dept['total_aktif'] ?></td>
                                                    <td class="text-center"><?= round($dept['total_dimatikan'] / ($dept['total_laporan'] > 0 ? $dept['total_laporan'] : 1), 1) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports -->
            <div class="row fade-in">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list mr-2"></i>Detail Laporan Pemadaman</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="reportsTable" class="table table-hover table-sm w-100">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Waktu</th>
                                            <th>Bagian/Lokasi</th>
                                            <th>Pelapor</th>
                                            <th class="text-center">Dimatikan</th>
                                            <th class="text-center">Aktif</th>
                                            <th class="text-center">Foto</th>
                                            <th>Keterangan</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="fas fa-camera mr-2"></i>Foto Laporan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <div id="modalLoading" class="p-5 text-center" style="display: none;">
                    <div class="loading-spinner" style="width: 40px; height: 40px; margin: 0 auto;"></div>
                    <p class="mt-3">Memuat foto...</p>
                </div>
                <img id="modalPhoto" class="modal-photo img-fluid" src="" alt="Foto Laporan" style="display: none;">
                <div id="modalError" class="p-5 text-center" style="display: none;">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5>Foto Tidak Ditemukan</h5>
                    <p class="text-muted">File foto tidak dapat diakses di server</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- CSS Libraries -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<!-- JS Libraries -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
// Helper function to escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

$(document).ready(function() {
    // Initialize DataTables Server-side
    var reportsTable = $('#reportsTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "rekap_pemadaman_serverside.php",
            "type": "POST",
            "data": function(d) {
                d.filter = $('#filter').val();
                d.month = $('#month').val();
                d.year = $('#year').val();
                d.day = $('#day').val();
                d.search_value = d.search.value;
            },
            "error": function(xhr, status, error) {
                console.error('AJAX error', status, error);
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi kesalahan',
                    text: 'Gagal memuat data laporan pemadaman.',
                    timer: 3500,
                    showConfirmButton: false
                });
            }
        },
        "columns": [
            { 
                "data": null, 
                "orderable": false, 
                "searchable": false, 
                "render": function(data, type, row, meta) {
                    return meta.row + 1 + meta.settings._iDisplayStart;
                }
            },
            { "data": "waktu" },
            { 
                "data": "bagian",
                "render": function(data) {
                    return '<span class="department-badge">' + escapeHtml(data) + '</span>';
                }
            },
            { 
                "data": "nama_pelapor",
                "render": function(data) {
                    return escapeHtml(data || '-');
                }
            },
            { 
                "data": "jumlah_dimatikan",
                "className": "text-center",
                "render": function(data) {
                    return '<span class="badge badge-danger p-2">' + data + '</span>';
                }
            },
            { 
                "data": "jumlah_aktif",
                "className": "text-center",
                "render": function(data) {
                    return '<span class="badge badge-success p-2">' + data + '</span>';
                }
            },
            { 
                "data": "foto",
                "orderable": false,
                "searchable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    if (data && row.foto_exists) {
                        var photoUrl = '/gg_app/uploads/pemadaman/' + data;
                        return '<img src="' + photoUrl + '" class="photo-thumbnail" data-toggle="modal" data-target="#photoModal" data-photo="' + photoUrl + '" alt="Foto Laporan" title="Klik untuk melihat">';
                    } else if (data) {
                        return '<div class="photo-placeholder" title="File foto tidak ditemukan"><i class="fas fa-exclamation-triangle"></i></div>';
                    } else {
                        return '<div class="photo-placeholder" title="Tidak ada foto"><i class="fas fa-camera-slash"></i></div>';
                    }
                }
            },
            { 
                "data": "keterangan",
                "render": function(data) {
                    if (data) {
                        var escaped = escapeHtml(data);
                        return '<div class="text-truncate" style="max-width: 300px;" data-toggle="tooltip" title="' + escaped + '">' + escaped + '</div>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { 
                "data": "aksi",
                "orderable": false,
                "searchable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    var fotoName = row.foto || '';
                    return '<button class="btn btn-action btn-delete btn-sm" onclick="confirmDelete(' + data + ', \'' + fotoName + '\')" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
            }
        ],
        "responsive": true,
        "autoWidth": false,
        "ordering": true,
        "order": [[1, 'desc']],
        "pageLength": 10,
        "language": {
            "emptyTable": "Tidak ada data laporan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ laporan",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 laporan",
            "infoFiltered": "(disaring dari _MAX_ total laporan)",
            "lengthMenu": "Tampilkan _MENU_ laporan",
            "loadingRecords": "Memuat...",
            "processing": "Memproses...",
            "search": "Cari:",
            "zeroRecords": "Tidak ditemukan laporan yang sesuai",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        },
        "drawCallback": function(settings) {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
    
    <?php if (!empty($summary)): ?>
    var lokasiTable = $('#lokasiTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "ordering": true,
        "order": [[2, 'desc']],
        "pageLength": 10,
        "language": {
            "emptyTable": "Tidak ada data bagian",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ bagian",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 bagian",
            "infoFiltered": "(disaring dari _MAX_ total bagian)",
            "lengthMenu": "Tampilkan _MENU_ bagian",
            "loadingRecords": "Memuat...",
            "processing": "Memproses...",
            "search": "Cari:",
            "zeroRecords": "Tidak ditemukan bagian yang sesuai",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        }
    });
    <?php endif; ?>

    // Show photo in modal
    $('#photoModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var photoUrl = button.data('photo');
        var modal = $(this);
        var modalPhoto = modal.find('#modalPhoto');
        var modalLoading = modal.find('#modalLoading');
        var modalError = modal.find('#modalError');
        
        // Hide all, show loading
        modalPhoto.hide();
        modalError.hide();
        modalLoading.show();
        
        // Preload image
        var img = new Image();
        img.onload = function() {
            modalPhoto.attr('src', photoUrl);
            modalLoading.hide();
            modalPhoto.show();
            modalError.hide();
        };
        img.onerror = function() {
            modalLoading.hide();
            modalPhoto.hide();
            modalError.show();
            console.log('Foto tidak ditemukan:', photoUrl);
        };
        img.src = photoUrl;
    });
    
    // Reset modal when closed
    $('#photoModal').on('hidden.bs.modal', function() {
        $(this).find('#modalPhoto').attr('src', '').hide();
        $(this).find('#modalLoading').hide();
        $(this).find('#modalError').hide();
    });

    // Handle broken images in table
    $(document).on('error', '.photo-thumbnail', function() {
        const $this = $(this);
        $this.replaceWith(
            '<div class="photo-placeholder" title="File foto tidak ditemukan">' +
            '<i class="fas fa-exclamation-triangle"></i>' +
            '</div>'
        );
    });

    // Show notification
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= addslashes($_SESSION['success']) ?>",
            timer: 3000,
            showConfirmButton: false,
            position: 'top-end',
            toast: true,
            background: '#f8f9fa'
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= addslashes($_SESSION['error']) ?>",
            timer: 3000,
            showConfirmButton: false,
            position: 'top-end',
            toast: true,
            background: '#f8f9fa'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});

// Update filter fields based on selected filter
function updateFilterFields() {
    var filter = document.getElementById('filter').value;
    var monthField = document.getElementById('monthField');
    var dayField = document.getElementById('dayField');
    
    if (filter === 'daily') {
        monthField.style.display = 'block';
        dayField.style.display = 'block';
    } else if (filter === 'monthly') {
        monthField.style.display = 'block';
        dayField.style.display = 'none';
    } else {
        monthField.style.display = 'none';
        dayField.style.display = 'none';
    }
}

// Confirm delete function using AJAX POST to delete_pemadaman.php
function confirmDelete(id, foto) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        html: '<div class="text-center"><i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i><p>Laporan pemadaman akan dihapus secara permanen!</p></div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        focusCancel: true,
        customClass: {
            confirmButton: 'btn btn-danger mr-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete_pemadaman.php',
                type: 'POST',
                data: {
                    id: id,
                    foto: foto
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sukses!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload page to refresh all stats cards and top department rekap
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Gagal menghapus data.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete error', status, error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan saat menghubungi server.'
                    });
                }
            });
        }
    });
}
</script>