<?php
session_start();
ob_start();

require_once '../DatabaseManager.php';
require_once '../config/load_connection.php';
include '../includes/header.php';
include '../includes/sidebar.php';


// Pastikan user login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['db_active'] = $_POST['koneksi'] ?? 'sqlsrv1';
    $_SESSION['success'] = "Koneksi database berhasil diubah ke: {$_SESSION['db_active']}";
    header('Location: pilih_koneksi.php');
    exit;
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Pengaturan Koneksi Database</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Pilih Koneksi</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header bg-primary">
                <h3 class="card-title">Pilih Koneksi Database Aktif</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <div class="form-group">
                        <label for="koneksi">Database Koneksi</label>
                        <select class="form-control" name="koneksi" id="koneksi" required>
                            <option value="sqlsrv1" <?= ($_SESSION['db_active'] ?? '') === 'sqlsrv1' ? 'selected' : '' ?>>SQL Server - GG</option>
                            <option value="sqlsrv2" <?= ($_SESSION['db_active'] ?? '') === 'sqlsrv2' ? 'selected' : '' ?>>SQL Server - SUM</option>
                            <option value="pgsql" <?= ($_SESSION['db_active'] ?? '') === 'pgsql' ? 'selected' : '' ?>>PostgreSQL - Demo</option>
                        </select>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-success"><i class="fas fa-database"></i> Aktifkan Koneksi</button>
                </div>
            </form>
        </div>
    </div>
    </section>
</div>

<!-- Notifikasi -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sukses!',
        text: '<?= $_SESSION['success'] ?>',
        timer: 3000,
        showConfirmButton: false
    });
    <?php unset($_SESSION['success']); endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
