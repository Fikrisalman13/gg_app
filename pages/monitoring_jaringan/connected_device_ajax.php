<?php
session_start();

require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ==========================
// HELPERS (harus sama dengan file utama)
// ==========================
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function cleanName($name){
    return trim(preg_replace('/port[_ ]?\d+/i', '', $name));
}

function normalizeDevice($name){
    $n = strtolower($name ?? '');

    if (str_contains($n, 'hp') || str_contains($n, 'android') || str_contains($n, 'iphone'))
        return "HP";

    if (str_contains($n, 'laptop') || str_contains($n, 'notebook'))
        return "Laptop";

    if (str_contains($n, 'pc') || str_contains($n, 'desktop'))
        return "PC";

    if (str_contains($n, 'tablet') || str_contains($n, 'ipad') || str_contains($n, 'tab'))
        return "Tablet";

    if (str_contains($n, 'server'))
        return "Server";

    if (str_contains($n, 'printer') || str_contains($n, 'print'))
        return "Printer";

    if (str_contains($n, 'mesin') || str_contains($n, 'scan') || str_contains($n, 'absen'))
        return "Mesin";

    if (str_contains($n, 'cctv') || str_contains($n, 'nvr') || str_contains($n, 'dvr'))
        return "CCTV";

    return "Unknown";
}

// ==========================
// GET DATA FROM MIKROTIK
// ==========================
$API = new RouterosAPI();
$rows = [];
$leases = [];

if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

    $rows = $API->comm("/queue/simple/print", ["stats" => ""]);
    $API->disconnect();

    foreach ($rows as $r) {

        $raw = $r['name'] ?? '';
        $name = cleanName($raw);
        $device = normalizeDevice($raw);
        $ip = explode("/", $r['target'] ?? '-')[0];

        list($down, $up) = explode("/", $r['rate'] ?? "0/0");
        $up = intval($up) * 8;
        $down = intval($down) * 8;

        $leases[] = [
            'name' => $name,
            'device' => $device,
            'ip' => $ip,
            'upload' => $up,
            'download' => $down,
            'status' => ($up > 0 || $down > 0) ? "Online" : "Offline"
        ];
    }
}

// ==========================
// FILTERING
// ==========================
$filter   = $_GET['filter'] ?? '';
$search   = strtolower($_GET['search'] ?? '');
$perPage  = $_GET['perpage'] ?? 10;

$data = $leases;

if ($filter !== '') {
    $data = array_values(array_filter($data, fn($x) => $x['device'] === $filter));
}

if ($search !== '') {
    $data = array_values(array_filter($data, function($x) use ($search){
        return str_contains(strtolower($x['name']), $search)
            || str_contains(strtolower($x['ip']), $search)
            || str_contains(strtolower($x['device']), $search)
            || str_contains(strtolower($x['status']), $search);
    }));
}

// ==========================
// PAGINATION
// ==========================
$totalData = count($data);

if ($perPage === "all") {
    $perPage = $totalData;
} else {
    $perPage = max(1, intval($perPage));
}

$page = 1; // selalu page 1 saat AJAX
$start = ($page - 1) * $perPage;
$display = array_slice($data, $start, $perPage);

// ==========================
// OUTPUT HTML (TABEL SAJA)
// ==========================
?>
<div class="table-responsive">
<table class="table table-hover table-bordered table-sm-custom mb-0">
<thead>
<tr>
    <th>No</th>
    <th>Nama</th>
    <th>Device</th>
    <th>IP</th>
    <th>Upload Avg</th>
    <th>Download Avg</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php if (empty($display)): ?>
<tr><td colspan="7" class="text-center text-muted">Tidak ada data</td></tr>
<?php else: $no=1; ?>
<?php foreach ($display as $row): ?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= e($row['name']) ?></td>
    <td><?= e($row['device']) ?></td>
    <td><?= e($row['ip']) ?></td>
    <td><?= number_format($row['upload']) ?> bps</td>
    <td><?= number_format($row['download']) ?> bps</td>
    <td>
        <?php if ($row['status'] === 'Online'): ?>
            <span class="status-online">Online</span>
        <?php else: ?>
            <span class="status-offline">Offline</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>
</div>

<div class="p-3">
    Total data: <strong><?= $totalData ?></strong>
</div>
