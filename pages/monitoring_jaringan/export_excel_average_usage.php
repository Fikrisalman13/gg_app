<?php
require_once '../../koneksi.php';
date_default_timezone_set("Asia/Jakarta");

// ==========================
// FUNCTION FORMAT BYTES
// ==========================
function formatBytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ==========================
// GET PARAMETER
// ==========================
$search = trim($_GET['search'] ?? '');
$start  = $_GET['start'] ?? '';
$end    = $_GET['end'] ?? '';

$params = [];
$whereParts = [];

// ==========================
// SEARCH
// ==========================
if ($search !== "") {
    $whereParts[] = "(LOWER(name) LIKE ? OR LOWER(target) LIKE ?)";
    $params[] = "%" . strtolower($search) . "%";
    $params[] = "%" . strtolower($search) . "%";
}

// ==========================
// DATE RANGE 
// ==========================
$dateFilter = false;
if (!empty($start) && !empty($end)) {
    $whereParts[] = "(CAST(date AS DATE) BETWEEN ? AND ?)";
    $params[] = $start;
    $params[] = $end;
    $dateFilter = true;
}

$whereSQL = "";
if (!empty($whereParts)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereParts);
}

// ==========================
// TOTAL HARI
// ==========================
if ($dateFilter) {
    $totalHari = (strtotime($end) - strtotime($start)) / 86400 + 1;
    if ($totalHari < 1) $totalHari = 0;
} else {
    $totalHari = 0;
}

// ==========================
// QUERY EXPORT
// ==========================
$sql = "
SELECT 
    name,
    target,
    AVG(upload) AS avg_upload,
    AVG(download) AS avg_download,
    AVG(total) AS avg_total,
    MAX(created_at) AS last_date
FROM monitoring_jaringan
$whereSQL
GROUP BY name, target
ORDER BY avg_total DESC
";

$stmt = sqlsrv_query($conn, $sql, $params);

// ==========================
// FILE NAME
// ==========================
$filename = "Pemakaian_Internet" . date("Ymd_His") . ".csv";

// ==========================
// HEADERS
// ==========================
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

// ==========================
// UTF-8 BOM
// ==========================
echo "\xEF\xBB\xBF";

$output = fopen("php://output", "w");

// ===========================================================
// WRITE HEADER / KETERANGAN LAPORAN
// ===========================================================

fputcsv($output, ["Laporan Pemakaian Internet"]);
fputcsv($output, ["Periode:", ($start ?: "-") . " s/d " . ($end ?: "-")]);
fputcsv($output, ["Total Hari:", $totalHari]);
fputcsv($output, []); // baris kosong

// ==========================
// CSV HEADER TABEL
// ==========================
fputcsv($output, [
    "No", "Nama", "Target",
    "Avg Upload", "Avg Download", "Avg Total",
    "Total Hari", "Last Update"
]);

$no = 1;

// ==========================
// DATA
// ==========================
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $lastDate = $r['last_date'] ? $r['last_date']->format("d/m/Y H:i") : "";

    fputcsv($output, [
        $no++,
        $r['name'],
        $r['target'],
        formatBytes($r['avg_upload']),
        formatBytes($r['avg_download']),
        formatBytes($r['avg_total']),
        $totalHari,
        $lastDate
    ]);
}

fclose($output);
exit;
?>
