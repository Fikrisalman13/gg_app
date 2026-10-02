<?php
/**
 * Detail Izin Pulang Cepat (IPC)
 * Layout mengikuti pola detail_izin_keluar_pabrik.php / formulir fisik HRD.
 */
session_start();
require_once '../../koneksi.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['UserName'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Diperlukan - Detail Izin Pulang Cepat</title>
  <link rel="icon" href="/gg_app/dist/img/sumlogo.png" type="image/x-icon">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page" style="background-color: #f4f6f9;">
<div class="login-box" style="width: 460px; max-width: 92%;">
  <div class="card card-outline card-primary shadow">
    <div class="card-header text-center bg-primary text-white py-3">
      <h5 class="m-0 font-weight-bold"><i class="fas fa-lock mr-2"></i> Akses Detail Formulir</h5>
    </div>
    <div class="card-body text-center p-4">
      <div class="mb-3">
        <i class="fas fa-user-shield text-warning" style="font-size: 3.5rem;"></i>
      </div>
      <h5 class="font-weight-bold text-dark mb-2">Silakan Login Terlebih Dahulu</h5>
      <p class="text-muted small mb-4">
        Untuk melihat detail lengkap pengajuan izin pulang cepat, riwayat tanda tangan, dan dokumen lampiran, silakan login dengan akun yang memiliki hak akses.
      </p>
      <div class="d-flex justify-content-center">
        <a href="/gg_app/login.php" class="btn btn-primary font-weight-bold px-4 mr-2">
          <i class="fas fa-sign-in-alt mr-1"></i> Login Sekarang
        </a>
        <button type="button" onclick="window.close()" class="btn btn-default px-3">
          <i class="fas fa-times mr-1"></i> Tutup
        </button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
<?php
  exit;
}

$ticket = $_GET['ticket'] ?? '';
if (empty($ticket)) {
  die('Ticket tidak ditemukan');
}

$sql = "SELECT * FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
  die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

$currentStatus = strtolower(trim($data['status_ticket'] ?? ''));
$isRejected = $currentStatus === 'ditolak' || $currentStatus === 'rejected';
$isApproved = $currentStatus === 'approved';
$currentUserId = (int) ($_SESSION['UserId'] ?? 0);

function ipcIsKaRoleStr($role)
{
  return stripos($role, 'kabag') !== false
    || stripos($role, 'kasie') !== false
    || stripos($role, 'kadept') !== false
    || preg_match('/^ka\./i', trim($role)) === 1;
}

$hasRolePemohon = $hasRoleAtasan = $hasRoleHRD = $canSaveCatatan = false;
$canRejectAsAtasan = $canRejectAsHRD = false;
if ($currentUserId) {
  $stmtRole = sqlsrv_query(
    $conn,
    "SELECT GroupRole FROM User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1",
    [$currentUserId]
  );
  if ($stmtRole) {
    while ($r = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
      $role = trim($r['GroupRole'] ?? '');
      if (strcasecmp($role, 'Pemohon') === 0)
        $hasRolePemohon = true;
      if (strcasecmp($role, 'HRD') === 0 || strcasecmp($role, 'Personalia') === 0)
        $hasRoleHRD = true;
      if (ipcIsKaRoleStr($role) || strcasecmp($role, 'Atasan Pemohon') === 0)
        $hasRoleAtasan = true;
      if (strcasecmp($role, 'Atasan Pemohon') === 0)
        $canRejectAsAtasan = true;
      if (strcasecmp($role, 'HRD') === 0)
        $canRejectAsHRD = true;
      if (strcasecmp($role, 'DanRu SATPAM') === 0)
        $canSaveCatatan = true;
    }
    sqlsrv_free_stmt($stmtRole);
  }
}

$stmtTtd = sqlsrv_query(
  $conn,
  "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Umum_TTD WHERE Ticket = ?",
  [$ticket]
);
$ttd = [];
if ($stmtTtd) {
  while ($r = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
    $ttd[$r['GroupRole']] = $r;
  }
  sqlsrv_free_stmt($stmtTtd);
}

