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

// Ambil MenuId untuk Mesin Type Weaving Inspector
$menuId = 14; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT CanEdit 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dan ambil hak akses
$canEdit = false;
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canEdit = $row['CanEdit'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

// Cek apakah pengguna memiliki hak akses CanEdit
if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

// Ambil data berdasarkan ID
$typeId = $_GET['id'] ?? null;
if (!$typeId) {
    $_SESSION['error'] = "ID tidak valid!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

$sql = "SELECT TypeId, TypeName 
        FROM dbo.SMMesinType 
        WHERE TypeId = ?";
$params = [$typeId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan!";
    header('Location: mesintype_inspecting_weaving.php');
    exit;
}

// Proses form edit data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $typeName = $_POST['typeName'];
    $updUser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.SMMesinType 
            SET TypeName = ?, UpdDate = GETDATE(), UpdUser = ? 
            WHERE TypeId = ?";
    $params = [$typeName, $updUser, $typeId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal mengupdate data!";
    } else {
        $_SESSION['success'] = "Data berhasil diupdate!";
        header('Location: mesintype_inspecting_weaving.php');
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
                    <h1 class="m-0">Edit Mesin Tipe</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="mesintype_inspecting_weaving.php">Mesin Tipe</a></li>
                        <li class="breadcrumb-item active">Edit Mesin Tipe</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Mesin Tipe</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="typeName">Nama Tipe</label>
                            <input type="text" class="form-control" id="typeName" name="typeName" value="<?= htmlspecialchars($row['TypeName']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="mesintype_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>