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

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Cacat Weaving Inspector
$menuId = 13; // Sesuaikan dengan MenuId yang benar

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
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

// Ambil ID dari parameter URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Cacat tidak ditemukan.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}
$cacatId = $_GET['id'];

// Query untuk mengambil data cacat berdasarkan ID
$sql = "SELECT CacatId, CacatKode, CacatName, TypeId
        FROM dbo.SMCacat 
        WHERE CacatId = ?";
$params = [$cacatId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
}

$cacatData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$cacatData) {
    $_SESSION['error'] = "Data Cacat tidak ditemukan.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

// Query untuk mengambil data jenis cacat dan kategori cacat
$sqlType = "SELECT TypeId, TypeName FROM dbo.SMCacatType";


$stmtType = sqlsrv_query($conn, $sqlType);


if ($stmtType === false) {
    die("Terjadi kesalahan dalam mengambil data: " . print_r(sqlsrv_errors(), true));
}

// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cacatKode = $_POST['cacatKode'];
    $cacatName = $_POST['cacatName'];
    $typeId = $_POST['typeId'];


    // Query untuk update data
    $sql = "UPDATE dbo.SMCacat 
            SET CacatKode = ?, CacatName = ?, TypeId = ?, UpdDate = GETDATE(), UpdUser = ? 
            WHERE CacatId = ?";
    $params = [$cacatKode, $cacatName, $typeId, $_SESSION['UserName'], $cacatId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Terjadi kesalahan saat memperbarui data.";
    } else {
        $_SESSION['success'] = "Data Cacat berhasil diperbarui.";
        header('Location: smcacat_inspecting_weaving.php');
        exit;
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Cacat Weaving Inspector</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Cacat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="smcacat_inspecting_weaving.php">Cacat</a></li>
                        <li class="breadcrumb-item active">Edit Cacat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Cacat</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="cacatKode">Kode Cacat</label>
                            <input type="text" class="form-control" id="cacatKode" name="cacatKode" value="<?= htmlspecialchars($cacatData['CacatKode']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cacatName">Nama Cacat</label>
                            <input type="text" class="form-control" id="cacatName" name="cacatName" value="<?= htmlspecialchars($cacatData['CacatName']) ?>" required>
                        </div>
                        <div class="form-group">
                        <label for="typeId">Jenis Cacat</label>
                        <select class="form-control" id="typeId" name="typeId" required>
                             <?php while ($row = sqlsrv_fetch_array($stmtType, SQLSRV_FETCH_ASSOC)): ?>
                                <option value="<?= $row['TypeId'] ?>" <?= $row['TypeId'] == $cacatData['TypeId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['TypeName']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="smcacat_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>



</body>
</html>

<?php include '../../includes/footer.php'; ?>