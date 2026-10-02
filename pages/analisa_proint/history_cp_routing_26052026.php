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
$entryFrom = isset($_GET['entry_from']) ? trim($_GET['entry_from']) : '';
$entryTo = isset($_GET['entry_to']) ? trim($_GET['entry_to']) : '';
$workcenterId = isset($_GET['workcenterid']) ? trim($_GET['workcenterid']) : '111';
$routingStartId = isset($_GET['routing_start_id']) ? trim($_GET['routing_start_id']) : '';
$routingEndId = isset($_GET['routing_end_id']) ? trim($_GET['routing_end_id']) : '';
$results = [];
$routingOptions = [];
$defaultRoutingStartId = '';
$defaultRoutingEndId = '';
$workcenterOptions = [
    '111' => 'DYEING',
    '118' => 'SIZING BARU',
    '125' => 'WARPING BARU',
    '100' => 'WEAVING BARU',
];

if (!isset($workcenterOptions[$workcenterId])) {
    $workcenterId = '111';
}

$routingOptionSql = "SELECT DISTINCT
    m.rtgmsid,
    COALESCE(m.rtgcode, '') AS rtgcode,
    COALESCE(m.rtgname, '') AS rtgname
FROM pdproductionrtg r
JOIN pdproductionhd h
    ON h.productionhdid = r.productionhdid
JOIN pdrtgms m
    ON m.rtgmsid = r.rtgmsid
WHERE h.workcenterid = :workcenterid
  AND COALESCE(m.rtgname, '') <> ''
ORDER BY rtgcode, rtgname";
$routingOptionStmt = $conn3->prepare($routingOptionSql);
$routingOptionStmt->bindValue(':workcenterid', $workcenterId);
$routingOptionStmt->execute();
$routingOptions = $routingOptionStmt->fetchAll(PDO::FETCH_ASSOC);

$validRoutingIds = array_map('strval', array_column($routingOptions, 'rtgmsid'));
foreach ($routingOptions as $routingOption) {
    $optionId = (string) ($routingOption['rtgmsid'] ?? '');
    $optionCode = strtoupper(trim((string) ($routingOption['rtgcode'] ?? '')));
    $optionName = strtoupper(trim((string) ($routingOption['rtgname'] ?? '')));

    if ($defaultRoutingStartId === '' && $optionCode === '01000' && $optionName === 'PEMARTAIAN') {
        $defaultRoutingStartId = $optionId;
    }
    if ($defaultRoutingEndId === '' && $optionCode === '09900' && $optionName === 'VERPACKING') {
        $defaultRoutingEndId = $optionId;
    }
}

