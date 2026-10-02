<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 75; // Sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Ambil parameter dari GET
$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$workcenterId = isset($_GET['workcenterid']) ? trim($_GET['workcenterid']) : '111';
$results = [];
$workcenterOptions = [
    '111' => 'DYEING',
    '118' => 'SIZING BARU',
    '125' => 'WARPING BARU',
    '100' => 'WEAVING BARU',
];

if (!isset($workcenterOptions[$workcenterId])) {
    $workcenterId = '111';
}

if (!function_exists('formatDurationMinutesSeconds')) {
    function formatDurationMinutesSeconds($startTime, $endTime) {
        if (empty($startTime) || empty($endTime)) {
            return '';
        }

        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);
        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp < $startTimestamp) {
            return '';
        }

        $totalSeconds = $endTimestamp - $startTimestamp;
        $minutes = floor($totalSeconds / 60);
        $seconds = $totalSeconds % 60;

        return $minutes . ' menit ' . $seconds . ' detik';
    }
}

// Cek apakah ada parameter pencarian
if ($cpno !== '' || $dateFrom !== '' || $dateTo !== '') {
    if ($cpno !== '') {
        $query = "WITH hd AS (\n    SELECT \n        productionhdid, \n        prdnmbr,\n        prddate\n    FROM pdproductionhd\n    WHERE prdnmbr = :cpno\n      AND workcenterid = :workcenterid\n),\nlast_p AS (\n    SELECT \n        r.productionhdid,\n        MAX(r.rtgseq) AS last_p_seq\n    FROM pdproductionrtg r\n    JOIN hd h \n        ON h.productionhdid = r.productionhdid\n    GROUP BY r.productionhdid\n)\nSELECT \n    h.prdnmbr,\n    h.prddate,\n    r.rtgseq,\n    r.rtgmsid,\n    m.rtgname,\n    m.rtgcode,\n    r.starttime,\n    r.endtime,\n    r.upddate,\n    r.upduser,\n    e.empname\nFROM pdproductionrtg r\nJOIN hd h \n    ON h.productionhdid = r.productionhdid\nJOIN last_p lp \n    ON lp.productionhdid = r.productionhdid\nLEFT JOIN pdrtgms m \n    ON m.rtgmsid = r.rtgmsid\nLEFT JOIN msuser u\n    ON u.userid = r.upduser\nLEFT JOIN smemployee e\n    ON e.empid = u.empid\nWHERE r.rtgseq <= lp.last_p_seq + 1\nORDER BY h.prdnmbr, r.rtgseq;";
        $stmt = $conn3->prepare($query);
        $stmt->bindValue(':cpno', $cpno);
        $stmt->bindValue(':workcenterid', $workcenterId);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($dateFrom !== '' && $dateTo !== '') {
        $query = "WITH hd AS (\n    SELECT \n        productionhdid, \n        prdnmbr,\n        prddate\n    FROM pdproductionhd\n    WHERE prddate BETWEEN :date_from AND :date_to\n      AND workcenterid = :workcenterid\n),\nlast_p AS (\n    SELECT \n        r.productionhdid,\n        MAX(r.rtgseq) AS last_p_seq\n    FROM pdproductionrtg r\n    JOIN hd h \n        ON h.productionhdid = r.productionhdid\n   \n    GROUP BY r.productionhdid\n)\nSELECT \n    h.prdnmbr,\n    h.prddate,\n    r.rtgseq,\n    r.rtgmsid,\n    m.rtgname,\n    m.rtgcode,\n    r.starttime,\n    r.endtime,\n    r.upddate,\n    r.upduser,\n    e.empname\nFROM pdproductionrtg r\nJOIN hd h \n    ON h.productionhdid = r.productionhdid\nJOIN last_p lp \n    ON lp.productionhdid = r.productionhdid\nLEFT JOIN pdrtgms m \n    ON m.rtgmsid = r.rtgmsid\nLEFT JOIN msuser u\n    ON u.userid = r.upduser\nLEFT JOIN smemployee e\n    ON e.empid = u.empid\nWHERE r.rtgseq <= lp.last_p_seq + 1\nORDER BY h.prdnmbr, r.rtgseq;";
        $stmt = $conn3->prepare($query);
        $stmt->bindValue(':date_from', $dateFrom);
        $stmt->bindValue(':date_to', $dateTo);
        $stmt->bindValue(':workcenterid', $workcenterId);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">History CP Routing</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">History CP Routing</li>
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
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                                History CP Routing</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="cpno">No CP:</label>
                                                <input type="text" id="cpno" name="cpno" class="form-control form-control-sm" 
                                                       value="<?= htmlspecialchars($cpno) ?>" placeholder="Masukkan no CP">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="workcenterid">Work Center:</label>
                                                <select id="workcenterid" name="workcenterid" class="form-control form-control-sm">
                                                    <?php foreach ($workcenterOptions as $optionValue => $optionLabel) : ?>
                                                        <option value="<?= htmlspecialchars($optionValue) ?>" <?= $workcenterId === $optionValue ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($optionLabel) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="date_from">Tanggal Dari:</label>
                                                <input type="date" id="date_from" name="date_from" class="form-control form-control-sm"
                                                       value="<?= htmlspecialchars($dateFrom) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="date_to">Sampai Tanggal:</label>
                                                <input type="date" id="date_to" name="date_to" class="form-control form-control-sm"
                                                       value="<?= htmlspecialchars($dateTo) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group" style="margin-top: 32px;">
                                                <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                                    <i class="fas fa-search"></i> Cari
                                                </button>
                                                <a href="?" class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-refresh"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <?php if (!empty($results)) : ?>
                                    <a
                                        href="export_excel_history_cp_routing.php?cpno=<?= urlencode($cpno) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&workcenterid=<?= urlencode($workcenterId) ?>"
                                        class="btn btn-success btn-sm mb-3"
                                    >
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                <?php endif; ?>
                                <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="cpRoutingTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>No CP</th>
                                                <th>Production Date</th>
                                                <th>Routing Seq</th>
                                                <th>Routing Code</th>
                                                <th>Routing Name</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Durasi</th>
                                                <th>Update By</th>
                                                <th>Emp Name</th>
                                                <th>Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                $updUser = trim((string) ($row['upduser'] ?? ''));
                                                $empName = trim((string) ($row['empname'] ?? ''));
                                                $duration = formatDurationMinutesSeconds($row['starttime'] ?? '', $row['endtime'] ?? '');
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td>".htmlspecialchars($row['prdnmbr'])."</td>";
                                                echo "<td>".(!empty($row['prddate']) ? date("d/m/Y", strtotime($row['prddate'])) : '')."</td>";
                                                echo "<td class='text-center'>".htmlspecialchars($row['rtgseq'])."</td>";
                                                echo "<td>".htmlspecialchars($row['rtgcode'] ?? '')."</td>";
                                                echo "<td>".htmlspecialchars($row['rtgname'])."</td>";
                                                echo "<td>".($row['starttime'] ? date("d/m/Y H:i", strtotime($row['starttime'])) : '')."</td>";
                                                echo "<td>".($row['endtime'] ? date("d/m/Y H:i", strtotime($row['endtime'])) : '')."</td>";
                                                echo "<td>".htmlspecialchars($duration)."</td>";
                                                echo "<td>".htmlspecialchars($updUser)."</td>";
                                                echo "<td>".htmlspecialchars($empName)."</td>";
                                                echo "<td>".($row['upddate'] ? date("d/m/Y H:i", strtotime($row['upddate'])) : '')."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            if ($cpno !== '' || $dateFrom !== '' || $dateTo !== '') {
                                                echo "<tr><td colspan='12' class='text-center py-4 text-danger'>Data tidak ditemukan untuk filter yang dipilih.</td></tr>";
                                            } else {
                                                echo "<tr><td colspan='12' class='text-center py-4'>Silakan masukkan No CP atau pilih rentang tanggal</td></tr>";
                                            }
                                        }
                                        ?>
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
</div>
<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        var hasColspanRow = $("#cpRoutingTable tbody tr td[colspan]").length > 0;
        if (!hasColspanRow) {
            $("#cpRoutingTable").DataTable({
                responsive: true,
                autoWidth: false,
                language: {
                    processing: "Memproses...",
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
                },
            });
        }
    });
</script>
