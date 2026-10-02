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

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

$groupId = $_SESSION['GroupId'];
$menuId = 40; // Ganti sesuai MenuId untuk modul Subbagian

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
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID Subbag tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}
$id_subbag = $_GET['id'];

// Ambil data subbag
$sql = "SELECT id_subbag, subbag, sn_subbag, id_bag FROM dbo.m_subbag WHERE id_subbag = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_subbag]);
$subbagData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$subbagData) {
    $_SESSION['error'] = "Data Subbag tidak ditemukan.";
    header('Location:/gg_app/pages/sm_employee/subbag.php');
    exit;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subbag = $_POST['subbag'];
    $sn_subbag = $_POST['sn_subbag'];
    $id_bag = $_POST['id_bag'];
    $upduser = $_SESSION['UserName'];

    $sql = "UPDATE dbo.m_subbag 
            SET subbag = ?, sn_subbag = ?, id_bag = ?, upddate = GETDATE(), upduser = ? 
            WHERE id_subbag = ?";
    $params = [$subbag, $sn_subbag, $id_bag, $upduser, $id_subbag];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error'] = "Gagal memperbarui data: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['success'] = "Data Subbag berhasil diperbarui.";
        header('Location: /gg_app/pages/sm_employee/subbag.php');
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
                    <h1 class="m-0">Edit Subbag</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="subbag.php">Subbag</a></li>
                        <li class="breadcrumb-item active">Edit Subbag</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Subbag</h3>
                </div>
                <div class="card-body table-responsive">
                    <form method="POST">
                        <div class="form-group">
                            <label for="subbag">Nama Subbag</label>
                            <input type="text" class="form-control" id="subbag" name="subbag" value="<?= htmlspecialchars($subbagData['subbag'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="sn_subbag">Singkatan</label>
                            <input type="text" class="form-control" id="sn_subbag" name="sn_subbag" value="<?= htmlspecialchars($subbagData['sn_subbag'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="id_bag">Bagian</label>
                            <select class="form-control" name="id_bag" id="id_bag" required>
                                <option value="">-- Pilih Bagian --</option>
                                <?php
                                $sqlBag = "SELECT m_bag.id_bag, m_bag.bagian, m_dept.dept 
                                           FROM dbo.m_bag
                                           LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
                                           ORDER BY m_dept.dept, m_bag.bagian";
                                $stmtBag = sqlsrv_query($conn, $sqlBag);
                                while ($rowBag = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($rowBag['id_bag'] == $subbagData['id_bag']) ? 'selected' : '';
                                    echo "<option value='{$rowBag['id_bag']}' $selected>" . htmlspecialchars("{$rowBag['dept']} - {$rowBag['bagian']}") . "</option>";
                                }
                                ?>
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