if ($routingStartId === '') {
    $routingStartId = $defaultRoutingStartId;
}
if ($routingEndId === '') {
    $routingEndId = $defaultRoutingEndId;
}
if ($routingStartId !== '' && !in_array($routingStartId, $validRoutingIds, true)) {
    $routingStartId = $defaultRoutingStartId;
}
if ($routingEndId !== '' && !in_array($routingEndId, $validRoutingIds, true)) {
    $routingEndId = $defaultRoutingEndId;
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

$hasSearch = count($_GET) > 0;

// Cek apakah ada parameter pencarian
if ($hasSearch) {
    $whereConditions = ["h.workcenterid = :workcenterid"];
    $params = [':workcenterid' => $workcenterId];

    if ($cpno !== '') {
        $whereConditions[] = "h.prdnmbr = :cpno";
        $params[':cpno'] = $cpno;
    }

    if ($dateFrom !== '' && $dateTo !== '') {
        $whereConditions[] = "h.prddate BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $dateFrom;
        $params[':date_to'] = $dateTo;
    }

    if ($entryFrom !== '') {
        $params[':entry_from'] = $entryFrom . ' 00:00:00';
    }

    if ($entryTo !== '') {
        $params[':entry_to'] = $entryTo . ' 23:59:59';
    }

    if ($routingStartId !== '') {
        $params[':routing_start_id'] = $routingStartId;
    }

    if ($routingEndId !== '') {
        $params[':routing_end_id'] = $routingEndId;
    }

    $query = "WITH hd AS (
    SELECT
        h.productionhdid,
        h.prdnmbr,
        h.prddate
    FROM pdproductionhd h
    WHERE " . implode("\n      AND ", $whereConditions) . "
),
start_bounds AS (
    SELECT
        r.productionhdid,
        MIN(r.rtgseq) AS start_seq
    FROM pdproductionrtg r
    JOIN hd h
        ON h.productionhdid = r.productionhdid
    WHERE 1 = 1
      " . ($routingStartId !== '' ? "AND r.rtgmsid = :routing_start_id" : "") . "
    GROUP BY r.productionhdid
),
end_bounds AS (
    SELECT
        r.productionhdid,
        MIN(r.rtgseq) AS end_seq
    FROM pdproductionrtg r
    JOIN start_bounds sb
        ON sb.productionhdid = r.productionhdid
    WHERE sb.start_seq IS NOT NULL
      AND r.rtgseq >= sb.start_seq
      AND r.fgresult = 'P'
      " . ($routingEndId !== '' ? "AND r.rtgmsid = :routing_end_id" : "") . "
      " . ($entryFrom !== '' ? "AND r.starttime >= :entry_from" : "") . "
      " . ($entryTo !== '' ? "AND r.starttime <= :entry_to" : "") . "
    GROUP BY r.productionhdid
)
SELECT
    h.prdnmbr,
    h.prddate,
    r.rtgseq,
    r.rtgmsid,
    m.rtgname,
    m.rtgcode,
    r.starttime,
    r.endtime,
    r.upddate,
    r.upduser,
    e.empname
FROM pdproductionrtg r
JOIN hd h
    ON h.productionhdid = r.productionhdid
JOIN start_bounds sb
    ON sb.productionhdid = r.productionhdid
JOIN end_bounds eb
    ON eb.productionhdid = r.productionhdid
LEFT JOIN pdrtgms m
    ON m.rtgmsid = r.rtgmsid
LEFT JOIN msuser u
    ON u.userid = r.upduser
LEFT JOIN smemployee e
    ON e.empid = u.empid
WHERE sb.start_seq IS NOT NULL
  AND eb.end_seq IS NOT NULL
  AND r.rtgseq BETWEEN sb.start_seq AND eb.end_seq
ORDER BY h.prdnmbr, r.rtgseq;";

    $stmt = $conn3->prepare($query);
    foreach ($params as $paramName => $paramValue) {
        $stmt->bindValue($paramName, $paramValue);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<style>
    .filter-group-title {
        display: inline-block;
        margin-bottom: 6px;
        padding-bottom: 2px;
        border-bottom: 1px solid #555;
        font-weight: 700;
        font-size: 14px;
    }

    .filter-block {
        padding: 2px 0 6px;
        margin-bottom: 6px;
    }

    .filter-line {
        display: grid;
        grid-template-columns: 48px 8px minmax(150px, 235px) 42px 8px minmax(150px, 235px);
        align-items: center;
        column-gap: 4px;
        row-gap: 6px;
    }

    .filter-line-label {
        font-weight: 400;
        margin-bottom: 0;
    }

    .filter-line-colon {
        text-align: center;
    }

    .filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        height: 100%;
        padding-bottom: 10px;
    }

    @media (max-width: 767.98px) {
        .filter-line {
            grid-template-columns: 48px 8px minmax(0, 1fr);
        }

        .filter-actions {
            align-items: flex-start;
            padding-bottom: 0;
        }
    }
</style>
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
                                        <div class="col-lg-6 col-12">
                                            <div class="form-group filter-block">
                                                <div class="filter-group-title">Production Date</div>
                                                <div class="filter-line">
                                                    <label class="filter-line-label" for="date_from">Start</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <input type="date" id="date_from" name="date_from" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($dateFrom) ?>">
                                                    <label class="filter-line-label" for="date_to">End</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <input type="date" id="date_to" name="date_to" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($dateTo) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-12">
                                            <div class="form-group filter-block">
                                                <div class="filter-group-title">Entry Time</div>
                                                <div class="filter-line">
                                                    <label class="filter-line-label" for="entry_from">Start</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <input type="date" id="entry_from" name="entry_from" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($entryFrom) ?>">
                                                    <label class="filter-line-label" for="entry_to">End</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <input type="date" id="entry_to" name="entry_to" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($entryTo) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-12">
                                            <div class="form-group filter-block">
                                                <div class="filter-group-title">Routing</div>
                                                <div class="filter-line">
                                                    <label class="filter-line-label" for="routing_start_id">Start</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <select id="routing_start_id" name="routing_start_id" class="form-control form-control-sm">
                                                        <option value="">Semua Routing Start</option>
                                                        <?php foreach ($routingOptions as $routingOption) : ?>
                                                            <?php
                                                            $optionValue = (string) ($routingOption['rtgmsid'] ?? '');
                                                            $optionCode = trim((string) ($routingOption['rtgcode'] ?? ''));
                                                            $optionName = trim((string) ($routingOption['rtgname'] ?? ''));
                                                            $optionLabel = trim($optionCode . ' - ' . $optionName, ' -');
                                                            ?>
                                                            <option value="<?= htmlspecialchars($optionValue) ?>" <?= $routingStartId === $optionValue ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($optionLabel) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label class="filter-line-label" for="routing_end_id">End</label>
                                                    <span class="filter-line-colon">:</span>
                                                    <select id="routing_end_id" name="routing_end_id" class="form-control form-control-sm">
                                                        <option value="">Semua Routing End</option>
                                                        <?php foreach ($routingOptions as $routingOption) : ?>
                                                            <?php
                                                            $optionValue = (string) ($routingOption['rtgmsid'] ?? '');
                                                            $optionCode = trim((string) ($routingOption['rtgcode'] ?? ''));
                                                            $optionName = trim((string) ($routingOption['rtgname'] ?? ''));
                                                            $optionLabel = trim($optionCode . ' - ' . $optionName, ' -');
                                                            ?>
                                                            <option value="<?= htmlspecialchars($optionValue) ?>" <?= $routingEndId === $optionValue ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($optionLabel) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group filter-actions">
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
                                        href="export_excel_history_cp_routing.php?cpno=<?= urlencode($cpno) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&entry_from=<?= urlencode($entryFrom) ?>&entry_to=<?= urlencode($entryTo) ?>&workcenterid=<?= urlencode($workcenterId) ?>&routing_start_id=<?= urlencode($routingStartId) ?>&routing_end_id=<?= urlencode($routingEndId) ?>"
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
                                            if ($hasSearch) {
                                                echo "<tr><td colspan='12' class='text-center py-4 text-danger'>Data tidak ditemukan untuk filter yang dipilih.</td></tr>";
                                            } else {
                                                echo "<tr><td colspan='12' class='text-center py-4'>Silakan masukkan filter lalu klik Cari</td></tr>";
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
