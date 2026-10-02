<?php
// status_log.php - Halaman Log Status Padder
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// helper: ambil permission user untuk menu Status Log
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
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission (sesuaikan MenuId dengan menu Status Log di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 119);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

// ====== Get Status List ======
function getStatusList() {
    return [
        'DIBELI' => 'Dibeli',
        'READY' => 'Ready',
        'IN_USE' => 'In Use',
        'MAINTENANCE' => 'Maintenance',
        'REPAIR_VENDOR' => 'Repair Vendor',
        'REPAIRED' => 'Repaired',
        'SCRAP' => 'Scrap'
    ];
}

$padderList = getPadderList($conn);
$statusList = getStatusList();

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Status Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-log { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .log-card {
            border-left: 4px solid #6f42c1;
        }
        .table td {
            vertical-align: middle;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .remarks-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .remarks-text:hover {
            white-space: normal;
            overflow: visible;
        }
        .user-badge {
            background: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
        }
        .timeline-item {
            position: relative;
            padding-left: 20px;
            margin-bottom: 15px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6f42c1;
        }
        .timeline-content {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #6f42c1;
        }
        .export-buttons {
            margin-bottom: 15px;
        }
        .export-dropdown .dropdown-menu {
            min-width: 200px;
        }
        .export-option {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
        }
        .export-option:last-child {
            border-bottom: none;
        }
        .export-option:hover {
            background-color: #f8f9fa;
        }
        .export-icon {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Log Status Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                  
                        <li class="breadcrumb-item active">Log Status</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h3 class="card-title">Riwayat Perubahan Status Padder</h3>
                    <div class="card-tools">
                        <!-- Dropdown Export -->
                        <div class="btn-group export-dropdown">
                            <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown">
                                <i class="fas fa-download"></i> Export Data
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <div class="export-option">
                                    <strong><i class="fas fa-file-pdf text-danger export-icon"></i> Export PDF</strong>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-block btn-export" data-format="pdf" data-type="current">
                                            Data Tampilan
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-block btn-export mt-1" data-format="pdf" data-type="all">
                                            Semua Data
                                        </button>
                                    </div>
                                </div>
                                <div class="export-option">
                                    <strong><i class="fas fa-file-excel text-success export-icon"></i> Export Excel</strong>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-outline-success btn-sm btn-block btn-export" data-format="excel" data-type="current">
                                            Data Tampilan
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm btn-block btn-export mt-1" data-format="excel" data-type="all">
                                            Semua Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" class="form-inline">
                            <div class="form-group mr-3">
                                <label for="padder_id" class="mr-2">Padder:</label>
                                <select id="padder_id" name="padder_id" class="form-control form-control-sm">
                                    <option value="">Semua Padder</option>
                                    <?php foreach ($padderList as $padder): ?>
                                        <option value="<?= htmlspecialchars($padder['padder_id']) ?>">
                                            <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-3">
                                <label for="status" class="mr-2">Status:</label>
                                <select id="status" name="status" class="form-control form-control-sm">
                                    <option value="">Semua Status</option>
                                    <?php foreach ($statusList as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>">
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mr-3">
                                <label for="user" class="mr-2">User:</label>
                                <input type="text" id="user" name="user" class="form-control form-control-sm" 
                                       placeholder="Nama user...">
                            </div>

                            <div class="form-group mr-3">
                                <label for="start_date" class="mr-2">Dari Tanggal:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm">
                            </div>

                            <div class="form-group mr-3">
                                <label for="end_date" class="mr-2">Sampai Tanggal:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm">
                            </div>

                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                            <button type="button" id="btnReset" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Reset</button>
                        </form>
                    </div>

                    <!-- Quick Export Buttons -->
                    <div class="export-buttons">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-success btn-export-quick" data-format="excel" data-type="current">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-danger btn-export-quick" data-format="pdf" data-type="current">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                        <small class="text-muted ml-2">(Data yang ditampilkan)</small>
                    </div>

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="statusLogTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal & Waktu</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Status</th>
                                    <th>User</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Log Detail -->
<div class="modal fade" id="viewLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                <h5 class="modal-title">Detail Log Status</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Padder ID:</strong></label>
                            <p id="viewPadderId" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nama Padder:</strong></label>
                            <p id="viewPadderName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Tanggal & Waktu:</strong></label>
                            <p id="viewChangedAt" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Status:</strong></label>
                            <p id="viewStatus" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>User:</strong></label>
                            <p id="viewChangedBy" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Status Saat Ini:</strong></label>
                            <p id="viewCurrentStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><strong>Keterangan Lengkap:</strong></label>
                    <div id="viewRemarks" class="border rounded p-3 bg-light" style="min-height: 100px;"></div>
                </div>

                <!-- Timeline for this padder -->
                <div class="mt-4">
                    <h6><i class="fas fa-history"></i> Riwayat Status untuk Padder Ini</h6>
                    <div id="padderTimeline" class="mt-3">
                        <!-- Timeline will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Export Progress -->
<div class="modal fade" id="exportProgressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Mempersiapkan Export</h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-info mb-3" role="status"></div>
                <p id="exportProgressText">Mempersiapkan data untuk export...</p>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    var $spinner = $('#table-spinner');
    var viewModal = $('#viewLogModal');
    var exportProgressModal = $('#exportProgressModal');
    var currentPadderId = null;

    // Initialize DataTable
    var table = $('#statusLogTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'status_log_serverside.php',
            type: 'POST',
            data: function(d) {
                return {
                    draw: d.draw,
                    start: d.start,
                    length: d.length,
                    search: {
                        value: d.search.value
                    },
                    padder_id: $('#padder_id').val(),
                    status: $('#status').val(),
                    user: $('#user').val(),
                    start_date: $('#start_date').val(),
                    end_date: $('#end_date').val()
                };
            },
            dataSrc: function (json) {
                // Check for errors
                if (json.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Server',
                        text: json.error,
                        timer: 5000,
                        showConfirmButton: true
                    });
                    return [];
                }
                
                // Validate response structure
                if (typeof json.data === 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format Response Salah',
                        text: 'Struktur data dari server tidak valid',
                        timer: 5000,
                        showConfirmButton: true
                    });
                    return [];
                }
                
                return json.data;
            },
            beforeSend: function() {
                $spinner.show();
            },
            complete: function() {
                $spinner.hide();
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                
                let errorMessage = 'Gagal memuat data dari server';
                if (xhr.status === 0) {
                    errorMessage = 'Tidak dapat terhubung ke server';
                } else if (xhr.status === 500) {
                    errorMessage = 'Error internal server';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    text: errorMessage,
                    timer: 5000,
                    showConfirmButton: true
                });
            }
        },
        columns: [
            { 
                data: null, 
                orderable: false, 
                searchable: false,
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { 
                data: 'changed_at',
                render: function(data, type, row) {
                    if (row.changed_at_formatted) {
                        return row.changed_at_formatted;
                    }
                    return data || '-';
                }
            },
            { 
                data: 'padder_id',
                render: function(data, type, row) {
                    return '<strong>' + (data || '-') + '</strong>';
                }
            },
            { 
                data: 'padder_name',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'status',
                render: function(data, type, row) {
                    let badgeClass = 'badge-secondary';
                    let statusText = data || '-';
                    
                    switch(statusText) {
                        case 'DIBELI': badgeClass = 'badge-info'; break;
                        case 'READY': badgeClass = 'badge-success'; break;
                        case 'IN_USE': badgeClass = 'badge-primary'; break;
                        case 'MAINTENANCE': badgeClass = 'badge-warning'; break;
                        case 'REPAIR_VENDOR': badgeClass = 'badge-info'; break;
                        case 'REPAIRED': badgeClass = 'badge-success'; break;
                        case 'SCRAP': badgeClass = 'badge-danger'; break;
                    }
                    return '<span class="badge status-badge ' + badgeClass + '">' + statusText + '</span>';
                }
            },
            { 
                data: 'changed_by',
                render: function(data, type, row) {
                    return data ? '<span class="user-badge">' + data + '</span>' : '-';
                }
            },
            { 
                data: 'remarks',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (data.length > 100) {
                        return '<span class="remarks-text" title="' + data + '">' + data.substring(0, 100) + '...</span>';
                    }
                    return '<span class="remarks-text">' + data + '</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(id, type, row) {
                    if (!id) return '-';
                    
                    return '<button class="btn btn-info btn-sm btn-view" data-id="' + id + '" title="Lihat Detail">' +
                           '<i class="fas fa-eye"></i>' +
                           '</button>';
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 25,
        order: [[1, 'desc']], // Default order by date descending
        language: {
            search: "Cari:",
            lengthMenu: "Tampil _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang ditemukan",
            emptyTable: "Tidak ada data log status yang tersedia",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Berikutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Refresh table function
    function refreshTable() {
        table.ajax.reload(null, false);
    }

    // Filter buttons
    $('#btnFilter').on('click', refreshTable);
    
    $('#btnReset').on('click', function(){
        $('#padder_id').val('');
        $('#status').val('');
        $('#user').val('');
        $('#start_date').val('');
        $('#end_date').val('');
        refreshTable();
    });

    // Set default dates (last 30 days)
    var today = new Date();
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    $('#start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // Auto refresh on date change
    $('#start_date, #end_date').on('change', refreshTable);

    // View detail handler
    $(document).on('click', '.btn-view', function(){
        let id = $(this).data('id');
        
        if (!id) return;
        
        loadLogDetail(id);
    });

    // Export handlers - PERBAIKAN
function exportData(format, type) {
    const params = {
        padder_id: $('#padder_id').val(),
        status: $('#status').val(),
        user: $('#user').val(),
        start_date: $('#start_date').val(),
        end_date: $('#end_date').val(),
        format: format,
        export_type: type
    };

    // Show progress modal
    $('#exportProgressText').text(`Mempersiapkan ${format.toUpperCase()} ${type === 'current' ? 'data tampilan' : 'semua data'}...`);
    exportProgressModal.modal('show');

    // Build query string
    const queryString = Object.keys(params)
        .filter(key => params[key] !== undefined && params[key] !== '')
        .map(key => key + '=' + encodeURIComponent(params[key]))
        .join('&');

    // Create new window/tab for download
    const downloadWindow = window.open('export_status_log.php?' + queryString, '_blank');
    
    // Check if window was blocked
    if (!downloadWindow || downloadWindow.closed || typeof downloadWindow.closed == 'undefined') {
        exportProgressModal.modal('hide');
        Swal.fire({
            icon: 'warning',
            title: 'Popup Diblokir',
            text: 'Izinkan popup untuk download file. Atau klik link berikut:',
            html: `<a href="export_status_log.php?${queryString}" target="_blank">Klik di sini untuk download</a>`,
            confirmButtonText: 'OK'
        });
        return;
    }

    // Hide progress modal after a delay
    setTimeout(() => {
        exportProgressModal.modal('hide');
        
        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Export Berhasil',
            text: `Data berhasil diexport dalam format ${format.toUpperCase()}`,
            timer: 3000,
            showConfirmButton: false
        });
    }, 2000);
}

    // Quick export buttons
    $('.btn-export-quick').on('click', function() {
        const format = $(this).data('format');
        const type = $(this).data('type');
        exportData(format, type);
    });

    // Dropdown export buttons
    $('.btn-export').on('click', function() {
        const format = $(this).data('format');
        const type = $(this).data('type');
        exportData(format, type);
        
        // Close dropdown
        $('.export-dropdown .dropdown-toggle').dropdown('toggle');
    });

    // Function to load log detail
    function loadLogDetail(id) {
        $.ajax({
            url: 'get_log_detail.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            beforeSend: function() { 
                $spinner.show(); 
            },
            success: function(response) {
                $spinner.hide();
                
                if (response.success) {
                    let data = response.data;
                    
                    // Fill basic information
                    $('#viewPadderId').text(data.padder_id || '-');
                    $('#viewPadderName').text(data.padder_name || '-');
                    $('#viewChangedAt').text(data.changed_at_formatted || '-');
                    $('#viewChangedBy').text(data.changed_by || '-');
                    $('#viewCurrentStatus').html(getStatusBadge(data.current_status));
                    
                    // Status badge
                    $('#viewStatus').html(getStatusBadge(data.status));
                    
                    // Remarks
                    $('#viewRemarks').html(data.remarks ? data.remarks : '<em class="text-muted">Tidak ada keterangan</em>');
                    
                    // Load timeline
                    loadPadderTimeline(data.padder_id);
                    
                    viewModal.modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Gagal mengambil data', 'error');
                }
            },
            error: function() {
                $spinner.hide();
                Swal.fire('Error', 'Terjadi kesalahan saat mengambil data', 'error');
            }
        });
    }

    // Function to load padder timeline
    function loadPadderTimeline(padderId) {
        $.ajax({
            url: 'get_padder_timeline.php',
            type: 'POST',
            data: { padder_id: padderId },
            dataType: 'json',
            success: function(response) {
                const $timeline = $('#padderTimeline');
                $timeline.empty();
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(log) {
                        const timelineItem = `
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <strong>${log.status}</strong>
                                        <small class="text-muted">${log.changed_at_formatted}</small>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-muted">Oleh: ${log.changed_by}</small>
                                    </div>
                                    ${log.remarks ? `<div class="mt-1"><small>${log.remarks}</small></div>` : ''}
                                </div>
                            </div>
                        `;
                        $timeline.append(timelineItem);
                    });
                } else {
                    $timeline.html('<div class="text-muted">Tidak ada riwayat status</div>');
                }
            },
            error: function() {
                $('#padderTimeline').html('<div class="text-muted">Gagal memuat riwayat</div>');
            }
        });
    }

    // Function to get status badge HTML
    function getStatusBadge(status) {
        if (!status) return '-';
        
        let badgeClass = 'badge-secondary';
        
        switch(status) {
            case 'DIBELI': badgeClass = 'badge-info'; break;
            case 'READY': badgeClass = 'badge-success'; break;
            case 'IN_USE': badgeClass = 'badge-primary'; break;
            case 'MAINTENANCE': badgeClass = 'badge-warning'; break;
            case 'REPAIR_VENDOR': badgeClass = 'badge-info'; break;
            case 'REPAIRED': badgeClass = 'badge-success'; break;
            case 'SCRAP': badgeClass = 'badge-danger'; break;
        }
        
        return '<span class="badge ' + badgeClass + '">' + status + '</span>';
    }

    // Notifications
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000 
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal!', 
        text: <?= json_encode($_SESSION['error']) ?>, 
        timer: 3000 
    });
    <?php unset($_SESSION['error']); endif; ?>

    // Initial load
    $(document).ready(function() {
        refreshTable();
    });

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>