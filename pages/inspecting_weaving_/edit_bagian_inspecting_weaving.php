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
requireEdit($conn, MENU_BAGIAN_INSPECT);
// ===================================================
// 6. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Id Bagian dari URL
$bagianId = $_GET['id'] ?? null;

if (!$bagianId) {
    $_SESSION['error'] = "Invalid Bagian ID!";
    header("Location: bagian_inspecting_weaving.php");
    exit();
}
// Mendapatkan data bagian yang akan diedit
$bagianSql = "
        SELECT 
            BagianId, 
            BagianName, 
            Ket,
            UpdDate,
            UpdUser
        FROM dbo.SMBagianWInspector
        WHERE BagianId = ?
";
$bagianStmt = sqlsrv_query($conn, $bagianSql, array($bagianId));
if ($bagianStmt === false) {
    error_log("Failed to fetch bagian data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Failed to retrieve bagian data.";
    header("Location: bagian_inspecting_weaving.php");
    exit;
}

$bagianData = sqlsrv_fetch_array($bagianStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($bagianStmt);

if (!$bagianData) {
    $_SESSION['error'] = "Bagian not found!";
    header("Location: bagian_inspecting_weaving.php");
    exit;
}
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE BAGIAN
// ===================================================
// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bagianName = $_POST['BagianName'];
    $ket = $_POST['ket'];

    // Query untuk update data
    $sql = "UPDATE dbo.SMBagianWInspector 
            SET BagianName = ?, Ket = ?, UpdDate = GETDATE(), UpdUser = ? 
            WHERE BagianId = ?";
    $params = [$bagianName, $ket, $_SESSION['UserName'], $bagianId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "An error occurred while updating the section.";
    } else {
        $_SESSION['success'] = "Section Data successfully updated.";
        header('Location: bagian_inspecting_weaving.php');
        exit;
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
                    <h1 class="m-0">Edit Bagian Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="bagian_inspecting_weaving.php">Bagian Weaving</a></li>
                        <li class="breadcrumb-item active">Edit Bagian Weaving</li>
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

            <!-- EDIT BAGIAN FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit Bagian Weaving - <?= htmlspecialchars($bagianData['BagianName']) ?>
                    </h3>
                </div>

                <div class="card-body">
                    <form action="" method="POST" id="editBagianForm" autocomplete="off">
                        <input type="hidden" name="bagianId" value="<?= $bagianData['BagianId'] ?>">
                        
                        <!-- Bagian Name FIELD -->
                        <div class="form-group">
                            <label for="BagianName" class="font-weight-bold">Bagian Name</label>
                            <input type="text" 
                                   name="BagianName" 
                                   id="BagianName" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($bagianData['BagianName']) ?>" 
                                   required
                                   autocomplete="BagianName">
                            
                        </div>

                        <!-- Bagian Desc FIELD -->
                         <div class="form-group">
                            <label for="ket" class="font-weight-bold">Desc</label>
                            <input type="text" 
                                   name="ket" 
                                   id="ket" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($bagianData['Ket']) ?>" 
                                   required
                                   autocomplete="ket">
                            
                        </div>
                        <!-- ACTION BUTTONS -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Save
                            </button>
                            <a href="bagian_inspecting_weaving.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                        </div>       
                    </form>
                </div>
            </div>

        </div>
    </section>
</div

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>