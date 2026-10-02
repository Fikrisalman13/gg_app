<?php
require_once '../../koneksi.php';

$ids = $_POST['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    exit('<div class="text-danger">Tidak ada data dipilih</div>');
}

$idList = implode(',', array_map('intval', $ids));

/* ================= HEADER ================= */
$qHeader = sqlsrv_query($conn, "
    SELECT id_header, cp_no, processed
    FROM cl_cutting_header
    WHERE id_header IN ($idList)
");
?>

<?php while ($h = sqlsrv_fetch_array($qHeader, SQLSRV_FETCH_ASSOC)): ?>
<div class="card mb-3">
<div class="card-header bg-light">
<strong>CP No :</strong> <?= htmlspecialchars($h['cp_no']) ?>
</div>

<div class="card-body">

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab"
           href="#piece<?= $h['id_header'] ?>">Piece</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab"
           href="#cacat<?= $h['id_header'] ?>">Cacat</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab"
           href="#hasil<?= $h['id_header'] ?>">Hasil Proses Cutting</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab"
           href="#summary<?= $h['id_header'] ?>">Summary</a>
    </li>
</ul>

<div class="tab-content">

<!-- ================= PIECE ================= -->
<div class="tab-pane fade show active" id="piece<?= $h['id_header'] ?>">
<div class="table-responsive">
<table class="table table-sm table-bordered">
<thead>
<tr>
<th>No</th><th>Piece</th><th>Panjang Awal</th><th>Panjang Akhir</th>
<th>Susut</th><th>Std</th><th>Min</th><th>Max</th><th>Tol</th><th>UOM CP</th>
<th>Std Conv</th><th>Min Conv</th><th>Max Conv</th><th>Tol Conv</th><th>UOM Counter</th>
<?php if (!$h['processed']): ?><th style="width: 100px;">Action</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php
$qPiece = sqlsrv_query($conn, "
    SELECT * FROM cl_cutting_piece WHERE id_header = ?
", [$h['id_header']]);

while ($p = sqlsrv_fetch_array($qPiece, SQLSRV_FETCH_ASSOC)):
?>
<tr>
<td><?= $p['piece_no'] ?></td>
<td><?= htmlspecialchars($p['piece_code']) ?></td>
<td><?= $p['panjang_awal'] ?></td>
<td><?= $p['panjang_akhir'] ?></td>
<td><?= $p['susut'] ?></td>
<td><?= $p['standart_potong'] ?></td>
<td><?= $p['min_potong'] ?></td>
<td><?= $p['max_potong'] ?></td>
<td><?= $p['toleransi'] ?? '0.00' ?></td>
<td><?= $p['uom_cp'] ?? $p['satuan'] ?? 'M' ?></td>
<td><?= $p['standart_potong_conv'] ?? '0.000' ?></td>
<td><?= $p['min_potong_conv'] ?? '0.000' ?></td>
<td><?= $p['max_potong_conv'] ?? '0.000' ?></td>
<td><?= $p['toleransi_conv'] ?? '0.000' ?></td>
<td><?= $p['uom_counter'] ?? 'M' ?></td>
<?php if (!$h['processed']): ?>
<td class="text-center">
    <button type="button" class="btn btn-sm btn-danger btn-hapus-piece-detail" data-piece-id="<?= $p['id_piece'] ?>" title="Hapus">
        <i class="fas fa-trash"></i>
    </button>
</td>
<?php endif; ?>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- ================= CACAT ================= -->
<div class="tab-pane fade" id="cacat<?= $h['id_header'] ?>">
<table class="table table-sm table-bordered">
<thead>
<tr>
<th>Piece</th><th>No</th><th>Kode</th><th>Nama</th>
<th>Status</th><th>Dari</th><th>Sampai</th><th>Panjang</th><?php if (!$h['processed']): ?><th style="width: 100px;">Action</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php
$qCacat = sqlsrv_query($conn, "
SELECT c.*, p.piece_code
FROM cl_cutting_cacat c
JOIN cl_cutting_piece p ON c.id_piece = p.id_piece
WHERE p.id_header = ?
", [$h['id_header']]);

while ($c = sqlsrv_fetch_array($qCacat, SQLSRV_FETCH_ASSOC)):
?>
<tr>
<td><?= $c['piece_code'] ?></td>
<td><?= $c['cacat_no'] ?></td>
<td><?= $c['kode_cacat'] ?></td>
<td><?= $c['nama_cacat'] ?></td>
<td><?= $c['status_cacat'] ?></td>
<td><?= $c['dari'] ?></td>
<td><?= $c['sampai'] ?></td>
<td><?= $c['panjang_cacat'] ?></td>
<?php if (!$h['processed']): ?>
<td class="text-center">
    <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat-detail" data-cacat-id="<?= $c['id_cacat'] ?>" title="Hapus">
        <i class="fas fa-trash"></i>
    </button>
</td>
<?php endif; ?>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<!-- ================= HASIL ================= -->
<div class="tab-pane fade" id="hasil<?= $h['id_header'] ?>">

<?php
$qPieceForResult = sqlsrv_query($conn, "
    SELECT id_piece, piece_code, piece_no
    FROM cl_cutting_piece
    WHERE id_header = ?
    ORDER BY piece_no
", [$h['id_header']]);

while ($piece_res = sqlsrv_fetch_array($qPieceForResult, SQLSRV_FETCH_ASSOC)):
?>
<div class="card mb-3">
<div class="card-header bg-secondary text-white">
    <strong>Piece: <?= htmlspecialchars($piece_res['piece_code']) ?></strong>
</div>
<div class="card-body">

<!-- Data Cacat -->
<h6 class="mt-3 mb-2">Data Cacat</h6>
<div class="table-responsive">
<table class="table table-sm table-bordered">
<thead>
<tr>
<th>No</th><th>Kode</th><th>Nama</th><th>Status</th><th>Dari</th><th>Sampai</th><th>Panjang</th>
</tr>
</thead>
<tbody>
<?php
$qCacatDetail = sqlsrv_query($conn, "
SELECT c.*
FROM cl_cutting_cacat c
WHERE c.id_piece = ?
ORDER BY c.cacat_no
", [$piece_res['id_piece']]);

$has_cacat_detail = false;
while ($c = sqlsrv_fetch_array($qCacatDetail, SQLSRV_FETCH_ASSOC)):
    $has_cacat_detail = true;
?>
<tr>
<td><?= $c['cacat_no'] ?></td>
<td><?= $c['kode_cacat'] ?></td>
<td><?= $c['nama_cacat'] ?></td>
<td><?= $c['status_cacat'] ?></td>
<td><?= number_format($c['dari'], 2, ',', '.') ?></td>
<td><?= number_format($c['sampai'], 2, ',', '.') ?></td>
<td><?= number_format($c['panjang_cacat'], 2, ',', '.') ?></td>
</tr>
<?php endwhile; ?>
<?php if (!$has_cacat_detail): ?>
<tr><td colspan="7" class="text-center text-muted">-</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Proses Cutting -->
<h6 class="mt-3 mb-2">Proses Cutting</h6>
<div class="table-responsive">
<table class="table table-sm table-bordered">
<thead>
<tr>
<th>No</th><th>Start</th><th>End</th><th>Hasil</th><th>Kategori</th>
</tr>
</thead>
<tbody>
<?php
$qProc = sqlsrv_query($conn, "
SELECT pr.*
FROM cl_cutting_process pr
WHERE pr.id_piece = ?
ORDER BY pr.process_no
", [$piece_res['id_piece']]);

$has_process = false;
while ($pr = sqlsrv_fetch_array($qProc, SQLSRV_FETCH_ASSOC)):
    $has_process = true;
    $start_formatted = $pr['start_pos'] == 0 ? '0' : number_format($pr['start_pos'], 2, ',', '.');
    $end_formatted = number_format($pr['end_pos'], 2, ',', '.');
    
    // Gunakan hasil_cutting_conv jika ada, jika tidak ada gunakan hasil_cutting
    $hasil_display = isset($pr['hasil_cutting_conv']) && $pr['hasil_cutting_conv'] > 0 
        ? $pr['hasil_cutting_conv'] 
        : $pr['hasil_cutting'];
    $hasil_formatted = number_format($hasil_display, 2, ',', '.');
?>
<tr>
<td class="text-center"><?= $pr['process_no'] ?></td>
<td class="text-right"><?= $start_formatted ?></td>
<td class="text-right"><?= $end_formatted ?></td>
<td class="text-right"><?= $hasil_formatted ?> <?= isset($pr['uom_conversi']) ? htmlspecialchars($pr['uom_conversi']) : '' ?></td>
<td class="text-center"><?= htmlspecialchars($pr['kategori']) ?></td>
</tr>
<?php endwhile; ?>
<?php if (!$has_process): ?>
<tr><td colspan="5" class="text-center text-muted">-</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</div>
</div>
<?php endwhile; ?>

</div>

<!-- ================= SUMMARY ================= -->
<div class="tab-pane fade" id="summary<?= $h['id_header'] ?>">

<?php
$qPieceForSummary = sqlsrv_query($conn, "
    SELECT id_piece, piece_code, piece_no
    FROM cl_cutting_piece
    WHERE id_header = ?
    ORDER BY piece_no
", [$h['id_header']]);

while ($piece_sum = sqlsrv_fetch_array($qPieceForSummary, SQLSRV_FETCH_ASSOC)):
?>
<div class="card mb-3">
<div class="card-header bg-secondary text-white">
    <strong>Piece: <?= htmlspecialchars($piece_sum['piece_code']) ?></strong>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table table-sm table-bordered">
<thead>
<tr><th>Kategori</th><th>Total PCS</th><th>Total Panjang</th></tr>
</thead>
<tbody>
<?php
$qSum = sqlsrv_query($conn, "
SELECT s.*
FROM cl_cutting_summary s
WHERE s.id_piece = ?
", [$piece_sum['id_piece']]);

$has_summary = false;
while ($s = sqlsrv_fetch_array($qSum, SQLSRV_FETCH_ASSOC)):
    $has_summary = true;
    $panjang_formatted = number_format($s['total_panjang'], 2, ',', '.');
?>
<tr>
<td><?= htmlspecialchars($s['kategori']) ?></td>
<td class="text-center"><?= $s['total_pcs'] ?></td>
<td class="text-right"><?= $panjang_formatted ?></td>
</tr>
<?php endwhile; ?>
<?php if (!$has_summary): ?>
<tr><td colspan="3" class="text-center text-muted">-</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endwhile; ?>

<script>
/* ================= DELETE PIECE ================= */
$(document).on('click', '.btn-hapus-piece-detail', function() {
    if (!confirm('Hapus piece ini dan semua cacat terkait?')) {
        return;
    }
    
    const pieceId = $(this).data('piece-id');
    
    $.ajax({
        url: 'delete_piece.php',
        type: 'POST',
        data: { id_piece: pieceId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'ok') {
                alert('Piece berhasil dihapus');
                location.reload();
            } else {
                alert('Gagal hapus: ' + (response.error || 'Unknown error'));
            }
        },
        error: function(xhr) {
            alert('Error: ' + xhr.statusText);
            console.error('Delete error:', xhr);
        }
    });
});

/* ================= DELETE CACAT ================= */
$(document).on('click', '.btn-hapus-cacat-detail', function() {
    if (!confirm('Hapus cacat ini?')) {
        return;
    }
    
    const cacatId = $(this).data('cacat-id');
    
    $.ajax({
        url: 'delete_cacat.php',
        type: 'POST',
        data: { id_cacat: cacatId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'ok') {
                alert('Cacat berhasil dihapus');
                location.reload();
            } else {
                alert('Gagal hapus: ' + (response.error || 'Unknown error'));
            }
        },
        error: function(xhr) {
            alert('Error: ' + xhr.statusText);
            console.error('Delete error:', xhr);
        }
    });
});
</script>

</div>

</div>
</div>
</div>
<?php endwhile; ?>
