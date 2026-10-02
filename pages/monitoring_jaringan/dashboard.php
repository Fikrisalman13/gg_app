<?php
// ================================================
// dashboard.php – Monitoring Jaringan Dashboard (V8 FINAL)
// ================================================

session_start();
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

include '../../includes/header.php';
include '../../includes/sidebar.php';

date_default_timezone_set("Asia/Jakarta");

// Helpers
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function formatBytesPretty($bytes){
    if (!is_numeric($bytes)) return "0 B";
    if ($bytes >= 1099511627776) return round($bytes/1099511627776,2) . " TB";
    if ($bytes >= 1073741824) return round($bytes/1073741824,2) . " GB";
    if ($bytes >= 1048576) return round($bytes/1048576,2) . " MB";
    if ($bytes >= 1024) return round($bytes/1024,2) . " KB";
    return $bytes . " B";
}
function normalizeDevice($name){
    $n = strtolower($name ?? '');
    if (str_contains($n,'hp') || str_contains($n,'android') || str_contains($n,'iphone')) return "HP";
    if (str_contains($n,'laptop') || str_contains($n,'notebook')) return "Laptop";
    if (str_contains($n,'pc') || str_contains($n,'desktop')) return "PC";
    if (str_contains($n,'tablet') || str_contains($n,'ipad') || str_contains($n,'tab')) return "Tablet";
    if (str_contains($n,'server')) return "Server";
    if (str_contains($n,'printer') || str_contains($n,'print')) return "Printer";
    if (str_contains($n,'mesin') || str_contains($n,'scan') || str_contains($n,'absen')) return "Mesin";
    if (str_contains($n,'cctv') || str_contains($n,'nvr') || str_contains($n,'dvr')) return "CCTV";
    return "Unknown";
}

// ================================================
// 1) REALTIME MIKROTIK
// ================================================
$deviceTypeCount = [];
$onlineCount = 0;
$offlineCount = 0;
$totalUsersRealtime = 0;

try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        $rows = $API->comm('/queue/simple/print', ['stats'=>'']);
        $API->disconnect();

        foreach ($rows as $r) {
            $name = $r['name'] ?? '-';
            $devType = normalizeDevice($name);

            $rate = $r['rate'] ?? "0/0";
            if (!str_contains($rate,'/')) $rate = "0/0";
            [$rx,$tx] = explode("/", $rate);

            $rx = intval($rx)*8;
            $tx = intval($tx)*8;

            $status = ($rx>0 || $tx>0) ? 'Online':'Offline';

            $deviceTypeCount[$devType] = ($deviceTypeCount[$devType] ?? 0) + 1;

            if ($status=='Online') $onlineCount++; else $offlineCount++;

            $totalUsersRealtime++;
        }
    }
} catch(Exception $e){}

$allTypes = ['HP','Laptop','PC','Tablet','Server','Printer','Mesin','CCTV','Unknown'];
foreach ($allTypes as $t){ if (!isset($deviceTypeCount[$t])) $deviceTypeCount[$t]=0; }
ksort($deviceTypeCount);

// ================================================
// 2) DATABASE SUMMARY + PEMAKAIAN HARI INI
// ================================================
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$whereSQL = "";
$params = [];

if ($start && $end){
    $whereSQL = "WHERE CAST([date] AS DATE) BETWEEN ? AND ?";
    $params = [$start,$end];
} elseif ($start){
    $whereSQL = "WHERE CAST([date] AS DATE) >= ?";
    $params = [$start];
} elseif ($end){
    $whereSQL = "WHERE CAST([date] AS DATE) <= ?";
    $params = [$end];
}

$sqlSum = "
SELECT SUM(upload) total_upload, SUM(download) total_download, SUM(total) total_traffic 
FROM monitoring_jaringan $whereSQL
";
$stmt = sqlsrv_query($conn,$sqlSum,$params);
$sum = $stmt? sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):0;

$totalUpload   = $sum['total_upload']   ?? 0;
$totalDownload = $sum['total_download'] ?? 0;
$totalTraffic  = $sum['total_traffic']  ?? 0;

// ================================================
// 3) TOP USER PER TANGGAL (LIST UTAMA)
// ================================================
$sqlTop = "
SELECT TOP 10 
    name,
    SUM(upload+download) AS total_usage
FROM monitoring_jaringan
$whereSQL
GROUP BY name
ORDER BY total_usage DESC
";
$stmtTop = sqlsrv_query($conn,$sqlTop,$params);

