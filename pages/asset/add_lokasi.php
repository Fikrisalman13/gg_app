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

// MenuId khusus untuk halaman Lokasi
$menuId = 50; // Sesuaikan dengan MenuId untuk lokasi di database Anda

// Ambil hak akses
$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = [];
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Jika tidak punya hak tambah
if (isset($permissions['CanAdd']) && $permissions['CanAdd'] == 0) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data lokasi.";
    header('Location: lokasi.php');
    exit;
}

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lokasi = $_POST['nama_lokasi'] ?? '';
    $divisi = $_POST['divisi'] ?? '';
    $upduser = $_SESSION['UserName'];

    // Validasi input
    if (empty($nama_lokasi) || empty($divisi)) {
        $_SESSION['error'] = "Nama lokasi dan divisi harus diisi!";
    } else {
        // Insert data ke database
        $sql = "INSERT INTO dbo.m_lokasi (nama_lokasi, divisi, upddate, upduser) 
                VALUES (?, ?, GETDATE(), ?)";
        $params = [$nama_lokasi, $divisi, $upduser];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal menambahkan lokasi: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = "Lokasi berhasil ditambahkan!";
            header('Location: lokasi.php');
            exit;
        }
    }
}

// Ambil data divisi dari tabel m_dept
$sql_dept = "SELECT dept FROM dbo.m_dept ORDER BY dept";
$stmt_dept = sqlsrv_query($conn, $sql_dept);
$divisi_options = [];
if ($stmt_dept === false) {
    $_SESSION['error'] = "Gagal mengambil data divisi: " . print_r(sqlsrv_errors(), true);
} else {
    while ($row = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
        $divisi_options[] = $row['dept'];
    }
    sqlsrv_free_stmt($stmt_dept);
}

ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Lokasi</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="lokasi.php">Lokasi</a></li>
                        <li class="breadcrumb-item active">Tambah Lokasi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Lokasi</h3>
                </div>
                <div class="card-body">
                    <form action="add_lokasi.php" method="post">
                        <div class="form-group">
                            <label for="nama_lokasi">Nama Lokasi</label>
                            <input type="text" class="form-control" id="nama_lokasi" name="nama_lokasi" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="divisi">Divisi</label>
                            <select class="form-control" id="divisi" name="divisi" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisi_options as $divisi): ?>
                                    <option value="<?= htmlspecialchars($divisi) ?>"><?= htmlspecialchars($divisi) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                            <a href="lokasi.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
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
<script>
    $(document).ready(function () {
        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); endif; ?>
    });
</script>
