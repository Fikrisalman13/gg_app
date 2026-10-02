<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/experiment_visibility_helper.php';
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
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = 'Anda tidak memiliki hak akses.';
  header('Location:../../dashboard.php');
  exit;
}
$resep_id = $_GET['resep_id'] ?? '';
$next_group_id = $_GET['next_group_id'] ?? '';
$from = ($_GET['from'] ?? '') === 'nongroup' ? 'nongroup' : 'group';
$experimentListUrl = $from === 'nongroup' ? 'nongroup/list_nongroup.php' : 'list_experiment.php';
$mode = $resep_id ? 'Edit' : 'Tambah';
if ($next_group_id)
  $mode = 'Tambah Experiment Berikutnya';
$data = ['experiment_seq' => 1, 'experiment_status' => 'Draft', 'group_id' => $next_group_id];
$group = ['soi' => '', 'no_cp' => ''];
$details = [];
if ($resep_id) {
  $s = sqlsrv_query($conn, "SELECT e.*, g.soi AS group_soi, g.no_cp AS group_no_cp, g.kode_grey AS g_kode_grey, g.mesin AS g_mesin, g.kode_warna AS g_kode_warna, g.color_name AS g_color_name, g.color_desc AS g_color_desc, g.resep_prod_code AS g_resep_prod_code, g.resep_prod_name AS g_resep_prod_name, g.cus_color AS g_cus_color, g.proint_resephdid AS g_proint_resephdid, g.group_status FROM dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id=e.group_id WHERE e.id=?", [$resep_id]);
  if ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC))
    $data = $r;
  else
    die('Data not found');
  if (!resepExperimentCanAccessStatus($conn, $data['experiment_status'] ?? 'Draft', $data['was_in_process'] ?? 0, $data['was_approved'] ?? 0)) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengubah experiment ini.';
    header('Location: list_experiment.php');
    exit;
  }
  $group = ['soi' => !empty($data['soi']) ? $data['soi'] : ($data['group_soi'] ?? ''), 'no_cp' => $data['group_no_cp'] ?? ($data['no_cp'] ?? '')];
  // Data teknis di form edit diambil dari row experiment itu sendiri, bukan dari group.
  $sd = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=?", [$resep_id]);
  while ($sd && $r = sqlsrv_fetch_array($sd, SQLSRV_FETCH_ASSOC))
    $details[] = $r;
} elseif ($next_group_id) {
  // Load experiment terakhir dalam group sebagai template, tapi belum insert ke DB.
  $sg = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_group WHERE id=?", [$next_group_id]);
  $gdata = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
  if (!$gdata)
    die('Group not found');
  $seqStmt = sqlsrv_query($conn, "SELECT TOP 1 experiment_seq FROM dbo.resep_obat_experiment WHERE group_id=? AND created_by=? ORDER BY experiment_seq DESC, id DESC", [$next_group_id, $_SESSION['UserName'] ?? '']);
  $seqRow = $seqStmt ? sqlsrv_fetch_array($seqStmt, SQLSRV_FETCH_ASSOC) : null;
  $nextSeq = $seqRow ? ((int) $seqRow['experiment_seq'] + 1) : 1;
  $sl = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment WHERE group_id=? ORDER BY id DESC", [$next_group_id]);
  $last = $sl ? sqlsrv_fetch_array($sl, SQLSRV_FETCH_ASSOC) : null;
  $data = [
    'group_id' => $next_group_id,
    'experiment_seq' => $nextSeq,
    'experiment_status' => 'Draft',
    'experiment_note' => '',
    'kode_grey' => $gdata['kode_grey'] ?? '',
    'mesin' => $gdata['mesin'] ?? '',
    'kode_warna' => $gdata['kode_warna'] ?? '',
    'color_name' => $gdata['color_name'] ?? '',
    'color_desc' => $gdata['color_desc'] ?? '',
    'resep_prod_code' => $gdata['resep_prod_code'] ?? '',
    'resep_prod_name' => $gdata['resep_prod_name'] ?? '',
    'cus_color' => $gdata['cus_color'] ?? '',
    'proint_resephdid' => $gdata['proint_resephdid'] ?? null,
    'lot_no' => $last['lot_no'] ?? '',
    'weight' => $last['weight'] ?? 0,
    'plan_qty' => $last['plan_qty'] ?? 3500,
    'vlot' => $last['vlot'] ?? 0,
    'soi' => '',
    'group_no_cp' => ''
  ];
  $group = ['soi' => '', 'no_cp' => ''];
  if ($last) {
    $sd = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_detail WHERE id_resep_experiment=? ORDER BY id ASC", [$last['id']]);
    while ($sd && $r = sqlsrv_fetch_array($sd, SQLSRV_FETCH_ASSOC))
      $details[] = $r;
  }
}
$isAdmin = (int) ($_SESSION['GroupId'] ?? 0) === 1;
$isPpc = resepUserIsPpc($conn);
$isKabag = resepUserIsKabag($conn);
$canEdit = $isAdmin || (int)($permissions['CanEdit'] ?? 0) === 1;
$isApproved = !$isAdmin && !$isPpc && !$isKabag && (($data['experiment_status'] ?? '') === 'Approved');
$themeColor = $_SESSION['Theme'] ?? 'primary';
function nf4($n) { return number_format((float)$n, 4, '.', ','); }
function nf2($n) { return number_format((float)$n, 2, '.', ','); }

/**
 * Helper: apakah user boleh melihat kolom price/total/grand total/cost?
 * Boleh jika:
 *   - Admin (GroupId = 1), atau
 *   - User adalah anggota grup resep yang role_type = 'KABAG'
 */
$canSeePrice = false;
if ($isAdmin) {
  $canSeePrice = true;
} else {
  $uKabag = $_SESSION['UserName'] ?? '';
  if ($uKabag !== '') {
    $chkKabag = sqlsrv_query(
      $conn,
      "SELECT TOP 1 g.id
             FROM dbo.resep_obat_group_members m
             INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
             WHERE m.username = ? AND g.role_type = 'KABAG'",
      [$uKabag]
    );
    if ($chkKabag && sqlsrv_fetch_array($chkKabag, SQLSRV_FETCH_ASSOC)) {
      $canSeePrice = true;
    }
    if ($chkKabag)
      sqlsrv_free_stmt($chkKabag);
  }
}

$labParam = []; // machine_code, machine_name, infra_red, tekanan_padder, wpu, speed, fan1, fan2, temp_chamber_1, temp_chamber_1_time, temp_chamber_2, temp_chamber_2_time, lainnya
$labData = []; // delta_l, delta_a, delta_b, delta_e
if ($resep_id) {
  $slp = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_param WHERE id_resep_experiment=?", [$resep_id]);
  if ($slp && $rp = sqlsrv_fetch_array($slp, SQLSRV_FETCH_ASSOC))
    $labParam = $rp;
  if ($slp)
    sqlsrv_free_stmt($slp);
  $sld = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_data WHERE id_resep_experiment=?", [$resep_id]);
  if ($sld && $rd = sqlsrv_fetch_array($sld, SQLSRV_FETCH_ASSOC))
    $labData = $rd;
  if ($sld)
    sqlsrv_free_stmt($sld);
} elseif ($next_group_id && $last) {
  // Load lab_param & lab_data dari experiment terakhir sebagai template
  $slp = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_param WHERE id_resep_experiment=?", [$last['id']]);
  if ($slp && $rp = sqlsrv_fetch_array($slp, SQLSRV_FETCH_ASSOC)) {
    unset($rp['id'], $rp['id_resep_experiment'], $rp['created_at'], $rp['created_by'], $rp['updated_at'], $rp['updated_by']);
    $labParam = $rp;
  }
  if ($slp) sqlsrv_free_stmt($slp);
  $sld = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_data WHERE id_resep_experiment=?", [$last['id']]);
  if ($sld && $rd = sqlsrv_fetch_array($sld, SQLSRV_FETCH_ASSOC)) {
    unset($rd['id'], $rd['id_resep_experiment'], $rd['created_at'], $rd['created_by'], $rd['updated_at'], $rd['updated_by']);
    $labData = $rd;
  }
  if ($sld) sqlsrv_free_stmt($sld);
}

