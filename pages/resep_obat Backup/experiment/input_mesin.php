<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
if (!isset($_SESSION['UserName'])) { header('Location:/gg_app/login.php'); exit; }

function checkPermissions($conn, $groupId, $menuId) {
  $stmt = sqlsrv_query($conn, "SELECT CanView,CanAdd,CanEdit,CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $p = $r;
  return $p;
}

/** Tampilkan nilai numerik dari DB dengan aman (NULL → string kosong) */
function numVal($v) { return ($v === null) ? '' : $v; }
function nf2($n) { return number_format((float)$n, 2, '.', ','); }

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) { $_SESSION['error']='Anda tidak memiliki hak akses.'; header('Location:../../dashboard.php'); exit; }

$machineId = (int)($_GET['id'] ?? 0);
$isEdit = false;
$data = [
  'kode_mesin'=>'', 'nama_mesin'=>'', 'status'=>'Active',
  'infra_red'=>null, 'tekanan_padder'=>null, 'wpu'=>null, 'speed'=>null,
  'fan1'=>null, 'fan2'=>null, 'temp_chamber_1'=>null, 'temp_chamber_2'=>null,
  'temp_chamber_1_time'=>null, 'temp_chamber_2_time'=>null
];
if ($machineId > 0) {
  $stmt = sqlsrv_query($conn, "SELECT id, kode_mesin, nama_mesin, status, infra_red, tekanan_padder, wpu, speed, fan1, fan2, temp_chamber_1, temp_chamber_2, temp_chamber_1_time, temp_chamber_2_time FROM dbo.master_mesin_lab WHERE id=?", [$machineId]);
  if ($stmt && $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data = $r; $isEdit = true;
  } else {
    $_SESSION['error'] = 'Data mesin tidak ditemukan.';
    header('Location:master_mesin.php'); exit;
  }
}

$isAdmin  = (int)($_SESSION['GroupId'] ?? 0) === 1;
$canSave  = $isEdit ? ($permissions['CanEdit'] ?? 0) == 1 : ($permissions['CanAdd'] ?? 0) == 1;
$ro       = $canSave ? '' : 'readonly';

$themeColor = $_SESSION['Theme'] ?? 'primary';
include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><i class="fas fa-<?= $isEdit?'edit':'plus' ?> mr-1"></i> <?= $isEdit?'Edit':'Tambah' ?> Master Mesin Lab</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="master_mesin.php">Master Mesin Lab</a></li>
            <li class="breadcrumb-item active"><?= $isEdit?'Edit':'Tambah' ?></li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-<?= htmlspecialchars($themeColor) ?>">
        <div class="card-header"><h3 class="card-title">Form Master Mesin Lab</h3></div>
        <form id="formMachine">
          <div class="card-body">
            <input type="hidden" name="id" value="<?= $machineId ?>">
            <input type="hidden" name="action" value="<?= $isEdit?'update':'insert' ?>">
            <?php if (!$canSave && $isEdit): ?>
              <input type="hidden" name="status" value="<?= htmlspecialchars($data['status']) ?>">
            <?php endif; ?>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="kode_mesin">Kode Mesin <span class="text-danger">*</span></label>
                  <input type="text" name="kode_mesin" id="kode_mesin" class="form-control" maxlength="50" required
                    value="<?= htmlspecialchars($data['kode_mesin']) ?>" <?= $ro ?>>
                  <small class="form-text text-muted">Contoh: ML-001, TEMP-1. Bersifat unik.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="nama_mesin">Nama Mesin <span class="text-danger">*</span></label>
                  <input type="text" name="nama_mesin" id="nama_mesin" class="form-control" maxlength="200" required
                    value="<?= htmlspecialchars($data['nama_mesin']) ?>" <?= $ro ?>>
                  <small class="form-text text-muted">Contoh: Temp Chamber 1, Paddry-01.</small>
                </div>
              </div>
            </div>

            <hr><p class="text-muted font-weight-bold mb-2"><i class="fas fa-sliders-h mr-1"></i> Parameter Default Mesin</p>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="infra_red">Infra Red (%)</label>
                  <input type="text" name="infra_red" id="infra_red" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['infra_red'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="tekanan_padder">Tekanan Padder</label>
                  <input type="text" name="tekanan_padder" id="tekanan_padder" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['tekanan_padder'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="wpu">WPU (%)</label>
                  <input type="text" name="wpu" id="wpu" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['wpu'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="speed">Speed</label>
                  <input type="text" name="speed" id="speed" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['speed'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="fan1">Fan1 (%)</label>
                  <input type="text" name="fan1" id="fan1" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['fan1'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="fan2">Fan2 (%)</label>
                  <input type="text" name="fan2" id="fan2" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['fan2'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="temp_chamber_1">Temp Chamber 1 (°C)</label>
                  <input type="text" name="temp_chamber_1" id="temp_chamber_1" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['temp_chamber_1'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="temp_chamber_2">Temp Chamber 2 (°C)</label>
                  <input type="text" name="temp_chamber_2" id="temp_chamber_2" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['temp_chamber_2'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="temp_chamber_1_time">Waktu Chamber 1 (detik)</label>
                  <input type="text" name="temp_chamber_1_time" id="temp_chamber_1_time" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['temp_chamber_1_time'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="temp_chamber_2_time">Waktu Chamber 2 (detik)</label>
                  <input type="text" name="temp_chamber_2_time" id="temp_chamber_2_time" class="form-control"
                    value="<?= htmlspecialchars(nf2($data['temp_chamber_2_time'] ?? 0)) ?>" <?= $ro ?>>
                </div>
              </div>
            </div>

            <hr>
            <div class="form-group">
              <label for="status">Status</label>
              <?php if ($canSave): ?>
                <select name="status" id="status" class="form-control">
                  <option value="Active" <?= $data['status']==='Active'?'selected':'' ?>>Active</option>
                  <option value="Inactive" <?= $data['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
              <?php else: ?>
                <input type="text" class="form-control" value="<?= htmlspecialchars($data['status']) ?>" readonly>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-footer text-right">
            <a href="master_mesin.php" class="btn btn-default">Kembali</a>
            <?php if ($canSave): ?>
              <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-save"></i> Simpan</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
  $('#formMachine').on('submit',function(e){
    e.preventDefault();
    if(!$('#kode_mesin').val().trim()){ Swal.fire('Peringatan','Kode Mesin wajib diisi.','warning'); return; }
    if(!$('#nama_mesin').val().trim()){ Swal.fire('Peringatan','Nama Mesin wajib diisi.','warning'); return; }
    $.post('save_machine.php', $(this).serialize(), function(resp){
      if(resp.status==='success') Swal.fire('Berhasil', resp.message, 'success').then(()=>location.href='master_mesin.php');
      else Swal.fire('Gagal', resp.message || 'Simpan gagal.', 'error');
    },'json').fail(()=> Swal.fire('Error','Request simpan gagal.','error'));
  });
});
</script>
