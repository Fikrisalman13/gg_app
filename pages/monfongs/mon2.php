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

if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Hak akses (contoh MenuId = 86 untuk Monfongs 2, silakan sesuaikan)
$groupId = $_SESSION['GroupId'];
$menuId  = 85;

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

$data = [];

// Proses filter tanggal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    $start_date = str_replace("T", " ", $_POST['start_date']) . ":00";
    $end_date   = str_replace("T", " ", $_POST['end_date']) . ":00";

    $sqlData = "
        SELECT
            b.Machine AS Mesin, 
            a.LogTimeStamp AS Waktu,
            ROUND(c.Value01/10, 0) AS [Speed],
            c.Value02 AS [TD1], 
            c.Value03 AS [TD2], 
            a.Value01 AS [EXT1], 
            a.Value02 AS [EXT2], 
            a.Value03 AS [EXT3], 
            a.Value04 AS [EXT4], 
            a.Value13 AS [NTA1], 
            a.Value14 AS [NTA2], 
            a.Value15 AS [NTA3], 
            a.Value16 AS [NTA4], 
            a.Value17 AS [NTB1], 
            a.Value18 AS [NTB2], 
            a.Value19 AS [NTB3], 
            a.Value20 AS [NTB4], 
            a.Value23 AS [HCT1], 
            a.Value25 AS [HCT2], 
            a.Value27 AS [HCT3], 
            a.Value29 AS [HCT4]
        FROM dbo.logvaluefloat AS a
        LEFT JOIN dbo.logtype AS b ON a.LogType_ID = b.ID
        LEFT JOIN dbo.logvaluefloat AS c ON c.LogTimeStamp = a.LogTimeStamp AND c.LogType_ID = '1212'
        LEFT JOIN dbo.logtype AS d ON d.ID = c.LogType_ID
        WHERE a.LogType_ID = '1211'
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
                    <h1 class="m-0">Data Mesin Monfongs 2 (Paddry 4)</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Monfongs 2</li>
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
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                                    <input type="datetime-local" id="start_date" name="start_date"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                                    <input type="datetime-local" id="end_date" name="end_date"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="submit" name="filter" value="1"
                                            class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                        <i class="fas fa-search"></i> Proses
                                    </button>
                                    <?php if (!empty($_POST['start_date']) && !empty($_POST['end_date'])): ?>
                                        <a href="export_monfongs2_pdf.php?start_date=<?= urlencode($_POST['start_date']) ?>&end_date=<?= urlencode($_POST['end_date']) ?>"
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
                        <li>TD = Temp Dryer</li>
                        <li>EXT = Exhaust Thermex</li>
                        <li>NTA = Nozzle Thermex Atas</li>
                        <li>NTB = Nozzle Thermex Bawah</li>
                        <li>HCT = Heating Chamber Thermex</li>
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
                            <table id="monfongs2Table" class="table table-hover table-sm">
                                <thead class="thead-light">
                                <tr>
                                    <th>Mesin</th>
                                    <th>Waktu</th>
                                    <th>Speed</th>
                                    <th>TD1</th><th>TD2</th>
                                    <th>EXT1</th><th>EXT2</th><th>EXT3</th><th>EXT4</th>
                                    <th>NTA1</th><th>NTA2</th><th>NTA3</th><th>NTA4</th>
                                    <th>NTB1</th><th>NTB2</th><th>NTB3</th><th>NTB4</th>
                                    <th>HCT1</th><th>HCT2</th><th>HCT3</th><th>HCT4</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-center">
                                        <td><?= htmlspecialchars($row['Mesin']) ?></td>
                                        <td><?= htmlspecialchars($row['Waktu']->format('Y-m-d H:i:s')) ?></td>
                                        <td><?= htmlspecialchars($row['Speed']) ?></td>
                                        <td><?= htmlspecialchars($row['TD1']) ?></td>
                                        <td><?= htmlspecialchars($row['TD2']) ?></td>
                                        <td><?= htmlspecialchars($row['EXT1']) ?></td>
                                        <td><?= htmlspecialchars($row['EXT2']) ?></td>
                                        <td><?= htmlspecialchars($row['EXT3']) ?></td>
                                        <td><?= htmlspecialchars($row['EXT4']) ?></td>
                                        <td><?= htmlspecialchars($row['NTA1']) ?></td>
                                        <td><?= htmlspecialchars($row['NTA2']) ?></td>
                                        <td><?= htmlspecialchars($row['NTA3']) ?></td>
                                        <td><?= htmlspecialchars($row['NTA4']) ?></td>
                                        <td><?= htmlspecialchars($row['NTB1']) ?></td>
                                        <td><?= htmlspecialchars($row['NTB2']) ?></td>
                                        <td><?= htmlspecialchars($row['NTB3']) ?></td>
                                        <td><?= htmlspecialchars($row['NTB4']) ?></td>
                                        <td><?= htmlspecialchars($row['HCT1']) ?></td>
                                        <td><?= htmlspecialchars($row['HCT2']) ?></td>
                                        <td><?= htmlspecialchars($row['HCT3']) ?></td>
                                        <td><?= htmlspecialchars($row['HCT4']) ?></td>
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
        $('#monfongs2Table').DataTable({
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

