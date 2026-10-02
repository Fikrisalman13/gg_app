<?php
session_start();
ob_start();

// Enable output buffering dan compression
if (!ob_start("ob_gzhandler")) {
    ob_start();
}

// Set header untuk caching
header("Cache-Control: public, max-age=300"); // Cache 5 menit
header("Content-Type: text/html; charset=utf-8");

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
unset($_SESSION['notif']);

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];
$menuId = 28;

// Query untuk mengambil hak akses - menggunakan prepared statement
$sqlPermissions = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
                  FROM dbo.SMGroupTrustee 
                  WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmtPermissions = sqlsrv_query($conn, $sqlPermissions, $params);

$permissions = ($stmtPermissions && $row = sqlsrv_fetch_array($stmtPermissions, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmtPermissions);

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

// Tentukan jenis karyawan yang dipilih (default: kontrak)
$jenis_karyawan = $_GET['jenis_karyawan'] ?? 'kontrak';

// Optimasi query dengan hanya mengambil field yang diperlukan
if ($jenis_karyawan == 'kontrak') {
    $golongan = "('2','7','8','10','11')";
} else {
    $golongan = "('1','3')";
}

// Query yang dioptimalkan dengan JOIN yang lebih efisien
$sqlEmployees = "SELECT
    a.nik,
    a.nama_lengkap,
    b.dept,
    c.bagian,
    d.subbag,
    e.jabatan,
    g.golongan,
    CASE 
        WHEN a.id_gol IN ('2','7','8','10','11') THEN 'Kontrak'
        WHEN a.id_gol IN ('1','3') THEN 'Staff'
        ELSE 'Lainnya'
    END as jenis_karyawan
FROM dbo.m_emp AS a
LEFT JOIN dbo.m_dept AS b ON a.id_dept = b.id_dept
LEFT JOIN dbo.m_bag AS c ON a.id_bag = c.id_bag
LEFT JOIN dbo.m_subbag AS d ON a.id_subbag = d.id_subbag
LEFT JOIN dbo.m_jab AS e ON a.id_jab = e.id_jab
LEFT JOIN dbo.m_gol AS g ON a.id_gol = g.id_gol
WHERE a.aktif = '1' 
    AND a.id_gol IN $golongan
    AND EXISTS (
        SELECT 1 FROM dbo.tanda_tangan AS h 
        WHERE a.nik = h.nik
    )";

$stmtEmployees = sqlsrv_query($conn, $sqlEmployees);

if ($stmtEmployees === false) {
    die(print_r(sqlsrv_errors(), true));
}

$employees = [];
while ($row = sqlsrv_fetch_array($stmtEmployees, SQLSRV_FETCH_ASSOC)) {
    $employees[] = $row;
}

sqlsrv_free_stmt($stmtEmployees);

// Pre-calculate count untuk menghindari count() berulang
$employeeCount = count($employees);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Tanda Tangan Digital</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css" as="style">
    <link rel="preload" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css" as="style">
    <link rel="preload" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css" as="style">
    
    <!-- CSS dengan media query yang tepat -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css" media="all">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css" media="all">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css" media="all">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css" media="all">
    
    <!-- Inline critical CSS untuk above-the-fold content -->
    <style>
        .badge-kontrak { background-color: #ffc107; color: #212529; }
        .badge-staff { background-color: #17a2b8; color: #fff; }
        .filter-container { background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .filter-title { font-weight: 600; color: #495057; margin-bottom: 10px; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .stats-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stats-label { font-size: 0.9rem; opacity: 0.9; }
        
        /* Loading state */
        .table-loading { display: none; }
        .loading .table-loading { display: block; }
        .loading .table-content { display: none; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Data Tanda Tangan Digital</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Data Tanda Tangan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?= $error_message ?>
                    </div>
                <?php else: ?>
                
                <!-- Filter Section -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="filter-container">
                            <div class="filter-title">
                                <i class="fas fa-filter mr-2"></i>Filter Jenis Karyawan
                            </div>
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <select name="jenis_karyawan" class="form-control" onchange="this.form.submit()">
                                        <option value="kontrak" <?= $jenis_karyawan == 'kontrak' ? 'selected' : '' ?>>Karyawan Kontrak</option>
                                        <option value="staff" <?= $jenis_karyawan == 'staff' ? 'selected' : '' ?>>Karyawan Staff</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                    <i class="fas fa-sync-alt mr-1"></i> Terapkan
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?= $employeeCount ?></div>
                            <div class="stats-label">
                                Total <?= $jenis_karyawan == 'kontrak' ? 'Karyawan Kontrak' : 'Karyawan Staff' ?> dengan Tanda Tangan
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-list mr-2"></i>
                                    Daftar <?= $jenis_karyawan == 'kontrak' ? 'Karyawan Kontrak' : 'Karyawan Staff' ?> dengan Tanda Tangan
                                </h3>
                                <span class="badge badge-light">
                                    <?= $employeeCount ?> Data
                                </span>
                            </div>
                            <div class="card-body table-responsive">
                                <!-- Loading indicator -->
                                <div class="table-loading text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Memuat data...</p>
                                </div>
                                
                                <div class="table-content">
                                    <table id="dataTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center">No</th>
                                                <th class="text-center">NIK</th>
                                                <th class="text-center">Nama</th>
                                                <th class="text-center">Departemen</th>
                                                <th class="text-center">Bagian</th>
                                                <th class="text-center">Sub Bagian</th>
                                                <th class="text-center">Jabatan</th>
                                                <th class="text-center">Golongan</th>
                                                <th class="text-center">Jenis</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($employees): ?>
                                                <?php foreach ($employees as $index => $emp): ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['nik']) ?></td>
                                                        <td><?= htmlspecialchars($emp['nama_lengkap']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['dept']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['bagian']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['subbag']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['jabatan'] ?? '-') ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($emp['golongan']) ?></td>
                                                        <td class="text-center">
                                                            <span class="badge badge-<?= $emp['jenis_karyawan'] == 'Kontrak' ? 'warning' : 'info' ?>">
                                                                <?= htmlspecialchars($emp['jenis_karyawan']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group" role="group">
                                                                <?php
                                                                    $pdfUrl = $emp['jenis_karyawan'] == 'Kontrak' 
                                                                        ? "/gg_app/pages/esign/generate_pdf_sudahttd.php?nik=" . urlencode($emp['nik'])
                                                                        : "/gg_app/pages/esign/generate_pdf_sudahttd_staff.php?nik=" . urlencode($emp['nik']);
                                                                ?>
                                                                <a href="<?= $pdfUrl ?>" 
                                                                   class="btn btn-info btn-sm"
                                                                   title="Generate PDF"
                                                                   target="_blank">
                                                                    <i class="fas fa-file-pdf"></i>
                                                                </a>
                                                                
                                                                <button type="button" 
                                                                        class="btn btn-danger btn-sm" 
                                                                        onclick="confirmDelete('<?= htmlspecialchars($emp['nik']) ?>', '<?= htmlspecialchars($emp['jenis_karyawan']) ?>')"
                                                                        title="Hapus Tanda Tangan">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                                            <br>
                                                            Tidak ada data <?= $jenis_karyawan == 'kontrak' ? 'karyawan kontrak' : 'karyawan staff' ?> dengan tanda tangan.
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Defer non-critical JavaScript -->
<script>
    // Inline minimal JavaScript untuk fungsi penting
    document.addEventListener('DOMContentLoaded', function() {
        // Hilangkan loading state setelah halaman siap
        setTimeout(function() {
            document.querySelector('.table-loading').style.display = 'none';
            document.querySelector('.table-content').style.display = 'block';
        }, 100);
        
        <?php if ($notif): ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: "<?= $notif['status'] ?>",
                    title: "<?= $notif['status'] == 'success' ? 'Berhasil!' : 'Gagal!' ?>",
                    text: "<?= $notif['message'] ?>",
                    showConfirmButton: false,
                    timer: 2500
                });
            }
        <?php endif; ?>
    });

    function confirmDelete(nik, jenisKaryawan) {
        const jenisText = jenisKaryawan == 'Kontrak' ? 'karyawan kontrak' : 'karyawan staff';
        
        if (typeof Swal === 'undefined') {
            if (confirm(`Hapus tanda tangan untuk ${nik} (${jenisText})?`)) {
                const deleteUrl = jenisKaryawan == 'Kontrak' 
                    ? "/gg_app/pages/esign/hapus_data_ttd_kontrak.php?nik=" + nik
                    : "/gg_app/pages/esign/hapus_data_ttd_staff.php?nik=" + nik;
                window.location.href = deleteUrl;
            }
            return;
        }
        
        Swal.fire({
            title: "Apakah Anda yakin?",
            html: `Tanda tangan untuk <strong>${nik}</strong> (${jenisText}) akan dihapus!`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Ya, hapus!",
            cancelButtonText: "Batal"
        }).then((result) => {
            if (result.isConfirmed) {
                const deleteUrl = jenisKaryawan == 'Kontrak' 
                    ? "/gg_app/pages/esign/hapus_data_ttd_kontrak.php?nik=" + nik
                    : "/gg_app/pages/esign/hapus_data_ttd_staff.php?nik=" + nik;
                window.location.href = deleteUrl;
            }
        });
    }
</script>

<!-- Load JavaScript secara deferred -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js" defer></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js" defer></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js" defer></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js" defer></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js" defer></script>

<!-- Initialize DataTables setelah semua script loaded -->
<script defer>
    window.addEventListener('load', function() {
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $("#dataTable").DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang ditemukan",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Berikutnya",
                        previous: "Sebelumnya"
                    }
                },
                initComplete: function() {
                    // Sembunyikan loading indicator sepenuhnya setelah DataTables siap
                    document.querySelector('.table-loading').style.display = 'none';
                }
            });
        }
    });
</script>

</body>
</html>

<?php 
// Clean output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
include '../../includes/footer.php'; 
?>