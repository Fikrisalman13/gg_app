<?php
// ======================================================
// edit_user_internet.php
// Form Edit Data User Internet Mikrotik
// ======================================================
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

// ======================================================
// 1. TIMEZONE
// ======================================================
date_default_timezone_set('Asia/Jakarta');

// ======================================================
// 2. VALIDASI LOGIN
// ======================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

// ======================================================
// 3. KONEKSI & DEPENDENSI
// ======================================================
require_once '../../koneksi.php';

// ======================================================
// 3.1. PERMISSION CHECK
// ======================================================
$groupId = $_SESSION['GroupId'];
$menuId = 140; // Menu ID untuk User Internet

$sql = "SELECT CanView, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canView = $canEdit = $canDelete = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($row) {
    $canView = $row['CanView'] == 1;
    $canEdit = $row['CanEdit'] == 1;
    $canDelete = $row['CanDelete'] == 1;
}
sqlsrv_free_stmt($stmt);

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: user_internet.php');
    exit;
}

session_write_close();

// ======================================================
// 4. KONFIGURASI UMUM
// ======================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ======================================================
// 5. LAYOUT
// ======================================================
// Layout
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Mikrotik Config & API
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// 6. FUNGSI BANTU (HELPERS)
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
// 7. AMBIL DATA USER DARI MIKROTIK
// ======================================================
$queueData = null;
$errorMessage = null;

$name = $_GET['name'] ?? '';

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
                // Ambil firewall mode dari address-list berdasarkan target_ip (sama seperti view_user_internet.php)
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
// 8. AMBIL DATA KARYAWAN UNTUK DROPDOWN
// ======================================================
$employeeList = [];
try {
    $sqlEmp = "SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
               FROM dbo.m_emp 
               LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
               LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
               WHERE m_emp.aktif = 1 
               ORDER BY m_emp.nama_lengkap ASC";
    $stmtEmp = sqlsrv_query($conn, $sqlEmp);
    
    if ($stmtEmp) {
        while ($row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
            $employeeList[] = $row;
        }
        sqlsrv_free_stmt($stmtEmp);
    }
} catch (Exception $e) {
    // Ignore error for employee list
}

?>

<!-- ======================================================
     9. CSS TAMBAHAN
====================================================== -->
<style>
.form-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.form-section h5 {
    color: <?= $themeColor ?>;
    border-bottom: 2px solid #<?= $themeColor ?>;
    padding-bottom: 5px;
    margin-bottom: 15px;
}
.select2-container--bootstrap4 .select2-selection--single {
    height: 38px;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
}
</style>

<!-- ======================================================
     10. HTML STRUKTUR HALAMAN
