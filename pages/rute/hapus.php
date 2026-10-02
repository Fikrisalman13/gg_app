<?php
session_start();
ob_start();
include '../../koneksi.php';
require_once __DIR__ . '/deletion_history.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = "Silakan login terlebih dahulu!";
  header('Location: /gg_app/login.php');
  exit;
}

$sessionUser = (string)$_SESSION['UserName'];
$isIt1 = strtoupper($sessionUser) === 'IT1';

function nocache(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

function json_out(array $data, int $status = 200): never {
  if (ob_get_length()) ob_clean();
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  nocache();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function dt_to_str($v): ?string {
  if ($v instanceof DateTimeInterface) return $v->format('Y-m-d H:i:s');
  return is_string($v) ? $v : null;
}

function deleted_summary(string $table, array $data): string {
  if ($table === 'RouteMaster') {
    return trim(($data['Name'] ?? '-') . ' | ' . ($data['Address'] ?? '-'));
  }
  if ($table === 'Vehicles') {
    return trim(($data['Plate'] ?? '-') . ' | ' . ($data['Merk'] ?? '-') . ' ' . ($data['Model'] ?? '-'));
  }
  if ($table === 'Routes') {
    return trim(($data['Name'] ?? '-') . ' | ' . ($data['VehiclePlate'] ?? '-') . ' | ' . ($data['DriverName'] ?? '-'));
  }
  if ($table === 'RouteBatch') {
    return trim('Batch ' . ($data['BatchNo'] ?? ($data['BatchId'] ?? '-')));
  }
  if ($table === 'RouteActivities') {
    return trim(($data['Comment'] ?? '-') . ' | RouteId ' . ($data['RouteId'] ?? '-'));
  }
  return substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 180);
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
  try {
    if (!$conn) json_out(['ok'=>false,'msg'=>'Koneksi DB gagal'], 500);
    if (!$isIt1) json_out(['ok'=>false,'msg'=>'Hanya user IT1 yang boleh melihat dan rollback data hapus.'], 403);

    ensure_hapus_table($conn);

    if ($action === 'list') {
      $status = (string)($_GET['status'] ?? 'deleted');
      if (!in_array($status, ['deleted', 'restored', 'all'], true)) $status = 'deleted';

      $sql = "SELECT TOP 500 HapusId, SourceTable, SourcePk, SourcePkValue, DataJson,
                     DeletedBy, DeletedAt, RestoredBy, RestoredAt, Status
              FROM dbo.hapus";
      $params = [];
      if ($status !== 'all') {
        $sql .= " WHERE Status=?";
        $params[] = $status;
      }
      $sql .= " ORDER BY DeletedAt DESC, HapusId DESC";

      $st = sqlsrv_query($conn, $sql, $params);
      if ($st === false) throw new Exception(dh_sqlerr());

      $rows = [];
      while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
        $data = json_decode((string)$r['DataJson'], true);
        if (!is_array($data)) $data = [];
        $r['DeletedAt'] = dt_to_str($r['DeletedAt'] ?? null);
        $r['RestoredAt'] = dt_to_str($r['RestoredAt'] ?? null);
        $r['Summary'] = deleted_summary((string)$r['SourceTable'], $data);
        $r['Data'] = $data;
        unset($r['DataJson']);
        $rows[] = $r;
      }
      sqlsrv_free_stmt($st);
      json_out(['ok'=>true,'rows'=>$rows]);
    }

    if ($action === 'restore') {
      $hapusId = (int)($_POST['hapusId'] ?? 0);
      if ($hapusId <= 0) json_out(['ok'=>false,'msg'=>'ID history tidak valid'], 400);

      if (!sqlsrv_begin_transaction($conn)) {
        throw new Exception('Begin transaction failed: '.dh_sqlerr());
      }
      try {
        restore_hapus_row($conn, $hapusId, $sessionUser);
        if (!sqlsrv_commit($conn)) throw new Exception('Commit failed: '.dh_sqlerr());
      } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        throw $e;
      }

      json_out(['ok'=>true,'msg'=>'Data berhasil di-rollback']);
    }

    json_out(['ok'=>false,'msg'=>'Unknown action'], 404);
  } catch (Throwable $e) {
    json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
  }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
ob_end_flush();
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">History Data Dihapus</h1>
        </div>
        <div class="col-sm-6 text-end">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
            <li class="breadcrumb-item active">History Hapus</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$isIt1): ?>
        <div class="alert alert-danger">Hanya user IT1 yang bisa melihat history data dihapus dan melakukan rollback.</div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white d-flex align-items-center">
            <h3 class="card-title mb-0">Daftar Data Terhapus</h3>
            <div class="ml-auto">
              <select id="statusFilter" class="form-control form-control-sm">
                <option value="deleted">Belum rollback</option>
                <option value="restored">Sudah rollback</option>
                <option value="all">Semua</option>
              </select>
            </div>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-sm table-hover" id="tblHapus">
              <thead class="thead-light">
                <tr>
                  <th style="width:70px">ID</th>
                  <th style="width:140px">Tabel</th>
                  <th>Data</th>
                  <th style="width:120px">Dihapus Oleh</th>
                  <th style="width:170px">Waktu Hapus</th>
                  <th style="width:110px">Status</th>
                  <th style="width:110px">Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<style>
