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
    header('Location: smmesin_inspecting_weaving.php');
    exit;
}

// Ambil data berdasarkan No Mesin
$mesinNo = $_GET['no'] ?? null;
if (!$mesinNo) {
    $_SESSION['error'] = "No Mesin tidak valid!";
    header('Location: smmesin_inspecting_weaving.php');
    exit;
}

$sql = "SELECT TypeId, MesinNo, MesinName 
        FROM dbo.SMMesinInspector 
        WHERE MesinNo = ?";
$params = [$mesinNo];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data!";
    header('Location: smmesin_inspecting_weaving.php');
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan!";
    header('Location: smmesin_inspecting_weaving.php');
    exit;
}

// Proses form edit data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newMesinNo = $_POST['mesinNo']; // No Mesin baru
    $typeId = $_POST['typeId'];
    $mesinName = $_POST['mesinName'];
    $updUser = $_SESSION['UserName'];

    // Cek apakah No Mesin baru sudah ada di database
    $sqlCheck = "SELECT MesinNo FROM dbo.SMMesinInspector WHERE MesinNo = ?";
    $paramsCheck = [$newMesinNo];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

    if ($stmtCheck === false) {
        $_SESSION['error'] = "Gagal memeriksa No Mesin!";
        header('Location: smmesin_inspecting_weaving.php');
        exit;
    }

    if (sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) && $newMesinNo != $mesinNo) {
        $_SESSION['error'] = "No Mesin sudah digunakan!";
        header('Location: smmesin_inspecting_weaving.php');
        exit;
    }

    // Update data
    $sqlUpdate = "UPDATE dbo.SMMesinInspector 
                  SET TypeId = ?, MesinNo = ?, MesinName = ?, UpdDate = GETDATE(), UpdUser = ? 
                  WHERE MesinNo = ?";
    $paramsUpdate = [$typeId, $newMesinNo, $mesinName, $updUser, $mesinNo];
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

    if ($stmtUpdate === false) {
        $_SESSION['error'] = "Gagal mengupdate data!";
    } else {
        $_SESSION['success'] = "Data berhasil diupdate!";
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
                    <h1 class="m-0">Edit Mesin</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="smmesin_inspecting_weaving.php">Mesin</a></li>
                        <li class="breadcrumb-item active">Edit Mesin</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Mesin</h3>
                </div>
                <div class="card-body table-responsive">
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
                                while ($rowType = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($rowType['TypeId'] == $row['TypeId']) ? 'selected' : '';
                                    echo "<option value='{$rowType['TypeId']}' {$selected}>{$rowType['TypeName']}</option>";
                                }
                                sqlsrv_free_stmt($stmt);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mesinNo">No Mesin</label>
                            <input type="text" class="form-control" id="mesinNo" name="mesinNo" value="<?= htmlspecialchars($row['MesinNo']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="mesinName">Nama Mesin</label>
                            <input type="text" class="form-control" id="mesinName" name="mesinName" value="<?= htmlspecialchars($row['MesinName']) ?>" required>
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