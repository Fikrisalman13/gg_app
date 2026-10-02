<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
if (!isset($_SESSION['UserName'])) {
  header('Location:/gg_app/login.php');
  exit;
}
function checkPermissions($conn, $groupId, $menuId)
{
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
    $p = $r;
  return $p;
}
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212); // Sama menu ID dengan experiment
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = 'Anda tidak memiliki hak akses.';
  header('Location:../dashboard.php');
  exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
  #mesinTable thead th {
    text-align: center;
    vertical-align: middle;
    background: #f4f6f9
  }

  .param-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px 12px;
    font-size: 12.5px;
    line-height: 1.45;
    min-width: 240px
  }

  .param-item {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding: 2px 6px;
    border-radius: 4px;
    background: #f8fafc
  }

  .param-item:nth-child(odd) {
    background: #eef2f7
  }

  .param-label {
    font-weight: 600;
    color: #475569;
    min-width: 62px;
    display: inline-block
  }

  .param-sep {
    color: #94a3b8
  }

  .param-val {
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    font-weight: 500;
    margin-left: auto;
    padding-left: 8px
  }

  .param-unit {
    color: #64748b;
    font-size: 11px;
    margin-left: 2px
  }
</style>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><i class="fas fa-industry mr-1"></i> Master Mesin Lab</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Master Mesin Lab</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-<?= htmlspecialchars($themeColor) ?>">
        <div class="card-header">
          <h3 class="card-title">Daftar Mesin Lab</h3>
          <div class="card-tools">
            <?php if (($permissions['CanAdd'] ?? 0) == 1): ?>
              <a href="input_mesin.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah Mesin</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <table id="mesinTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
            <thead>
              <tr>
                <th>No</th>
                <th>Kode Mesin</th>
                <th>Nama Mesin</th>
                <th>Parameter Default</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
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
    $('#mesinTable').DataTable({
      processing: true, serverSide: true, responsive: true,
      ajax: { url: 'serverside_master_mesin.php', type: 'POST' },
      columns: [
        { data: null, orderable: false, searchable: false, className: 'text-center', render: (data, type, row, meta) => meta.settings._iDisplayStart + meta.row + 1 },
        { data: 'kode_mesin' },
        { data: 'nama_mesin' },
        {
          data: null, orderable: false, searchable: false, render: (d, t, row) => {
            const fmt = v => (v === null || v === undefined || v === '') ? '-' : parseFloat(v);
            const item = (label, val, unit) => `<div class="param-item"><span class="param-label">${label}</span><span class="param-sep">:</span><span class="param-val">${fmt(val)}<span class="param-unit">${unit}</span></span></div>`;
            return `<div class="param-grid">
          ${item('IR', row.infra_red, '%')}
          ${item('Padder', row.tekanan_padder, '')}
          ${item('WPU', row.wpu, '%')}
          ${item('Speed', row.speed, '')}
          ${item('Fan 1', row.fan1, '%')}
          ${item('Fan 2', row.fan2, '%')}
          ${item('Chamber 1', row.temp_chamber_1, '°C')}
          ${item('Chamber 2', row.temp_chamber_2, '°C')}
          ${item('Time 1', row.temp_chamber_1_time, ' dtk')}
          ${item('Time 2', row.temp_chamber_2_time, ' dtk')}
        </div>`;
          }
        },
        { data: null, orderable: false, searchable: false, render: (d, t, row) => `<span class="badge badge-${row.status === 'Active' ? 'success' : 'danger'}">${row.status}</span>` },
        {
          data: null, orderable: false, searchable: false, className: 'text-center', render: (data, type, row, meta) => {
            let h = '';
            if (!isApproved || isAdmin) {
              h += `<a href="input_mesin.php?id=${row.id}" class="btn btn-warning btn-sm mr-1"><i class="fas fa-edit"></i></a>`;
            }
            if ((permissions.CanDelete ?? 0) == 1) {
              h += `<button type="button" class="btn btn-danger btn-sm" data-id="${row.id}" onclick="deleteMachine(${row.id})"><i class="fas fa-trash"></i></button>`;
            }
            return h;
          }
        }
      ],
      language: { processing: 'Sedang memproses...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada data', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } }
    });
  });
  var permissions = <?= json_encode($permissions) ?>; var isApproved = false; var isAdmin = (parseInt("<?= $_SESSION['GroupId'] ?? 0 ?>") === 1);
  function deleteMachine(id) {
    Swal.fire({ title: 'Hapus Mesin?', text: 'Mesin akan dihapus secara permanen dari database. Data ini tidak bisa dikembalikan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
      .then(r => { if (!r.isConfirmed) return; $.post('delete_machine.php', { id: id }, function (resp) { if (resp.status === 'success') Swal.fire('Berhasil', 'Mesin berhasil dihapus.', 'success').then(() => location.reload()); else Swal.fire('Gagal', resp.message || 'Hapus gagal.', 'error'); }, 'json').fail(() => Swal.fire('Error', 'Request hapus gagal.', 'error')); });
  }
</script>