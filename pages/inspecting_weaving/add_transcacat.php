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

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 23;

// Query hak akses
$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canAdd = false;
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $canAdd = $row['CanAdd'] == 1;
    sqlsrv_free_stmt($stmt);
}

if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambahkan data.";
    header('Location: inspecting_weaving.php');
    exit;
}

$noCP = $_GET['noCP'] ?? null;
if (!$noCP) {
    $_SESSION['error'] = "No CP tidak valid!";
    header('Location: inspecting_weaving.php');
    exit;
}

// Query untuk mengambil nomor urut terakhir
$sql = "SELECT MAX(NoDetail) AS LastNoDetail FROM dbo.SMCacatDetail WHERE NoCP = ?";
$params = [$noCP];
$stmt = sqlsrv_query($conn, $sql, $params);

$nextNoDetail = 1;
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $nextNoDetail = $row['LastNoDetail'] + 1;
    sqlsrv_free_stmt($stmt);
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Cacat</title>
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
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Data Cacat</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/inspecting_weaving/datacacat_inspecting_weaving.php">Data Cacat Inspecting Weaving</a></li>
                        <li class="breadcrumb-item active">Tambah Data Cacat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h3 class="card-title">Form Tambah Data Cacat</h3>
                </div>
                
                <div class="card-body table-responsive">
                    <form method="POST" action="proses_add_transcacat.php">
                        <input type="hidden" name="noCP" value="<?= htmlspecialchars($noCP) ?>">
                        <div class="form-group">
                            <label for="noDetail">No Detail</label>
                            <input type="text" class="form-control" id="noDetail" name="noDetail" value="<?= $nextNoDetail ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="cacatId">Kode Cacat</label>
                            <select class="form-control" id="cacatId" name="cacatId" required>
                                <option value="">Pilih Kode Cacat</option>
                                <?php
                                $sql = "SELECT CacatId, CacatKode, CacatName FROM dbo.SMCacat";
                                $stmt = sqlsrv_query($conn, $sql);
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($row['CacatId']) . '">' . htmlspecialchars($row['CacatKode']) . ' - ' . htmlspecialchars($row['CacatName']) . '</option>';
                                }
                                sqlsrv_free_stmt($stmt);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="meterKe">Dari Meter Ke</label>
                            <input type="number" class="form-control" id="meterKe" name="meterKe" required>
                        </div>
                        <div class="form-group">
                            <label for="sMeterKe">Sampai Meter Ke</label>
                            <input type="number" class="form-control" id="sMeterKe" name="sMeterKe" required>
                        </div>
                        <div class="form-group">
                            <label for="pointCacat">Point Cacat</label>
                            <input type="number" class="form-control" id="pointCacat" name="pointCacat" required>
                        </div>
                        <div class="form-group">
                            <label for="shiftId">Shift</label>
                            <select class="form-control select" id="shiftId" name="shiftId" required>
                                <option value="">Pilih Shift</option>
                                <?php
                                $sql = "SELECT ShiftId, ShiftKode FROM dbo.SMShiftWInspector";
                                $stmt = sqlsrv_query($conn, $sql);
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($row['ShiftId']) . '">' . htmlspecialchars($row['ShiftKode']) . '</option>';
                                }
                                sqlsrv_free_stmt($stmt);
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="lastNoDetail" name="lastNoDetail" value="1">
                                <label class="form-check-label" for="lastNoDetail">Last No Detail</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Simpan</button>
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
</script>

</body>
</html>
