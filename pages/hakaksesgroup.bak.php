<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. PENGATURAN NOTIFIKASI
// ===================================================
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
require '../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireView($conn, MENU_GROUP_ACCESS);
$perm = userPermissions($conn, MENU_GROUP_ACCESS);

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        Hak Akses Group
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li class="breadcrumb-item active">Hak Akses Group</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Card -->
                    <div class="card card">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                            <h3 class="card-title">
                                <i class="fas fa-list mr-1"></i>Daftar Hak Akses Group
                            </h3>
                            <div class="card-tools">
                                <?php if ($perm['CanAdd'] == 1): ?>
                                    <a href="hakaksesgroup_trustee.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus-circle mr-1"></i>Tambah Hak Akses
                                    </a>
                                <?php endif; ?>
                               
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                               
                                <table id="hakAksesTable" class="table table-hover table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="5%" class="text-center">No</th>
                                            <th width="15%">Group</th>
                                            <th width="20%">Description</th>
                                            <th width="20%">Menu</th>
                                            <th width="8%" class="text-center">View</th>
                                            <th width="8%" class="text-center">Add</th>
                                            <th width="8%" class="text-center">Edit</th>
                                            <th width="8%" class="text-center">Delete</th>
                                            <th width="8%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody><!-- Data akan diisi via AJAX --></tbody>
                                    <tfoot>
                                        <tr>
                                            <th>No</th>
                                            <th>Group</th>
                                            <th>Description</th>
                                            <th>Menu</th>
                                            <th class="text-center">View</th>
                                            <th class="text-center">Add</th>
                                            <th class="text-center">Edit</th>
                                            <th class="text-center">Delete</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="legend">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>Keterangan:</strong>
                                            <span class="badge badge-light ml-2"><i class="fas fa-folder-open text-warning mr-1"></i> Menu Utama</span>
                                            <span class="badge badge-light ml-2"><i class="fas fa-level-down-alt text-success mr-1"></i> Submenu</span>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <small class="text-muted">
                                        Total data: <span id="totalRecords">0</span> 
                                        | Filtered: <span id="filteredRecords">0</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================
    9. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>

<!-- ===================================================
    10. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- ===================================================
    11. JAVASCRIPT CUSTOM
======================================================= -->
<!-- NOTIFIKASI SUKSES/ERROR -->
<script>
$(function() {
    // Toast notifications
    <?php if ($successMessage): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'success',
            title: <?= json_encode($successMessage) ?>
        });
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: <?= json_encode($errorMessage) ?>,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>
});
</script>

<script>
$(document).ready(function () {
    // Initialize DataTable
    const table = $('#hakAksesTable').DataTable({
        responsive: true,
        autoWidth: false,
        processing: true,
        serverSide: true,
        ajax: {
            url: 'hakaksesgroup_ajax.php',
            type: 'GET',
            dataSrc: function(json) {
                if (json.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: json.error,
                        confirmButtonColor: '#dc3545'
                    });
                    return [];
                }
                // Update record counts
                $('#totalRecords').text(json.recordsTotal.toLocaleString());
                $('#filteredRecords').text(json.recordsFiltered.toLocaleString());
                return json.data;
            },
            error: function(xhr, error, thrown) {
                let msg = "Terjadi kesalahan saat memuat data.";
                if (xhr.status === 404) msg = "File AJAX tidak ditemukan.";
                else if (xhr.status === 500) msg = "Kesalahan server internal.";
                else if (xhr.status === 403) msg = "Akses ditolak.";
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg,
                    confirmButtonColor: '#dc3545'
                });
                console.error('DataTables AJAX Error:', error, thrown);
            }
        },
        
        
        initComplete: function() {
            // Add custom search input
            $('.dataTables_filter input').addClass('form-control form-control-sm');
            $('.dataTables_length select').addClass('form-control form-control-sm');
        },
        drawCallback: function(settings) {
            // Update record counts on each draw
            $('#totalRecords').text(settings.json.recordsTotal.toLocaleString());
            $('#filteredRecords').text(settings.json.recordsFiltered.toLocaleString());
        }
    });

    // Tombol Delete Hak Akses
    $(document).on("click", ".btn-delete-hakakses", function () {
        var trusteeId = $(this).data('trustee-id');
        var groupName = $(this).data('group-name');
        var menuName = $(this).data('menu-name');

        Swal.fire({
            title: 'Hapus Hak Akses?',
            html: `Yakin ingin menghapus hak akses untuk:<br>
                  <strong>${groupName}</strong><br>
                  pada menu: <strong>${menuName}</strong>`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc3545",
            cancelButtonColor: "#6c757d",
            confirmButtonText: '<i class="fas fa-trash mr-1"></i>Ya, Hapus!',
            cancelButtonText: '<i class="fas fa-times mr-1"></i>Batal',
            reverseButtons: true,
            backdrop: true,
            allowOutsideClick: false,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: "delete_hakaksesgroup.php",
                        type: "POST",
                        data: { id: trusteeId },
                        dataType: "json",
                        success: function(response) {
                            resolve(response);
                        },
                        error: function(xhr, status, error) {
                            reject('Terjadi kesalahan saat menghapus data.');
                        }
                    });
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (result.value.status === "success") {
                    // Show success message
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                    });
                    
                    Toast.fire({
                        icon: 'success',
                        title: result.value.message
                    });
                    
                    // Reload DataTable
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: result.value.message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            }
        });
    });

    // Tombol Edit Hak Akses
    $(document).on("click", ".btn-warning", function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        
        Swal.fire({
            title: 'Mengedit Hak Akses',
            html: 'Anda akan diarahkan ke halaman edit hak akses.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-edit mr-1"></i>Lanjutkan',
            cancelButtonText: '<i class="fas fa-times mr-1"></i>Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });

    // Refresh button functionality
    $('#refreshTable').click(function() {
        table.ajax.reload();
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'success',
            title: 'Data diperbarui'
        });
    });
});
</script>

