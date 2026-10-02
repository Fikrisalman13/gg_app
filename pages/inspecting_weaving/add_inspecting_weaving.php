<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
date_default_timezone_set('Asia/Jakarta');


// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}


// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
include '../../koneksi.php';
if (!$conn) {
    error_log("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan sistem. Hubungi administrator.");
}


// ===================================================
// 4. IMPORT DEPENDENSI
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';


// ===================================================
// 5. VALIDASI IZIN (CanAdd)
// ===================================================
requireAdd($conn, MENU_INSPECT_HEADER); // sesuaikan ID menu Anda


// ===================================================
// 6. INISIALISASI VARIABEL FORM
// ===================================================
$noCP          = $_POST['noCP']          ?? '';
$inspectDate   = $_POST['inspectDate']   ?? '';
$empId         = $_POST['empId']         ?? '';
$artikelId     = $_POST['artikelId']     ?? '';
$lot           = $_POST['lot']           ?? '';
$noBeam        = $_POST['noBeam']        ?? '';
$WVMCId        = $_POST['WVMCId']        ?? '';
$PanjangKainW  = $_POST['PanjangKainW'][0] ?? '';
$PanjangKainI  = $_POST['PanjangKainI'][0] ?? '';
$LebarKainW    = $_POST['LebarKainW'][0] ?? '';
$LebarKainI    = $_POST['LebarKainI'][0] ?? '';
$Keterangan    = $_POST['Keterangan']    ?? '';


// ===================================================
// 7. PROSES SIMPAN DATA
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Format tanggal
    if (!empty($inspectDate)) {
        $inspectDate = date('Y-m-d H:i:s', strtotime($inspectDate));
    }

    $updUser = $_SESSION['UserName'];
    $updDate = date('Y-m-d H:i:s');

    // Validasi wajib isi
    if (empty($noCP) || empty($inspectDate) || empty($empId) || empty($artikelId)) {
        $_SESSION['error'] = "Harap isi semua field yang wajib!";
    } else {

        // Cek duplikasi No CP
        $sqlCheck = "SELECT COUNT(*) AS jumlah FROM dbo.FormInspectHd WHERE NoCP = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$noCP]);

        if ($stmtCheck === false) {
            $_SESSION['error'] = "Gagal memeriksa duplikasi: " . print_r(sqlsrv_errors(), true);

        } else {
            $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtCheck);

            if ($row['jumlah'] > 0) {
                $_SESSION['error'] = "No CP sudah digunakan! Gunakan No CP lain.";

            } else {

                // INSERT DATA
                $sqlInsert = "INSERT INTO dbo.FormInspectHd 
                             (NoCP, InspectDate, EmpId, ArtikelId, Lot, NoBeam, WVMCId,
                              PanjangKainW, PanjangKainI, LebarKainW, LebarKainI, Keterangan, UpdDate, UpdUser)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $paramsInsert = [
                    $noCP, $inspectDate, $empId, $artikelId, $lot, $noBeam, $WVMCId,
                    $PanjangKainW, $PanjangKainI, $LebarKainW, $LebarKainI,
                    $Keterangan, $updDate, $updUser
                ];

                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

                if ($stmtInsert) {
                    sqlsrv_free_stmt($stmtInsert);
                    $_SESSION['success'] = "Data berhasil ditambahkan!";
                    header("Location: add_inspecting_weaving.php");
                    exit;
                } else {
                    $_SESSION['error'] = "Gagal menambah data: " . print_r(sqlsrv_errors(), true);
                }
            }
        }
    }
}


// ===================================================
// 8. TEMA UI
// ===================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Inspect Weaving</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>

<body>

