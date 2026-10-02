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

// Cek apakah ada notifikasi dalam session
$notif = isset($_SESSION['notif']) ? $_SESSION['notif'] : null;
unset($_SESSION['notif']); // Hapus setelah ditampilkan

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Inspecting Weaving
$menuId = 28; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

// Query to retrieve employee data
$sql = "SELECT
            a.nik,
            a.nama_lengkap,
            b.dept,
            c.bagian,
            d.subbag,
            e.jabatan,
            g.golongan,
            h.signature,
            f.file_pdf 
        FROM
            dbo.m_emp AS a
            LEFT JOIN dbo.m_dept AS b ON a.id_dept = b.id_dept
            LEFT JOIN dbo.m_bag AS c ON a.id_bag = c.id_bag
            LEFT JOIN dbo.m_subbag AS d ON a.id_subbag = d.id_subbag
            LEFT JOIN dbo.kontrak_kerja AS f ON a.nik = f.nik
            LEFT JOIN dbo.m_jab AS e ON a.id_jab = e.id_jab
            LEFT JOIN dbo.m_gol AS g ON a.id_gol = g.id_gol
            LEFT JOIN dbo.tanda_tangan AS h ON a.nik = h.nik
        WHERE
            a.aktif = '1' 
            AND
            a.id_gol IN ('2','7','8','10','11') AND
            a.id_dept != '22' AND
            NOT EXISTS (
                SELECT 1 FROM dbo.tanda_tangan AS h WHERE a.nik = h.nik
            )
        ORDER BY
            a.nama_lengkap ASC";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$employees = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $employees[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kontrak Belum Tanda Tangan</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Data Kontrak Belum Tanda Tangan</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title">Daftar Karyawan</h3>
                            </div>
                            <div class="card-body table-responsive">
                                <table id="example1" class="table table-hover table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>NIK</th>
                                            <th>Nama</th>
                                            <th>Departemen</th>
                                            <th>Bagian</th>
                                            <th>Sub Bagian</th>
                                   
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($employees): ?>
                                            <?php $no = 1; foreach ($employees as $emp): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($no++) ?></td>
                                                    <td><?= htmlspecialchars($emp['nik']) ?></td>
                                                    <td><?= htmlspecialchars($emp['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($emp['dept']) ?></td>
                                                    <td><?= htmlspecialchars($emp['bagian']) ?></td>
                                                    <td><?= htmlspecialchars($emp['subbag']) ?></td>
                                                    
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Tidak ada data ditemukan.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $("#example1").DataTable({
            responsive: true
        });
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        <?php if ($notif): ?>
            Swal.fire({
                icon: "<?= $notif['status'] ?>",
                title: "<?= $notif['status'] == 'success' ? 'Berhasil!' : 'Gagal!' ?>",
                text: "<?= $notif['message'] ?>",
                showConfirmButton: false,
                timer: 2500
            });
        <?php endif; ?>
    });

    function confirmDelete(nik) {
        Swal.fire({
            title: "Apakah Anda yakin?",
            text: "Data ini akan dihapus dan tidak bisa dikembalikan!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Ya, hapus!",
            cancelButtonText: "Batal"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "/gg_app/pages/esign/hapus_data_kontrak.php?nik=" + nik;
            }
        });
    }
</script>
<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>


</body>
</html>

<?php include '../../includes/footer.php'; ?>