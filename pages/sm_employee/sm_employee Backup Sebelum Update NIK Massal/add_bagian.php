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
$menuId = 39; // Menu ID untuk halaman Bagian

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
    header('Location: bagian.php');
    exit;
}

// Ambil daftar departemen
$departemen = [];
$deptSql = "SELECT id_dept, dept FROM dbo.m_dept ORDER BY dept";
$deptStmt = sqlsrv_query($conn, $deptSql);
if ($deptStmt !== false) {
    while ($row = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
        $departemen[] = $row;
    }
    sqlsrv_free_stmt($deptStmt);
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bagian = $_POST['bagian'];
    $sn_bagian = $_POST['sn_bagian'];
    $id_dept = $_POST['id_dept'];
    $upduser = $_SESSION['UserName'];

    $sql = "INSERT INTO dbo.m_bag (bagian, sn_bagian, id_dept, upddate, upduser)
            VALUES (?, ?, ?, GETDATE(), ?)";
    $params = [$bagian, $sn_bagian, $id_dept, $upduser];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Bagian berhasil ditambahkan.";
        header('Location: bagian.php');
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
                    <h1 class="m-0">Tambah Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="bagian.php">Bagian</a></li>
                        <li class="breadcrumb-item active">Tambah Bagian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Bagian</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="bagian">Nama Bagian</label>
                            <input type="text" class="form-control" id="bagian" name="bagian" required>
                        </div>
                        <div class="form-group">
                            <label for="sn_bagian">Singkatan</label>
                            <input type="text" class="form-control" id="sn_bagian" name="sn_bagian" required>
                        </div>
                        <div class="form-group">
                            <label for="id_dept">Departemen</label>
                            <select class="form-control" id="id_dept" name="id_dept" required>
                                <option value="">-- Pilih Departemen --</option>
                                <?php foreach ($departemen as $dept): ?>
                                    <option value="<?= $dept['id_dept'] ?>"><?= htmlspecialchars($dept['dept']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="bagian.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
