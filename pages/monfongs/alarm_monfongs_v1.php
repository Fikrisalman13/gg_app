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

// Hak akses (sesuaikan MenuId untuk Alarm Monfongs)
$groupId = $_SESSION['GroupId'];
$menuId  = 138; // Sesuaikan dengan MenuId untuk halaman Alarm Monfongs

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
$selected_machine = 'MON4'; // Default value
$start_date = '';
$end_date = '';

// Proses filter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    $selected_machine = $_POST['machine'] ?? 'MON4';
    $start_date = str_replace("T", " ", $_POST['start_date']) . ":00";
    $end_date = str_replace("T", " ", $_POST['end_date']) . ":00";

    $sqlData = "
        SELECT
	CASE
		
	WHEN
		mp.Machine = 'MON3' THEN
			'Paddry 3' 
			WHEN mp.Machine = 'MON2' THEN
			'Paddry 4' 
			WHEN mp.Machine = 'MON4' THEN
			'Paddry 5' 
			WHEN mp.Machine = 'MON5' THEN
			'Paddry 6' ELSE mp.Machine 
		END AS MachineName, 
	mp.LogTimeStamp, 
	mp.AlarmNo, 
	ma.Name AS AlarmName
FROM
	dbo.machineprotocol AS mp
	LEFT JOIN
	dbo.machgrpalarm AS ma
	ON 
		mp.AlarmNo = ma.ID
WHERE
	mp.Machine IN (?) AND
	mp.LogTimeStamp BETWEEN ? AND ? AND
	ma.ID IN (
	'215',
    '117',
    '2196',
    '769',
    '770',
    '771',
    '1151',
    '1156',
    '1269',
    '1274',
    '2191',
    '1105',
    '1107',
    '1176',
    '1178',
    '1180',
    '1182',
    '3156',
    '3157',
    '1104',
    '1106',
    '1175',
    '1177',
    '1179',
    '1181',
    '3154',
    '3155',
    '979',
    '983',
    '1560',
    '1564',
    '295',
    '1380',
    '967',
    '975',
    '1569',
    '1574',
    '968',
    '976',
    '1570',
    '1575',
    '964',
    '1540',
    '4800'
)
ORDER BY
	MachineName ASC, 
	mp.LogTimeStamp ASC
    ";

    $stmtData = sqlsrv_prepare($conn4, $sqlData, [$selected_machine, $start_date, $end_date]);

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
                    <h1 class="m-0">Alarm Mesin Monfongs</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Alarm Monfongs</li>
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
                            <i class="fas fa-filter"></i> Filter Data Alarm
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="machine" class="form-label fw-bold">Pilih Mesin</label>
                                    <select id="machine" name="machine" class="form-control form-control-sm" required>
                                        <option value="MON4" <?= $selected_machine == 'MON4' ? 'selected' : '' ?>>Paddry 5 (MON4)</option>
                                        <option value="MON3" <?= $selected_machine == 'MON3' ? 'selected' : '' ?>>Paddry 3 (MON3)</option>
                                        <option value="MON2" <?= $selected_machine == 'MON2' ? 'selected' : '' ?>>Paddry 4 (MON2)</option>
                                        <option value="MON5" <?= $selected_machine == 'MON5' ? 'selected' : '' ?>>Paddry 6 (MON5)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                                    <input type="datetime-local" id="start_date" name="start_date"
                                           class="form-control form-control-sm" 
                                           value="<?= !empty($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : '' ?>" 
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                                    <input type="datetime-local" id="end_date" name="end_date"
                                           class="form-control form-control-sm" 
                                           value="<?= !empty($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : '' ?>" 
                                           required>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button type="submit" name="filter" value="1"
                                            class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                        <i class="fas fa-search"></i> Proses
                                    </button>
                                    <?php if (!empty($_POST['start_date']) && !empty($_POST['end_date'])): ?>
                                        <a href="export_alarm_monfongs_pdf.php?machine=<?= urlencode($selected_machine) ?>&start_date=<?= urlencode($_POST['start_date']) ?>&end_date=<?= urlencode($_POST['end_date']) ?>"
                                           class="btn btn-danger btn-sm ms-2" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Export PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabel hasil -->
                <?php if (!empty($data)): ?>
                    <div class="card">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                            <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                            Data Alarm Mesin</h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table id="alarmMonfongsTable" class="table table-hover table-sm">
                                <thead class="thead-light">
                                <tr>
                                    <th>Nama Mesin</th>
                                    <th>Waktu Alarm</th>
                                    <th>Kode Alarm</th>
                                    <th>Nama Alarm</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['MachineName']) ?></td>
                                        <td><?= htmlspecialchars($row['LogTimeStamp']->format('Y-m-d H:i:s')) ?></td>
                                        <td><?= htmlspecialchars($row['AlarmNo']) ?></td>
                                        <td><?= htmlspecialchars($row['AlarmName']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="alert alert-warning">Data alarm tidak ditemukan untuk rentang tanggal dan mesin yang dipilih.</div>
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
        $('#alarmMonfongsTable').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [[1, 'desc']], // Default urutkan berdasarkan waktu alarm descending
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
