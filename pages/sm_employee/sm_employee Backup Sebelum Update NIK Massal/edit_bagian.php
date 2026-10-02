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
$menuId = 39; // Ganti sesuai MenuId untuk modul Bagian

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
    header('Location:/gg_app/pages/sm_employee/bagian.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Bagian tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/bagian.php');
    exit;
}
$id_bag = $_GET['id'];

// Ambil data bagian
$sql = "SELECT id_bag, bagian, sn_bagian, id_dept FROM dbo.m_bag WHERE id_bag = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_bag]);
$bagData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$bagData) {
    $_SESSION['error'] = "Data Bagian tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/bagian.php');
    exit;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bagian = $_POST['bagian'];
    $sn_bagian = $_POST['sn_bagian'];
    $id_dept = $_POST['id_dept'];
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_bag 
            SET bagian = ?, sn_bagian = ?, id_dept = ?, upddate = GETDATE(), upduser = ? 
            WHERE id_bag = ?";
    $params = [$bagian, $sn_bagian, $id_dept, $upduser, $id_bag];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Bagian berhasil diperbarui.";
        header('Location: /gg_app/pages/sm_employee/bagian.php');
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
                    <h1 class="m-0">Edit Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="bagian.php">Bagian</a></li>
                        <li class="breadcrumb-item active">Edit Bagian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Bagian</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="bagian">Nama Bagian</label>
                            <input type="text" class="form-control" id="bagian" name="bagian" value="<?= htmlspecialchars($bagData['bagian'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="sn_bagian">Alias</label>
                            <input type="text" class="form-control" id="sn_bagian" name="sn_bagian" value="<?= htmlspecialchars($bagData['sn_bagian'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="id_dept">Departemen</label>
                            <select class="form-control" name="id_dept" id="id_dept" required>
                                <option value="">-- Pilih Departemen --</option>
                                <?php
                                $sqlDept = "SELECT id_dept, dept FROM dbo.m_dept ORDER BY dept";
                                $stmtDept = sqlsrv_query($conn, $sqlDept);
                                while ($rowDept = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($rowDept['id_dept'] == $bagData['id_dept']) ? 'selected' : '';
                                    echo "<option value='{$rowDept['id_dept']}' $selected>" . htmlspecialchars($rowDept['dept']) . "</option>";
                                }
                                ?>
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
