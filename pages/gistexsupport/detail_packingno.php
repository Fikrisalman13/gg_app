<?php
require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$uniqueid = $_GET['uniqueid'] ?? '';

// Get parent info
$qParent = sqlsrv_query($conn, "SELECT no_po, item, description FROM orderitem_gistex WHERE uniqueid = ?", [$uniqueid]);
$parent = $qParent ? sqlsrv_fetch_array($qParent, SQLSRV_FETCH_ASSOC) : null;

// Get detail rows
$rows = [];
$qDt = sqlsrv_query($conn, "SELECT * FROM orderitem_gistex_dt WHERE uniqueid_parent = ? ORDER BY id ASC", [$uniqueid]);
if ($qDt) {
    while ($row = sqlsrv_fetch_array($qDt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
}
?>
<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Packingno Detail</h1>
        <?php if ($parent): ?>
        <p class="mb-0">PO: <?= htmlspecialchars($parent['no_po']) ?> | Item: <?= htmlspecialchars($parent['item'] ?? '') ?> | <?= htmlspecialchars($parent['description'] ?? '') ?></p>
        <?php endif; ?>
    </div>
</section>
<section class="content">
<div class="container-fluid">
<div class="card">
<div class="card-body">
<a href="detail_po.php?no_po=<?= urlencode($parent['no_po'] ?? '') ?>" class="btn btn-secondary mb-3">Back to Detail PO</a>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>Balenmbr</th>
    <th>Prodname</th>
    <th>Prodcode</th>
    <th>Batchno</th>
    <th>Qtym</th>
    <th>Qtyyard</th>
    <th>Stdqty</th>
    <th>Lot</th>
    <th>Create Date</th>
    <th>Created By</th>
</tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="11" class="text-muted">Tidak ada data packingno</td></tr>
<?php else: ?>
<?php $no = 1; ?>
<?php foreach ($rows as $row): ?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= htmlspecialchars($row['balenmbr'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['prodname'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['prodcode'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['batchno'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['qtym'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['qtyyard'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['stdqty'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['lot'] ?? '') ?></td>
    <td><?= htmlspecialchars(gistexDate($row['create_date'] ?? '')) ?></td>
    <td><?= htmlspecialchars($row['created_by'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>
