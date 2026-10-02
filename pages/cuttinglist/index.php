<?php
// ======================================================
// cuttinglist/index.php — LIST CUTTING (FINAL FIX)
// ======================================================

require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$menuId = 175;
requireView($conn, $menuId);

// ================= FILTER =================
$status    = $_GET['status'] ?? 'not_processed';

// Date range - hanya jika user input atau status = all (optional)
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

// Jika user tidak input date, gunakan range yang luas untuk processed/all
if (in_array($status, ['processed', 'all']) && empty($startDate) && empty($endDate)) {
    // Tampilkan semua data processed/all tanpa filter date
    // User bisa mengisi date jika mau filter specific range
}

// ================= SQL =====================
$sql = "
SELECT
    h.id_header,
    h.cp_no,
    h.processed,
    h.created_date,
    h.created_by,
    h.updated_by,
    h.type_counter,
    h.uom_cp,
    p.id_piece,
    p.piece_no,
    p.piece_code,
    p.panjang_awal,
    p.panjang_akhir,
    p.susut,
    p.standart_potong,
    p.min_potong,
    p.max_potong,
    p.uom_cp as piece_uom
FROM cl_cutting_header h
LEFT JOIN cl_cutting_piece p ON h.id_header = p.id_header
WHERE 1=1
";

$params = [];

// NOT PROCESSED: processed = 0
if ($status === 'not_processed') {
    $sql .= " AND processed = 0";
}

// PROCESSED: processed = 1, dan filter tanggal jika ada
if ($status === 'processed') {
    $sql .= " AND processed = 1";
    if ($startDate && $endDate) {
        $sql .= " AND created_date BETWEEN ? AND ?";
        $params[] = $startDate . ' 00:00:00';
        $params[] = $endDate   . ' 23:59:59';
    }
}

// ALL: tidak filter processed, tapi filter tanggal jika ada
if ($status === 'all') {
    if ($startDate && $endDate) {
        $sql .= " AND created_date BETWEEN ? AND ?";
        $params[] = $startDate . ' 00:00:00';
        $params[] = $endDate   . ' 23:59:59';
    }
}

$sql .= " ORDER BY h.created_date DESC, p.piece_no ASC";

$stmt = sqlsrv_query($conn, $sql, $params);
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

<!-- ================= TOOLBAR ================= -->
<form method="get" id="filterForm" class="mb-3">
<div class="d-flex align-items-end flex-wrap">

   <!-- STATUS -->
    <div class="mr-2">
        <label>Status</label>
        <select name="status" id="statusSelect" class="form-control">
            <option value="not_processed" <?= $status=='not_processed'?'selected':'' ?>>Not Processed</option>
            <option value="processed" <?= $status=='processed'?'selected':'' ?>>Processed</option>
            <option value="all" <?= $status=='all'?'selected':'' ?>>All</option>
        </select>
    </div>

    <!-- DATE RANGE (muncul untuk processed dan all) -->
    <div class="mr-2" id="dateRangeBox" style="display: none;">
        <label>Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="mr-2" id="dateRangeBox2" style="display: none;">
        <label>End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>

    <div class="mr-2 mt-4" id="applyBox" style="display: none;">
        <button type="submit" class="btn btn-info">
            <i class="fas fa-search"></i> Apply
        </button>
    </div>

    <div class="mr-2 mt-4">
        <button type="button" class="btn btn-light" id="btnToggleFilter">
            <i class="fas fa-filter"></i> Filter
        </button>
    </div>

    <div class="mr-2 mt-4">
        <a href="tambahcutting.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Tambah
        </a>
    </div>

    <div class="mr-2 mt-4">
        <button type="button" class="btn btn-warning" id="btnEdit">
            <i class="fas fa-edit"></i> Edit
        </button>
    </div>

    <div class="mr-2 mt-4">
        <button type="button" class="btn btn-danger" id="btnDelete">
            <i class="fas fa-trash"></i> Hapus
        </button>
    </div>

    <div class="mr-2 mt-4">
        <button type="button" class="btn btn-info" id="btnPrint">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <div class="mt-4">
        <button type="button" class="btn btn-primary" id="btnProcessToggle">
            <i class="fas fa-check"></i>
            <span id="processText">Processed</span>
        </button>
    </div>

</div>
</form>

<div class="table-responsive">

<ul class="nav nav-tabs mb-3" id="mainTab">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#tabList">List</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tabDetail" id="tabDetailBtn">
            Detail
        </a>
    </li>
</ul>

<div class="tab-content">

<!-- ================= LIST ================= -->
<div class="tab-pane fade show active" id="tabList">

<table id="dataTable" class="table table-bordered table-hover table-sm">

