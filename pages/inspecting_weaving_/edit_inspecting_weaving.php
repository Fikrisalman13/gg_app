<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireEdit($conn, MENU_SHIFT_INSPECT);

// ===================================================
// 7. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Id No CP dari URL

$noCP = $_GET['id'] ?? null;

if (!$noCP) {
    $_SESSION['error'] = "Invalid NoCP!";
    header("Location: inspecting_weaving.php");
    exit;
}

$sql = "
    SELECT 
        NoCP, InspectDate, EmpId, ShiftId, ArtikelId, Lot, NoBeam,
        WRMCId, SZMCId, WVMCId, INSMCId,
        PanjangKainW, PanjangKainI,
        LebarKainW, LebarKainI,
        WastKiri, WastKanan,
        Keterangan,
        UpdDate, UpdUser
    FROM dbo.FormInspectHd
    WHERE NoCP = ?
";

$stmt = sqlsrv_query($conn, $sql, [$noCP]);

if ($stmt === false) {
    error_log("Failed to fetch NoCP data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Gagal mengambil data.";
    header("Location: inspecting_weaving.php");
    exit;
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$data) {
    $_SESSION['error'] = "Data tidak ditemukan!";
    header("Location: inspecting_weaving.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $inspectDate   = trim($_POST['inspectDate'] ?? '');
    $empId         = trim($_POST['empId'] ?? '');
    $artikelId     = trim($_POST['artikelId'] ?? '');
    $lot           = trim($_POST['lot'] ?? '');
    $noBeam        = trim($_POST['noBeam'] ?? '');
    $WVMCId        = trim($_POST['WVMCId'] ?? '');
    $PanjangKainW  = trim($_POST['PanjangKainW'] ?? '');
    $PanjangKainI  = trim($_POST['PanjangKainI'] ?? '');
    $LebarKainW    = trim($_POST['LebarKainW'] ?? '');
    $LebarKainI    = trim($_POST['LebarKainI'] ?? '');
    $Keterangan    = trim($_POST['Keterangan'] ?? '');

    $updUser = $_SESSION['UserName'];
    $updDate = date('Y-m-d H:i:s');

    // Validasi wajib isi
    if (empty($inspectDate) || empty($empId) || empty($artikelId)) {
        $_SESSION['error'] = "Harap isi semua field yang diperlukan!";
    } else {

        // Konversi tanggal ke format SQL
        $inspectDate = date('Y-m-d H:i:s', strtotime($inspectDate));

        $sqlUpdate = "
            UPDATE dbo.FormInspectHd SET
                InspectDate     = ?, 
                EmpId           = ?, 
                ArtikelId       = ?, 
                Lot             = ?, 
                NoBeam          = ?, 
                WVMCId          = ?, 
                PanjangKainW    = ?, 
                PanjangKainI    = ?, 
                LebarKainW      = ?, 
                LebarKainI      = ?, 
                Keterangan      = ?, 
                UpdDate         = ?, 
                UpdUser         = ?
            WHERE NoCP = ?
        ";

        $paramsUpdate = [
            $inspectDate, $empId, $artikelId, $lot, $noBeam,
            $WVMCId, $PanjangKainW, $PanjangKainI,
            $LebarKainW, $LebarKainI, $Keterangan,
            $updDate, $updUser, $noCP
        ];

        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

        if ($stmtUpdate) {
            sqlsrv_free_stmt($stmtUpdate);
            $_SESSION['success'] = "Data berhasil diupdate!";
            header("Location: inspecting_weaving.php");
            exit;
        } else {
            $_SESSION['error'] = "Terjadi kesalahan saat update: " . print_r(sqlsrv_errors(), true);
        }
    }
}
?>

<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->

<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Data Inspecting Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="inspecting_weaving.php">Inspecting Weaving</a></li>
                        <li class="breadcrumb-item active">Edit Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- ALERT MESSAGE -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="icon fas fa-check"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="icon fas fa-ban"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= $themeColor ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit – NoCP: <?= htmlspecialchars($data['NoCP']) ?>
                    </h3>
                </div>

                <div class="card-body">

                    <form method="POST" action="" autocomplete="off">

                        <!-- No CP -->
                        <div class="form-group">
                            <label>No CP</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($data['NoCP']) ?>" readonly>
                        </div>

                        <!-- Tanggal Inspeksi -->
                        <div class="form-group">
                            <label>Tanggal Inspeksi *</label>
                            <?php
                            $defaultDate = $data['InspectDate']->format('Y-m-d\TH:i');
                            ?>
                            <input type="datetime-local" class="form-control" name="inspectDate" value="<?= $defaultDate ?>" required>
                        </div>

                        <!-- Nama Inspektor -->
                        <div class="form-group">
                            <label>Nama Inspektor *</label>
                            <select class="form-control" name="empId" required>
                                <option value="">Pilih Inspektor</option>
                                <?php
                                $sql = "SELECT EmpId, EmpName FROM dbo.SMEmployeeInspector";
                                $stmt = sqlsrv_query($conn, $sql);
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                    $selected = ($row['EmpId'] == $data['EmpId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $row['EmpId'] ?>" <?= $selected ?>>
                                        <?= $row['EmpName'] ?>
                                    </option>
                                <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                            </select>
                        </div>

                        <!-- Artikel -->
                        <div class="form-group">
                            <label>Kode Artikel *</label>
                            <select class="form-control" name="artikelId" required>
                                <option value="">Pilih Artikel</option>
                                <?php
                                $sql = "SELECT ArtikelId, ArtikelKode FROM dbo.SMArtikel";
                                $stmt = sqlsrv_query($conn, $sql);
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                    $selected = ($row['ArtikelId'] == $data['ArtikelId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $row['ArtikelId'] ?>" <?= $selected ?>>
                                        <?= $row['ArtikelKode'] ?>
                                    </option>
                                <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                            </select>
                        </div>

                        <!-- LOT -->
                        <div class="form-group">
                            <label>Lot *</label>
                            <input type="text" class="form-control" name="lot" value="<?= htmlspecialchars($data['Lot']) ?>" required>
                        </div>

                        <!-- No Beam -->
                        <div class="form-group">
                            <label>No Beam *</label>
                            <input type="text" class="form-control" name="noBeam" value="<?= htmlspecialchars($data['NoBeam']) ?>" required>
                        </div>

                        <!-- Mesin Weaving -->
                        <div class="form-group">
                            <label>No MC Weaving *</label>
                            <select class="form-control" name="WVMCId" required>
                                <option value="">Pilih No Mesin</option>
                                <?php
                                $sql = "SELECT MesinId, MesinNo FROM dbo.SMMesinInspector WHERE TypeId IN ('1','6')";
                                $stmt = sqlsrv_query($conn, $sql);
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                    $selected = ($row['MesinId'] == $data['WVMCId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $row['MesinId'] ?>" <?= $selected ?>>
                                        <?= $row['MesinNo'] ?>
                                    </option>
                                <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                            </select>
                        </div>

                        <!-- Panjang / Lebar -->
                        <div class="form-group">
                            <label>Panjang Kain (Weaving) *</label>
                            <input type="number" class="form-control" name="PanjangKainW" value="<?= htmlspecialchars($data['PanjangKainW']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Panjang Kain (Inspect) *</label>
                            <input type="number" class="form-control" name="PanjangKainI" value="<?= htmlspecialchars($data['PanjangKainI']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Lebar Kain (Weaving) *</label>
                            <input type="number" class="form-control" name="LebarKainW" value="<?= htmlspecialchars($data['LebarKainW']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Lebar Kain (Inspect) *</label>
                            <input type="number" class="form-control" name="LebarKainI" value="<?= htmlspecialchars($data['LebarKainI']) ?>" required>
                        </div>

                        <!-- Keterangan -->
                        <div class="form-group">
                            <label>Keterangan *</label>
                            <textarea class="form-control" name="Keterangan" required><?= htmlspecialchars($data['Keterangan']) ?></textarea>
                        </div>

                        <!-- BUTTON -->
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan
                        </button>

                        <a href="inspecting_weaving.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>

                    </form>

                </div>
            </div>

        </div>
    </section>
</div>

<!-- ===================================================
    9. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>