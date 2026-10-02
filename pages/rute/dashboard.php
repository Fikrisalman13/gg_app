<?php
session_start();
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

// ---------- Auth ----------
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}

// ---------- Setup ----------
date_default_timezone_set('Asia/Jakarta');
if (!$conn) die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));

// ---------- Permissions ----------
$groupId = $_SESSION['GroupId'] ?? null;
$menuId  = 120;
$permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1];

if ($groupId !== null) {
    $permQuery = sqlsrv_query($conn, "
        SELECT CanView, CanAdd, CanEdit, CanDelete
        FROM dbo.SMGroupTrustee
        WHERE GroupId = ? AND MenuId = ?
    ", [$groupId, $menuId]);
    if ($permQuery && ($row = sqlsrv_fetch_array($permQuery, SQLSRV_FETCH_ASSOC))) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($permQuery);
}
if (empty($permissions['CanView'])) $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";

// ---------- Date Filter (default: hari ini) ----------
$today = (new DateTime())->format('Y-m-d');
$start_in = $_GET['start'] ?? $today;
$end_in   = $_GET['end']   ?? $today;

$start_dt = DateTime::createFromFormat('Y-m-d', $start_in) ?: new DateTime($today);
$end_dt   = DateTime::createFromFormat('Y-m-d', $end_in)   ?: new DateTime($today);
$start_dt->setTime(0,0,0);
$end_dt->setTime(23,59,59);

// ---------- Fungsi Ambil Data ----------
function getRouteStats($conn, DateTime $start_dt, DateTime $end_dt) {
    $stats = [
        'total_routes'     => 0,
        'active_routes'    => 0,
        'completed_routes' => 0,
        'total_vehicles'   => 0,
        // GLOBAL (semua tanggal)
        'active_vehicles'  => 0,
        'total_drivers'    => 0,
        'active_drivers'   => 0,
    ];

    $sql = "
    -- TERFILTER tanggal
    SELECT 'total_routes' AS name, COUNT(*) AS total
      FROM dbo.Routes
     WHERE RouteDate BETWEEN ? AND ?
    UNION ALL
    SELECT 'active_routes', COUNT(*)
      FROM dbo.Routes
     WHERE Status IN ('open','inprog') AND RouteDate BETWEEN ? AND ?
    UNION ALL
    SELECT 'completed_routes', COUNT(*)
      FROM dbo.Routes
     WHERE Status='done' AND RouteDate BETWEEN ? AND ?
    -- GLOBAL (tidak terfilter tanggal)
    UNION ALL
    SELECT 'total_vehicles', COUNT(*) FROM dbo.Vehicles
    UNION ALL
    SELECT 'active_vehicles', COUNT(DISTINCT VehiclePlate)
      FROM dbo.Routes
     WHERE Status IN ('open','inprog') AND VehiclePlate IS NOT NULL
    UNION ALL
    SELECT 'active_drivers', COUNT(DISTINCT DriverName)
      FROM dbo.Routes
     WHERE Status IN ('open','inprog') AND DriverName IS NOT NULL
    UNION ALL
    SELECT 'total_drivers', COUNT(DISTINCT u.EmpId)
      FROM dbo.SMUserMs u
      JOIN dbo.m_emp e ON e.id_emp = u.EmpId
     WHERE u.GroupId IN (29,41)
    ";

    $params = [
        $start_dt, $end_dt,
        $start_dt, $end_dt,
        $start_dt, $end_dt,
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $stats[$row['name']] = (int)$row['total'];
        }
        sqlsrv_free_stmt($stmt);
    }
    return $stats;
}

function getRecentRoutes($conn, DateTime $start_dt, DateTime $end_dt) {
    $data = [];
    $stmt = sqlsrv_query($conn, "
        SELECT TOP 50 RouteId, Name, VehiclePlate, DriverName, Status,
               CONVERT(varchar(10), RouteDate, 23) AS RouteDate, CreatedAt
          FROM dbo.Routes
         WHERE RouteDate BETWEEN ? AND ?
         ORDER BY CreatedAt DESC
    ", [$start_dt, $end_dt]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $r;
        sqlsrv_free_stmt($stmt);
    }
    return $data;
}

