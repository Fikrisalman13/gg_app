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
$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = 'Anda tidak memiliki hak akses.';
  header('Location: ../../dashboard.php');
  exit;
}
$resep_id = $_GET['resep_id'] ?? null;
if (!$resep_id)
  die('ID Missing');
$stmt = sqlsrv_query($conn, "SELECT e.*, g.soi AS group_soi, g.no_cp AS group_no_cp, g.group_status, g.approved_experiment_id, g.kode_grey AS g_kode_grey, g.mesin AS g_mesin, g.kode_warna AS g_kode_warna, g.color_name AS g_color_name, g.color_desc AS g_color_desc, g.resep_prod_code AS g_resep_prod_code, g.resep_prod_name AS g_resep_prod_name, g.cus_color AS g_cus_color FROM dbo.resep_obat_experiment e LEFT JOIN dbo.resep_obat_experiment_group g ON g.id=e.group_id WHERE e.id=?", [$resep_id]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$data)
  die('Data not found');
require_once __DIR__ . '/experiment_visibility_helper.php';
if (!resepExperimentCanAccessStatus($conn, $data['experiment_status'] ?? 'Draft', $data['was_in_process'] ?? 0, $data['was_approved'] ?? 0)) {
  $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk experiment ini.';
  header('Location: list_experiment.php');
  exit;
}
// Detail experiment menampilkan data dari row experiment itu sendiri, bukan info group.
$items = [];
$stmtD = sqlsrv_query($conn, "SELECT d.*, COALESCE(NULLIF(d.codeprod_proint, ''), m_by_id.codeprod_proint, m_legacy.codeprod_proint) AS display_codeprod_proint FROM dbo.resep_obat_experiment_detail d LEFT JOIN dbo.resep_master_obat m_by_id ON m_by_id.id = d.master_obat_id OUTER APPLY (SELECT TOP 1 codeprod_proint FROM dbo.resep_master_obat m WHERE m.kode_obat = d.kode AND LTRIM(RTRIM(m.nama_obat)) = LTRIM(RTRIM(d.name)) ORDER BY m.id DESC) m_legacy WHERE d.id_resep_experiment=? ORDER BY d.id ASC", [$resep_id]);
while ($stmtD && $row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC))
  $items[] = $row;
$labParam = [];
$slp = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_param WHERE id_resep_experiment=? ORDER BY id DESC", [$resep_id]);
if ($slp && $r = sqlsrv_fetch_array($slp, SQLSRV_FETCH_ASSOC))
  $labParam = $r;
$labData = [];
$sld = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.resep_obat_experiment_lab_data WHERE id_resep_experiment=? ORDER BY id DESC", [$resep_id]);
if ($sld && $r = sqlsrv_fetch_array($sld, SQLSRV_FETCH_ASSOC))
  $labData = $r;
$grandTotal = 0;
$catTotals = [];
$catCFTotals = [];
foreach ($items as &$item) {
  $item['uom_display'] = strtoupper(trim($item['uom'])) === 'G/L' ? 'GR' : $item['uom'];
  $grandTotal += (float) ($item['total'] ?? 0);
  $cat = trim($item['category'] ?? '') ?: 'Others';
  $catTotals[$cat] = ($catTotals[$cat] ?? 0) + (float) ($item['total'] ?? 0);
  $catCFTotals[$cat] = ($catCFTotals[$cat] ?? 0) + (float) ($item['cf'] ?? 0);
}
unset($item);
$planQty = (float) ($data['plan_qty'] ?? 0);
if ($planQty <= 0)
  $planQty = 1;
$totalCost = $grandTotal / $planQty;
$categoryCosts = [];
foreach ($catTotals as $cat => $total)
  $categoryCosts[$cat] = $total / $planQty;
