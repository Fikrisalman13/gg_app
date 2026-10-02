<?php
session_start();
require_once '../../koneksi.php';
require_once '../../includes/app_version.php';

date_default_timezone_set('Asia/Jakarta');

// Cek login
if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Mendapatkan Informasi Sistem
$phpVersion = PHP_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'];
$dbVersion = "Unknown";
$serverName = "Unknown";

$server_info = sqlsrv_server_info($conn);
if ($server_info) {
    $dbVersion = $server_info['SQLServerVersion'] ?? 'Unknown';
    $serverName = $server_info['ServerName'] ?? 'Local/Remote Server';
}

$appDescription = "Sistem manajemen terpadu (Integrated Management System) yang dirancang khusus untuk mengoptimalkan seluruh alur operasional perusahaan secara efisien, transparan, dan real-time.";
$versionDetails = "Versi ini mencakup optimasi performa pada modul Support, pembaruan keamanan sistem, dan peningkatan antarmuka pengguna untuk pengalaman yang lebih modern.";

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
    .about-card {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        transition: transform 0.3s ease;
    }
      <?php 
    // Map AdminLTE themes to specific gradient colors
    $themeGradients = [
        'primary'   => '#007bff',
        'success'   => '#28a745',
        'info'      => '#17a2b8',
        'warning'   => '#ffc107',
        'danger'    => '#dc3545',
        'indigo'    => '#6610f2',
        'navy'      => '#001f3f',
        'purple'    => '#605ca8',
        'fuchsia'   => '#f012be',
        'pink'      => '#e83e8c',
        'maroon'    => '#d81b60',
        'orange'    => '#fd7e14',
        'lime'      => '#01ff70',
        'teal'      => '#39cccc',
        'olive'     => '#3d9970',
        'cyan'      => '#17a2b8', // Defaulting to info if not exact
        'dark'      => '#343a40'
    ];
    $activeColor = $themeGradients[$themeColor] ?? '#007bff';
    ?>
    .about-header {
        background: linear-gradient(135deg, <?= $activeColor ?> 0%, #343a40 100%);
        padding: 40px 20px;
        text-align: center;
        color: white;
    }
    .app-logo-container {
        width: 100px;
        height: 100px;
        background: white;
        border-radius: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .app-logo-preview {
        width: 70px;
        height: auto;
    }
    .version-badge {
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(5px);
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 0.9rem;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .info-item {
        padding: 15px;
        border-bottom: 1px solid #f4f4f4;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .info-item:last-child {
        border-bottom: none;
    }
    .info-label {
        color: #6c757d;
        font-weight: 500;
    }
    .info-value {
        color: #343a40;
        font-weight: 600;
        text-align: right;
    }
    .license-box {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        font-size: 0.85rem;
        line-height: 1.6;
        color: #495057;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">About System</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <!-- App Info Card -->
                    <div class="card about-card elevation-3 mb-4">
                        <div class="about-header">
                            <div class="app-logo-container border">
                                <img src="/gg_app/dist/img/sumlogo.png" alt="App Logo" class="app-logo-preview">
                            </div>
                            <h3 class="font-weight-bold mb-1">Support App</h3>
                            <div class="d-inline-block mt-2">
                                <span class="version-badge">Version <?= APP_VERSION ?></span>
                            </div>
                            <p class="text-white-50 mt-3 px-4 text-sm">
                                <?= $appDescription ?>
                            </p>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-light">
                                <h6 class="font-weight-bold text-xs text-uppercase text-secondary mb-2">Release Notes</h6>
                                <p class="text-xs text-muted mb-0">
                                    <?= $versionDetails ?>
                                </p>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-code mr-2"></i> Environment</span>
                                <span class="info-value">Production</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar-alt mr-2"></i> Last Update</span>
                                <span class="info-value"><?= date("d M Y", filemtime(__DIR__ . '/../../includes/app_version.php')) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-building mr-2"></i> Organization</span>
                                <span class="info-value">SUM IT Department</span>
                            </div>
                        </div>
                    </div>

                    <!-- System Info Card -->
                    <div class="card about-card elevation-2">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-server mr-2 text-primary"></i> System Information</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="info-item">
                                <span class="info-label">PHP Version</span>
                                <span class="info-badge badge badge-info px-2"><?= $phpVersion ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">SQL Server Version</span>
                                <span class="info-value text-sm"><?= $dbVersion ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Web Server</span>
                                <span class="info-value text-sm"><?= $serverSoftware ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Database Host</span>
                                <span class="info-value text-sm font-italic"><?= $serverName ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <!-- License Card -->
                    <div class="card about-card elevation-2 mb-4">
                        <div class="card-header bg-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-file-contract mr-2 text-warning"></i> License & User Agreement</h3>
                        </div>
                        <div class="card-body">
                            <div class="license-box">
                                <p><strong>End User License Agreement (EULA)</strong></p>
                                <p>This application is proprietary software developed and owned by the SUM IT Department. By using this software, you agree to the following terms:</p>
                                <ul>
                                    <li>Unauthorized copying, distribution, or modification of this software is strictly prohibited.</li>
                                    <li>This software is provided "as is" without warranty of any kind, express or implied.</li>
                                    <li>Ownership of all data entered into the system remains with the organization.</li>
                                    <li>Access to this system is restricted to authorized personnel only.</li>
                                </ul>
                                <p>Copyright &copy; <?= date('Y') ?> SUM Support App. All rights reserved.</p>
                            </div>
                            <div class="mt-3 text-muted text-sm">
                                <p><i class="fas fa-info-circle mr-1"></i> This application uses open-source components including AdminLTE, Bootstrap, and jQuery, each governed by their respective licenses (MIT/Apache 2.0).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Support Card -->
                    <div class="card about-card elevation-2">
                        <div class="card-header bg-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-headset mr-2 text-success"></i> Technical Support</h3>
                        </div>
                        <div class="card-body text-center p-4">
                            <p class="mb-4">Need help or found a bug? Contact our IT Support team.</p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 bg-light rounded shadow-sm">
                                        <i class="fas fa-envelope fa-2x text-primary mb-2"></i>
                                        <h6 class="font-weight-bold">Email Support</h6>
                                        <p class="text-sm mb-0">it.support@sum.web.id</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 bg-light rounded shadow-sm">
                                        <i class="fab fa-whatsapp fa-2x text-success mb-2"></i>
                                        <h6 class="font-weight-bold">WhatsApp</h6>
                                        <p class="text-sm mb-0">Internal IT Extension</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
