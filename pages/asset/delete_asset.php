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
$menuId = 48; // Menu ID untuk halaman Asset

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

// Jika tidak punya hak hapus
if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: asset.php');
    exit;
}

// Ambil ID asset yang akan dihapus
$id_asset = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id_asset) {
    $_SESSION['error'] = "ID Asset tidak valid!";
    header('Location: asset.php');
    exit;
}

// Ambil data asset untuk konfirmasi
$sql = "SELECT a.id_asset, a.kode_asset_seq, a.serial_number, 
               ka.kode_asset, kat.nama_kategori, m.nama_merk, t.nama_tipe
        FROM dbo.m_asset a
        LEFT JOIN dbo.m_kode_asset ka ON a.id_kode = ka.id_kode
        LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
        LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
        LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
        WHERE a.id_asset = ?";
$params = [$id_asset];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !($asset = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $_SESSION['error'] = "Data asset tidak ditemukan!";
    header('Location: asset.php');
    exit;
}

// Proses penghapusan jika konfirmasi diterima
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $sql = "DELETE FROM dbo.m_asset WHERE id_asset = ?";
    $params = [$id_asset];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menghapus asset: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Asset dengan kode " . htmlspecialchars($asset['kode_asset_seq']) . " berhasil dihapus.";
    }
    
    header('Location: asset.php');
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Asset</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Asset</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="asset.php">Asset</a></li>
                        <li class="breadcrumb-item active">Hapus Asset</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Konfirmasi Penghapusan Asset</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                        Anda akan menghapus data asset berikut. Data yang sudah dihapus tidak dapat dikembalikan.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Kode Asset</th>
                                <td><?= htmlspecialchars($asset['kode_asset_seq']) ?></td>
                            </tr>
                            <tr>
                                <th>Serial Number</th>
                                <td><?= htmlspecialchars($asset['serial_number']) ?></td>
                            </tr>
                            <tr>
                                <th>Jenis Asset</th>
                                <td><?= htmlspecialchars($asset['kode_asset']) ?></td>
                            </tr>
                            <tr>
                                <th>Kategori</th>
                                <td><?= htmlspecialchars($asset['nama_kategori']) ?></td>
                            </tr>
                            <tr>
                                <th>Merk</th>
                                <td><?= htmlspecialchars($asset['nama_merk']) ?></td>
                            </tr>
                            <tr>
                                <th>Tipe</th>
                                <td><?= htmlspecialchars($asset['nama_tipe']) ?></td>
                            </tr>
                        </table>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="confirm_delete" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Ya, Hapus Data
                        </button>
                        <a href="asset.php" class="btn btn-secondary">
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