$themeColor = $_SESSION['Theme'] ?? 'primary';
function nf0($n)
{
  return number_format((float) $n, 0, '.', ',');
}
function nf2($n)
{
  return number_format((float) $n, 2, '.', ',');
}
function nf4($n)
{
  return number_format((float) $n, 4, '.', ',');
}
function rp($n)
{
  return 'Rp ' . number_format((float) $n, 2, ',', '.');
}
$status = $data['experiment_status'] ?? 'Draft';
$isAdmin = (int) ($_SESSION['GroupId'] ?? 0) === 1;
$isKabagLab = false;
if (!$isAdmin) {
  $chkKabagLab = sqlsrv_query(
    $conn,
    "SELECT TOP 1 g.id
       FROM dbo.resep_obat_group_members m
       INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
       WHERE m.username = ? AND (g.role_type = 'KABAG' OR UPPER(g.group_name) = 'KABAG LAB')",
    [$_SESSION['UserName'] ?? '']
  );
  $isKabagLab = $chkKabagLab && sqlsrv_fetch_array($chkKabagLab, SQLSRV_FETCH_ASSOC);
  if ($chkKabagLab)
    sqlsrv_free_stmt($chkKabagLab);
}
$isExperimentApproved = $status === 'Approved';
$isApproved = !$isAdmin && ($isExperimentApproved || ($data['group_status'] ?? '') === 'Approved');
$canApproveAction = ($permissions['CanEdit'] ?? 0) == 1 && ($isAdmin || $isKabagLab);
$canEditApproved = $isAdmin && ($permissions['CanEdit'] ?? 0) == 1;
$canDeleteApproved = $isAdmin && ($permissions['CanDelete'] ?? 0) == 1;

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

include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<style>
  .info-label {
    font-weight: 600;
    background: #f4f6f9;
    width: 140px;
    white-space: nowrap;
    vertical-align: top
  }

  .detail-info-table td.val {
    vertical-align: top
  }

  .resep-table th,
  .resep-table td {
    border: 1px solid #dee2e6 !important;
    padding: .5rem;
    font-size: .875rem;
    vertical-align: middle
  }

  .resep-table thead th {
    background: #f4f6f9;
    color: #495057;
    font-weight: 600;
    text-align: center;
    white-space: nowrap
  }

  .resep-table tbody td.num {
    text-align: right
  }

  .resep-table tbody td.ctr {
    text-align: center
  }

  .resep-table tfoot td {
    background: #f8f9fa
  }

  .source-badge {
    display: inline-block;
    font-size: .65rem;
    font-weight: 700;
    padding: .15rem .45rem;
    border-radius: 4px
  }

  .source-badge-manual {
    background: #ffc107;
    color: #1f2d3d
  }

  .source-badge-proint {
    background: #28a745;
    color: #fff
  }
