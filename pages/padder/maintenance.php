<?php
// maintenance.php - Daftar Riwayat Maintenance Padder
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

// helper: ambil permission user untuk menu Maintenance
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

// Ambil permission (sesuaikan MenuId dengan menu Maintenance di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 116); // Contoh MenuId = 116 untuk Maintenance
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            WHERE status IN ('IN_USE', 'MAINTENANCE')
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

$padderList = getPadderList($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-maintenance { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        /* Button spacing in action column */
        .btn-action {
            margin: 1px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .table td {
            vertical-align: middle;
        }
        .info-box {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .hardness-check {
            font-weight: bold;
            color: #28a745;
        }
        .work-done-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-column {
            min-width: 140px;
        }
        .btn-group {
            display: flex;
            flex-wrap: nowrap;
        }
        .maintenance-card {
            border-left: 4px solid #ffc107;
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
                    <h1 class="m-0">Maintenance Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                  
                        <li class="breadcrumb-item active">Riwayat Maintenance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                    <h3 class="card-title">Daftar Maintenance Padder</h3>
                    <div class="card-tools">
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="maintenance_form.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Input Maintenance Baru</a>
                        <?php endif; ?>
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

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="maintenanceTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Maintenance</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Pekerjaan Dilakukan</th>
                                    <th>Hardness Check</th>
                                    <th>Catatan</th>
                                    <th>Status Padder</th>
                                    <th class="action-column">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div> <!-- /.table-responsive -->
                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div>
    </div>
</div>

<!-- Modal View Maintenance -->
<div class="modal fade" id="viewMaintenanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                <h5 class="modal-title">Detail Maintenance</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tanggal Maintenance:</strong></label>
                            <p id="viewMaintenanceDate" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Padder ID:</strong></label>
                            <p id="viewPadderId" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nama Padder:</strong></label>
                            <p id="viewPadderName" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Hardness Check:</strong></label>
                            <p id="viewHardnessCheck" class="form-control-plaintext hardness-check"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Status Padder:</strong></label>
                            <p id="viewPadderStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><strong>Pekerjaan Dilakukan:</strong></label>
                    <p id="viewWorkDone" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
                <div class="form-group">
                    <label><strong>Catatan:</strong></label>
                    <p id="viewNotes" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <?php if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
                    <button type="button" class="btn btn-warning" id="btnEditMaintenance">
                        <i class="fas fa-edit"></i> Edit Data
                    </button>
                <?php endif; ?>
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
    // Permission fallback
    window.appPermissions = {
        canEdit: <?php echo (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1) ? 'true' : 'false'; ?>,
        canDelete: <?php echo (!empty($permissions['CanDelete']) && $permissions['CanDelete'] == 1) ? 'true' : 'false'; ?>
    };

    var $spinner = $('#table-spinner');
    var viewModal = $('#viewMaintenanceModal');
    var currentMaintenanceId = null;

    // Initialize DataTable
    var table = $('#maintenanceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'maintenance_serverside.php',
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
                data: 'maintenance_date',
                render: function(data, type, row) {
                    // Gunakan formatted date jika ada
                    if (row.maintenance_date_formatted) {
                        return row.maintenance_date_formatted;
                    }
                    // Fallback ke original data
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
                data: 'work_done',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (data.length > 50) {
                        return '<span class="work-done-text" title="' + data + '">' + data.substring(0, 50) + '...</span>';
                    }
                    return data;
                }
            },
            { 
                data: 'hardness_check',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return '<span class="hardness-check">' + data + '</span>';
                }
            },
            { 
                data: 'notes',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data;
                }
            },
            { 
                data: 'padder_status',
                render: function(data, type, row) {
                    let badgeClass = 'badge-secondary';
                    let statusText = data || '-';
                    
                    switch(statusText) {
                        case 'READY': badgeClass = 'badge-success'; break;
                        case 'IN_USE': badgeClass = 'badge-primary'; break;
                        case 'MAINTENANCE': badgeClass = 'badge-warning'; break;
                        case 'REPAIR_VENDOR': badgeClass = 'badge-info'; break;
                        case 'SCRAP': badgeClass = 'badge-danger'; break;
                    }
                    return '<span class="badge status-badge ' + badgeClass + '">' + statusText + '</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'action-column',
                render: function(id, type, row) {
                    if (!id) return '-';
                    
                    let html = '<div class="btn-group btn-group-sm" role="group">';
                    
                    // Tombol View - selalu tampil
                    html += '<button class="btn btn-info btn-action btn-view" data-id="' + id + '" title="Lihat Detail">';
                    html += '<i class="fas fa-eye"></i>';
                    html += '</button>';
                    
                    if (window.appPermissions.canEdit) {
                        html += '<a href="edit_maintenance.php?id=' + id + '" class="btn btn-warning btn-action" title="Edit">';
                        html += '<i class="fas fa-edit"></i>';
                        html += '</a>';
                    }
                    
                    if (window.appPermissions.canDelete) {
                        html += '<button class="btn btn-danger btn-action btn-delete" data-id="' + id + '" title="Hapus">';
                        html += '<i class="fas fa-trash"></i>';
                        html += '</button>';
                    }
                    
                    // Tombol Selesai Maintenance - hanya tampil jika status padder = MAINTENANCE
                    if (row.padder_status === 'MAINTENANCE' && window.appPermissions.canEdit) {
                        html += '<button class="btn btn-success btn-action btn-complete" data-id="' + id + '" title="Selesaikan Maintenance">';
                        html += '<i class="fas fa-check"></i>';
                        html += '</button>';
                    }
                    
                    html += '</div>';
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10, 25, 50], [10, 25, 50]],
        pageLength: 10,
        language: {
            search: "Cari:",
            lengthMenu: "Tampil _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang ditemukan",
            emptyTable: "Tidak ada data maintenance yang tersedia",
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
        $('#start_date').val('');
        $('#end_date').val('');
        refreshTable();
    });

    // Set default dates
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#start_date').val(firstDay.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // Auto refresh
    $('#start_date, #end_date').on('change', refreshTable);

    // View detail handler
    $(document).on('click', '.btn-view', function(){
        let id = $(this).data('id');
        if (!id) return;
        
        currentMaintenanceId = id;
        loadMaintenanceDetail(id);
    });

    // Complete maintenance handler
    $(document).on('click', '.btn-complete', function(){
        let id = $(this).data('id');
        if (!id) return;
        
        Swal.fire({
            title: 'Selesaikan Maintenance?',
            text: 'Padder akan dikembalikan dengan status IN_USE',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Selesaikan',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'complete_maintenance.php?id=' + id;
            }
        });
    });

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        let id = $(this).data('id');
        if (!id) return;
        
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data maintenance akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_maintenance.php?id=' + id;
            }
        });
    });

    // Edit button in modal
    $('#btnEditMaintenance').on('click', function(){
        if (currentMaintenanceId) {
            window.location.href = 'edit_maintenance.php?id=' + currentMaintenanceId;
        }
    });

    // Function to load maintenance detail
    function loadMaintenanceDetail(id) {
        $.ajax({
            url: 'get_maintenance_detail.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            beforeSend: function() { $spinner.show(); },
            success: function(response) {
                $spinner.hide();
                if (response.success) {
                    let data = response.data;
                    
                    // Fill basic information
                    $('#viewMaintenanceDate').text(data.maintenance_date_formatted || '-');
                    $('#viewPadderId').text(data.padder_id || '-');
                    $('#viewPadderName').text(data.padder_name || '-');
                    $('#viewHardnessCheck').text(data.hardness_check || '-');
                    $('#viewWorkDone').text(data.work_done || '-');
                    $('#viewNotes').text(data.notes || '-');
                    
                    // Status badges
                    let statusClass = 'badge-secondary';
                    switch(data.padder_status) {
                        case 'READY': statusClass = 'badge-success'; break;
                        case 'IN_USE': statusClass = 'badge-primary'; break;
                        case 'MAINTENANCE': statusClass = 'badge-warning'; break;
                        case 'REPAIR_VENDOR': statusClass = 'badge-info'; break;
                        case 'SCRAP': statusClass = 'badge-danger'; break;
                    }
                    $('#viewPadderStatus').html('<span class="badge ' + statusClass + '">' + (data.padder_status || '-') + '</span>');
                    
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

    // Notifications
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000 
    }).then(refreshTable);
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