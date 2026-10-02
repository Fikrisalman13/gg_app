<?php
// ======================================================
// Add User - LENGKAP (ARP + DHCP + Firewall + Queues + Karyawan + Select2)
// ======================================================
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_start();
date_default_timezone_set('Asia/Jakarta');

// ======================================================
// Validasi login
// ======================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ======================================================
// Load environment
// ======================================================
require_once '../../../koneksi.php';              // SQL Server
include '../../../includes/header.php';
include '../../../includes/sidebar.php';

require_once '../../../config.php';               // Mikrotik
require_once '../../../routeros_api.class.php';


// ======================================================
// Helper
// ======================================================
function e($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// ======================================================
// 1. Ambil DATA KARYAWAN untuk dropdown Select2
// ======================================================
$employeeList = [];

$sqlEmp = "SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.aktif = 1 
ORDER BY m_emp.dept ASC, m_emp.nama_lengkap ASC";

$stmtEmp = sqlsrv_query($conn, $sqlEmp);

if ($stmtEmp) {
    while ($row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
        $employeeList[] = $row;
    }
}


// ======================================================
// 2. Ambil ARP List + DHCP List + Firewall List
// ======================================================
$arpList = [];
$fwLists = [];
$dhcpList = [];

try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        // ---- ARP LIST (tampilkan ARP yang belum diberi comment dan bersifat dynamic/DHCP) ----
        $arpAll = $API->comm('/ip/arp/print');

        // Filter: tidak ada comment/tags dan merupakan entry dynamic atau ada pada DHCP leases
        foreach ($arpAll as $arp) {
            $comment = $arp['comment'] ?? '';
            $address = $arp['address'] ?? '';
            $dynamic = $arp['dynamic'] ?? null;
            $flags = $arp['flags'] ?? '';

            // hanya pertimbangkan jika ada alamat IP
            if (empty($comment) && $address) {
                $isDhcpLike = false;

                // 1) routeros kadang mengembalikan key 'dynamic' untuk ARP dynamic
                if (!empty($dynamic) && strtolower($dynamic) !== 'false') {
                    $isDhcpLike = true;
                }

                // 2) jika IP ada pada list DHCP leases, anggap juga DHCP
                if (isset($dhcpList[$address])) {
                    $isDhcpLike = true;
                }

                // 3) beberapa perangkat menandai entry dengan flags seperti 'D' (dynamic) atau 'C'
                if (is_string($flags) && (strpos($flags, 'D') !== false || strpos($flags, 'C') !== false)) {
                    $isDhcpLike = true;
                }

                if ($isDhcpLike) {
                    $arpList[] = $arp;
                }
            }
        }

        // ---- DHCP LEASE LIST ----
        $leases = $API->comm('/ip/dhcp-server/lease/print');
        foreach ($leases as $d) {
            if (!empty($d['address'])) {
                $dhcpList[$d['address']] = $d;
            }
        }

        // ---- FIREWALL LIST ----
        $fwData  = $API->comm('/ip/firewall/address-list/print');
        foreach ($fwData as $fw) {
            if (!empty($fw['list'])) {
                $fwLists[] = $fw['list'];
            }
        }

        $fwLists = array_unique($fwLists);
        sort($fwLists);

        $API->disconnect();
    }
} catch (Exception $e) {}


// ======================================================

// ======================================================
// BANDWIDTH OPTIONS (Sesuai Mikrotik)
// ======================================================
$bwList = [
    "unlimited" => "Unlimited",
    "64k"  => "64 kbps",
    "128k" => "128 kbps",
    "256k" => "256 kbps",
    "384k" => "384 kbps",
    "512k" => "512 kbps",
    "768k" => "768 kbps",
    "1M" => "1 Mbps",
    "2M" => "2 Mbps",
    "4M" => "4 Mbps",
    "5M" => "5 Mbps",
    "10M" => "10 Mbps",
    "20M" => "20 Mbps",
    "50M" => "50 Mbps",
    "100M" => "100 Mbps"
];

