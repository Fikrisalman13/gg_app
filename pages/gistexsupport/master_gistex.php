<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';

$editMaster = null;
if (isset($_GET['edit_master'])) {
    $stmt = sqlsrv_query($conn, 'SELECT id, item, kode_produk FROM master_gistex WHERE id = ?', [(int)$_GET['edit_master']]);
    $editMaster = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
}

$masterList = [];
$q = sqlsrv_query($conn, 'SELECT id, item, kode_produk, create_date, created_by, updatedate, updateby FROM master_gistex ORDER BY id DESC');
if ($q) while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $masterList[] = $row;
?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Master Gistex</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" action="<?= $editMaster ? 'update_master.php' : 'save_master.php' ?>" class="row">
<input type="hidden" name="old_id" value="<?= htmlspecialchars((string)($editMaster['id'] ?? '')) ?>">
<div class="col-md-4 mb-3"><label>ID</label><input type="number" name="id" class="form-control" value="<?= htmlspecialchars((string)($editMaster['id'] ?? '')) ?>" required></div>
<div class="col-md-4 mb-3"><label>Item</label><input type="text" name="item" class="form-control" value="<?= htmlspecialchars($editMaster['item'] ?? '') ?>" required></div>
<div class="col-md-4 mb-3"><label>Kode Produk</label><input type="text" name="kode_produk" class="form-control" value="<?= htmlspecialchars($editMaster['kode_produk'] ?? '') ?>" required></div>
<div class="col-12 mb-3"><button class="btn btn-primary"><?= $editMaster ? 'Update Master' : 'Save Master' ?></button></div>
</form>
<table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>Item</th><th>Kode Produk</th><th>Create</th><th>Created By</th><th>Update</th><th>Update By</th><th>Aksi</th></tr></thead><tbody><?php foreach ($masterList as $row): ?><tr><td><?= htmlspecialchars((string)$row['id']) ?></td><td><?= htmlspecialchars($row['item'] ?? '') ?></td><td><?= htmlspecialchars($row['kode_produk'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['create_date'] ?? '')) ?></td><td><?= htmlspecialchars($row['created_by'] ?? '') ?></td><td><?= htmlspecialchars(gistexDate($row['updatedate'] ?? '')) ?></td><td><?= htmlspecialchars($row['updateby'] ?? '') ?></td><td><a class="btn btn-warning btn-sm" href="master_gistex.php?edit_master=<?= urlencode((string)$row['id']) ?>">Edit</a> <a class="btn btn-danger btn-sm" href="delete_master.php?id=<?= urlencode((string)$row['id']) ?>" onclick="return confirm('Hapus data?')">Delete</a></td></tr><?php endforeach; ?></tbody></table>
</div></div>
</div></section>
</div>
<?php include '../../includes/footer.php'; ?>
