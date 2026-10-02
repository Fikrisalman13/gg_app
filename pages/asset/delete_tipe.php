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
$menuId = 49; // Menu ID untuk halaman Tipe

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
    header('Location: tipe.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Tipe tidak ditemukan.";
    header('Location: tipe.php');
    exit;
}

$id_tipe = $_GET['id'];

// Ambil data tipe untuk konfirmasi
$sql = "SELECT t.nama_tipe, m.nama_merk 
        FROM dbo.m_tipe t
        JOIN dbo.m_merk m ON t.id_merk = m.id_merk
        WHERE t.id_tipe = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_tipe]);
$tipe = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$tipe) {
    $_SESSION['error'] = "Data Tipe tidak ditemukan.";
    header('Location: tipe.php');
    exit;
}

// Cek apakah tipe digunakan di tabel asset
$sqlCheckAsset = "SELECT COUNT(*) as jumlah FROM dbo.m_asset WHERE id_tipe = ?";
$stmtCheckAsset = sqlsrv_query($conn, $sqlCheckAsset, [$id_tipe]);
$resultAsset = sqlsrv_fetch_array($stmtCheckAsset, SQLSRV_FETCH_ASSOC);
$isUsedInAsset = $resultAsset['jumlah'] > 0;

$isUsed = $isUsedInAsset;
$usageDetails = [];

if ($isUsedInAsset) {
    $usageDetails[] = "digunakan di data asset";
}

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    if ($isUsed) {
        $_SESSION['error'] = "Tipe tidak dapat dihapus karena masih " . implode(" dan ", $usageDetails) . "!";
        header('Location: tipe.php');
        exit;
    }
    
    $sql = "DELETE FROM dbo.m_tipe WHERE id_tipe = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_tipe]);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Tipe '".htmlspecialchars($tipe['nama_tipe'])."' dari merk '".htmlspecialchars($tipe['nama_merk'])."' berhasil dihapus.";
    }
    header('Location: tipe.php');
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Tipe</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Tipe</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="tipe.php">Tipe</a></li>
                        <li class="breadcrumb-item active">Hapus Tipe</li>
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
                            <p>Tipe <strong><?= htmlspecialchars($tipe['nama_tipe']) ?></strong> tidak dapat dihapus karena:</p>
                            <ul>
                                <?php if ($isUsedInAsset): ?>
                                <li>Masih digunakan di data asset</li>
                                <?php endif; ?>
                            </ul>
                            <p>Anda harus menghapus semua data terkait terlebih dahulu.</p>
                            <a href="tipe.php" class="btn btn-secondary mt-2">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Tipe
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                            <p>Apakah Anda yakin ingin menghapus tipe berikut?</p>
                            <div class="callout callout-danger">
                                <h5>Detail Tipe</h5>
                                <p><strong>Merk:</strong> <?= htmlspecialchars($tipe['nama_merk']) ?></p>
                                <p><strong>Tipe:</strong> <?= htmlspecialchars($tipe['nama_tipe']) ?></p>
                            </div>
                            <p class="text-danger font-weight-bold">Data yang dihapus tidak dapat dikembalikan!</p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Ya, Hapus
                            </button>
                            <a href="tipe.php" class="btn btn-secondary">
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