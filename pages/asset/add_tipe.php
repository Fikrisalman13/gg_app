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

// Cek hak akses CanAdd
$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canAdd = false;
if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canAdd = $row['CanAdd'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

// Jika tidak punya hak tambah
if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: tipe.php');
    exit;
}

// Ambil daftar merk untuk dropdown
$merks = [];
$merkSql = "SELECT id_merk, nama_merk FROM dbo.m_merk ORDER BY nama_merk";
$merkStmt = sqlsrv_query($conn, $merkSql);
if ($merkStmt !== false) {
    while ($row = sqlsrv_fetch_array($merkStmt, SQLSRV_FETCH_ASSOC)) {
        $merks[] = $row;
    }
    sqlsrv_free_stmt($merkStmt);
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_merk = $_POST['id_merk'];
    $nama_tipe = trim($_POST['nama_tipe']);
    $upduser = $_SESSION['UserName'];

    // Validasi input
    if (empty($nama_tipe)) {
        $_SESSION['error'] = "Nama tipe tidak boleh kosong!";
    } else {
        $sql = "INSERT INTO dbo.m_tipe (id_merk, nama_tipe, upddate, upduser)
                VALUES (?, ?, GETDATE(), ?)";
        $params = [$id_merk, $nama_tipe, $upduser];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Tipe berhasil ditambahkan.";
            header('Location: tipe.php');
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
                    <h1 class="m-0">Tambah Tipe</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="tipe.php">Tipe</a></li>
                        <li class="breadcrumb-item active">Tambah Tipe</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Tipe</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" autocomplete="off">
                        <div class="form-group">
                            <label for="id_merk">Merk</label>
                            <select class="form-control" id="id_merk" name="id_merk" required>
                                <option value="">-- Pilih Merk --</option>
                                <?php foreach ($merks as $merk): ?>
                                    <option value="<?= $merk['id_merk'] ?>"><?= htmlspecialchars($merk['nama_merk']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nama_tipe">Nama Tipe</label>
                            <input type="text" class="form-control" id="nama_tipe" name="nama_tipe" 
                                   placeholder="Masukkan nama tipe" required maxlength="100">
                            <small class="text-muted">Maksimal 100 karakter</small>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                        <a href="tipe.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
        // Fokus ke input nama tipe saat halaman dimuat
        $('#nama_tipe').focus();
    });
</script>
