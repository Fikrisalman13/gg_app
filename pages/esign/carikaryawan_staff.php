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

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Inspecting Weaving
$menuId = 29; // Sesuaikan dengan MenuId yang benar

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
// Pencarian karyawan berdasarkan NIK atau Nama Lengkap
$employee = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['nik']) || isset($_POST['nama_lengkap']))) {
    $nik = $_POST['nik'] ?? '';
    $nama = $_POST['nama_lengkap'] ?? '';

    $sql = "SELECT
                a.nik, 
                a.nama_lengkap, 
                b.dept, 
                c.bagian, 
                d.subbag, 
                e.golongan
            FROM
                dbo.m_emp AS a
                LEFT JOIN
                dbo.m_dept AS b
                ON 
                    a.id_dept = b.id_dept
                LEFT JOIN
                dbo.m_bag AS c
                ON 
                    a.id_bag = c.id_bag
                LEFT JOIN
                dbo.m_subbag AS d
                ON 
                    a.id_subbag = d.id_subbag
                LEFT JOIN
                dbo.m_gol AS e
                ON 
                    a.id_gol = e.id_gol
            WHERE
                a.aktif = '1' AND
                a.id_gol IN ('1','3')";

    $params = [];
    if (!empty($nik)) {
        $sql .= " AND a.nik = ?";
        $params[] = $nik;
    }
    if (!empty($nama)) {
        $sql .= " AND a.nama_lengkap LIKE ?";
        $params[] = "%" . $nama . "%";
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $employee[] = $row;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Data Karyawan Staff</title>
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
                        <h1 class="m-0">Proses Staff</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Proses Staff</li>
                        </ol>
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
                                <h3 class="card-title">
                                    Cari Karyawan Staff
                                </h3>
                            </div>
                            <div class="card-body table-responsive">
                                <form method="POST">
                                <div class="form-group">
                                    <label for="nik">NIK:</label>
                                    <input type="text" class="form-control" name="nik" id="nik">
                                </div>
                                <div class="form-group">
                                    <label for="nama">Nama Lengkap:</label>
                                    <input type="text" class="form-control" name="nama_lengkap" id="nama">
                                </div>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>">Cari</button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($employee)): ?>
    <div class="card">
        <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
            <h3 class="card-title">
                Data Karyawan Staff
            </h3>
        </div>
        <div class="card-body table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">NIK</th>
                            <th class="text-center">Nama</th>
                            <th class="text-center">Departemen</th>
                            <th class="text-center">Bagian</th>
                            <th class="text-center">Level</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employee as $index => $emp): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td class="text-center"><?= htmlspecialchars($emp['nik']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($emp['nama_lengkap']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($emp['dept']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($emp['bagian']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($emp['golongan']) ?></td>
                                <td class="text-center">
                                    <a href="/gg_app/pages/esign/prosesttd_staff.php?nik=<?= htmlspecialchars($emp['nik']) ?>" 
                                       class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm shadow-sm">
                                        <i class="fas fa-signature"></i> Proses
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
    <div class="card">
        <div class="card-body text-center">
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-circle"></i> Karyawan dengan data tersebut tidak ditemukan.
            </div>
        </div>
    </div>
<?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    sessionStorage.setItem("lastPage", window.location.href);
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