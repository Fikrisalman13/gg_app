<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

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
requireAdd($conn, MENU_BAGIAN_INSPECT);
// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bagianName = $_POST['bagianName'];
    $ket = $_POST['ket'];

    // Query untuk menambahkan data baru
    $sql = "INSERT INTO dbo.SMBagianWInspector (BagianName, Ket, UpdDate, UpdUser) 
            VALUES (?, ?, GETDATE(), ?)";
    $params = [$bagianName, $ket, $_SESSION['UserName']];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Terjadi kesalahan saat menambahkan data bagian.";
    } else {
        $_SESSION['success'] = "Data Bagian berhasil ditambahkan.";
        header('Location: bagian_inspecting_weaving.php');
        exit;
    }
}
// ===================================================
// 8. MENDAPATKAN DATA
// ===================================================
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';


?>
<!-- ===================================================
    9. HTML: STRUKTUR HALAMAN
======================================================= -->
<!-- CONTENT WRAPPER -->
<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Add Bagian Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="shift_inspecting_weaving.php">Bagian Weaving</a></li>
                        <li class="breadcrumb-item active">Add Bagian Weaving</li>
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
                    <h3 class="card-title">Form Add Bagian</h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">
                        <!-- Kode Shift -->
                        <div class="form-group">
                            <label for="bagianName">Nama Bagian</label>
                            <input type="text" name="bagianName" id="bagianName" class="form-control" required>
                        </div>
                        <!-- Keterangan -->
                        <div class="form-group">
                            <label for="ket">Keterangan</label>
                            <textarea class="form-control" id="ket" name="ket" rows="3"></textarea>
                        </div>

                        <!-- Button Aksi -->
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Save
                        </button>

                        <a href="bagian_inspecting_weaving.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>

                    </form>
                </div>
            </div>

        </div>
    </section>

</div>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>