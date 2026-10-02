<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

session_start();
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
include '../../koneksi.php';
if (!$conn) {
    error_log("Koneksi database gagal: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 4. IMPORT DEPENDENSI
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 5. VALIDASI IZIN AKSES
// ===================================================
requireEdit($conn, MENU_JENISCACAT_INSPECT);

// ===================================================
// 6. MENDAPATKAN DATA
// ===================================================

$typeId = $_GET['id'] ?? null;

if (!$typeId) {
    $_SESSION['error'] = "Invalid Type ID!";
    header("Location: jeniscacat_inspecting_weaving.php");
    exit;
}

// Ambil data jenis cacat berdasarkan ID
$sql = "SELECT TypeId, TypeName FROM dbo.SMCacatType WHERE TypeId = ?";
$stmt = sqlsrv_query($conn, $sql, [$typeId]);

if ($stmt === false) {
    error_log("Gagal mengambil data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Gagal mengambil data jenis cacat.";
    header("Location: jeniscacat_inspecting_weaving.php");
    exit;
}

$typeData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$typeData) {
    $_SESSION['error'] = "Jenis Cacat tidak ditemukan!";
    header("Location: jeniscacat_inspecting_weaving.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $typeName = trim($_POST['typeName'] ?? '');
    $updUser  = $_SESSION['UserName'];
    $updDate  = date('Y-m-d H:i:s');

    if (!empty($typeName)) {

        $updateSql = "
            UPDATE dbo.SMCacatType
            SET TypeName = ?, UpdDate = ?, UpdUser = ?
            WHERE TypeId = ?
        ";

        $updateParams = [$typeName, $updDate, $updUser, $typeId];
        $updateStmt   = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt) {
            sqlsrv_free_stmt($updateStmt);
            $_SESSION['success'] = "Jenis Cacat berhasil diperbarui.";
            header("Location: jeniscacat_inspecting_weaving.php");
            exit;
        } else {
            $_SESSION['error'] = "Terjadi kesalahan saat memperbarui data: "
                                . print_r(sqlsrv_errors(), true);
        }

    } else {
        $_SESSION['error'] = "Nama Jenis Cacat wajib diisi!";
    }
}
?>

<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->

<div class="content-wrapper">

    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Jenis Cacat Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="jeniscacat_inspecting_weaving.php">Jenis Cacat</a></li>
                        <li class="breadcrumb-item active">Edit Jenis Cacat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- SUCCESS -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-check"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- ERROR -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-ban"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- FORM EDIT -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit Jenis Cacat – <?= htmlspecialchars($typeData['TypeName']) ?>
                    </h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">
                        <input type="hidden" name="typeId" value="<?= $typeData['TypeId'] ?>">

                        <div class="form-group">
                            <label for="typeName" class="font-weight-bold">Nama Jenis Cacat *</label>
                            <input type="text"
                                   name="typeName"
                                   id="typeName"
                                   class="form-control"
                                   value="<?= htmlspecialchars($typeData['TypeName']) ?>"
                                   required>
                        </div>

                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>

                        <a href="jeniscacat_inspecting_weaving.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali
                        </a>

                    </form>

                </div>
            </div>

        </div>
    </section>

</div>

<!-- FOOTER -->
<?php include '../../includes/footer.php'; ?>
