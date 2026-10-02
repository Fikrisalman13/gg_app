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
requireAdd($conn, MENU_JENISCACAT_INSPECT);
// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typeName = trim($_POST['typeName'] ?? '');
    $updUser  = $_SESSION['UserName'] ?? 'SYSTEM';
    $updDate  = date('Y-m-d H:i:s');

    if (!empty($typeName)) {
        // Periksa apakah nama jenis cacat sudah ada
        $sqlCheck = "SELECT COUNT(*) AS count FROM dbo.SMCacatType WHERE TypeName = ?";
        $paramsCheck = [$typeName];
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

        if ($stmtCheck === false) {
            $_SESSION['error'] = "Terjadi kesalahan saat memeriksa duplikasi data: " . print_r(sqlsrv_errors(), true);
        } else {
            $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCheck);

            if ($row['count'] > 0) {
                $_SESSION['error'] = "Jenis Cacat dengan nama tersebut sudah ada.";
            } else {

                // Insert data baru
                $sqlInsert = "INSERT INTO dbo.SMCacatType (TypeName, UpdDate, UpdUser) 
                              VALUES (?, ?, ?)";
                $paramsInsert = [$typeName, $updDate, $updUser];
                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

                if ($stmtInsert) {
                    sqlsrv_free_stmt($stmtInsert);
                    $_SESSION['success'] = "Data Jenis Cacat berhasil ditambahkan.";
                    header('Location: jeniscacat_inspecting_weaving.php');
                    exit;
                } else {
                    $_SESSION['error'] = "Terjadi kesalahan saat menambahkan data: " . print_r(sqlsrv_errors(), true);
                }
            }
        }
    } else {
        $_SESSION['error'] = "Mohon isi nama jenis cacat terlebih dahulu.";
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
                    <h1 class="m-0">Add Jenis Cacat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="jeniscacat_inspecting_weaving.php">Jenis Cacat</a></li>
                        <li class="breadcrumb-item active">Add Jenis Cacat</li>
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
                    <h3 class="card-title">Form Add Jenis Cacat</h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">

                        <!-- Nama Jenis Cacat -->
                        <div class="form-group">
                            <label for="typeName">Nama Jenis Cacat</label>
                            <input type="text" name="typeName" id="typeName" class="form-control" required>
                        </div>                        
                        <!-- Button Aksi -->
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Save
                        </button>

                        <a href="jeniscacat_inspecting_weaving.php" class="btn btn-secondary">
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