#tblHapus td { vertical-align: top; }
.history-summary { max-width: 560px; white-space: normal; }
.history-json { display: none; margin-top: .5rem; max-height: 220px; overflow: auto; }
</style>

<?php include '../../includes/footer.php'; ?>

<script>
(function(){
  const IS_IT1 = <?php echo $isIt1 ? 'true' : 'false'; ?>;
  const API_URL = window.location.href.split('#')[0].split('?')[0];
  if (!IS_IT1) return;

  if (typeof window.jQuery === 'undefined') {
    var body = document.querySelector('#tblHapus tbody');
    if (body) body.innerHTML = '<tr><td colspan="7" class="text-danger">jQuery belum termuat, data history tidak bisa ditampilkan.</td></tr>';
    return;
  }

  function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html();
  }

  function loadRows() {
    const status = $('#statusFilter').val() || 'deleted';
    $.getJSON(API_URL, {action:'list', status})
      .done(function(res){
        const $tb = $('#tblHapus tbody').empty();
        if (!res.ok) {
          $tb.append(`<tr><td colspan="7" class="text-danger">${esc(res.msg || 'Gagal memuat data')}</td></tr>`);
          return;
        }
        if (!res.rows.length) {
          $tb.append('<tr><td colspan="7" class="text-muted text-center">Tidak ada data.</td></tr>');
          return;
        }
        res.rows.forEach(function(r){
          const dataJson = esc(JSON.stringify(r.Data || {}, null, 2));
          const canRestore = r.Status === 'deleted';
          const btn = canRestore
            ? `<button class="btn btn-primary btn-sm btn-restore" data-id="${r.HapusId}"><i class="fas fa-undo"></i> Rollback</button>`
            : `<span class="text-muted">${esc(r.RestoredBy || '')}</span>`;
          $tb.append(`
            <tr>
              <td>${esc(r.HapusId)}</td>
              <td>${esc(r.SourceTable)}<br><small class="text-muted">${esc(r.SourcePk)}: ${esc(r.SourcePkValue)}</small></td>
              <td class="history-summary">
                ${esc(r.Summary || '-')}
                <div><button type="button" class="btn btn-link btn-sm p-0 btn-detail">Detail JSON</button></div>
                <pre class="history-json bg-light border rounded p-2">${dataJson}</pre>
              </td>
              <td>${esc(r.DeletedBy)}</td>
              <td>${esc(r.DeletedAt)}</td>
              <td>${esc(r.Status)}</td>
              <td>${btn}</td>
            </tr>
          `);
        });
      })
      .fail(function(xhr){
        let msg = 'Gagal memuat data';
        try { msg = JSON.parse(xhr.responseText).msg || msg; } catch(e) {}
        $('#tblHapus tbody').html(`<tr><td colspan="7" class="text-danger">${esc(msg)}</td></tr>`);
      });
  }

  $(function(){
    loadRows();
    $('#statusFilter').on('change', loadRows);

    $(document).on('click', '.btn-detail', function(){
      $(this).closest('td').find('.history-json').toggle();
    });

    $(document).on('click', '.btn-restore', function(){
      const id = $(this).data('id');
      const runRestore = function(){
        $.post(API_URL, {action:'restore', hapusId:id})
          .done(function(res){
            if (typeof res === 'string') { try { res = JSON.parse(res); } catch(e) {} }
            if (res.ok) {
              if (window.Swal) Swal.fire({icon:'success', title:'Sukses', text:res.msg || 'Rollback berhasil'});
              loadRows();
            } else {
              if (window.Swal) Swal.fire({icon:'error', title:'Gagal', text:res.msg || 'Rollback gagal'});
              else alert(res.msg || 'Rollback gagal');
            }
          })
          .fail(function(xhr){
            let msg = 'Rollback gagal';
            try { msg = JSON.parse(xhr.responseText).msg || msg; } catch(e) {}
            if (window.Swal) Swal.fire({icon:'error', title:'Gagal', text:msg});
            else alert(msg);
          });
      };

      if (!window.Swal) {
        if (confirm('Rollback data ini?')) runRestore();
        return;
      }
      Swal.fire({
        title: 'Rollback Data?',
        text: 'Data akan dikembalikan ke tabel asal.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Rollback',
        cancelButtonText: 'Batal'
      }).then((result) => { if (result.isConfirmed) runRestore(); });
    });
  });
})();
</script>
