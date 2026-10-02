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
  header('Location:../../dashboard.php');
  exit;
}
$group_id = $_GET['group_id'] ?? '';
if (!$group_id)
  die('Group ID Missing');
$sg = sqlsrv_query($conn, "SELECT * FROM dbo.resep_obat_experiment_group WHERE id=?", [$group_id]);
$group = $sg ? sqlsrv_fetch_array($sg, SQLSRV_FETCH_ASSOC) : null;
if (!$group)
  die('Group not found');
require_once __DIR__ . '/experiment_visibility_helper.php';
$scope = resepExperimentVisibilityScope($conn);

$experiments = [];
$visFilter = '';
if ($scope === 'APPROVED_ONLY') {
    $visFilter = "AND e.was_approved = 1 ";
} elseif ($scope === 'PROCESS_ONLY') {
    $visFilter = "AND e.was_in_process = 1 ";
}
$sql = "SELECT e.*, x.total_item, x.grand_total FROM dbo.resep_obat_experiment e OUTER APPLY (SELECT COUNT(*) total_item, SUM(total) grand_total FROM dbo.resep_obat_experiment_detail d WHERE d.id_resep_experiment=e.id) x WHERE e.group_id=? {$visFilter}ORDER BY e.id ASC";
$se = sqlsrv_query($conn, $sql, [$group_id]);
while ($se && $r = sqlsrv_fetch_array($se, SQLSRV_FETCH_ASSOC))
  $experiments[] = $r;

if ($scope !== 'ALL' && empty($experiments)) {
  $_SESSION['error'] = 'Anda tidak memiliki hak akses ke group ini.';
  header('Location: list_experiment.php');
  exit;
}

$historyCpList = [];
foreach ($experiments as $expIdx => $expRow) {
  $experiments[$expIdx]['display_status'] = trim((string)($expRow['experiment_status'] ?? 'Draft')) ?: 'Draft';
  $cpNo = strtoupper(trim((string)($expRow['no_cp'] ?? '')));
  if ($cpNo !== '' && in_array($experiments[$expIdx]['display_status'], ['Process', 'Prosess'], true)) {
    $historyCpList[] = $cpNo;
  }
}
$historyStatusMap = [];
if ($historyCpList) {
  try {
    if (!isset($conn3) || !($conn3 instanceof PDO)) {
      require_once __DIR__ . '/../../../koneksi3.php';
    }
    $placeholders = [];
    $historyParams = [];
    foreach (array_unique($historyCpList) as $idx => $cpNo) {
      $key = ':cp_' . $idx;
      $placeholders[] = $key;
      $historyParams[$key] = $cpNo;
    }
    $stmtHistoryStatus = $conn3->prepare("
      WITH latest_cp AS (
        SELECT
          UPPER(TRIM(CAST(prdnmbr AS TEXT))) AS cp_no,
          productionhdid,
          ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(CAST(prdnmbr AS TEXT))) ORDER BY prddate DESC NULLS LAST, productionhdid DESC) AS rn
        FROM pdproductionhd
        WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) IN (" . implode(',', $placeholders) . ")
      ),
      acc_warna AS (
        SELECT
          l.cp_no,
          COALESCE(r.failmsid, 0) AS failmsid,
          COALESCE(r.fgresult, '') AS fgresult,
          COALESCE(f.failcode, '') AS failcode,
          COALESCE(f.faildesc, '') AS faildesc,
          ROW_NUMBER() OVER (
            PARTITION BY l.cp_no
            ORDER BY
              CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC,
              r.starttime DESC NULLS LAST,
              r.productionrtgid DESC
          ) AS rn
        FROM latest_cp l
        JOIN pdproductionrtg r ON r.productionhdid = l.productionhdid AND r.rtgmsid = 589
        LEFT JOIN pdfailms f ON r.failmsid = f.failmsid
        WHERE l.rn = 1
      )
      SELECT cp_no, failmsid, fgresult, failcode, faildesc
      FROM acc_warna
      WHERE rn = 1
    ");
    $stmtHistoryStatus->execute($historyParams);
    while ($historyStatus = $stmtHistoryStatus->fetch(PDO::FETCH_ASSOC)) {
      $cpNo = strtoupper(trim((string)($historyStatus['cp_no'] ?? '')));
      $failmsid = (int)($historyStatus['failmsid'] ?? 0);
      $fgResult = strtoupper(trim((string)($historyStatus['fgresult'] ?? '')));
      $hasFailName = trim((string)($historyStatus['failcode'] ?? '')) !== '' || trim((string)($historyStatus['faildesc'] ?? '')) !== '';
      if ($failmsid !== 0 && $hasFailName) {
        $historyStatusMap[$cpNo] = 'Fail';
      } elseif ($fgResult === 'P' && $failmsid === 0 && !$hasFailName) {
        $historyStatusMap[$cpNo] = 'Pass';
      }
    }
  } catch (Throwable $e) {
    error_log('view_group history status error: ' . $e->getMessage());
  }
}
foreach ($experiments as $expIdx => $expRow) {
  $displayStatus = $experiments[$expIdx]['display_status'];
  $cpNo = strtoupper(trim((string)($expRow['no_cp'] ?? '')));
  if (in_array($displayStatus, ['Process', 'Prosess'], true) && isset($historyStatusMap[$cpNo])) {
    $experiments[$expIdx]['display_status'] = $historyStatusMap[$cpNo];
  }
}
$isAdmin = (int) ($_SESSION['GroupId'] ?? 0) === 1;
$isPpc = resepUserIsPpc($conn);
$isKabag = resepUserIsKabag($conn);

