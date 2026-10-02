<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$menuId = 175;
requireView($conn, $menuId);

$id_header = (int)($_GET['id'] ?? 0);

if (!$id_header) {
    echo '<div class="alert alert-danger">ID tidak valid</div>';
    exit;
}

// Get header info dengan lengkap
$qHeader = sqlsrv_query($conn, "
    SELECT id_header, cp_no, processed, type_counter, uom_cp, id_mesin
    FROM cl_cutting_header
    WHERE id_header = ?
", [$id_header]);

$header = sqlsrv_fetch_array($qHeader, SQLSRV_FETCH_ASSOC);

if (!$header) {
    echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    exit;
}

// Get mesin list
$mesinList = [];
$qMesin = sqlsrv_query($conn,
    "SELECT id_mesin, nama_mesin FROM cl_m_mesin_inspect WHERE is_active = 1 ORDER BY id_mesin"
);
while ($r = sqlsrv_fetch_array($qMesin, SQLSRV_FETCH_ASSOC)) {
    $mesinList[] = $r;
}

// Get all pieces dengan toleransi dan kolom conv
$qPieces = sqlsrv_query($conn, "
    SELECT id_piece, piece_no, piece_code, panjang_awal, panjang_akhir,
           lebar_kain, standart_potong, min_potong, max_potong, uom_cp, toleransi,
           ISNULL(standart_potong_conv, 0) as standart_potong_conv,
           ISNULL(min_potong_conv, 0) as min_potong_conv,
           ISNULL(max_potong_conv, 0) as max_potong_conv,
           ISNULL(uom_counter, 'M') as uom_counter,
           ISNULL(toleransi_conv, 0) as toleransi_conv
    FROM cl_cutting_piece
    WHERE id_header = ?
    ORDER BY piece_no
", [$id_header]);

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

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<h1>Edit Cutting Detail - <?= htmlspecialchars($header['cp_no']) ?></h1>
</div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card">
<div class="card-body">

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#tabHeader">Header</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tabPiece">Piece</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tabCacat">Cacat</a>
    </li>
</ul>

<div class="tab-content">

<!-- ================= HEADER ================= -->
<div class="tab-pane fade show active" id="tabHeader">
<form id="formEditHeader" class="row mt-3">
<div class="col-md-3">
<label>CP No</label>
<input type="text" id="headerCpNo" class="form-control" value="<?= htmlspecialchars($header['cp_no']) ?>">
</div>

<div class="col-md-3">
<label>UOM CP</label>
<select id="headerUomCp" class="form-control">
<option value="M" <?= $header['uom_cp'] === 'M' ? 'selected' : '' ?>>Meter</option>
<option value="Y" <?= $header['uom_cp'] === 'Y' ? 'selected' : '' ?>>Yard</option>
</select>
</div>

<div class="col-md-3">
<label>No Mesin Inspect</label>
<select id="headerMesinInspect" class="form-control">
<option value="">-- pilih mesin --</option>
<?php foreach ($mesinList as $m): ?>
<option value="<?= $m['id_mesin'] ?>" <?= $header['id_mesin'] == $m['id_mesin'] ? 'selected' : '' ?>>
<?= htmlspecialchars($m['nama_mesin']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label>Type Counter</label>
<select id="headerTypeCounter" class="form-control">
<option value="M" <?= $header['type_counter'] === 'M' ? 'selected' : '' ?>>Meter</option>
<option value="Y" <?= $header['type_counter'] === 'Y' ? 'selected' : '' ?>>Yard</option>
</select>
</div>

<div class="col-12 mt-3">
<button type="button" class="btn btn-primary" id="btnSaveHeader" <?php if ($header['processed']) echo 'disabled'; ?>>
<i class="fas fa-save"></i> Simpan Header
</button>
<?php if ($header['processed']): ?>
<span class="badge badge-success ml-2">Sudah di-Process (Read-Only)</span>
<?php endif; ?>
</div>
</form>
</div>

<!-- ================= PIECE ================= -->
<div class="tab-pane fade" id="tabPiece">
<div class="table-responsive">
<table class="table table-bordered table-sm" id="tablePieceEdit">
<thead>
<tr>
<th>No</th><th>Piece</th><th>Panjang Awal</th><th>Panjang Akhir</th>
<th>Susut</th><th>Std</th><th>Min</th><th>Max</th><th>Tol</th><th>UOM CP</th>
<th>Std Conv</th><th>Min Conv</th><th>Max Conv</th><th>Tol Conv</th><th>UOM Counter</th>
<th style="width: 100px;">Action</th>
</tr>
</thead>
<tbody>
<?php while ($p = sqlsrv_fetch_array($qPieces, SQLSRV_FETCH_ASSOC)): ?>
<tr class="piece-row" data-piece-id="<?= $p['id_piece'] ?>">
<td><?= $p['piece_no'] ?></td>
<td><?= htmlspecialchars($p['piece_code']) ?></td>
<td class="panjang_awal"><?= $p['panjang_awal'] ?></td>
<td class="panjang_akhir"><?= $p['panjang_akhir'] ?></td>
<td class="susut"><?= $p['panjang_awal'] - $p['panjang_akhir'] ?></td>
<td class="standart_potong"><?= $p['standart_potong'] ?></td>
<td class="min_potong"><?= $p['min_potong'] ?></td>
<td class="max_potong"><?= $p['max_potong'] ?></td>
<td class="toleransi"><?= $p['toleransi'] ?? '0.00' ?></td>
<td class="uom_cp"><?= $p['uom_cp'] ?? 'M' ?></td>
<td class="standart_potong_conv"><?= $p['standart_potong_conv'] ?? '0.000' ?></td>
<td class="min_potong_conv"><?= $p['min_potong_conv'] ?? '0.000' ?></td>
<td class="max_potong_conv"><?= $p['max_potong_conv'] ?? '0.000' ?></td>
<td class="toleransi_conv"><?= $p['toleransi_conv'] ?? '0.000' ?></td>
<td class="uom_counter"><?= $p['uom_counter'] ?? 'M' ?></td>
<td class="text-center">
    <button type="button" class="btn btn-sm btn-info btn-edit-piece-detail" title="Edit" <?php if ($header['processed']) echo 'disabled'; ?>>
        <i class="fas fa-edit"></i>
    </button>
    <?php if (!$header['processed']): ?>
    <button type="button" class="btn btn-sm btn-danger btn-hapus-piece-detail-edit" data-piece-id="<?= $p['id_piece'] ?>" title="Hapus">
        <i class="fas fa-trash"></i>
    </button>
    <?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- ================= CACAT ================= -->
<div class="tab-pane fade" id="tabCacat">
<table class="table table-bordered table-sm" id="tableCacatEdit">
<thead>
<tr>
<th>Piece</th><th>No</th><th>Kode</th><th>Nama</th>
<th>Status</th><th>Dari</th><th>Sampai</th><th>Panjang</th><th style="width: 100px;">Action</th>
</tr>
</thead>
<tbody>
<?php
$qCacats = sqlsrv_query($conn, "
SELECT c.*, p.piece_code
FROM cl_cutting_cacat c
JOIN cl_cutting_piece p ON c.id_piece = p.id_piece
WHERE p.id_header = ?
ORDER BY c.id_piece, c.cacat_no
", [$id_header]);

while ($c = sqlsrv_fetch_array($qCacats, SQLSRV_FETCH_ASSOC)): ?>
<tr class="cacat-row" data-cacat-id="<?= $c['id_cacat'] ?>">
<td><?= htmlspecialchars($c['piece_code']) ?></td>
<td><?= $c['cacat_no'] ?></td>
<td class="kode_cacat"><?= htmlspecialchars($c['kode_cacat']) ?></td>
<td class="nama_cacat"><?= htmlspecialchars($c['nama_cacat']) ?></td>
<td class="status_cacat"><?= htmlspecialchars($c['status_cacat']) ?></td>
<td class="dari"><?= $c['dari'] ?></td>
<td class="sampai"><?= $c['sampai'] ?></td>
<td class="panjang_cacat"><?= $c['panjang_cacat'] ?></td>
<td class="text-center">
    <button type="button" class="btn btn-sm btn-info btn-edit-cacat-detail" title="Edit" <?php if ($header['processed']) echo 'disabled'; ?>>
        <i class="fas fa-edit"></i>
    </button>
    <?php if (!$header['processed']): ?>
    <button type="button" class="btn btn-sm btn-danger btn-hapus-cacat-detail-edit" data-cacat-id="<?= $c['id_cacat'] ?>" title="Hapus">
        <i class="fas fa-trash"></i>
    </button>
    <?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>

<div class="mt-3">
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

</div>
</div>

</div>
</section>
</div>

<!-- Modal Edit Piece -->
<div class="modal fade" id="modalEditPiece" data-backdrop="static" data-keyboard="false">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-info">
<h5 class="modal-title">Edit Piece</h5>
<button class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<div class="form-group">
<label>Panjang Awal</label>
<input type="number" step="0.001" id="editPanjangAwal" class="form-control">
</div>
<div class="form-group">
<label>Panjang Akhir</label>
<input type="number" step="0.001" id="editPanjangAkhir" class="form-control">
</div>
<div class="form-group">
<label>Lebar</label>
<input type="number" step="0.001" id="editLebarKain" class="form-control">
</div>
<div class="form-group">
<label>Std</label>
<input type="number" step="0.001" id="editStdPotong" class="form-control">
</div>
<div class="form-group">
<label>Max</label>
<input type="number" step="0.001" id="editMaxPotong" class="form-control">
</div>
<div class="form-group">
<label>Min</label>
<input type="number" step="0.001" id="editMinPotong" class="form-control">
</div>
<div class="form-group">
<label>Toleransi (M)</label>
<input type="number" step="0.01" id="editToleransi" class="form-control" placeholder="0.00">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-primary" id="btnSaveEditPiece">Simpan</button>
<button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
</div>
</div>
</div>
</div>

<!-- Modal Edit Cacat -->
<div class="modal fade" id="modalEditCacat" data-backdrop="static" data-keyboard="false">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-info">
<h5 class="modal-title">Edit Cacat</h5>
<button class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
<div class="form-group">
<label>Kode Cacat</label>
<select id="editKodeCacat" class="form-control select-cacat-kode-edit" style="width: 100%;">
<option value="">- pilih -</option>
</select>
</div>
<div class="form-group">
<label>Nama Cacat</label>
<input type="text" id="editNamaCacat" class="form-control" readonly>
</div>
<div class="form-group">
<label>Status Cacat</label>
<input type="text" id="editStatusCacat" class="form-control" readonly>
</div>
<div class="form-group">
<label>Dari</label>
<input type="number" step="0.001" id="editDari" class="form-control">
</div>
<div class="form-group">
<label>Sampai</label>
<input type="number" step="0.001" id="editSampai" class="form-control">
</div>
<div class="form-group">
<label>Panjang Cacat</label>
<input type="text" id="editPanjangCacat" class="form-control" readonly>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-primary" id="btnSaveEditCacat">Simpan</button>
<button type="button" class="btn btn-danger" data-dismiss="modal">Tutup</button>
</div>
</div>
</div>
</div>

<!-- ======= JQUERY & SELECT2 ======= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<!-- Sweet Alert 2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
const MASTER_KODE_CACAT = <?= json_encode($kodeCacat) ?>;
let currentPieceId = null;
let currentCacatId = null;

// Initialize Select2
$('.select-cacat-kode-edit').select2({
    placeholder: 'Pilih kode cacat',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#modalEditCacat')
});


/* ================= EDIT PIECE ================= */
$(document).on('click', '.btn-edit-piece-detail', function() {
    const row = $(this).closest('tr');
    currentPieceId = row.data('piece-id');
    
    $('#editPanjangAwal').val(row.find('.panjang_awal').text());
    $('#editPanjangAkhir').val(row.find('.panjang_akhir').text());
    $('#editLebarKain').val(row.find('.lebar_kain').text());
    $('#editStdPotong').val(row.find('.standart_potong').text());
    $('#editMaxPotong').val(row.find('.max_potong').text());
    $('#editMinPotong').val(row.find('.min_potong').text());
    $('#editToleransi').val(row.find('.toleransi').text());
    
    $('#modalEditPiece').modal('show');
});

/* ================= SAVE EDIT PIECE ================= */
$('#btnSaveEditPiece').on('click', function() {
    if (!currentPieceId) {
        Swal.fire('Error', 'Piece ID tidak ditemukan', 'error');
        return;
    }
    
    $.ajax({
        url: 'save_edit_piece.php',
        type: 'POST',
        data: {
            id_piece: currentPieceId,
            panjang_awal: $('#editPanjangAwal').val(),
            panjang_akhir: $('#editPanjangAkhir').val(),
            lebar_kain: $('#editLebarKain').val(),
            standart_potong: $('#editStdPotong').val(),
            max_potong: $('#editMaxPotong').val(),
            min_potong: $('#editMinPotong').val(),
            toleransi: $('#editToleransi').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'ok') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Piece berhasil diperbarui',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Gagal', response.error || 'Unknown error', 'error');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Error: ' + xhr.statusText, 'error');
            console.error('Error:', xhr);
        }
    });
});

/* ================= EDIT CACAT ================= */
$(document).on('click', '.btn-edit-cacat-detail', function() {
    const row = $(this).closest('tr');
    currentCacatId = row.data('cacat-id');
    
    const kodeCacat = row.find('.kode_cacat').text().trim();
    
   $(document).on('click', '.btn-edit-cacat-detail', function() {
    const row = $(this).closest('tr');
    currentCacatId = row.data('cacat-id');

    buildEditKodeCacatOptions(); // 🔥 TAMBAHAN WAJIB

    const kodeCacat = row.find('.kode_cacat').text().trim();

    $('#editKodeCacat')
        .val(kodeCacat)
        .trigger('change.select2');

    $('#editNamaCacat').val(row.find('.nama_cacat').text());
    $('#editStatusCacat').val(row.find('.status_cacat').text());
    $('#editDari').val(row.find('.dari').text());
    $('#editSampai').val(row.find('.sampai').text());
    $('#editPanjangCacat').val(row.find('.panjang_cacat').text());

    $('#modalEditCacat').modal('show');
});

    $('#editNamaCacat').val(row.find('.nama_cacat').text());
    $('#editStatusCacat').val(row.find('.status_cacat').text());
    $('#editDari').val(row.find('.dari').text());
    $('#editSampai').val(row.find('.sampai').text());
    $('#editPanjangCacat').val(row.find('.panjang_cacat').text());
    
    $('#modalEditCacat').modal('show');
});

/* ================= AUTO FILL CACAT ================= */
$(document).on('change', '#editKodeCacat', function() {
    const kodeCacat = $(this).val();
    const matching = MASTER_KODE_CACAT.find(d => d.kode_defect === kodeCacat);
    
    if (matching) {
        $('#editNamaCacat').val(matching.nama_defect);
        $('#editStatusCacat').val(matching.status_defect);
    } else {
        $('#editNamaCacat').val('');
        $('#editStatusCacat').val('');
    }
});

/* ================= HITUNG PANJANG CACAT ================= */
$(document).on('input', '#editDari, #editSampai', function() {
    const dari = parseFloat($('#editDari').val()) || 0;
    const sampai = parseFloat($('#editSampai').val()) || 0;
    const panjang = sampai - dari;
    
    $('#editPanjangCacat').val(panjang > 0 ? panjang.toFixed(3) : '0.000');
});

/* ================= SAVE EDIT CACAT ================= */
$('#btnSaveEditCacat').on('click', function() {
    if (!currentCacatId) {
        Swal.fire('Error', 'Cacat ID tidak ditemukan', 'error');
        return;
    }
    
    const kodeCacat = $('#editKodeCacat').val();
    if (!kodeCacat) {
        Swal.fire('Perhatian!', 'Pilih kode cacat terlebih dahulu', 'warning');
        return;
    }
    
    $.ajax({
        url: 'save_edit_cacat.php',
        type: 'POST',
        data: {
            id_cacat: currentCacatId,
            kode_cacat: kodeCacat,
            nama_cacat: $('#editNamaCacat').val(),
            status_cacat: $('#editStatusCacat').val(),
            dari: $('#editDari').val(),
            sampai: $('#editSampai').val(),
            panjang_cacat: $('#editPanjangCacat').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'ok') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Cacat berhasil diperbarui',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Gagal', response.error || 'Unknown error', 'error');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Error: ' + xhr.statusText, 'error');
            console.error('Error:', xhr);
        }
    });
});

