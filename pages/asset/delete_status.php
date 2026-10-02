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
$menuId = 51; // Menu ID untuk halaman Status

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
    header('Location: status.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Status tidak ditemukan.";
    header('Location: status.php');
    exit;
}

$id_status = $_GET['id'];

// Ambil data status untuk konfirmasi
$sql = "SELECT nama_status FROM dbo.m_status WHERE id_status = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_status]);
$status = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$status) {
    $_SESSION['error'] = "Data Status tidak ditemukan.";
    header('Location: status.php');
    exit;
}

// Cek apakah status digunakan di tabel lain
$sqlCheck = "SELECT COUNT(*) as jumlah FROM dbo.m_asset WHERE id_status = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$id_status]);
$result = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
$isUsed = $result['jumlah'] > 0;

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    if ($isUsed) {
        $_SESSION['error'] = "Status tidak dapat dihapus karena masih digunakan di data lain!";
        header('Location: status.php');
        exit;
    }
    
    $sql = "DELETE FROM dbo.m_status WHERE id_status = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_status]);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Status '".htmlspecialchars($status['nama_status'])."' berhasil dihapus.";
    }
    header('Location: status.php');
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Status</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Status</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="status.php">Status</a></li>
                        <li class="breadcrumb-item active">Hapus Status</li>
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
                            <p>Status <strong><?= htmlspecialchars($status['nama_status']) ?></strong> tidak dapat dihapus karena masih digunakan di data lain.</p>
                            <p>Anda harus menghapus semua data yang terkait dengan status ini terlebih dahulu.</p>
                            <a href="status.php" class="btn btn-secondary mt-2">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Status
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                            <p>Apakah Anda yakin ingin menghapus status berikut?</p>
                            <p class="font-weight-bold"><?= htmlspecialchars($status['nama_status']) ?></p>
                            <p class="text-danger">Data yang dihapus tidak dapat dikembalikan!</p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Ya, Hapus
                            </button>
                            <a href="status.php" class="btn btn-secondary">
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