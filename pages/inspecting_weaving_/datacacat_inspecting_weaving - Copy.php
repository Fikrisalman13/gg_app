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
$errorMessage   = $_SESSION['error'] ?? null;
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
include '../../koneksi.php';
if (!$conn) {
    error_log("DB ERROR: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan koneksi database.");
}

// ===================================================
// 5. IMPORT DEPENDENSI (LAYOUT & PERMISSIONS)
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
$perm = userPermissions($conn, MENU_INSPECT_DETAIL);
requireView($conn, MENU_INSPECT_DETAIL);

// ===================================================
// 7. DATA TAMBAHAN
// ===================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- ===================================================
    8. CONTENT WRAPPER
======================================================= -->
<div class="content-wrapper">

    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Data Cacat Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Inspect Weaving - Cacat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Body -->
    <section class="content">
        <div class="container-fluid">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>

        <?php else: ?>
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Daftar Data Cacat Inspect Weaving</h3>

                    <?php if (!empty($perm['CanAdd'])): ?>
                        <a href="add_data_cacat.php" class="btn btn-success btn-sm float-right">
                            <i class="fas fa-plus"></i> Tambah Data Cacat
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card-body table-responsive">
                    <table id="dataCacatTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                        <tr>
                            <th>No</th>
                            <th>No CP</th>
                            <th>No Detail</th>
                            <th>Kode Cacat</th>
                            <th>Dari</th>
                            <th>Sampai</th>
                            <th>Point</th>
                            <th>UpdDate</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        <?php endif; ?>

        </div>
    </section>
</div>

<!-- ===================================================
    9. FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<!-- ===================================================
    10. CSS & JS
======================================================= -->

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

<!-- jQuery -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(document).ready(function () {

    // ===================================================
    // 11. DATATABLES
    // ===================================================
    $('#dataCacatTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: 'ajax_datacacat_inspecting_weaving.php',
        columns: [
            { data: 'no' },
            { data: 'NoCP' },
            { data: 'NoDetail' },
            { data: 'CacatKode' },
            { data: 'MeterKe' },
            { data: 'SMeterKe' },
            { data: 'PointCacat' },
            { data: 'UpdDate' },
            { data: 'aksi', orderable: false, searchable: false }
        ]
    });

    // ===================================================
    // 12. NOTIFIKASI
    // ===================================================
    <?php if ($successMessage): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $successMessage ?>",
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $errorMessage ?>",
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    // ===================================================
    // 13. DELETE HANDLING
    // ===================================================
    $(document).on('click', '.btn-delete', function () {
        let id = $(this).data('id');

        Swal.fire({
            title: 'Hapus data?',
            text: 'Data ini akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_data_cacat.php', { id: id }, function (response) {
                    try {
                        const res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Dihapus!',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    } catch {
                        Swal.fire('Error!', 'Respon server tidak valid!', 'error');
                    }
                }).fail(() => {
                    Swal.fire('Error!', 'Gagal menghapus data.', 'error');
                });
            }
        });
    });
});
</script>