/* ================= SAVE HEADER ================= */
$('#btnSaveHeader').on('click', function() {
    const cpNo = $('#headerCpNo').val().trim();
    const uomCp = $('#headerUomCp').val();
    const mesinInspect = $('#headerMesinInspect').val();
    const typeCounter = $('#headerTypeCounter').val();
    
    if (!cpNo) {
        Swal.fire('Perhatian!', 'CP No wajib diisi', 'warning');
        return;
    }
    
    if (!mesinInspect) {
        Swal.fire('Perhatian!', 'Pilih No Mesin Inspect', 'warning');
        return;
    }
    
    $.ajax({
        url: 'save_edit_header.php',
        type: 'POST',
        data: {
            id_header: <?= $id_header ?>,
            cp_no: cpNo,
            uom_cp: uomCp,
            id_mesin: mesinInspect,
            type_counter: typeCounter
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'ok') {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Header berhasil diperbarui',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Gagal', response.error || 'Unknown error', 'error');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Error: ' + xhr.statusText, 'error');
            console.error('Error:', xhr);
        }
    });
});

/* ================= DELETE PIECE (dari edit_detail) ================= */
$(document).on('click', '.btn-hapus-piece-detail-edit', function() {
    const pieceId = $(this).data('piece-id');
    
    Swal.fire({
        title: 'Hapus Piece',
        text: 'Hapus piece ini dan semua cacat terkait?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'delete_piece.php',
            type: 'POST',
            data: { id_piece: pieceId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Piece berhasil dihapus',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal hapus: ' + (response.error || 'Unknown error'),
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error: ' + xhr.statusText,
                    icon: 'error'
                });
                console.error('Delete error:', xhr);
            }
        });
    });
});

function buildEditKodeCacatOptions() {
    let opt = '<option value="">- pilih -</option>';

    MASTER_KODE_CACAT.forEach(d => {
        opt += `<option value="${d.kode_defect}">
                    ${d.kode_defect} - ${d.nama_defect}
                </option>`;
    });

    $('#editKodeCacat').html(opt);
}


/* ================= DELETE CACAT (dari edit_detail) ================= */
$(document).on('click', '.btn-hapus-cacat-detail-edit', function() {
    const cacatId = $(this).data('cacat-id');
    
    Swal.fire({
        title: 'Hapus Cacat',
        text: 'Hapus cacat ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'delete_cacat.php',
            type: 'POST',
            data: { id_cacat: cacatId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Cacat berhasil dihapus',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal hapus: ' + (response.error || 'Unknown error'),
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error: ' + xhr.statusText,
                    icon: 'error'
                });
                console.error('Delete error:', xhr);
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