$topNames = [];
$topTotals = [];

if ($stmtTop){
    while($r = sqlsrv_fetch_array($stmtTop,SQLSRV_FETCH_ASSOC)){
        $topNames[] = $r['name'];
        $topTotals[] = round(($r['total_usage'] ?? 0)/1024/1024,2);
    }
}

// ================================================
// 4) TOP USER HARI INI (MINI LIST CARD) - ambil dari Mikrotik
// ================================================
require_once '../../routeros_api.class.php';
$topUsersToday = [];
try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
        $rawQueues = $API->comm('/queue/simple/print');
        $API->disconnect();
        foreach ($rawQueues as $q) {
            if (isset($q['bytes']) && strpos($q['bytes'], '/') !== false) {
                [$uBytes, $dBytes] = explode('/', $q['bytes']);
            } else {
                $uBytes = 0;
                $dBytes = 0;
            }
            $total = (int)$uBytes + (int)$dBytes;
            $topUsersToday[] = [
                'name' => $q['name'] ?? '-',
                'total' => $total
            ];
        }
        usort($topUsersToday, function($a, $b) { return $b['total'] <=> $a['total']; });
        $topUsersToday = array_slice($topUsersToday, 0, 10);
    }
} catch (Exception $e) {
    $topUsersToday = [];
}

