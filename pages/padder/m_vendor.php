<?php
// m_vendor.php - Manajemen Vendor
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

// helper: ambil permission user untuk menu Vendor
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

// Ambil permission (sesuaikan MenuId dengan menu Vendor di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 112); // Contoh MenuId = 59 untuk Vendor
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Vendor</title>

    <!-- CSS (AdminLTE + DataTables minimal) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        .badge-vendor { font-size: .9em; padding: 5px 8px; }
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
        .contact-info {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .vendor-card {
            border-left: 4px solid #28a745;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Vendor Padder</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Vendor</li>
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
                    <h3 class="card-title">Daftar Vendor</h3>
                    <?php if (!empty($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                        <a href="add_vendor.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Vendor</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="vendorTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Vendor</th>
                                    <th>Alamat</th>
                                    <th>Contact Person</th>
                                    <th>Telepon</th>
                                    <th>Email</th>
                                    <th>Tanggal Dibuat</th>
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

<!-- Modal View Vendor -->
<div class="modal fade" id="viewVendorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Detail Vendor</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Nama Vendor:</strong></label>
                            <p id="viewVendorName" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Contact Person:</strong></label>
                            <p id="viewContactPerson" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Telepon:</strong></label>
                            <p id="viewPhone" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Email:</strong></label>
                            <p id="viewEmail" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group">
                            <label><strong>Tanggal Dibuat:</strong></label>
                            <p id="viewCreatedAt" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><strong>Alamat:</strong></label>
                    <p id="viewAddress" class="form-control-plaintext border rounded p-2 bg-light"></p>
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
    var viewModal = $('#viewVendorModal');

    var table = $('#vendorTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'vendor_serverside.php',
            type: 'POST',
            data: function(d) {
                d.search_vendor = $('#search_vendor').val();
                d.search_value = d.search.value;
            },
            beforeSend: function() {
                $spinner.show();
            },
            complete: function() {
                $spinner.hide();
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('AJAX error details:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                var errorMessage = 'Gagal memuat data. ';
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMessage += response.error || response.message || 'Terjadi kesalahan server.';
                    } catch (e) {
                        errorMessage += 'Terjadi kesalahan pada server.';
                    }
                } else {
                    errorMessage += 'Tidak ada response dari server.';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi kesalahan',
                    html: errorMessage + '<br><small>Periksa console untuk detail lebih lanjut.</small>',
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
                data: 'vendor_name',
                render: function(data, type, row) {
                    return '<strong>' + (data || '-') + '</strong>';
                }
            },
            { 
                data: 'address',
                render: function(data, type, row) {
                    if (data && data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data || '-';
                }
            },
            { 
                data: 'contact_person',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'phone',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return '<span class="contact-info"><i class="fas fa-phone"></i> ' + data + '</span>';
                }
            },
            { 
                data: 'email',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return '<span class="contact-info"><i class="fas fa-envelope"></i> ' + data + '</span>';
                }
            },
            { 
                data: 'created_at',
                render: function(data, type, row) {
                    if (!data) return '-';
                    var date = new Date(data);
                    return date.toLocaleDateString('id-ID');
                }
            },
            {
                data: 'vendor_id',
                orderable: false,
                searchable: false,
                render: function(vendor_id, type, row) {
                    if (!vendor_id) return '-';
                    
                    var canEdit = window.appPermissions.canEdit;
                    var canDelete = window.appPermissions.canDelete;

                    var html = '<div class="btn-group">';
                   
                    
                    if (canEdit) {
                        html += '<a href="edit_vendor.php?id=' + vendor_id + '" class="btn btn-warning btn-sm btn-action" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button class="btn btn-danger btn-sm btn-action btn-delete" data-id="' + vendor_id + '" title="Hapus"><i class="fas fa-trash"></i></button>';
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
            text: 'Data vendor akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_vendor.php?id=' + id;
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