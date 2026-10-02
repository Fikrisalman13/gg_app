<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$editPack = null;
if (isset($_GET['edit_pack'])) {
    $stmt = sqlsrv_query($conn, 'SELECT id, packaging FROM packaged_gistex WHERE id = ?', [(int)$_GET['edit_pack']]);
    $editPack = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
}

$packList = [];
$q = sqlsrv_query($conn, 'SELECT id, packaging, create_date, created_by, updatedate, updateby FROM packaged_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $packList[] = $row;
?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Packaged Gistex</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" action="<?= $editPack ? 'update_packaged.php' : 'save_packaged.php' ?>" class="row">
<input type="hidden" name="id" value="<?= htmlspecialchars((string)($editPack['id'] ?? '')) ?>">
<div class="col-md-6 mb-3"><label>Packaging</label><input type="text" name="packaging" class="form-control" value="<?= htmlspecialchars($editPack['packaging'] ?? '') ?>" required></div>
<div class="col-md-6 mb-3"><button class="btn btn-primary mt-4"><?= $editPack ? 'Update Packaging' : 'Save Packaging' ?></button></div>
</form>
<table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Packaging</th><th>Create</th><th>Created By</th><th>Update</th><th>Update By</th><th>Aksi</th></tr></thead><tbody><?php foreach ($packList as $row): ?><tr><td><?= htmlspecialchars((string)$row['id']) ?></td><td><?= htmlspecialchars($row['packaging'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['create_date'] ?? '')) ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['updatedate'] ?? '')) ?></td><td><?= htmlspecialchars($row['updateby'] ?? '') ?></td><td><a class="btn btn-warning btn-sm" href="packaged_gistex.php?edit_pack=<?= urlencode((string)$row['id']) ?>">Edit</a> <a class="btn btn-danger btn-sm" href="delete_packaged.php?id=<?= urlencode((string)$row['id']) ?>" onclick="return confirm('Hapus data?')">Delete</a></td></tr><?php endforeach; ?></tbody></table>
</div></div>
</div></section>
</div>
<?php include '../../includes/footer.php'; ?>
