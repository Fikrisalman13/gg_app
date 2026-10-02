<?php
// ======================================================
// average_usage_ajax.php — AJAX Table Renderer
// ======================================================

require_once '../../koneksi.php';
date_default_timezone_set("Asia/Jakarta");

// Safety function
function e($s){
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function formatBytes($bytes){
    if (!is_numeric($bytes)) return "0 B";
    if ($bytes >= 1073741824) return round($bytes/1073741824,2)." GB";
    if ($bytes >= 1048576) return round($bytes/1048576,2)." MB";
    if ($bytes >= 1024) return round($bytes/1024,2)." KB";
    return $bytes . " B";
}

// GET parameters
$search      = trim($_GET['search'] ?? '');
$start_date  = $_GET['start'] ?? '';
$end_date    = $_GET['end'] ?? '';
$perpage_raw = $_GET['perpage'] ?? 10;
$page_raw    = $_GET['page'] ?? 1;

$params = [];
$whereParts = [];

// SEARCH
if ($search !== "") {
    $whereParts[] = "(LOWER(name) LIKE ? OR LOWER(target) LIKE ?)";
    $params[] = "%".strtolower($search)."%";
    $params[] = "%".strtolower($search)."%";
}

// DATE RANGE
if (!empty($start_date) && !empty($end_date)) {
    $whereParts[] = "(CAST([date] AS DATE) BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
}

$whereSQL = "";
if (!empty($whereParts)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereParts);
}

// COUNT
$count_sql = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT name, target
        FROM monitoring_jaringan
        $whereSQL
        GROUP BY name, target
    ) AS x
";

$count_stmt = sqlsrv_query($conn, $count_sql, $params);
$row = $count_stmt ? sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC) : null;
$totalData = $row['total'] ?? 0;

// Pagination
$perpage = ($perpage_raw === "all") ? $totalData : max(1, intval($perpage_raw));
$totalPages = max(1, (int)ceil($totalData / max(1,$perpage)));
$page = max(1, min($totalPages, intval($page_raw)));
$offset = ($page - 1) * $perpage;

// DATA QUERY
$sql = "
SELECT 
    name,
    target,
    AVG(upload) AS avg_upload,
    AVG(download) AS avg_download,
    AVG(total) AS avg_total,
    MAX([created_at]) AS last_date
FROM monitoring_jaringan
$whereSQL
GROUP BY name, target
ORDER BY avg_total DESC
OFFSET $offset ROWS FETCH NEXT $perpage ROWS ONLY
";

$stmt = sqlsrv_query($conn, $sql, $params);
$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
}

// Hitung total hari
if (!empty($start_date) && !empty($end_date)) {
    $totalHari = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
    if ($totalHari < 1) $totalHari = 0;
} else {
    $totalHari = 0;
}

// =====================================
// OUTPUT HTML (TABLE + PAGINATION ONLY)
// =====================================
?>
<!------------------>
<!-- TABLE ONLY -->
<!------------------>
<div class="table-responsive mt-3">
    <table class="table table-bordered table-hover table-sm mb-0">
        <thead class="thead-light">
            <tr class="text-center">
                <th style="width:60px;">No</th>
                <th>Nama</th>
                <th>Target</th>
                <th>Avg Upload</th>
                <th>Avg Download</th>
                <th>Avg Total</th>
                <th>Total Hari</th>
                <th>Last Update</th>
            </tr>
        </thead>
        <tbody>

<?php if (empty($rows)): ?>
    <tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>

<?php else:
    $no = $offset + 1;
    foreach ($rows as $r): ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['target']) ?></td>
            <td><?= formatBytes($r['avg_upload']) ?></td>
            <td><?= formatBytes($r['avg_download']) ?></td>
            <td><?= formatBytes($r['avg_total']) ?></td>
            <td><?= $totalHari ?></td>
            <td>
                <?php
                    if ($r['last_date'] instanceof DateTime) {
                        echo $r['last_date']->format("Y-m-d H:i");
                    } elseif (is_string($r['last_date'])) {
                        echo $r['last_date'];
                    } else {
                        echo "-";
                    }
                ?>
            </td>
        </tr>
<?php endforeach; endif; ?>

        </tbody>
    </table>
</div>


<!-- PAGINATION ONLY -->
<div class="p-3 d-flex justify-content-between">
    <div>
        Menampilkan  
        <strong><?= ($totalData>0? $offset+1:0) ?></strong> - 
        <strong><?= ($totalData>0? min($offset+$perpage,$totalData):0) ?></strong> 
        dari <strong><?= $totalData ?></strong>
    </div>

    <ul class="pagination mb-0">

        <!-- Prev -->
        <li class="page-item <?= ($page <= 1?'disabled':'') ?>">
            <a class="page-link page-link-ajax" data-page="<?= max(1,$page-1) ?>">Sebelumnya</a>
        </li>

        <?php
        $pagesToShow = [];
        $pagesToShow[] = 1;

        for ($i=$page-2; $i<=$page+2; $i++) {
            if ($i > 1 && $i < $totalPages) $pagesToShow[] = $i;
        }

        if ($totalPages > 1) $pagesToShow[] = $totalPages;

        $pagesToShow = array_unique($pagesToShow);
        sort($pagesToShow);
        $last = 0;

        foreach ($pagesToShow as $p):
            if ($p > $last + 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            $active = ($p == $page) ? 'active' : '';
            echo '
            <li class="page-item '.$active.'">
                <a class="page-link page-link-ajax" data-page="'.$p.'">'.$p.'</a>
            </li>';

            $last = $p;
        endforeach;
        ?>

        <!-- Next -->
        <li class="page-item <?= ($page >= $totalPages?'disabled':'') ?>">
            <a class="page-link page-link-ajax" data-page="<?= min($totalPages,$page+1) ?>">Selanjutnya</a>
        </li>

    </ul>
</div>

