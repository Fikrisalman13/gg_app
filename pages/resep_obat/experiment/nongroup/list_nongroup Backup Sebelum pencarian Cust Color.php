<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
if (!isset($_SESSION['UserName'])) { header('Location:/gg_app/login.php'); exit; }

function checkPermissions($conn, $groupId, $menuId) {
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $p = $r;
  return $p;
}
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) { $_SESSION['error']='Anda tidak memiliki hak akses.'; header('Location:../../dashboard.php'); exit; }

$isAdmin = (int)($_SESSION['GroupId'] ?? 0) === 1;
$canEdit = $isAdmin || ($permissions['CanEdit'] ?? 0) == 1;
$canDelete = $isAdmin || ($permissions['CanDelete'] ?? 0) == 1;
$themeColor = $_SESSION['Theme'] ?? 'primary';
include __DIR__ . '/../../../../includes/header.php';
include __DIR__ . '/../../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
  .nongroup-table th, .nongroup-table td { vertical-align: middle; font-size: .85rem; }
  .status-pass { background: #28a745; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-fail { background: #dc3545; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-draft { background: #6c757d; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-approved { background: #007bff; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-sukses { background: #28a745; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-gagal { background: #fd7e14; color: #fff; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .status-process { background: #ffc107; color: #212529; padding: .25rem .5rem; border-radius: .25rem; font-size: .75rem; }
  .filter-group label { font-size: .8rem; font-weight: 600; margin-bottom: 2px; }
  .filter-group input, .filter-group select { font-size: .85rem; }
  .nongroup-toolbar { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
  .nongroup-toolbar .nav-pills { margin-bottom:0; }
  .nongroup-filter { display:flex; align-items:flex-end; gap:.75rem; flex-wrap:wrap; justify-content:flex-end; }
  .nongroup-filter .filter-item { min-width:150px; }
  .nongroup-filter .filter-actions { white-space:nowrap; }
  .text-truncate-click { display:inline-block; max-width: 180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; cursor:pointer; color:#0056b3; }
  .text-truncate-click:hover { text-decoration: underline; }
  .cell-main { font-weight: 600; line-height: 1.2; }
  .cell-sub { color:#6c757d; font-size:.78rem; line-height:1.25; margin-top:2px; }
  .cell-stack { min-width: 0; }
</style>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-list mr-2"></i>Daftar Eksperimen</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="list_experiment.php">Experiment</a></li>
            <li class="breadcrumb-item active">Daftar Eksperimen</li>
          </ol>
        </div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white py-2">
          <div class="d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-flask mr-1"></i> Data Eksperimen</h3>
            <a href="../input_resep.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah Experiment</a>
          </div>
        </div>
        <div class="card-body">
          <div class="nongroup-toolbar mb-3 filter-group">
            <ul class="nav nav-pills">
              <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-flask mr-1"></i> Non Group View</a></li>
              <li class="nav-item"><a class="nav-link" href="../list_experiment.php"><i class="fas fa-layer-group mr-1"></i> Group View</a></li>
            </ul>
            <div class="nongroup-filter">
              <div class="filter-item">
                <label>Status</label>
                <select id="filterStatus" class="form-control form-control-sm">
                  <option value="">Semua</option>
                  <option value="Draft">Draft</option>
                  <option value="Pass">Pass</option>
                  <option value="Fail">Fail</option>
                  <option value="Approved">Approved</option>
                  <option value="Sukses">Sukses</option>
                  <option value="Process">Process</option>
                  <option value="Gagal">Gagal</option>
                </select>
              </div>
              <div class="filter-item">
                <label>Dari Tanggal</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
              </div>
              <div class="filter-item">
                <label>Sampai Tanggal</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm">
              </div>
              <div class="filter-actions">
                <button id="btnFilter" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter"></i> Filter</button>
                <button id="btnReset" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i> Reset</button>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table id="nongroupTable" class="table table-bordered table-hover table-sm nowrap nongroup-table" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th class="text-center">No</th>
                  <th class="text-center">Input Info</th>
                  <th class="text-center">Produksi / SOI</th>
                  <th class="text-center">Warna</th>
                  <th class="text-center">Label / Produk</th>
                  <th class="text-center">Exp</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Deskripsi Produksi</th>
                  <th class="text-center">Posisi / QC</th>
                  <th class="text-center">Update Info</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function() {
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
  const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

  const statusBadge = (s) => {
    const cls = {
      'Pass': 'status-pass', 'Fail': 'status-fail', 'Draft': 'status-draft',
      'Approved': 'status-approved', 'Sukses': 'status-sukses', 'Gagal': 'status-gagal',
      'Process': 'status-process'
    }[s] || 'status-draft';
    return `<span class="${cls}">${s}</span>`;
  };

  const escapeHtml = (s) => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const renderLongText = (value, limit = 22) => {
    const text = value == null || value === '' ? '-' : String(value);
    const safeText = escapeHtml(text);
    if (text.length <= limit) return safeText;
    return `<span class="text-truncate-click" title="Klik untuk lihat lengkap" data-full="${safeText}">${escapeHtml(text.slice(0, limit))}...</span>`;
  };

  const table = $('#nongroupTable').DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    stateSave: true,
    stateDuration: -1,
    autoWidth: false,
    stateLoadParams: function(settings, data) {
      if ((data.columns || []).length !== 11) return false;
    },
    columnDefs: [
      { responsivePriority: 1, targets: 0 },
      { responsivePriority: 2, targets: -1 },
      { responsivePriority: 3, targets: 6 },
      { responsivePriority: 4, targets: 2 },
      { responsivePriority: 5, targets: 7 },
      { responsivePriority: 8, targets: [1, 3, 4, 5, 8, 9] }
    ],
    ajax: {
      url: 'serverside_nongroup.php',
      data: function(d) {
        d.filter_status = $('#filterStatus').val();
        d.filter_date_from = $('#filterDateFrom').val();
        d.filter_date_to = $('#filterDateTo').val();
      }
    },
    columns: [
      { data: null, orderable: false, searchable: false, className: 'text-center', render: (d, t, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
      { data: null, className: 'cell-stack text-center', render: d => `<div class="cell-main">${renderLongText(d.created_by, 18)}</div><div class="cell-sub">${escapeHtml(d.waktu_input || '-')}</div>` },
      { data: null, className: 'cell-stack', render: d => `<div class="cell-main">${renderLongText(d.no_cp, 20)}</div><div class="cell-sub">SOI: ${renderLongText(d.soi, 18)}</div>` },
      { data: null, className: 'cell-stack', render: d => `<div class="cell-main">${renderLongText(d.kode_warna, 18)}</div><div class="cell-sub">${renderLongText(d.color_name, 22)}</div>` },
      { data: null, className: 'cell-stack', render: d => `<div class="cell-main">${renderLongText(d.cus_color, 18)}</div><div class="cell-sub">${renderLongText(d.resep_prod_code, 18)}</div><div class="cell-sub">${renderLongText(d.resep_prod_name, 24)}</div>` },
      { data: 'experiment_seq', className: 'text-center', render: d => '#' + d },
      { data: 'display_status', className: 'text-center', render: d => statusBadge(d) },
      { data: 'deskripsi_produksi', className: 'cell-stack', render: d => renderLongText(d, 30) },
      {
        data: null,
        className: 'cell-stack',
        render: d => {
          const qc = (d.qc_keputusan || '').trim();
          const qcLine = qc && qc !== '-' ? `<div class="cell-sub">QC: ${renderLongText(qc, 20)}</div>` : '';
          return `<div class="cell-main">${renderLongText(d.posisi_hari_ini, 20)}</div>${qcLine}`;
        }
      },
      { data: null, className: 'cell-stack text-center', render: d => `<div class="cell-main">${renderLongText(d.updated_by, 18)}</div><div class="cell-sub">${escapeHtml(d.updated_at || '-')}</div>` },
      {
        data: null, orderable: false, searchable: false, className: 'text-center',
        render: (d) => {
          let btns = `<a href="../view_resep.php?resep_id=${d.id}&from=nongroup" class="btn btn-info btn-sm mr-1" title="Detail"><i class="fas fa-eye"></i></a>`;
          if (canEdit) btns += `<a href="../input_resep.php?resep_id=${d.id}&from=nongroup" class="btn btn-warning btn-sm mr-1" title="Edit"><i class="fas fa-edit"></i></a>`;
          if (canDelete) btns += `<button type="button" class="btn btn-danger btn-sm btn-delete" data-id="${d.id}" title="Hapus"><i class="fas fa-trash"></i></button>`;
          return btns;
        }
      }
    ],
    order: [[1, 'desc']],
    language: { processing: 'Memuat data...', emptyTable: 'Tidak ada data.', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data' }
  });

  $('#btnFilter').click(() => table.draw());
  $('#btnReset').click(() => {
    $('#filterStatus').val('');
    $('#filterDateFrom').val('');
    $('#filterDateTo').val('');
    table.draw();
  });

  $(document).on('click', '.text-truncate-click', function() {
    Swal.fire({
      title: 'Detail Teks',
      html: '<div class="text-left" style="white-space:pre-wrap;word-break:break-word">' + ($(this).data('full') || '') + '</div>',
      width: 600,
      confirmButtonText: 'Tutup'
    });
  });

  $(document).on('click', '.btn-delete', function() {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Hapus Experiment?',
      text: 'Detail resep experiment ini akan ikut terhapus.',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
    }).then(r => {
      if (!r.isConfirmed) return;
      $.post('../delete_experiment.php', { resep_id: id }, function(resp) {
        if (resp.status === 'success') Swal.fire('Berhasil', 'Experiment berhasil dihapus.', 'success').then(() => table.draw());
        else Swal.fire('Gagal', resp.message || 'Gagal menghapus.', 'error');
      }, 'json').fail(() => Swal.fire('Error', 'Request gagal.', 'error'));
    });
  });
});
</script>
