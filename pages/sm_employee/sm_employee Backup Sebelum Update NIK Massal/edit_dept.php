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
// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil hak akses berdasarkan session
$groupId = $_SESSION['GroupId'];
$menuId = 38; // Sesuaikan MenuId untuk Department

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

// Jika tidak punya hak edit, redirect
if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}

// Cek parameter ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Department tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}
$deptId = $_GET['id'];

// Ambil data department berdasarkan ID
$sql = "SELECT id_dept, dept, sn_dept FROM dbo.m_dept WHERE id_dept = ?";
$stmt = sqlsrv_query($conn, $sql, [$deptId]);
$deptData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$deptData) {
    $_SESSION['error'] = "Data Department tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/dept.php');
    exit;
}

// Proses form update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dept = $_POST['dept'];
    $sn_dept = $_POST['sn_dept'];
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_dept 
            SET dept = ?, sn_dept = ?, upddate = GETDATE(), upduser = ? 
            WHERE id_dept = ?";
    $params = [$dept, $sn_dept, $upduser, $deptId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Department berhasil diperbarui.";
        header('Location: /gg_app/pages/sm_employee/dept.php');
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
                    <h1 class="m-0">Edit Departemen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="dept.php">Departemen</a></li>
                        <li class="breadcrumb-item active">Edit Departemen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Departemen</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="dept">Nama Departemen</label>
                            <input type="text" class="form-control" id="dept" name="dept" value="<?= htmlspecialchars($deptData['dept']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="sn_dept">Alias</label>
                            <input type="text" class="form-control" id="sn_dept" name="sn_dept" value="<?= htmlspecialchars($deptData['sn_dept']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="dept.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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