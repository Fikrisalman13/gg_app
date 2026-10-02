<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 23;

// Query hak akses
$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canEdit = false;
if ($stmt && ($rowPerm = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $canEdit = $rowPerm['CanEdit'] == 1;
    sqlsrv_free_stmt($stmt);
}

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

$id = $_GET['id'] ?? null; // Ambil ID dari parameter URL
if (!$id) {
    $_SESSION['error'] = "ID tidak valid!";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Query untuk mengambil data cacat yang akan diedit berdasarkan ID
$sql = "SELECT * FROM dbo.SMCacatDetail WHERE Id = ?";
$params = [$id];
$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $_SESSION['error'] = "Data cacat tidak ditemukan!";
    header('Location: datacacat_inspecting_weaving.php');
    exit;
}

// Ambil nilai NoDetail terbesar untuk menentukan nextNoDetail
$sqlNext = "SELECT MAX(NoDetail) AS MaxNoDetail FROM dbo.SMCacatDetail";
$stmtNext = sqlsrv_query($conn, $sqlNext);

if ($stmtNext && ($rowNext = sqlsrv_fetch_array($stmtNext, SQLSRV_FETCH_ASSOC))) {
    $nextNoDetail = $rowNext['MaxNoDetail'] + 1;
} else {
    $nextNoDetail = 1; // Default jika tidak ada data
}

// Ambil status FgCacat dari FormInspectHd untuk NoCP terkait (untuk menentukan state checkbox)
$noCP_current = $row['NoCP'];
$isLastChecked = false;
if ($noCP_current) {
    $sqlFgCheck = "SELECT TOP 1 FgCacat FROM dbo.FormInspectHd WHERE NoCP = ?";
    $stmtFgCheck = sqlsrv_query($conn, $sqlFgCheck, [$noCP_current]);
    if ($stmtFgCheck && ($fgRow = sqlsrv_fetch_array($stmtFgCheck, SQLSRV_FETCH_ASSOC))) {
        $isLastChecked = (isset($fgRow['FgCacat']) && intval($fgRow['FgCacat']) === 1);
    }
    if ($stmtFgCheck) sqlsrv_free_stmt($stmtFgCheck);
}

// Proses update data cacat
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cacatId = isset($_POST['cacatId']) ? $_POST['cacatId'] : null;
    $meterKe = isset($_POST['meterKe']) ? intval($_POST['meterKe']) : null;
    $sMeterKe = isset($_POST['sMeterKe']) ? intval($_POST['sMeterKe']) : null;
    $pointCacat = isset($_POST['pointCacat']) ? floatval($_POST['pointCacat']) : null;
    $shiftId = isset($_POST['shiftId']) ? $_POST['shiftId'] : null;
    $lastNoDetail = isset($_POST['lastNoDetail']) ? 1 : 0; // 1 jika dicentang, 0 jika tidak

    // Validasi sederhana
    if ($cacatId === null || $meterKe === null || $sMeterKe === null || $pointCacat === null || $shiftId === null) {
        $_SESSION['error'] = "Lengkapi semua field yang wajib.";
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }

    // Mulai transaksi
    if (!sqlsrv_begin_transaction($conn)) {
        $_SESSION['error'] = "Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true);
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }

    $success = true;

    // 1) Update SMCacatDetail
    $sqlUpdate = "UPDATE dbo.SMCacatDetail 
                  SET UpdDate = GETDATE(), CacatId = ?, MeterKe = ?, SMeterKe = ?, PointCacat = ?, ShiftId = ?
                  WHERE Id = ?";
    $paramsUpdate = [$cacatId, $meterKe, $sMeterKe, $pointCacat, $shiftId, $id];
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
    if ($stmtUpdate === false) {
        $success = false;
        $err = sqlsrv_errors();
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal mengupdate data cacat: " . print_r($err, true);
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }

    // 2) Jika Last No Detail dicentang => set FgCacat = 1, jika tidak dicentang => set FgCacat = NULL
    if ($noCP_current) {
        if ($lastNoDetail) {
            $sqlFg = "UPDATE dbo.FormInspectHd SET FgCacat = 1 WHERE NoCP = ?";
            $paramsFg = [$noCP_current];
        } else {
            $sqlFg = "UPDATE dbo.FormInspectHd SET FgCacat = NULL WHERE NoCP = ?";
            $paramsFg = [$noCP_current];
        }
        $stmtFg = sqlsrv_query($conn, $sqlFg, $paramsFg);
        if ($stmtFg === false) {
            $success = false;
            $err = sqlsrv_errors();
            sqlsrv_rollback($conn);
            $_SESSION['error'] = "Gagal mengupdate FormInspectHd.FgCacat: " . print_r($err, true);
            header('Location: edit_data_cacat.php?id=' . $id);
            exit;
        }
    }

    // 3) Recalculate & upsert SMCacatSummary hanya untuk NoCP terkait
    $noCP = $noCP_current;

    $mergeSql = "
    DECLARE @NoCPParam nvarchar(100) = ?;

    WITH Agg AS (
        SELECT
            d.NoCP,
            COUNT(d.NoDetail) AS TotalKodeCacat,
            SUM(
                CASE 
                    WHEN d.SMeterKe = d.MeterKe THEN 1
                    ELSE ABS(d.SMeterKe - d.MeterKe)
                END
            ) AS TotalMeterCacat,
            SUM(
                CASE
                    WHEN d.SMeterKe = d.MeterKe THEN 1 * d.PointCacat
                    ELSE ABS(d.SMeterKe - d.MeterKe) * d.PointCacat
                END
            ) AS TotalPointCacat,
            fh.PanjangKainI
        FROM dbo.SMCacatDetail d WITH (NOLOCK)
        INNER JOIN dbo.FormInspectHd fh WITH (NOLOCK) ON d.NoCP = fh.NoCP
        WHERE fh.FgCacat = 1
          AND d.NoCP = @NoCPParam
        GROUP BY d.NoCP, fh.PanjangKainI
    ),
    AggWithGrade AS (
        SELECT
            a.*,
            CASE 
                WHEN ISNULL(a.PanjangKainI,0) <= 0 THEN 'C'
                ELSE
                    CASE 
                        WHEN (CAST(a.TotalPointCacat AS float) / CAST(a.PanjangKainI AS float)) <= 0.30 THEN 'A'
                        WHEN (CAST(a.TotalPointCacat AS float) / CAST(a.PanjangKainI AS float)) <= 0.60 THEN 'B'
                        ELSE 'C'
                    END
            END AS Grade
        FROM Agg a
    )

    MERGE INTO dbo.SMCacatSummary AS T
    USING AggWithGrade AS S
        ON T.NoCP = S.NoCP
    WHEN MATCHED THEN
        UPDATE SET
            T.TotalKodeCacat = S.TotalKodeCacat,
            T.TotalMeterCacat = S.TotalMeterCacat,
            T.TotalPointCacat = S.TotalPointCacat,
            T.PanjangKainI = S.PanjangKainI,
            T.Grade = S.Grade,
            T.UpdatedAt = GETDATE()
    WHEN NOT MATCHED BY TARGET THEN
        INSERT (NoCP, TotalKodeCacat, TotalMeterCacat, TotalPointCacat, PanjangKainI, Grade, CreatedAt, UpdatedAt)
        VALUES (S.NoCP, S.TotalKodeCacat, S.TotalMeterCacat, S.TotalPointCacat, S.PanjangKainI, S.Grade, GETDATE(), GETDATE())
    ;

    -- Jika tidak ada hasil agregasi (tidak ada row di AggWithGrade) maka hapus SMCacatSummary untuk NoCP tersebut
    IF NOT EXISTS (SELECT 1 FROM AggWithGrade)
    BEGIN
        DELETE FROM dbo.SMCacatSummary WHERE NoCP = @NoCPParam;
    END
    ";

    $paramsMerge = [$noCP];
    $stmtMerge = sqlsrv_query($conn, $mergeSql, $paramsMerge);
    if ($stmtMerge === false) {
        $success = false;
        $err = sqlsrv_errors();
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal memperbarui SMCacatSummary: " . print_r($err, true);
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }

    // Commit transaksi jika semua sukses
    if ($success) {
        if (!sqlsrv_commit($conn)) {
            $err = sqlsrv_errors();
            sqlsrv_rollback($conn);
            $_SESSION['error'] = "Gagal commit transaksi: " . print_r($err, true);
            header('Location: edit_data_cacat.php?id=' . $id);
            exit;
        }

        $_SESSION['success'] = "Data cacat berhasil diupdate dan SMCacatSummary ter-refresh!";
        header('Location: datacacat_inspecting_weaving.php');
        exit;
    } else {
        // seharusnya sudah di-rollback pada error path di atas
        $_SESSION['error'] = "Terjadi kesalahan saat memproses update.";
        header('Location: edit_data_cacat.php?id=' . $id);
        exit;
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Cacat</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap.min.css">

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12">
                    <h1 class="m-0">Edit Data Cacat</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Data Cacat</h3>
                </div>
                <div class="card-body card-body table-responsive">
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                        <div class="form-group">
                            <label for="noDetail">No Detail</label>
                            <input type="text" class="form-control" id="noDetail" name="noDetail" value="<?= htmlspecialchars($row['NoDetail']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="cacatId">Kode Cacat</label>
                            <select class="form-control select2" id="cacatId" name="cacatId" required>
                                <option value="">Pilih Kode Cacat</option>
                                <?php
                                $sqlCac = "SELECT CacatId, CacatKode, CacatName FROM dbo.SMCacat";
                                $stmtCac = sqlsrv_query($conn, $sqlCac);
                                while ($cacat = sqlsrv_fetch_array($stmtCac, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($cacat['CacatId'] == $row['CacatId']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($cacat['CacatId']) . '" ' . $selected . '>' . htmlspecialchars($cacat['CacatKode']) . ' - ' . htmlspecialchars($cacat['CacatName']) . '</option>';
                                }
                                if ($stmtCac) sqlsrv_free_stmt($stmtCac);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="meterKe">Dari Meter Ke</label>
                            <input type="number" class="form-control" id="meterKe" name="meterKe" value="<?= htmlspecialchars($row['MeterKe']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="sMeterKe">Sampai Meter Ke</label>
                            <input type="number" class="form-control" id="sMeterKe" name="sMeterKe" value="<?= htmlspecialchars($row['SMeterKe']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="pointCacat">Point Cacat</label>
                            <input type="number" step="0.01" class="form-control" id="pointCacat" name="pointCacat" value="<?= htmlspecialchars($row['PointCacat']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="shiftId">Shift</label>
                            <select class="form-control select2" id="shiftId" name="shiftId" required>
                                <option value="">Pilih Shift</option>
                                <?php
                                $sqlShift = "SELECT ShiftId, ShiftKode FROM dbo.SMShiftWInspector";
                                $stmtShift = sqlsrv_query($conn, $sqlShift);
                                while ($shift = sqlsrv_fetch_array($stmtShift, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($shift['ShiftId'] == $row['ShiftId']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($shift['ShiftId']) . '" ' . $selected . '>' . htmlspecialchars($shift['ShiftKode']) . '</option>';
                                }
                                if ($stmtShift) sqlsrv_free_stmt($stmtShift);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="lastNoDetail" name="lastNoDetail" value="1" <?= $isLastChecked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="lastNoDetail">Last No Detail</label>
                                <small class="form-text text-muted">Dicentang jika NoCP ini sudah final (FgCacat = 1).</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"> <i class="fas fa-save"></i> Simpan</button>
                        <a href="datacacat_inspecting_weaving.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
<script src="/gg_app/plugins/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap',
            width: '100%',
            placeholder: "Pilih opsi",
            allowClear: true
        });
    });

    // Tampilkan notifikasi jika ada session messages
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= addslashes($_SESSION['success']) ?>",
            timer: 2500,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= addslashes($_SESSION['error']) ?>",
            timer: 4000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