/* ---- Load Field-Level Permissions (per sub_bagian) ---- */
$fieldPermissions = []; // [field_key => true|false]
if (!$isAdmin) {
  // Get id_subbag user login dari SMUserMs -> m_emp
  $u = $_SESSION['UserName'] ?? '';
  $rs = sqlsrv_query($conn, "SELECT e.id_subbag FROM dbo.SMUserMs u LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp WHERE u.UserName = ?", [$u]);
  $r = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC) : null;
  $subbagId = $r ? (int) $r['id_subbag'] : 0;
  if ($subbagId > 0) {
    $sp = sqlsrv_query($conn, "SELECT field_key, is_readonly FROM dbo.resep_field_trustee WHERE subbag_id = ?", [$subbagId]);
    while ($sp && $row = sqlsrv_fetch_array($sp, SQLSRV_FETCH_ASSOC)) {
      $fieldPermissions[$row['field_key']] = (int) $row['is_readonly'] === 1;
    }
  }
}
/* Helper: cek apakah field tertentu readonly untuk user saat ini */
function isFieldReadonly($key)
{
  global $isApproved, $canEdit, $fieldPermissions;
  if (!$canEdit || $isApproved)
    return true;
  /* Field yang selalu readonly (hardcoded di form) */
  $alwaysRO = ['color_name', 'color_desc', 'resep_prod_name'];
  if (in_array($key, $alwaysRO))
    return true;
  return !empty($fieldPermissions[$key]);
}
/* Helper: return string 'readonly' atau '' untuk inline use */
function roAttr($key)
{
  return isFieldReadonly($key) ? 'readonly' : '';
}
function disAttr($key)
{
  return isFieldReadonly($key) ? 'disabled' : '';
}
function bgROClass($key)
{
  return isFieldReadonly($key) ? 'bg-readonly' : '';
}

$detailRO = isFieldReadonly('detail_items');
$detailKodeRO = isFieldReadonly('detail_kode');
$detailQtyRO = isFieldReadonly('detail_qty');
$detailCfRO = isFieldReadonly('detail_cf');
$detailUomCfRO = isFieldReadonly('detail_uom_cf');

