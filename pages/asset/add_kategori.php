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
$menuId = 47; // Menu ID untuk halaman Kategori

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
    header('Location: kategori.php');
    exit;
}

// Ambil daftar kode asset untuk dropdown
$sqlKodeAsset = "SELECT id_kode, kode_asset FROM dbo.m_kode_asset ORDER BY kode_asset";
$stmtKodeAsset = sqlsrv_query($conn, $sqlKodeAsset);
$kodeAssets = [];
if ($stmtKodeAsset) {
    while ($row = sqlsrv_fetch_array($stmtKodeAsset, SQLSRV_FETCH_ASSOC)) {
        $kodeAssets[] = $row;
    }
    sqlsrv_free_stmt($stmtKodeAsset);
}

// Warna-warna yang tersedia
$warnaOptions = [
    '#FF5733' => 'Merah',
    '#33FF57' => 'Hijau',
    '#3357FF' => 'Biru',
    '#F3FF33' => 'Kuning',
    '#FF33F3' => 'Pink',
    '#33FFF3' => 'Cyan',
    '#8A2BE2' => 'Ungu',
    '#FF8C00' => 'Orange',
    '#A9A9A9' => 'Abu-abu',
    '#006400' => 'Hijau Gelap'
];

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kategori = $_POST['nama_kategori'];
    $warna = $_POST['warna'];
    $id_kode = !empty($_POST['id_kode']) ? $_POST['id_kode'] : null;
    $upduser = $_SESSION['UserName'];

    $sql = "INSERT INTO dbo.m_kategori (nama_kategori, warna, id_kode, upddate, upduser)
            VALUES (?, ?, ?, GETDATE(), ?)";
    $params = [$nama_kategori, $warna, $id_kode, $upduser];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Kategori berhasil ditambahkan.";
        header('Location: kategori.php');
        exit;
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Kategori</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .color-option {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .color-option.selected {
            border-color: #000;
        }
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Kategori</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="kategori.php">Kategori</a></li>
                        <li class="breadcrumb-item active">Tambah Kategori</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Kategori</h3>
                </div>
                <div class="card-body table-responsive">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="nama_kategori">Nama Kategori</label>
                            <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_kode">Kode Asset</label>
                            <select class="form-control" id="id_kode" name="id_kode">
                                <option value="">-- Pilih Kode Asset --</option>
                                <?php foreach ($kodeAssets as $kode): ?>
                                    <option value="<?= $kode['id_kode'] ?>">
                                        <?= htmlspecialchars($kode['kode_asset']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Pilih Warna</label>
                            <div>
                                <?php foreach ($warnaOptions as $kode => $nama): ?>
                                    <div class="color-option" style="background-color: <?= $kode ?>;" 
                                         data-color="<?= $kode ?>" 
                                         title="<?= $nama ?>"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="warna" name="warna" value="#FF5733">
                            <div class="mt-2">
                                Warna terpilih: 
                                <span id="warnaTerpilih" class="color-preview" style="background-color: #FF5733;"></span>
                                <span id="namaWarna">Merah</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="kategori.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Fungsi untuk memilih warna
        $('.color-option').click(function() {
            $('.color-option').removeClass('selected');
            $(this).addClass('selected');
            
            var warna = $(this).data('color');
            var nama = $(this).attr('title');
            
            $('#warna').val(warna);
            $('#warnaTerpilih').css('background-color', warna);
            $('#namaWarna').text(nama);
        });

        // Pilih warna pertama secara default
        $('.color-option:first').addClass('selected');
    });
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>