<div class="content-wrapper">

    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Data Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="inspecting_weaving.php">Inspect Weaving</a></li>
                        <li class="breadcrumb-item active">Tambah Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>


    <!-- FORM -->
    <div class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Form Tambah Data</h3>
                </div>

                <div class="card-body">

                    <form method="POST">

                        <!-- No CP -->
                        <div class="form-group">
                            <label>No CP</label>
                            <input type="text" class="form-control" name="noCP"
                                   value="<?= htmlspecialchars($noCP) ?>" required>
                        </div>

                        <!-- Tanggal -->
                        <div class="form-group">
                            <label>Tanggal Inspeksi</label>
                            <input type="datetime-local" class="form-control" name="inspectDate"
                                   value="<?= $inspectDate ? date('Y-m-d\TH:i', strtotime($inspectDate)) : '' ?>"
                                   required>
                        </div>

                        <!-- Inspektor -->
                        <div class="form-group">
                            <label>Nama Inspektor</label>
                            <select class="form-control" name="empId" required>
                                <option value="">Pilih Inspektor</option>
                                <?php
                                $q = sqlsrv_query($conn, "SELECT EmpId, EmpName FROM dbo.SMEmployeeInspector");
                                while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
                                    $sel = ($empId == $r['EmpId']) ? 'selected' : '';
                                    echo "<option value='{$r['EmpId']}' $sel>{$r['EmpName']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Artikel -->
                        <div class="form-group">
                            <label>Kode Artikel</label>
                            <select class="form-control" name="artikelId" required>
                                <option value="">Pilih Artikel</option>
                                <?php
                                $q = sqlsrv_query($conn, "SELECT ArtikelId, ArtikelKode FROM dbo.SMArtikel");
                                while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
                                    $sel = ($artikelId == $r['ArtikelId']) ? 'selected' : '';
                                    echo "<option value='{$r['ArtikelId']}' $sel>{$r['ArtikelKode']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- LOT -->
                        <div class="form-group">
                            <label>Lot</label>
                            <input type="text" class="form-control" name="lot" value="<?= htmlspecialchars($lot) ?>" required>
                        </div>

                        <!-- No Beam -->
                        <div class="form-group">
                            <label>No Beam</label>
                            <input type="text" class="form-control" name="noBeam" value="<?= htmlspecialchars($noBeam) ?>" required>
                        </div>

                        <!-- Mesin -->
                        <div class="form-group">
                            <label>No MC (Weaving)</label>
                            <select class="form-control" name="WVMCId" required>
                                <option value="">Pilih Mesin</option>
                                <?php
                                $q = sqlsrv_query($conn, "SELECT MesinId, MesinNo FROM dbo.SMMesinInspector WHERE TypeId IN('1','6') ORDER BY MesinNo");
                                while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
                                    $sel = ($WVMCId == $r['MesinId']) ? 'selected' : '';
                                    echo "<option value='{$r['MesinId']}' $sel>{$r['MesinNo']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Panjang Kain -->
                        <label>Panjang Kain</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="PanjangKainW[]" placeholder="Weaving"
                                   value="<?= htmlspecialchars($PanjangKainW) ?>" required>
                            <span class="input-group-text">|</span>
                            <input type="number" class="form-control" name="PanjangKainI[]" placeholder="Inspect"
                                   value="<?= htmlspecialchars($PanjangKainI) ?>" required>
                        </div>

                        <!-- Lebar Kain -->
                        <label class="mt-3">Lebar Kain</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="LebarKainW[]" placeholder="Weaving"
                                   value="<?= htmlspecialchars($LebarKainW) ?>" required>
                            <span class="input-group-text">|</span>
                            <input type="number" class="form-control" name="LebarKainI[]" placeholder="Inspect"
                                   value="<?= htmlspecialchars($LebarKainI) ?>" required>
                        </div>

                        <!-- Keterangan -->
                        <div class="form-group mt-3">
                            <label>Keterangan</label>
                            <textarea class="form-control" name="Keterangan" style="height:125px;"><?= htmlspecialchars($Keterangan) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Simpan
                        </button>

                        <a href="inspecting_weaving.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>

                    </form>

                </div>
            </div>

        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- Notifikasi -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire({ icon: 'error', title: 'Error', text: <?= json_encode($_SESSION['error']) ?> });
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({ icon: 'success', title: 'Sukses', text: <?= json_encode($_SESSION['success']) ?> })
.then(() => { window.location.href = 'add_inspecting_weaving.php'; });
</script>
<?php unset($_SESSION['success']); endif; ?>

</body>
</html>
