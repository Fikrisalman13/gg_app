<?php
// ======================================================
// view_user_internet.php
// View Data User Internet Mikrotik (Read-Only)
// ======================================================
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

// ======================================================
// 1. VALIDASI LOGIN
// ======================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    header("Location: ../../login.php");
    exit;
}

// ======================================================
// 2. KONEKSI & DEPENDENSI
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// 3. AMBIL DATA USER DARI MIKROTIK
// ======================================================
$queueData = null;
$errorMessage = null;

$name = $_GET['name'] ?? '';
$modal = $_GET['modal'] ?? 0; // Check if called from modal

if ($name) {
    try {
        $API = new RouterosAPI();
        if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
            $queues = $API->comm('/queue/simple/print', [
                '?name' => $name
            ]);
            if (!empty($queues)) {
                $queueData = $queues[0];
                // Parse max-limit
                if (isset($queueData['max-limit']) && strpos($queueData['max-limit'], '/') !== false) {
                    [$uploadMax, $downloadMax] = explode('/', $queueData['max-limit']);
                    $queueData['upload_max'] = convertToMikrotikFormat($uploadMax);
                    $queueData['download_max'] = convertToMikrotikFormat($downloadMax);
                }
                // Parse target
                if (isset($queueData['target'])) {
                    $queueData['target_ip'] = explode('/', $queueData['target'])[0];
                }
                // Ambil firewall mode dari address-list berdasarkan target_ip
                $queueData['firewall_mode'] = '-';
                if (!empty($queueData['target_ip'])) {
                    $fwLists = $API->comm('/ip/firewall/address-list/print', [ '?address' => $queueData['target_ip'] ]);
                    if (!empty($fwLists) && !empty($fwLists[0]['list'])) {
                        $queueData['firewall_mode'] = $fwLists[0]['list'];
                    }
                }
            } else {
                $errorMessage = "User dengan nama '{$name}' tidak ditemukan.";
            }
            $API->disconnect();
        } else {
            $errorMessage = 'Gagal koneksi ke Mikrotik';
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = "Parameter nama tidak valid.";
}

// ======================================================
// 4. FUNGSI BANTU (HELPERS)
// ======================================================
function e($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatSpeed($bps)
{
    if (!is_numeric($bps) || $bps <= 0) return '0 Kbps';
    $kb = $bps / 1024;
    if ($kb >= 1024) {
        $mb = $kb / 1024;
        return ($mb >= 1024) ? round($mb/1024, 2).' Gbps' : round($mb, 2).' Mbps';
    }
    return round($kb, 2).' Kbps';
}

// Konversi dari format Mikrotik ke format Mikrotik standar
function convertToMikrotikFormat($speed)
{
    // Jika unlimited, return as-is
    if ($speed === 'unlimited') {
        return $speed;
    }
    
    // Jika sudah dalam format Mikrotik (1M, 2M, dll), return as-is
    if (preg_match('/^\d+[kMG]$/', $speed)) {
        return $speed;
    }
    
    // Jika numeric, konversi ke format Mikrotik
    if (is_numeric($speed)) {
        $bps = (int)$speed;
        
        // Konversi bps ke Kbps dulu
        $kbps = $bps / 1000;
        
        if ($kbps < 1000) {
            return $kbps . 'k';
        }
        
        $mbps = $kbps / 1000;
        
        // Bulatkan ke nilai yang umum
        if ($mbps <= 1.5) {
            return $mbps == 1 ? '1M' : '1.5M';
        }
        if ($mbps <= 2.5) {
            return '2M';
        }
        if ($mbps <= 3.5) {
            return '3M';
        }
        if ($mbps <= 4.5) {
            return '4M';
        }
        if ($mbps <= 5.5) {
            return '5M';
        }
        if ($mbps <= 6.5) {
            return '6M';
        }
        if ($mbps <= 8.5) {
            return '8M';
        }
        if ($mbps <= 12) {
            return $mbps <= 10 ? '10M' : '12M';
        }
        if ($mbps <= 15) {
            return '15M';
        }
        if ($mbps <= 20) {
            return '20M';
        }
        if ($mbps <= 25) {
            return '25M';
        }
        if ($mbps <= 30) {
            return '30M';
        }
        if ($mbps <= 40) {
            return '40M';
        }
        if ($mbps <= 50) {
            return '50M';
        }
        if ($mbps <= 60) {
            return '60M';
        }
        if ($mbps <= 80) {
            return '80M';
        }
        if ($mbps <= 100) {
            return '100M';
        }
        
        // Jika di atas 100Mbps, return dalam Mbps dengan pembulatan
        return round($mbps) . 'M';
    }
    
    return $speed; // Return as-is jika tidak dikenal
}

// ======================================================
// 5. THEME & LAYOUT
// ======================================================
$themeColor = 'primary';
$pageTitle = "View User Internet";

// Ambil data karyawan untuk dropdown (jika diperlukan)
$employeeList = [];
try {
    $sqlEmp = "SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
                FROM m_emp 
                LEFT JOIN m_bag ON m_emp.id_bag = m_bag.id_bag
                LEFT JOIN m_dept ON m_emp.id_dept = m_dept.id_dept
                WHERE m_emp.status_emp = 1
                ORDER BY m_emp.nama_lengkap";
    $stmtEmp = sqlsrv_query($conn, $sqlEmp);
    
    if ($stmtEmp) {
        while ($row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
            $employeeList[] = $row;
        }
        sqlsrv_free_stmt($stmtEmp);
    }
} catch (Exception $e) {
    // Handle error silently
}

// If modal mode, return only the content
if ($modal) {
    ob_start();
}

?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= e($errorMessage) ?>
    </div>
    <div class="text-center mt-3">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
<?php elseif ($queueData): ?>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nama User</label>
                <input type="text" class="form-control" value="<?= e($queueData['name'] ?? '') ?>" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><i class="fas fa-network-wired"></i> Target IP</label>
                <input type="text" class="form-control" value="<?= e($queueData['target_ip'] ?? '') ?>" readonly>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label><i class="fas fa-shield-alt"></i> Firewall Mode</label>
                <input type="text" class="form-control" value="<?= e($queueData['firewall_mode'] ?? '-') ?>" readonly>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><i class="fas fa-upload"></i> Upload Max</label>
                <input type="text" class="form-control" value="<?= e($queueData['upload_max'] ?? '') ?>" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><i class="fas fa-download"></i> Download Max</label>
                <input type="text" class="form-control" value="<?= e($queueData['download_max'] ?? '') ?>" readonly>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-3">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times"></i> Tutup
        </button>
        <a href="edit_user_internet.php?name=<?= urlencode($queueData['name'] ?? '') ?>" class="btn btn-primary" target="_blank">
            <i class="fas fa-edit"></i> Edit
        </a>
    </div>
<?php endif; ?>

<?php
// If modal mode, output buffered content and exit
if ($modal) {
    $content = ob_get_clean();
    echo $content;
    exit;
}

// Full HTML page for direct access
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GG App</title>
    
    <!-- Bootstrap CSS -->
    <link href="/gg_app/plugins/bootstrap-5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="/gg_app/plugins/fontawesome-free-6.4.0/css/all.min.css" rel="stylesheet">
    <!-- AdminLTE -->
    <link href="/gg_app/dist/css/adminlte.min.css" rel="stylesheet">
    
    <style>
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .form-section h5 {
            color: #007bff;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .form-group label {
            font-weight: 500;
            color: #495057;
        }
        .form-control[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="/gg_app/index.php" class="nav-link">Beranda</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">/</span>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="user_internet.php" class="nav-link">User Internet</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">/</span>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">View</span>
            </li>
        </ul>
    </nav>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?= e($pageTitle) ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="user_internet.php">User Internet</a></li>
                            <li class="breadcrumb-item active">View</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-eye"></i> View User Internet</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Output the content that was buffered earlier
                        if ($errorMessage): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= e($errorMessage) ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="user_internet.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        <?php elseif ($queueData): ?>
                            <!-- INFORMASI USER -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Informasi User</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nama User</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['name'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Target IP</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['target_ip'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- LIMIT KECEPATAN -->
                            <div class="form-section">
                                <h5><i class="fas fa-tachometer-alt"></i> Limit Kecepatan</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Upload Max</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['upload_max'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Download Max</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['download_max'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KONFIGURASI TAMBAHAN -->
                            <div class="form-section">
                                <h5><i class="fas fa-cog"></i> Konfigurasi Tambahan</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Queue Type</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['queue'] ?? 'default') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Comment</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['comment'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- INFORMASI SISTEM -->
                            <div class="form-section">
                                <h5><i class="fas fa-info-circle"></i> Informasi Sistem</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Queue ID</label>
                                            <input type="text" class="form-control" value="<?= e($queueData['.id'] ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Bytes In</label>
                                            <input type="text" class="form-control" value="<?= isset($queueData['bytes']) ? formatSpeed($queueData['bytes']) : '0' ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Bytes Out</label>
                                            <input type="text" class="form-control" value="<?= isset($queueData['bytes-out']) ? formatSpeed($queueData['bytes-out']) : '0' ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Packet In</label>
                                            <input type="text" class="form-control" value="<?= number_format($queueData['packets'] ?? 0) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Packet Out</label>
                                            <input type="text" class="form-control" value="<?= number_format($queueData['packets-out'] ?? 0) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Dropped Packets</label>
                                            <input type="text" class="form-control" value="<?= number_format($queueData['dropped'] ?? 0) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TOMBOL AKSI -->
                            <div class="form-section">
                                <div class="text-center">
                                    <a href="user_internet.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                    <a href="edit_user_internet.php?name=<?= urlencode($queueData['name'] ?? '') ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 1.0.0
        </div>
        <strong>Copyright &copy; 2024 GG App.</strong> All rights reserved.
    </footer>
</div>

<!-- jQuery -->
<script src="/gg_app/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="/gg_app/plugins/bootstrap-5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="/gg_app/dist/js/adminlte.min.js"></script>

</body>
</html>
