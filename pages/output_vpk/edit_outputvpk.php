<?php
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set zona waktu
date_default_timezone_set('Asia/Jakarta');

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Hak akses (ganti MenuId sesuai kebutuhan)
$groupId = $_SESSION['GroupId'];
$menuId  = 67; // contoh MenuId
$sql     = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params  = [$groupId, $menuId];
$stmt    = sqlsrv_query($conn, $sql, $params);

$canEdit = false;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canEdit = $row['CanEdit'] == 1;
}
sqlsrv_free_stmt($stmt);

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: data.php');
    exit;
}

// Ambil ID dari URL
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    $_SESSION['error'] = "ID data tidak valid.";
    header('Location: data.php');
    exit;
}

// Ambil data lama
$sql = "SELECT * FROM dbo.packing_output WHERE id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt || !($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: data.php');
    exit;
}
sqlsrv_free_stmt($stmt);

// Isi form default
$tanggal = $data['tanggal']->format('Y-m-d');
$qty     = $data['qty'];
$qty_a1  = $data['qty_a1'];

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $qty     = $_POST['qty'] ?? '';
    $qty_a1  = $_POST['qty_a1'] ?? '';

    if (empty($tanggal) || !is_numeric($qty) || !is_numeric($qty_a1)) {
        $_SESSION['error'] = "Semua field wajib diisi dengan benar!";
    } else {
        $sql = "UPDATE dbo.packing_output 
                SET tanggal = ?, qty = ?, qty_a1 = ?
                WHERE id = ?";
        $params = [$tanggal, (float)$qty, (float)$qty_a1, $id];
        $stmt   = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['success'] = "Data berhasil diperbarui.";
            header('Location: data.php');
            exit;
        } else {
            $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
        }
    }
}
ob_end_flush();
?>

<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Data Packing</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="data.php">Data Packing</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-warning">
                    <h3 class="card-title">Form Edit Data Packing</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <div class="form-group">
                            <label for="tanggal">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" id="tanggal" name="tanggal" class="form-control" 
                                   value="<?= htmlspecialchars($tanggal) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="qty">Quantity Packing <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="qty" name="qty" class="form-control" 
                                   value="<?= htmlspecialchars($qty) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="qty_a1">Quantity A1 <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="qty_a1" name="qty_a1" class="form-control" 
                                   value="<?= htmlspecialchars($qty_a1) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update
                        </button>
                        <a href="data.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Tanda <span class="text-danger">*</span> wajib diisi</small>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(function(){
        $('#tanggal').focus();
    });
</script>

<?php include '../../includes/footer.php'; ?>