/**
 * Helper: apakah user boleh melihat harga?
 * - Admin (GroupId=1) boleh
 * - User dengan role KABAG boleh
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

$statusSummary = ['Approved' => 0, 'Process' => 0, 'Draft' => 0];
foreach ($experiments as $expRow) {
  $st = trim((string)($expRow['display_status'] ?? ($expRow['experiment_status'] ?? 'Draft')));
  if ($st === 'Prosess') $st = 'Process';
  if ($st === '') $st = 'Draft';
  if (!isset($statusSummary[$st])) $statusSummary[$st] = 0;
  $statusSummary[$st]++;
}

$approvedSeq = '-';
$canAddOnApproved = $isAdmin && ($permissions['CanAdd'] ?? 0) == 1;
$displayExperiment = null;
// 1) Prioritize approved experiment
foreach ($experiments as $expRow) {
  if (!empty($group['approved_experiment_id']) && (int) $expRow['id'] === (int) $group['approved_experiment_id']) {
    $approvedSeq = 'EXP #' . (int) $expRow['experiment_seq'];
    $displayExperiment = $expRow;
    break;
  }
}
// 2) If no approved, find any experiment with non-draft status that has SOI/NoCP
if (!$displayExperiment) {
  $nonDraftStatuses = ['Approved', 'Prosess', 'Process', 'Success', 'Lunas', 'Pass', 'Fail'];
  foreach ($experiments as $expRow) {
    $st = trim($expRow['display_status'] ?? ($expRow['experiment_status'] ?? ''));
    if (in_array($st, $nonDraftStatuses) && (!empty($expRow['soi']) || !empty($expRow['no_cp']))) {
      $displayExperiment = $expRow;
      break;
    }
  }
}
// 3) Fallback: any non-draft experiment
if (!$displayExperiment) {
  $nonDraftStatuses = ['Approved', 'Prosess', 'Process', 'Success', 'Lunas', 'Pass', 'Fail'];
  foreach ($experiments as $expRow) {
    $st = trim($expRow['display_status'] ?? ($expRow['experiment_status'] ?? ''));
    if (in_array($st, $nonDraftStatuses)) {
      $displayExperiment = $expRow;
      break;
    }
  }
}
// 4) Last resort: latest experiment
if (!$displayExperiment && $experiments) {
  $displayExperiment = end($experiments);
  reset($experiments);
}
// Calculate effective group status from all experiments.
// Rule: any Pass => Pass; all Fail => Fail; Fail mixed/Process active => Process.
$effectiveGroupStatus = 'Draft';
$effectiveExpSeq = 0;
$totalExperiments = count($experiments);
$findSeqByStatus = static function (array $experiments, array $statuses): int {
  foreach ($experiments as $expRow) {
    $st = trim($expRow['display_status'] ?? ($expRow['experiment_status'] ?? ''));
    if (in_array($st, $statuses, true)) {
      return (int)($expRow['experiment_seq'] ?? 0);
    }
  }
  return 0;
};
$countPass = (int)($statusSummary['Pass'] ?? 0);
$countFail = (int)($statusSummary['Fail'] ?? 0);
$countProcess = (int)($statusSummary['Process'] ?? 0);
$countApproved = (int)($statusSummary['Approved'] ?? 0);
$countDraft = (int)($statusSummary['Draft'] ?? 0);
$countTungguCp = (int)($statusSummary['Tunggu CP'] ?? 0);
$countTungguMatching = (int)($statusSummary['Tunggu Matching'] ?? 0);

if ($countPass > 0) {
  $effectiveGroupStatus = 'Pass';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Pass']);
} elseif ($totalExperiments > 0 && $countFail === $totalExperiments) {
  $effectiveGroupStatus = 'Fail';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Fail']);
} elseif ($countProcess > 0 || $countFail > 0) {
  $effectiveGroupStatus = 'Process';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Process', 'Prosess', 'Fail', 'Draft', 'Approved']);
} elseif ($countApproved > 0) {
  $effectiveGroupStatus = 'Approved';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Approved']);
} elseif ($countTungguMatching > 0) {
  $effectiveGroupStatus = 'Tunggu Matching';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Tunggu Matching']);
} elseif ($countTungguCp > 0) {
  $effectiveGroupStatus = 'Tunggu CP';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Tunggu CP']);
} elseif ($countDraft > 0) {
  $effectiveGroupStatus = 'Draft';
  $effectiveExpSeq = $findSeqByStatus($experiments, ['Draft']);
} else {
  foreach (['Success', 'Sukses', 'Lunas', 'Gagal'] as $fallbackStatus) {
    if (!empty($statusSummary[$fallbackStatus])) {
      $effectiveGroupStatus = $fallbackStatus;
      $effectiveExpSeq = $findSeqByStatus($experiments, [$fallbackStatus]);
      break;
    }
  }
}
$displayInfo = $group;
if ($displayExperiment) {
  foreach (['soi', 'no_cp', 'kode_grey', 'mesin', 'kode_warna', 'color_name', 'color_desc', 'resep_prod_code', 'resep_prod_name', 'cus_color', 'created_by', 'created_at'] as $field) {
    if (!array_key_exists($field, $displayExperiment))
      continue;

    // SOI/No CP may still live in group row for legacy data; don't overwrite it with empty experiment values.
    if (in_array($field, ['soi', 'no_cp'], true) && trim((string)($displayExperiment[$field] ?? '')) === '')
      continue;

    $displayInfo[$field] = $displayExperiment[$field];
  }
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
function rp($n)
{
  return 'Rp ' . number_format((float) $n, 2, ',', '.');
}
function dt($d)
{
  return $d instanceof DateTime ? $d->format('d-M-Y H:i') : '-';
}
include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/sidebar.php';
?>
<style>
  .info-label {
    font-weight: 600;
    background: #f4f6f9;
    width: 150px;
    white-space: nowrap;
    vertical-align: top
  }

  .history-table th,
  .history-table td {
    vertical-align: middle
  }

  .status-badge {
    display: inline-block;
    font-size: .75rem;
    font-weight: 700;
    padding: .35rem .55rem;
    border-radius: .25rem
  }

  .status-tunggu-cp { background: #17a2b8; color: #fff; }
  .status-tunggu-matching { background: #6f42c1; color: #fff; }

  .metric-card {
    border: 1px solid #dee2e6;
    border-radius: .35rem;
    padding: 1rem;
    background: #fff;
    height: 100%
  }

  .metric-title {
    font-size: .75rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 700
  }

  .metric-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #212529
  }
</style>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0"><i class="fas fa-layer-group mr-2"></i>Detail Group Experiment</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="list_experiment.php">Experiment</a></li>
            <li class="breadcrumb-item active">Group</li>
          </ol>
        </div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i> Info Group</h3>
          <div class="card-tools">
            <?php if (($permissions['CanAdd'] ?? 0) == 1): ?><a
                href="input_resep.php?next_group_id=<?= (int) $group_id ?>" class="btn btn-success btn-sm mr-1"><i
                  class="fas fa-plus"></i> Buat Experiment Berikutnya</a><?php endif; ?>
            <a href="list_experiment.php" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-4">
              <div class="metric-card">
                <div class="metric-title">Total Experiment</div>
                <div class="metric-value"><?= count($experiments) ?></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="metric-card">
                <div class="metric-title">Status Group</div>
                <div class="metric-value">
                  <?php if ($effectiveGroupStatus === 'Process'): ?>
                    <span class="badge badge-warning">Process</span>
                  <?php elseif ($effectiveGroupStatus === 'Approved'): ?>
                    <span class="badge badge-primary"><?= $countApproved > 1 ? 'Approved' : 'Approved EXP #' . ($effectiveExpSeq > 0 ? $effectiveExpSeq : '') ?></span>
                  <?php elseif ($effectiveGroupStatus === 'Pass'): ?>
                    <span class="badge badge-success">Pass</span>
                  <?php elseif ($effectiveGroupStatus === 'Fail'): ?>
                    <span class="badge badge-danger">Fail</span>
                  <?php elseif ($effectiveGroupStatus === 'Tunggu CP'): ?>
                    <span class="status-badge status-tunggu-cp">Tunggu CP</span>
                  <?php elseif ($effectiveGroupStatus === 'Tunggu Matching'): ?>
                    <span class="status-badge status-tunggu-matching">Tunggu Matching</span>
                  <?php else: ?>
                    <span class="badge badge-secondary"><?= htmlspecialchars($effectiveGroupStatus) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="metric-card">
                <div class="metric-title">Status Summary</div>
                <div class="metric-value" style="font-size:.95rem; line-height:1.8;">
                  <span class="badge badge-primary">Approved: <?= (int)($statusSummary['Approved'] ?? 0) ?></span>
                  <span class="badge badge-warning">Process: <?= (int)($statusSummary['Process'] ?? 0) ?></span>
                  <span class="badge status-tunggu-matching">Tunggu Matching: <?= $countTungguMatching ?></span>
                  <span class="badge status-tunggu-cp">Tunggu CP: <?= $countTungguCp ?></span>
                  <span class="badge badge-success">Pass: <?= (int)($statusSummary['Pass'] ?? 0) ?></span>
                  <span class="badge badge-danger">Fail: <?= (int)($statusSummary['Fail'] ?? 0) ?></span>
                  <span class="badge badge-secondary">Draft: <?= (int)($statusSummary['Draft'] ?? 0) ?></span>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <table class="table table-bordered table-sm mb-0">
                <tr>
                  <td class="info-label">SOI</td>
                  <td><?= htmlspecialchars($displayInfo['soi'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">No CP</td>
                  <td><?= htmlspecialchars($displayInfo['no_cp'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Kode Grey</td>
                  <td><?= htmlspecialchars($displayInfo['kode_grey'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Mesin</td>
                  <td><?= htmlspecialchars($displayInfo['mesin'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Kode Warna</td>
                  <td><b><?= htmlspecialchars($displayInfo['kode_warna'] ?? '-') ?></b></td>
                </tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-bordered table-sm mb-0">
                <tr>
                  <td class="info-label">Color Name</td>
                  <td><?= htmlspecialchars($displayInfo['color_name'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Resep Prod Code</td>
                  <td><b><?= htmlspecialchars($displayInfo['resep_prod_code'] ?? '-') ?></b></td>
                </tr>
                <tr>
                  <td class="info-label">Resep Prod Name</td>
                  <td><?= htmlspecialchars($displayInfo['resep_prod_name'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Cus Color</td>
                  <td><?= htmlspecialchars($displayInfo['cus_color'] ?? '-') ?></td>
                </tr>
                <tr>
                  <td class="info-label">Created By</td>
                  <td><?= htmlspecialchars($displayInfo['created_by'] ?? '-') ?> /
                    <?= dt($displayInfo['created_at'] ?? null) ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title mb-0"><i class="fas fa-history mr-1"></i> History Experiment</h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm history-table mb-0">
              <thead class="thead-light">
                <tr>
                  <th class="text-center">Urutan</th>
                  <th>Status</th>
                  <th class="text-right">Total Item</th>
                  <th class="text-right">Grand Total</th>
                  <th class="text-right">Total Cost / Meter</th>
                  <th class="text-right">Created By</th>
                  <th class="text-right">Created At</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$experiments): ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted p-4">Belum ada experiment.</td>
                  </tr><?php endif; ?>
                <?php foreach ($experiments as $e):
                  $st = $e['display_status'] ?: ($e['experiment_status'] ?: 'Draft');
                  $cls = ['Draft' => 'secondary', 'Process' => 'warning', 'Prosess' => 'warning', 'Gagal' => 'danger', 'Sukses' => 'success', 'Approved' => 'primary', 'Pass' => 'success', 'Fail' => 'danger'][$st] ?? 'secondary';
                  $statusClass = ['Tunggu CP' => 'status-tunggu-cp', 'Tunggu Matching' => 'status-tunggu-matching'][$st] ?? ('badge badge-' . $cls);
                  $planQtyRow = (float) ($e['plan_qty'] ?? 0);
                  $costPerMeter = ($planQtyRow > 0) ? ((float) ($e['grand_total'] ?? 0) / $planQtyRow) : 0; ?>
                  <tr>
                    <td class="text-center"><span class="badge badge-info">EXP #<?= (int) $e['experiment_seq'] ?></span>
                    </td>
                    <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($st) ?></span></td>
                    <td class="text-right"><?= (int) ($e['total_item'] ?? 0) ?></td>
                    <td class="text-right"><?= $canSeePrice ? rp($e['grand_total'] ?? 0) : '-' ?></td>
                    <td class="text-right"><?= $canSeePrice ? rp($costPerMeter) : '-' ?></td>
                    <td class="text-right"><?= htmlspecialchars($e['created_by'] ?? '-') ?></td>
                    <td class="text-right"><?= dt($e['created_at'] ?? null) ?></td>
                    <td class="text-center"><a href="view_resep.php?resep_id=<?= (int) $e['id'] ?>&from=group"
                        class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                      <?php $canEditRow = $isAdmin || (($permissions['CanEdit'] ?? 0) == 1);
                      $canDeleteRow = $isAdmin || (($permissions['CanDelete'] ?? 0) == 1);
                      $canEditApprovedRow = ($isAdmin || $isPpc || $isKabag) && $canEditRow;
                      $canDeleteApprovedRow = $isAdmin && $canDeleteRow;
                      if ($canEditRow && ($st !== 'Approved' || $canEditApprovedRow)): ?><a
                          href="input_resep.php?resep_id=<?= (int) $e['id'] ?>" class="btn btn-warning btn-sm"><i
                            class="fas fa-edit"></i></a><?php endif; ?>
                      <?php if ($canDeleteRow && ($st !== 'Approved' || $canDeleteApprovedRow)): ?><button
                          type="button" class="btn btn-danger btn-sm btn-delete-exp" data-id="<?= (int) $e['id'] ?>"><i
                            class="fas fa-trash"></i></button><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    $(document).on('click', '.btn-delete-exp', function () {
      const id = $(this).data('id');
      Swal.fire({ title: 'Hapus Experiment?', text: 'Detail resep experiment ini akan ikut terhapus. Jika ini experiment terakhir, group juga ikut terhapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then(r => {
        if (!r.isConfirmed) return;
        $.post('delete_experiment.php', { resep_id: id }, function (resp) {
          if (resp.status === 'success') Swal.fire('Berhasil', 'Experiment berhasil dihapus.', 'success').then(() => { if (resp.group_deleted) location.href = 'list_experiment.php'; else location.reload(); });
          else Swal.fire('Gagal', resp.message || 'Gagal menghapus experiment.', 'error');
        }, 'json').fail(() => Swal.fire('Error', 'Request hapus gagal.', 'error'));
      });
    });
  });
</script>