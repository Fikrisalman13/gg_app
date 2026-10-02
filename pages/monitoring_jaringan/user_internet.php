<?php
// ======================================================
// user_internet.php
// Menampilkan Data Queue User Internet Mikrotik
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

if (!$canView) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat data.";
    header('Location: /gg_app/index.php');
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
// 5. FUNGSI BANTU (HELPERS)
// ======================================================
function e($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanTarget($target)
{
    return explode('/', $target)[0];
}

function formatSpeed($bps)
{
    if (!is_numeric($bps) || $bps <= 0) return '0 Kbps';

    $kb = $bps / 1024;
    if ($kb >= 1024) {
        $mb = $kb / 1024;
        return ($mb >= 1024)
            ? round($mb / 1024, 2) . ' Gbps'
            : round($mb, 2) . ' Mbps';
    }

    return round($kb, 2) . ' Kbps';
}

function formatBytes($bytes)
{
    if (!is_numeric($bytes) || $bytes <= 0) return '0 KB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1024, 2) . ' KB';
}

// ======================================================
// 6. AMBIL DATA QUEUE DARI MIKROTIK
// ======================================================
$queues        = [];
$totalUpload   = 0;
$totalDownload = 0;
$errorMessage  = null;

try {

    $API = new RouterosAPI();

    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        $rawQueues = $API->comm('/queue/simple/print');
        $API->disconnect();

        foreach ($rawQueues as $q) {

            if (isset($q['bytes']) && strpos($q['bytes'], '/') !== false) {
                [$uBytes, $dBytes] = explode('/', $q['bytes']);
            } else {
                $uBytes = 0;
                $dBytes = 0;
            }

            $uVal = (int) $uBytes;
            $dVal = (int) $dBytes;

            $q['_upload']   = $uVal;
            $q['_download'] = $dVal;
            $q['_total']    = $uVal + $dVal;

            $queues[] = $q;

            $totalUpload   += $uVal;
            $totalDownload += $dVal;
        }

        usort($queues, fn ($a, $b) => ($b['_total'] ?? 0) <=> ($a['_total'] ?? 0));

    } else {
        $errorMessage = 'Gagal koneksi ke Mikrotik';
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

// ======================================================
// 7. PARAMETER GET (BACKWARD COMPATIBILITY)
// ======================================================
$search       = trim((string) ($_GET['search'] ?? ''));
$perpage_raw  = $_GET['perpage'] ?? '10';
$page_raw     = $_GET['page'] ?? '1';

?>

<!-- ======================================================
     8. CSS TAMBAHAN
====================================================== -->
<style>
.filter-section {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.loading-overlay {
    display: none;
    position: fixed;
    inset: 0;
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
.autosave-status {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 0.9rem;
    z-index: 1000;
    display: none;
}
.autosave-success {
    background: #28a745;
    color: white;
}
.autosave-error {
    background: #dc3545;
    color: white;
}
.autosave-info {
    background: #17a2b8;
    color: white;
}
</style>

<!-- ======================================================
     9. HTML STRUKTUR HALAMAN
====================================================== -->
<!-- Auto save notification -->
<div id="autosaveStatus" class="autosave-status"></div>

<div class="content-wrapper">

    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">User Internet</h1>
                </div>
                <div class="col-md-6">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item">
                            <a href="/gg_app/index.php">Beranda</a>
                        </li>
                        <li class="breadcrumb-item active">User Internet</li>
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
                    <h3 class="card-title m-0">Data User Internet</h3>
                    <div class="card-tools">
                        <button id="btnExportPDF" class="btn btn-danger btn-sm">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <a href="add_user/add_user.php" id="btnAddUser" class="btn btn-primary btn-sm ml-2">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                        
                        <button id="btnSyncDB" class="btn btn-success btn-sm ml-2" title="Sync Database">
                            <i class="fas fa-database"></i> Sync Database
                        </button>

                        <button id="btnRefresh" class="btn btn-light btn-sm ml-2">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- CARD BODY -->
                <div class="card-body">

                    <!-- SUMMARY -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary">
                                    <i class="fas fa-upload"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Upload</span>
                                    <span class="info-box-number"><?= e(formatBytes($totalUpload)) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success">
                                    <i class="fas fa-download"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Download</span>
                                    <span class="info-box-number"><?= e(formatBytes($totalDownload)) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning">
                                    <i class="fas fa-exchange-alt"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Trafik</span>
                                    <span class="info-box-number">
                                        <?= e(formatBytes($totalUpload + $totalDownload)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILTER -->
                    <div class="filter-section">
                        <label class="small">Cari (Nama / Target IP)</label>
                        <input type="text"
                               name="search_user"
                               class="form-control form-control-sm"
                               placeholder="Cari nama atau target IP...">

                        <div class="mt-2">
                            <a href="user_internet.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eraser"></i> Reset Filter
                            </a>
                            <span class="auto-refresh-info ml-2">
                                <i class="fas fa-info-circle"></i>
                                Data akan otomatis dimuat ulang
                            </span>
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div class="table-responsive">
                        <table id="userInternetTable"
                               class="table table-hover table-sm w-100">
                            <thead class="thead-light">
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Target IP</th>
                                    <th>Upload Max</th>
                                    <th>Download Max</th>
                                    <!-- Upload Avg & Download Avg removed -->
                                    <th>Upload</th>
                                    <th>Download</th>
                                    <th>Update By</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data via AJAX -->
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<!-- ======================================================
     10. FOOTER
====================================================== -->
<?php include '../../includes/footer.php'; ?>

<!-- ======================================================
     11. JAVASCRIPT LIBRARIES
====================================================== -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="fas fa-eye"></i> View User Internet
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

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

<!-- ======================================================
     12. JAVASCRIPT CUSTOM
====================================================== -->
<script>
$(function () {

    const ajaxUrl = 'ajax_user_internet.php';

    let debounceTimer;  
    function debounce(fn, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fn, delay);
    }

    const showAlert = (type, msg) =>
        Swal.fire({ icon: type, title: msg, timer: 2500, showConfirmButton: false });

    const dataTable = $('#userInternetTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxUrl,
            data: d => {
                d.search_user = $('input[name="search_user"]').val();
                d.canEdit = <?php echo $canEdit ? '1' : '0'; ?>;
                d.canDelete = <?php echo $canDelete ? '1' : '0'; ?>;
            }
        },
        columns: [
            { data: null, orderable: false, className: 'text-center', width: '50px' },
            { data: 1 },
            { data: 2 },
            { data: 3, className: 'text-center' },
            { data: 4, className: 'text-center' },
            { data: 5, className: 'text-center' },
            { data: 6, className: 'text-center' },
            { data: 7, className: 'text-center' }, // Update By
            { 
                data: 8, 
                orderable: false, 
                className: 'text-center',
                width: '120px',
                render: function(data, type, row, meta) {
                    const name = row[1];
                    const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
                    const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;
                    
                    let buttons = '';
                    
                    // Edit button
                    if (canEdit) {
                        buttons += `<a href="edit_user_internet.php?name=${encodeURIComponent(name)}" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a> `;
                    }
                    
                    // View button
                    buttons += `<a href="#" class="btn btn-info btn-sm btn-view" data-name="${name}" title="Lihat"><i class="fas fa-eye"></i></a> `;
                    
                    // Delete button
                    if (canDelete) {
                        buttons += `<button class="btn btn-danger btn-sm btn-delete" data-name="${name}" title="Hapus"><i class="fas fa-trash"></i></button>`;
                    }
                    
                    return buttons;
                }
            }
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        initComplete: function(settings, json) {
            // Store permissions in column settings
            settings.aoColumns[7].canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
            settings.aoColumns[7].canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;
        }
    });

    $('.dataTables_filter').hide();

    $('input[name="search_user"]').on('keyup', function () {
        debounce(() => dataTable.ajax.reload(), 500);
    });

    // Auto save dengan timing lebih presis dan notifikasi
    let autoSaveExecuted = false;
    setInterval(() => {
        const d = new Date();
        const hours = d.getHours();
        const minutes = d.getMinutes();
        const seconds = d.getSeconds();
        
        // Jalankan pada jam 09:01 dengan toleransi 2 menit
        if (hours === 23 && minutes >= 50 && minutes <= 51 && !autoSaveExecuted) {
            autoSaveExecuted = true; // Flag untuk mencegah spam
            const statusEl = document.getElementById('autosaveStatus');
            statusEl.textContent = 'Sedang menyimpan data...';
            statusEl.className = 'autosave-status autosave-info';
            statusEl.style.display = 'block';
            
            fetch("auto_save_mikrotik.php")
                .then(response => response.text())
                .then(data => {
                    if (data === "OK") {
                        statusEl.textContent = 'Auto save berhasil: ' + new Date().toLocaleString();
                        statusEl.className = 'autosave-status autosave-success';
                        console.log("Auto save berhasil:", new Date().toLocaleString());
                    } else {
                        statusEl.textContent = 'Auto save gagal: ' + data;
                        statusEl.className = 'autosave-status autosave-error';
                        console.error("Auto save gagal:", data);
                    }
                    setTimeout(() => statusEl.style.display = 'none', 5000);
                })
                .catch(error => {
                    statusEl.textContent = 'Auto save error: ' + error.message;
                    statusEl.className = 'autosave-status autosave-error';
                    console.error("Auto save error:", error);
                    setTimeout(() => statusEl.style.display = 'none', 5000);
                });
        }
        
        // Reset flag setiap jam baru
        if (hours === 10) {
            autoSaveExecuted = false;
        }
    }, 1000); // Check setiap detik untuk lebih presis

    // Auto refresh (10 menit)
    setInterval(() => dataTable.ajax.reload(null, false), 600000);

    // Event handlers for action buttons
    $('#userInternetTable').on('click', '.btn-view', function(e) {
        e.preventDefault();
        const name = $(this).data('name');
        
        // Load view content via AJAX
        $.ajax({
            url: 'view_user_internet.php',
            type: 'GET',
            data: { name: name, modal: 1 },
            success: function(response) {
                // Direct response for modal mode
                $('#viewModal .modal-body').html(response);
                $('#viewModal').modal('show');
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal memuat data user'
                });
            }
        });
    });

    $('#userInternetTable').on('click', '.btn-delete', function(e) {
        e.preventDefault();
        const name = $(this).data('name');
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: `Apakah Anda yakin ingin menghapus user "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                showAlert('info', `Fitur hapus untuk ${name} dalam pengembangan`);
            }
        });
    });


    // Keep session alive
    setInterval(() => {
    fetch("ping_session.php")
        .then(res => {
            if (!res.ok) throw new Error('Ping session gagal');
            return res.text();
        })
        .then(txt => {
            if (txt !== 'OK') {
                console.error('Ping session response:', txt);
            }
        })
        .catch(err => {
            console.error('Ping session error:', err);
            // Optional: tampilkan notifikasi jika ingin
        });
}, 120000); // 2 menit, lebih sering untuk menjaga session tetap hidup

    $('#btnExportPDF').click(() => {
        window.open(
            "export_user_internet_pdf.php?search=" +
            encodeURIComponent($('input[name="search_user"]').val()),
            "_blank"
        );
    });

    $('#btnRefresh').click(() => location.href = 'user_internet.php');

    // Sync database with Mikrotik queues + firewall address-list
    $('#btnSyncDB').click(function (e) {
        e.preventDefault();
        Swal.fire({
            title: 'Sync Database',
            text: 'Sinkronkan data queues ke database user_internet sekarang?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, sync sekarang',
            cancelButtonText: 'Batal'
        }).then((res) => {
            if (!res.isConfirmed) return;

            Swal.fire({
                title: 'Sedang melakukan sinkronisasi...',
                html: 'Tunggu sampai proses selesai. Jangan tutup jendela ini.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();

                    $.ajax({
                        url: 'sync_user_internet.php',
                        method: 'POST',
                        dataType: 'json',
                        timeout: 120000,
                        success: function (resp) {
                            Swal.close();
                            if (resp && resp.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Selesai',
                                    html: `Inserted: <b>${resp.inserted}</b><br>Updated: <b>${resp.updated}</b>`
                                });
                                dataTable.ajax.reload(null, false);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: resp.message || 'Unknown error' });
                            }
                        },
                        error: function (xhr, status, err) {
                            Swal.close();
                            let msg = 'Terjadi kesalahan saat sinkronisasi.';
                            try {
                                const j = JSON.parse(xhr.responseText);
                                if (j && j.message) msg = j.message;
                            } catch (e) {}
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                        }
                    });
                }
            });
        });
    });

});
</script>
