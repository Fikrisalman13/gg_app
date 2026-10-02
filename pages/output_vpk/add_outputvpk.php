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
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Set zona waktu
date_default_timezone_set('Asia/Jakarta');

// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Hak akses (ganti MenuId sesuai kebutuhan)
$groupId = $_SESSION['GroupId'];
$menuId  = 67; // contoh MenuId untuk halaman packing
$sql     = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params  = [$groupId, $menuId];
$stmt    = sqlsrv_query($conn, $sql, $params);

$canAdd = false;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canAdd = $row['CanAdd'] == 1;
}
sqlsrv_free_stmt($stmt);

if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: data.php');
    exit;
}

// Default nilai form
$tanggal = date('Y-m-d');
$qty     = '';
$qty_a1  = '';

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $qty     = $_POST['qty'] ?? '';
    $qty_a1  = $_POST['qty_a1'] ?? '';


    // Validasi
    if (empty($tanggal) || !is_numeric($qty) || !is_numeric($qty_a1)) {
        $_SESSION['error'] = "Semua field wajib diisi dengan benar!";
    } else {
        $sql = "INSERT INTO dbo.packing_output (tanggal, qty, qty_a1)
                VALUES (?, ?, ?)";
        $params = [$tanggal, (float)$qty, (float)$qty_a1];
        $stmt   = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['success'] = "Data packing berhasil ditambahkan.";
            header('Location: data.php');
            exit;
        } else {
            $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
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
                    <h1 class="m-0">Input Data Packing</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="data.php">Data Packing</a></li>
                        <li class="breadcrumb-item active">Input</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Input Data Packing</h3>
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
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                        <a href="data.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<script>
    $(function(){
        $('#tanggal').focus();
    });
</script>

