<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$editUom = null;
if (isset($_GET['edit_uom'])) {
    $stmt = sqlsrv_query($conn, 'SELECT id, uomid, uomname FROM uom_gistex WHERE id = ?', [(int)$_GET['edit_uom']]);
    $editUom = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
}

$uomList = [];
$q = sqlsrv_query($conn, 'SELECT id, uomid, uomname, create_date, created_by, updatedate, updateby FROM uom_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $uomList[] = $row;
?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>UOM Gistex</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" action="<?= $editUom ? 'update_uom.php' : 'save_uom.php' ?>" class="row">
<input type="hidden" name="id" value="<?= htmlspecialchars((string)($editUom['id'] ?? '')) ?>">
<div class="col-md-4 mb-3"><label>UOM ID</label><input type="text" name="uomid" class="form-control" value="<?= htmlspecialchars($editUom['uomid'] ?? '') ?>" required></div>
<div class="col-md-4 mb-3"><label>UOM Name</label><input type="text" name="uomname" class="form-control" value="<?= htmlspecialchars($editUom['uomname'] ?? '') ?>" required></div>
<div class="col-md-4 mb-3"><button class="btn btn-primary mt-4"><?= $editUom ? 'Update UOM' : 'Save UOM' ?></button></div>
</form>
<table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>UOM ID</th><th>UOM Name</th><th>Create</th><th>Created By</th><th>Update</th><th>Update By</th><th>Aksi</th></tr></thead><tbody><?php foreach ($uomList as $row): ?><tr><td><?= htmlspecialchars((string)$row['id']) ?></td><td><?= htmlspecialchars($row['uomid'] ?? '') ?></td><td><?= htmlspecialchars($row['uomname'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['create_date'] ?? '')) ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['updatedate'] ?? '')) ?></td><td><?= htmlspecialchars($row['updateby'] ?? '') ?></td><td><a class="btn btn-warning btn-sm" href="uom_gistex.php?edit_uom=<?= urlencode((string)$row['id']) ?>">Edit</a> <a class="btn btn-danger btn-sm" href="delete_uom.php?id=<?= urlencode((string)$row['id']) ?>" onclick="return confirm('Hapus data?')">Delete</a></td></tr><?php endforeach; ?></tbody></table>
</div></div>
</div></section>
</div>
<?php include '../../includes/footer.php'; ?>
