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
$menuId = 48; // Menu ID untuk halaman Merk

// Cek hak akses CanEdit
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
    header('Location: merk.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Merk tidak ditemukan.";
    header('Location: merk.php');
    exit;
}

$id_merk = $_GET['id'];

// Ambil data merk yang akan diedit
$sql = "SELECT id_merk, nama_merk FROM dbo.m_merk WHERE id_merk = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_merk]);
$merk = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$merk) {
    $_SESSION['error'] = "Data Merk tidak ditemukan.";
    header('Location: merk.php');
    exit;
}

// Proses form update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_merk = trim($_POST['nama_merk']);
    $upduser = $_SESSION['UserName'];

    // Validasi input
    if (empty($nama_merk)) {
        $_SESSION['error'] = "Nama merk tidak boleh kosong!";
    } else {
        $sql = "UPDATE dbo.m_merk 
                SET nama_merk = ?, upddate = GETDATE(), upduser = ?
                WHERE id_merk = ?";
        $params = [$nama_merk, $upduser, $id_merk];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Data Merk berhasil diperbarui.";
            header('Location: merk.php');
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
                    <h1 class="m-0">Edit Merk</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="merk.php">Merk</a></li>
                        <li class="breadcrumb-item active">Edit Merk</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Merk</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" autocomplete="off">
                        <div class="form-group">
                            <label for="nama_merk">Nama Merk</label>
                            <input type="text" class="form-control" id="nama_merk" name="nama_merk" 
                                   value="<?= htmlspecialchars($merk['nama_merk']) ?>" 
                                   placeholder="Masukkan nama merk" required maxlength="100">
                            <small class="text-muted">Maksimal 100 karakter</small>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                        <a href="merk.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </a>
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
        // Fokus ke input nama merk saat halaman dimuat
        $('#nama_merk').focus();
    });
</script>
