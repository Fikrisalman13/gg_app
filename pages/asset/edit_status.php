<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// MenuId khusus untuk halaman Status
$menuId = 51; // Sesuaikan dengan MenuId untuk status di database Anda

// Ambil hak akses
$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canEdit = false;
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canEdit = $row['CanEdit'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data status.";
    header('Location: status.php');
    exit;
}

// Ambil ID status dari parameter URL
$id_status = $_GET['id'] ?? '';
if (empty($id_status)) {
    $_SESSION['error'] = "ID Status tidak valid!";
    header('Location: status.php');
    exit;
}

// Ambil data status yang akan diedit
$sql = "SELECT id_status, nama_status, keterangan 
        FROM dbo.m_status 
        WHERE id_status = ?";
$params = [$id_status];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data status: " . print_r(sqlsrv_errors(), true);
    header('Location: status.php');
    exit;
}

$status_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$status_data) {
    $_SESSION['error'] = "Data status tidak ditemukan!";
    header('Location: status.php');
    exit;
}

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_status = $_POST['nama_status'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $upduser = $_SESSION['UserName'];

    // Validasi input
    if (empty($nama_status)) {
        $_SESSION['error'] = "Nama status harus diisi!";
    } else {
        // Update data di database
        $sql = "UPDATE dbo.m_status 
                SET nama_status = ?, keterangan = ?, upddate = GETDATE(), upduser = ?
                WHERE id_status = ?";
        $params = [$nama_status, $keterangan, $upduser, $id_status];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal mengupdate status: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Status berhasil diperbarui!";
            header('Location: status.php');
            exit;
        }
    }
}

ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Status</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="status.php">Status</a></li>
                        <li class="breadcrumb-item active">Edit Status</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Status</h3>
                </div>
                <div class="card-body">
                    <form action="edit_status.php?id=<?= htmlspecialchars($id_status) ?>" method="post">
                        <div class="form-group">
                            <label for="nama_status">Nama Status</label>
                            <input type="text" class="form-control" id="nama_status" name="nama_status" 
                                   value="<?= htmlspecialchars($status_data['nama_status']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($status_data['keterangan'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                            <a href="status.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    $(document).ready(function () {
        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); endif; ?>
    });
</script>
