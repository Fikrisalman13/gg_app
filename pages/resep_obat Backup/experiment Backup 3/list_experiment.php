<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = 'Silakan login terlebih dahulu!';
  header('Location: /gg_app/login.php');
  exit;
}

function checkPermissions($conn, $groupId, $menuId)
{
  $stmt = sqlsrv_query($conn, "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?", [$groupId, $menuId]);
  $p = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
  if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
    $p = $row;
  if ($stmt)
    sqlsrv_free_stmt($stmt);
  return $p;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = 'Anda tidak memiliki hak untuk melihat halaman ini.';
  header('Location: ../../dashboard.php');
  exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Resep Obat Experiment</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item active">Resep Obat Experiment</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title"><i class="fas fa-layer-group mr-1"></i> Daftar Experiment</h3>
          <div class="card-tools"><?php if (($permissions['CanAdd'] ?? 0) == 1): ?><a href="input_resep.php"
                class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah Experiment</a><?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-layer-group mr-1"></i> Group View</a></li>
            <li class="nav-item"><a class="nav-link" href="nongroup/list_nongroup.php"><i class="fas fa-flask mr-1"></i> Non Group View</a></li>
          </ul>
          <div class="row mb-3">
            <div class="col-md-3"><label for="startDate">Start Date</label><input type="date" id="startDate"
                class="form-control"></div>
            <div class="col-md-3"><label for="endDate">End Date</label><input type="date" id="endDate"
                class="form-control"></div>
            <div class="col-md-3 align-self-end"><button id="btnFilter" class="btn btn-primary"><i
                  class="fas fa-filter"></i> Filter</button></div>
          </div>
          <div class="table-responsive">
            <table id="groupTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th>No</th>
                  <th>SOI</th>
                  <th>No CP</th>
                  <th>Kode Warna</th>
                  <th>Color Name</th>
                  <th>Resep Prod Code</th>
                  <th>Cus Color</th>
                  <th>Total Exp</th>
                  <th>Status</th>
                  <th>Aksi</th>
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
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    const canEdit = <?= (int) ($permissions['CanEdit'] ?? 0) ?>;
    const canDelete = <?= (int) ($permissions['CanDelete'] ?? 0) ?>;
    const isAdmin = <?= ((int) ($_SESSION['GroupId'] ?? 0) === 1) ? 'true' : 'false' ?>;
    const canEditApprovedRow = isAdmin && canEdit == 1;
    const canDeleteApprovedRow = isAdmin && canDelete == 1;
    const table = $('#groupTable').DataTable({
      processing: true, serverSide: true, responsive: true, order: [[1, 'desc']],
      ajax: { url: 'serverside_experiment_group.php', type: 'POST', data: function (d) { d.startDate = $('#startDate').val(); d.endDate = $('#endDate').val(); }, error: function (xhr) { console.error(xhr.responseText); Swal.fire('Error', 'Gagal memuat data group experiment.', 'error'); } },
      columns: [
        { data: null, orderable: false, searchable: false, render: function (d, t, r, m) { return m.row + 1 + m.settings._iDisplayStart; } },
        { data: 'soi' },
        { data: 'no_cp' },
        { data: 'kode_warna', render: function (d) { return '<b>' + escapeHtml(d) + '</b>'; } },
        { data: 'color_name' },
        { data: 'resep_prod_code', render: function (d) { return '<b>' + escapeHtml(d) + '</b>'; } },
        { data: 'cus_color' },
        { data: 'total_experiment', className: 'text-center' },
        {
          data: 'last_status', className: 'text-center', render: function (d, type, row) {
            const map = { Draft: 'secondary', Process: 'warning', Prosess: 'warning', Gagal: 'danger', Sukses: 'success', Approved: 'primary' };
            // Priority: Process > Approved > others
            if ((row.process_status === 'Process' || row.process_status === 'Prosess') && row.process_experiment_seq > 0) {
              return '<span class="badge badge-warning">Process #' + row.process_experiment_seq + '</span>';
            }
            if (row.approved_experiment_seq && parseInt(row.approved_experiment_seq) > 0) {
              return '<span class="badge badge-primary">Approved EXP #' + row.approved_experiment_seq + '</span>';
            }
            return '<span class="badge badge-' + (map[d] || 'secondary') + '">' + escapeHtml(d) + '</span>';
          }
        },
        { data: 'id', orderable: false, searchable: false, render: function (id, t, row) { let b = `<a href="view_group.php?group_id=${id}" class="btn btn-info btn-sm" title="Detail Group"><i class="fas fa-layer-group"></i></a>`; if (canEdit == 1 && (row.group_status !== 'Approved' || canEditApprovedRow)) b += ` <a href="input_resep.php?next_group_id=${id}" class="btn btn-success btn-sm" title="Buat Experiment Berikutnya"><i class="fas fa-plus"></i> Exp</a>`; if (canDelete == 1 && ((row.group_status !== 'Approved' && !row.approved_experiment_id) || canDeleteApprovedRow)) b += ` <button type="button" class="btn btn-danger btn-sm btn-delete-group" data-id="${id}" title="Delete Group"><i class="fas fa-trash"></i></button>`; return b; } }
      ],
      language: { processing: 'Sedang memproses...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada data', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } }
    });
    function escapeHtml(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    $('#btnFilter').on('click', function () { table.ajax.reload(); });
    $(document).on('click', '.btn-delete-group', function () {
      const id = $(this).data('id');
      Swal.fire({ title: 'Hapus Group Experiment?', text: 'Semua experiment dan detail resep dalam group ini akan ikut terhapus. Group yang sudah punya approved experiment tidak bisa dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then(r => {
        if (!r.isConfirmed) return;
        $.post('delete_group.php', { group_id: id }, function (resp) {
          if (resp.status === 'success') Swal.fire('Berhasil', 'Group experiment berhasil dihapus.', 'success').then(() => table.ajax.reload(null, false));
          else Swal.fire('Gagal', resp.message || 'Gagal menghapus group.', 'error');
        }, 'json').fail(() => Swal.fire('Error', 'Request hapus gagal.', 'error'));
      });
    });
  });
</script>