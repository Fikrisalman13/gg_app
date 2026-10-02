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
include '../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../includes/header.php';
include '../includes/sidebar.php';
// Import konstanta dan permission
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireAdd($conn, MENU_USER_GROUP);

// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $groupName = trim($_POST['groupName']);
    $groupDesc = trim($_POST['groupDesc']);
    $updUser = $_SESSION['UserName'] ?? '';

    if (!empty($groupName)) {
        // Cek apakah GroupName sudah ada
        $checkSql = "SELECT COUNT(*) as count FROM dbo.SMUserGroup WHERE GroupName = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, array($groupName));
        
        if ($checkStmt === false) {
            $_SESSION['error'] = "An error occurred while checking the data: " . print_r(sqlsrv_errors(), true);
        } else {
            $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            if ($row['count'] > 0) {
                $_SESSION['error'] = "User Group with name '$groupName' already exist!";
            } else {
                // Insert jika tidak ada duplikasi
                $insertSql = "INSERT INTO dbo.SMUserGroup (GroupName, GroupDesc, UpdDate, UpdUser) 
                              VALUES (?, ?, GETDATE(), ?)";
                $params = array($groupName, $groupDesc, $updUser);
                $insertStmt = sqlsrv_query($conn, $insertSql, $params);

                if ($insertStmt) {
                    $_SESSION['success'] = "User Group berhasil ditambahkan!";
                    header("Location: usergroup.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to add User Group: " . print_r(sqlsrv_errors(), true);
                }
            }
        }
        sqlsrv_free_stmt($checkStmt);
    } else {
        $_SESSION['error'] = "Please fill in all fields!";
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
                    <h1 class="m-0">Add User Group</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="usergroup.php">User Group Manager</a></li>
                        <li class="breadcrumb-item active">Add User Group</li>
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
                    <h3 class="card-title">Form Add User Group</h3>
                </div>

  
                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">

                        <!-- GROUP NAME -->
                        <div class="form-group">
                            <label for="groupName">Group Name</label>
                            <input type="text" name="groupName" id="groupName" class="form-control" required>
                        </div>

                        <!-- GROUP DESCRIPTION -->
                        <div class="form-group">
                            <label for="groupDesc">Group Description</label>
                            <textarea name="groupDesc" id="groupDesc" class="form-control"></textarea>
                        </div>

                        <!-- BUTTON -->
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Save
                        </button>

                        <a href="usergroup.php" class="btn btn-secondary">
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
<?php include '../includes/footer.php'; ?>



