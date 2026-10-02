<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$menuId = 175;
requireView($conn, $menuId);

// master mesin
$mesinList = [];
$q = sqlsrv_query($conn,
    "SELECT id_mesin, nama_mesin FROM cl_m_mesin_inspect WHERE is_active = 1 ORDER BY id_mesin"
);
while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    $mesinList[] = $r;
}
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<h1>Cutting List Entry</h1>
</div>
</section>

<section class="content">
<div class="container-fluid">
<div class="card">
<div class="card-body">

<div class="row mb-3">
    <div class="col-md-3">
        <label>CP No *</label>
        <input type="text" id="cpNo" class="form-control">
    </div>

    <div class="col-md-2">
        <label>UOM CP *</label>
        <select id="uomCp" class="form-control">
            <option value="">-- pilih --</option>
            <option value="M">Meter</option>
            <option value="Y">Yard</option>
        </select>
    </div>

    <div class="col-md-3">
        <label>No Mesin Inspect *</label>
        <select id="mesinInspect" class="form-control">
            <option value="">-- pilih mesin --</option>
            <?php foreach ($mesinList as $m): ?>
                <option value="<?= $m['id_mesin'] ?>">
                    <?= htmlspecialchars($m['nama_mesin']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label>Type Counter *</label>
        <div class="d-flex">
            <select id="typeCounter" class="form-control mr-2">
                <option value="M">Meter</option>
                <option value="Y">Yard</option>
            </select>
            <button class="btn btn-success mr-1" id="btnSaveAll">Save</button>
            <button class="btn btn-secondary" id="btnCancelAll">Cancel</button>
        </div>
    </div>
</div>

<hr>

<button class="btn btn-primary mb-2" id="btnAddPiece">
    <i class="fas fa-plus"></i> Tambah Piece
</button>

<ul class="nav nav-tabs">
<li class="nav-item">
<a class="nav-link active" data-toggle="tab" href="#tabPiece">Piece</a>
</li>
<li class="nav-item">
<a class="nav-link" data-toggle="tab" href="#tabCacat">Cacat</a>
</li>
</ul>

<div class="tab-content">

<div class="tab-pane fade show active" id="tabPiece">
<table class="table table-bordered table-sm mt-2" id="tablePiece">
<thead>
<tr>
<th>No</th><th>Piece</th><th>Panjang Awal</th><th>Panjang Akhir</th>
<th>Lebar</th><th>Std</th><th>Max</th><th>Min</th><th>Tol</th><th>UOM</th><th style="width: 80px;">Action</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>

<div class="tab-pane fade" id="tabCacat">
<table class="table table-bordered table-sm mt-2" id="tableCacatAll">
<thead>
<tr>
<th>Piece</th><th>No</th><th>Kode</th><th>Nama</th>
<th>Status</th><th>Dari</th><th>Sampai</th><th>Panjang</th><th style="width: 80px;">Action</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>

</div>
</div>
</div>
</div>
</section>
</div>

<?php include 'modal_piece.php'; ?>

<!-- ======= JQUERY (REQUIRED FIRST) ======= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- ======= SELECT2 CSS & JS ======= -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<!-- ======= SWEETALERT2 ======= -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ======= CUSTOM JS (AFTER JQUERY & SELECT2) ======= -->
<script src="/gg_app/pages/cuttinglist/js/cuttinglist.js" defer></script>

<?php include '../../includes/footer.php'; ?>
