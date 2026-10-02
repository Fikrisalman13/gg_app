<?php
require_once '../../koneksi.php';
session_start();

$ids = $_POST['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    exit('<div class="text-danger">Tidak ada data dipilih</div>');
}

$currentUser = $_SESSION['fullname'] ?? $_SESSION['user'] ?? 'Unknown';
$printDate   = date('d-m-Y H:i');

$idList = implode(',', array_map('intval', $ids));

$qHeader = sqlsrv_query($conn, "
    SELECT h.id_header, h.cp_no, h.created_date, h.type_counter, h.uom_cp
    FROM cl_cutting_header h
    WHERE h.id_header IN ($idList)
    ORDER BY h.created_date DESC, h.cp_no ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cutting List Report</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
body{font-size:16px;background:#f5f5f5}
.container-print{background:#fff;margin:10px;box-shadow:0 0 6px #ccc}
.header-banner{
    background:#17a2b8;
    color:#fff;
    padding:12px;
}
.header-banner table td{
    padding:2px 4px;
    font-size:16px;
    color:#fff;
}
.info-table td{border:1px solid #ccc;padding:6px;font-size:16px}
.section-header{background:#17a2b8;color:#fff;padding:8px;margin-top:10px;font-weight:bold}
.data-table{width:100%;border-collapse:collapse;font-size:16px}
.data-table th,.data-table td{border:1px solid #ccc;padding:6px;text-align:center}
.two-column{display:flex;gap:12px}
.column.left{flex:1.3}
.column.right{flex:0.7}
@media print{.no-print{display:none}}
@page{size:A4;margin:8mm}
</style>
</head>

<body>

<div class="no-print position-fixed" style="top:10px;right:10px">
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fa fa-print"></i> Print
    </button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm">
        Close
    </button>
</div>

<?php while($h = sqlsrv_fetch_array($qHeader,SQLSRV_FETCH_ASSOC)): ?>

<?php
$qPiece = sqlsrv_query($conn,"
    SELECT * FROM cl_cutting_piece 
    WHERE id_header = ?
    ORDER BY piece_no
",[$h['id_header']]);

while($p = sqlsrv_fetch_array($qPiece,SQLSRV_FETCH_ASSOC)):
?>

<div class="container-print">

<!-- ================= HEADER CUTTING LIST CP ================= -->
<!-- ================= HEADER CUTTING LIST CP (SEJAJAR) ================= -->
<div class="header-banner">
    <div style="display:flex;align-items:center;flex-wrap:nowrap;font-size:12px;">
        <strong style="font-size:14px;margin-right:14px;">
            <i class="fa fa-list"></i> CUTTING LIST CP
        </strong>

        <span style="margin-right:18px;">
            <strong>No CP</strong>: <?= htmlspecialchars($h['cp_no']) ?>
        </span>

        <span style="margin-right:18px;">
            <strong>UOM CP</strong>: <?= htmlspecialchars($h['uom_cp']) ?>
        </span>

        <span style="margin-right:18px;">
            <strong>UOM Counter</strong>: <?= htmlspecialchars($h['type_counter']) ?>
        </span>

    

        <span>
            <strong>Date Print</strong>: <?= $printDate ?>
        </span>
    </div>
</div>

<!-- ================================================================ -->

<!-- ========================================================== -->

<div class="p-3">

<table class="data-table w-100 mb-2">
<thead>
<tr>
<th>No</th>
<th>Panjang Awal</th>
<th>Panjang Akhir</th>
<th>Susut</th>
<th>UOM</th>
<th>Standart Potong</th>
<th>Min</th>
<th>Max</th>
<th>Satuan</th>
</tr>
</thead>
<tbody>
<tr>
<td><?=$p['piece_code']?></td>
<td align="right"><?=number_format($p['panjang_awal'],2,',','.')?></td>
<td align="right"><?=number_format($p['panjang_akhir'],2,',','.')?></td>
<td align="right"><?=number_format($p['susut'],2,',','.')?></td>
<td>M</td>
<td align="right"><?=number_format($p['standart_potong'],2,',','.')?></td>
<td align="right"><?=number_format($p['min_potong'],2,',','.')?></td>
<td align="right"><?=number_format($p['max_potong'],2,',','.')?></td>
<td><?=htmlspecialchars($h['uom_cp'] ?? 'meter')?></td>
</tr>
<tr style="background:#f0f8ff;font-weight:bold;font-size:13px;">
<td>Conv</td>
<td align="right"><?=number_format($p['panjang_awal_conv'],2,',','.')?></td>
<td align="right"><?=number_format($p['panjang_akhir_conv'],2,',','.')?></td>
<td align="right"><?=number_format($p['susut_conv'],2,',','.')?></td>
<td><?=htmlspecialchars($h['uom_conv'] ?? 'Y')?></td>
<td align="right"><?=number_format($p['standart_potong_conv'],2,',','.')?></td>
<td align="right"><?=number_format($p['min_potong_conv'],2,',','.')?></td>
<td align="right"><?=number_format($p['max_potong_conv'],2,',','.')?></td>
<td><?=htmlspecialchars($h['type_counter'] ?? 'M')?></td>
</tr>
</tbody>
</table>

<div class="two-column">

<!-- ================= LEFT ================= -->
<div class="column left">
<div class="section-header">
    <i class="fa fa-scissors"></i>
    Proses Cutting | No Piece <?=htmlspecialchars($p['piece_code'])?>
</div>

<table class="data-table">
<thead>
<tr>
<th>No</th><th>Start</th><th>End</th><th>Hasil</th><th>UOM Hasil</th><th>Kategori</th><th>Start Conv</th><th>End Conv</th><th>Hasil Conv</th><th>UOM Conv</th>
</tr>
</thead>
<tbody>
<?php
$qProc = sqlsrv_query($conn,"
    SELECT * FROM cl_cutting_process
    WHERE id_piece = ?
    ORDER BY process_no
",[$p['id_piece']]);

$has=false;
while($pr=sqlsrv_fetch_array($qProc,SQLSRV_FETCH_ASSOC)):
$has=true;
?>
<tr>
<td><?=$pr['process_no']?></td>
<td align="right"><?=number_format($pr['start_pos'],2,',','.')?></td>
<td align="right"><?=number_format($pr['end_pos'],2,',','.')?></td>
<td align="right"><?=number_format($pr['hasil_cutting'],2,',','.')?></td>
<td><?=htmlspecialchars($pr['uom_hasil_cutting'])?></td>
<td><?=$pr['kategori']?></td>
<td align="right"><?=number_format($pr['start_pos_conv'],2,',','.')?></td>
<td align="right"><?=number_format($pr['end_pos_conv'],2,',','.')?></td>
<td align="right"><?=number_format($pr['hasil_cutting_conv'],2,',','.')?></td>
<td><?=htmlspecialchars($pr['uom_conv'])?></td>
</tr>
<?php endwhile; ?>
<?php if(!$has): ?><tr><td colspan="10">-</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- ================= RIGHT ================= -->
<div class="column right">

<div class="section-header">
    <i class="fa fa-chart-bar"></i>
    Summary Kategori
</div>

<table class="data-table">
<thead>
<tr><th>Kategori</th><th>PCS</th><th>Panjang</th></tr>
</thead>
<tbody>
<?php
$qSum = sqlsrv_query($conn,"
    SELECT * FROM cl_cutting_summary WHERE id_piece = ?
",[$p['id_piece']]);

$tp=0;$tj=0;$hs=false;
while($s=sqlsrv_fetch_array($qSum,SQLSRV_FETCH_ASSOC)):
$hs=true;$tp+=$s['total_pcs'];$tj+=$s['total_panjang'];
?>
<tr>
<td><?=$s['kategori']?></td>
<td><?=$s['total_pcs']?></td>
<td align="right"><?=number_format($s['total_panjang'],2,',','.')?></td>
</tr>
<?php endwhile; ?>
<?php if($hs): ?>
<tr style="font-weight:bold;background:#f8f9fa">
<td>Total</td><td><?=$tp?></td><td align="right"><?=number_format($tj,2,',','.')?></td>
</tr>
<?php else: ?><tr><td colspan="3">-</td></tr><?php endif; ?>
</tbody>
</table>

<div class="section-header">
    <i class="fa fa-exclamation-triangle"></i>
    Data Cacat
</div>

<table class="data-table">
<thead>
<tr><th>No</th><th>Kode</th><th>Status</th><th>Dari</th><th>Sampai</th><th>Panjang</th></tr>
</thead>
<tbody>
<?php
$qC = sqlsrv_query($conn,"
    SELECT * FROM cl_cutting_cacat
    WHERE id_piece = ?
    ORDER BY cacat_no
",[$p['id_piece']]);

$hc=false;
while($c=sqlsrv_fetch_array($qC,SQLSRV_FETCH_ASSOC)):
$hc=true;
?>
<tr>
<td><?=$c['cacat_no']?></td>
<td><?=$c['kode_cacat']?></td>
<td><?=$c['status_cacat']?></td>
<td align="right"><?=number_format($c['dari'],2,',','.')?></td>
<td align="right"><?=number_format($c['sampai'],2,',','.')?></td>
<td align="right"><?=number_format($c['panjang_cacat'],2,',','.')?></td>
</tr>
<?php endwhile; ?>
<?php if(!$hc): ?><tr><td colspan="6">-</td></tr><?php endif; ?>
</tbody>
</table>

</div>
</div>
</div>
</div>

<?php endwhile; ?>
<?php endwhile; ?>

</body>
</html>
