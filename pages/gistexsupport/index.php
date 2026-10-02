<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'helpers.php';
?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid"><h1>Gistex Support</h1></div></section>
<section class="content"><div class="container-fluid">
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div class="row">
<div class="col-lg-3 col-6">
<div class="small-box bg-info"><div class="inner"><h3>Master</h3><p>master_gistex</p></div><div class="icon"><i class="fas fa-database"></i></div><a href="master_gistex.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a></div>
</div>
<div class="col-lg-3 col-6">
<div class="small-box bg-success"><div class="inner"><h3>UOM</h3><p>uom_gistex</p></div><div class="icon"><i class="fas fa-ruler"></i></div><a href="uom_gistex.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a></div>
</div>
<div class="col-lg-3 col-6">
<div class="small-box bg-warning"><div class="inner"><h3>Packaged</h3><p>packaged_gistex</p></div><div class="icon"><i class="fas fa-box"></i></div><a href="packaged_gistex.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a></div>
</div>
<div class="col-lg-3 col-6">
<div class="small-box bg-danger"><div class="inner"><h3>Order Item</h3><p>orderitem_gistex</p></div><div class="icon"><i class="fas fa-shopping-cart"></i></div><a href="orderitem_gistex.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a></div>
</div>
</div>
</div></section>
</div>
<?php include '../../includes/footer.php'; ?>