function ipcTtdExists($d)
{
  return !empty($d['SignaturePath']);
}
function ipcTtdByMe($d, $userId)
{
  if (!ipcTtdExists($d)) return false;
  $signedBy = (int) ($d['SignedByUserId'] ?? 0);
  if ($signedBy === $userId) return true;
  return ((int) ($_SESSION['GroupId'] ?? 0) === 1 && $signedBy === 0);
}
function ipcFmtDate($dt)
{
  if ($dt instanceof DateTime)
    return $dt->format('d-m-Y');
  if (is_string($dt) && !empty($dt))
    return date('d-m-Y', strtotime($dt));
  return '-';
}

// Helper: Cek apakah ada template TTD Manual/System (UserId=0/NULL) untuk role tertentu
function ipcHasManualTemplate($conn, $role)
{
  $sql = "SELECT TOP 1 id FROM User_TTD_Template_Umum 
          WHERE GroupRole = ? AND (UserId IS NULL OR UserId = 0) AND IsActive = 1";
  $stmt = sqlsrv_query($conn, $sql, [$role]);
  return $stmt && sqlsrv_has_rows($stmt);
}
function ipcVal($v)
{
  $v = trim((string) ($v ?? ''));
  return $v === '' ? '-' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function ipcRenderTtdCell($ttdData, $roleCode, $canSign, $isLocked, $userId, $waiting = false)
{
  $exists = ipcTtdExists($ttdData);
  $isMine = ipcTtdByMe($ttdData, $userId);
  $role = htmlspecialchars($roleCode);
  $img = $exists ? "<img src='" . htmlspecialchars($ttdData['SignaturePath']) . "' height='60' alt='TTD'>" : '';
  $btn = '';
  if (!$isLocked) {
    if ($canSign) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-primary btn-ttd' data-role='{$role}'>Tanda Tangan</button>";
    } elseif ($exists && $isMine) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-danger btn-delete-ttd' data-role='{$role}'>Hapus Tanda Tangan</button>";
    } elseif ($exists) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled>Sudah Ditandatangani</button>";
    } elseif ($waiting) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled title='Menunggu tanda tangan sebelumnya'>Belum giliran</button>";
    }
  } elseif ($exists) {
    $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled>Sudah Ditandatangani</button>";
  }
  return "<td style='padding:6px;vertical-align:middle;'><div class='ttd-area-slot' data-ttd-role='{$role}'>{$img}</div><div class='ttd-btn-slot mt-2' data-ttd-role='{$role}'>{$btn}</div></td>";
}
function ipcRenderTtdLabel($ttdData, $roleCode, $defaultLabel)
{
  $name = ipcTtdExists($ttdData) ? htmlspecialchars($ttdData['SignedByUserName'] ?? $defaultLabel) : htmlspecialchars($defaultLabel);
  return "<td class='ttd-label-slot' data-ttd-role='" . htmlspecialchars($roleCode) . "' data-default-label='" . htmlspecialchars($defaultLabel) . "'>{$name}</td>";
}

$ttdPemohon = $ttd['Pemohon'] ?? [];
$ttdAtasan = $ttd['Atasan Pemohon'] ?? [];
$ttdHRD = $ttd['HRD'] ?? [];

$canSignPemohon = $hasRolePemohon && !ipcTtdExists($ttdPemohon);
$canSignAtasan = $hasRoleAtasan && ipcTtdExists($ttdPemohon) && !ipcTtdExists($ttdAtasan);

// Updated: Punya role ATAU ada template manual/system (UserId=0/NULL) untuk role tersebut
$hasManualPemohon = ipcHasManualTemplate($conn, 'Pemohon');
$hasManualAtasan = ipcHasManualTemplate($conn, 'Atasan Pemohon');
$hasManualHRD = ipcHasManualTemplate($conn, 'HRD');

$canSignHRD = ($hasRoleHRD || $hasManualHRD) && ipcTtdExists($ttdAtasan) && !ipcTtdExists($ttdHRD);

// Updated: Jika user tidak punya role tapi ada template manual, tetap bisa sign
if (!$hasRolePemohon && $hasManualPemohon && !ipcTtdExists($ttdPemohon)) {
  $canSignPemohon = true;
}
if (!$hasRoleAtasan && $hasManualAtasan && ipcTtdExists($ttdPemohon) && !ipcTtdExists($ttdAtasan)) {
  $canSignAtasan = true;
}

