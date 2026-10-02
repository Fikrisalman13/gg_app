<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi4.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Cek koneksi database utama
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Hak akses (contoh MenuId = 85 untuk monitoring Monfongs 1)
$groupId = $_SESSION['GroupId'];
$menuId  = 84;

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
        FROM dbo.SMGroupTrustee
        WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

$permissions = [];
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

// Variabel data hasil query
$data = [];

// Proses filter tanggal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    $start_date = str_replace("T", " ", $_POST['start_date']) . ":00";
    $end_date   = str_replace("T", " ", $_POST['end_date']) . ":00";

    $sqlData = "
        SELECT
    b.Machine AS Mesin,
    a.LogTimeStamp AS Waktu,
    ROUND(c.Value21/10, 0) AS [Speed],
    a.Value30 AS TC1,
    a.Value31 AS TC2,
    a.Value32 AS TC3,
    a.Value33 AS TC4,
    a.Value34 AS TC5,
    a.Value35 AS TC6,
    a.Value36 AS TC7,
    a.Value37 AS TC8,
    a.Value38 AS TC9,
    a.Value27 AS TC10,
    a.Value28 AS TC11,
    a.Value29 AS TC12,
    a.Value39 AS EX1,
    a.Value40 AS EX2,
    ROUND(c.Value03/10,0) AS ARM,
    c.Value31 AS AEAH,
    c.Value01 AS AFRM
FROM
    dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    LEFT JOIN dbo.logvaluefloat AS c ON a.LogTimeStamp = c.LogTimeStamp 
        AND c.LogType_ID = '1112'
    LEFT JOIN dbo.logtype AS d ON c.LogType_ID = d.ID 
WHERE
    a.LogType_ID = '1111' 
    AND a.LogTimeStamp BETWEEN ? AND ?
ORDER BY a.LogTimeStamp ASC
            ";

    $stmtData = sqlsrv_prepare($conn4, $sqlData, [$start_date, $end_date]);

    if ($stmtData) {
        sqlsrv_execute($stmtData);
        while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}

ob_end_flush();
?>


<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Data Mesin Monfongs 1 (Stenter)</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Monfongs 1</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php else: ?>
                <!-- Filter -->
                <div class="card shadow-sm">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title m-0">
                            <i class="fas fa-filter"></i> Filter Rentang Tanggal
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3 align-items-end">
                                <!-- Kolom tanggal mulai -->
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                                    <input type="datetime-local" id="start_date" name="start_date"
                                        class="form-control form-control-sm" required>
                                </div>

                                <!-- Kolom tanggal selesai -->
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                                    <input type="datetime-local" id="end_date" name="end_date"
                                        class="form-control form-control-sm" required>
                                </div>

                                <!-- Tombol aksi -->
                                <div class="col-md-4 text-end">
                                    <button type="submit" name="filter" value="1"
                                            class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                        <i class="fas fa-search"></i> Proses
                                    </button>

                                    <?php if (!empty($_POST['start_date']) && !empty($_POST['end_date'])): ?>
                                        <a href="export_monfongs1_pdf.php?start_date=<?= urlencode($_POST['start_date']) ?>&end_date=<?= urlencode($_POST['end_date']) ?>"
                                        class="btn btn-danger btn-sm ms-2" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Export PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>

                <!-- Info singkatan -->
                <div class="alert alert-info">
                    <strong>Penjelasan Singkatan:</strong>
                    <ul class="mb-0">
                        <li>TC = Temp Chamber</li>
                        <li>EX = Exhaust</li>
                        <li>AEAH = Actual exhaust air humidity</li>
                        <li>AFRM = Actual type of fibre residual moisture</li>
                        <li>ARM = Actual Residual Moisture</li>                       
        
                    </ul>
                </div>

                <!-- Tabel hasil -->
                <?php if (!empty($data)): ?>
                    <div class="card">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
                            <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                            Hasil Data Mesin</h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table id="monfongsTable" class="table table-hover table-sm">
                                <thead class="thead-light">
                                <tr>
                                    <th>Mesin</th>
                                    <th>Waktu</th>
                                    <th>Speed</th>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <th>TC<?= $i ?></th>
                                    <?php endfor; ?>
                                    <th>EX1</th>
                                    <th>EX2</th>                                    
                                    <th>AEAH (%)</th>
                                    <th>AFRM (%)</th>
                                    <th>ARM (%)</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-center">
                                        <td><?= htmlspecialchars($row['Mesin']) ?></td>
                                        <td><?= htmlspecialchars($row['Waktu']->format('Y-m-d H:i:s')) ?></td>
                                        <td><?= htmlspecialchars($row['Speed']) ?></td>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <td><?= htmlspecialchars($row["TC{$i}"]) ?></td>
                                        <?php endfor; ?>
                                        <td><?= htmlspecialchars($row['EX1']) ?></td>
                                        <td><?= htmlspecialchars($row['EX2']) ?></td>
                                        <td><?= htmlspecialchars($row['AEAH']) ?></td>
                                         <td><?= htmlspecialchars($row['AFRM']) ?></td>
                                        <td><?= htmlspecialchars($row['ARM']) ?></td>
                                        
                                       
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="alert alert-warning">Data tidak ditemukan untuk rentang tanggal yang dipilih.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
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
        $('#monfongsTable').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            language: {
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });
    });
</script>

