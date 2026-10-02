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
requireEdit($conn, MENU_DEPT_INSPECT);

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Id Dept dari URL
$deptId = $_GET['id'] ?? null;

if (!$deptId) {
    $_SESSION['error'] = "Invalid Dept ID!";
    header("Location: dept_inspecting_weaving.php");
    exit();
}
// Mendapatkan data dept yang akan diedit
$deptSql = "
        SELECT 
            DeptId, 
            DeptName 
        FROM dbo.SMDeptWInspector 
        WHERE DeptId = ?
";
$deptStmt = sqlsrv_query($conn, $deptSql, array($deptId));
if ($deptStmt === false) {
    error_log("Failed to fetch dept data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Failed to retrieve dept data.";
    header("Location: dept_inspecting_weaving.php");
    exit;
}

$deptData = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($deptStmt);

if (!$deptData) {
    $_SESSION['error'] = "Departemen not found!";
    header("Location: dept_inspecting_weaving.php");
    exit;
}
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA DEPT
// ===================================================
// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil dan rapikan data dari form
    $deptName = trim($_POST['deptName'] ?? '');
    $updUser  = $_SESSION['UserName'];
    $updDate  = date('Y-m-d H:i:s');

    // Validasi form tidak boleh kosong
    if (!empty($deptName)) {

        // Query update data department
        $sqlUpdate = "UPDATE dbo.SMDeptWInspector
                      SET DeptName = ?, UpdDate = ?, UpdUser = ?
                      WHERE DeptId = ?";

        $paramsUpdate = [$deptName, $updDate, $updUser, $deptId];

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate) {
            sqlsrv_free_stmt($stmtUpdate);
            $_SESSION['success'] = "Department successfully updated.";
            header('Location: dept_inspecting_weaving.php');
            exit;

        } else {
            $_SESSION['error'] = "An error occurred while updating the department: " 
                                . print_r(sqlsrv_errors(), true);
        }

    } else {
        $_SESSION['error'] = "Please fill in the department name!";
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
                    <h1 class="m-0">Edit Departemen Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="dept_inspecting_weaving.php">Departemen Weaving Inspector</a></li>
                        <li class="breadcrumb-item active">Edit Departemen</li>
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

            <!-- EDIT DEPARTMENT FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit Departemen - <?= htmlspecialchars($deptData['DeptName']) ?>
                    </h3>
                </div>

                <div class="card-body">
                    <form action="" method="POST" id="editDeptForm" autocomplete="off">
                        <input type="hidden" name="deptId" value="<?= $deptData['DeptId'] ?>">

                        <!-- FIELD NAMA DEPARTEMEN -->
                        <div class="form-group">
                            <label for="deptName" class="font-weight-bold">Nama Departemen *</label>
                            <input type="text"
                                   name="deptName"
                                   id="deptName"
                                   class="form-control"
                                   value="<?= htmlspecialchars($deptData['DeptName']) ?>"
                                   required
                                   autocomplete="deptName">
                        </div>

                        <!-- ACTION BUTTONS -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Simpan
                            </button>
                            <a href="dept_inspecting_weaving.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali
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