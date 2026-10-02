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
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 40; // Ganti dengan MenuId untuk subbag

// Cek hak akses CanAdd
$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canAdd = false;
if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $canAdd = $row && $row['CanAdd'] == 1;
    sqlsrv_free_stmt($stmt);
}

if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: subbag.php');
    exit;
}

// Ambil data departemen dan bagian
$departemen = [];
$bagian = [];

$deptSql = "SELECT id_dept, dept FROM dbo.m_dept ORDER BY dept";
$deptStmt = sqlsrv_query($conn, $deptSql);
if ($deptStmt !== false) {
    while ($row = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
        $departemen[] = $row;
    }
    sqlsrv_free_stmt($deptStmt);
}

$bagSql = "SELECT m_bag.id_bag, m_bag.bagian, m_bag.id_dept, m_dept.dept 
           FROM dbo.m_bag 
           LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept 
           ORDER BY m_dept.dept, m_bag.bagian";
$bagStmt = sqlsrv_query($conn, $bagSql);
if ($bagStmt !== false) {
    while ($row = sqlsrv_fetch_array($bagStmt, SQLSRV_FETCH_ASSOC)) {
        $bagian[] = $row;
    }
    sqlsrv_free_stmt($bagStmt);
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subbag = $_POST['subbag'];
    $sn_subbag = $_POST['sn_subbag'];
    $id_bag = $_POST['id_bag'];
    $upduser = $_SESSION['UserName'];

    $sql = "INSERT INTO dbo.m_subbag (subbag, sn_subbag, id_bag, upddate, upduser)
            VALUES (?, ?, ?, GETDATE(), ?)";
    $params = [$subbag, $sn_subbag, $id_bag, $upduser];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Sub Bagian berhasil ditambahkan.";
        header('Location: subbag.php');
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
                    <h1 class="m-0">Tambah Sub Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="subbag.php">Sub Bagian</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Sub Bagian</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="subbag">Nama Sub Bagian</label>
                            <input type="text" class="form-control" id="subbag" name="subbag" required>
                        </div>
                        <div class="form-group">
                            <label for="sn_subbag">Singkatan</label>
                            <input type="text" class="form-control" id="sn_subbag" name="sn_subbag" required>
                        </div>
                        <div class="form-group">
                            <label for="id_bag">Bagian</label>
                            <select class="form-control" id="id_bag" name="id_bag" required>
                                <option value="">-- Pilih Bagian --</option>
                                <?php foreach ($bagian as $b): ?>
                                    <option value="<?= $b['id_bag'] ?>">
                                        <?= htmlspecialchars($b['dept']) ?> - <?= htmlspecialchars($b['bagian']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="subbag.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