</style>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0"><i class="fas fa-flask mr-2"></i>Detail Resep Obat Experiment</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="list_experiment.php">Experiment</a></li>
            <li class="breadcrumb-item active">Detail</li>
          </ol>
        </div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white py-2">
          <h3 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i> Info Resep</h3>
          <div class="card-tools"><button type="button" class="btn btn-default btn-sm mr-1" onclick="window.print()"><i
                class="fas fa-print mr-1"></i> Print</button><button type="button" class="btn btn-primary btn-sm mr-1"
              data-toggle="modal" data-target="#modalLabParam"><i class="fas fa-cogs"></i> Mesin
              Lab</button><button type="button" class="btn btn-secondary btn-sm mr-1" data-toggle="modal"
              data-target="#modalLabData"><i class="fas fa-vial"></i> Lab
              Data</button><?php if ($canApproveAction): ?><?php if ($isExperimentApproved): ?><button type="button"
                  id="btnUnapprove" class="btn btn-warning btn-sm mr-1"><i class="fas fa-undo"></i>
                  Unapprove</button><?php else: ?><button type="button" id="btnApprove"
                  class="btn btn-success btn-sm mr-1"><i class="fas fa-check"></i>
                  Approve</button><?php endif; ?><?php endif; ?></div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <table class="table table-bordered table-sm detail-info-table mb-0">
                <tr>
                  <td class="info-label">Eksperimen Ke</td>
                  <td class="val"><span class="badge badge-info"><?= (int) ($data['experiment_seq'] ?? 1) ?></span>
                    <span
                      class="badge badge-<?= ['Draft' => 'secondary', 'Process' => 'warning', 'Prosess' => 'warning', 'Gagal' => 'danger', 'Sukses' => 'success', 'Approved' => 'primary'][$status] ?? 'secondary' ?> ml-1"><?= htmlspecialchars($status) ?></span>
                  </td>
                </tr>
                <tr>
                  <td class="info-label">No Sales Order</td>
                  <td class="val"><?= htmlspecialchars(!empty($data['soi']) ? $data['soi'] : '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Kode Produksi</td>
                  <td class="val"><b><?= htmlspecialchars(!empty($data['no_cp']) ? $data['no_cp'] : '-') ?></b></td>
                </tr>
                <tr>
                  <td class="info-label">Kode Greige</td>
                  <td class="val"><?= htmlspecialchars($data['kode_grey'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Mesin Paddry</td>
                  <td class="val"><?= htmlspecialchars($data['mesin'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Kode Warna Lab</td>
                  <td class="val"><b><?= htmlspecialchars($data['kode_warna'] ?? '-') ?></b></td>
                </tr>
                <tr>
                  <td class="info-label">Warna</td>
                  <td class="val"><?= htmlspecialchars($data['color_name'] ?? '-') ?></td>
                </tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-bordered table-sm detail-info-table mb-0">
                <tr>
                  <td class="info-label">Kode Produk Kain</td>
                  <td class="val"><b><?= htmlspecialchars($data['resep_prod_code'] ?? '-') ?></b></td>
                </tr>
                <tr>
                  <td class="info-label">Nama Produk Kain</td>
                  <td class="val"><?= htmlspecialchars($data['resep_prod_name'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Label Jual</td>
                  <td class="val"><?= htmlspecialchars($data['cus_color'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Plan Qty</td>
                  <td class="val num"><?= nf0($data['plan_qty'] ?? 0) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Berat</td>
                  <td class="val num"><?= nf4($data['weight'] ?? 0) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Vlot</td>
                  <td class="val num"><?= nf2($data['vlot'] ?? 0) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Dibuat Oleh</td>
                  <td class="val"><?= htmlspecialchars($data['created_by'] ?? '-') ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Detail Resep</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="resep-table table table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th style="width:40px">No</th>
                  <th>Kode</th>
                  <th>Nama Obat</th>
                  <th>Kategori</th>
                  <th class="num">Jumlah</th>
                  <th class="ctr">Satuan</th>
                  <th class="num">Konsentrasi<br>Obat</th>
                  <th class="ctr">Satuan Konsentrasi<br>Obat</th>
                  <?php if ($canSeePrice): ?>
                    <th class="num">Harga</th>
                    <th class="num">Total</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody><?php $no = 1;
              foreach ($items as $item): ?>
                  <tr>
                    <td class="ctr"><?= $no++ ?></td>
                    <td><b><?= htmlspecialchars($item['display_codeprod_proint'] ?? '-') ?></b></td>
                    <td><?= htmlspecialchars($item['name'] ?? '-') ?></td>
                    <td class="ctr"><?= htmlspecialchars($item['category'] ?? '-') ?></td>
                    <td class="num"><?= nf4($item['receipe'] ?? 0) ?></td>
                    <td class="ctr"><?= htmlspecialchars($item['uom_display'] ?? '-') ?></td>
                    <td class="num"><?= nf4($item['cf'] ?? 0) ?></td>
                    <td class="ctr"><?= htmlspecialchars($item['uom_cf'] ?? '-') ?></td>
                    <?php if ($canSeePrice): ?>
                      <td class="num">
                        <?= rp($item['std_price'] ?? 0) ?>
                        <?= !empty($item['price_satuan']) ? ' / ' . htmlspecialchars($item['price_satuan']) : '' ?>
                      </td>
                      <td class="num"><?= rp($item['total'] ?? 0) ?></td>
                    <?php endif; ?>
                  </tr><?php endforeach; ?>
              </tbody>
              <?php if ($canSeePrice): ?>
                <tfoot>
                  <tr>
                    <td colspan="9" class="text-right font-weight-bold">Grand Total</td>
                    <td class="num font-weight-bold"><?= rp($grandTotal) ?></td>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-5 offset-md-7">
          <div class="card">
            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
              <h3 class="card-title mb-0"><i class="fas fa-calculator mr-1"></i> Cost Summary Per Meter</h3>
            </div>
            <div class="card-body p-0">
              <table class="resep-table table table-bordered table-sm mb-0">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th class="ctr">Total CF</th>
                    <?php if ($canSeePrice): ?>
                      <th class="num">Cost / Plan Qty</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody><?php foreach ($categoryCosts as $cat => $cost): ?>
                    <tr>
                      <td><b class="text-muted"><?= htmlspecialchars($cat) ?></b></td>
                      <td class="ctr"><?= nf2($catCFTotals[$cat] ?? 0) ?></td>
                      <?php if ($canSeePrice): ?>
                        <td class="num"><?= rp($cost) ?></td>
                      <?php endif; ?>
                    </tr><?php endforeach; ?>
                </tbody>
                <?php if ($canSeePrice): ?>
                  <tfoot>
                    <tr>
                      <td colspan="2" class="font-weight-bold">Total Cost / Meter</td>
                      <td class="num font-weight-bold" style="font-size:1.2rem"><?= rp($totalCost) ?></td>
                    </tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-light">
              <h3 class="card-title mb-0"><i class="fas fa-cogs mr-1"></i> Parameter Mesin Lab</h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-bordered table-sm mb-0">
                <?php
                $empty = empty($labParam);
                $valOrDash = function ($v) {
                  return ($v === null || $v === '') ? '-' : $v; };
                $mesinDisplay = $empty ? '<span class="text-muted">Belum diinput</span>' : htmlspecialchars(($labParam['machine_name'] ?? '-') . ' (' . ($labParam['machine_code'] ?? '-') . ')');
                $num = function ($v, $percent = false, $temp = false) use ($empty) {
                  if ($empty)
                    return '-';
                  if ($v === null || $v === '')
                    return '-';
                  return nf2($v) . ($percent ? ' %' : '') . ($temp ? ' °C' : '');
                };
                $lainnyaRaw = $labParam['lainnya'] ?? '';
                $lainnyaDisplay = ($lainnyaRaw === '' || $lainnyaRaw === null)
                  ? '-'
                  : nl2br(htmlspecialchars($lainnyaRaw));
                ?>
                <tr>
                  <td class="info-label" style="width:18%">Mesin</td>
                  <td colspan="5"><?= $mesinDisplay ?></td>
                </tr>
                <tr>
                  <td class="info-label" style="width:18%">Infra Red</td>
                  <td style="width:15%"><?= $num($labParam['infra_red'] ?? null, true) ?></td>
                  <td class="info-label" style="width:18%">Tekanan<br>Padder</td>
                  <td style="width:15%"><?= $num($labParam['tekanan_padder'] ?? null) ?></td>
                  <td class="info-label" style="width:18%">WPU</td>
                  <td><?= $num($labParam['wpu'] ?? null, true) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Speed</td>
                  <td><?= $num($labParam['speed'] ?? null) ?></td>
                  <td class="info-label">Fan1</td>
                  <td><?= $num($labParam['fan1'] ?? null, true) ?></td>
                  <td class="info-label">Fan2</td>
                  <td><?= $num($labParam['fan2'] ?? null, true) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Temp<br>Chamber 1</td>
                  <td><?= $num($labParam['temp_chamber_1'] ?? null, false, true) ?></td>
                  <td class="info-label">Waktu<br>Chamber 1</td>
                  <td><?= $num($labParam['temp_chamber_1_time'] ?? null) ?> detik</td>
                  <td class="info-label">Temp<br>Chamber 2</td>
                  <td><?= $num($labParam['temp_chamber_2'] ?? null, false, true) ?></td>
                </tr>
                <tr>
                  <td class="info-label">Waktu<br>Chamber 2</td>
                  <td><?= $num($labParam['temp_chamber_2_time'] ?? null) ?> detik</td>
                  <td colspan="4"></td>
                </tr>
                <tr>
                  <td class="info-label">Lainnya</td>
                  <td colspan="5"><?= $lainnyaDisplay ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-light">
              <h3 class="card-title mb-0"><i class="fas fa-vial mr-1"></i> Lab Data</h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-bordered table-sm mb-0">
                <tr>
                  <td class="info-label">Delta L</td>
                  <td>
                    <?= !empty($labData) ? nf2($labData['delta_l'] ?? 0) : '<span class="text-muted">Belum diinput</span>' ?>
                  </td>
                </tr>
                <tr>
                  <td class="info-label">Delta A</td>
                  <td><?= !empty($labData) ? nf2($labData['delta_a'] ?? 0) : '-' ?></td>
                </tr>
                <tr>
                  <td class="info-label">Delta B</td>
                  <td><?= !empty($labData) ? nf2($labData['delta_b'] ?? 0) : '-' ?></td>
                </tr>
                <tr>
                  <td class="info-label">Delta E</td>
                  <td><?= !empty($labData) ? nf2($labData['delta_e'] ?? 0) : '-' ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- Card Lampiran (Terpisah) -->
      <div class="card">
        <div class="card-header bg-light">
          <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran</h3>
        </div>
        <div class="card-body">
          <?php
          $lp = $data['lampiran_path'] ?? '';
          $ln = $data['lampiran_name'] ?? '';
          if (!empty($lp) && !empty($ln)):
            $ext = strtolower(pathinfo($ln, PATHINFO_EXTENSION));
            $lampUrl = '/gg_app/pages/resep_obat/experiment/' . htmlspecialchars($lp);
            ?>
            <div class="d-flex align-items-start flex-wrap" style="gap:1rem">
              <?php if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                <a href="<?= $lampUrl ?>" target="_blank" title="<?= htmlspecialchars($ln) ?>">
                  <img src="<?= $lampUrl ?>"
                    style="max-height:160px;border:1px solid #dee2e6;border-radius:4px;display:block">
                </a>
              <?php else: ?>
                <a href="<?= $lampUrl ?>" target="_blank" class="btn btn-outline-danger">
                  <i class="fas fa-file-pdf fa-2x d-block mb-1"></i><?= htmlspecialchars($ln) ?>
                </a>
              <?php endif; ?>
              <div>
                <p class="mb-1"><i class="fas fa-paperclip text-muted"></i> <b><?= htmlspecialchars($ln) ?></b></p>
                <a href="<?= $lampUrl ?>" target="_blank" class="btn btn-sm btn-primary">
                  <i class="fas fa-download mr-1"></i> Download / Lihat
                </a>
              </div>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Belum ada lampiran.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php include __DIR__ . '/historycp/historycp.php'; ?>
    </div>
  </section>
</div>
<?php $labCanSave = !$isApproved && (($permissions['CanAdd'] ?? 0) == 1 || ($permissions['CanEdit'] ?? 0) == 1); ?>
<div class="modal fade" id="modalLabParam" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="labParamForm"><input type="hidden" name="resep_id" value="<?= (int) $resep_id ?>">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h5 class="modal-title"><i class="fas fa-cogs mr-1"></i> Parameter Mesin Lab</h5><button type="button"
            class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group"><label>Pilih Mesin</label><select id="lab_machine_select" class="form-control"
                  style="width:100%" <?= $isApproved ? 'disabled' : '' ?>><?php if (!empty($labParam['machine_code'])): ?>
                    <option selected value="<?= htmlspecialchars($labParam['machine_code']) ?>">
                      <?= htmlspecialchars(($labParam['machine_name'] ?? '') . ' (' . ($labParam['machine_code'] ?? '') . ')') ?>
                    </option><?php endif; ?>
                </select><input type="hidden" name="machine_code" id="lab_machine_code"
                  value="<?= htmlspecialchars($labParam['machine_code'] ?? '') ?>"><input type="hidden"
                  name="machine_name" id="lab_machine_name"
                  value="<?= htmlspecialchars($labParam['machine_name'] ?? '') ?>"></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Infra Red</label><input type="text" class="form-control" name="infra_red"
                  id="lab_infra_red" value="<?= nf2($labParam['infra_red'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Tekanan Padder</label><input type="text" class="form-control"
                  name="tekanan_padder" id="lab_tekanan_padder" value="<?= nf2($labParam['tekanan_padder'] ?? 0) ?>"
                  <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>WPU</label><input type="text" class="form-control" name="wpu" id="lab_wpu"
                  value="<?= nf2($labParam['wpu'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Speed</label><input type="text" class="form-control" name="speed"
                  id="lab_speed" value="<?= nf2($labParam['speed'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Fan1 (%)</label><input type="text" class="form-control" name="fan1"
                  id="lab_fan1" value="<?= nf2($labParam['fan1'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Fan2 (%)</label><input type="text" class="form-control" name="fan2"
                  id="lab_fan2" value="<?= nf2($labParam['fan2'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Temp Chamber 1 (°C)</label><input type="text" class="form-control"
                  name="temp_chamber_1" id="lab_temp_chamber_1" value="<?= nf2($labParam['temp_chamber_1'] ?? 0) ?>"
                  <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Waktu Chamber 1 (detik)</label><input type="text" class="form-control"
                  name="temp_chamber_1_time" id="lab_temp_chamber_1_time"
                  value="<?= nf2($labParam['temp_chamber_1_time'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Temp Chamber 2 (°C)</label><input type="text" class="form-control"
                  name="temp_chamber_2" id="lab_temp_chamber_2" value="<?= nf2($labParam['temp_chamber_2'] ?? 0) ?>"
                  <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-3">
              <div class="form-group"><label>Waktu Chamber 2 (detik)</label><input type="text" class="form-control"
                  name="temp_chamber_2_time" id="lab_temp_chamber_2_time"
                  value="<?= nf2($labParam['temp_chamber_2_time'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>></div>
            </div>
            <div class="col-md-12">
              <div class="form-group"><label>Lainnya</label><textarea class="form-control" name="lainnya" rows="3"
                  <?= $isApproved ? 'readonly' : '' ?>><?= htmlspecialchars($labParam['lainnya'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary"
            data-dismiss="modal">Batal</button><?php if ($labCanSave): ?><button type="submit"
              class="btn btn-success"><i class="fas fa-save"></i> Simpan</button><?php endif; ?></div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="modalLabData" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="labDataForm"><input type="hidden" name="resep_id" value="<?= (int) $resep_id ?>">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h5 class="modal-title"><i class="fas fa-vial mr-1"></i> Lab Data</h5><button type="button"
            class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group"><label>Delta L</label><input type="text" class="form-control" name="delta_l"
                  value="<?= nf2($labData['delta_l'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group"><label>Delta A</label><input type="text" class="form-control" name="delta_a"
                  value="<?= nf2($labData['delta_a'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group"><label>Delta B</label><input type="text" class="form-control" name="delta_b"
                  value="<?= nf2($labData['delta_b'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group"><label>Delta E</label><input type="text" class="form-control" name="delta_e"
                  value="<?= nf2($labData['delta_e'] ?? 0) ?>" <?= $isApproved ? 'readonly' : '' ?>>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary"
            data-dismiss="modal">Batal</button><?php if ($labCanSave): ?><button type="submit"
              class="btn btn-success"><i class="fas fa-save"></i> Simpan</button><?php endif; ?></div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    const labApproved = <?= $isApproved ? 'true' : 'false' ?>;
    $('#modalLabParam').on('shown.bs.modal', function () {
      if ($('#lab_machine_select').data('select2')) {
        $('#lab_machine_select').select2('destroy');
      }
      $('#lab_machine_select').select2({
        dropdownParent: $('#modalLabParam'),
        theme: 'bootstrap4',
        placeholder: 'Cari mesin...',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
          url: 'get_master_mesin.php',
          dataType: 'json',
          delay: 250,
          data: p => ({ q: p.term || '' }),
          processResults: d => ({ results: d.results || [] })
        }
      }).on('select2:select', function (e) {
        var md = e.params.data.machine_data || {};
        $('#lab_machine_code').val(md.kode_mesin || e.params.data.id);
        $('#lab_machine_name').val(md.nama_mesin || e.params.data.text);
        $('#lab_infra_red').val(md.infra_red ?? '');
        $('#lab_tekanan_padder').val(md.tekanan_padder ?? '');
        $('#lab_wpu').val(md.wpu ?? '');
        $('#lab_speed').val(md.speed ?? '');
        $('#lab_fan1').val(md.fan1 ?? '');
        $('#lab_fan2').val(md.fan2 ?? '');
        $('#lab_temp_chamber_1').val(md.temp_chamber_1 ?? '');
        $('#lab_temp_chamber_2').val(md.temp_chamber_2 ?? '');
      }).on('select2:clear', function () {
        $('#lab_machine_code, #lab_machine_name').val('');
      });
      if ($('#lab_machine_code').val()) {
        const code = $('#lab_machine_code').val();
        const name = $('#lab_machine_name').val();
        const opt = new Option(name + ' (' + code + ')', code, true, true);
        $('#lab_machine_select').append(opt).trigger('change');
      }
    });
    $('#labParamForm').on('submit', function (e) {
      e.preventDefault();
      if (labApproved) return;
      $.post('save_lab_param.php', $(this).serialize(), function (r) {
        if (r.status === 'success') Swal.fire('Berhasil', 'Parameter Mesin Lab tersimpan.', 'success').then(() => location.reload());
        else Swal.fire('Gagal', r.message || 'Simpan gagal.', 'error');
      }, 'json').fail(() => Swal.fire('Error', 'Request gagal.', 'error'));
    });
    $('#labDataForm').on('submit', function (e) {
      e.preventDefault();
      if (labApproved) return;
      $.post('save_lab_data.php', $(this).serialize(), function (r) {
        if (r.status === 'success') Swal.fire('Berhasil', 'Lab Data tersimpan.', 'success').then(() => location.reload());
        else Swal.fire('Gagal', r.message || 'Simpan gagal.', 'error');
      }, 'json').fail(() => Swal.fire('Error', 'Request gagal.', 'error'));
    });
    $('#btnApprove').on('click', function () {
      Swal.fire({
        title: 'Approve Experiment?',
        text: 'Experiment dan Group akan dikunci sebagai Approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
      }).then(r => {
        if (!r.isConfirmed) return;
        $.post('approve_experiment.php', { resep_id: <?= (int) $resep_id ?> }, function (resp) {
          if (resp.status === 'success') Swal.fire('Berhasil', 'Experiment berhasil diapprove.', 'success').then(() => location.reload());
          else Swal.fire('Gagal', resp.message || 'Approve gagal.', 'error');
        }, 'json').fail(() => Swal.fire('Error', 'Request approve gagal.', 'error'));
      });
    });
    $('#btnUnapprove').on('click', function () {
      Swal.fire({
        title: 'Unapprove Experiment?',
        text: 'Status Approved akan dikembalikan ke status sebelumnya.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Unapprove',
        cancelButtonText: 'Batal'
      }).then(r => {
        if (!r.isConfirmed) return;
        $.post('unapprove_experiment.php', { resep_id: <?= (int) $resep_id ?> }, function (resp) {
          if (resp.status === 'success') Swal.fire('Berhasil', 'Experiment dikembalikan ke status ' + (resp.new_status || 'Draft') + '.', 'success').then(() => location.reload());
          else Swal.fire('Gagal', resp.message || 'Unapprove gagal.', 'error');
        }, 'json').fail(() => Swal.fire('Error', 'Request unapprove gagal.', 'error'));
      });
    });
  });
</script>