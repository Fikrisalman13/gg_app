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
    header('Location: tipe.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Tipe tidak ditemukan.";
    header('Location: tipe.php');
    exit;
}

$id_tipe = $_GET['id'];

// Ambil data tipe yang akan diedit
$sql = "SELECT t.id_tipe, t.id_merk, t.nama_tipe, m.nama_merk 
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

// Proses form update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_merk = $_POST['id_merk'];
    $nama_tipe = trim($_POST['nama_tipe']);
    $upduser = $_SESSION['UserName'];

    // Validasi input
    if (empty($nama_tipe)) {
        $_SESSION['error'] = "Nama tipe tidak boleh kosong!";
    } else {
        $sql = "UPDATE dbo.m_tipe 
                SET id_merk = ?, nama_tipe = ?, upddate = GETDATE(), upduser = ?
                WHERE id_tipe = ?";
        $params = [$id_merk, $nama_tipe, $upduser, $id_tipe];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Data Tipe berhasil diperbarui.";
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
                    <h1 class="m-0">Edit Tipe</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="tipe.php">Tipe</a></li>
                        <li class="breadcrumb-item active">Edit Tipe</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Tipe</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" autocomplete="off">
                        <div class="form-group">
                            <label for="id_merk">Merk</label>
                            <select class="form-control" id="id_merk" name="id_merk" required>
                                <?php foreach ($merks as $merk): ?>
                                    <option value="<?= $merk['id_merk'] ?>" <?= ($merk['id_merk'] == $tipe['id_merk']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($merk['nama_merk']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nama_tipe">Nama Tipe</label>
                            <input type="text" class="form-control" id="nama_tipe" name="nama_tipe" 
                                   value="<?= htmlspecialchars($tipe['nama_tipe']) ?>" 
                                   placeholder="Masukkan nama tipe" required maxlength="100">
                            <small class="text-muted">Maksimal 100 karakter</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="tipe.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
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