<thead class="thead-light">
<tr>
    <th style="width:40px">Pilih</th>
    <th>CP No</th>
    <th>Status</th>
    <th>Type Counter</th>
    <th>Piece</th>
    <th>Panjang Awal</th>
    <th>Panjang Akhir</th>
    <th>Susut</th>
    <th>Std</th>
    <th>Min</th>
    <th>Max</th>
    <th>UOM</th>
    <th>Created Date</th>
    <th>Created By</th>
</tr>

<tr id="filterRow" style="display:none">
    <th></th>
    <th><input class="form-control form-control-sm filter-input" data-col="1"></th>
    <th><input class="form-control form-control-sm filter-input" data-col="2"></th>
    <th><input class="form-control form-control-sm filter-input" data-col="3"></th>
    <th><input class="form-control form-control-sm filter-input" data-col="4"></th>
</tr>
</thead>

<tbody>
<?php if ($stmt): ?>
<?php 
$lastCpNo = null;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
    // Hanya tampilkan checkbox di baris pertama dari setiap CP No
    $isNewCp = ($row['cp_no'] !== $lastCpNo);
    $lastCpNo = $row['cp_no'];
?>
<tr>
    <td class="text-center">
        <?php if ($isNewCp): ?>
        <input type="checkbox" class="chk-cp" value="<?= $row['id_header'] ?>">
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($row['cp_no']) ?></td>
    <td class="text-center">
        <?php if ($isNewCp): ?>
            <?php if ($row['processed'] == 1): ?>
                <span class="badge badge-success">Processed</span>
            <?php else: ?>
                <span class="badge badge-warning">Not Processed</span>
            <?php endif; ?>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($row['type_counter']) ?></td>
    <td><?php if ($row['id_piece']): ?><?= htmlspecialchars($row['piece_code'] ?? 'Piece ' . $row['piece_no']) ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['panjang_awal'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['panjang_akhir'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['susut'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['standart_potong'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['min_potong'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= $row['max_potong'] ?><?php endif; ?></td>
    <td><?php if ($row['id_piece']): ?><?= htmlspecialchars($row['piece_uom']) ?><?php endif; ?></td>
    <td><?php if ($isNewCp): ?><?= $row['created_date'] ? $row['created_date']->format('Y-m-d H:i') : '' ?><?php endif; ?></td>
    <td><?php if ($isNewCp): ?><?= htmlspecialchars($row['created_by']) ?><?php endif; ?></td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</tbody>

</table>

</div>

<!-- ================= DETAIL ================= -->
<div class="tab-pane fade" id="tabDetail">
    <div id="detailContainer" class="text-muted p-3">
        Pilih minimal 1 CP No untuk melihat detail.
    </div>
</div>

</div>
</div>

</div>
</div>

</div>
</section>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Sweet Alert 2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- ================= JS ================= -->
<script>
$('#btnToggleFilter').on('click', function () {
    $('#filterRow').toggle();
    if (!$('#filterRow').is(':visible')) {
        $('.filter-input').val('');
        filterTable();
    }
});

$('.filter-input').on('keyup', filterTable);

function filterTable() {
    const filters = [];
    $('.filter-input').each(function () {
        filters.push($(this).val().toLowerCase());
    });

    $('#dataTable tbody tr').each(function () {
        let show = true;
        $(this).find('td').each(function (i) {
            if (filters[i-1] && !$(this).text().toLowerCase().includes(filters[i-1])) {
                show = false;
            }
        });
        $(this).toggle(show);
    });
}

function updateProcessButton() {
    const status = $('#statusSelect').val();
    const btn    = $('#btnProcessToggle');
    const text   = $('#processText');
    const icon   = btn.find('i');

    if (status === 'processed') {
        btn.removeClass('btn-primary').addClass('btn-secondary');
        icon.removeClass('fa-check').addClass('fa-undo');
        text.text('Unprocessed');
    } else {
        btn.removeClass('btn-secondary').addClass('btn-primary');
        icon.removeClass('fa-undo').addClass('fa-check');
        text.text('Processed');
    }
}

updateProcessButton();
$('#statusSelect').on('change', updateProcessButton);

$('#tabDetailBtn').on('click', function () {
    const ids = [];
    $('.chk-cp:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        $('#detailContainer').html('<div class="text-danger">Pilih minimal 1 CP No</div>');
        return;
    }

    $.post('detail_cutting.php', { ids }, function (html) {
        $('#detailContainer').html(html);
    });
});

function handleStatusChange() {
    const status = $('#statusSelect').val();

    if (status === 'processed' || status === 'all') {
        $('#dateRangeBox').show();
        $('#dateRangeBox2').show();
        $('#applyBox').show();
        // Jangan auto-fill date, biarkan user yang isi jika mau
    } else {
        $('#dateRangeBox').hide();
        $('#dateRangeBox2').hide();
        $('#applyBox').hide();
        // Reset tanggal untuk not_processed
        $('input[name="start_date"]').val('');
        $('input[name="end_date"]').val('');
    }
}

// INIT
handleStatusChange();

// CHANGE STATUS: submit form untuk refresh data
$('#statusSelect').on('change', function () {
    handleStatusChange();
    $('#filterForm').submit();
});

/* ===============================
   PROCESS BUTTON HANDLER
   ➜ Untuk status 'not_processed': process cutting calculation
   ➜ Untuk status 'processed': unprocess (set processed = 0)
================================ */
$('#btnProcessToggle').on('click', function () {
    const status = $('#statusSelect').val();
    const ids = [];
    $('.chk-cp:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        alert('Pilih minimal 1 CP No');
        return;
    }

    if (status === 'not_processed') {
        // Process cutting
        Swal.fire({
            title: 'Proses Cutting',
            text: 'Lanjutkan proses cutting untuk ' + ids.length + ' item?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Proses',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'process_cutting.php',
                type: 'POST',
                data: { ids: ids },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Berhasil diproses: ' + response.processed + ' item',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = '?status=processed&start_date=&end_date=';
                        });
                    } else {
                        Swal.fire({
                            title: 'Gagal!',
                            text: 'Error: ' + (response.error || 'Unknown error'),
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Gagal proses';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || errorMsg;
                    } catch(e) {
                        errorMsg = xhr.statusText || 'Network error';
                        console.log('Raw response:', xhr.responseText);
                    }
                    Swal.fire({
                        title: 'Error!',
                        text: errorMsg,
                        icon: 'error'
                    });
                    console.error('Process error:', xhr);
                    console.error('Response text:', xhr.responseText);
                }
            });
        });

    } else if (status === 'processed') {
        // Unprocess
        Swal.fire({
            title: 'Batalkan Proses',
            text: 'Hapus proses untuk ' + ids.length + ' item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'unprocess_cutting.php',
                type: 'POST',
                data: { ids: ids },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Berhasil dibatalkan: ' + response.processed + ' item',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = '?status=not_processed&start_date=&end_date=';
                        });
                    } else {
                        Swal.fire({
                            title: 'Gagal!',
                            text: 'Error: ' + (response.error || 'Unknown error'),
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Gagal batalkan';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || errorMsg;
                    } catch(e) {
                        errorMsg = xhr.statusText || 'Network error';
                    }
                    Swal.fire({
                        title: 'Error!',
                        text: errorMsg,
                        icon: 'error'
                    });
                    console.error('Unprocess error:', xhr);
                }
            });
        });
    }
});

