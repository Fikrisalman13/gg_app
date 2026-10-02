<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. PENGATURAN NOTIFIKASI
// ===================================================
// *Menangani notifikasi sukses dan error dari session
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireView($conn, MENU_INSPECT_HEADER);
$perm = userPermissions($conn, MENU_INSPECT_HEADER);
// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Inspect Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?= $error_message ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title">Daftar Inspect Weaving</h3>
                        <?php if (!empty($perm['CanAdd'])): ?>
                            <a href='add_inspecting_weaving.php' class='btn btn-success btn-sm float-right'>
                                <i class='fas fa-plus'></i> Add Detail Inspecting Weaving
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="inspectingTable" class="table table-hover table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>No</th>
                                        <th>No CP</th>                                   
                                        <th>Date</th>
                                        <th>Inspektor</th>                            
                                        <th>Artikel</th>                            
                                        <th>Lot</th>                                    
                                        <th>UpdUser</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
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
    $('#inspectingTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "ajax_inspecting_weaving.php",
        "columns": [
            { "data": "no", className: 'text-center' },
            { "data": "NoCP", className: 'text-center' },
            { "data": "InspectDate", className: 'text-center' },
            { "data": "EmpName", className: 'text-center' },
            { "data": "ArtikelKode", className: 'text-center' },
            { "data": "Lot", className: 'text-center' },
            { "data": "UpdUser", className: 'text-center' },
            { "data": "aksi", className: 'text-center', "orderable": false }
        ]
    });

    // Notifikasi sukses
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $_SESSION['success'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    // Notifikasi error
    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Hapus data
    $(document).on('click', '.btn-delete', function () {
        let noCP = $(this).data('id');

        if (!noCP) {
            Swal.fire({ icon: 'error', title: 'Error!', text: 'ID tidak valid!' });
            return;
        }

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_inspecting_weaving.php', { id: noCP }, function (response) {
                    try {
                        const res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                        }
                    } catch {
                        Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Respon tidak valid dari server.' });
                    }
                }).fail(function () {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan saat menghapus data.' });
                });
            }
        });
    });
});
</script>
