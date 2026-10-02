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
$menuId = 41; // Ganti dengan MenuId untuk jabatan

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
    header('Location: jabatan.php');
    exit;
}

// Ambil data subbagian
$subbagian = [];
$query = "
    SELECT m_subbag.id_subbag, m_subbag.subbag, m_bag.bagian, m_dept.dept 
    FROM dbo.m_subbag
    LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
    LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
    ORDER BY m_dept.dept, m_bag.bagian, m_subbag.subbag
";
$stmt = sqlsrv_query($conn, $query);
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $subbagian[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jabatan = $_POST['jabatan'];
    $id_subbag = $_POST['id_subbag'];
    $upduser = $_SESSION['UserName'];

    // Ambil id_bag dan id_dept dari id_subbag
    $infoQuery = "
        SELECT m_bag.id_bag, m_dept.id_dept 
        FROM dbo.m_subbag
        LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
        LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
        WHERE m_subbag.id_subbag = ?";
    $infoStmt = sqlsrv_query($conn, $infoQuery, [$id_subbag]);
    $info = sqlsrv_fetch_array($infoStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($infoStmt);

    $id_bag = $info['id_bag'];
    $id_dept = $info['id_dept'];

    $sql = "INSERT INTO dbo.m_jab (jabatan, id_subbag, id_bag, id_dept, upddate, upduser)
            VALUES (?, ?, ?, ?, GETDATE(), ?)";
    $params = [$jabatan, $id_subbag, $id_bag, $id_dept, $upduser];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Jabatan berhasil ditambahkan.";
        header('Location: jabatan.php');
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
                    <h1 class="m-0">Tambah Jabatan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="jabatan.php">Jabatan</a></li>
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
                    <h3 class="card-title">Form Tambah Jabatan</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="jabatan">Nama Jabatan</label>
                            <input type="text" class="form-control" id="jabatan" name="jabatan" required>
                        </div>
                        <div class="form-group">
                            <label for="id_subbag">Sub Bagian</label>
                            <select class="form-control" id="id_subbag" name="id_subbag" required>
                                <option value="">-- Pilih Sub Bagian --</option>
                                <?php foreach ($subbagian as $sb): ?>
                                    <option value="<?= $sb['id_subbag'] ?>">
                                        <?= htmlspecialchars($sb['dept']) ?> - <?= htmlspecialchars($sb['bagian']) ?> - <?= htmlspecialchars($sb['subbag']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="jabatan.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>


