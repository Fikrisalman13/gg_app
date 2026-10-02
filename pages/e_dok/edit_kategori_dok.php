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

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 70; // Menu ID untuk halaman Kategori Dokumen

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
    header('Location: kategori_dok.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Kategori Dokumen tidak ditemukan.";
    header('Location: kategori_dok.php');
    exit;
}

$id_kategori = $_GET['id'];

// Ambil data kategori dokumen
$sql = "SELECT id_kategori, nama_kategori, id_kode_dok 
        FROM dbo.m_kategori_dok 
        WHERE id_kategori = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_kategori]);
$kategoriData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$kategoriData) {
    $_SESSION['error'] = "Data Kategori Dokumen tidak ditemukan.";
    header('Location: kategori_dok.php');
    exit;
}

// Ambil daftar kode dokumen untuk dropdown
$sqlKodeDok = "SELECT id_kode_dok, kode_dok FROM dbo.m_kode_dok ORDER BY kode_dok";
$stmtKodeDok = sqlsrv_query($conn, $sqlKodeDok);
$kodeDoks = [];
if ($stmtKodeDok) {
    while ($row = sqlsrv_fetch_array($stmtKodeDok, SQLSRV_FETCH_ASSOC)) {
        $kodeDoks[] = $row;
    }
    sqlsrv_free_stmt($stmtKodeDok);
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kategori = $_POST['nama_kategori'];
    $id_kode_dok = $_POST['id_kode_dok'];
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_kategori_dok 
            SET nama_kategori = ?, id_kode_dok = ?, upddate = GETDATE(), upduser = ? 
            WHERE id_kategori = ?";
    $params = [$nama_kategori, $id_kode_dok, $upduser, $id_kategori];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Kategori Dokumen berhasil diperbarui.";
        header('Location: kategori_dok.php');
        exit;
    }
}
ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Kategori Dokumen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kategori_dok.php">Kategori Dokumen</a></li>
                        <li class="breadcrumb-item active">Edit Kategori Dokumen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Kategori Dokumen</h3>
                </div>
                <div class="card-body table-responsive">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="nama_kategori">Nama Kategori</label>
                            <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" 
                                   value="<?= htmlspecialchars($kategoriData['nama_kategori'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_kode_dok">Kode Dokumen</label>
                            <select class="form-control" id="id_kode_dok" name="id_kode_dok" required>
                                <option value="">-- Pilih Kode Dokumen --</option>
                                <?php foreach ($kodeDoks as $kode): ?>
                                    <option value="<?= $kode['id_kode_dok'] ?>" 
                                        <?= ($kode['id_kode_dok'] == $kategoriData['id_kode_dok']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kode['kode_dok']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="kategori_dok.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