include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<style>
  /* ===== Enhanced Tabs Styling ===== */
  .nav-tabs.card-header-tabs {
    margin-bottom: 0;
    border-bottom: none;
    padding: 0 0.75rem;
    gap: 0.125rem;
  }

  .nav-tabs.card-header-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: 0;
    padding: 0.85rem 1.35rem;
    font-weight: 500;
    font-size: 0.9rem;
    color: #6c757d;
    transition: all 0.25s ease;
    position: relative;
    border-radius: 0;
    background: transparent;
  }

  .nav-tabs.card-header-tabs .nav-link:not(.disabled):hover {
    color: #1a73e8;
    background: rgba(26, 115, 232, 0.06);
    border-bottom-color: rgba(26, 115, 232, 0.3);
  }

  .nav-tabs.card-header-tabs .nav-link.active {
    color: #1a73e8;
    background: transparent;
    border-bottom-color: #1a73e8;
    font-weight: 600;
  }

  .nav-tabs.card-header-tabs .nav-link i {
    margin-right: 7px;
    font-size: 1rem;
    transition: transform 0.2s ease;
  }

  .nav-tabs.card-header-tabs .nav-link:not(.disabled):hover i {
    transform: scale(1.1);
  }

  .nav-tabs.card-header-tabs .nav-link.active i {
    color: #1a73e8;
  }

  /* Tab content area */
  .tab-content {
    background: #fff;
    border-top: 1px solid #e9ecef;
  }

  .tab-content>.tab-pane {
    padding: 0;
    animation: fadeInTab 0.25s ease;
  }

  .tab-content>.tab-pane>.p-3 {
    padding: 1.5rem !important;
  }

  @keyframes fadeInTab {
    from {
      opacity: 0;
      transform: translateY(4px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Disabled state for tabs */
  .nav-tabs.card-header-tabs .nav-link.disabled {
    color: #adb5bd;
    cursor: not-allowed;
    background: transparent;
  }

  /* Card header polish — only for the tabs card (p-0 variant) */
  .card-header.bg-light.p-0 {
    background: #f8f9fa !important;
    border-bottom: none;
  }
</style>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<style>
  #detailTable thead th {
    text-align: center;
    vertical-align: middle;
    background: #f4f6f9
  }

  .select2-container--bootstrap4 .select2-dropdown {
    min-width: 350px !important
  }
</style>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><?= $mode ?> Resep Obat Experiment</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($experimentListUrl) ?>">Experiment</a></li>
            <li class="breadcrumb-item active"><?= $mode ?></li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <form id="resepForm" novalidate>
        <input type="hidden" name="resep_id" value="<?= htmlspecialchars($resep_id) ?>">
        <input type="hidden" name="group_id" value="<?= htmlspecialchars($data['group_id'] ?? '') ?>">
        <input type="hidden" name="next_group_id" value="<?= htmlspecialchars($next_group_id) ?>">
        <input type="hidden" name="proint_resephdid" id="proint_resephdid"
          value="<?= htmlspecialchars($data['proint_resephdid'] ?? '') ?>">
        <div class="card card-<?= htmlspecialchars($themeColor) ?>">
          <div class="card-header">
            <h3 class="card-title">Informasi Dasar</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <?php if ($resep_id): ?>
                  <div class="form-group row"><label class="col-sm-4 col-form-label">Urutan Eksperimen</label>
                    <div class="col-sm-8"><input readonly class="form-control"
                        value="EXP #<?= (int) ($data['experiment_seq'] ?? 1) ?>"></div>
                  </div>
                  <div class="form-group row"><label class="col-sm-4 col-form-label">Status</label>
                    <div class="col-sm-8"><select class="form-control" name="experiment_status"
                        <?= $isApproved ? 'disabled' : '' ?>><?php foreach (['Draft', 'Tunggu CP', 'Tunggu Matching', 'Sukses', 'Process'] as $st): ?>
                          <option value="<?= $st ?>" <?= (($data['experiment_status'] ?? 'Draft') === $st) ? 'selected' : '' ?>>
                            <?= $st ?></option><?php endforeach; ?>
                      </select></div>
                  </div>
                <?php else: ?>
                  <input type="hidden" name="experiment_status" value="Draft">
                <?php endif; ?>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Kode Grey</label>
                  <div class="col-sm-8"><input type="hidden" name="kode_grey" id="kode_grey_hidden"
                      value="<?= htmlspecialchars($data['kode_grey'] ?? '') ?>"><select id="select_kode_grey"
                      class="form-control select2-grey" style="width:100%" <?= disAttr('kode_grey') ?>><?php if (!empty($data['kode_grey'])): ?>
                        <option selected value="<?= htmlspecialchars($data['kode_grey']) ?>">
                          <?= htmlspecialchars($data['kode_grey']) ?></option><?php endif; ?>
                    </select></div>
                </div>
                <?php
                $selectedMachines = array_values(array_filter(array_map('trim', explode(',', $data['mesin'] ?? ''))));
                $machineOptions = ['Paddry 1', 'Paddry 2', 'Paddry 3', 'Paddry 4', 'Paddry 5', 'Paddry 6'];
                ?>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Mesin</label>
                  <div class="col-sm-8">
                    <input type="hidden" name="mesin" id="mesin" value="<?= htmlspecialchars($data['mesin'] ?? '') ?>">
                    <select class="form-control select2-mesin" id="select_mesin" multiple style="width:100%" <?= disAttr('mesin') ?>>
                      <?php foreach ($machineOptions as $machine): ?>
                        <option value="<?= htmlspecialchars($machine) ?>" <?= in_array($machine, $selectedMachines, true) ? 'selected' : '' ?>><?= htmlspecialchars($machine) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Otomatis dari Kode Grey, bisa ditambah jika memakai lebih dari satu mesin.</small>
                  </div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Kode Warna</label>
                  <div class="col-sm-8"><input type="hidden" name="kode_warna" id="kode_warna_hidden"
                      value="<?= htmlspecialchars($data['kode_warna'] ?? '') ?>"><select
                      class="form-control select2-color" id="select_kode_warna" style="width:100%"
                      required <?= disAttr('kode_warna') ?>><?php if (!empty($data['kode_warna'])): ?>
                        <option selected value="<?= htmlspecialchars($data['kode_warna']) ?>">
                          <?= htmlspecialchars($data['kode_warna'] . ' - ' . ($data['color_name'] ?? '')) ?></option>
                      <?php endif; ?>
                    </select></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Color Name</label>
                  <div class="col-sm-8"><input readonly class="form-control" id="color_name" name="color_name"
                      value="<?= htmlspecialchars($data['color_name'] ?? '') ?>"></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Description</label>
                  <div class="col-sm-8"><textarea readonly class="form-control" id="color_desc" name="color_desc"
                      rows="2"><?= htmlspecialchars($data['color_desc'] ?? '') ?></textarea></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">SOI</label>
                  <div class="col-sm-8"><input type="hidden" name="soi" id="soi"
                      value="<?= htmlspecialchars($data['soi'] ?? $group['soi'] ?? '') ?>"><select id="select_soi"
                      class="form-control select2-soi" style="width:100%" <?= disAttr('soi') ?>><?php if (!empty($data['soi'] ?? $group['soi'] ?? '')): ?>
                        <option selected value="<?= htmlspecialchars($data['soi'] ?? $group['soi'] ?? '') ?>">
                          <?= htmlspecialchars($data['soi'] ?? $group['soi'] ?? '') ?></option><?php endif; ?>
                    </select></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">No CP</label>
                  <div class="col-sm-8"><input type="hidden" name="no_cp" id="no_cp"
                      value="<?= htmlspecialchars($data['group_no_cp'] ?? $group['no_cp'] ?? '') ?>"><select
                      id="select_no_cp" class="form-control select2-nocp" style="width:100%" <?= disAttr('no_cp') ?>><?php if (!empty($data['group_no_cp'] ?? $group['no_cp'] ?? '')): ?>
                        <option selected value="<?= htmlspecialchars($data['group_no_cp'] ?? $group['no_cp'] ?? '') ?>">
                          <?= htmlspecialchars($data['group_no_cp'] ?? $group['no_cp'] ?? '') ?></option><?php endif; ?>
                    </select></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Cus Color</label>
                  <div class="col-sm-8"><input class="form-control" id="cus_color" name="cus_color"
                      value="<?= htmlspecialchars($data['cus_color'] ?? '') ?>" <?= roAttr('cus_color') ?>></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group row"><label class="col-sm-4 col-form-label">Prod Code</label>
                  <div class="col-sm-8">
                    <div class="input-group"><input type="text" class="form-control" id="resepprodcode"
                        name="resepprodcode" value="<?= htmlspecialchars($data['resep_prod_code'] ?? '') ?>"
                        placeholder="Klik tombol untuk pilih" <?= roAttr('resep_prod_code') ?>>
                      <div class="input-group-append"><button type="button" class="btn btn-default" id="btnPilihProd"
                          <?= disAttr('resep_prod_code') ?>><i class="fas fa-ellipsis-h"></i></button></div>
                    </div>
                  </div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Prod Name</label>
                  <div class="col-sm-8"><input readonly class="form-control" id="resepprodname" name="resepprodname"
                      value="<?= htmlspecialchars($data['resep_prod_name'] ?? '') ?>"></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Lot No</label>
                  <div class="col-sm-8"><input class="form-control" name="lot_no"
                      value="<?= htmlspecialchars($data['lot_no'] ?? '') ?>" <?= roAttr('lot_no') ?>></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Plan Qty</label>
                  <div class="col-sm-8"><input type="number" step="0.01" class="form-control" name="plan_qty"
                      id="plan_qty" required value="<?= htmlspecialchars($data['plan_qty'] ?? '3500') ?>"
                      <?= roAttr('plan_qty') ?>></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Weight</label>
                  <div class="col-sm-8"><input class="form-control" name="weight"
                      value="<?= htmlspecialchars($data['weight'] ?? '100') ?>" <?= roAttr('weight') ?>></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Vlot</label>
                  <div class="col-sm-8"><input type="number" step="0.01" class="form-control" name="vlot" id="vlot"
                      value="<?= htmlspecialchars($data['vlot'] ?? '') ?>" <?= roAttr('vlot') ?>></div>
                </div>
                <div class="form-group row"><label class="col-sm-4 col-form-label">Catatan</label>
                  <div class="col-sm-8"><textarea class="form-control" name="experiment_note" rows="2"
                      <?= roAttr('catatan') ?>><?= htmlspecialchars($data['experiment_note'] ?? '') ?></textarea></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php
        $lampiran_path = $data['lampiran_path'] ?? '';
        $lampiran_name = $data['lampiran_name'] ?? '';
        ?>
        <div class="card">
          <div class="card-header bg-light">
            <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran</h3>
          </div>
          <div class="card-body">
            <?php if (!$isApproved && !isFieldReadonly('lampiran')): ?>
              <div class="form-group mb-2">
                <label>Upload File <small class="text-muted">(PDF / JPG / PNG, maks 5 MB)</small></label>
                <div class="custom-file" style="max-width:420px">
                  <input type="file" class="custom-file-input" id="lampiranFile" name="lampiran"
                    accept=".pdf,.jpg,.jpeg,.png">
                  <label class="custom-file-label" for="lampiranFile" id="lampiranFileLabel">Pilih file...</label>
                </div>
                <input type="hidden" name="hapus_lampiran" id="hapusLampiranFlag" value="0">
              </div>
            <?php endif; ?>
            <!-- Preview lampiran baru (dipilih tapi belum save) -->
            <div id="lampiranPreviewNew" class="mt-2" style="display:none">
              <p class="mb-1 font-weight-bold text-info"><i class="fas fa-file mr-1"></i> File dipilih:</p>
              <div id="lampiranPreviewImg"></div>
            </div>
            <!-- Lampiran yang sudah tersimpan -->
            <?php if (!empty($lampiran_path)): ?>
              <div id="lampiranSavedBox" class="mt-2">
                <p class="mb-1 font-weight-bold"><i class="fas fa-file-alt mr-1"></i> Lampiran tersimpan:</p>
                <?php
                $ext = strtolower(pathinfo($lampiran_name, PATHINFO_EXTENSION));
                $url = '/gg_app/pages/resep_obat/experiment/' . htmlspecialchars($lampiran_path);
                ?>
                <?php if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                  <a href="<?= $url ?>" target="_blank"><img src="<?= $url ?>"
                      style="max-height:120px;border:1px solid #dee2e6;border-radius:4px" class="mb-1"></a>
                <?php else: ?>
                  <a href="<?= $url ?>" target="_blank" class="btn btn-outline-danger btn-sm"><i
                      class="fas fa-file-pdf mr-1"></i><?= htmlspecialchars($lampiran_name) ?></a>
                <?php endif; ?>
                <?php if (!$isApproved && !isFieldReadonly('lampiran')): ?>
                  <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnGantiLampiran"><i
                        class="fas fa-exchange-alt mr-1"></i>Ganti</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnHapusLampiran"><i
                        class="fas fa-trash mr-1"></i>Hapus</button>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div id="lampiranSavedBox" style="display:none"></div>
            <?php endif; ?>
            <p id="noLampiranMsg" class="text-muted small mt-1" <?= !empty($lampiran_path) ? 'style="display:none"' : '' ?>>Belum ada lampiran.</p>
          </div>
        </div>
        <div class="card">
          <div class="card-header bg-light p-0">
            <ul class="nav nav-tabs card-header-tabs" id="resepDetailTab" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" id="tab-detail-link" data-toggle="tab" href="#tab-detail" role="tab"
                  aria-controls="tab-detail" aria-selected="true">
                  <i class="fas fa-list mr-1"></i>Detail Resep Manual
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-mesin-link" data-toggle="tab" href="#tab-mesin" role="tab"
                  aria-controls="tab-mesin" aria-selected="false">
                  <i class="fas fa-cogs mr-1"></i>Mesin Lab
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-lab-link" data-toggle="tab" href="#tab-lab" role="tab"
                  aria-controls="tab-lab" aria-selected="false">
                  <i class="fas fa-flask mr-1"></i>Data Lab
                </a>
              </li>
            </ul>
          </div>
          <div class="card-body p-0">
            <div class="tab-content">
              <!-- TAB 1: Detail Resep Manual (existing) -->
              <div class="tab-pane fade show active" id="tab-detail" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                  <h5 class="m-0"><i class="fas fa-table mr-1"></i>Daftar Item Resep</h5>
                  <div><?php if (!$isApproved && !$detailRO): ?><button type="button" class="btn btn-primary btn-sm"
                        id="btnAddRow"><i class="fas fa-plus"></i> Tambah Item</button><?php endif; ?></div>
                </div>
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="detailTable">
                    <thead>
                      <tr>
                        <th>Kode</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Uom</th>
                        <th>Cf</th>
                        <th>Uom Cf</th><?php if ($canSeePrice): ?>
                          <th>Price</th>
                          <th>Total</th><?php endif; ?>
                        <?php if (!$detailRO): ?><th>Aksi</th><?php endif; ?>
                      </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot><?php if ($canSeePrice): ?>
                        <tr>
                          <td colspan="<?= $detailRO ? 7 : 8 ?>" class="text-right font-weight-bold">Grand Total</td>
                          <td colspan="2"><input readonly class="form-control-plaintext font-weight-bold text-right"
                              id="grandVal" value="Rp 0"></td>
                        </tr><?php else: ?>
                        <tr>
                          <td colspan="<?= $detailRO ? 7 : 8 ?>" class="text-right font-weight-bold">&nbsp;</td>
                        </tr><?php endif; ?>
                    </tfoot>
                  </table>
                </div>
              </div>
              <!-- TAB 2: Mesin Lab -->
              <div class="tab-pane fade" id="tab-mesin" role="tabpanel">
                <div class="p-3">
                  <div class="form-group row"><label class="col-sm-3 col-form-label">Pilih Mesin</label>
                    <div class="col-sm-9">
                      <input type="hidden" id="lab_machine_code_hidden" name="lab_machine_code"
                        value="<?= htmlspecialchars($labParam['machine_code'] ?? '') ?>">
                      <select class="form-control select2-mesin-lab" id="lab_machine_code"
                        style="width:100%" <?= disAttr('lab_machine') ?>>
                        <?php if (!empty($labParam['machine_code'])): ?>
                          <option selected value="<?= htmlspecialchars($labParam['machine_code']) ?>"
                            data-machine_name="<?= htmlspecialchars($labParam['machine_name']) ?>">
                            <?= htmlspecialchars($labParam['machine_code'] . ' - ' . $labParam['machine_name']) ?></option>
                        <?php endif; ?>
                      </select>
                      <input type="hidden" id="lab_machine_name" name="lab_machine_name"
                        value="<?= htmlspecialchars($labParam['machine_name'] ?? '') ?>">
                    </div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Infra Red</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_infra_red"
                            name="lab_infra_red" value="<?= nf2($labParam['infra_red'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Tekanan Padder</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_tekanan_padder"
                            name="lab_tekanan_padder" value="<?= nf2($labParam['tekanan_padder'] ?? 0) ?>" <?= roAttr('lab_param') ?>>
                        </div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">WPU</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_wpu"
                            name="lab_wpu" value="<?= nf2($labParam['wpu'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Speed</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_speed"
                            name="lab_speed" value="<?= nf2($labParam['speed'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Fan 1</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_fan1"
                            name="lab_fan1" value="<?= nf2($labParam['fan1'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Fan 2</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_fan2"
                            name="lab_fan2" value="<?= nf2($labParam['fan2'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Temp Chamber 1</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_temp_chamber_1"
                            name="lab_temp_chamber_1" value="<?= nf2($labParam['temp_chamber_1'] ?? 0) ?>" <?= roAttr('lab_param') ?>>
                        </div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Waktu Chamber 1 (detik)</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param"
                            id="lab_temp_chamber_1_time" name="lab_temp_chamber_1_time"
                            value="<?= nf2($labParam['temp_chamber_1_time'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Temp Chamber 2</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param" id="lab_temp_chamber_2"
                            name="lab_temp_chamber_2" value="<?= nf2($labParam['temp_chamber_2'] ?? 0) ?>" <?= roAttr('lab_param') ?>>
                        </div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Waktu Chamber 2 (detik)</label>
                        <div class="col-sm-7"><input type="text" class="form-control lab-param"
                            id="lab_temp_chamber_2_time" name="lab_temp_chamber_2_time"
                            value="<?= nf2($labParam['temp_chamber_2_time'] ?? 0) ?>" <?= roAttr('lab_param') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-5 col-form-label">Lainnya</label>
                        <div class="col-sm-7"><textarea class="form-control lab-param" id="lab_lainnya"
                            name="lab_lainnya" rows="3" <?= roAttr('lab_param') ?>><?= htmlspecialchars($labParam['lainnya'] ?? '') ?></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                  <hr>
                  <div class="text-right text-muted small"><i class="fas fa-info-circle"></i> Data parameter mesin akan
                    tersimpan otomatis saat klik <b>Simpan Experiment</b></div>
                </div>
              </div>
              <!-- TAB 3: Data Lab -->
              <div class="tab-pane fade" id="tab-lab" role="tabpanel">
                <div class="p-3">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group row"><label class="col-sm-4 col-form-label">Delta E</label>
                        <div class="col-sm-8"><input type="text" class="form-control lab-data" id="lab_delta_e"
                            name="lab_delta_e" value="<?= nf2($labData['delta_e'] ?? 0) ?>" <?= roAttr('lab_data') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-4 col-form-label">Delta L</label>
                        <div class="col-sm-8"><input type="text" class="form-control lab-data" id="lab_delta_l"
                            name="lab_delta_l" value="<?= nf2($labData['delta_l'] ?? 0) ?>" <?= roAttr('lab_data') ?>></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group row"><label class="col-sm-4 col-form-label">Delta A</label>
                        <div class="col-sm-8"><input type="text" class="form-control lab-data" id="lab_delta_a"
                            name="lab_delta_a" value="<?= nf2($labData['delta_a'] ?? 0) ?>" <?= roAttr('lab_data') ?>></div>
                      </div>
                      <div class="form-group row"><label class="col-sm-4 col-form-label">Delta B</label>
                        <div class="col-sm-8"><input type="text" class="form-control lab-data" id="lab_delta_b"
                            name="lab_delta_b" value="<?= nf2($labData['delta_b'] ?? 0) ?>" <?= roAttr('lab_data') ?>></div>
                      </div>
                    </div>
                  </div>
                  <hr>
                  <div class="text-right text-muted small"><i class="fas fa-info-circle"></i> Data lab akan tersimpan
                    otomatis saat klik <b>Simpan Experiment</b></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row mb-4">
          <div class="col-12"><?php if (!$isApproved): ?><button type="submit" class="btn btn-success float-right"
                id="btnSave"><i class="fas fa-save"></i> Simpan Experiment</button><?php endif; ?><a
              href="<?= !empty($data['group_id']) ? 'view_group.php?group_id=' . (int) $data['group_id'] : 'list_experiment.php' ?>"
              class="btn btn-secondary float-right mr-2">Kembali</a></div>
        </div>
      </form>
    </div>
  </section>
  <div class="modal fade" id="modalPilihProd" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h5 class="modal-title">Pilih Resep Prod Code</h5><button type="button" class="close text-white"
            data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table id="prodTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th>Prod Code</th>
                  <th>Prod Name</th>
                  <th>Cus Color</th>
                  <th>Prod Type</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="modalPilihCp" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h5 class="modal-title">Pilih No CP</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <table id="cpTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
            <thead class="thead-light">
              <tr><th>No CP</th><th>Nama Produk</th><th>Cus Color</th><th>Aksi</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div><?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    const isApproved = <?= $isApproved ? 'true' : 'false' ?>; const detailRO = <?= $detailRO ? 'true' : 'false' ?>; const dKodeRO = <?= $detailKodeRO ? 'true' : 'false' ?>; const dQtyRO = <?= $detailQtyRO ? 'true' : 'false' ?>; const dCfRO = <?= $detailCfRO ? 'true' : 'false' ?>; const dUomCfRO = <?= $detailUomCfRO ? 'true' : 'false' ?>; const canSeePrice = <?= $canSeePrice ? 'true' : 'false' ?>; const isNewGroup = <?= (!$resep_id && !$next_group_id) ? 'true' : 'false' ?>; let rowIdx = 0, isSubmitting = false, currentColorMsid = ''; function fmtNum(n) { return 'Rp ' + (parseFloat(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); } function parseNum(s) { return parseFloat((s || '').replace(/[Rp\s.]/g, '').replace(',', '.')) || 0; } function fmtUS(n) { return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 }); } function escapeHtml(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    const _prevColorId = $('.select2-color').val();
    $('.select2-color').select2({ theme: 'bootstrap4', ajax: { url: 'get_colors.php', dataType: 'json', delay: 250, data: p => ({ q: p.term || '', cus_color: $('#cus_color').val() || '' }), processResults: d => ({ results: d.results }) }, placeholder: 'Cari Kode Warna', minimumInputLength: 0 }).on('select2:select', function (e) { $('#kode_warna_hidden').val(e.params.data.id); let d = e.params.data.color_data || {}; currentColorMsid = d.colormsid || ''; $('#color_name').val(d.name || ''); $('#color_desc').val(d.desc || ''); $('#resepprodcode,#resepprodname,#proint_resephdid').val(''); /* Auto-fill cus_color from standard smprodtechdata.cuscolor */ if (d.cus_color) { $('#cus_color').val(d.cus_color); } /* Clear SOI when color changes (re-filter needed) */ if (_prevColorId !== null && _prevColorId !== e.params.data.id) { let $selSOI = $('.select2-soi'); if ($selSOI.val()) { $selSOI.val(null).trigger('change'); $('#soi').val(''); } } if (isNewGroup) { $.getJSON('check_kode_warna.php', { kode_warna: e.params.data.id }, function (resp) { if (resp.exists) { let g = resp.group || {}; Swal.fire({ icon: 'warning', title: 'Kode Warna Sudah Ada', html: `Kode warna <b>${escapeHtml(g.kode_warna || e.params.data.id)}</b> sudah pernah dibuat pada group experiment.<br><br><div class="text-left"><b>Color Name:</b> ${escapeHtml(g.color_name || '-')}<br><b>SOI:</b> ${escapeHtml(g.soi || '-')}<br><b>No CP:</b> ${escapeHtml(g.no_cp || '-')}<br><b>Status Group:</b> ${escapeHtml(g.group_status || '-')}<br><b>Created By:</b> ${escapeHtml(g.created_by || '-')}</div>`, confirmButtonText: 'OK' }); } }); } if (prodTable) prodTable.ajax.reload(); });
    /* When cus_color is typed/changed: clear kode_warna, clear SOI, reset related fields */
    let _cusColorTimer = null; $('#cus_color').on('input', function () { clearTimeout(_cusColorTimer); let v = $(this).val().trim(); _cusColorTimer = setTimeout(function () { /* Only clear SOI when cus_color changes, preserve kode_warna/color_name/color_desc */ let $selSOI = $('.select2-soi'); if ($selSOI.val()) { $selSOI.val(null).trigger('change'); $('#soi').val(''); } if (prodTable) prodTable.ajax.reload(); }, 400); });
    let prodTable = null; function initProdTable() { if (prodTable) return; prodTable = $('#prodTable').DataTable({ processing: true, serverSide: true, responsive: true, ajax: { url: 'serverside_resep_prod.php', type: 'POST', data: d => { d.colormsid = currentColorMsid; } }, columns: [{ data: 'resepprodcode' }, { data: 'resepprodname' }, { data: 'cuscolor' }, { data: 'prodtypecode' }, { data: null, orderable: false, searchable: false, className: 'text-center', render: (d, t, row) => `<button type="button" class="btn btn-primary btn-sm btn-select-prod" data-id="${row.resephdid}" data-code="${escapeHtml(row.resepprodcode)}" data-name="${escapeHtml(row.resepprodname)}" data-cus="${escapeHtml(row.cuscolor)}"><i class="fas fa-check"></i> Pilih</button>` }], language: { processing: 'Sedang memproses...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada Resep Prod Code ditemukan', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } } }); }
    const rpcRO = <?= isFieldReadonly('resep_prod_code') ? 'true' : 'false' ?>; $('#btnPilihProd,#resepprodcode').on('click', function () { if (isApproved || rpcRO) return; if (!currentColorMsid) { Swal.fire('Pilih Kode Warna', 'Silakan pilih Kode Warna terlebih dahulu.', 'warning'); return; } initProdTable(); $('#modalPilihProd').modal('show'); prodTable.ajax.reload(); }); $(document).on('click', '.btn-select-prod', function () { $('#proint_resephdid').val($(this).data('id') || ''); $('#resepprodcode').val($(this).data('code') || ''); $('#resepprodname').val($(this).data('name') || ''); $('#cus_color').val($(this).data('cus') || ''); $('#modalPilihProd').modal('hide'); });
    $('.select2-mesin').select2({ theme: 'bootstrap4', placeholder: 'Pilih mesin...', allowClear: true }).on('change', syncMesinValue);
    function normalizePaddry(value) { const raw = String(value || '').trim(); if (!raw || raw === '-') return ''; return raw.toUpperCase().includes('PAD') ? raw : `Paddry ${raw}`; }
    function syncMesinValue() { $('#mesin').val(($('#select_mesin').val() || []).join(', ')); }
    syncMesinValue();
    $('.select2-grey').select2({ theme: 'bootstrap4', ajax: { url: 'get_grey_items.php', dataType: 'json', delay: 250, data: p => ({ q: p.term }), processResults: d => ({ results: d.results }) }, placeholder: 'Cari Kode Grey', minimumInputLength: 1 }).on('select2:select', function (e) { let d = e.params.data.item_data || {}; $('#kode_grey_hidden').val(d.kode_gray || ''); const mesinDefault = normalizePaddry(d.padry); $('#select_mesin').val(mesinDefault ? [mesinDefault] : []).trigger('change'); let g = parseFloat(d.gramasi) || 0, p = parseFloat(d.pickup) || 0; $('input[name="weight"]').data('gramasi', g).data('pickup', p); calcWeight(); });

    /* --- Open No CP modal on Select2 click (multi-result) --- */
    $('#btnPilihCp,#select_no_cp').on('click', function () {
      if (isApproved) return;
      var soi = $('#soi').val();
      if (!soi) { Swal.fire('Pilih SOI', 'Silakan pilih SOI terlebih dahulu.', 'warning'); return; }
      $.getJSON('get_no_cp.php', { soi: soi, cus_color: $('#cus_color').val() || '' }, function (resp) {
        var results = resp.results || [];
        if (results.length === 0) {
          Swal.fire('Info', 'Tidak ditemukan No CP untuk SOI ini.', 'info');
        } else if (results.length === 1) {
          var opt = new Option(results[0].text, results[0].id, true, true);
          $('#select_no_cp').append(opt).trigger('change');
          $('#no_cp').val(results[0].id);
          $('#resepprodcode').val(results[0].prodcode || '');
          $('#resepprodname').val(results[0].prdname || '');
          if (results[0].cus_color && !$('#cus_color').val()) {
            $('#cus_color').val(results[0].cus_color);
          }
        } else {
          initCpTable();
          cpTable.clear().rows.add(results).draw();
          $('#modalPilihCp').modal('show');
        }
      });
    });

    /* --- No CP DataTable modal --- */
    let cpTable = null;
    function initCpTable() {
      if (cpTable) return;
      cpTable = $('#cpTable').DataTable({
        data: [],
        columns: [
          { data: 'id' },
          { data: 'prdname', render: function (d) { return escapeHtml(d || '-'); } },
          { data: 'cus_color', render: function (d) { return '<span class="badge badge-info">' + escapeHtml(d || '-') + '</span>'; } },
          { data: null, orderable: false, searchable: false, className: 'text-center',
            render: function (d, t, row) {
              return '<button type="button" class="btn btn-primary btn-sm btn-select-cp" data-id="' + escapeHtml(row.id) + '" data-prdname="' + escapeHtml(row.prdname || '') + '" data-prodcode="' + escapeHtml(row.prodcode || '') + '" data-cuscolor="' + escapeHtml(row.cus_color || '') + '"><i class="fas fa-check"></i> Pilih</button>';
            }
          }
        ],
        language: {
          search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
          zeroRecords: 'Tidak ada No CP ditemukan',
          info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
          infoEmpty: 'Tidak ada data',
          paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' }
        }
      });
    }
    $(document).on('click', '.btn-select-cp', function () {
      var id = $(this).data('id');
      var opt = new Option(id, id, true, true);
      $('#select_no_cp').append(opt).trigger('change');
      $('#no_cp').val(id);
      $('#resepprodcode').val($(this).data('prodcode') || '');
      $('#resepprodname').val($(this).data('prdname') || '');
      if ($(this).data('cuscolor') && !$('#cus_color').val()) {
        $('#cus_color').val($(this).data('cuscolor'));
      }
      $('#modalPilihCp').modal('hide');
    });

    /* --- SOI Select2 --- */
    if (!isApproved) {
      $('.select2-soi').select2({
        theme: 'bootstrap4',
        ajax: { url: 'get_soi.php', dataType: 'json', delay: 250, data: p => ({ q: p.term || '', cus_color: $('#cus_color').val() || '' }), processResults: d => ({ results: d.results }) },
        placeholder: 'Cari SOI',
        minimumInputLength: 0,
        allowClear: true
      }).on('select2:select', function (e) {
        var selectedSOI = e.params.data.id;
        var selectedCusColor = e.params.data.cus_color || '';
        $('#soi').val(selectedSOI);
        /* Auto-fill cus_color from SOI response if empty */
        if (selectedCusColor && !$('#cus_color').val()) {
          $('#cus_color').val(selectedCusColor);
        }
        /* Clear current No CP */
        $('#select_no_cp').val(null).trigger('change');
        $('#no_cp').val('');
        /* Fetch No CP options filtered by cus_color */
        $.getJSON('get_no_cp.php', { soi: selectedSOI, cus_color: $('#cus_color').val() || '' }, function (resp) {
          var results = resp.results || [];
          if (results.length === 0) {
            Swal.fire('Info', 'Tidak ditemukan No CP untuk SOI ini.', 'info');
          } else if (results.length === 1) {
            var opt = new Option(results[0].text, results[0].id, true, true);
            $('#select_no_cp').append(opt).trigger('change');
            $('#no_cp').val(results[0].id);
            $('#resepprodcode').val(results[0].prodcode || '');
            $('#resepprodname').val(results[0].prdname || '');
            if (results[0].cus_color && !$('#cus_color').val()) {
              $('#cus_color').val(results[0].cus_color);
            }
          } else {
            /* Show Bootstrap modal with DataTable */
            initCpTable();
            cpTable.clear().rows.add(results).draw();
            $('#modalPilihCp').modal('show');
          }
        });
      }).on('select2:clear', function () {
        $('#soi').val('');
        $('#select_no_cp').val(null).trigger('change');
        $('#no_cp').val('');
        $('#resepprodcode').val('');
        $('#resepprodname').val('');
      });
    }

    /* --- No CP Select2 (editable dropdown) --- */
    if (!isApproved) {
      $('.select2-nocp').select2({
        theme: 'bootstrap4',
        ajax: {
          url: 'get_no_cp.php', dataType: 'json', delay: 250,
          data: function (p) {
            return { q: p.term || '', soi: $('#soi').val() || '', cus_color: $('#cus_color').val() || '' };
          },
          processResults: function (d) { return { results: d.results }; }
        },
        placeholder: 'Cari No CP',
        minimumInputLength: 0,
        allowClear: true
      }).on('select2:select', function (e) {
        /* Store prodcode/prdname/cuscolor from response */
        var d = e.params.data;
        $('#no_cp').val(d.id);
        if (d.prodcode !== undefined) $('#resepprodcode').val(d.prodcode || '');
        if (d.prdname !== undefined) $('#resepprodname').val(d.prdname || '');
        /* Auto-fill cus_color from No CP if empty */
        if (d.cus_color && !$('#cus_color').val()) {
          $('#cus_color').val(d.cus_color);
        }
      }).on('select2:clear', function () {
        $('#no_cp').val('');
        $('#resepprodcode').val('');
        $('#resepprodname').val('');
      });
    }
    /* Sync initial values if existing */
    if ($('#soi').val()) $('#select_soi').val($('#soi').val()).trigger('change');
    if ($('#no_cp').val()) $('#select_no_cp').val($('#no_cp').val()).trigger('change');
    function calcWeight() { let g = parseFloat($('input[name="weight"]').data('gramasi')) || 0, q = parseFloat($('#plan_qty').val()) || 0; if (g > 0) { $('input[name="weight"]').val(((g * q) / 1000).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })); calcVlot(); } } function calcVlot() { let w = parseFloat(($('input[name="weight"]').val() || '').replace(/,/g, '')) || 0, p = parseFloat($('input[name="weight"]').data('pickup')) || 0; if (p > 0) $('#vlot').val(Math.ceil((w * p) / 10) * 10).trigger('input'); } $('#plan_qty').on('input', calcWeight); $('input[name="weight"]').on('input', calcVlot);
    function addRow(data = {}) { rowIdx++; let ro = isApproved || detailRO ? 'readonly' : ''; let dis = isApproved || detailRO ? 'disabled' : ''; let roKode = isApproved || dKodeRO ? 'disabled' : ''; let roQty = isApproved || dQtyRO ? 'readonly' : ''; let roCf = isApproved || dCfRO ? 'readonly' : ''; let roUomCf = isApproved || dUomCfRO ? 'readonly' : ''; let priceCell = canSeePrice ? `<td><input readonly class="form-control form-control-sm item-price" name="items[${rowIdx}][std_price]" value="${data.std_price ? fmtNum(data.std_price) : ''}"><input type="hidden" name="items[${rowIdx}][price_satuan]" class="item-price-satuan" value="${data.price_satuan || ''}"><input type="hidden" name="items[${rowIdx}][price_source]" class="item-price-source" value="${data.price_source || ''}"><small class="text-danger price-source"></small></td><td><input readonly class="form-control form-control-sm row-total" value="Rp 0"></td>` : `<td style="display:none"><input type="hidden" name="items[${rowIdx}][std_price]" class="item-price" value="${data.std_price ? fmtNum(data.std_price) : ''}"><input type="hidden" name="items[${rowIdx}][price_satuan]" class="item-price-satuan" value="${data.price_satuan || ''}"><input type="hidden" name="items[${rowIdx}][price_source]" class="item-price-source" value="${data.price_source || ''}"><input type="hidden" class="row-total" value="0"></td>`; let aksiCell = !detailRO && !isApproved ? `<td class="text-center"><button type="button" class="btn btn-danger btn-xs btn-remove"><i class="fas fa-trash"></i></button></td>` : ''; let html = `<tr id="row_${rowIdx}"><td><input type="hidden" name="items[${rowIdx}][master_obat_id]" class="item-master-obat-id" value="${data.master_obat_id || ''}"><input type="hidden" name="items[${rowIdx}][codeprod_proint]" class="item-codeprod-proint" value="${data.codeprod_proint || ''}"><input type="hidden" name="items[${rowIdx}][kode]" class="item-kode" value="${data.kode || ''}"><select class="form-control select2-item" style="width:100%" ${roKode}>${data.kode ? `<option selected value="${data.kode}">${data.kode} - ${data.name || ''}</option>` : ''}</select><input type="hidden" name="items[${rowIdx}][is_manual]" value="1"></td><td><input readonly class="form-control form-control-sm item-name" name="items[${rowIdx}][name]" value="${data.name || ''}"></td><td><input readonly class="form-control form-control-sm item-category" name="items[${rowIdx}][category]" value="${data.category || ''}"></td><td><input class="form-control form-control-sm item-qty" name="items[${rowIdx}][receipe]" value="${data.receipe ? fmtUS(data.receipe) : ''}" ${roQty}></td><td><input readonly class="form-control form-control-sm item-uom" name="items[${rowIdx}][uom]" value="${data.uom || ''}"></td><td><input class="form-control form-control-sm item-cf" name="items[${rowIdx}][cf]" value="${data.cf ? fmtUS(data.cf) : ''}" ${roCf}></td><td><input class="form-control form-control-sm item-uom-cf" name="items[${rowIdx}][uom_cf]" value="${data.uom_cf || ''}" ${roUomCf}></td>${priceCell}${aksiCell}</tr>`; $('#detailTable tbody').append(html); let $s = $(`#row_${rowIdx} .select2-item`).select2({ theme: 'bootstrap4', ajax: { url: 'get_items.php', dataType: 'json', delay: 250, data: p => ({ q: p.term }), processResults: d => ({ results: d.results }) }, placeholder: 'Cari Kode / Nama', minimumInputLength: 1 }); $s.on('select2:select', function (e) { let it = e.params.data.item_data, $r = $(this).closest('tr'); $r.find('.item-kode').val(e.params.data.id); $r.find('.item-master-obat-id').val(it.master_obat_id || ''); $r.find('.item-codeprod-proint').val(it.codeprod_proint || it.codeprod || ''); $r.find('.item-name').val(it.name); $r.find('.item-category').val(it.group_obat); $r.find('.item-uom').val(it.uom); $r.find('.item-uom-cf').val(it.uom_raw); if (it.codeprod) { $.getJSON('get_item_price.php', { prodcode: it.codeprod }, function (resp) { $r.find('.item-price').val(fmtNum(resp.price || 0)); $r.find('.item-price-satuan').val(resp.satuan || ''); $r.find('.item-price-source').val(resp.source || ''); if (canSeePrice) calcRow($r); }); } if (canSeePrice) calcRow($r); }); if (canSeePrice) calcRow($('#row_' + rowIdx)); }
    function calcRow($r) { let qty = parseFloat(($r.find('.item-qty').val() || '').replace(/,/g, '')) || 0, price = parseNum($r.find('.item-price').val()), total = qty * price, uom = ($r.find('.item-uom').val() || '').toUpperCase(); if (uom === 'GR' || uom === 'G/L') total /= 1000; $r.find('.row-total').val(fmtNum(total)); calcAll(); } function calcAll() { let gt = 0; $('.row-total').each(function () { gt += parseNum($(this).val()); }); $('#grandVal').val(fmtNum(gt)); } function calcRowQty($r) { let v = parseFloat($('#vlot').val()) || 0, cf = parseFloat(($r.find('.item-cf').val() || '').replace(/,/g, '')) || 0; $r.find('.item-qty').val(fmtUS(v * cf)); calcRow($r); } $(document).on('input', '.item-qty', function () { calcRow($(this).closest('tr')); }); $(document).on('input', '.item-cf,#vlot', function () { if (this.id === 'vlot') $('#detailTable tbody tr').each(function () { calcRowQty($(this)); }); else calcRowQty($(this).closest('tr')); }); $(document).on('click', '.btn-remove', function () { $(this).closest('tr').remove(); calcAll(); }); $('#btnAddRow').click(() => addRow());
