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

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'] ?? 0;

// Ambil MenuId untuk Inspecting Weaving
$menuId = 24; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
if ($stmt) sqlsrv_free_stmt($stmt);

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}
ob_end_flush();
?>


<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hasil Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Hasil Inspect Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title">Hasil Inspect Weaving</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="dataCacatTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:50px">No</th>
                                    <th>No CP</th>
                                    <th>Total Kode Cacat</th>
                                    <th>Total Meter Cacat</th>
                                    <th>Total Point Cacat</th>
                                    <th>Grade</th>
                                    <th style="width:110px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    $(document).ready(function () {
        var table = $('#dataCacatTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'ajax.dataresult_inspecting_weaving_summary.php',
                type: 'GET'
            },
            columns: [
                { data: 'no', className: 'text-center' },
                { data: 'NoCP', className: 'text-center' },
                { data: 'TotalKodeCacat', className: 'text-center' },
                { data: 'TotalMeterCacat_display', className: 'text-center' }, // tampilkan formatted string
                { data: 'TotalPointCacat_display', className: 'text-center' }, // tampilkan formatted string
                { data: 'Grade', className: 'text-center' },
                { data: 'Aksi', className: 'text-center', orderable: false, searchable: false }
            ],
            // agar sorting default by NoCP desc
            order: [[1, 'desc']],
            lengthMenu: [10, 25, 50],
            responsive: true,
            deferRender: true
        });

        // optional: ketika klik row -> misal view
        $('#dataCacatTable tbody').on('click', 'a.view-btn', function(e){
            // contoh handler jika butuh
        });
    });
</script>
