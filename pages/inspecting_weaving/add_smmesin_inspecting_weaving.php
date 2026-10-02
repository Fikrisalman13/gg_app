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

// Ambil MenuId untuk SMMesin Inspector Weaving
$menuId = 15; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT CanAdd 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dan ambil hak akses
$canAdd = false;
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canAdd = $row['CanAdd'] == 1;
    }
    sqlsrv_free_stmt($stmt);
}

// Cek apakah pengguna memiliki hak akses CanAdd
if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data!";
    header('Location: smmesin_inspecting_weaving.php');
    exit;
}

// Proses form tambah data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $typeId = $_POST['typeId'];
    $mesinNo = $_POST['mesinNo'];
    $mesinName = $_POST['mesinName'];
    $updUser = $_SESSION['UserName'];

    $sql = "INSERT INTO dbo.SMMesinInspector (TypeId, MesinNo, MesinName, UpdDate, UpdUser) 
            VALUES (?, ?, ?, GETDATE(), ?)";
    $params = [$typeId, $mesinNo, $mesinName, $updUser];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menambahkan data!";
    } else {
        $_SESSION['success'] = "Data berhasil ditambahkan!";
        header('Location: smmesin_inspecting_weaving.php');
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
                    <h1 class="m-0">Tambah Mesin</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="smmesin_inspecting_weaving.php">Mesin</a></li>
                        <li class="breadcrumb-item active">Tambah Mesin</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Mesin</h3>
                </div>
                <div class="card-body card-body table-responsive">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="typeId">Mesin Tipe</label>
                            <select class="form-control" id="typeId" name="typeId" required>
                                <?php
                                $sql = "SELECT TypeId, TypeName FROM dbo.SMMesinType";
                                $stmt = sqlsrv_query($conn, $sql);
                                if ($stmt === false) {
                                    die("Gagal mengambil data type mesin: " . print_r(sqlsrv_errors(), true));
                                }
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='{$row['TypeId']}'>{$row['TypeName']}</option>";
                                }
                                sqlsrv_free_stmt($stmt);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mesinNo">No Mesin</label>
                            <input type="text" class="form-control" id="mesinNo" name="mesinNo" required>
                        </div>
                        <div class="form-group">
                            <label for="mesinName">Nama Mesin</label>
                            <input type="text" class="form-control" id="mesinName" name="mesinName" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="smmesin_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../includes/footer.php'; ?>