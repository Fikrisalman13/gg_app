<?php
session_start();
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

date_default_timezone_set("Asia/Jakarta");

// Helpers (sama seperti di file utama)
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function cleanTarget($t){ return explode('/', $t)[0]; }
function formatSpeed($bps){
    if(!is_numeric($bps)||$bps<=0) return "0 Kbps";
    $kb = $bps/1024;
    if($kb>=1024){
        $mb = $kb/1024;
        return ($mb>=1024)?round($mb/1024,2).' Gbps':round($mb,2).' Mbps';
    }
    return round($kb,2).' Kbps';
}
function formatBytes($b){
    if($b<=0) return "0 KB";
    if($b>=1073741824) return round($b/1073741824,2)." GB";
    if($b>=1048576) return round($b/1048576,2)." MB";
    return round($b/1024,2)." KB";
}

// ==========================
// FETCH DATA FROM MIKROTIK
// ==========================
$API = new RouterosAPI();
$queues = [];

if($API->connect($mt_ip,$mt_user,$mt_pass)){
    $raw = $API->comm("/queue/simple/print");
    $API->disconnect();

    foreach($raw as $q){
        [$u,$d] = explode("/", $q["bytes"] ?? "0/0");
        $q["_upload"] = intval($u);
        $q["_download"] = intval($d);
        $q["_total"] = $q["_upload"] + $q["_download"];
        $queues[] = $q;
    }
    usort($queues, fn($a,$b)=>$b["_total"] <=> $a["_total"]);
}

// ==========================
// FILTER
// ==========================
$search   = strtolower($_GET["search"] ?? "");
$perpage  = $_GET["perpage"] ?? 10;
$page     = intval($_GET["page"] ?? 1);

if($search !== ""){
    $queues = array_values(array_filter($queues, function($q) use ($search){
        return str_contains(strtolower($q["name"] ?? ""), $search)
            || str_contains(strtolower($q["target"] ?? ""), $search)
            || str_contains(strtolower($q["_upload"]), $search)
            || str_contains(strtolower($q["_download"]), $search);
    }));
}

$totalData = count($queues);

if($perpage === "all"){
    $perpage = $totalData > 0 ? $totalData : 1;
} else {
    $perpage = max(1, intval($perpage));
}

$totalPages = max(1, ceil($totalData / $perpage));
$page = max(1, min($totalPages, $page));

$start = ($page - 1) * $perpage;
$display = array_slice($queues, $start, $perpage);

// ==========================
// RENDER HTML (TABLE + PAGINATION)
// ==========================
?>

<div class="table-responsive">
<table class="table table-bordered table-hover table-sm-custom text-nowrap mb-0">
<thead>
<tr>
    <th>No</th>
    <th>Nama</th>
    <th>Target IP</th>
    <th>Upload Max</th>
    <th>Download Max</th>
    <th>Upload Avg</th>
    <th>Download Avg</th>
    <th>Upload</th>
    <th>Download</th>
</tr>
</thead>
<tbody>

<?php if(empty($display)): ?>
<tr><td colspan="9" class="text-center text-muted">Tidak ada data</td></tr>

<?php else: $no=$start+1; ?>
<?php foreach($display as $q):
    [$mu,$md] = explode("/", $q["max-limit"] ?? "0/0");
    [$au,$ad] = explode("/", $q["rate"] ?? "0/0");
?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= e($q["name"] ?? "-") ?></td>
    <td><?= e(cleanTarget($q["target"] ?? "-")) ?></td>
    <td><?= e(formatSpeed($mu)) ?></td>
    <td><?= e(formatSpeed($md)) ?></td>
    <td><?= e(formatSpeed($au)) ?></td>
    <td><?= e(formatSpeed($ad)) ?></td>
    <td><?= e(formatBytes($q["_upload"])) ?></td>
    <td><?= e(formatBytes($q["_download"])) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>
</div>

<!-- ==========================
     PAGINATION (AJAX) 
========================== -->
<div class="p-3 d-flex justify-content-between align-items-center">
    <div>
        Menampilkan <b><?= ($totalData>0?$start+1:0) ?></b> -
        <b><?= min($start+$perpage, $totalData) ?></b>
        dari <b><?= $totalData ?></b> data
    </div>

    <nav>
        <ul class="pagination mb-0">

            <!-- Prev -->
            <li class="page-item <?= ($page<=1?'disabled':'') ?>">
                <a class="page-link page-link-ajax" href="#" data-page="<?= $page-1 ?>">Sebelumnya</a>
            </li>

            <?php
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);

            if($startPage > 1){
                echo '<li class="page-item"><a class="page-link page-link-ajax" data-page="1">1</a></li>';
                if($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            for($i=$startPage;$i<=$endPage;$i++):
                $active = ($i==$page?'active':'');
                echo '<li class="page-item '.$active.'"><a class="page-link page-link-ajax" data-page="'.$i.'">'.$i.'</a></li>';
            endfor;

            if($endPage < $totalPages){
                if($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo '<li class="page-item"><a class="page-link page-link-ajax" data-page="'.$totalPages.'">'.$totalPages.'</a></li>';
            }
            ?>

            <!-- Next -->
            <li class="page-item <?= ($page>=$totalPages?'disabled':'') ?>">
                <a class="page-link page-link-ajax" href="#" data-page="<?= $page+1 ?>">Selanjutnya</a>
            </li>

        </ul>
    </nav>
</div>
