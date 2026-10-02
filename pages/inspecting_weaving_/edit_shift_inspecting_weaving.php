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
requireEdit($conn, MENU_SHIFT_INSPECT);

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Id Shift dari URL
$shiftId = $_GET['id'] ?? null;

if (!$shiftId) {
    $_SESSION['error'] = "Invalid Shift ID!";
    header("Location: shift_inspecting_weaving.php");
    exit();
}
// Mendapatkan data shift yang akan diedit
$shiftSql = "
        SELECT 
            ShiftId,
            ShiftKode,
            ShiftKet,
            UpdDate,
            UpdUser
        FROM dbo.SMShiftWInspector
        WHERE ShiftId = ?
";
$shiftStmt = sqlsrv_query($conn, $shiftSql, array($shiftId));
if ($shiftStmt === false) {
    error_log("Failed to fetch shift data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Failed to retrieve shift data.";
    header("Location: shift_inspecting_weaving.php");
    exit;
}

$shiftData = sqlsrv_fetch_array($shiftStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($shiftStmt);

if (!$shiftData) {
    $_SESSION['error'] = "Shift not found!";
    header("Location: shift_inspecting_weaving.php");
    exit;
}
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA SHIFT
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $shiftKode = trim($_POST['ShiftKode'] ?? '');
    $ShiftKet = trim($_POST['ShiftKet'] ?? '');
    $updUser = $_SESSION['UserName'];
    $updDate = date('Y-m-d H:i:s');

    if (!empty($shiftKode) && !empty($ShiftKet)) {
        // Update data shift
        $sqlUpdate = "UPDATE dbo.SMShiftWInspector SET ShiftKode = ?, ShiftKet = ?, UpdDate = ?, UpdUser = ? WHERE ShiftId = ?";
        $paramsUpdate = [$shiftKode, $ShiftKet, $updDate, $updUser, $shiftId];
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate) {
            sqlsrv_free_stmt($stmtUpdate);
            $_SESSION['success'] = "Shift updated successfully.";
            header('Location: shift_inspecting_weaving.php');
            exit;
        } else {
            $_SESSION['error'] = "An error occurred while updating shift: " . print_r(sqlsrv_errors(), true);
        }
    } else {
        $_SESSION['error'] = "Please fill in all required fields!";
    }
}
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
                    <h1 class="m-0">Edit Shift Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="shift_inspecting_weaving.php">Shift Weaving</a></li>
                        <li class="breadcrumb-item active">Edit Shift Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- ALERT MESSAGES -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-check"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-ban"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- EDIT SHIFT FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit Shift Weaving - <?= htmlspecialchars($shiftData['ShiftKode']) ?>
                    </h3>
                </div>

                <div class="card-body">
                    <form action="" method="POST" id="editShiftForm" autocomplete="off">
                        <input type="hidden" name="shiftId" value="<?= $shiftData['ShiftId'] ?>">
                        
                        <!-- SHIFT KODE FIELD -->
                        <div class="form-group">
                            <label for="ShiftKode" class="font-weight-bold">Shift Kode *</label>
                            <input type="text" 
                                   name="ShiftKode" 
                                   id="ShiftKode" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($shiftData['ShiftKode']) ?>" 
                                   required
                                   autocomplete="ShiftKode">
                            <small class="form-text text-muted">
                                ShiftKode must be unique and must not contain spaces.
                            </small>
                        </div>

                        <!-- SHIFT DESC FIELD -->
                         <div class="form-group">
                            <label for="ShiftKet" class="font-weight-bold">Shift Name</label>
                            <input type="text" 
                                   name="ShiftKet" 
                                   id="ShiftKet" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($shiftData['ShiftKet']) ?>" 
                                   required
                                   autocomplete="ShiftKet">
                            
                        </div>
                        <!-- ACTION BUTTONS -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Save
                            </button>
                            <a href="shift_inspecting_weaving.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                        </div>       
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>
<!-- ===================================================
    9. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>

<!-- CUSTOM SCRIPT -->

