<?php
// usage.php - Daftar Riwayat Pemakaian Padder
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

$themeColor = $_SESSION['Theme'] ?? 'primary';

// helper: ambil permission user untuk menu Pemakaian
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

// Ambil permission (sesuaikan MenuId dengan menu Pemakaian di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 115); // Contoh MenuId = 115 untuk Pemakaian
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            WHERE status IN ('READY', 'IN_USE', 'MAINTENANCE', 'REPAIRED')
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

// ====== Static Lists (Machine & Location) - SAMA DENGAN usage_form.php ======
$machineList = [
    ['machine_name' => 'Mesin Pad Steam'],
    ['machine_name' => 'Mesin Washing 1'],
    ['machine_name' => 'Mesin Washing 2'],
    ['machine_name' => 'Mesin Washing 3'],
    ['machine_name' => 'Mesin PBR 1'],
    ['machine_name' => 'Mesin PBR 2']
];

$locationList = [
    ['location' => 'Dyeing Finishing'],
    ['location' => 'Weaving']
];

$padderList = getPadderList($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemakaian Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-usage { font-size: .9em; padding: 5px 8px; }
        #table-spinner {
            display: none;
            margin-bottom: 8px;
            text-align: center;
        }
        #table-spinner .spinner-border { width: 1rem; height: 1rem; vertical-align: middle; }
        
        /* Button spacing in action column */
        .btn-action {
            margin: 1px;
        }
        .filter-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .usage-card {
            border-left: 4px solid #007bff;
        }
        .table td {
            vertical-align: middle;
        }
        .photo-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .photo-thumbnail:hover {
            transform: scale(1.1);
        }
        .photo-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .info-box {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .form-group {
            margin-right: 15px;
            margin-bottom: 10px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
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
                    <h1 class="m-0">Pemakaian Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                  
                        <li class="breadcrumb-item active">Riwayat Pemakaian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title">Daftar Pemakaian Padder</h3>
                    <div class="card-tools">
                        
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="usage_form.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Input Pemakaian Baru</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <form id="filterForm" class="form-inline">
                            <div class="filter-row">
                                <div class="form-group">
                                    <label for="padder_id" class="mr-2">Padder:</label>
                                    <select id="padder_id" name="padder_id" class="form-control form-control-sm" style="min-width: 200px;">
                                        <option value="">Semua Padder</option>
                                        <?php foreach ($padderList as $padder): ?>
                                            <option value="<?= htmlspecialchars($padder['padder_id']) ?>">
                                                <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="machine_name" class="mr-2">Mesin:</label>
                                    <select id="machine_name" name="machine_name" class="form-control form-control-sm" style="min-width: 180px;">
                                        <option value="">Semua Mesin</option>
                                        <?php foreach ($machineList as $machine): ?>
                                            <option value="<?= htmlspecialchars($machine['machine_name']) ?>">
                                                <?= htmlspecialchars($machine['machine_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="location" class="mr-2">Lokasi:</label>
                                    <select id="location" name="location" class="form-control form-control-sm" style="min-width: 180px;">
                                        <option value="">Semua Lokasi</option>
                                        <?php foreach ($locationList as $location): ?>
                                            <option value="<?= htmlspecialchars($location['location']) ?>">
                                                <?= htmlspecialchars($location['location']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="start_date" class="mr-2">Dari Tanggal:</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control form-control-sm">
                                </div>

                                <div class="form-group">
                                    <label for="end_date" class="mr-2">Sampai Tanggal:</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control form-control-sm">
                                </div>

                                <div class="form-group">
                                    <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2"><i class="fas fa-filter"></i> Filter</button>
                                    <button type="button" id="btnReset" class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Reset</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- spinner kecil -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="usageTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Pasang</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Lokasi</th>
                                    <th>Nama Mesin</th>
                                    <th>Status Padder</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
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

<!-- Modal View Usage -->
<div class="modal fade" id="viewUsageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title">Detail Pemakaian</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tanggal Pemasangan:</strong></label>
                            <p id="viewUsageDate" class="form-control-plaintext"></p>
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
                            <label><strong>Lokasi:</strong></label>
                            <p id="viewLocation" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Nama Mesin:</strong></label>
                            <p id="viewMachineName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Status Padder:</strong></label>
                            <p id="viewPadderStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><strong>Keterangan:</strong></label>
                    <p id="viewRemarks" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
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
    var viewModal = $('#viewUsageModal');

    // Initialize DataTable
    var table = $('#usageTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'usage_serverside.php',
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
                    machine_name: $('#machine_name').val(),
                    location: $('#location').val(),
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
                data: 'used_date',
                render: function(data, type, row) {
                    // Gunakan formatted date jika ada
                    if (row.used_date_formatted) {
                        return row.used_date_formatted;
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
                data: 'location',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'machine_name',
                render: function(data, type, row) {
                    return data || '-';
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
                        case 'REPAIRED': badgeClass = 'badge-info'; break;
                        case 'SCRAP': badgeClass = 'badge-danger'; break;
                    }
                    return '<span class="badge status-badge ' + badgeClass + '">' + statusText + '</span>';
                }
            },
            { 
                data: 'remarks',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data;
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    if (!id) return '-';
                    
                    let html = '<div class="btn-group btn-group-sm">';
                    html += '<button class="btn btn-info btn-action btn-view" data-id="' + id + '" title="Lihat Detail"><i class="fas fa-eye"></i></button>';
                    
                    if (window.appPermissions.canEdit) {
                        html += '<a href="edit_usage.php?id=' + id + '" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (window.appPermissions.canDelete) {
                        html += '<button class="btn btn-danger btn-action btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
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
            emptyTable: "Tidak ada data usage yang tersedia",
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
        $('#machine_name').val('');
        $('#location').val('');
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
        
        $.ajax({
            url: 'get_usage_detail.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            beforeSend: function() { $spinner.show(); },
            success: function(response) {
                $spinner.hide();
                if (response.success) {
                    let data = response.data;
                    $('#viewUsageDate').text(data.used_date_formatted || '-');
                    $('#viewPadderId').text(data.padder_id || '-');
                    $('#viewPadderName').text(data.padder_name || '-');
                    $('#viewLocation').text(data.location || '-');
                    $('#viewMachineName').text(data.machine_name || '-');
                    $('#viewRemarks').text(data.remarks || '-');
                    
                    let statusClass = 'badge-secondary';
                    switch(data.padder_status) {
                        case 'READY': statusClass = 'badge-success'; break;
                        case 'IN_USE': statusClass = 'badge-primary'; break;
                        case 'MAINTENANCE': statusClass = 'badge-warning'; break;
                        case 'REPAIRED': statusClass = 'badge-info'; break;
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
    });

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        let id = $(this).data('id');
        if (!id) return;
        
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data pemakaian akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_usage.php?id=' + id;
            }
        });
    });

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