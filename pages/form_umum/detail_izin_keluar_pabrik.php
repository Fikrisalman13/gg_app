<?php
/**
 * Detail Izin Keluar Sementara (IKS)
 * Layout mengikuti formulir fisik SUM-FM-HRD-010
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
  <title>Login Diperlukan - Detail Izin Keluar</title>
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
        Untuk melihat detail lengkap pengajuan izin keluar, riwayat tanda tangan, dan dokumen lampiran, silakan login dengan akun yang memiliki hak akses.
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

// â”€â”€ Query data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sql = "SELECT * FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
  die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

$employees = [];
$detailStmt = sqlsrv_query($conn, "SELECT nik, nama_pemohon, departemen, bagian, jabatan, no_hp FROM Form_Umum_Izin_Keluar_Pabrik_Detail WHERE ticket = ? ORDER BY id", [$ticket]);
if ($detailStmt) {
  while ($employee = sqlsrv_fetch_array($detailStmt, SQLSRV_FETCH_ASSOC)) $employees[] = $employee;
  sqlsrv_free_stmt($detailStmt);
}
if (!$employees) {
  $employees[] = [
    'nik' => $data['nik'] ?? '', 'nama_pemohon' => $data['nama_pemohon'] ?? '',
    'departemen' => $data['departemen'] ?? '', 'bagian' => $data['bagian'] ?? '',
    'jabatan' => $data['jabatan'] ?? '', 'no_hp' => $data['no_hp'] ?? '',
  ];
}

$currentStatus = strtolower(trim($data['status_ticket'] ?? ''));
$isRejected = $currentStatus === 'ditolak';
$isApproved = $currentStatus === 'approved';
$currentUserId = (int) ($_SESSION['UserId'] ?? 0);

// â”€â”€ Determine if IKS or legacy IKP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$isIKS = stripos($ticket, 'IKS-') === 0;
function iksRequiresKadeptIT($ticket, $tglPengajuan)
{
  if (stripos($ticket, 'IKS-') !== 0) return false;
  if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
  $time = strtotime((string)$tglPengajuan);
  return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}
$iksNeedsKadeptIT = iksRequiresKadeptIT($ticket, $data['tgl_pengajuan'] ?? null);

// â”€â”€ Cek role TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function isKaRoleStr($role)
{
  return stripos($role, 'kabag') !== false
    || stripos($role, 'kasie') !== false
    || stripos($role, 'kadept') !== false
    || preg_match('/^ka\./i', trim($role)) === 1;
}

$hasRolePemohon = $hasRoleAtasan = $hasRoleKadeptIT = $hasRolePersonalia = $hasRoleHRD = $hasRoleDanRu = $canSaveCatatan = false;
// â”€â”€ Flag khusus untuk hak tolak (pakai exact-match, BUKAN broad isKaRoleStr) â”€â”€
//   Tujuannya: user yang punya role 'Kabag ICS', 'Kasie', 'Kadept', 'Ka. xxx', atau
//   'Personalia' TIDAK boleh ikut menolak form IKS, walaupun di signing flow
//   mereka boleh TTD di kolom Atasan/HRD.
//   Aturan:
//     â€¢ IKS       â†’ hanya 'Atasan Pemohon' atau 'HRD'  (sesuai permintaan user)
//     â€¢ IKP legacy â†’ 'Atasan Pemohon' atau 'Personalia' (mengikuti flow lama)
$canRejectAsAtasan = $canRejectAsHRD = $canRejectAsPersonalia = false;
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
      if (strcasecmp($role, 'DanRu SATPAM') === 0) {
        $hasRoleDanRu = true;
        $canSaveCatatan = true;
      }
      if (strcasecmp($role, 'HRD') === 0 || strcasecmp($role, 'Personalia') === 0) {
        $hasRoleHRD = true;
        $hasRolePersonalia = true;
      }
      if (stripos($role, 'kadept') !== false) {
        $hasRoleKadeptIT = true;
      }
      if (isKaRoleStr($role)) {
        $hasRoleAtasan = true;
        $hasRolePersonalia = true;
      }
      // Role "Atasan Pemohon" juga boleh TTD di kolom Ka.Sie / Ka.Bag / Ka.Dept
      if (strcasecmp($role, 'Atasan Pemohon') === 0) {
        $hasRoleAtasan = true;
      }

      // â”€â”€ Hak tolak: exact match (tidak turunan dari Ka-role) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      if (strcasecmp($role, 'Atasan Pemohon') === 0) {
        $canRejectAsAtasan = true;
      }
      if (strcasecmp($role, 'HRD') === 0) {
        $canRejectAsHRD = true;
      }
      if (strcasecmp($role, 'Personalia') === 0) {
        $canRejectAsPersonalia = true;
      }
    }
    sqlsrv_free_stmt($stmtRole);
  }
}

// â”€â”€ Ambil TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function isTtdExists($d)
{
  return !empty($d['SignaturePath']);
}
function isTtdByCurrentUser($d, $userId)
{
  if (!isTtdExists($d)) return false;
  $signedBy = (int)($d['SignedByUserId'] ?? 0);
  if ($signedBy === $userId) return true;
  return ((int)($_SESSION['GroupId'] ?? 0) === 1 && $signedBy === 0);
}
function fmtDate($dt)
{
  if ($dt instanceof DateTime)
    return $dt->format('d-m-Y');
  if (is_string($dt) && !empty($dt))
    return date('d-m-Y', strtotime($dt));
  return '-';
}

$ttdPemohon = $ttd['Pemohon'] ?? [];
$ttdAtasan = $ttd['Atasan Pemohon'] ?? [];
$ttdKadeptIT = $ttd['Kadept'] ?? $ttd['Kadept IT'] ?? $ttd['Kadept ACC'] ?? [];
$ttdHRD = $ttd['HRD'] ?? $ttd['Personalia'] ?? [];  // fallback Personalia for legacy IKP
$ttdDanRu = $ttd['DanRu SATPAM'] ?? [];

// Sequential gate
$canSignPemohon = $hasRolePemohon && !isTtdExists($ttdPemohon);
$canSignAtasan = $hasRoleAtasan && isTtdExists($ttdPemohon) && !isTtdExists($ttdAtasan);
$canSignKadeptIT = false;
$waitingKadeptIT = false;
$canSignHRD = false;
$waitingHRD = false;
$canSignDanRu = false;
$waitingDanRu = false;

if ($isIKS) {
  if ($iksNeedsKadeptIT) {
    // IKS baru: Pemohon → Atasan → Kadept → HRD
    $canSignKadeptIT = $hasRoleKadeptIT && isTtdExists($ttdAtasan) && !isTtdExists($ttdKadeptIT);
    $waitingKadeptIT = $hasRoleKadeptIT && !$canSignKadeptIT && !isTtdExists($ttdKadeptIT);
    $canSignHRD = $hasRoleHRD && isTtdExists($ttdKadeptIT) && !isTtdExists($ttdHRD);
  } else {
    // IKS lama: Pemohon → Atasan → HRD
    $canSignHRD = $hasRoleHRD && isTtdExists($ttdAtasan) && !isTtdExists($ttdHRD);
  }
  $waitingHRD = $hasRoleHRD && !$canSignHRD && !isTtdExists($ttdHRD);
  $canSignDanRu = false;
  $waitingDanRu = false;
} else {
  // Legacy IKP: 4-role flow
  $canSignPersonalia = $hasRolePersonalia && isTtdExists($ttdAtasan) && !isTtdExists($ttdHRD);
  $canSignHRD = $canSignPersonalia;
  $waitingHRD = $hasRolePersonalia && !$canSignHRD && !isTtdExists($ttdHRD);
  $canSignDanRu = $hasRoleDanRu && isTtdExists($ttdHRD) && !isTtdExists($ttdDanRu);
  $waitingDanRu = $hasRoleDanRu && !$canSignDanRu && !isTtdExists($ttdDanRu);
}

$waitingPemohon = $hasRolePemohon && !$canSignPemohon && !isTtdExists($ttdPemohon);
$waitingAtasan = $hasRoleAtasan && !$canSignAtasan && !isTtdExists($ttdAtasan);

$isLocked = $isRejected || $isApproved;
if ($isLocked) {
  $canSignPemohon = $canSignAtasan = $canSignKadeptIT = $canSignHRD = $canSignDanRu = false;
  $waitingPemohon = $waitingAtasan = $waitingKadeptIT = $waitingHRD = $waitingDanRu = false;
}

// â”€â”€ Hak tolak: dihitung SERVER (single source of truth) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
//   Aturan per jenis tiket:
//     IKS        â†’ Atasan Pemohon ATAU HRD
//     IKP legacy â†’ Atasan Pemohon ATAU Personalia
//   Variabel ini TIDAK bergantung pada urutan tanda tangan (berbeda dengan
//   $canSignAtasan / $canSignHRD), sehingga user bisa menolak kapan pun selama
//   form belum dikunci (rejected/approved).
if ($isIKS) {
  $canReject = !$isLocked && ($canRejectAsAtasan || $canRejectAsHRD);
} else {
  $canReject = !$isLocked && ($canRejectAsAtasan || $canRejectAsPersonalia);
}

$theme = $_SESSION['Theme'] ?? 'primary';

// â”€â”€ Generate QR Code â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$qrCodeBase64 = '';
try {
  $qrContent = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . '/gg_app/pages/form_umum/scan_action.php?ticket=' . urlencode($ticket);
  $qrObj = new TCPDF2DBarcode($qrContent, 'QRCODE,M');
  $qrPng = $qrObj->getBarcodePngData(4, 4, [0, 0, 0]);
  $qrCodeBase64 = base64_encode($qrPng);
} catch (Exception $e) {
  $qrCodeBase64 = '';
}

// â”€â”€ Status Kembali helper (dynamic calculation) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function iksTimeToMinutes($timeValue)
{
  if ($timeValue instanceof DateTime) {
    return ((int) $timeValue->format('H')) * 60 + (int) $timeValue->format('i');
  }

  $timeValue = trim((string) $timeValue);
  if ($timeValue === '') {
    return null;
  }

  // Accept HH:MM, HH:MM:SS, or datetime-like strings.
  if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?/', $timeValue, $m)) {
    return ((int) $m[1]) * 60 + (int) $m[2];
  }

  return null;
}

function iksIsLateReturn($jamKembaliReal, $estimasiKembali)
{
  $actualMinutes = iksTimeToMinutes($jamKembaliReal);
  $estimateMinutes = iksTimeToMinutes($estimasiKembali);

  if ($actualMinutes === null || $estimateMinutes === null) {
    return false;
  }

  return $actualMinutes > $estimateMinutes;
}

$jamKeluarReal = $data['jam_keluar_real'] ?? null;
$jamKembaliReal = $data['jam_kembali_real'] ?? null;
$estimasiKembali = $data['estimasi_kembali'] ?? null;

// Calculate status dynamically based on scan times. Time-only fields must be
// compared as time-only values; parsing "11:54" with DateTime would attach
// today's date and can incorrectly mark old tickets as late.
if (empty($jamKeluarReal)) {
  $statusKembali = 'Belum Keluar';
  $statusBadgeClass = 'secondary';
} elseif (!empty($jamKeluarReal) && empty($jamKembaliReal)) {
  $statusKembali = 'Sedang Keluar';
  $statusBadgeClass = 'warning';
} elseif (!empty($jamKeluarReal) && !empty($jamKembaliReal)) {
  if (iksIsLateReturn($jamKembaliReal, $estimasiKembali)) {
    $statusKembali = 'Terlambat Kembali';
    $statusBadgeClass = 'danger';
  } else {
    $statusKembali = 'Sudah Kembali';
    $statusBadgeClass = 'success';
  }
}

// â”€â”€ Helper: render satu sel TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function renderTtdCell($ttdData, $roleCode, $canSign, $isLocked, $userId, $waiting = false)
{
  $exists = isTtdExists($ttdData);
  $isMine = isTtdByCurrentUser($ttdData, $userId);
  $role = htmlspecialchars($roleCode);
  $img = $exists
    ? "<img src='" . htmlspecialchars($ttdData['SignaturePath']) . "' height='60' alt='TTD'>"
    : '';
  $btn = '';
  if (!$isLocked) {
    if ($canSign) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-primary btn-ttd'
                            data-role='{$role}'>Tanda Tangan</button>";
    } elseif ($exists && $isMine) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-danger btn-delete-ttd'
                            data-role='{$role}'>Hapus Tanda Tangan</button>";
    } elseif ($exists) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled>Sudah Ditandatangani</button>";
    } elseif ($waiting) {
      $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled
                            title='Menunggu tanda tangan sebelumnya'>Belum giliran</button>";
    }
  } elseif ($exists) {
    $btn = "<button type='button' class='btn btn-sm btn-outline-secondary' disabled>Sudah Ditandatangani</button>";
  }
  return "
<td style='padding:6px; vertical-align:middle;'>
    <div class='ttd-area-slot' data-ttd-role='{$role}'>{$img}</div>
    <div class='ttd-btn-slot mt-2' data-ttd-role='{$role}'>{$btn}</div>
</td>";
}

function renderTtdLabel($ttdData, $roleCode, $defaultLabel)
{
  $name = isTtdExists($ttdData)
    ? htmlspecialchars($ttdData['SignedByUserName'] ?? $defaultLabel)
    : htmlspecialchars($defaultLabel);
  return "<td class='ttd-label-slot' data-ttd-role='" . htmlspecialchars($roleCode) . "'
                data-default-label='" . htmlspecialchars($defaultLabel) . "'>
                {$name}</td>";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Detail IKS â€“ <?= htmlspecialchars($ticket) ?></title>
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
        overflow-x: auto;
      }

      .outer-form {
        min-width: 700px;
      }
    }

    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
  </style>
  <script>
    window.addEventListener('load', function () {
      var canReject = <?= $canReject ? 'true' : 'false'; ?>;
      if (window.parent && window.parent.updateRejectButtonVisibility)
        window.parent.updateRejectButtonVisibility(canReject, '<?= htmlspecialchars($ticket) ?>');
    });
  </script>
</head>

<body>

  <?php if ($isRejected):
    $rejBy = htmlspecialchars($data['rejected_by'] ?? 'N/A');
    $rejAlasan = $data['rejection_reason'] ?? '-';
    $rejDate = $data['rejection_date'] ?? null;
    if ($rejDate instanceof DateTime)
      $rejDateStr = $rejDate->format('d-m-Y H:i');
    elseif (is_string($rejDate) && $rejDate)
      $rejDateStr = $rejDate;
    else
      $rejDateStr = '-';
    ?>
    <div style="border-left:5px solid #dc3545;background:#fff5f5;padding:16px 20px;
            border-radius:8px;max-width:900px;margin:10px auto 18px;">
      <div style="color:#dc3545;font-size:1.15rem;font-weight:700;margin-bottom:10px;
                border-bottom:1px solid rgba(220,53,69,.2);padding-bottom:8px;">
        <i class="fas fa-times-circle"></i> PENGAJUAN DITOLAK
      </div>
      <div class="mb-2"><strong style="min-width:140px;display:inline-block;">Ditolak oleh</strong>: <?= $rejBy ?></div>
      <div class="mb-2"><strong style="min-width:140px;display:inline-block;">Tanggal</strong>:
        <?= htmlspecialchars($rejDateStr) ?>
      </div>
      <div><strong style="min-width:140px;display:inline-block;">Alasan</strong>:
        <div style="background:#ffecec;padding:10px 14px;border-radius:6px;border:1px solid #f5c6cb;
                    margin-top:6px;font-style:italic;color:#721c24;">
          <?= nl2br(htmlspecialchars($rejAlasan)) ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="form-wrapper" style="max-width:900px;margin:10px auto;">
    <table class="outer-form"
      style="width:100%; border:1px solid #000; border-collapse:collapse; font-size:14px; line-height:1.4;">

      <!-- â”€â”€ Kop Surat â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <tr>
        <td style="padding:0;">
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td style="width:90px;border-right:1px solid #000;padding:8px 6px;
                   text-align:center;vertical-align:middle;">
                <img src="../../dist/img/sumlogo.png" width="70" alt="logo">
              </td>
              <td style="padding:6px 10px;vertical-align:middle;text-align:center;">
                <div style="font-size:12px;">PT. SURYA USAHA MANDIRI</div>
                <div style="font-size:10px;">FORMULIR</div>
                <div style="font-size:15px;font-weight:bold;line-height:1.3;">SURAT IZIN
                  KELUAR<?= $isIKS ? '<br>SEMENTARA' : '<br>PABRIK' ?></div>
              </td>
              <td style="width:150px;border-left:1px solid #000;padding:6px 8px;
                   vertical-align:middle;font-size:11px;">
                No : SUM-FM-HRD-010<br>Rev : 0<br>Hal : 1/1

              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- â”€â”€ Data Form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <tr>
        <td style="border-top:1px solid #000;padding:0;">
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td style="width:120px;padding:6px 8px;">Nomor Pengajuan</td>
              <td style="width:10px;padding:6px 0;">:</td>
              <td style="padding:6px 8px; font-weight:bold;"><?= htmlspecialchars($ticket) ?></td>
              <td style="width:130px;padding:6px 8px;text-align:right;white-space:nowrap;">
                Tgl. Pengajuan&nbsp;:&nbsp;<?= fmtDate($data['tgl_pengajuan'] ?? '') ?>
              </td>
            </tr>
          </table>
          <div style="padding:6px 8px 10px;font-style:italic;text-decoration:underline;">
            Diberikan kepada tersebut di bawah ini :
          </div>
          <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
            <!-- 2 kolom: kiri = data karyawan & jadwal, kanan = detail perizinan -->
            <colgroup>
              <col style="width:120px">
              <col style="width:5px">
              <col style="width:35%">
              <col style="width:130px">
              <col style="width:5px">
              <col style="width:auto">
            </colgroup>
            <tr>
              <td colspan="6" style="padding:4px 8px 2px; font-weight:bold;">Daftar Karyawan (<?= count($employees) ?> orang)</td>
            </tr>
            <tr>
              <td colspan="6" style="padding:0 8px 8px;">
                <table style="width:100%; border-collapse:collapse; table-layout:fixed; font-size:0.92em;">
                  <tr style="background:#f1f3f5;">
                    <th style="width:6%; border:1px solid #adb5bd; padding:4px; text-align:center;">No</th>
                    <th style="width:34%; border:1px solid #adb5bd; padding:4px; text-align:left;">Nama Karyawan</th>
                    <th style="width:20%; border:1px solid #adb5bd; padding:4px; text-align:left;">NIK</th>
                    <th style="width:18%; border:1px solid #adb5bd; padding:4px; text-align:left;">Jabatan</th>
                    <th style="width:22%; border:1px solid #adb5bd; padding:4px; text-align:left;">No. HP</th>
                  </tr>
                  <?php foreach ($employees as $employeeIndex => $employee): ?>
                    <tr>
                      <td style="border:1px solid #adb5bd; padding:4px; text-align:center;"><?= $employeeIndex + 1 ?></td>
                      <td style="border:1px solid #adb5bd; padding:4px; word-break:normal;"><?= htmlspecialchars($employee['nama_pemohon'] ?? '-') ?></td>
                      <td style="border:1px solid #adb5bd; padding:4px; white-space:nowrap;"><?= htmlspecialchars($employee['nik'] ?? '-') ?></td>
                      <td style="border:1px solid #adb5bd; padding:4px;"><?= htmlspecialchars($employee['jabatan'] ?? '-') ?></td>
                      <td style="border:1px solid #adb5bd; padding:4px; white-space:nowrap;"><?= htmlspecialchars($employee['no_hp'] ?? '-') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:4px 8px; vertical-align:top;">Departemen</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word;"><?= htmlspecialchars($data['departemen'] ?? '-') ?></td>
              <td style="padding:4px 8px; vertical-align:top;">Estimasi Kembali</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word;"><?= htmlspecialchars($data['estimasi_kembali'] ?? $data['jam_keluar_sampai'] ?? '-') ?></td>
            </tr>
            <tr>
              <td style="padding:4px 8px; vertical-align:top;">Bagian</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word;"><?= htmlspecialchars($data['bagian'] ?? '-') ?></td>
              <td style="padding:4px 8px; vertical-align:top;">Tujuan</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word; word-break:break-word;"><?= htmlspecialchars($data['tujuan'] ?? '-') ?></td>
            </tr>
            <tr>
              <td style="padding:4px 8px; vertical-align:top;">Tanggal Keluar</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top;"><?= fmtDate($data['tgl_keluar'] ?? $data['tgl_pengajuan'] ?? '') ?></td>
              <td style="padding:4px 8px; vertical-align:top;">Keperluan</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word; word-break:break-word;"><?php
              $kep = $data['keperluan'] ?? '';
              $kepLain = $data['keperluan_lain'] ?? '';
              echo htmlspecialchars($kep === 'Lainnya' && $kepLain ? "Lainnya: $kepLain" : ($kep ?: '-'));
              ?></td>
            </tr>
            <tr>
              <td style="padding:4px 8px; vertical-align:top;">Jam Keluar</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top;"><?= htmlspecialchars($data['jam_keluar'] ?? $data['jam_keluar_dari'] ?? '-') ?></td>
              <td style="padding:4px 8px; vertical-align:top;">Kendaraan</td>
              <td style="padding:4px 0; vertical-align:top;">:</td>
              <td style="padding:4px 8px; vertical-align:top; word-wrap:break-word; word-break:break-word;"><?php
              $kend = $data['kendaraan'] ?? '';
              $kendLain = $data['kendaraan_lain'] ?? '';
              echo htmlspecialchars($kend === 'Lainnya' && $kendLain ? "Lainnya: $kendLain" : ($kend ?: '-'));
              ?></td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- â”€â”€ Lampiran Section â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <?php if (!empty($data['attachment_filename'])): ?>
        <tr>
          <td style="padding:6px;">
            <table style="width:100%; border-collapse:collapse;">
              <tr>
                <td style="padding:4px 8px; font-weight:600; width:100px;">Lampiran</td>
                <td style="padding:4px 0; width:5px;">:</td>
                <td style="padding:4px 8px;">
                  <button type="button" class="btn btn-sm btn-info btn-preview-lampiran"
                    data-path="../../<?= htmlspecialchars($data['attachment_path']) ?>" style="margin-left:5px;">
                    <i class="fas fa-file-alt"></i> Lihat Dokumen
                  </button>

                </td>
              </tr>
            </table>
          </td>
        </tr>
      <?php endif; ?>

      <!-- â”€â”€ Tabel TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <tr>
        <td style="padding:6px;">
          <table class="signature-table"
            style="width:100%; border-collapse:collapse; text-align:center; table-layout:fixed;" border="1">
            <!-- Header row -->
            <tr style="font-weight:bold; font-size:14px;">
              <?php if ($iksNeedsKadeptIT): ?>
                <td style="width:25%; padding:4px;">Pemohon</td>
                <td colspan="2" style="width:50%; padding:4px;">Mengetahui,</td>
                <td style="width:25%; padding:4px;">Menyetujui,</td>
              <?php elseif ($isIKS): ?>
                <td style="width:33.33%; padding:4px;">Pemohon</td>
                <td style="width:33.33%; padding:4px;">Mengetahui,</td>
                <td style="width:33.33%; padding:4px;">Menyetujui,</td>
              <?php else: ?>
                <td style="width:25%; padding:4px;">Pemohon</td>
                <td style="width:25%; padding:4px;">Mengetahui,</td>
                <td style="width:25%; padding:4px;">Menyetujui,</td>
                <td style="width:25%; padding:4px;">Menyetujui,</td>
              <?php endif; ?>
            </tr>
            <!-- Gambar TTD -->
            <tr style="height:120px;vertical-align:middle;">
              <?= renderTtdCell($ttdPemohon, 'pemohon', $canSignPemohon, $isLocked, $currentUserId, $waitingPemohon) ?>
              <?= renderTtdCell($ttdAtasan, 'atasan_pemohon', $canSignAtasan, $isLocked, $currentUserId, $waitingAtasan) ?>
              <?php if ($iksNeedsKadeptIT): ?>
                <?= renderTtdCell($ttdKadeptIT, 'kadept', $canSignKadeptIT, $isLocked, $currentUserId, $waitingKadeptIT) ?>
              <?php endif; ?>
              <?= renderTtdCell($ttdHRD, $isIKS ? 'hrd' : 'personalia', $canSignHRD, $isLocked, $currentUserId, $waitingHRD) ?>
              <?php if (!$isIKS): ?>
                <?= renderTtdCell($ttdDanRu, 'danru_satpam', $canSignDanRu, $isLocked, $currentUserId, $waitingDanRu) ?>
              <?php endif; ?>
            </tr>
            <!-- Label -->
            <tr style="text-align:center; font-weight:bold; font-size:13px;">
              <?= renderTtdLabel($ttdPemohon, 'pemohon', 'Pemohon') ?>
              <?= renderTtdLabel($ttdAtasan, 'atasan_pemohon', 'Atasan Pemohon') ?>
              <?php if ($iksNeedsKadeptIT): ?>
                <?= renderTtdLabel($ttdKadeptIT, 'kadept', 'Kadept') ?>
              <?php endif; ?>
              <?= renderTtdLabel($ttdHRD, $isIKS ? 'hrd' : 'personalia', $isIKS ? 'HRD' : 'Ka.Bag/Ka.Dept Personalia') ?>
              <?php if (!$isIKS): ?>
                <?= renderTtdLabel($ttdDanRu, 'danru_satpam', 'DanRu SATPAM') ?>
              <?php endif; ?>
            </tr>
          </table>
        </td>
      </tr>

      <!-- â”€â”€ Status Monitoring Kehadiran (IKS only) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <?php if ($isIKS): ?>
        <tr>
          <td style="border-top:1px solid #000;padding:8px 10px;">
            <b>Status Kehadiran</b>
            <div class="mt-2">
              <span class="status-badge badge-<?= $statusBadgeClass ?>" style="background:<?= match ($statusBadgeClass) {
                  'warning' => '#ffc107',
                  'success' => '#28a745',
                  'danger' => '#dc3545',
                  default => '#6c757d'
                } ?>;color:<?= $statusBadgeClass === 'warning' ? '#212529' : '#fff' ?>">
                <?= htmlspecialchars($statusKembali) ?>
              </span>
              <span style="margin-left:16px;font-size:13px;">
                <?php if ($jamKeluarReal): ?>
                  <b>Keluar:</b> <?= htmlspecialchars($jamKeluarReal) ?>
                <?php endif; ?>
                <?php if ($jamKembaliReal): ?>
                  &nbsp;&nbsp;<b>Kembali:</b> <?= htmlspecialchars($jamKembaliReal) ?>
                <?php endif; ?>
              </span>
            </div>

            <?php if ($isApproved && !$jamKeluarReal && ($hasRoleDanRu || $canSaveCatatan)): ?>
              <!-- Tombol scan manual untuk Satpam (fallback jika QR tidak terscan) -->
              <div class="mt-3">
                <button type="button" class="btn btn-sm btn-warning" id="btnScanKeluar">
                  <i class="fas fa-sign-out-alt"></i> Catat Keluar Sekarang
                </button>
              </div>
            <?php elseif ($isApproved && $jamKeluarReal && !$jamKembaliReal && ($hasRoleDanRu || $canSaveCatatan)): ?>
              <div class="mt-3">
                <button type="button" class="btn btn-sm btn-success" id="btnScanKembali">
                  <i class="fas fa-sign-in-alt"></i> Catat Kembali Sekarang
                </button>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endif; ?>

      <!-- â”€â”€ Footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <tr>
        <td style="padding:0;">
          <table
            style="width:100%; border-collapse:collapse; border:none; border-top:1px solid #000; font-size:10px; text-align:center;">
            <tr>
              <td style="width:50%;padding:4px;border-right:1px solid #000;">SUM-FM-HRD-010</td>
              <td style="width:50%;padding:4px;">ERP</td>
            </tr>
          </table>
        </td>
      </tr>

    </table>
  </div><!-- /.form-wrapper -->

  <!-- â”€â”€ Modal Preview Lampiran â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" aria-labelledby="modalPreviewLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95%;">
      <div class="modal-content shadow-lg" style="border: none; border-radius: 8px; overflow: hidden;">
        <div
          class="modal-header bg-<?= $theme ?> text-white d-flex align-items-center justify-content-between py-2 px-3">
          <h5 class="modal-title font-weight-bold m-0" id="modalPreviewLabel"
            style="font-size: 1.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
            <i class="fas fa-file-alt mr-2"></i> Preview Lampiran:
            <?= htmlspecialchars($data['attachment_filename'] ?? '') ?>
          </h5>
          <div class="d-flex align-items-center" style="gap: 10px;">
            <a href="../../<?= htmlspecialchars($data['attachment_path'] ?? '') ?>"
              class="btn btn-sm btn-light font-weight-bold"
              download="<?= htmlspecialchars($data['attachment_filename'] ?? '') ?>">
              <i class="fas fa-download mr-1"></i> Unduh File
            </a>
            <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close"
              style="opacity: 0.8; font-size: 1.5rem; background: none; border: none; padding: 0;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class="modal-body p-0" style="height: 80vh; background-color: #525659;">
          <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <!-- â”€â”€ Modal TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-<?= $theme ?> text-white">
          <h5 class="modal-title">Buat / Upload Tanda Tangan</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <form id="formTtd" enctype="multipart/form-data">
            <input type="hidden" name="ticket" value="<?= htmlspecialchars($ticket) ?>">
            <input type="hidden" name="role_code" id="ttdRoleCode" value="">
            <input type="hidden" name="canvas_data" id="canvasData" value="">
            <div class="row">
              <div class="col-md-8 mb-3">
                <label><b>Gambar di Canvas</b></label>
                <canvas id="ttdCanvas" width="600" height="200"
                  style="border:1px solid #ccc;background:#fff;cursor:crosshair;width:100%;height:200px;">
                </canvas>
                <button type="button" id="btnClearCanvas" class="btn btn-sm btn-secondary mt-2">Bersihkan</button>
              </div>
              <div class="col-md-4 mb-3">
                <label><b>Atau Upload File PNG/JPG</b></label>
                <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
                <div class="form-check mt-2">
                  <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1"
                    checked>
                  <label class="form-check-label" for="chkSaveTemplate">Simpan sebagai template</label>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-success" id="btnSimpanTtd">Simpan &amp; Gunakan</button>
        </div>
      </div>
    </div>
  </div>

  <!-- â”€â”€ Modal Konfirmasi Hapus TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
          <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus</button>
        </div>
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
      var currentRole = null;
      var roleToDelete = null;

      // â”€â”€ Canvas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      var canvas = document.getElementById('ttdCanvas');
      var ctx = canvas ? canvas.getContext('2d') : null;
      var drawing = false;
      if (canvas && ctx) {
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#000';
        function getPos(e) {
          var r = canvas.getBoundingClientRect();
          return {
            x: (e.clientX || (e.touches && e.touches[0].clientX) || 0) - r.left,
            y: (e.clientY || (e.touches && e.touches[0].clientY) || 0) - r.top
          };
        }
        canvas.addEventListener('mousedown', function (e) { drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
        canvas.addEventListener('mousemove', function (e) { if (!drawing) return; var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
        canvas.addEventListener('mouseup', function () { drawing = false; });
        canvas.addEventListener('mouseleave', function () { drawing = false; });
        canvas.addEventListener('touchstart', function (e) { drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchmove', function (e) { if (!drawing) return; var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchend', function () { drawing = false; });
      }
      $('#btnClearCanvas').on('click', function () { if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); });
      $('#modalTtd').on('shown.bs.modal', function () { if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); $('#ttdFile').val(''); });

      function signWithManualTemplate(roleCode, templateId) {
        var fd = new FormData();
        fd.append('ticket', ticket);
        fd.append('role_code', roleCode);
        fd.append('signature_source', 'manual_template');
        fd.append('manual_template_id', templateId);
        $.ajax({
          url: 'ttd_save_template.php', method: 'POST', data: fd,
          processData: false, contentType: false,
          success: function (resp) {
            try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { }
            if (resp.success && resp.signature_url) {
              updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            } else {
              Swal.fire('Gagal', resp.message || 'Gagal menyimpan TTD manual.', 'error');
            }
          },
          error: function () { Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); }
        });
      }

      function showAdminTtdChoice(roleCode) {
        $.getJSON('get_manual_ttd_templates.php', { role_code: roleCode }, function (resp) {
          var manualTemplates = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
          if (!manualTemplates.length) { runAccountTtd(roleCode); return; }
          var options = '<option value="">-- Pilih TTD manual --</option>';
          manualTemplates.forEach(function (tpl) {
            options += '<option value="' + tpl.id + '">' + $('<div>').text(tpl.user_name).html() + '</option>';
          });
          Swal.fire({
            title: 'Pilih Sumber TTD',
            html: '<p class="text-left mb-2">Anda login sebagai Administrator. Pilih TTD yang akan digunakan.</p>' +
                  '<select id="swalManualTemplate" class="form-control" ' + (manualTemplates.length ? '' : 'disabled') + '>' + options + '</select>' +
                  (!manualTemplates.length ? '<small class="text-muted d-block mt-2">Tidak ada template manual aktif untuk role ini.</small>' : ''),
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Pakai TTD Manual',
            denyButtonText: 'Pakai TTD Akun Saya',
            cancelButtonText: 'Batal',
            preConfirm: function () {
              var val = $('#swalManualTemplate').val();
              if (!val) { Swal.showValidationMessage('Pilih TTD manual terlebih dahulu.'); return false; }
              return val;
            }
          }).then(function (result) {
            if (result.isConfirmed) {
              signWithManualTemplate(roleCode, result.value);
            } else if (result.isDenied) {
              runAccountTtd(roleCode);
            }
          });
        }).fail(function () { runAccountTtd(roleCode); });
      }

      function runAccountTtd(roleCode) {
        $.post('ttd_sign.php', { ticket: ticket, role_code: roleCode }, function (resp) {
          if (resp.success && resp.signature_url) {
            updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
          } else if (resp.need_template) {
            $('#ttdRoleCode').val(roleCode);
            $('#modalTtd').modal('show');
          } else {
            Swal.fire('Gagal', resp.message || 'Gagal memproses tanda tangan.', 'error');
          }
        }, 'json').fail(function () {
          Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error');
        });
      }

      // â”€â”€ Tombol Tanda Tangan â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $(document).on('click', '.btn-ttd', function () {
        if (isRejected) return;
        var roleCode = $(this).data('role');
        currentRole = roleCode;
        if (isAdminUser) {
          showAdminTtdChoice(roleCode);
          return;
        }
        runAccountTtd(roleCode);
      });

      // â”€â”€ Tombol Hapus TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $(document).on('click', '.btn-delete-ttd', function () {
        roleToDelete = $(this).data('role');
        $('#modalKonfirmasiHapusTtd').modal('show');
      });
      $('#btnKonfirmasiHapusTtd').on('click', function () {
        if (!roleToDelete) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
        $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function (resp) {
          $('#modalKonfirmasiHapusTtd').modal('hide');
          $btn.prop('disabled', false).html('Ya, Hapus');
          if (resp.success) {
            removeTtdArea(roleToDelete);
          } else {
            Swal.fire('Gagal', resp.message || 'Gagal menghapus tanda tangan.', 'error');
          }
        }, 'json').fail(function () {
          $('#modalKonfirmasiHapusTtd').modal('hide');
          $btn.prop('disabled', false).html('Ya, Hapus');
          Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error');
        });
      });

      // â”€â”€ Simpan TTD dari modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $('#btnSimpanTtd').on('click', function () {
        var roleCode = $('#ttdRoleCode').val() || currentRole;
        if (!roleCode) { Swal.fire('Error', 'Role tidak dikenal.', 'error'); return; }
        $('#canvasData').val(canvas ? canvas.toDataURL('image/png') : '');
        var fd = new FormData(document.getElementById('formTtd'));
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        $.ajax({
          url: 'ttd_save_template.php', method: 'POST', data: fd,
          processData: false, contentType: false,
          success: function (resp) {
            try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { }
            $btn.prop('disabled', false).html('Simpan &amp; Gunakan');
            if (resp.success && resp.signature_url) {
              $('#modalTtd').modal('hide');
              updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            } else {
              Swal.fire('Gagal', resp.message || 'Gagal menyimpan tanda tangan.', 'error');
            }
          },
          error: function () {
            $btn.prop('disabled', false).html('Simpan &amp; Gunakan');
            Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error');
          }
        });
      });

      // â”€â”€ Scan Keluar (tombol manual Satpam) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $('#btnScanKeluar').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('proses_update.php', { ticket: ticket, update_action: 'scan_keluar' }, function (resp) {
          $btn.prop('disabled', false).html('<i class="fas fa-sign-out-alt"></i> Catat Keluar Sekarang');
          if (resp && resp.success) {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Jam keluar ' + resp.jam_keluar_real + ' telah dicatat.', timer: 2000, showConfirmButton: false })
              .then(function () { location.reload(); });
          } else {
            Swal.fire('Gagal', resp.message || 'Gagal mencatat keluar.', 'error');
          }
        }, 'json');
      });

      // â”€â”€ Scan Kembali (tombol manual Satpam) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $('#btnScanKembali').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('proses_update.php', { ticket: ticket, update_action: 'scan_kembali' }, function (resp) {
          $btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Catat Kembali Sekarang');
          if (resp && resp.success) {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Jam kembali ' + resp.jam_kembali_real + ' (' + resp.status_kembali + ')', timer: 2500, showConfirmButton: false })
              .then(function () { location.reload(); });
          } else {
            Swal.fire('Gagal', resp.message || 'Gagal mencatat kembali.', 'error');
          }
        }, 'json');
      });

      // â”€â”€ Helper: update area TTD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      function updateTtdArea(roleCode, imgUrl, name, userId) {
        if (!roleCode || !imgUrl) return;
        var selector = '[data-ttd-role="' + roleCode + '"]';
        var separator = imgUrl.indexOf('?') === -1 ? '?' : '&';
        var displayUrl = imgUrl + separator + 'v=' + Date.now();
        $('.ttd-area-slot' + selector).html('<img src="' + displayUrl + '" height="60" alt="TTD">');
        if (name) $('.ttd-label-slot' + selector).text(name);
        if (isRejected) return;
        var btnDel = $('<button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd">Hapus Tanda Tangan</button>');
        btnDel.attr('data-role', roleCode);
        $('.ttd-btn-slot' + selector).html(btnDel);
        refreshParentStatus();
      }

      function removeTtdArea(roleCode) {
        if (!roleCode) return;
        var selector = '[data-ttd-role="' + roleCode + '"]';
        $('.ttd-area-slot' + selector).empty();
        $('.ttd-label-slot' + selector).each(function () {
          $(this).text($(this).data('default-label') || '');
        });
        var btnSign = $('<button type="button" class="btn btn-sm btn-outline-primary btn-ttd">Tanda Tangan</button>');
        btnSign.attr('data-role', roleCode);
        $('.ttd-btn-slot' + selector).html(btnSign);
        refreshParentStatus();
      }

      // â”€â”€ Lampiran Modal Preview Handler â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
      $(document).on('click', '.btn-preview-lampiran', function () {
        var filePath = $(this).data('path');
        $('#previewIframe').attr('src', filePath);
        $('#modalPreview').modal('show');
      });

      $('#modalPreview').on('hidden.bs.modal', function () {
        $('#previewIframe').attr('src', '');
      });

      function refreshParentStatus() {
        try {
          if (window.parent && window.parent.refreshTableRow)
            window.parent.refreshTableRow(ticket);
        } catch (e) { }
      }
    });
  </script>
</body>

</html>
