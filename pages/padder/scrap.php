<?php
// scrap.php - Form Pencatatan Scrap Padder
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

// helper: ambil permission user untuk menu Scrap
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

// Ambil permission (sesuaikan MenuId dengan menu Scrap di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 118); // MenuId untuk Scrap
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            WHERE status NOT IN ('SCRAP')
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
    <title>Pencatatan Scrap Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-scrap { font-size: .9em; padding: 5px 8px; }
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
        .scrap-card {
            border-left: 4px solid #dc3545;
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
        .photo-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid #dee2e6;
            transition: transform 0.2s;
        }
        .photo-thumbnail:hover {
            transform: scale(1.2);
            border-color: #dc3545;
        }
        .photo-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        .photo-badge {
            font-size: 0.7em;
            margin-left: 2px;
        }
        .scrap-notes {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        /* Fix untuk tombol aksi yang tidak terlihat */
        .btn-group {
            display: flex;
            flex-wrap: nowrap;
        }
        .action-column {
            min-width: 120px;
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
                    <h1 class="m-0">Pencatatan Scrap Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                  
                        <li class="breadcrumb-item active">Data Scrap</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'danger'); ?> text-white">
                    <h3 class="card-title">Daftar Padder Scrap</h3>
                    <div class="card-tools">
                        <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="scrap_form.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Input Scrap Baru</a>
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
                        <table id="scrapTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Scrap</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>Lokasi Scrap</th>
                                    <th>Foto</th>
                                    <th>Catatan Scrap</th>
                                    <th class="action-column">Aksi</th>
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

<!-- Modal View Scrap -->
<div class="modal fade" id="viewScrapModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor ?? 'danger'); ?> text-white">
                <h5 class="modal-title">Detail Scrap Padder</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tanggal Scrap:</strong></label>
                            <p id="viewScrapDate" class="form-control-plaintext"></p>
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
                            <label><strong>Lokasi Scrap:</strong></label>
                            <p id="viewScrapLocation" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Status Padder:</strong></label>
                            <p id="viewPadderStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Photo Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label><strong>Foto Scrap:</strong></label>
                            <div id="viewPhotoGallery" class="photo-gallery">
                                <!-- Photos will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Catatan Scrap:</strong></label>
                    <p id="viewScrapNotes" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <?php if (!empty($permissions['CanEdit']) && $permissions['CanEdit'] == 1): ?>
                    <button type="button" class="btn btn-warning" id="btnEditScrap">
                        <i class="fas fa-edit"></i> Edit Data
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Photo -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="viewPhotoTitle">Foto Scrap</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="viewPhotoImage" src="" alt="Foto Scrap" class="img-fluid" style="max-height: 70vh;">
                <div class="mt-3">
                    <small class="text-muted" id="viewPhotoInfo"></small>
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
    var viewModal = $('#viewScrapModal');
    var photoModal = $('#viewPhotoModal');
    var currentScrapId = null;

    // Initialize DataTable
    var table = $('#scrapTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'scrap_serverside.php',
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
                data: 'scrap_date',
                render: function(data, type, row) {
                    if (row.scrap_date_formatted) {
                        return row.scrap_date_formatted;
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
                data: 'scrap_location',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'photo_count',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if (!data || data == 0) return '-';
                    
                    return `<span class="badge badge-danger">${data} Foto</span>`;
                }
            },
            { 
                data: 'scrap_notes',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (data.length > 50) {
                        return '<span class="scrap-notes" title="' + data + '">' + data.substring(0, 50) + '...</span>';
                    }
                    return data;
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
                        html += '<a href="edit_scrap.php?id=' + id + '" class="btn btn-warning btn-action" title="Edit">';
                        html += '<i class="fas fa-edit"></i>';
                        html += '</a>';
                    }
                    
                    if (window.appPermissions.canDelete) {
                        html += '<button class="btn btn-danger btn-action btn-delete" data-id="' + id + '" title="Hapus">';
                        html += '<i class="fas fa-trash"></i>';
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
            emptyTable: "Tidak ada data scrap yang tersedia",
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

    // Set default dates (last 30 days)
    var today = new Date();
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    $('#start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // Auto refresh
    $('#start_date, #end_date').on('change', refreshTable);

    // View detail handler
    $(document).on('click', '.btn-view', function(){
        let id = $(this).data('id');
        
        if (!id) return;
        
        currentScrapId = id;
        loadScrapDetail(id);
    });

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        let id = $(this).data('id');
        if (!id) return;
        
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data scrap akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_scrap.php?id=' + id;
            }
        });
    });

    // Edit button in modal
    $('#btnEditScrap').on('click', function(){
        if (currentScrapId) {
            window.location.href = 'edit_scrap.php?id=' + currentScrapId;
        }
    });

    // Function to load scrap detail
    function loadScrapDetail(id) {
        $.ajax({
            url: 'get_scrap_detail.php',
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
                    $('#viewScrapDate').text(data.scrap_date_formatted || '-');
                    $('#viewPadderId').text(data.padder_id || '-');
                    $('#viewPadderName').text(data.padder_name || '-');
                    $('#viewScrapLocation').text(data.scrap_location || '-');
                    $('#viewScrapNotes').text(data.scrap_notes || '-');
                    
                    // Status badge
                    let padderStatusClass = 'badge-danger';
                    $('#viewPadderStatus').html('<span class="badge ' + padderStatusClass + '">SCRAP</span>');
                    
                    // Load photos
                    loadScrapPhotos(data.photos || []);
                    
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

    // Function to load scrap photos
    function loadScrapPhotos(photos) {
        const $gallery = $('#viewPhotoGallery');
        $gallery.empty();
        
        if (photos.length === 0) {
            $gallery.html('<div class="text-muted">Tidak ada foto</div>');
            return;
        }
        
        photos.forEach(function(photo) {
            const photoHtml = `
                <div class="photo-item text-center">
                    <img src="${photo.file_path}" 
                         alt="Foto Scrap" 
                         class="photo-thumbnail"
                         data-src="${photo.file_path}"
                         data-date="${photo.uploaded_at_formatted || ''}">
                </div>
            `;
            $gallery.append(photoHtml);
        });
        
        // Add click event for photos
        $('.photo-thumbnail').on('click', function() {
            const src = $(this).data('src');
            const date = $(this).data('date');
            
            $('#viewPhotoTitle').text('Foto Scrap');
            $('#viewPhotoImage').attr('src', src);
            $('#viewPhotoInfo').text(date ? 'Upload: ' + date : '');
            photoModal.modal('show');
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