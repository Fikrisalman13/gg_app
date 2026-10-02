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
    $errors = sqlsrv_errors();
    error_log("Koneksi DB gagal: " . print_r($errors, true));
    die("Kesalahan sistem! Hubungi admin IT.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireView($conn, MENU_INSPECT_RESULT);        // Pastikan user boleh View
$perm = userPermissions($conn, MENU_INSPECT_RESULT);

// ===================================================
// 7. MENDAPATKAN DATA TAMBAHAN
// ===================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';

?>
<!-- ===================================================
     8. HTML - PAGE CONTENT
======================================================= -->

<div class="content-wrapper">

    <!-- Page Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hasil Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Hasil Inspect Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Body -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?= $errorMessage ?></div>

            <?php else: ?>

                <div class="card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title">Hasil Inspect Weaving</h3>
                    </div>

                    <div class="card-body table-responsive">
                        <table id="dataCacatTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>Total Kode Cacat</th>
                                    <th>Total Meter Cacat</th>
                                    <th>Total Point Cacat</th>
                                    <th>Grade</th>
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
     10. JS LIBRARIES
======================================================= -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- ===================================================
     11. JAVASCRIPT - DATATABLE & DELETE
======================================================= -->
<script>
$(document).ready(function () {

    // LOAD DATATABLE
    $('#dataCacatTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ajax.dataresult_inspecting_weaving.php',
            type: 'GET'
        },
        columns: [
            { data: 'no', className: 'text-center' },
            { data: 'NoCP', className: 'text-center' },
            { data: 'TotalKodeCacat', className: 'text-center' },
            { data: 'TotalMeterCacat', className: 'text-center' },
            { data: 'TotalPointCacat', className: 'text-center' },
            { data: 'Grade', className: 'text-center' },
            { data: 'Aksi', className: 'text-center', orderable: false, searchable: false }
        ]
    });

    // NOTIF SUCCESS
    <?php if ($successMessage): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $successMessage ?>",
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    // NOTIF ERROR
    <?php if ($errorMessage): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $errorMessage ?>",
            timer: 3000,
            showConfirmButton: false
        });
    <?php endif; ?>

    // DELETE DATA
    $(document).on('click', '.btn-delete', function () {
        let noCP = $(this).data('id');

        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {

                $.post('hapus_data_cacat.php', { noCP: noCP }, function (response) {

                    try {
                        let res = JSON.parse(response);

                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());

                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                        }

                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Respon server invalid.' });
                    }

                }).fail(() => {
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Server gagal merespon.' });
                });

            }
        });
    });

});
</script>