====================================================== -->
<div class="content-wrapper">
    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Edit User Internet</h1>
                </div>
                <div class="col-md-6">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item">
                            <a href="/gg_app/index.php">Beranda</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="user_internet.php">User Internet</a>
                        </li>
                        <li class="breadcrumb-item active">Edit User</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <!-- CARD HEADER -->
                <div class="card-header bg-<?= e($themeColor) ?> text-white">
                    <h3 class="card-title m-0">Edit Data User Internet</h3>
                    <div class="card-tools">
                        <a href="user_internet.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- CARD BODY -->
                <div class="card-body">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= e($errorMessage) ?>
                        </div>
                    <?php elseif ($queueData): ?>
                        <form method="POST" action="proses_edit_user_internet.php" id="editUserForm">
                            <input type="hidden" name="original_name" value="<?= e($queueData['name']) ?>">
                            
                            <!-- INFORMASI USER -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Informasi User</h5>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Nama User <span class="text-danger">*</span></label>
                                            <div id="inputNamaDropdown">
                                                <select name="name_dropdown" class="form-control select2" id="selectNamaUser">
                                                    <option value="">Pilih Nama User</option>
                                                    <?php
                                                    $currentNameNoDevice = $queueData['name'];
                                                    if (preg_match('/^(.*)\s*\(([^)]+)\)$/', $queueData['name'], $m)) {
                                                        $currentNameNoDevice = trim($m[1]);
                                                    }
                                                    foreach ($employeeList as $emp):
                                                        $optionLabel = trim(($emp['dept'] ? $emp['dept'] : ($emp['bagian'] ?: 'Tidak Ada')) . ' - ' . $emp['nama_lengkap']);
                                                        $selected = ($currentNameNoDevice == $optionLabel) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= htmlspecialchars($optionLabel) ?>" <?= $selected ?>><?= htmlspecialchars($optionLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div id="inputNamaManual" style="display:none;">
                                                <input type="text" name="name_manual" class="form-control" placeholder="Masukkan nama user manual" value="<?= ($currentNameNoDevice && !in_array($currentNameNoDevice, array_map(function($emp){return trim(($emp['dept'] ? $emp['dept'] : ($emp['bagian'] ?: 'Tidak Ada')) . ' - ' . $emp['nama_lengkap']);}, $employeeList))) ? e($currentNameNoDevice) : '' ?>">
                                            </div>
                                            <div class="mt-2">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="name_mode" id="modeDropdown" value="dropdown" checked>
                                                    <label class="form-check-label" for="modeDropdown">Pilih dari daftar</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="name_mode" id="modeManual" value="manual">
                                                    <label class="form-check-label" for="modeManual">Input manual</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Device <span class="text-danger">*</span></label>
                                            <select name="device_type" class="form-control" id="selectDevice" required>
                                                <option value="">Pilih Device</option>
                                                <?php
                                                $deviceList = ['Laptop', 'Hp', 'PC', 'Printer', 'Mesin', 'Tab'];
                                                // Ambil device dari queueData['name'] jika device_type belum ada
                                                $selectedDevice = isset($queueData['device_type']) ? $queueData['device_type'] : '';
                                                if (!$selectedDevice && preg_match('/\(([^)]+)\)$/', $queueData['name'] ?? '', $mdev)) {
                                                    $selectedDevice = trim($mdev[1]);
                                                }
                                                foreach ($deviceList as $dev) {
                                                    $sel = ($selectedDevice == $dev) ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($dev) . '" ' . $sel . '>' . htmlspecialchars($dev) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Target IP <span class="text-danger">*</span></label>
                                            <input type="text" name="target" class="form-control" 
                                                   value="<?= e($queueData['target_ip'] ?? '') ?>" required>
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
                                            <label>Upload Max <span class="text-danger">*</span></label>
                                            <select name="upload_max" class="form-control" required>
                                                <option value="">Pilih Kecepatan</option>
                                                <option value="unlimited" <?= (e($queueData['upload_max'] ?? '') == 'unlimited') ? 'selected' : '' ?>>Unlimited</option>
                                                <option value="64k" <?= (e($queueData['upload_max'] ?? '') == '64k') ? 'selected' : '' ?>>64 Kbps</option>
                                                <option value="128k" <?= (e($queueData['upload_max'] ?? '') == '128k') ? 'selected' : '' ?>>128 Kbps</option>
                                                <option value="256k" <?= (e($queueData['upload_max'] ?? '') == '256k') ? 'selected' : '' ?>>256 Kbps</option>
                                                <option value="384k" <?= (e($queueData['upload_max'] ?? '') == '384k') ? 'selected' : '' ?>>384 Kbps</option>
                                                <option value="512k" <?= (e($queueData['upload_max'] ?? '') == '512k') ? 'selected' : '' ?>>512 Kbps</option>
                                                <option value="768k" <?= (e($queueData['upload_max'] ?? '') == '768k') ? 'selected' : '' ?>>768 Kbps</option>
                                                <option value="1M" <?= (e($queueData['upload_max'] ?? '') == '1M') ? 'selected' : '' ?>>1 Mbps</option>
                                                <option value="1.5M" <?= (e($queueData['upload_max'] ?? '') == '1.5M') ? 'selected' : '' ?>>1.5 Mbps</option>
                                                <option value="2M" <?= (e($queueData['upload_max'] ?? '') == '2M') ? 'selected' : '' ?>>2 Mbps</option>
                                                <option value="3M" <?= (e($queueData['upload_max'] ?? '') == '3M') ? 'selected' : '' ?>>3 Mbps</option>
                                                <option value="4M" <?= (e($queueData['upload_max'] ?? '') == '4M') ? 'selected' : '' ?>>4 Mbps</option>
                                                <option value="5M" <?= (e($queueData['upload_max'] ?? '') == '5M') ? 'selected' : '' ?>>5 Mbps</option>
                                                <option value="6M" <?= (e($queueData['upload_max'] ?? '') == '6M') ? 'selected' : '' ?>>6 Mbps</option>
                                                <option value="8M" <?= (e($queueData['upload_max'] ?? '') == '8M') ? 'selected' : '' ?>>8 Mbps</option>
                                                <option value="10M" <?= (e($queueData['upload_max'] ?? '') == '10M') ? 'selected' : '' ?>>10 Mbps</option>
                                                
                                            </select>
                                            
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Download Max <span class="text-danger">*</span></label>
                                            <select name="download_max" class="form-control" required>
                                                <option value="">Pilih Kecepatan</option>
                                                <option value="unlimited" <?= (e($queueData['download_max'] ?? '') == 'unlimited') ? 'selected' : '' ?>>Unlimited</option>
                                                <option value="64k" <?= (e($queueData['download_max'] ?? '') == '64k') ? 'selected' : '' ?>>64 Kbps</option>
                                                <option value="128k" <?= (e($queueData['download_max'] ?? '') == '128k') ? 'selected' : '' ?>>128 Kbps</option>
                                                <option value="256k" <?= (e($queueData['download_max'] ?? '') == '256k') ? 'selected' : '' ?>>256 Kbps</option>
                                                <option value="384k" <?= (e($queueData['download_max'] ?? '') == '384k') ? 'selected' : '' ?>>384 Kbps</option>
                                                <option value="512k" <?= (e($queueData['download_max'] ?? '') == '512k') ? 'selected' : '' ?>>512 Kbps</option>
                                                <option value="768k" <?= (e($queueData['download_max'] ?? '') == '768k') ? 'selected' : '' ?>>768 Kbps</option>
                                                <option value="1M" <?= (e($queueData['download_max'] ?? '') == '1M') ? 'selected' : '' ?>>1 Mbps</option>
                                                <option value="1.5M" <?= (e($queueData['download_max'] ?? '') == '1.5M') ? 'selected' : '' ?>>1.5 Mbps</option>
                                                <option value="2M" <?= (e($queueData['download_max'] ?? '') == '2M') ? 'selected' : '' ?>>2 Mbps</option>
                                                <option value="3M" <?= (e($queueData['download_max'] ?? '') == '3M') ? 'selected' : '' ?>>3 Mbps</option>
                                                <option value="4M" <?= (e($queueData['download_max'] ?? '') == '4M') ? 'selected' : '' ?>>4 Mbps</option>
                                                <option value="5M" <?= (e($queueData['download_max'] ?? '') == '5M') ? 'selected' : '' ?>>5 Mbps</option>
                                                <option value="6M" <?= (e($queueData['download_max'] ?? '') == '6M') ? 'selected' : '' ?>>6 Mbps</option>
                                                <option value="8M" <?= (e($queueData['download_max'] ?? '') == '8M') ? 'selected' : '' ?>>8 Mbps</option>
                                                <option value="10M" <?= (e($queueData['download_max'] ?? '') == '10M') ? 'selected' : '' ?>>10 Mbps</option>
                                             
                                            </select>
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FIREWALL MODE -->
                            <div class="form-section">
                                <h5><i class="fas fa-shield-alt"></i> Firewall Mode</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Firewall Mode <span class="text-danger">*</span></label>
                                            <?php
                                                $firewallList = [
                                                    'Allow-Internet', 'Block-Internet', 'LAN', 'Mailserver',
                                                    'allow-access-router', 'allow-ftp-ssh-telnet', 'allow-smb-rdp',
                                                    'dns-allow', 'dst-allow-spesifik', 'safe-access',
                                                    'src-allow-spesifik', 'sumber-flooding', 'trusted-server'
                                                ];
                                                // Ambil firewall_mode dari queueData (hasil address-list Mikrotik)
                                                $selectedFirewall = isset($queueData['firewall_mode']) ? $queueData['firewall_mode'] : '';
                                            ?>
                                            <select name="firewall_mode" class="form-control" required>
                                                <option value="">Pilih Firewall Mode</option>
                                                <?php foreach ($firewallList as $fw): ?>
                                                    <option value="<?= htmlspecialchars($fw) ?>" <?= ($selectedFirewall == $fw) ? 'selected' : '' ?>><?= htmlspecialchars($fw) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BUTTONS -->
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-<?= $themeColor ?>">
                                        <i class="fas fa-save"></i> Simpan Perubahan
                                    </button>
                                    <a href="user_internet.php" class="btn btn-secondary ml-2">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ======================================================
     11. FOOTER
====================================================== -->
<?php include '../../includes/footer.php'; ?>

<!-- ======================================================
     12. JAVASCRIPT LIBRARIES
====================================================== -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<link href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css" rel="stylesheet" />
<link href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" />
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<!-- ======================================================
     13. JAVASCRIPT CUSTOM
====================================================== -->
<script>
$(document).ready(function() {
    // Inisialisasi Select2 pada dropdown Nama User
    $('#selectNamaUser').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Pilih Nama User',
        allowClear: true
    });
    // Toggle input mode Nama User
    function toggleNamaInput() {
        if ($('input[name="name_mode"]:checked').val() === 'dropdown') {
            $('#inputNamaDropdown').show();
            $('#inputNamaManual').hide();
        } else {
            $('#inputNamaDropdown').hide();
            $('#inputNamaManual').show();
        }
    }
    $('input[name="name_mode"]').on('change', toggleNamaInput);
    toggleNamaInput();
    // Pastikan salah satu input required sesuai mode
    $('#editUserForm').on('submit', function() {
        if ($('input[name="name_mode"]:checked').val() === 'dropdown') {
            $('#selectNamaUser').attr('required', true);
            $('input[name="name_manual"]').removeAttr('required');
        } else {
            $('#selectNamaUser').removeAttr('required');
            $('input[name="name_manual"]').attr('required', true);
        }
    });
    const showAlert = (type, msg) =>
        Swal.fire({ icon: type, title: msg, timer: 2500, showConfirmButton: false });

    // Form validation
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const formData = new FormData(form);
        
        // Show loading
        Swal.fire({
            title: 'Menyimpan...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit form
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data === 'SUCCESS') {
                showAlert('success', 'Data user berhasil diperbarui!');
                setTimeout(() => {
                    window.location.href = 'user_internet.php';
                }, 1500);
            } else {
                showAlert('error', 'Gagal menyimpan: ' + data);
            }
        })
        .catch(error => {
            showAlert('error', 'Terjadi kesalahan: ' + error.message);
        });
    });
});
</script>

</body>
</html>
