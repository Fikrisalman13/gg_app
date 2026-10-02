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

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 47; // Menu ID untuk halaman Kategori

// Cek hak akses CanDelete
$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canDelete = false;
if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canDelete = $row['CanDelete'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: kategori.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Kategori tidak ditemukan.";
    header('Location: kategori.php');
    exit;
}

$id_kategori = $_GET['id'];

// Ambil data kategori untuk konfirmasi
$sql = "SELECT k.id_kategori, k.nama_kategori, ka.kode_asset
        FROM dbo.m_kategori k
        LEFT JOIN dbo.m_kode_asset ka ON k.id_kode = ka.id_kode
        WHERE k.id_kategori = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kategori]);
$kategori = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$kategori) {
    $_SESSION['error'] = "Data Kategori tidak ditemukan.";
    header('Location: kategori.php');
    exit;
}

// Cek apakah kategori digunakan di tabel m_asset
$sqlCheckAsset = "SELECT COUNT(*) as jumlah FROM dbo.m_asset WHERE id_kategori = ?";
$stmtCheckAsset = sqlsrv_query($conn, $sqlCheckAsset, [$id_kategori]);
$resultAsset = sqlsrv_fetch_array($stmtCheckAsset, SQLSRV_FETCH_ASSOC);
$isUsedInAsset = $resultAsset['jumlah'] > 0;

// Cek apakah kategori digunakan sebagai referensi di tabel m_kode_asset
$sqlCheckKodeAsset = "SELECT COUNT(*) as jumlah FROM dbo.m_kode_asset WHERE id_kode IN 
                     (SELECT id_kode FROM dbo.m_kategori WHERE id_kategori = ?)";
$stmtCheckKodeAsset = sqlsrv_query($conn, $sqlCheckKodeAsset, [$id_kategori]);
$resultKodeAsset = sqlsrv_fetch_array($stmtCheckKodeAsset, SQLSRV_FETCH_ASSOC);
$isUsedInKodeAsset = $resultKodeAsset['jumlah'] > 0;

$isUsed = $isUsedInAsset || $isUsedInKodeAsset;
$usageDetails = [];

if ($isUsedInAsset) {
    $usageDetails[] = "digunakan di data asset";
}
if ($isUsedInKodeAsset) {
    $usageDetails[] = "terhubung dengan kode asset";
}

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    if ($isUsed) {
        $_SESSION['error'] = "Kategori tidak dapat dihapus karena masih " . implode(" dan ", $usageDetails) . "!";
        header('Location: kategori.php');
        exit;
    }
    
    $sql = "DELETE FROM dbo.m_kategori WHERE id_kategori = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_kategori]);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Kategori '".htmlspecialchars($kategori['nama_kategori'])."' berhasil dihapus.";
    }
    header('Location: kategori.php');
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Kategori</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Kategori</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kategori.php">Kategori</a></li>
                        <li class="breadcrumb-item active">Hapus Kategori</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Konfirmasi Penghapusan</h3>
                </div>
                <div class="card-body">
                    <?php if ($isUsed): ?>
                        <div class="alert alert-danger">
                            <h5><i class="icon fas fa-ban"></i> Tidak Dapat Dihapus!</h5>
                            <p>Kategori <strong><?= htmlspecialchars($kategori['nama_kategori']) ?></strong> tidak dapat dihapus karena:</p>
                            <ul>
                                <?php if ($isUsedInAsset): ?>
                                <li>Masih digunakan di data asset</li>
                                <?php endif; ?>
                                <?php if ($isUsedInKodeAsset): ?>
                                <li>Masih terhubung dengan kode asset</li>
                                <?php endif; ?>
                            </ul>
                            <p>Anda harus memutuskan semua hubungan ini terlebih dahulu sebelum menghapus.</p>
                            <a href="kategori.php" class="btn btn-secondary mt-2">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Kategori
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                            <p>Apakah Anda yakin ingin menghapus kategori berikut?</p>
                            <div class="callout callout-danger">
                                <h5>Detail Kategori</h5>
                                <p><strong>Nama Kategori:</strong> <?= htmlspecialchars($kategori['nama_kategori']) ?></p>
                                <?php if (!empty($kategori['kode_asset'])): ?>
                                <p><strong>Kode Asset Terkait:</strong> <?= htmlspecialchars($kategori['kode_asset']) ?></p>
                                <?php endif; ?>
                            </div>
                            <p class="text-danger">Data yang dihapus tidak dapat dikembalikan!</p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Ya, Hapus
                            </button>
                            <a href="kategori.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>