<!-- ===================================================
    12. CUSTOM CSS STYLES
======================================================= -->
<style>
/* Styles untuk menu hierarchy */
.menu-with-parent {
    padding-left: 15px;
    position: relative;
    border-left: 2px solid #dee2e6;
    margin-left: 5px;
}

.menu-with-parent .parent-menu {
    font-size: 0.85em;
    margin-bottom: 2px;
    color: #6c757d;
    font-style: italic;
}

.menu-with-parent .sub-menu {
    font-weight: 500;
    color: #495057;
    padding-left: 5px;
}

.menu-with-parent .parent-menu i {
    color: #fd7e14;
    font-size: 0.9em;
}

.menu-with-parent .sub-menu i {
    color: #20c997;
    font-size: 0.8em;
    margin-right: 5px;
}

.main-menu {
    font-weight: 600;
    color: #343a40;
}

.main-menu i {
    color: #ffc107;
    margin-right: 5px;
}

/* Permission icons styling */
.permission-yes {
    color: #28a745;
    font-size: 1.3em;
}

.permission-no {
    color: #dc3545;
    font-size: 1.3em;
}

.permission-icon {
    transition: transform 0.2s;
}

.permission-icon:hover {
    transform: scale(1.2);
}

/* Table styling */
#hakAksesTable tbody tr {
    transition: background-color 0.2s;
}

#hakAksesTable tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

#hakAksesTable tbody td {
    vertical-align: middle !important;
}

/* Card styling */
.card-outline {
    border-top: 3px solid <?= $themeColor === 'primary' ? '#007bff' : ($themeColor === 'success' ? '#28a745' : '#17a2b8') ?>;
}

/* Badge styling */
.badge-light {
    border: 1px solid #dee2e6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .menu-with-parent {
        padding-left: 10px;
        margin-left: 2px;
    }
    
    .menu-with-parent .parent-menu {
        font-size: 0.8em;
    }
    
    .menu-with-parent .sub-menu {
        font-size: 0.9em;
    }
    
    .main-menu {
        font-size: 0.95em;
    }
}

/* Loading state */
.dataTables_wrapper .dataTables_processing {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 4px;
    padding: 20px !important;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

/* Action buttons */
.btn-group-xs > .btn, .btn-xs {
    padding: 0.25rem 0.4rem;
    font-size: 0.75rem;
    line-height: 1.5;
    border-radius: 0.2rem;
}

/* Card footer styling */
.card-footer .legend {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

/* Table header styling */
.thead-dark th {
    background-color: <?= $themeColor === 'primary' ? '#0069d9' : ($themeColor === 'success' ? '#218838' : '#138496') ?>;
    border-color: <?= $themeColor === 'primary' ? '#0062cc' : ($themeColor === 'success' ? '#1e7e34' : '#117a8b') ?>;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85em;
    letter-spacing: 0.5px;
}
</style>