<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

date_default_timezone_set('Asia/Jakarta');

if (!$conn || !$conn3) {
    die("Koneksi ke database gagal");
}

// Helper format datetime (handle string atau DateTime object)
function fmtDT($val) {
    if (empty($val)) return '';
    if ($val instanceof DateTime) {
        return $val->format('d/m/Y H:i');
    }
    $ts = strtotime($val);
    if ($ts === false) return '';
    return date('d/m/Y H:i', $ts);
}

// Ambil filter dari SQL Server
$savedFilters = [];
$sf = "SELECT id, rtgmsid, is_active, created_by, created_time, reactivate_by, reactivate_time, inactive_by, inactive_time FROM monitoringstartstopkurang_filter ORDER BY id";
$ss = sqlsrv_query($conn, $sf);
if ($ss) {
    while ($r = sqlsrv_fetch_array($ss, SQLSRV_FETCH_ASSOC)) {
        $savedFilters[] = $r;
    }
    sqlsrv_free_stmt($ss);
}

// Ambil nama rtgms dari PostgreSQL
$savedRtgmsIds = array_map(function($f) { return (int)$f['rtgmsid']; }, $savedFilters);
$detailLookup = [];
if (!empty($savedRtgmsIds)) {
    $placeholders = implode(',', array_fill(0, count($savedRtgmsIds), '?'));
    $detailQuery = "SELECT rtgmsid, rtgcode, rtgname FROM pdrtgms WHERE rtgmsid IN ($placeholders) ORDER BY rtgcode";
    $detailStmt = $conn3->prepare($detailQuery);
    $detailStmt->execute($savedRtgmsIds);
    while ($d = $detailStmt->fetch(PDO::FETCH_ASSOC)) {
        $detailLookup[(int)$d['rtgmsid']] = $d;
    }
}

$filename = 'Pengaturan_Filter_Routing_' . date('Ymd_His') . '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");
?>
<table border='1'>
<tr><td colspan='11' style='font-weight:bold; text-align:center; background-color:#e0e0e0;'>PENGATURAN FILTER ROUTING</td></tr>
<tr><td colspan='11' style='font-weight:bold;'>Export Date: <?= date("d/m/Y H:i") ?></td></tr>
<tr><td colspan='11'></td></tr>
<thead>
<tr style='background-color:#f0f0f0; font-weight:bold;'>
    <th>No</th>
    <th>RTGMS ID</th>
    <th>Kode</th>
    <th>Nama Routing</th>
    <th>Status</th>
    <th>Created By</th>
    <th>Created Time</th>
    <th>Reactivate By</th>
    <th>Reactivate Time</th>
    <th>Inactive By</th>
    <th>Inactive Time</th>
</tr>
</thead>
<tbody>
<?php $no = 1; foreach ($savedFilters as $f):
    $rtgmsid = (int)$f['rtgmsid'];
?>
<tr>
    <td style='text-align:center;'><?= $no++ ?></td>
    <td style='text-align:center;'><?= htmlspecialchars($rtgmsid) ?></td>
    <td><?= htmlspecialchars($detailLookup[$rtgmsid]['rtgcode'] ?? '') ?></td>
    <td><?= htmlspecialchars($detailLookup[$rtgmsid]['rtgname'] ?? '') ?></td>
    <td style='text-align:center;'><?= (int)$f['is_active'] ? 'Aktif' : 'Nonaktif' ?></td>
    <td><?= htmlspecialchars($f['created_by'] ?? '') ?></td>
    <td><?= fmtDT($f['created_time'] ?? '') ?></td>
    <td><?= htmlspecialchars($f['reactivate_by'] ?? '') ?></td>
    <td><?= fmtDT($f['reactivate_time'] ?? '') ?></td>
    <td><?= htmlspecialchars($f['inactive_by'] ?? '') ?></td>
    <td><?= fmtDT($f['inactive_time'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
<tr><td colspan='11' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: <?= count($savedFilters) ?></td></tr>
</tbody>
</table>
