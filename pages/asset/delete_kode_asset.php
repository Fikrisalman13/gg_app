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
$menuId = 46; // Menu ID untuk halaman Kode Asset

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
    header('Location: kode_asset.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Kode Asset tidak ditemukan.";
    header('Location: kode_asset.php');
    exit;
}

$id_kode = $_GET['id'];

// Ambil data kode asset untuk konfirmasi
$sql = "SELECT kode_asset, deskripsi FROM dbo.m_kode_asset WHERE id_kode = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kode]);
$kode_asset = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$kode_asset) {
    $_SESSION['error'] = "Data Kode Asset tidak ditemukan.";
    header('Location: kode_asset.php');
    exit;
}

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    $sql = "DELETE FROM dbo.m_kode_asset WHERE id_kode = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_kode]);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Kode Asset '".htmlspecialchars($kode_asset['kode_asset'])."' berhasil dihapus.";
    }
    header('Location: kode_asset.php');
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Kode Asset</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Kode Asset</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kode_asset.php">Kode Asset</a></li>
                        <li class="breadcrumb-item active">Hapus Kode Asset</li>
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
                    <div class="alert alert-warning">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                        Apakah Anda yakin ingin menghapus kode asset berikut?
                    </div>
                    
                    <div class="callout callout-danger">
                        <h5>Detail Kode Asset</h5>
                        <p><strong>Kode:</strong> <?= htmlspecialchars($kode_asset['kode_asset']) ?></p>
                        <p><strong>Deskripsi:</strong> <?= htmlspecialchars($kode_asset['deskripsi']) ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Ya, Hapus
                        </button>
                        <a href="kode_asset.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
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