$waitingPemohon = ($hasRolePemohon || $hasManualPemohon) && !$canSignPemohon && !ipcTtdExists($ttdPemohon);
$waitingAtasan = ($hasRoleAtasan || $hasManualAtasan) && !$canSignAtasan && !ipcTtdExists($ttdAtasan);
$waitingHRD = ($hasRoleHRD || $hasManualHRD) && !$canSignHRD && !ipcTtdExists($ttdHRD);

$isLocked = $isRejected || $isApproved;
if ($isLocked) {
  $canSignPemohon = $canSignAtasan = $canSignHRD = false;
  $waitingPemohon = $waitingAtasan = $waitingHRD = false;
}

$canReject = !$isLocked && ($canRejectAsAtasan || $canRejectAsHRD);
$theme = $_SESSION['Theme'] ?? 'primary';
$statusKeluar = !empty($data['jam_keluar_real']) ? 'Sudah Keluar' : 'Belum Keluar';
$statusBadgeClass = $statusKeluar === 'Sudah Keluar' ? 'success' : 'secondary';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Detail IPC – <?= htmlspecialchars($ticket) ?></title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <style>
    body {
      background: #fff;
      font-size: 14px;
      color: #000;
    }

    table {
      border-collapse: collapse;
      width: 100%;
    }

    td {
      padding: 5px 8px;
      vertical-align: top;
    }

    @media(max-width:768px) {
      .form-wrapper {
        overflow-x: auto
      }

      .outer-form {
        min-width: 700px
      }
    }

    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600
    }
  </style>
  <script>
    window.addEventListener('load', function () {
      var canReject = <?= $canReject ? 'true' : 'false'; ?>;
      if (window.parent && window.parent.updateRejectButtonVisibility) {
        window.parent.updateRejectButtonVisibility(canReject, '<?= htmlspecialchars($ticket) ?>');
      }
    });
  </script>
</head>

