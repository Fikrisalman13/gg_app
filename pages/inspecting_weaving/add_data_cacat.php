<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
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
// 4. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 5. VALIDASI IZIN AKSES
// ===================================================
requireAdd($conn, MENU_INSPECT_HEADER);

// ===================================================
// 6. PROSES SIMPAN
// ===================================================
// (Tidak ada proses simpan di halaman ini karena hanya untuk menampilkan dan navigasi)

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

?>


<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->
<!-- CONTENT WRAPPER -->
<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Data Inspecting Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="inspecting_weaving.php">Inspecting Weaving</a></li>
                        <li class="breadcrumb-item active">Tambah Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <section class="content">
        <div class="container-fluid">
            
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Form Tambah Data Cacat</h3>
                </div>

                <div class="card-body">
                    <!-- Form Pilih No CP -->
                    <form method="POST" action="" autocomplete="off">
    <div class="form-row align-items-center">

        <!-- Kolom No CP -->
        <div class="col-md-8">
            <div class="form-group mb-0">
                <label for="noCP">Pilih No CP</label>

                <select class="form-control select2bs4" id="noCP" name="noCP" required>
                    <option value="">Pilih No CP</option>
                    <?php
                    $sql = "SELECT NoCP FROM dbo.FormInspectHd WHERE FgCacat IS NULL";
                    $stmt = sqlsrv_query($conn, $sql);

                    if ($stmt !== false) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $noCP = htmlspecialchars($row['NoCP'], ENT_QUOTES, 'UTF-8');
                            echo "<option value=\"{$noCP}\">{$noCP}</option>";
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                    ?>
                </select>

                <small class="form-text text-muted">
                    Pilih No CP yang belum memiliki data cacat
                </small>
            </div>
        </div>

        <!-- Tombol sejajar -->
        <div class="col-md-2 d-flex">
            <button type="button" id="btnShow" class="btn btn-<?= htmlspecialchars($themeColor) ?> ml-2">
                <i class="fas fa-search"></i> Tampilkan Detail
            </button>
        </div>

    </div>
</form>



                    <!-- Hasil Detail NoCP -->
                    <div class="mt-4" id="detailNoCP"></div>

                    <!-- Tombol Aksi -->
                    <div class="mt-3" id="actionButtons" style="display: none;">
                        <button type="button" id="btnTampilkanCacat" class="btn btn-info">
                            <i class="fas fa-list"></i> Tampilkan Data Cacat
                        </button>
                        <button type="button" id="btnTambahCacat" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-plus"></i> Tambah Data Cacat
                        </button>
                        <a href="inspecting_weaving.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    <!-- Hasil Data Cacat -->
                    <div class="mt-4" id="dataCacat"></div>
                </div>
            </div>

        </div>
    </section>

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
<!-- ===================================================
    9. SCRIPT JAVASCRIPT
======================================================= -->
<script>
$(document).ready(function () {
    // Inisialisasi Select2
     // --- Select2 Bootstrap 4 ---
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
    
    // Tombol Tampilkan Detail
    $('#btnShow').click(function () {
        var noCP = $('#noCP').val();
        if (!noCP) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Pilih No CP terlebih dahulu!'
            });
            return;
        }

        // Tampilkan loading
        $('#detailNoCP').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></div>');

        $.ajax({
            url: '/gg_app/get/get_detail_nocp.php',
            type: 'POST',
            data: { noCP: noCP },
            success: function (response) {
                $('#detailNoCP').html(response);
                $('#actionButtons').show();
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#detailNoCP').html('<div class="alert alert-danger">Gagal mengambil data. Silakan coba lagi.</div>');
                $('#actionButtons').hide();
            }
        });
    });

    // Tombol Tampilkan Data Cacat
    $('#btnTampilkanCacat').click(function () {
        var noCP = $('#noCP').val();
        if (!noCP) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Pilih No CP terlebih dahulu!'
            });
            return;
        }

        // Tampilkan loading
        $('#dataCacat').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Memuat data cacat...</p></div>');

        $.ajax({
            url: '/gg_app/get/get_tampilkan_cacat.php',
            type: 'POST',
            data: { noCP: noCP },
            success: function (response) {
                $('#dataCacat').html(response);
            },
            error: function () {
                $('#dataCacat').html('<div class="alert alert-danger">Gagal mengambil data cacat.</div>');
            }
        });
    });

    // Tombol Tambah Data Cacat
    $('#btnTambahCacat').click(function () {
        var noCP = $('#noCP').val();
        if (!noCP) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Pilih No CP terlebih dahulu!'
            });
            return;
        }
        
        // Redirect ke halaman tambah cacat
        window.location.href = "/gg_app/pages/inspecting_weaving/add_transcacat.php?noCP=" + encodeURIComponent(noCP);
    });
});
</script>



<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: <?= json_encode($_SESSION['success']) ?>,
    timer: 3000,
    showConfirmButton: false
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: <?= json_encode($_SESSION['error']) ?>
});
</script>
<?php unset($_SESSION['error']); endif; ?>

