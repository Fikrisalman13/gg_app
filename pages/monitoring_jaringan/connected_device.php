<?php
// ======================================================
// connected_device.php
// Halaman untuk menampilkan perangkat yang terhubung ke Mikrotik
// ======================================================
// ======================================================
// 1. SESSION & INIT
// ======================================================
session_start();
ob_start();

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

// ======================================================
// 2. REQUIRE FILES
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

include '../../includes/header.php';
include '../../includes/sidebar.php';

// ======================================================
// 3. HELPERS FOR NORMALIZATION
// ======================================================
function normalizeDevice($name)
{
    $n = strtolower($name ?? '');

    if (str_contains($n, 'hp') || str_contains($n, 'android') || str_contains($n, 'iphone'))
        return "HP";

    if (str_contains($n, 'laptop') || str_contains($n, 'notebook'))
        return "Laptop";

    if (str_contains($n, 'pc') || str_contains($n, 'desktop'))
        return "PC";

    if (str_contains($n, 'tablet') || str_contains($n, 'ipad') || str_contains($n, 'tab'))
        return "Tablet";

    if (str_contains($n, 'server'))
        return "Server";

    if (str_contains($n, 'printer') || str_contains($n, 'print'))
        return "Printer";

    if (str_contains($n, 'mesin') || str_contains($n, 'scan') || str_contains($n, 'absen'))
        return "Mesin";

    if (str_contains($n, 'cctv') || str_contains($n, 'nvr') || str_contains($n, 'dvr'))
        return "CCTV";

    return "Unknown";
}

function cleanName($name)
{
    return trim(preg_replace('/port[_ ]?\d+/i', '', $name));
}

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ======================================================
// 4. FETCH QUEUES FROM MIKROTIK (REAL TRAFFIC) - For Summary Display
// ======================================================
$leases = [];
$errorMessage = null;

try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        $rows = $API->comm("/queue/simple/print", ["stats" => ""]);
        $API->disconnect();

        foreach ($rows as $r) {

            $rawName = $r['name'] ?? '-';
            $name = cleanName($rawName);

            $device = normalizeDevice($rawName);

            $target = explode("/", $r['target'] ?? "-")[0];

            // RATE format = "download/upload" (BYTE PER SECOND)
            $rate = $r['rate'] ?? "0/0";
            list($downRateBps, $upRateBps) = explode("/", $rate);

            // Convert to bps
            $downloadAvg = intval($downRateBps) * 8;
            $uploadAvg   = intval($upRateBps) * 8;

            $status = ($downloadAvg > 0 || $uploadAvg > 0) ? "Online" : "Offline";

            $leases[] = [
                'name' => $name,
                'device' => $device,
                'ip' => $target,
                'uploadAvg' => $uploadAvg,
                'downloadAvg' => $downloadAvg,
                'status' => $status
            ];
        }
    } else {
        $errorMessage = "Tidak dapat terhubung ke Mikrotik.";
    }
} catch (Exception $ex) {
    $errorMessage = "Error: " . $ex->getMessage();
}

// ======================================================
// 5. SUMMARY COUNT (untuk display di halaman)
// ======================================================
$deviceCounts = [];
$statusOnline = 0;
$statusOffline = 0;

foreach ($leases as $l) {
    $deviceCounts[$l['device']] = ($deviceCounts[$l['device']] ?? 0) + 1;
    if ($l['status'] === 'Online') $statusOnline++;
    else $statusOffline++;
}
ksort($deviceCounts);