// ======================================================
// DEVICE TYPE OPTIONS
// ======================================================
$deviceTypes = [
    "PC" => "PC",
    "Laptop" => "Laptop",
    "Tab" => "Tab",
    "HP" => "HP",
    "Printer" => "Printer",
    "Server" => "Server",
    "CCTV" => "CCTV"
];
?>

<!-- Select2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap.min.css">

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Pendaftaran User Jaringan</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">

                <div class="card-header bg-<?= e($themeColor) ?> text-white">
                    <h3 class="card-title">Pendaftaran User Baru Mikrotik</h3>
                </div>

                <div class="card-body">

                    <!-- NOTIFIKASI -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= e($_SESSION['error']) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form method="POST" action="proses_add_user.php" id="formAddUser">
                        <style>
                            /* Reuse add_emp visual style */
                            .form-section { margin-bottom: 20px; padding: 12px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; }
                            .form-section h5 { margin-bottom: 12px; color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
                            .required-label { font-weight: bold; color: #d9534f; }
                        </style>

                        <div class="row">
                            <div class="col-lg-8">

                                <!-- SECTION 1: EMPLOYEE (Nama Karyawan) -->
                                <div class="form-section">
                                    <h5><i class="fas fa-user"></i> 1. Nama Karyawan</h5>
                                    <div class="form-group">
                                        <label for="employee_select" class="font-weight-bold">Nama Karyawan</label>
                                        <select name="employee_select" id="employee_select" class="form-control select2" data-placeholder="Cari nama karyawan..." required>
                                            <option value=""></option>
                                        </select>
                                        
                                    </div>
                                </div>

                                <!-- SECTION 2: DEVICE TYPE -->
                                <div class="form-section">
                                    <h5><i class="fas fa-laptop"></i> 2. Jenis Device</h5>
                                    <div class="form-group">
                                        <label for="device_type" class="font-weight-bold">Jenis Device</label>
                                        <select name="device_type" id="device_type" class="form-control" required>
                                            <option value="">Pilih Jenis Device...</option>
                                            <?php foreach ($deviceTypes as $key => $val): ?>
                                                <option value="<?= e($key) ?>"><?= e($val) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- SECTION 3: ARP LIST -->
                                <div class="form-section">
                                    <h5><i class="fas fa-network-wired"></i> 3. Pilih IP dari ARP List</h5>
                                    <div class="form-group">
                                        <label for="arp_id">Pilih IP</label>
                                        <select name="arp_id" id="arp_id" class="form-control select2" data-placeholder="Cari IP / MAC / Hostname..." required>
                                            <option value=""></option>
                                            <?php foreach ($arpList as $a): ?>
                                                <?php
                                                $id = $a['.id'] ?? ($a['id'] ?? null);
                                                if (!$id) continue;
                                                $ip   = $a['address'] ?? '-';
                                                $mac  = $a['mac-address'] ?? 'Unknown';
                                                $comm = $a['comment'] ?? '';
                                                $iface = $a['interface'] ?? '';
                                                $status = $a['status'] ?? '';
                                                $hostname = $dhcpList[$ip]['host-name'] ?? '';
                                                $label = "$ip - $mac";
                                                if ($comm)         $label .= " ($comm)";
                                                elseif ($hostname) $label .= " ($hostname)";
                                                else               $label .= " ($status)";
                                                $search = strtolower("$ip $mac $comm $hostname $iface $status");
                                                ?>
                                                <option value="<?= e($id) ?>" data-search="<?= e($search) ?>" data-ip="<?= e($ip) ?>"><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                    </div>
                                    <div class="form-group d-flex align-items-center">
                                        <!-- Tombol Make Static (ARP) dihapus, proses otomatis saat submit -->
                                        <button type="button" class="btn btn-outline-secondary btn-sm ml-2" id="btnRefreshArp" title="Reload ARP List"><i class="fas fa-sync"></i></button>
                                        
                                    </div>
                                </div>

                                <!-- SECTION 4: DHCP LEASES -->
                                <div class="form-section">
                                    <h5><i class="fas fa-server"></i> 4. DHCP Server Leases</h5>
                                    <div class="form-group">
                                            <label for="dhcp_lease_id">Pilih IP</label>
                                            <select name="dhcp_lease_id" id="dhcp_lease_id" class="form-control select2" data-placeholder="Cari dari DHCP Leases...">
                                                <option value=""></option>
                                            </select>
                                        </div>

                                        <!-- Komentar DHCP dihapus: gunakan Preview Comment otomatis -->

                                        <div class="form-group d-flex align-items-center">
                                            <!-- Tombol Make Static (DHCP Leases) dihapus, proses otomatis saat submit -->
                                            <button type="button" class="btn btn-outline-secondary btn-sm ml-2" id="btnRefreshDhcp" title="Refresh DHCP List"><i class="fas fa-sync"></i></button>
                                            
                                        </div>
                                </div>

                                <!-- SECTION 5: FIREWALL -->
                                <div class="form-section">
                                    <h5><i class="fas fa-shield-alt"></i> 5. Firewall Address List</h5>
                                    <div class="form-group">
                                        <label for="fw_rule">Pilih Firewall List</label>
                                        <select name="fw_rule" id="fw_rule" class="form-control select2">
                                            <option value="">Pilih Firewall List...</option>
                                            <?php foreach ($fwLists as $fw): ?>
                                                <option value="<?= e($fw) ?>"><?= e($fw) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        
                                    </div>
                                </div>

                                <!-- SECTION 6: BANDWIDTH -->
                                <div class="form-section">
                                    <h5><i class="fas fa-sitemap"></i> 6. Bandwidth Limit (Queue)</h5>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="bw_up">Max Upload Limit</label>
                                            <select name="bw_up" id="bw_up" class="form-control select2" required>
                                                <option value="">Pilih Limit Upload...</option>
                                                <?php foreach ($bwList as $key => $val): ?>
                                                    <option value="<?= e($key) ?>"><?= e($val) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="bw_down">Max Download Limit</label>
                                            <select name="bw_down" id="bw_down" class="form-control select2" required>
                                                <option value="">Pilih Limit Download...</option>
                                                <?php foreach ($bwList as $key => $val): ?>
                                                    <option value="<?= e($key) ?>"><?= e($val) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                </div>

                            </div>

                            <div class="col-lg-4">
                                <!-- PREVIEW -->
                                <div class="form-section">
                                    <h5><i class="fas fa-eye"></i> Preview</h5>
                                    <div class="form-group">
                                        <label class="font-weight-bold">Comment Format</label>
                                        <div class="alert alert-light border"><strong id="commentPreview">Departemen - Nama (Device Type)</strong></div>
                                    </div>
                                </div>

                                <!-- TOMBOL SUBMIT -->
                                <div class="form-section">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block" id="btnSubmit"><i class="fas fa-plus-circle"></i>Tambahkan User</button>
                                        <a href="../user_internet.php" class="btn btn-secondary btn-block mt-2"><i class="fas fa-times-circle"></i> Batal</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </section>

</div>

<!-- Select2 JS -->
<script src="/gg_app/plugins/js/jquery.min.js"></script>
<script src="/gg_app/plugins/js/select2.full.min.js"></script>

<script>
$(document).ready(function () {

    // ========================================
    // INITIALIZE SELECT2
    // ========================================
    
    // Custom matcher untuk ARP
    function customMatcher(params, data) {
        if ($.trim(params.term) === '') return data;
        let term = params.term.toLowerCase();
        let search = (data.element.dataset.search || '').toLowerCase();
        return search.includes(term) ? data : null;
    }

    // ARP Select2
    $('#arp_id').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Cari IP / MAC / Hostname...',
        allowClear: true,
        matcher: customMatcher
    });

    // Employee Select2
    $('#employee_select').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Cari nama karyawan...',
        allowClear: true
    });

    // Populate employee select from centralized emp source (emp.php)
    $.ajax({
        url: 'get_employees_select.php',
        method: 'GET',
        dataType: 'html',
        success: function(html) {
            $('#employee_select').append(html).trigger('change');
        }
    });

    // Firewall Select2
    $('#fw_rule').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Pilih Firewall List...'
    });

    // Bandwidth Select2
    $('#bw_up, #bw_down').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Device Type
    $('#device_type').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // DHCP Lease
    $('#dhcp_lease_id').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Cari dari DHCP Leases...'
    });


    // ========================================
    // DHCP Leases mandiri: load semua leases dan tombol refresh
    // ========================================
    function loadDhcpLeases() {
        $.ajax({
            url: 'get_dhcp_leases.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                let html = '<option value="">Pilih DHCP Lease...</option>';
                if (response.leases && response.leases.length > 0) {
                    response.leases.forEach(function(lease) {
                        let host = lease.hostname || lease.comment || 'No Hostname';
                        html += '<option value="' + lease.id + '" data-lease-id="' + lease.id + '">' +
                                lease.address + ' - ' + host + '</option>';
                    });
                }
                $('#dhcp_lease_id').html(html).trigger('change');
            },
            error: function() {
                $('#dhcp_lease_id').html('<option value="">Gagal memuat DHCP leases</option>').trigger('change');
            }
        });
    }

    // Panggil saat dokumen siap
    loadDhcpLeases();

    // Tombol refresh
    $('#btnRefreshDhcp').click(function() {
        loadDhcpLeases();
    });


    // ========================================
    // HANDLER: UPDATE PREVIEW COMMENT
    // ========================================
    function updateCommentPreview() {
        let selected = $('#employee_select').find('option:selected');
        let deptBagian = selected.data('dept') || 'Departemen';
        let nama = selected.data('name') || selected.text() || 'Nama';
        let device = $('#device_type').val() || 'Device Type';

        let comment = deptBagian + ' - ' + nama;
        if (device) {
            comment += ' (' + device + ')';
        }

        $('#commentPreview').text(comment);
        $('#queueNamePreview').text(comment);
    }

    $('#employee_select, #device_type').change(function() {
        updateCommentPreview();
    });

    // Tombol Make Static (ARP) dihapus, proses otomatis saat submit


    // Tombol Make Static (DHCP Leases) dihapus, proses otomatis saat submit


    // ========================================
    // BUTTON: ADD FIREWALL
    // ========================================
    $('#btnAddFirewall').click(function(e) {
        e.preventDefault();
        
        let arpId = $('#arp_id').val();
        let selectedOption = $('#arp_id').find('option:selected');
        let ip = selectedOption.data('ip');
        let fwList = $('#fw_rule').val();
        let comment = $('#commentPreview').text();

        if (!arpId || !ip) {
            alert('Silakan pilih IP dari ARP List terlebih dahulu!');
            return;
        }

        if (!fwList) {
            alert('Silakan pilih Firewall List terlebih dahulu!');
            return;
        }

        $.ajax({
            url: 'add_firewall.php',
            method: 'POST',
            data: {
                ip: ip,
                fw_list: fwList,
                comment: comment
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('IP ' + ip + ' berhasil ditambahkan ke Firewall List ' + fwList);
                    $('#btnAddFirewall').prop('disabled', true).text('✓ Firewall (Done)');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Gagal menambahkan ke Firewall');
            }
        });
    });


    // ========================================
    // FORM SUBMIT
    // ========================================
    $('#formAddUser').submit(function(e) {
        e.preventDefault();

        // Validasi minimal
        if (!$('#arp_id').val()) {
            alert('Silakan pilih IP dari ARP List!');
            return false;
        }

        if (!$('#employee_select').val()) {
            alert('Silakan pilih nama karyawan!');
            return false;
        }

        if (!$('#device_type').val()) {
            alert('Silakan pilih jenis device!');
            return false;
        }

        if (!$('#bw_up').val() || !$('#bw_down').val()) {
            alert('Silakan pilih bandwidth limit!');
            return false;
        }

        // Submit form
        this.submit();
    });

});
</script>

<?php include '../../../includes/footer.php'; ?>
