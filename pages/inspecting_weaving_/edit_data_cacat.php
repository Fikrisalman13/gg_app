<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');


// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}


// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
include '../../koneksi.php';
if (!$conn) {
    error_log("Koneksi database gagal: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan sistem. Hubungi IT Administrator.");
}


// ===================================================
// 4. IMPORT DEPENDENSI
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';


// ===================================================
// 5. VALIDASI IZIN AKSES (CanEdit)
// ===================================================
requireEdit($conn, MENU_INSPECT_HEADER);


// ===================================================
// 6. MENDAPATKAN ID DAN DATA DETAIL CACAT
// ===================================================
$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['error'] = "ID tidak valid!";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Ambil data cacat berdasarkan ID
$sqlDetail = "SELECT * FROM dbo.SMCacatDetail WHERE Id = ?";
$stmtDetail = sqlsrv_query($conn, $sqlDetail, [$id]);

if (!$stmtDetail || !($row = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC))) {
    $_SESSION['error'] = "Data cacat tidak ditemukan!";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}
sqlsrv_free_stmt($stmtDetail);


// ===================================================
// 7. MENDAPATKAN NEXT NO DETAIL
// ===================================================
$sqlNext = "SELECT MAX(NoDetail) AS MaxNoDetail FROM dbo.SMCacatDetail";
$stmtNext = sqlsrv_query($conn, $sqlNext);

$nextNoDetail = 1;
if ($stmtNext && ($rowNext = sqlsrv_fetch_array($stmtNext, SQLSRV_FETCH_ASSOC))) {
    $nextNoDetail = ((int)$rowNext['MaxNoDetail']) + 1;
}
sqlsrv_free_stmt($stmtNext);


// ===================================================
// 8. PROSES UPDATE DATA
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cacatId     = $_POST['cacatId'];
    $meterKe     = $_POST['meterKe'];
    $sMeterKe    = $_POST['sMeterKe'];
    $pointCacat  = $_POST['pointCacat'];
    $shiftId     = $_POST['shiftId'];
    $lastNo      = isset($_POST['lastNoDetail']) ? 1 : 0;
    $updUser     = $_SESSION['UserName'];

    // Update data cacat
    $sqlUpdate = "
        UPDATE dbo.SMCacatDetail 
        SET UpdDate = GETDATE(),
            UpdUser = ?,
            CacatId = ?, 
            MeterKe = ?, 
            SMeterKe = ?, 
            PointCacat = ?, 
            ShiftId = ?
        WHERE Id = ?
    ";

    $paramsUpdate = [
        $updUser,
        $cacatId,
        $meterKe,
        $sMeterKe,
        $pointCacat,
        $shiftId,
        $id
    ];

    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);

    if ($stmtUpdate) {

        // Jika last no detail diaktifkan → update FgCacat
        if ($lastNo) {
            $sqlFlag = "UPDATE dbo.FormInspectHd SET FgCacat = 1 WHERE NoCP = ?";
            sqlsrv_query($conn, $sqlFlag, [$row['NoCP']]);
        }

        $_SESSION['success'] = "Data cacat berhasil diperbarui.";
        header('Location: datacacat_inspecting_weaving.php');
        exit;

    } else {
        error_log("Gagal update cacat: " . print_r(sqlsrv_errors(), true));
        $_SESSION['error'] = "Gagal mengupdate data cacat!";
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }
}


// ===================================================
// 9. LOAD DROPDOWN LIST
// ===================================================
$stmtCacat = sqlsrv_query($conn, "SELECT CacatId, CacatKode, CacatName FROM dbo.SMCacat ORDER BY CacatKode");
$stmtShift = sqlsrv_query($conn, "SELECT ShiftId, ShiftKode FROM dbo.SMShiftWInspector ORDER BY ShiftId");

// Tema UI
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- ===================================================
    10. HTML: FORM EDIT DATA CACAT
======================================================= -->
<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Edit Data Cacat</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- NOTIFIKASI -->
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i> Form Edit Data Cacat
                    </h3>
                </div>

                <div class="card-body">

                    <form method="POST" autocomplete="off">

                        <div class="form-group">
                            <label>No Detail</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['NoDetail']) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Kode Cacat *</label>
                            <select name="cacatId" class="form-control select2" required>
                                <option value="">Pilih Kode Cacat</option>
                                <?php while ($c = sqlsrv_fetch_array($stmtCacat, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $c['CacatId'] ?>"
                                        <?= $c['CacatId'] == $row['CacatId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['CacatKode'] . " - " . $c['CacatName']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Dari Meter *</label>
                            <input type="number" name="meterKe" class="form-control"
                                   value="<?= htmlspecialchars($row['MeterKe']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Sampai Meter *</label>
                            <input type="number" name="sMeterKe" class="form-control"
                                   value="<?= htmlspecialchars($row['SMeterKe']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Point Cacat *</label>
                            <input type="number" name="pointCacat" class="form-control"
                                   value="<?= htmlspecialchars($row['PointCacat']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Shift *</label>
                            <select name="shiftId" class="form-control select2" required>
                                <?php while ($s = sqlsrv_fetch_array($stmtShift, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $s['ShiftId'] ?>"
                                        <?= $s['ShiftId'] == $row['ShiftId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['ShiftKode']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="lastNoDetail" class="form-check-input"
                                   <?= ($row['NoDetail'] == $nextNoDetail - 1) ? 'checked' : '' ?>>
                            <label class="form-check-label">Last No Detail</label>
                        </div>

                        <button type="submit" class="btn btn-<?= $themeColor ?>">
                            <i class="fas fa-save"></i> Simpan
                        </button>

                        <a href="datacacat_inspecting_weaving.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>

                    </form>

                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