?>
<!-- =============================== -->
<!-- CSS -->
<!-- =============================== -->
<style>
.filter-section {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.status-online, .status-offline {
    display: inline-block;
    min-width: 70px;
    text-align: center;
    padding: 4px 8px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 4px;
    margin: 0 auto;
}
.status-online {
    background: #28a745;
    color: #fff;
}
.status-offline {
    background: #dc3545;
    color: #fff;
}
.badge-device { 
    padding: 4px 8px; 
    background: #e7e9ec; 
    border-radius: 12px; 
    font-weight: 600; 
    font-size: 12px; 
    margin-right: 5px;
    display: inline-block;
}
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.loading-spinner {
    color: white;
    font-size: 2rem;
}
.auto-refresh-info {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
}
</style>

<div class="content-wrapper">

    <!-- ============================= -->
    <!-- HEADER -->
    <!-- ============================= -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Connected Device</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                            
                        <li class="breadcrumb-item active">Connected Device</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (!is_null($errorMessage)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?= $themeColor ?> text-white">
                        <h3 class="card-title m-0">Data Connected Device</h3>
                        <div class="card-tools">
                            <button id="btnExportPDF" class="btn btn-danger btn-sm mr-2">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                            <button onclick="location.href='connected_device.php'" class="btn btn-light btn-sm">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="small">Filter Device</label>
                                    <select name="device_filter" class="form-control form-control-sm filter-select">
                                        <option value="">Semua Device</option>
                                        <?php foreach ($deviceCounts as $d => $c): ?>
                                            <option value="<?= e($d) ?>"><?= e($d) ?> (<?= $c ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small">Filter Status</label>
                                    <select name="status_filter" class="form-control form-control-sm filter-select">
                                        <option value="">Semua Status</option>
                                        <option value="Online">Online</option>
                                        <option value="Offline">Offline</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small">Cari (Nama/IP)</label>
                                    <input type="text" name="search_device" class="form-control form-control-sm filter-input" placeholder="Cari nama atau IP...">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <a href="connected_device.php" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eraser"></i> Reset Filter
                                    </a>
                                    <span class="auto-refresh-info ml-2">
                                        <i class="fas fa-info-circle"></i> Data akan otomatis dimuat ulang saat filter diubah
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Overlay -->
                        <div class="loading-overlay">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i> Memuat data...
                            </div>
                        </div>

                        <!-- Device Summary -->
                        <div class="mb-3">
                            <strong>Device Summary:</strong>
                            <?php foreach ($deviceCounts as $d => $c): ?>
                                <span class="badge-device"><?= e($d) ?> (<?= $c ?>)</span>
                            <?php endforeach; ?>
                            <span class="status-online ml-3">Online: <?= $statusOnline ?></span>
                            <span class="status-offline">Offline: <?= $statusOffline ?></span>
                        </div>

                        <!-- Table Data -->
                        <div class="table-responsive">
                            <table id="deviceTable" class="table table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Device</th>
                                        <th>IP</th>
                                        <th>Upload Avg</th>
                                        <th>Download Avg</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data akan di-load via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {
    // URL untuk AJAX
    const ajaxUrl = 'ajax_connected_device.php';
    
    // Debounce function untuk delay auto load
    let debounceTimer;
    function debounce(callback, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(callback, delay);
    }

    // --- SweetAlert2 helper seperti user_fingerprint.php ---
    const showAlert = (type, msg) => Swal.fire({icon: type, title: msg, timer: 2500, showConfirmButton: false});
    const setLoading = (btn, text) => btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (text || ''));
    const resetLoading = (btn, icon, text) => btn.prop('disabled', false).html('<i class="' + icon + '"></i> ' + (text || ''));
    
    // Inisialisasi DataTable dengan server-side processing
    const dataTable = $('#deviceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            data: function (d) {
                // Tambahkan parameter filter
                d.device_filter = $('select[name="device_filter"]').val();
                d.status_filter = $('select[name="status_filter"]').val();
                d.search_device = $('input[name="search_device"]').val();
                return d;
            },
            beforeSend: function() {},
            complete: function() {},
            error: function (xhr, error, thrown) {
                let errorMsg = 'Gagal memuat data. Silakan refresh halaman.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    if (xhr.responseText.includes('<!DOCTYPE')) {
                        errorMsg = 'Server mengembalikan halaman HTML bukan JSON. Silakan cek konfigurasi.';
                    }
                }
                showAlert('error', errorMsg);
            }
        },
        columns: [
            { data: 0, className: 'text-center' }, // No
            { data: 1 }, // Nama
            { data: 2, className: 'text-center' }, // Device
            { data: 3, className: 'text-center' }, // IP
            { data: 4, className: 'text-center' }, // Upload Avg
            { data: 5, className: 'text-center' }, // Download Avg
            { data: 6, className: 'text-center' } // Status
        ],
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            "processing": "Memproses...",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data yang ditemukan",
            "info": "Menampilkan _START_ hingga _END_ dari _TOTAL_ data",
            "infoEmpty": "Menampilkan 0 hingga 0 dari 0 data",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
    // Hide the default DataTables search box on the right
    $('.dataTables_filter').hide();

    // Hapus overlay loading jika ada
    $('.loading-overlay').remove();

    // Export PDF button
    $('#btnExportPDF').click(function () {
        setLoading($(this), 'Export...');
        const device = encodeURIComponent($('select[name="device_filter"]').val() || '');
        const status = encodeURIComponent($('select[name="status_filter"]').val() || '');
        const search = encodeURIComponent($('input[name="search_device"]').val() || '');
        window.open(`export_connected_device.php?device_filter=${device}&status_filter=${status}&search_device=${search}`, '_blank');
        resetLoading($(this), 'fas fa-file-pdf', 'Export PDF');
    });
    
    // Event listener untuk filter
    $('select[name="device_filter"], select[name="status_filter"], input[name="search_device"]').on('change keyup', function() {
        debounce(function() {
            dataTable.ajax.reload();
        }, 500);
    });
});
</script>
