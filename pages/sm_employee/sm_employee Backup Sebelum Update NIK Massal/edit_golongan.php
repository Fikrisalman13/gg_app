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

$groupId = $_SESSION['GroupId'];
$menuId = 41; // MenuId untuk modul Golongan

// Cek hak akses
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
    header('Location: golongan.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Golongan tidak ditemukan.";
    header('Location: golongan.php');
    exit;
}
$id_gol = $_GET['id'];

// Ambil data golongan
$sql = "SELECT id_gol, golongan, umr FROM dbo.m_gol WHERE id_gol = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_gol]);
$golData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$golData) {
    $_SESSION['error'] = "Data Golongan tidak ditemukan.";
    header('Location: golongan.php');
    exit;
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $golongan = $_POST['golongan'];
    $umr = floatval(str_replace([','], [''], $_POST['umr']));
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_gol SET golongan = ?, umr = ?, upddate = GETDATE(), upduser = ? WHERE id_gol = ?";
    $params = [$golongan, $umr, $upduser, $id_gol];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Golongan berhasil diperbarui.";
        header('Location: golongan.php');
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
                    <h1 class="m-0">Edit Golongan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="golongan.php">Golongan</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Golongan</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="golongan">Nama Golongan</label>
                            <input type="text" class="form-control" id="golongan" name="golongan" value="<?= htmlspecialchars($golData['golongan']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="umr">UMR</label>
                            <input type="number" step="0.0001" class="form-control" id="umr" name="umr" value="<?= number_format($golData['umr'], 4, '.', '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                        <a href="golongan.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
