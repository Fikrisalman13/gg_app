<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

$groupId = $_SESSION['GroupId'];
$menuId = 46; // Menu ID untuk halaman Kode Asset

$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canEdit = false;
if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canEdit = $row['CanEdit'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: kode_asset.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Kode Asset tidak ditemukan.";
    header('Location: kode_asset.php');
    exit;
}
$id_kode = $_GET['id'];

// Ambil data kode asset
$sql = "SELECT id_kode, kode_asset, deskripsi FROM dbo.m_kode_asset WHERE id_kode = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kode]);
$kodeData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$kodeData) {
    $_SESSION['error'] = "Data Kode Asset tidak ditemukan.";
    header('Location: kode_asset.php');
    exit;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_asset = $_POST['kode_asset'];
    $deskripsi = $_POST['deskripsi'];
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_kode_asset 
            SET kode_asset = ?, deskripsi = ?, upddate = GETDATE(), upduser = ? 
            WHERE id_kode = ?";
    $params = [$kode_asset, $deskripsi, $upduser, $id_kode];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Kode Asset berhasil diperbarui.";
        header('Location: kode_asset.php');
        exit;
    }
}
ob_end_flush();
?>


<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Kode Asset</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kode_asset.php">Kode Asset</a></li>
                        <li class="breadcrumb-item active">Edit Kode Asset</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Kode Asset</h3>
                </div>
                <div class="card-body table-responsive">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="kode_asset">Kode Asset</label>
                            <input type="text" class="form-control" id="kode_asset" name="kode_asset" 
                                   value="<?= htmlspecialchars($kodeData['kode_asset'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($kodeData['deskripsi'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="kode_asset.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $_SESSION['success'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); endif; ?>
    });
</script>