function getRecentActivities($conn, DateTime $start_dt, DateTime $end_dt) {
    $data = [];
    $stmt = sqlsrv_query($conn, "
        SELECT TOP 50 ra.Comment, ra.CreatedAt, r.Name as RouteName, r.VehiclePlate, r.DriverName
          FROM dbo.RouteActivities ra
          LEFT JOIN dbo.Routes r ON r.RouteId = ra.RouteId
         WHERE ra.CreatedAt BETWEEN ? AND ?
         ORDER BY ra.CreatedAt DESC
    ", [$start_dt, $end_dt]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $r;
        sqlsrv_free_stmt($stmt);
    }
    return $data;
}

function getTopVehicles($conn, DateTime $start_dt, DateTime $end_dt) {
    $data = [];
    $stmt = sqlsrv_query($conn, "
        SELECT TOP 5 VehiclePlate,
               COUNT(*) total_routes,
               SUM(CASE WHEN Status='done' THEN 1 ELSE 0 END) completed_routes
          FROM dbo.Routes
         WHERE VehiclePlate IS NOT NULL AND RouteDate BETWEEN ? AND ?
         GROUP BY VehiclePlate
         ORDER BY total_routes DESC
    ", [$start_dt, $end_dt]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $r;
        sqlsrv_free_stmt($stmt);
    }
    return $data;
}

function getTopDrivers($conn, DateTime $start_dt, DateTime $end_dt) {
    $data = [];
    $stmt = sqlsrv_query($conn, "
        SELECT TOP 4 DriverName,
               COUNT(*) total_routes,
               SUM(CASE WHEN Status='done' THEN 1 ELSE 0 END) completed_routes
          FROM dbo.Routes
         WHERE DriverName IS NOT NULL AND RouteDate BETWEEN ? AND ?
         GROUP BY DriverName
         ORDER BY total_routes DESC
    ", [$start_dt, $end_dt]);
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $data[] = $r;
        sqlsrv_free_stmt($stmt);
    }
    return $data;
}

// ---------- Load Data ----------
$stats             = getRouteStats($conn, $start_dt, $end_dt);
$recent_routes     = getRecentRoutes($conn, $start_dt, $end_dt);
$recent_activities = getRecentActivities($conn, $start_dt, $end_dt);
$top_vehicles      = getTopVehicles($conn, $start_dt, $end_dt);
$top_drivers       = getTopDrivers($conn, $start_dt, $end_dt);

// ---------- Nilai unik untuk Select2 ----------
$uniqueRoutesDrivers = []; // label => type ('Nama Rute' / 'Driver')
$uniqueVehicles = [];
$uniqueStatuses = [];

foreach ($recent_routes as $r) {
    if (!empty($r['Name']))       $uniqueRoutesDrivers[$r['Name']] = 'Nama Rute';
    if (!empty($r['DriverName'])) $uniqueRoutesDrivers[$r['DriverName']] = 'Driver';
    if (!empty($r['VehiclePlate'])) $uniqueVehicles[$r['VehiclePlate']] = true;
    if (!empty($r['Status']))     $uniqueStatuses[strtolower($r['Status'])] = true;
}