<body>
  <?php if ($isRejected):
    $rejBy = htmlspecialchars($data['rejected_by'] ?? 'N/A');
    $rejAlasan = $data['rejection_reason'] ?? '-';
    $rejDate = $data['rejection_date'] ?? null;
    $rejDateStr = $rejDate instanceof DateTime ? $rejDate->format('d-m-Y H:i') : ($rejDate ?: '-');
    ?>
    <div
      style="border-left:5px solid #dc3545;background:#fff5f5;padding:16px 20px;border-radius:8px;max-width:900px;margin:10px auto 18px;">
      <div
        style="color:#dc3545;font-size:1.15rem;font-weight:700;margin-bottom:10px;border-bottom:1px solid rgba(220,53,69,.2);padding-bottom:8px;">
        <i class="fas fa-times-circle"></i> PENGAJUAN DITOLAK
      </div>
      <div class="mb-2"><strong style="min-width:140px;display:inline-block;">Ditolak oleh</strong>: <?= $rejBy ?></div>
      <div class="mb-2"><strong style="min-width:140px;display:inline-block;">Tanggal</strong>:
        <?= htmlspecialchars($rejDateStr) ?>
      </div>
      <div><strong style="min-width:140px;display:inline-block;">Alasan</strong>:
        <div
          style="background:#ffecec;padding:10px 14px;border-radius:6px;border:1px solid #f5c6cb;margin-top:6px;font-style:italic;color:#721c24;">
          <?= nl2br(htmlspecialchars($rejAlasan)) ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="form-wrapper" style="max-width:900px;margin:10px auto;">
    <table class="outer-form"
      style="width:100%;border:1px solid #000;border-collapse:collapse;font-size:14px;line-height:1.4;">
      <tr>
        <td style="padding:0;">
          <table style="width:100%;border-collapse:collapse;">
            <tr>
              <td
                style="width:90px;border-right:1px solid #000;padding:8px 6px;text-align:center;vertical-align:middle;">
                <img src="../../dist/img/sumlogo.png" width="70" alt="logo">
              </td>
              <td style="padding:6px 10px;vertical-align:middle;text-align:center;">
                <div style="font-size:12px;">PT. SURYA USAHA MANDIRI</div>
                <div style="font-size:10px;">FORMULIR</div>
                <div style="font-size:15px;font-weight:bold;line-height:1.3;">FORM IZIN PULANG CEPAT</div>
              </td>
              <td style="width:150px;border-left:1px solid #000;padding:6px 8px;vertical-align:middle;font-size:11px;">
                No : SUM-FM-HRD-011<br>Rev : 0<br>Hal : 1/1
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="border-top:1px solid #000;padding:0;">
          <table style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="width:120px;padding:6px 8px;">Nomor Pengajuan</td>
              <td style="width:10px;padding:6px 0;">:</td>
              <td style="padding:6px 8px;font-weight:bold;"><?= htmlspecialchars($ticket) ?></td>
              <td style="width:140px;padding:6px 8px;text-align:right;white-space:nowrap;">Tgl.
                Pengajuan&nbsp;:&nbsp;<?= ipcFmtDate($data['tgl_pengajuan'] ?? '') ?></td>
            </tr>
          </table>
          <div style="padding:6px 8px 10px;font-style:italic;text-decoration:underline;">Diberikan kepada tersebut di
            bawah ini :</div>
          <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <colgroup>
              <col style="width:120px">
              <col style="width:5px">
              <col style="width:35%">
              <col style="width:150px">
              <col style="width:5px">
              <col style="width:auto">
            </colgroup>
            <tr>
              <td>NIK</td>
              <td>:</td>
              <td><?= ipcVal($data['nik'] ?? '-') ?></td>
              <td>Jam Pulang Normal</td>
              <td>:</td>
              <td><?= ipcVal($data['jam_pulang_normal'] ?? '-') ?></td>
            </tr>
            <tr>
              <td>Nama</td>
              <td>:</td>
              <td><?= ipcVal($data['nama_pemohon'] ?? '-') ?></td>
              <td>Jam Pulang Diminta</td>
              <td>:</td>
              <td><?= ipcVal($data['jam_pulang_diminta'] ?? '-') ?></td>
            </tr>
            <tr>
              <td>Departemen</td>
              <td>:</td>
              <td><?= ipcVal($data['departemen'] ?? '-') ?></td>
              <td>Alasan</td>
              <td>:</td>
              <td><?= ipcVal($data['alasan'] ?? '-') ?></td>
            </tr>
            <tr>
              <td>Bagian</td>
              <td>:</td>
              <td><?= ipcVal($data['bagian'] ?? '-') ?></td>
              <td>Alasan Lain</td>
              <td>:</td>
              <td><?= ipcVal($data['alasan_lain'] ?? '-') ?></td>
            </tr>
            <tr>
              <td>Jabatan</td>
              <td>:</td>
              <td><?= ipcVal($data['jabatan'] ?? '-') ?></td>
              <td>No HP</td>
              <td>:</td>
              <td><?= ipcVal($data['no_hp'] ?? '-') ?></td>
            </tr>
            <tr>
              <td>Tanggal</td>
              <td>:</td>
              <td><?= ipcFmtDate($data['tanggal'] ?? $data['tgl_pengajuan'] ?? '') ?></td>
              <td>Jam Keluar Aktual</td>
              <td>:</td>
              <td><?= ipcVal($data['jam_keluar_real'] ?? '-') ?></td>
            </tr>
            <tr>
              <td colspan="6" style="height:6px;"></td>
            </tr>
          </table>
        </td>
      </tr>

      <?php if (!empty($data['attachment_filename'])): ?>
        <tr>
          <td style="padding:6px;">
            <table>
              <tr>
                <td style="font-weight:600;width:100px;">Lampiran</td>
                <td style="width:5px;">:</td>
                <td><button type="button" class="btn btn-sm btn-info btn-preview-lampiran"
                    data-path="../../<?= htmlspecialchars($data['attachment_path']) ?>"><i class="fas fa-file-alt"></i>
                    Lihat Dokumen</button></td>
              </tr>
            </table>
          </td>
        </tr>
      <?php endif; ?>

      <tr>
        <td style="padding:6px;">
          <table class="signature-table"
            style="width:100%;border-collapse:collapse;text-align:center;table-layout:fixed;" border="1">
            <tr style="font-weight:bold;font-size:14px;">
              <td style="width:33.33%;padding:4px;">Pemohon</td>
              <td style="width:33.33%;padding:4px;">Mengetahui,</td>
              <td style="width:33.33%;padding:4px;">Menyetujui,</td>
            </tr>
            <tr style="height:120px;vertical-align:middle;">
              <?= ipcRenderTtdCell($ttdPemohon, 'pemohon', $canSignPemohon, $isLocked, $currentUserId, $waitingPemohon) ?>
              <?= ipcRenderTtdCell($ttdAtasan, 'atasan_pemohon', $canSignAtasan, $isLocked, $currentUserId, $waitingAtasan) ?>
              <?= ipcRenderTtdCell($ttdHRD, 'hrd', $canSignHRD, $isLocked, $currentUserId, $waitingHRD) ?>
            </tr>
            <tr style="text-align:center;font-weight:bold;font-size:13px;">
              <?= ipcRenderTtdLabel($ttdPemohon, 'pemohon', 'Pemohon') ?>
              <?= ipcRenderTtdLabel($ttdAtasan, 'atasan_pemohon', 'Atasan Pemohon') ?>
              <?= ipcRenderTtdLabel($ttdHRD, 'hrd', 'HRD') ?>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td style="border-top:1px solid #000;padding:8px 10px;">
          <b>Status</b>
          <div class="mt-2">
            <span class="status-badge badge-<?= $statusBadgeClass ?>"
              style="background:<?= $statusBadgeClass === 'success' ? '#28a745' : '#6c757d' ?>;color:#fff;">
              <?= htmlspecialchars($statusKeluar) ?>
            </span>
            <span style="margin-left:16px;font-size:13px;">
              <?php if (!empty($data['jam_keluar_real'])): ?><b>Keluar:</b>
                <?= htmlspecialchars($data['jam_keluar_real']) ?><?php endif; ?>
            </span>
          </div>
          <?php if ($isApproved && empty($data['jam_keluar_real']) && $canSaveCatatan): ?>
            <div class="mt-3"><button type="button" class="btn btn-sm btn-warning" id="btnScanKeluar"><i
                  class="fas fa-sign-out-alt"></i> Catat Keluar Sekarang</button></div>
          <?php endif; ?>
        </td>
      </tr>

      <tr>
        <td style="padding:0;">
          <table
            style="width:100%;border-collapse:collapse;border:none;border-top:1px solid #000;font-size:10px;text-align:center;">
            <tr>
              <td style="width:50%;padding:4px;border-right:1px solid #000;">SUM-FM-HRD-011</td>
              <td style="width:50%;padding:4px;">ERP</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>

  <div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width:95%;">
      <div class="modal-content shadow-lg" style="border:none;border-radius:8px;overflow:hidden;">
        <div class="modal-header bg-<?= $theme ?> text-white py-2 px-3">
          <h5 class="modal-title font-weight-bold m-0"><i class="fas fa-file-alt mr-2"></i> Preview Lampiran:
            <?= htmlspecialchars($data['attachment_filename'] ?? '') ?>
          </h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body p-0" style="height:80vh;background-color:#525659;"><iframe id="previewIframe" src=""
            style="width:100%;height:100%;border:none;"></iframe></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-<?= $theme ?> text-white">
          <h5 class="modal-title">Buat / Upload Tanda Tangan</h5><button type="button" class="close text-white"
            data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <form id="formTtd" enctype="multipart/form-data"><input type="hidden" name="ticket"
              value="<?= htmlspecialchars($ticket) ?>"><input type="hidden" name="role_code" id="ttdRoleCode"
              value=""><input type="hidden" name="canvas_data" id="canvasData" value="">
            <div class="row">
              <div class="col-md-8 mb-3"><label><b>Gambar di Canvas</b></label><canvas id="ttdCanvas" width="600"
                  height="200"
                  style="border:1px solid #ccc;background:#fff;cursor:crosshair;width:100%;height:200px;"></canvas><button
                  type="button" id="btnClearCanvas" class="btn btn-sm btn-secondary mt-2">Bersihkan</button></div>
              <div class="col-md-4 mb-3"><label><b>Atau Upload File PNG/JPG</b></label><input type="file"
                  name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
                <div class="form-check mt-2"><input type="checkbox" class="form-check-input" id="chkSaveTemplate"
                    name="save_template" value="1" checked><label class="form-check-label" for="chkSaveTemplate">Simpan
                    sebagai template</label></div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary"
            data-dismiss="modal">Batal</button><button type="button" class="btn btn-success" id="btnSimpanTtd">Simpan
            &amp; Gunakan</button></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5><button type="button" class="close text-white"
            data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
          <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary"
            data-dismiss="modal">Batal</button><button type="button" class="btn btn-danger"
            id="btnKonfirmasiHapusTtd">Ya, Hapus</button></div>
      </div>
    </div>
  </div>

  <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
  <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
  <script>
    $(function () {
      var ticket = '<?= htmlspecialchars($ticket, ENT_QUOTES) ?>';
      var isRejected = <?= $isRejected ? 'true' : 'false' ?>;
      var isAdminUser = <?= ((int)($_SESSION['GroupId'] ?? 0) === 1) ? 'true' : 'false' ?>;
      var currentRole = null, roleToDelete = null;
      var canvas = document.getElementById('ttdCanvas');
      var ctx = canvas ? canvas.getContext('2d') : null;
      var drawing = false;
      if (canvas && ctx) { ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; function pos(e) { var r = canvas.getBoundingClientRect(); return { x: (e.clientX || (e.touches && e.touches[0].clientX) || 0) - r.left, y: (e.clientY || (e.touches && e.touches[0].clientY) || 0) - r.top }; } canvas.addEventListener('mousedown', function (e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }); canvas.addEventListener('mousemove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }); canvas.addEventListener('mouseup', function () { drawing = false; }); canvas.addEventListener('mouseleave', function () { drawing = false; }); canvas.addEventListener('touchstart', function (e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }, { passive: false }); canvas.addEventListener('touchmove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }, { passive: false }); canvas.addEventListener('touchend', function () { drawing = false; }); }
      $('#btnClearCanvas').on('click', function () { if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); });
      $('#modalTtd').on('shown.bs.modal', function () { if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); $('#ttdFile').val(''); });

      function signWithManualTemplate(roleCode, templateId) { var fd = new FormData(); fd.append('ticket', ticket); fd.append('role_code', roleCode); fd.append('signature_source', 'manual_template'); fd.append('manual_template_id', templateId); $.ajax({ url: 'ttd_save_template.php', method: 'POST', data: fd, processData: false, contentType: false, success: function (resp) { try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { } if (resp.success && resp.signature_url) { updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); } else { Swal.fire('Gagal', resp.message || 'Gagal menyimpan TTD manual.', 'error'); } }, error: function () { Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); } }); }
      function showAdminTtdChoice(roleCode) { $.getJSON('get_manual_ttd_templates.php', { role_code: roleCode }, function (resp) { var manualTemplates = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : []; if (!manualTemplates.length) { runAccountTtd(roleCode); return; } var options = '<option value="">-- Pilih TTD manual --</option>'; manualTemplates.forEach(function (tpl) { options += '<option value="' + tpl.id + '">' + $('<div>').text(tpl.user_name).html() + '</option>'; }); Swal.fire({ title: 'Pilih Sumber TTD', html: '<p class="text-left mb-2">Anda login sebagai Administrator. Pilih TTD yang akan digunakan.</p><select id="swalManualTemplate" class="form-control" ' + (manualTemplates.length ? '' : 'disabled') + '>' + options + '</select>' + (!manualTemplates.length ? '<small class="text-muted d-block mt-2">Tidak ada template manual aktif untuk role ini.</small>' : ''), icon: 'question', showCancelButton: true, showDenyButton: true, confirmButtonText: 'Pakai TTD Manual', denyButtonText: 'Pakai TTD Akun Saya', cancelButtonText: 'Batal', preConfirm: function () { var val = $('#swalManualTemplate').val(); if (!val) { Swal.showValidationMessage('Pilih TTD manual terlebih dahulu.'); return false; } return val; } }).then(function (result) { if (result.isConfirmed) { signWithManualTemplate(roleCode, result.value); } else if (result.isDenied) { runAccountTtd(roleCode); } }); }).fail(function () { runAccountTtd(roleCode); }); }
      function runAccountTtd(roleCode) { $.post('ttd_sign.php', { ticket: ticket, role_code: roleCode }, function (resp) { if (resp.success && resp.signature_url) { updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); } else if (resp.need_template) { $('#ttdRoleCode').val(roleCode); $('#modalTtd').modal('show'); } else { Swal.fire('Gagal', resp.message || 'Gagal memproses tanda tangan.', 'error'); } }, 'json').fail(function () { Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); }); }
      $(document).on('click', '.btn-ttd', function () { if (isRejected) return; var roleCode = $(this).data('role'); currentRole = roleCode; if (isAdminUser) { showAdminTtdChoice(roleCode); return; } runAccountTtd(roleCode); });
      $(document).on('click', '.btn-delete-ttd', function () { roleToDelete = $(this).data('role'); $('#modalKonfirmasiHapusTtd').modal('show'); });
      $('#btnKonfirmasiHapusTtd').on('click', function () { if (!roleToDelete) return; var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...'); $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function (resp) { $('#modalKonfirmasiHapusTtd').modal('hide'); $btn.prop('disabled', false).html('Ya, Hapus'); if (resp.success) { removeTtdArea(roleToDelete); } else { Swal.fire('Gagal', resp.message || 'Gagal menghapus tanda tangan.', 'error'); } }, 'json').fail(function () { $('#modalKonfirmasiHapusTtd').modal('hide'); $btn.prop('disabled', false).html('Ya, Hapus'); Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); }); });
      $('#btnSimpanTtd').on('click', function () { var roleCode = $('#ttdRoleCode').val() || currentRole; if (!roleCode) { Swal.fire('Error', 'Role tidak dikenal.', 'error'); return; } $('#canvasData').val(canvas ? canvas.toDataURL('image/png') : ''); var fd = new FormData(document.getElementById('formTtd')); var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...'); $.ajax({ url: 'ttd_save_template.php', method: 'POST', data: fd, processData: false, contentType: false, success: function (resp) { try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { } $btn.prop('disabled', false).html('Simpan &amp; Gunakan'); if (resp.success && resp.signature_url) { $('#modalTtd').modal('hide'); updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); } else { Swal.fire('Gagal', resp.message || 'Gagal menyimpan tanda tangan.', 'error'); } }, error: function () { $btn.prop('disabled', false).html('Simpan &amp; Gunakan'); Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); } }); });
      $('#btnScanKeluar').on('click', function () { var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>'); $.post('proses_update.php', { ticket: ticket, update_action: 'scan_keluar' }, function (resp) { $btn.prop('disabled', false).html('<i class="fas fa-sign-out-alt"></i> Catat Keluar Sekarang'); if (resp && resp.success) { Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Jam keluar ' + resp.jam_keluar_real + ' telah dicatat.', timer: 2000, showConfirmButton: false }).then(function () { location.reload(); }); } else { Swal.fire('Gagal', resp.message || 'Gagal mencatat keluar.', 'error'); } }, 'json'); });
      function updateTtdArea(roleCode, imgUrl, name, userId) { if (!roleCode || !imgUrl) return; var selector = '[data-ttd-role="' + roleCode + '"]'; var sep = imgUrl.indexOf('?') === -1 ? '?' : '&'; var displayUrl = imgUrl + sep + 'v=' + Date.now(); $('.ttd-area-slot' + selector).html('<img src="' + displayUrl + '" height="60" alt="TTD">'); if (name) $('.ttd-label-slot' + selector).text(name); if (isRejected) return; var btnDel = $('<button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd">Hapus Tanda Tangan</button>'); btnDel.attr('data-role', roleCode); $('.ttd-btn-slot' + selector).html(btnDel); refreshParentStatus(); }
      function removeTtdArea(roleCode) { if (!roleCode) return; var selector = '[data-ttd-role="' + roleCode + '"]'; $('.ttd-area-slot' + selector).empty(); $('.ttd-label-slot' + selector).each(function () { $(this).text($(this).data('default-label') || ''); }); var btnSign = $('<button type="button" class="btn btn-sm btn-outline-primary btn-ttd">Tanda Tangan</button>'); btnSign.attr('data-role', roleCode); $('.ttd-btn-slot' + selector).html(btnSign); refreshParentStatus(); }
      $(document).on('click', '.btn-preview-lampiran', function () { var filePath = $(this).data('path'); $('#previewIframe').attr('src', filePath); $('#modalPreview').modal('show'); });
      $('#modalPreview').on('hidden.bs.modal', function () { $('#previewIframe').attr('src', ''); });
      function refreshParentStatus() { try { if (window.parent && window.parent.refreshTableRow) window.parent.refreshTableRow(ticket); } catch (e) { } }
    });
  </script>
</body>

</html>