<?php if ($details): ?>let saved = <?= json_encode($details) ?>; saved.forEach(d => addRow(d));<?php else: ?>for (let i = 0; i < 5; i++)addRow(); <?php endif; ?>
    $('#resepForm').on('submit', function (e) {
      e.preventDefault(); if (isSubmitting || isApproved) return; isSubmitting = true; $('#btnSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...'); $.ajax({ url: 'save_resep.php', type: 'POST', data: new FormData(this), contentType: false, processData: false, dataType: 'json' }).done(function (r) { if (r.status === 'success') { var msg = 'Data berhasil disimpan.'; if (r.auto_process_message) msg += '<br><small class="text-info"><i class="fas fa-info-circle"></i> ' + escapeHtml(r.auto_process_message) + '</small>'; if (r.lampiran_warning) msg += '<br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(r.lampiran_warning) + '</small>'; Swal.fire({ icon: 'success', title: 'Sukses', html: msg }).then(() => location.href = 'view_group.php?group_id=' + r.group_id); } else { isSubmitting = false; $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Experiment'); Swal.fire('Gagal', r.message || 'Gagal menyimpan.', 'error'); } }).fail(function (x) { isSubmitting = false; $('#btnSave').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Experiment'); Swal.fire('Error', 'Gagal menyimpan data.', 'error'); console.error(x.responseText); });
    });
    /* --- Lampiran UI --- */
    $('#lampiranFile').on('change', function () {
      var file = this.files[0];
      if (!file) { $('#lampiranPreviewNew').hide(); $('#lampiranFileLabel').text('Pilih file...'); return; }
      $('#lampiranFileLabel').text(file.name);
      var ext = file.name.split('.').pop().toLowerCase();
      var $prev = $('#lampiranPreviewImg').empty();
      if (['jpg', 'jpeg', 'png'].includes(ext)) {
        var reader = new FileReader();
        reader.onload = function (e) {
          $prev.html('<img src="' + e.target.result + '" style="max-height:120px;border:1px solid #dee2e6;border-radius:4px">');
        };
        reader.readAsDataURL(file);
      } else {
        $prev.html('<span class="text-danger"><i class="fas fa-file-pdf fa-2x"></i></span> <span>' + escapeHtml(file.name) + '</span>');
      }
      $('#lampiranPreviewNew').show();
      /* Reset hapus flag jika user pilih file baru */
      $('#hapusLampiranFlag').val('0');
    });
    $('#btnHapusLampiran').on('click', function () {
      Swal.fire({ title: 'Hapus Lampiran?', text: 'Lampiran akan dihapus saat Simpan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }).then(function (r) {
        if (r.isConfirmed) {
          $('#hapusLampiranFlag').val('1');
          $('#lampiranSavedBox').hide();
          $('#noLampiranMsg').show();
        }
      });
    });
    $('#btnGantiLampiran').on('click', function () {
      $('#lampiranFile').trigger('click');
    });
    (function initColorMsidFromExisting() { const existingCode = $('.select2-color').val(); if (!existingCode) return; $.getJSON('get_colors.php', { q: existingCode, cus_color: $('#cus_color').val() || '' }, function (resp) { const match = (resp.results || []).find(r => String(r.id) === String(existingCode)); if (match && match.color_data) { currentColorMsid = match.color_data.colormsid || ''; if (!$('#color_name').val()) $('#color_name').val(match.color_data.name || ''); if (!$('#color_desc').val()) $('#color_desc').val(match.color_data.desc || ''); } }); })();

    /* ============== LAB TAB HANDLERS ============== */
    const RESEP_ID = <?= (int) ($resep_id ?? 0) ?>;

    // Init Select2 for Mesin Lab
    if ($('#lab_machine_code').length) {
      $('#lab_machine_code').select2({
        theme: 'bootstrap4',
        placeholder: 'Cari Kode / Nama Mesin',
        allowClear: true,
        ajax: {
          url: 'get_master_mesin.php',
          dataType: 'json',
          delay: 250,
          data: p => ({ q: p.term || '', only_active: '1' }),
          processResults: d => ({ results: d.results || [] })
        }
      }).on('select2:select', function (e) {
        const d = e.params.data.machine_data || {};
        $('#lab_machine_code_hidden').val(e.params.data.id || '');
        $('#lab_machine_name').val(d.nama_mesin || '');
        // Auto-fill all parameters from machine master
        $('#lab_infra_red').val(d.infra_red || '');
        $('#lab_tekanan_padder').val(d.tekanan_padder || '');
        $('#lab_wpu').val(d.wpu || '');
        $('#lab_speed').val(d.speed || '');
        $('#lab_fan1').val(d.fan1 || '');
        $('#lab_fan2').val(d.fan2 || '');
        $('#lab_temp_chamber_1').val(d.temp_chamber_1 || '');
        $('#lab_temp_chamber_1_time').val(d.temp_chamber_1_time || '');
        $('#lab_temp_chamber_2').val(d.temp_chamber_2 || '');
        $('#lab_temp_chamber_2_time').val(d.temp_chamber_2_time || '');
      }).on('select2:clear', function () {
        $('#lab_machine_code_hidden').val('');
        $('#lab_machine_name').val('');
      });
    }

    // Save Lab Parameter (server expects $_POST with key 'resep_id')
    $('#btnSaveLabParam').on('click', function () {
      if (!RESEP_ID) {
        Swal.fire({ icon: 'warning', title: 'Belum Disimpan', text: 'Simpan experiment terlebih dahulu.' });
        return;
      }
      const payload = {
        resep_id: RESEP_ID,
        machine_code: $('#lab_machine_code').val() || '',
        machine_name: $('#lab_machine_name').val() || '',
        infra_red: $('#lab_infra_red').val() || '',
        tekanan_padder: $('#lab_tekanan_padder').val() || '',
        wpu: $('#lab_wpu').val() || '',
        speed: $('#lab_speed').val() || '',
        fan1: $('#lab_fan1').val() || '',
        fan2: $('#lab_fan2').val() || '',
        temp_chamber_1: $('#lab_temp_chamber_1').val() || '',
        temp_chamber_1_time: $('#lab_temp_chamber_1_time').val() || '',
        temp_chamber_2: $('#lab_temp_chamber_2').val() || '',
        temp_chamber_2_time: $('#lab_temp_chamber_2_time').val() || '',
        lainnya: $('#lab_lainnya').val() || ''
      };
      const $btn = $(this).prop('disabled', true);
      $('#labParamMsg').html('<i class="fas fa-spinner fa-spin text-muted"></i> Menyimpan...');
      $.ajax({
        url: 'save_lab_param.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function (resp) {
          const ok = resp && (resp.status === 'ok' || resp.status === 'success');
          if (ok) {
            $('#labParamMsg').html('<span class="text-success"><i class="fas fa-check"></i> Tersimpan</span>');
            Swal.fire({ icon: 'success', title: 'Tersimpan', text: 'Parameter mesin lab berhasil disimpan.', timer: 1500, showConfirmButton: false });
          } else {
            $('#labParamMsg').html('<span class="text-danger"><i class="fas fa-times"></i> Gagal</span>');
            Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) || 'Gagal menyimpan parameter.' });
          }
        },
        error: function (xhr) {
          $('#labParamMsg').html('<span class="text-danger"><i class="fas fa-times"></i> Error</span>');
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseText || 'Server error' });
        },
        complete: function () { $btn.prop('disabled', false); }
      });
    });

    // Save Lab Data (Delta L/A/B/E) (server expects $_POST with key 'resep_id')
    $('#btnSaveLabData').on('click', function () {
      if (!RESEP_ID) {
        Swal.fire({ icon: 'warning', title: 'Belum Disimpan', text: 'Simpan experiment terlebih dahulu.' });
        return;
      }
      const payload = {
        resep_id: RESEP_ID,
        delta_l: $('#lab_delta_l').val() || '',
        delta_a: $('#lab_delta_a').val() || '',
        delta_b: $('#lab_delta_b').val() || '',
        delta_e: $('#lab_delta_e').val() || ''
      };
      const $btn = $(this).prop('disabled', true);
      $('#labDataMsg').html('<i class="fas fa-spinner fa-spin text-muted"></i> Menyimpan...');
      $.ajax({
        url: 'save_lab_data.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function (resp) {
          const ok = resp && (resp.status === 'ok' || resp.status === 'success');
          if (ok) {
            $('#labDataMsg').html('<span class="text-success"><i class="fas fa-check"></i> Tersimpan</span>');
            Swal.fire({ icon: 'success', title: 'Tersimpan', text: 'Data lab berhasil disimpan.', timer: 1500, showConfirmButton: false });
          } else {
            $('#labDataMsg').html('<span class="text-danger"><i class="fas fa-times"></i> Gagal</span>');
            Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) || 'Gagal menyimpan data lab.' });
          }
        },
        error: function (xhr) {
          $('#labDataMsg').html('<span class="text-danger"><i class="fas fa-times"></i> Error</span>');
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseText || 'Server error' });
        },
        complete: function () { $btn.prop('disabled', false); }
      });
    });

    /* ============== LAB TAB: auto-save via main form (no separate buttons) ============== */
    // Lab fields now have name="lab_*" so they are automatically included in FormData
    // when the main form is submitted. No need for separate save buttons.
  });
</script>