// ---------- Layout ----------
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Rute Angkutan</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
    <style>
        .summary-card{border-left:4px solid;transition:.3s}
        .summary-card:hover{transform:translateY(-5px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
        .summary-card.total{border-left-color:#007bff}
        .summary-card.active{border-left-color:#ffc107}
        .summary-card.completed{border-left-color:#28a745}
        .summary-card.vehicle{border-left-color:#17a2b8}
        .summary-card.vehicle-active{border-left-color:#dc3545}
        .summary-card.driver{border-left-color:#6c757d}
        .summary-card .icon{font-size:2rem;opacity:.8}

        /* Rapikan filter Rute Terbaru */
        .filter-row .form-group{margin-bottom:0}
        .filter-row .select2-container{width:100% !important}
        .filter-row .select2-selection--single{
            height:38px !important;border:1px solid #ced4da;border-radius:.25rem;
        }
        .filter-row .select2-selection__rendered{line-height:38px !important}
        .filter-row .select2-selection__arrow{height:36px !important}
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <h1>Dashboard Rute Angkutan</h1>
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/gg_app/index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li class="breadcrumb-item active">Dashboard Rute</li>
                </ol>
            </div>

            <!-- Filter Tanggal -->
            <div class="container-fluid mt-3">
                <form id="dateFilterForm" class="row g-2 align-items-end" method="get">
                    <div class="col-sm-3">
                        <label for="start" class="form-label mb-1">Start Date</label>
                        <input type="date" id="start" name="start" class="form-control"
                               value="<?= htmlspecialchars($start_dt->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-sm-3">
                        <label for="end" class="form-label mb-1">End Date</label>
                        <input type="date" id="end" name="end" class="form-control"
                               value="<?= htmlspecialchars($end_dt->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                        <button type="button" id="btnToday" class="btn btn-outline-secondary mr-2">Hari ini</button>
                        <a href="?start=<?= $today ?>&end=<?= $today ?>" class="btn btn-outline-info">Reset</a>
                        <div class="ml-auto text-muted small align-self-center">
                            Rentang aktif: <?= htmlspecialchars($start_dt->format('d M Y')) ?> – <?= htmlspecialchars($end_dt->format('d M Y')) ?>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php else: ?>

                <!-- Ringkasan -->
                <div class="row mb-4">
                    <?php
                    $descVehicleActive = $stats['active_vehicles'] . " dari " . $stats['total_vehicles'] . " total";
                    $descDriverTotal   = $stats['active_drivers'] . " aktif dari " . $stats['total_drivers'] . " driver";

                    $cards = [
                        ['Total Rute', $stats['total_routes'], 'fa-route', 'total', '#007bff', 'Semua rute terdaftar'],
                        ['Rute Aktif', $stats['active_routes'], 'fa-truck-loading', 'active', '#ffc107', 'Sedang berjalan'],
                        ['Rute Selesai', $stats['completed_routes'], 'fa-check-circle', 'completed', '#28a745', 'Telah selesai'],
                        ['Total Kendaraan', $stats['total_vehicles'], 'fa-truck', 'vehicle', '#17a2b8', 'Semua kendaraan'],
                        // GLOBAL
                        ['Kendaraan Aktif', $stats['active_vehicles'], 'fa-car-side', 'vehicle-active', '#dc3545', $descVehicleActive],
                        ['Total Driver', $stats['total_drivers'], 'fa-users', 'driver', '#6c757d', $descDriverTotal],
                    ];
                    foreach ($cards as [$label, $value, $icon, $class, $color, $desc]): ?>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card summary-card <?= $class ?> h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title"><?= htmlspecialchars($label) ?></h5>
                                        <h2 class="mb-0"><?= number_format($value) ?></h2>
                                    </div>
                                    <div class="icon" style="color: <?= $color ?>;">
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars($desc) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <!-- Rute Terbaru -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <!-- Header + Filter rapi -->
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <h3 class="card-title mb-0">
                                        <i class="fas fa-route mr-2"></i>Rute Terbaru
                                    </h3>
                                    <div class="w-100 mt-3">
                                        <div class="row filter-row no-gutters">
                                            <div class="col-12 col-md-6 pr-md-2 mb-2 mb-md-0">
                                                <div class="form-group">
                                                    <label class="sr-only" for="filterRouteDriver">Nama Rute / Driver</label>
                                                    <select id="filterRouteDriver" class="form-control select2" data-placeholder="Nama Rute / Driver">
                                                        <option value=""></option>
                                                        <optgroup label="Nama Rute">
                                                            <?php foreach ($uniqueRoutesDrivers as $label => $type): ?>
                                                                <?php if ($type === 'Nama Rute'): ?>
                                                                    <option value="<?= htmlspecialchars($label) ?>" data-type="route"><?= htmlspecialchars($label) ?></option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </optgroup>
                                                        <optgroup label="Driver">
                                                            <?php foreach ($uniqueRoutesDrivers as $label => $type): ?>
                                                                <?php if ($type === 'Driver'): ?>
                                                                    <option value="<?= htmlspecialchars($label) ?>" data-type="driver"><?= htmlspecialchars($label) ?></option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </optgroup>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3 pr-md-2 mb-2 mb-md-0">
                                                <div class="form-group">
                                                    <label class="sr-only" for="filterVehicle">Kendaraan (Plat)</label>
                                                    <select id="filterVehicle" class="form-control select2" data-placeholder="Kendaraan (Plat)">
                                                        <option value=""></option>
                                                        <?php foreach (array_keys($uniqueVehicles) as $v): ?>
                                                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="form-group">
                                                    <label class="sr-only" for="filterStatus">Status</label>
                                                    <select id="filterStatus" class="form-control select2" data-placeholder="Status">
                                                        <option value=""></option>
                                                        <?php foreach (array_keys($uniqueStatuses) as $v): ?>
                                                            <option value="<?= htmlspecialchars($v) ?>"><?= ucfirst(htmlspecialchars($v)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-0" style="max-height:400px;overflow:auto;">
                                <table id="tblRoutes" class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nama Rute</th>
                                            <th>Kendaraan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_routes as $r):
                                            $status = strtolower($r['Status']);
                                            $statusClass = match($status) {
                                                'inprog' => 'badge bg-warning',
                                                'done'   => 'badge bg-success',
                                                default  => 'badge bg-primary'
                                            };
                                        ?>
                                        <tr
                                            data-route="<?= htmlspecialchars($r['Name']) ?>"
                                            data-driver="<?= htmlspecialchars($r['DriverName']) ?>"
                                            data-vehicle="<?= htmlspecialchars($r['VehiclePlate']) ?>"
                                            data-status="<?= htmlspecialchars($status) ?>"
                                        >
                                            <td>
                                                <strong><?= htmlspecialchars($r['Name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($r['DriverName']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($r['VehiclePlate']) ?></td>
                                            <td><span class="<?= $statusClass ?>"><?= ucfirst($status) ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Aktivitas Terbaru -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-clock mr-2"></i>Aktivitas Terbaru</h3></div>
                            <div class="card-body" style="max-height:400px;overflow:auto;">
                                <?php foreach ($recent_activities as $a): ?>
                                <div class="activity-item border-start border-3 border-primary ps-3 mb-3">
                                    <strong><?= htmlspecialchars($a['Comment']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($a['RouteName']) ?> • <?= htmlspecialchars($a['DriverName']) ?></small><br>
                                    <small><i class="far fa-clock mr-1"></i><?= $a['CreatedAt'] instanceof DateTime ? $a['CreatedAt']->format('d M H:i') : htmlspecialchars($a['CreatedAt']) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-user mr-2"></i>Driver Teratas</h3></div>
                            <div class="card-body text-center">
                                <div class="row">
                                    <?php foreach ($top_drivers as $d): ?>
                                    <div class="col-6 mb-3">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-2" style="width:50px;height:50px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <strong><?= htmlspecialchars($d['DriverName']) ?></strong><br>
                                        <small class="text-muted"><?= (int)$d['completed_routes'] ?>/<?= (int)$d['total_routes'] ?> selesai</small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-truck mr-2"></i>Kendaraan Teratas</h3></div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($top_vehicles as $v): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($v['VehiclePlate']) ?></strong><br>
                                        <small class="text-muted"><?= (int)$v['completed_routes'] ?>/<?= (int)$v['total_routes'] ?> selesai</small>
                                    </div>
                                    <span class="badge bg-primary"><?= (int)$v['total_routes'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
<script>
$(function () {
    // Inisialisasi Select2
    $('.select2').each(function(){
        $(this).select2({ width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || 'Semua' });
    });

    // Filter tabel Rute Terbaru
    function applyFilters() {
        var fRD  = ($('#filterRouteDriver').val() || '').toString();
        var fVeh = ($('#filterVehicle').val()     || '').toString();
        var fSts = ($('#filterStatus').val()      || '').toString().toLowerCase();

        $('#tblRoutes tbody tr').each(function(){
            var $tr = $(this), show = true;

            if (fRD) {
                var matchRD = ($tr.data('route') == fRD) || ($tr.data('driver') == fRD);
                if (!matchRD) show = false;
            }
            if (fVeh && $tr.data('vehicle') != fVeh) show = false;
            if (fSts && $tr.data('status')  != fSts) show = false;

            $tr.toggle(show);
        });
    }
    $('#filterRouteDriver,#filterVehicle,#filterStatus').on('change', applyFilters);
    applyFilters();

    // Tombol "Hari ini"
    $('#btnToday').on('click', function () {
        var t = '<?= $today ?>';
        $('#start').val(t);
        $('#end').val(t);
        $('#dateFilterForm').trigger('submit');
    });
});
</script>
</body>
</html>
