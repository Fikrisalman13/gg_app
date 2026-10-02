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
$menuId = 70; // Menu ID untuk halaman Kategori Dokumen

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
    header('Location: kategori_dok.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Kategori Dokumen tidak ditemukan.";
    header('Location: kategori_dok.php');
    exit;
}

$id_kategori = $_GET['id'];

// Ambil data kategori dokumen untuk konfirmasi
$sql = "SELECT k.id_kategori, k.nama_kategori, kd.kode_dok
        FROM dbo.m_kategori_dok k
        LEFT JOIN dbo.m_kode_dok kd ON k.id_kode_dok = kd.id_kode_dok
        WHERE k.id_kategori = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kategori]);
$kategori = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$kategori) {
    $_SESSION['error'] = "Data Kategori Dokumen tidak ditemukan.";
    header('Location: kategori_dok.php');
    exit;
}

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    $sql = "DELETE FROM dbo.m_kategori_dok WHERE id_kategori = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_kategori]);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Kategori Dokumen '".htmlspecialchars($kategori['nama_kategori'])."' berhasil dihapus.";
    }
    header('Location: kategori_dok.php');
    exit;
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Kategori Dokumen</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Kategori Dokumen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kategori_dok.php">Kategori Dokumen</a></li>
                        <li class="breadcrumb-item active">Hapus Kategori Dokumen</li>
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
                        Apakah Anda yakin ingin menghapus kategori dokumen berikut?
                    </div>
                    
                    <div class="callout callout-danger">
                        <h5>Detail Kategori Dokumen</h5>
                        <p><strong>Nama Kategori:</strong> <?= htmlspecialchars($kategori['nama_kategori']) ?></p>
                        <p><strong>Kode Dokumen:</strong> <?= htmlspecialchars($kategori['kode_dok'] ?? '-') ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Ya, Hapus
                        </button>
                        <a href="kategori_dok.php" class="btn btn-secondary">
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