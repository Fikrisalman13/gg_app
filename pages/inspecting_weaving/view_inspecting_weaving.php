<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
date_default_timezone_set('Asia/Jakarta');
ob_start();

// ===================================================
// 2. PENGATURAN NOTIFIKASI
// ===================================================
$successMessage = $_SESSION['success'] ?? null;
$errorMessage   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ===================================================
// 3. VALIDASI LOGIN USER
// ===================================================
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
include '../../koneksi.php';
if (!$conn) {
    error_log("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
    die("Terjadi kesalahan sistem. Hubungi administrator.");
}

// ===================================================
// 5. IMPORT LAYOUT & PERMISSIONS
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';

// ===================================================
// 6. VALIDASI PARAMETER (GET ID)
// ===================================================
$noCP = $_GET['id'] ?? null;
if (!$noCP) {
    die("Parameter NoCP tidak valid.");
}

// ===================================================
// 7. QUERY DATA DETAIL INSPECTING WEAVING
// ===================================================
$sql = "
    SELECT
        a.NoCP,
        a.InspectDate,
        b.EmpName,
        d.ArtikelKode,
        d.ArtikelName,
        a.Lot,
        a.NoBeam,
        m3.MesinNo AS WVMC_MesinNo,
        m3.MesinName AS WVMC_MesinName,
        a.PanjangKainW,
        a.PanjangKainI,
        a.LebarKainW,
        a.LebarKainI,
        a.Keterangan
    FROM dbo.FormInspectHd AS a
    LEFT JOIN dbo.SMEmployeeInspector AS b ON a.EmpId = b.EmpId
    LEFT JOIN dbo.SMArtikel AS d ON a.ArtikelId = d.ArtikelId
    LEFT JOIN dbo.SMMesinInspector AS m3 ON a.WVMCId = m3.MesinId
    WHERE a.NoCP = ?
";

$stmt = sqlsrv_query($conn, $sql, [$noCP]);
if (!$stmt) {
    die("Kesalahan Query: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$row) {
    die("Data tidak ditemukan.");
}

ob_end_flush();
?>

<!-- ===================================================
    8. HTML : STRUKTUR HALAMAN
======================================================= -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Inspecting Weaving</title>

    <!-- AdminLTE / Bootstrap 4 -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
</head>

<body>

<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">

                <div class="col-sm-6">
                    <h1>Detail Inspecting Weaving</h1>
                </div>

                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="inspecting_weaving.php">Inspecting Weaving</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>

            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <!-- Card Header -->
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Detail Inspecting Weaving</h3>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <table id="deptTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                        <tbody>
                            <?php
                            $fields = [
                                "No CP"              => "NoCP",
                                "Tanggal Inspeksi"   => "InspectDate",
                                "Inspektor"          => "EmpName",
                                "Kode Artikel"       => "ArtikelKode",
                                "Nama Artikel"       => "ArtikelName",
                                "WVMC Mesin No"      => "WVMC_MesinNo",
                                "WVMC Mesin Name"    => "WVMC_MesinName",
                                "Panjang Kain (W)"   => "PanjangKainW",
                                "Panjang Kain (I)"   => "PanjangKainI",
                                "Lebar Kain (W)"     => "LebarKainW",
                                "Lebar Kain (I)"     => "LebarKainI",
                                "Keterangan"         => "Keterangan"
                            ];

                            $count = 0;

                            foreach ($fields as $label => $col) {
                                if ($count % 2 === 0) echo "<tr>";

                                $value = $row[$col] ?? "-";

                                if ($value instanceof DateTime) {
                                    $value = $value->format('Y-m-d H:i:s');
                                }

                                echo "<th style='width: 25%;'>$label</th>";
                                echo "<td>" . htmlspecialchars($value) . "</td>";

                                if ($count % 2 === 1) echo "</tr>";
                                $count++;
                            }
                            ?>
                        </tbody>
                    </table>

                    <a href="inspecting_weaving.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>

                </div>
            </div>

        </div>
    </section>

</div>

<!-- ===================================================
    9. FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<!-- ===================================================
    10. JAVASCRIPT LIBRARIES
======================================================= -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- ===================================================
    11. NOTIFIKASI JS
======================================================= -->
<script>
$(function () {
    <?php if ($successMessage): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sukses',
        text: <?= json_encode($successMessage) ?>,
        timer: 2000,
        showConfirmButton: false
    });
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?= json_encode($errorMessage) ?>,
        timer: 2000,
        showConfirmButton: false
    });
    <?php endif; ?>
});
</script>

</body>
</html>
