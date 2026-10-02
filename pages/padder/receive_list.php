<?php
// receive_list.php - Daftar Riwayat Penerimaan Barang
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

// helper: ambil permission user untuk menu Penerimaan
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

// Ambil permission (sesuaikan MenuId dengan menu Penerimaan di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 114); // Contoh MenuId = 114 untuk Penerimaan
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

// ====== Check if Padder has Purchase ======
function hasPurchaseTransaction($conn, $padder_id) {
    $sql = "SELECT COUNT(*) as purchase_count 
            FROM dbo.pad_t_purchase 
            WHERE padder_id = ?";
    $params = [$padder_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    $hasPurchase = false;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hasPurchase = ($row['purchase_count'] > 0);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $hasPurchase;
}

$padderList = getPadderList($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Padder</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-receive { font-size: .9em; padding: 5px 8px; }
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
        .receive-card {
            border-left: 4px solid #28a745;
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
        .badge-no-purchase {
            background-color: #6c757d;
            color: white;
            font-size: 0.7em;
            padding: 3px 6px;
        }
        .info-box {
            margin-bottom: 20px;
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
                    <h1 class="m-0">Penerimaan Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>                    
                        <li class="breadcrumb-item active">Penerimaan Padder</li>
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
                    <h3 class="card-title">Daftar Penerimaan Padder</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="receive.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Input Penerimaan Baru</a>
                    <?php endif; ?>
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
                                <label for="grn_number" class="mr-2">GRN Number:</label>
                                <input type="text" id="grn_number" name="grn_number" class="form-control form-control-sm" placeholder="Nomor GRN">
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
                        <table id="receiveTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Terima</th>
                                    <th>Padder ID</th>
                                    <th>Nama Padder</th>
                                    <th>GRN Number</th>
                                    <th>Status Pembelian</th>
                                    <th>Foto</th>
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

<!-- Modal View Receive -->
<div class="modal fade" id="viewReceiveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Detail Penerimaan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Tanggal Penerimaan:</strong></label>
                            <p id="viewReceiveDate" class="form-control-plaintext"></p>
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
                            <label><strong>GRN Number:</strong></label>
                            <p id="viewGrnNumber" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Status Pembelian:</strong></label>
                            <p id="viewPurchaseStatus" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Jumlah Foto:</strong></label>
                            <p id="viewPhotoCount" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><strong>Keterangan:</strong></label>
                    <p id="viewRemarks" class="form-control-plaintext border rounded p-2 bg-light"></p>
                </div>
                <div class="form-group">
                    <label><strong>Foto Penerimaan:</strong></label>
                    <div id="viewPhotos" class="photo-gallery"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Photo -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Foto Penerimaan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="viewPhotoImg" src="" class="img-fluid" alt="Foto Penerimaan">
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
    var viewModal = $('#viewReceiveModal');
    var photoModal = $('#viewPhotoModal');

    var table = $('#receiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'receive_serverside.php',
            type: 'POST',
            data: function(d) {
                // Format data yang benar untuk DataTables
                return {
                    draw: d.draw,
                    start: d.start,
                    length: d.length,
                    search: {
                        value: d.search.value
                    },
                    padder_id: $('#padder_id').val(),
                    grn_number: $('#grn_number').val(),
                    start_date: $('#start_date').val(),
                    end_date: $('#end_date').val()
                };
            },
            dataSrc: function (json) {
                // Debug response
                console.log('Server response:', json);
                
                if (json.error) {
                    console.error('Server error:', json.error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: json.error,
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
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal memuat data',
                    text: 'Terjadi kesalahan saat memuat data dari server',
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
                    return meta.row + 1 + meta.settings._iDisplayStart;
                }
            },
            { 
                data: 'receive_date',
                render: function(data, type, row) {
                    if (!data) return '-';
                    try {
                        var date = new Date(data);
                        return date.toLocaleDateString('id-ID');
                    } catch (e) {
                        return data;
                    }
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
                data: 'grn_number',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'has_purchase',
                render: function(data, type, row) {
                    if (data == 1) {
                        return '<span class="badge badge-success">Sudah Pembelian</span>';
                    } else {
                        return '<span class="badge badge-warning">Belum Pembelian</span>';
                    }
                }
            },
            { 
                data: 'photos',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if (!data || data.length === 0) return '-';
                    
                    var html = '';
                    for (var i = 0; i < Math.min(data.length, 3); i++) {
                        var filePath = data[i].file_path || '';
                        if (filePath) {
                            html += '<img src="' + filePath + '" class="photo-thumbnail" data-full-src="' + filePath + '" title="Klik untuk melihat foto">';
                        }
                    }
                    if (data.length > 3) {
                        html += '<span class="badge badge-info ml-1">+' + (data.length - 3) + '</span>';
                    }
                    return html;
                }
            },
            { 
                data: 'remarks',
                render: function(data, type, row) {
                    if (data && data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data || '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    if (!id) return '-';
                    
                    var canEdit = window.appPermissions.canEdit;
                    var canDelete = window.appPermissions.canDelete;

                    var html = '<div class="btn-group">';
                    
                    // View button
                    html += '<button class="btn btn-info btn-sm btn-action btn-view" data-id="' + id + '" title="Lihat Detail"><i class="fas fa-eye"></i></button>';
                    
                    if (canEdit) {
                        html += '<a href="edit_receive.php?id=' + id + '" class="btn btn-warning btn-sm btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-action btn-delete" data-id="' + id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        lengthMenu: [[10,25,50],[10,25,50]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampil _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang ditemukan",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Berikutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Filter buttons
    $('#btnFilter').on('click', function(){
        console.log('Applying filters...');
        table.ajax.reload();
    });
    
    $('#btnReset').on('click', function(){
        $('#padder_id').val('');
        $('#grn_number').val('');
        $('#start_date').val('');
        $('#end_date').val('');
        table.ajax.reload();
    });

    // Set default dates
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#start_date').val(firstDay.toISOString().split('T')[0]);
    $('#end_date').val(today.toISOString().split('T')[0]);

    // View detail handler
    $(document).on('click', '.btn-view', function(){
        var id = $(this).data('id');
        if (!id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID tidak valid',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }
        
        // AJAX untuk mengambil detail penerimaan
        $.ajax({
            url: 'get_receive_detail.php',
            type: 'POST',
            data: { id: id },
            beforeSend: function() {
                $spinner.show();
            },
            success: function(response) {
                $spinner.hide();
                console.log('Detail response:', response);
                if (response.success) {
                    var data = response.data;
                    
                    // Isi modal dengan data
                    $('#viewReceiveDate').text(data.receive_date_formatted || '-');
                    $('#viewPadderId').text(data.padder_id || '-');
                    $('#viewPadderName').text(data.padder_name || '-');
                    $('#viewGrnNumber').text(data.grn_number || '-');
                    $('#viewRemarks').text(data.remarks || '-');
                    $('#viewPhotoCount').text(data.photo_count || '0');
                    
                    // Status pembelian
                    var purchaseStatus = data.has_purchase == 1 ? 
                        '<span class="badge badge-success">Sudah Pembelian</span>' : 
                        '<span class="badge badge-warning">Belum Pembelian</span>';
                    $('#viewPurchaseStatus').html(purchaseStatus);
                    
                    // Isi foto
                    var photosHtml = '';
                    if (data.photos && data.photos.length > 0) {
                        data.photos.forEach(function(photo) {
                            var filePath = photo.file_path || '';
                            if (filePath) {
                                photosHtml += '<img src="' + filePath + '" class="photo-thumbnail" data-full-src="' + filePath + '" title="Klik untuk melihat foto">';
                            }
                        });
                    } else {
                        photosHtml = '<p class="text-muted">Tidak ada foto</p>';
                    }
                    $('#viewPhotos').html(photosHtml);
                    
                    // Tampilkan modal
                    viewModal.modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Gagal mengambil data',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('Detail error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan saat mengambil data',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    });

    // Photo click handler
    $(document).on('click', '.photo-thumbnail', function(){
        var src = $(this).data('full-src');
        $('#viewPhotoImg').attr('src', src);
        photoModal.modal('show');
    });

    // Delete handler
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        if (!id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID tidak valid',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data penerimaan akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_receive.php?id=' + id;
            }
        });
    });

    // Show notification if any
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal!', 
        text: <?= json_encode($_SESSION['error']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['error']); endif; ?>

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>