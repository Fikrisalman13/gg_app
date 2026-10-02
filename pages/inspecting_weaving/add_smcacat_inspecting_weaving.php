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

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];
$menuId = 13; // MenuId untuk Cacat Weaving Inspector

// Query untuk mengecek hak akses
$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek hak akses
$canAdd = false;
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $canAdd = $row['CanAdd'] == 1;
}
sqlsrv_free_stmt($stmt);

if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: smcacat_inspecting_weaving.php');
    exit;
}

// Query untuk mengambil data jenis cacat
$sqlType = "SELECT TypeId, TypeName FROM dbo.SMCacatType";
$stmtType = sqlsrv_query($conn, $sqlType);
if (!$stmtType) {
    die("Terjadi kesalahan dalam mengambil data: " . print_r(sqlsrv_errors(), true));
}

// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cacatKode = trim($_POST['cacatKode']);
    $cacatName = trim($_POST['cacatName']);
    $typeId = $_POST['typeId'];
    $updUser = $_SESSION['UserName'];

    // Validasi input tidak boleh kosong
    if (empty($cacatKode) || empty($cacatName)) {
        $_SESSION['error'] = "Kode Cacat dan Nama Cacat harus diisi.";
    } else {
        // Cek apakah data sudah ada
        $sqlCheck = "SELECT 1 FROM dbo.SMCacat WHERE CacatKode = ? OR CacatName = ?";
        $paramsCheck = [$cacatKode, $cacatName];
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);

        if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
            $_SESSION['error'] = "Kode Cacat atau Nama Cacat sudah ada. Silakan gunakan data yang berbeda.";
        } else {
            // Insert data jika tidak ada duplikat
            $sqlInsert = "INSERT INTO dbo.SMCacat (CacatKode, CacatName, TypeId, UpdDate, UpdUser) 
                          VALUES (?, ?, ?, GETDATE(), ?)";
            $paramsInsert = [$cacatKode, $cacatName, $typeId, $updUser];
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

            if ($stmtInsert === false) {
                $_SESSION['error'] = "Terjadi kesalahan saat menambahkan data.";
            } else {
                $_SESSION['success'] = "Data Cacat berhasil ditambahkan.";
                header('Location: smcacat_inspecting_weaving.php');
                exit;
            }
        }
        sqlsrv_free_stmt($stmtCheck);
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Cacat</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Cacat</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Tambah Cacat</h3>
                </div>
                <div class="card-body table-responsive">
                    <?php if (isset($_SESSION['error'])): ?>
                        <script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: '<?= $_SESSION['error']; ?>'
                            });
                        </script>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <script>
                            Swal.fire({
                                icon: 'success',
                                title: 'Sukses!',
                                text: '<?= $_SESSION['success']; ?>'
                            });
                        </script>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="cacatKode">Kode Cacat</label>
                            <input type="text" class="form-control" id="cacatKode" name="cacatKode" required>
                        </div>
                        <div class="form-group">
                            <label for="cacatName">Nama Cacat</label>
                            <input type="text" class="form-control" id="cacatName" name="cacatName" required>
                        </div>
                        <div class="form-group">
                            <label for="typeId">Jenis Cacat</label>
                            <select class="form-control" id="typeId" name="typeId" required>
                                <?php while ($row = sqlsrv_fetch_array($stmtType, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $row['TypeId'] ?>"><?= htmlspecialchars($row['TypeName']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="smcacat_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
