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
requireAdd($conn, MENU_SHIFT_INSPECT);

// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shiftKode = trim($_POST['ShiftKode'] ?? '');
    $shiftKet = trim($_POST['ShiftKet'] ?? '');
    $updUser = $_SESSION['UserName'];
    $updDate = date('Y-m-d H:i:s');

    if (!empty($shiftKode) && !empty($shiftKet)) {
        // Periksa apakah ShiftKode atau ShiftKet sudah ada
        $sqlCheckDuplicate = "SELECT COUNT(*) AS count FROM dbo.SMShiftWInspector WHERE ShiftKode = ? OR ShiftKet = ?";
        $paramsCheck = [$shiftKode, $shiftKet];
        $stmtCheck = sqlsrv_query($conn, $sqlCheckDuplicate, $paramsCheck);

        if ($stmtCheck === false) {
            $_SESSION['error'] = "Terjadi kesalahan saat memeriksa duplikat shift: " . print_r(sqlsrv_errors(), true);
        } else {
            $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCheck);

            if ($rowCheck['count'] > 0) {
                $_SESSION['error'] = "Shift dengan Kode atau Nama tersebut sudah ada.";
            } else {
                // Insert data baru jika tidak ada duplikat
                $sqlInsert = "INSERT INTO dbo.SMShiftWInspector (ShiftKode, ShiftKet, UpdDate, UpdUser) VALUES (?, ?, ?, ?)";
                $paramsInsert = [$shiftKode, $shiftKet, $updDate, $updUser];
                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

                if ($stmtInsert) {
                    sqlsrv_free_stmt($stmtInsert);
                    $_SESSION['success'] = "Shift berhasil ditambahkan.";
                    header('Location: shift_inspecting_weaving.php');
                    exit;
                } else {
                    $_SESSION['error'] = "Terjadi kesalahan saat menambahkan shift: " . print_r(sqlsrv_errors(), true);
                }
            }
        }
    } else {
        $_SESSION['error'] = "Mohon isi semua bidang yang diperlukan.";
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
                    <h1 class="m-0">Add Shift Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="shift_inspecting_weaving.php">Shift Weaving</a></li>
                        <li class="breadcrumb-item active">Add Shift Weaving</li>
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
                    <h3 class="card-title">Form Add Shift</h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">

                        <!-- Kode Shift -->
                        <div class="form-group">
                            <label for="ShiftKode">Kode Shift</label>
                            <input type="text" name="ShiftKode" id="ShiftKode" class="form-control" required>
                        </div>

                        <!-- Ket Shift -->
                        <div class="form-group">
                            <label for="ShiftKet">Ket Shift</label>
                            <input type="text" name="ShiftKet" id="ShiftKet" class="form-control" required>
                        </div>

                        <!-- Button Aksi -->
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Save
                        </button>

                        <a href="shift_inspecting_weaving.php" class="btn btn-secondary">
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