function formatBytes($bytes) {
    if (!is_numeric($bytes) || $bytes <= 0) return '0 B';
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

?>

<!-- ========================================= -->
<!-- UI START -->
<!-- ========================================= -->
<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h3 class="mb-0">Dashboard Monitoring Jaringan</h3>
        
    </div>
</section>

<section class="content">
<div class="container-fluid">




<!-- ========================================= -->
<!-- SUMMARY BOX -->
<!-- ========================================= -->
<div class="row">

    <div class="col-md-3 mb-3">
        <div class="small-box bg-info shadow-sm">
            <div class="inner"><h3><?= formatBytesPretty($totalUpload) ?></h3><p>Total Upload</p></div>
            <div class="icon"><i class="fas fa-upload"></i></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="small-box bg-success shadow-sm">
            <div class="inner"><h3><?= formatBytesPretty($totalDownload) ?></h3><p>Total Download</p></div>
            <div class="icon"><i class="fas fa-download"></i></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="small-box bg-warning shadow-sm">
            <div class="inner"><h3><?= formatBytesPretty($totalTraffic) ?></h3><p>Total Traffic</p></div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="small-box bg-primary shadow-sm">
            <div class="inner"><h3><?= number_format($totalUsersRealtime) ?></h3><p>User Realtime</p></div>
            <div class="icon"><i class="fas fa-user"></i></div>
        </div>
    </div>


</div>

<!-- ========================================= -->
<!-- FILTER TANGGAL UNTUK SUMMARY BOX (DIPINDAH KE BAWAH) -->
<!-- ========================================= -->
<form method="get" class="row mb-3">
    <div class="col-md-3">
        <label>Dari</label>
        <input type="date" name="start" class="form-control form-control-sm" value="<?= e($_GET['start'] ?? '') ?>">
    </div>
    <div class="col-md-3">
        <label>Sampai</label>
        <input type="date" name="end" class="form-control form-control-sm" value="<?= e($_GET['end'] ?? '') ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-<?= $themeColor ?> btn-sm w-100">Filter</button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
    </div>
</form>

<!-- ========================================= -->
<!-- NAVIGATION BUTTONS -->
<!-- ========================================= -->
<div class="mb-3 d-flex gap-2">
    <a href="connected_device.php" class="btn btn-outline-<?= $themeColor ?> btn-sm">
        <i class="fas fa-plug"></i> Connected Device
    </a>
    <a href="user_internet.php" class="btn btn-outline-<?= $themeColor ?> btn-sm">
        <i class="fas fa-wifi"></i> User Internet
    </a>
    <a href="average_usage.php" class="btn btn-outline-<?= $themeColor ?> btn-sm">
        <i class="fas fa-chart-line"></i> Average Usage
    </a>
</div>
<!---------------------------------------------------------->
<!-- DEVICE TYPE + STATUS DEVICE + TOP USER HARI INI -->
<!---------------------------------------------------------->
<div class="row">

    <!-- DEVICE TYPE -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
            <div class="card-header"><b>Device Type</b></div>
            <div class="card-body">
                <canvas id="chartDeviceType" height="200"></canvas>
            </div>
        </div>
    </div>

    <!---------------------------------------------------------->
    <!-- STATUS DEVICE -->
    <!---------------------------------------------------------->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
            <div class="card-header"><b>Status Device</b></div>
            <div class="card-body">
                <canvas id="chartStatusDevice" height="200"></canvas>
            </div>
        </div>
    </div>

    <!---------------------------------------------------------->
    <!-- TOP USER HARI INI MINI LIST -->
    <!---------------------------------------------------------->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm" style="min-height:330px;">
            <div class="card-header"><b>User Dengan Pemakaian Terbanyak Hari Ini</b></div>

            <div class="card-body p-2">

                <?php if (empty($topUsersToday)): ?>
                    <div class="text-center text-muted py-4 small">
                        Tidak ada data hari ini
                    </div>
                <?php else: ?>
                    <ul class="list-group small">
                        <?php foreach ($topUsersToday as $user): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($user['name']) ?></span>
                                <span class="badge badge-primary badge-pill">
                                    <?= formatBytes($user['total']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php endif; ?>

            </div>
        </div>
    </div>

</div> 

<!---------------------------------------------------------->
<!-- TOP USER (LIST UTAMA) -->
<!---------------------------------------------------------->
<div class="card shadow-sm">
    <div class="card-header">
        <b>Top 10 Pengguna Internet</b>
    </div>

    <div class="card-body">
        <!-- Filter tanggal dihapus, hanya pakai filter global di bawah summary box -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    <!-- ========================================= -->
    <!-- FILTER TANGGAL UNTUK SUMMARY BOX -->
    <!-- ========================================= -->
$(function(){
    function reloadTopUserList() {
    let start = $('input[name="start"]').val();
    let end = $('input[name="end"]').val();
    console.log('Filter:', {start, end});
    if (!start && !end) {
        $('#topUserList').html('<div class="text-center text-muted py-4"><i class="fas fa-inbox"></i><br>Silahkan pilih periode tanggal untuk menampilkan data.</div>');
        return;
    }
    $.get('ajax_dashboard.php', {start, end}, function(res){
        console.log('Response:', res);
        let html = '';
        if(res.data && res.data.length > 0){
            res.data.forEach(function(u){
                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <div><b>${u.no}.</b> ${u.name}</div>
                    <span class="badge bg-dark">${u.total}</span>
                </li>`;
            });
        }else{
            html = '<div class="text-center text-muted py-4"><i class="fas fa-inbox"></i><br>Tidak ada data</div>';
        }
        $('#topUserList').html(html);
    },'json').fail(function(xhr, status, err){
        console.error('AJAX error:', status, err);
        console.error('Response text:', xhr.responseText);
        alert('Gagal mengambil data. Lihat console untuk detail.');
    });
}
    $('#btnTopUserFilter').click(reloadTopUserList);
    $('input[name="start"], input[name="end"]').on('change', reloadTopUserList);
    // Initialize empty state
    reloadTopUserList();
});
</script>

        <div class="text-center text-muted py-4" id="topUserList">
            <i class="fas fa-inbox"></i><br>Silahkan pilih periode tanggal untuk menampilkan data.
        </div>

    </div>
</div>


</div>
</section>

</div> 

<script src="/gg_app/plugins/js/chart.js"></script>

<script>
//======================================
// DEVICE TYPE PIE
//======================================
new Chart(document.getElementById('chartDeviceType'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_keys($deviceTypeCount)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($deviceTypeCount)) ?>,
            backgroundColor: [
                '#4e73df','#1cc88a','#36b9cc','#f6c23e',
                '#e74a3b','#858796','#ff6384','#20c997','#6610f2'
            ]
        }]
    },
    options: { responsive:true, animation:false, plugins:{ legend:{ position:'bottom' } } }
});

//======================================
// STATUS DEVICE DOUGHNUT
//======================================
new Chart(document.getElementById('chartStatusDevice'), {
    type: 'doughnut',
    data: {
        labels: ["Online", "Offline"],
        datasets: [{
            data: [<?= $onlineCount ?>, <?= $offlineCount ?>],
            backgroundColor: ['#1cc88a','#e74a3b']
        }]
    },
    options: {
        responsive:true,
        animation:false,
        plugins:{ legend:{ position:'bottom' } },
        cutout:'55%'
    }
});
</script>

<!-- ========================================= -->
<!-- UI END -->
<!-- ========================================= -->
<?php include '../../includes/footer.php'; ?>
