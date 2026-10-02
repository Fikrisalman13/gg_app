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
$menuId = 40; // Ganti dengan MenuId untuk modul Jabatan

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
    header('Location: jabatan.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Jabatan tidak ditemukan.";
    header('Location: jabatan.php');
    exit;
}
$id_jab = $_GET['id'];

// Ambil data jabatan
$sql = "SELECT id_jab, jabatan, id_subbag FROM dbo.m_jab WHERE id_jab = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_jab]);
$jabData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$jabData) {
    $_SESSION['error'] = "Data Jabatan tidak ditemukan.";
    header('Location: jabatan.php');
    exit;
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jabatan = $_POST['jabatan'];
    $id_subbag = $_POST['id_subbag'];
    $upduser = $_SESSION['UserName'];

    // Ambil id_bag dan id_dept dari id_subbag
    $query = "SELECT id_bag FROM dbo.m_subbag WHERE id_subbag = ?";
    $stmt = sqlsrv_query($conn, $query, [$id_subbag]);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_bag = $row['id_bag'] ?? null;

    $query = "SELECT id_dept FROM dbo.m_bag WHERE id_bag = ?";
    $stmt = sqlsrv_query($conn, $query, [$id_bag]);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id_dept = $row['id_dept'] ?? null;

    $sql = "UPDATE dbo.m_jab
            SET jabatan = ?, id_subbag = ?, id_bag = ?, id_dept = ?, upddate = GETDATE(), upduser = ?
            WHERE id_jab = ?";
    $params = [$jabatan, $id_subbag, $id_bag, $id_dept, $upduser, $id_jab];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Jabatan berhasil diperbarui.";
        header('Location: jabatan.php');
        exit;
    }
}

// Ambil data subbag
$subbagList = [];
$sql = "SELECT s.id_subbag, s.subbag, b.bagian, d.dept
        FROM dbo.m_subbag s
        LEFT JOIN dbo.m_bag b ON s.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
        ORDER BY d.dept, b.bagian, s.subbag";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $subbagList[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Jabatan</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Jabatan</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="jabatan.php">Jabatan</a></li>
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
                    <h3 class="card-title">Form Edit Jabatan</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="jabatan">Nama Jabatan</label>
                            <input type="text" class="form-control" id="jabatan" name="jabatan" value="<?= htmlspecialchars($jabData['jabatan'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="id_subbag">Subbagian</label>
                            <select class="form-control" id="id_subbag" name="id_subbag" required>
                                <option value="">-- Pilih Subbagian --</option>
                                <?php foreach ($subbagList as $sub): ?>
                                    <option value="<?= $sub['id_subbag'] ?>" <?= ($sub['id_subbag'] == $jabData['id_subbag']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars("{$sub['dept']} - {$sub['bagian']} - {$sub['subbag']}") ?>
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

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