/* ===============================
   EDIT BUTTON HANDLER
   ➜ Hanya bisa edit jika status unprocessed
================================ */
$('#btnEdit').on('click', function () {
    const ids = [];
    $('.chk-cp:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        alert('Pilih minimal 1 CP No untuk diedit');
        return;
    }

    if (ids.length > 1) {
        alert('Hanya bisa edit 1 CP No sekaligus');
        return;
    }

    // Check status - dapatkan status dari data attribute atau query
    const status = $('#statusSelect').val();
    if (status === 'processed') {
        alert('Tidak bisa edit data yang sudah di-process. Gunakan Unprocessed terlebih dahulu.');
        return;
    }

    // Redirect ke halaman edit
    window.location.href = 'edit_detail.php?id=' + ids[0];
});

/* ===============================
   DELETE BUTTON HANDLER
   ➜ Hapus semua data (header + pieces + cacat)
   ➜ Hanya untuk status unprocessed
================================ */
$('#btnDelete').on('click', function () {
    const ids = [];
    $('.chk-cp:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        Swal.fire({
            title: 'Perhatian!',
            text: 'Pilih minimal 1 CP No untuk dihapus',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    const status = $('#statusSelect').val();
    if (status === 'processed') {
        Swal.fire({
            title: 'Tidak Bisa!',
            text: 'Tidak bisa menghapus data yang sudah di-process. Gunakan Unprocessed terlebih dahulu.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    Swal.fire({
        title: 'Hapus Data',
        text: 'Hapus ' + ids.length + ' CP No beserta semua data terkait? Tindakan ini tidak bisa dibatalkan!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'delete_header.php',
            type: 'POST',
            data: { ids: ids },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Berhasil dihapus: ' + response.deleted + ' item',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Error: ' + (response.error || 'Unknown error'),
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = 'Gagal hapus';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch(e) {
                    errorMsg = xhr.statusText || 'Network error';
                }
                Swal.fire({
                    title: 'Error!',
                    text: errorMsg,
                    icon: 'error'
                });
                console.error('Delete error:', xhr);
            }
        });
    });
});

/* ===============================
   PRINT BUTTON HANDLER
================================ */
$('#btnPrint').on('click', function() {
    const ids = [];
    $('.chk-cp:checked').each(function () {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        Swal.fire({
            title: 'Perhatian!',
            text: 'Pilih minimal 1 CP No untuk dicetak',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Open print window with form submission
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'print_cutting.php';
    form.target = '_blank';
    
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});
</script>