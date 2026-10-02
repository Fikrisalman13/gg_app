<?php
// ambil master kode cacat
$kodeCacat = [];
$q = sqlsrv_query($conn, "
    SELECT kode_defect, nama_defect, status_defect
    FROM cl_kode_cacat
    ORDER BY kode_defect
");
while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    $kodeCacat[] = $r;
}
?>

<div class="modal fade"
     id="modalPiece"
     data-backdrop="static"
     data-keyboard="false">

<div class="modal-dialog modal-xl">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title">Tambah Piece</h5>
<button class="close" data-dismiss="modal">&times;</button>
</div>

<div class="modal-body">

<div class="row">
<div class="col-md-1">
<label>No</label>
<input id="pieceNo" class="form-control" readonly>
</div>

<div class="col-md-2">
<label>Piece *</label>
<input id="pieceValue" class="form-control">
</div>

<div class="col-md-2">
<label>Panjang Awal *</label>
<input type="number" id="panjangAwal" class="form-control">
</div>

<div class="col-md-2">
<label>Panjang Akhir *</label>
<input type="number" id="panjangAkhir" class="form-control">
</div>

<div class="col-md-1">
<label>Lebar *</label>
<input type="number" id="lebarKain" class="form-control">
</div>

<div class="col-md-1">
<label>Std *</label>
<input type="number" id="stdPotong" class="form-control">
</div>

<div class="col-md-1">
<label>Max *</label>
<input type="number" id="maxPotong" class="form-control">
</div>

<div class="col-md-1">
<label>Min *</label>
<input type="number" id="minPotong" class="form-control">
</div>

<div class="col-md-1">
<label>Toleransi</label>
<input type="number" step="0.01" id="toleransiPotong" class="form-control" placeholder="0.00">
</div>

<div class="col-md-1">
<label>UOM</label>
<input id="uomPiece" class="form-control" readonly>
</div>
</div>

<hr>

<button class="btn btn-success btn-sm mb-2" id="btnAddCacat">+ Tambah Cacat</button>

<table class="table table-bordered table-sm" id="tableCacatInput">
<thead>
<tr>
<th>No</th>
<th>Kode</th>
<th>Nama</th>
<th>Status</th>
<th>Dari</th>
<th>Sampai</th>
<th>Panjang</th>
<th>Hapus</th>
</tr>
</thead>
<tbody>
<tr><td colspan="8" class="text-center">No data</td></tr>
</tbody>
</table>

</div>

<div class="modal-footer">
<button class="btn btn-primary" id="btnApplyPiece">Apply</button>
<button class="btn btn-danger" data-dismiss="modal">Close</button>
</div>

</div>
</div>
</div>

<script>
const MASTER_KODE_CACAT = <?= json_encode($kodeCacat) ?>;
</script>
