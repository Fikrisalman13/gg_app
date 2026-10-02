<?php
/**
 * scan_action.php
 * URL yang ter-embed di QR Code IKS.
 * Satpam scan QR -> halaman ini -> konfirmasi -> update status keluar / kembali.
 */
$scanPublicAccess = true;
$GLOBALS['scanPublicAccess'] = true;

require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/barcode_token_helper.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Theme helpers (match pages/issues & pages/asset pattern) ======
$themeColor = $_SESSION['Theme'] ?? 'primary';
$lightThemeOptions = ['warning', 'light', 'lime', 'white'];
$isLightTheme = in_array($themeColor, $lightThemeOptions, true);
$headerTextClass = $isLightTheme ? 'text-dark' : 'text-white';
$badgeTextClass = $isLightTheme ? 'text-dark' : 'text-white';

$rawTicket = trim($_GET['ticket'] ?? $_POST['ticket'] ?? '');
$ticket = formUmumBarcodeResolveTicket($conn, $rawTicket);
if ($ticket === '') {
  $ticket = strtoupper($rawTicket);
}
$isIPCScan = stripos($ticket, 'IPC-') === 0;

function renderScanErrorPage($ticketDisplay, $title, $message, $type = 'warning')
{
  global $conn, $scanPublicAccess, $themeColor, $headerTextClass;
  $themeColor = $themeColor ?? 'primary';
  $headerTextClass = $headerTextClass ?? 'text-white';
  $icon = ($type === 'danger') ? 'fa-exclamation-triangle text-danger' : 'fa-search-minus text-warning';
  $cardOutline = ($type === 'danger') ? 'card-danger' : 'card-warning';

  include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
  if (!empty($_SESSION['UserId'])) {
    include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
  }
  ?>
  <style>
    .wrapper { background-color: #f4f6f9 !important; }
    .content-wrapper { background-color: #f4f6f9 !important; min-height: calc(100vh - 100px); }
    body.layout-top-nav .content-wrapper,
    body.layout-top-nav .main-header,
    body.layout-top-nav .main-footer {
      margin-left: 0 !important;
    }
  </style>
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-7">
            <h1 class="m-0">
              <i class="fas fa-qrcode text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>
              Scan Form Umum
            </h1>
          </div>
          <div class="col-sm-5">
            <ol class="breadcrumb float-sm-right mb-0">
              <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
              <li class="breadcrumb-item"><a href="scan_index.php">Scan Form Umum</a></li>
              <li class="breadcrumb-item active">Hasil Scan</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-lg-7 col-md-9">
            <div class="card <?= $cardOutline ?> card-outline shadow-sm mt-3">
              <div class="card-body text-center p-4">
                <div class="my-3">
                  <i class="fas <?= $icon ?>" style="font-size: 3.5rem;"></i>
                </div>
                <h4 class="font-weight-bold text-dark mb-2"><?= htmlspecialchars($title) ?></h4>
                <p class="text-muted mb-4"><?= $message ?></p>

                <div class="d-flex justify-content-center flex-wrap" style="gap: 10px;">
                  <a href="scan_index.php" class="btn btn-primary font-weight-bold px-4">
                    <i class="fas fa-barcode mr-1"></i> Scan Tiket Lain
                  </a>
                  <a href="/gg_app/index.php" class="btn btn-default px-3">
                    <i class="fas fa-home mr-1"></i> Beranda
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php
  include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php');
  exit;
}

if ($isIPCScan) {
  $sqlIPC = "SELECT ticket, nik, nama_pemohon, departemen, bagian, jabatan, no_hp,
                    tanggal, jam_pulang_normal, jam_pulang_diminta, alasan, alasan_lain,
                    status_ticket, status_keluar, jam_keluar_real
             FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
  $stmtIPC = sqlsrv_query($conn, $sqlIPC, [$ticket]);
  if (!$stmtIPC || !sqlsrv_has_rows($stmtIPC)) {
    renderScanErrorPage($ticket, 'Tiket IPC Tidak Ditemukan', 'Data tiket IPC <strong>' . htmlspecialchars($ticket) . '</strong> tidak ditemukan di sistem. Pastikan nomor tiket atau kode barcode yang dipindai sudah benar.');
  }
  $dataIPC = sqlsrv_fetch_array($stmtIPC, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($stmtIPC);

  $statusTicket = strtolower(trim($dataIPC['status_ticket'] ?? ''));
  $updatedBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'Security';
  $msg = '';
  $ok = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan_keluar') {
    if ($statusTicket !== 'approved') {
      $msg = 'Tiket belum approved.';
    } elseif (!empty($dataIPC['jam_keluar_real'])) {
      $msg = 'Sudah tercatat keluar pukul ' . htmlspecialchars($dataIPC['jam_keluar_real']);
    } else {
      $nowTime = date('H:i');
      $stmtU = sqlsrv_query($conn,
        "UPDATE Form_Umum_Izin_Pulang_Cepat
         SET jam_keluar_real = ?, status_keluar = 'Sudah Keluar', updated_at = GETDATE(), updated_by = ?
         WHERE ticket = ? AND jam_keluar_real IS NULL",
        [$nowTime, $updatedBy, $ticket]
      );
      $ok = ($stmtU !== false && sqlsrv_rows_affected($stmtU) > 0);
      $msg = $ok ? "Berhasil dicatat keluar pukul $nowTime." : 'Gagal mencatat. Coba lagi.';
      if ($ok) {
        $dataIPC['jam_keluar_real'] = $nowTime;
        $dataIPC['status_keluar'] = 'Sudah Keluar';
      }
    }
  }

  $tglDisplay = '-';
  if (!empty($dataIPC['tanggal'])) {
    $tglDisplay = $dataIPC['tanggal'] instanceof DateTime ? $dataIPC['tanggal']->format('d-m-Y') : date('d-m-Y', strtotime((string) $dataIPC['tanggal']));
  }
  $isApprovedIPC = $statusTicket === 'approved';
  $sudahKeluarIPC = !empty($dataIPC['jam_keluar_real']);
  $canScanKeluarIPC = $isApprovedIPC && !$sudahKeluarIPC;
  $statusKeluarText = $sudahKeluarIPC ? 'Sudah Keluar' : 'Belum Keluar';
  $statusBadge = $sudahKeluarIPC ? 'badge-success' : 'badge-secondary';
  $msgClass = $ok ? 'alert-success' : 'alert-warning';

  $ttdData = [];
  $ttdStmt = sqlsrv_query(
    $conn,
    "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId
     FROM Form_Umum_TTD WHERE Ticket = ?",
    [$ticket]
  );
  if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
      $ttdData[] = $r;
    }
    sqlsrv_free_stmt($ttdStmt);
  }

  function ipcScanTtdImg($row)
  {
    $path = $row['SignaturePath'] ?? '';
    if ($path === '') return '';
    return $path;
  }

  include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
  if (!empty($_SESSION['UserId'])) {
    include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
  }
  ?>
  <style>
    .wrapper { background-color: #f4f6f9 !important; }
    .content-wrapper { background-color: #f4f6f9 !important; min-height: calc(100vh - 100px); }
    body.layout-top-nav .content-wrapper,
    body.layout-top-nav .main-header,
    body.layout-top-nav .main-footer {
      margin-left: 0 !important;
    }
    .content-header { padding: 15px 0.5rem; }
    .section-header{padding:10px 14px;background:#f4f6f9;border-bottom:1px solid #dee2e6;font-weight:700;color:#495057;text-transform:uppercase;font-size:.78rem;letter-spacing:.02em}
    .col-divider-right{border-right:1px solid #dee2e6}
    .info-table{width:100%;margin:0;border-collapse:collapse}
    .info-table tr{border-bottom:1px solid #f1f3f5}
    .info-table tr:last-child{border-bottom:none}
    .info-table td{padding:9px 14px;vertical-align:top}
    .info-label{width:145px;color:#6c757d;font-weight:700;font-size:.82rem;background:#fbfcfd}
    .info-val{color:#212529;font-weight:600}
    .control-panel{border-top:1px solid #dee2e6;background:#fff;padding:12px 14px}
    .control-panel-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .status-cluster{display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap}
    .status-label{display:block;font-size:.72rem;color:#6c757d;font-weight:700;text-transform:uppercase;line-height:1;margin-bottom:3px}
    .status-time{font-size:.9rem;font-weight:700}
    .action-slot{text-align:right;margin-left:auto}
    .btn-action-main{font-weight:700;box-shadow:0 8px 18px rgba(0,0,0,.12);padding:8px 18px}
    .action-time{display:inline-block;margin-left:8px;padding-left:8px;border-left:1px solid rgba(255,255,255,.45)}
    .control-panel-bottom{margin-top:12px;padding-top:10px;border-top:1px dashed #dee2e6;display:flex;gap:6px;flex-wrap:wrap}
    .clickable{cursor:pointer}
    .signature-table-modal{width:100%;border-collapse:collapse;table-layout:fixed;text-align:center}
    .signature-table-modal td{border:1px solid #dee2e6;padding:8px;vertical-align:middle}
    .signature-table-modal thead td{font-weight:700;background:#f8f9fa;color:#495057}
    .ttd-img-cell{height:100px;background:#fff;overflow:hidden}
    .ttd-cell-img{max-width:92%;max-height:85px;object-fit:contain;display:block;margin:0 auto}
    .ttd-placeholder{color:#adb5bd;font-size:.85rem;font-style:italic}
    .ttd-name-cell{font-weight:700;font-size:.9rem;background:#fbfcfd}
    @media(max-width:768px){.col-divider-right{border-right:none;border-bottom:1px solid #dee2e6}.control-panel-top{align-items:flex-start}.action-slot{text-align:left;margin-left:0}.info-label{width:125px}}
  </style>
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-7">
            <h1 class="m-0"><i class="fas fa-qrcode text-<?= htmlspecialchars($themeColor) ?> mr-2"></i> Scan Izin Pulang Cepat</h1>
          </div>
          <div class="col-sm-5">
            <ol class="breadcrumb float-sm-right mb-0">
              <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
              <li class="breadcrumb-item"><a href="scan_index.php">Scan IPC</a></li>
              <li class="breadcrumb-item active"><?= htmlspecialchars($ticket) ?></li>
            </ol>
          </div>
        </div>
      </div>
    </section>
    <section class="content">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-lg-11 col-md-12">
            <div class="card card-<?= htmlspecialchars($themeColor) ?> card-outline">
              <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?>">
                <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Detail Pengajuan IPC <small class="ml-2" style="opacity:.85;">Ticket: <b><?= htmlspecialchars($ticket) ?></b></small></h3>
                <div class="card-tools"><a href="scan_index.php" class="btn btn-tool <?= $headerTextClass ?>"><i class="fas fa-qrcode"></i></a></div>
              </div>
              <div class="card-body p-0">
                <?php if ($msg): ?><div class="m-3 alert <?= $msgClass ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($msg) ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div><?php endif; ?>
                <div class="row no-gutters">
                  <div class="col-md-6 col-divider-right">
                    <div class="section-header"><i class="fas fa-user mr-2"></i>Data Pemohon</div>
                    <table class="info-table">
                      <tr><td class="info-label">NIK</td><td class="info-val"><?= htmlspecialchars($dataIPC['nik'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">Nama</td><td class="info-val"><?= htmlspecialchars($dataIPC['nama_pemohon'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">Departemen</td><td class="info-val"><?= htmlspecialchars($dataIPC['departemen'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">Bagian</td><td class="info-val"><?= htmlspecialchars($dataIPC['bagian'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">Jabatan</td><td class="info-val"><?= htmlspecialchars($dataIPC['jabatan'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">No. HP</td><td class="info-val"><?= htmlspecialchars($dataIPC['no_hp'] ?? '-') ?></td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <div class="section-header"><i class="fas fa-clock mr-2"></i>Jadwal &amp; Alasan</div>
                    <table class="info-table">
                      <tr><td class="info-label">Tanggal</td><td class="info-val"><?= htmlspecialchars($tglDisplay) ?></td></tr>
                      <tr><td class="info-label">Jam Normal</td><td class="info-val"><?= htmlspecialchars($dataIPC['jam_pulang_normal'] ?? '-') ?></td></tr>
                      <tr><td class="info-label">Jam Diminta</td><td class="info-val"><strong><?= htmlspecialchars($dataIPC['jam_pulang_diminta'] ?? '-') ?></strong></td></tr>
                      <tr><td class="info-label">Alasan</td><td class="info-val"><?= htmlspecialchars($dataIPC['alasan'] ?? '-') ?><?= !empty($dataIPC['alasan_lain']) ? ' - ' . htmlspecialchars($dataIPC['alasan_lain']) : '' ?></td></tr>
                      <tr><td class="info-label">Jam Keluar Aktual</td><td class="info-val"><?= htmlspecialchars($dataIPC['jam_keluar_real'] ?? '-') ?></td></tr>
                    </table>
                  </div>
                </div>
                <div class="control-panel">
                  <div class="control-panel-top">
                    <div class="status-cluster">
                      <div><span class="status-label">Tiket</span><span class="badge <?= $isApprovedIPC ? 'badge-success clickable' : 'badge-secondary' ?>" <?= $isApprovedIPC ? 'data-toggle="modal" data-target="#modalTtdDetail" title="Klik untuk melihat status TTD"' : '' ?>><i class="fas fa-check mr-1"></i><?= htmlspecialchars(ucfirst($statusTicket ?: '-')) ?></span></div>
                      <div><span class="status-label">Status</span><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($statusKeluarText) ?></span></div>
                      <?php if ($sudahKeluarIPC): ?><div><span class="status-label">Keluar</span><span class="status-time text-warning"><?= htmlspecialchars($dataIPC['jam_keluar_real']) ?></span></div><?php endif; ?>
                    </div>
                    <div class="action-slot">
                      <?php if (!$isApprovedIPC): ?>
                        <span class="badge badge-danger p-2"><i class="fas fa-exclamation-triangle mr-1"></i> Belum Disetujui</span>
                      <?php elseif ($canScanKeluarIPC): ?>
                        <form method="POST" class="m-0"><input type="hidden" name="ticket" value="<?= htmlspecialchars($ticket) ?>"><input type="hidden" name="action" value="scan_keluar"><button type="submit" class="btn btn-warning btn-action-main"><i class="fas fa-sign-out-alt mr-1"></i> Konfirmasi KELUAR <span class="action-time"><?= date('H:i') ?></span></button></form>
                      <?php else: ?>
                        <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i> Scan Selesai</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="control-panel-bottom">
                    <a href="scan_index.php" class="btn btn-default btn-sm"><i class="fas fa-qrcode mr-1"></i> Scan Lagi</a>
                    <a href="../../pages/form_umum/detail_izin_pulang_cepat.php?ticket=<?= urlencode($ticket) ?>" class="btn btn-default btn-sm" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> Detail Lengkap</a>
                    <?php if ($isApprovedIPC): ?><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#modalTtdDetail"><i class="fas fa-file-signature mr-1"></i> Lihat Status TTD</button><?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <div class="modal fade" id="modalTtdDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?>"><h5 class="modal-title"><i class="fas fa-file-signature"></i> &nbsp;Status Tanda Tangan â€” <?= htmlspecialchars($ticket) ?></h5><button type="button" class="close <?= $headerTextClass ?>" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <?php $rolesOrder = ['Pemohon', 'Atasan Pemohon', 'HRD']; ?>
        <table class="signature-table-modal"><thead><tr><td style="width:33.33%;">Pemohon</td><td style="width:33.33%;">Mengetahui,</td><td style="width:33.33%;">Menyetujui,</td></tr></thead><tbody>
          <tr><?php foreach ($rolesOrder as $roleKey): $cell = null; foreach ($ttdData as $r) { if (strcasecmp($r['GroupRole'] ?? '', $roleKey) === 0) { $cell = $r; break; } } $ttdImg = $cell ? ipcScanTtdImg($cell) : ''; ?><td class="ttd-img-cell"><?php if ($ttdImg): ?><img src="<?= htmlspecialchars($ttdImg) ?>" alt="TTD <?= htmlspecialchars($roleKey) ?>" class="ttd-cell-img"><?php else: ?><span class="ttd-placeholder">( belum TTD )</span><?php endif; ?></td><?php endforeach; ?></tr>
          <tr><?php foreach ($rolesOrder as $roleKey): $cell = null; foreach ($ttdData as $r) { if (strcasecmp($r['GroupRole'] ?? '', $roleKey) === 0) { $cell = $r; break; } } ?><td class="ttd-name-cell"><div><?= htmlspecialchars($cell['SignedByUserName'] ?? '-') ?></div><div style="font-weight:400;font-size:11px;color:#6c757d;"><?= htmlspecialchars($roleKey) ?></div></td><?php endforeach; ?></tr>
        </tbody></table>
        <?php if (empty($ttdData)): ?><div class="alert alert-info mt-3 mb-0"><i class="fas fa-info-circle"></i> Belum ada data tanda tangan yang terekam untuk tiket ini.</div><?php endif; ?>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
    </div></div>
  </div>
  <script>setTimeout(function(){ $('.alert-success, .alert-info').not('.alert-permanent').fadeOut(400); }, 4000);</script>
  <?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); exit;
}

if (empty($ticket) || (stripos($ticket, 'IKS-') !== 0 && stripos($ticket, 'IKP-') !== 0)) {
  renderScanErrorPage($rawTicket, 'Format Tiket Tidak Dikenal', 'Kode <strong>' . htmlspecialchars($rawTicket ?: '(kosong)') . '</strong> bukan format tiket IKS, IKP, atau IPC yang valid.', 'danger');
}

// Ambil data
$sql = "SELECT ticket, nik, nama_pemohon, departemen, bagian, jabatan, no_hp,
                tgl_keluar, tgl_pengajuan, jam_keluar, estimasi_kembali, keperluan, keperluan_lain,
                tujuan, kendaraan, kendaraan_lain,
                status_ticket, jam_keluar_real, jam_kembali_real, status_kembali
         FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
  renderScanErrorPage($ticket, 'Tiket Tidak Ditemukan', 'Data tiket <strong>' . htmlspecialchars($ticket) . '</strong> tidak ditemukan di sistem. Pastikan tiket sudah disimpan atau barcode yang dipindai valid.');
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

$statusTicket = strtolower(trim($data['status_ticket'] ?? ''));
$statusKembali = $data['status_kembali'] ?? 'Belum Keluar';
$jamKeluarReal = $data['jam_keluar_real'] ?? null;
$jamKembaliReal = $data['jam_kembali_real'] ?? null;

// Format keperluan (Lainnya â†’ pakai keperluan_lain)
$keperluanRaw = $data['keperluan'] ?? '';
$keperluanLain = $data['keperluan_lain'] ?? '';
$keperluanDisplay = '';
if ($keperluanRaw !== '') {
  $keperluanDisplay = ($keperluanRaw === 'Lainnya' && $keperluanLain)
    ? 'Lainnya: ' . $keperluanLain
    : $keperluanRaw;
}

// Format kendaraan
$kendaraanRaw = $data['kendaraan'] ?? '';
$kendaraanLain = $data['kendaraan_lain'] ?? '';
$kendaraanDisplay = '';
if ($kendaraanRaw !== '') {
  $kendaraanDisplay = ($kendaraanRaw === 'Lainnya' && $kendaraanLain)
    ? 'Lainnya: ' . $kendaraanLain
    : $kendaraanRaw;
}

// Format tanggal keluar
$tglKeluarDisplay = '-';
if (!empty($data['tgl_keluar'])) {
  if ($data['tgl_keluar'] instanceof DateTime) {
    $tglKeluarDisplay = $data['tgl_keluar']->format('d-m-Y');
  } elseif (is_string($data['tgl_keluar'])) {
    $tglKeluarDisplay = date('d-m-Y', strtotime($data['tgl_keluar']));
  }
}

function iksRequiresKadeptIT($ticket, $tglPengajuan)
{
  if (stripos($ticket, 'IKS-') !== 0) return false;
  if ($tglPengajuan instanceof DateTime) return $tglPengajuan->format('Y-m-d') >= '2026-08-10';
  $time = strtotime((string)$tglPengajuan);
  return $time !== false && date('Y-m-d', $time) >= '2026-08-10';
}
$iksNeedsKadeptIT = iksRequiresKadeptIT($ticket, $data['tgl_pengajuan'] ?? null);

function iksTimeToMinutes($timeValue)
{
  if ($timeValue instanceof DateTime) {
    return ((int) $timeValue->format('H')) * 60 + (int) $timeValue->format('i');
  }

  $timeValue = trim((string) $timeValue);
  if ($timeValue === '') {
    return null;
  }

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

// --- HANDLE POST (konfirmasi aksi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $updatedBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'Security';
  $nowTime = date('H:i');
  $msg = '';
  $ok = false;

  if ($action === 'scan_keluar') {
    if (!empty($jamKeluarReal)) {
      $msg = 'Sudah tercatat keluar pukul ' . htmlspecialchars($jamKeluarReal);
    } else {
      $stmtU = sqlsrv_query(
        $conn,
        "UPDATE Form_Umum_Izin_Keluar_Pabrik
                 SET jam_keluar_real = ?, status_kembali = 'Sedang Keluar',
                     updated_at = GETDATE(), updated_by = ?
                 WHERE ticket = ? AND jam_keluar_real IS NULL",
        [$nowTime, $updatedBy, $ticket]
      );
      $ok = ($stmtU !== false && sqlsrv_rows_affected($stmtU) > 0);
      $msg = $ok ? "Berhasil dicatat keluar pukul $nowTime." : 'Gagal mencatat. Coba lagi.';
    }
  } elseif ($action === 'scan_kembali') {
    if (!empty($jamKembaliReal)) {
      $msg = 'Sudah tercatat kembali pukul ' . htmlspecialchars($jamKembaliReal);
    } elseif (empty($jamKeluarReal)) {
      $msg = 'Scan keluar dulu sebelum scan kembali!';
    } else {
      $estimasi = $data['estimasi_kembali'] ?? '';
      $statusBaru = iksIsLateReturn($nowTime, $estimasi) ? 'Terlambat Kembali' : 'Sudah Kembali';
      $stmtU = sqlsrv_query(
        $conn,
        "UPDATE Form_Umum_Izin_Keluar_Pabrik
                 SET jam_kembali_real = ?, status_kembali = ?,
                     updated_at = GETDATE(), updated_by = ?
                 WHERE ticket = ? AND jam_kembali_real IS NULL",
        [$nowTime, $statusBaru, $updatedBy, $ticket]
      );
      $ok = ($stmtU !== false && sqlsrv_rows_affected($stmtU) > 0);
      $msg = $ok ? "Berhasil dicatat kembali pukul $nowTime ($statusBaru)." : 'Gagal mencatat. Coba lagi.';
      if ($ok) {
        $statusKembali = $statusBaru;
        $jamKembaliReal = $nowTime;
      }
    }
  }

  // Refresh data
  $stmtR = sqlsrv_query(
    $conn,
    "SELECT jam_keluar_real, jam_kembali_real, status_kembali
         FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?",
    [$ticket]
  );
  if ($stmtR && $rowR = sqlsrv_fetch_array($stmtR, SQLSRV_FETCH_ASSOC)) {
    $jamKeluarReal = $rowR['jam_keluar_real'] ?? $jamKeluarReal;
    $jamKembaliReal = $rowR['jam_kembali_real'] ?? $jamKembaliReal;
    $statusKembali = $rowR['status_kembali'] ?? $statusKembali;
  }
  if ($stmtR) {
    sqlsrv_free_stmt($stmtR);
  }
}

// --- Ambil data TTD (untuk modal Lihat Status TTD) -------------------------
$ttdData = [];
$ttdStmt = sqlsrv_query(
  $conn,
  "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId
   FROM Form_Umum_TTD WHERE Ticket = ?",
  [$ticket]
);
if ($ttdStmt) {
  while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
    $ttdData[] = $r;
  }
  sqlsrv_free_stmt($ttdStmt);
}

/**
 * Render gambar tanda tangan dari SignaturePath.
 * Bisa berisi: URL external, path lokal, atau base64 (dengan/tanpa prefix data:image).
 * Mengembalikan data URI base64 agar <img src=...> langsung render.
 */
function renderTtdImg(array $r): string
{
  $raw = trim($r['SignaturePath'] ?? '');
  if ($raw === '')
    return '';
  if (stripos($raw, 'data:image') === 0)
    return $raw;
  if (preg_match('#^https?://#i', $raw))
    return $raw;
  if (preg_match('#^[\w/\\:.\-]+\.(png|jpg|jpeg|svg)$#i', $raw) && @file_exists($raw)) {
    $mime = preg_match('#\.svg$#i', $raw) ? 'image/svg+xml' : 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($raw));
  }
  // Asumsikan raw base64 tanpa prefix
  $decoded = base64_decode($raw, true);
  if ($decoded !== false) {
    return 'data:image/png;base64,' . $raw;
  }
  return $raw;
}

$canScanKeluar = ($statusTicket === 'approved') && empty($jamKeluarReal);
$canScanKembali = ($statusTicket === 'approved') && !empty($jamKeluarReal) && empty($jamKembaliReal);

$msgClass = (isset($ok) && $ok) ? 'alert-success' : 'alert-danger';
$badgeClass = match ($statusKembali) {
  'Sedang Keluar' => 'badge-warning',
  'Sudah Kembali' => 'badge-success',
  'Terlambat Kembali' => 'badge-danger',
  default => 'badge-secondary'
};

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
if (!empty($_SESSION['UserId'])) {
  include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
}
?>
<style>
  .wrapper { background-color: #f4f6f9 !important; }
  .content-wrapper { background-color: #f4f6f9 !important; min-height: calc(100vh - 100px); }
  body.layout-top-nav .content-wrapper,
  body.layout-top-nav .main-header,
  body.layout-top-nav .main-footer {
    margin-left: 0 !important;
  }
  .content-header { padding: 15px 0.5rem; }
  .section-header{padding:10px 14px;background:#f4f6f9;border-bottom:1px solid #dee2e6;font-weight:700;color:#495057;text-transform:uppercase;font-size:.78rem;letter-spacing:.02em}
  .col-divider-right{border-right:1px solid #dee2e6}
  .info-table{width:100%;margin:0;border-collapse:collapse}
  .info-table tr{border-bottom:1px solid #f1f3f5}
  .info-table tr:last-child{border-bottom:none}
  .info-table td{padding:9px 14px;vertical-align:top}
  .info-label{width:145px;color:#6c757d;font-weight:700;font-size:.82rem;background:#fbfcfd}
  .info-val{color:#212529;font-weight:600}
  .control-panel{border-top:1px solid #dee2e6;background:#fff;padding:12px 14px}
  .control-panel-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .status-cluster{display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap}
  .status-label{display:block;font-size:.72rem;color:#6c757d;font-weight:700;text-transform:uppercase;line-height:1;margin-bottom:3px}
  .status-time{font-size:.9rem;font-weight:700}
  .action-slot{text-align:right;margin-left:auto}
  .btn-action-main{font-weight:700;box-shadow:0 8px 18px rgba(0,0,0,.12);padding:8px 18px}
  .action-time{display:inline-block;margin-left:8px;padding-left:8px;border-left:1px solid rgba(255,255,255,.45)}
  .control-panel-bottom{margin-top:12px;padding-top:10px;border-top:1px dashed #dee2e6;display:flex;gap:6px;flex-wrap:wrap}
  .clickable{cursor:pointer}
  .signature-table-modal{width:100%;border-collapse:collapse;table-layout:fixed;text-align:center}
  .signature-table-modal td{border:1px solid #dee2e6;padding:8px;vertical-align:middle}
  .signature-table-modal thead td{font-weight:700;background:#f8f9fa;color:#495057}
  .ttd-img-cell{height:100px;background:#fff;overflow:hidden}
  .ttd-cell-img{max-width:92%;max-height:85px;object-fit:contain;display:block;margin:0 auto}
  .ttd-placeholder{color:#adb5bd;font-size:.85rem;font-style:italic}
  .ttd-name-cell{font-weight:700;font-size:.9rem;background:#fbfcfd}
  @media(max-width:768px){.col-divider-right{border-right:none;border-bottom:1px solid #dee2e6}.control-panel-top{align-items:flex-start}.action-slot{text-align:left;margin-left:0}.info-label{width:125px}}
</style>
<div class="content-wrapper">

  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <h1 class="m-0">
            <i class="fas fa-qrcode text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>
            Scan Izin Keluar
            <small class="text-muted ml-2" style="font-size:.7rem;"><?= htmlspecialchars($ticket) ?></small>
          </h1>
        </div>
        <div class="col-sm-5">
          <ol class="breadcrumb float-sm-right mb-0">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item"><a href="scan_index.php">Scan IKS</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($ticket) ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row justify-content-center">
        <div class="col-lg-11 col-md-12">

          <div class="card card-<?= htmlspecialchars($themeColor) ?> card-outline">
            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?>">
              <h3 class="card-title">
                <i class="fas fa-file-alt mr-2"></i> Detail Pengajuan IKS
                <small class="ml-2" style="opacity:.85;">Ticket: <b><?= htmlspecialchars($ticket) ?></b></small>
              </h3>
              <div class="card-tools">
                <a href="scan_index.php" class="btn btn-tool <?= $headerTextClass ?>">
                  <i class="fas fa-qrcode"></i>
                </a>
              </div>
            </div>

            <div class="card-body p-0">

              <?php if (isset($msg) && $msg): ?>
                <div class="m-3 alert <?= $msgClass ?> alert-dismissible fade show" role="alert">
                  <?= htmlspecialchars($msg) ?>
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
              <?php endif; ?>

              <div class="row no-gutters">
                <!-- Col 1: Data Pemohon -->
                <div class="col-md-6 col-divider-right">
                  <div class="section-header">
                    <i class="fas fa-user mr-2"></i>Data Pemohon
                  </div>
                  <table class="info-table">
                    <tr>
                      <td class="info-label" style="width:22%; vertical-align:top;">Karyawan</td>
                      <td class="info-val" style="padding:0.45rem 0.65rem;">
                        <div style="font-weight:600; margin-bottom:0.2rem;">Daftar Karyawan (<?= count($employees) ?> orang)</div>
                        <?php foreach ($employees as $employeeIndex => $employee): ?>
                          <div style="padding:0.35rem 0; <?= $employeeIndex < count($employees) - 1 ? 'border-bottom:1px solid #e5e7eb;' : '' ?>">
                            <div style="font-weight:600;"><?= $employeeIndex + 1 ?>. <?= htmlspecialchars($employee['nama_pemohon'] ?? '-') ?></div>
                            <div style="font-size:0.88em; color:#4b5563; line-height:1.45;">
                              <span>NIK: <?= htmlspecialchars($employee['nik'] ?? '-') ?></span>
                              <span style="margin-left:0.65rem;">Jabatan: <?= htmlspecialchars($employee['jabatan'] ?? '-') ?></span><br>
                              <span>No. HP: <?= htmlspecialchars($employee['no_hp'] ?? '-') ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </td>
                    </tr>
                    <tr><td class="info-label">Departemen</td><td class="info-val"><?= htmlspecialchars($data['departemen'] ?? '-') ?></td></tr>
                    <tr><td class="info-label">Bagian</td><td class="info-val"><?= htmlspecialchars($data['bagian'] ?? '-') ?></td></tr>
                  </table>
                </div>

                <!-- Col 2: Jadwal & Keperluan -->
                <div class="col-md-6">
                  <div class="section-header">
                    <i class="fas fa-clock mr-2"></i>Jadwal &amp; Keperluan
                  </div>
                  <table class="info-table">
                    <tr>
                      <td class="info-label">Tgl Keluar</td>
                      <td class="info-val"><?= htmlspecialchars($tglKeluarDisplay) ?></td>
                    </tr>
                    <tr>
                      <td class="info-label">Jam Keluar</td>
                      <td class="info-val"><?= htmlspecialchars($data['jam_keluar'] ?? '-') ?></td>
                    </tr>
                    <tr>
                      <td class="info-label">Estimasi</td>
                      <td class="info-val"><?= htmlspecialchars($data['estimasi_kembali'] ?? '-') ?></td>
                    </tr>
                    <tr>
                      <td class="info-label">Tujuan</td>
                      <td class="info-val"><?= htmlspecialchars($data['tujuan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                      <td class="info-label">Keperluan</td>
                      <td class="info-val">
                        <?php if ($keperluanDisplay): ?>
                          <?= htmlspecialchars($keperluanDisplay) ?>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <tr>
                      <td class="info-label">Kendaraan</td>
                      <td class="info-val">
                        <?php if ($kendaraanDisplay): ?>
                          <?= htmlspecialchars($kendaraanDisplay) ?>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

              <!-- â•â•â• Control Panel: Status (left) + Action (right) + Secondary (bottom) â•â•â• -->
              <div class="control-panel">
                <div class="control-panel-top">

                  <!-- LEFT: Status Cluster -->
                  <div class="status-cluster">
                    <div>
                      <span class="status-label">Tiket</span>
                      <?php if (strtolower($statusTicket) === 'approved'): ?>
                        <span class="badge badge-success clickable" data-toggle="modal" data-target="#modalTtdDetail"
                          title="Klik untuk melihat siapa saja yang sudah tanda tangan">
                          <i class="fas fa-check mr-1"></i><?= htmlspecialchars(ucfirst($statusTicket)) ?>
                        </span>
                      <?php else: ?>
                        <span class="badge badge-secondary">
                          <?= htmlspecialchars(ucfirst($statusTicket)) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <div>
                      <span class="status-label">Status</span>
                      <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusKembali) ?></span>
                    </div>
                    <?php if ($jamKeluarReal): ?>
                      <div>
                        <span class="status-label">Keluar</span>
                        <span class="status-time text-warning"><?= htmlspecialchars($jamKeluarReal) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if ($jamKembaliReal): ?>
                      <div>
                        <span class="status-label">Kembali</span>
                        <span class="status-time text-success"><?= htmlspecialchars($jamKembaliReal) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- RIGHT: Action Slot -->
                  <div class="action-slot">
                    <?php if ($statusTicket !== 'approved'): ?>
                      <span class="badge badge-danger p-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Belum Disetujui
                      </span>
                    <?php elseif ($canScanKeluar): ?>
                      <form method="POST" class="m-0">
                        <input type="hidden" name="ticket" value="<?= htmlspecialchars($ticket) ?>">
                        <input type="hidden" name="action" value="scan_keluar">
                        <button type="submit" class="btn btn-warning btn-action-main">
                          <i class="fas fa-sign-out-alt mr-1"></i> Konfirmasi KELUAR
                          <span class="action-time"><?= date('H:i') ?></span>
                        </button>
                      </form>
                    <?php elseif ($canScanKembali): ?>
                      <form method="POST" class="m-0">
                        <input type="hidden" name="ticket" value="<?= htmlspecialchars($ticket) ?>">
                        <input type="hidden" name="action" value="scan_kembali">
                        <button type="submit" class="btn btn-success btn-action-main">
                          <i class="fas fa-sign-in-alt mr-1"></i> Konfirmasi KEMBALI
                          <span class="action-time"><?= date('H:i') ?></span>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge badge-success p-2">
                        <i class="fas fa-check-circle mr-1"></i> Scan Selesai
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Bottom: Secondary actions -->
                <div class="control-panel-bottom">
                  <a href="scan_index.php" class="btn btn-default btn-sm">
                    <i class="fas fa-qrcode mr-1"></i> Scan Lagi
                  </a>
                  <a href="../../pages/form_umum/detail_izin_keluar_pabrik.php?ticket=<?= urlencode($ticket) ?>"
                    class="btn btn-default btn-sm" target="_blank">
                    <i class="fas fa-external-link-alt mr-1"></i> Detail Lengkap
                  </a>
                  <?php if (strtolower($statusTicket) === 'approved'): ?>
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal"
                      data-target="#modalTtdDetail">
                      <i class="fas fa-file-signature mr-1"></i> Lihat Status TTD
                    </button>
                  <?php endif; ?>
                </div>
              </div>

            </div><!-- /.card-body -->
          </div><!-- /.card -->

        </div>
      </div>
    </div>
  </section>
</div>

<!-- â”€â”€ Modal Detail TTD (read only) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="modalTtdDetail" tabindex="-1" role="dialog" aria-labelledby="modalTtdDetailTitle"
  aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?>">
        <h5 class="modal-title" id="modalTtdDetailTitle">
          <i class="fas fa-file-signature"></i> &nbsp;Status Tanda Tangan â€” <?= htmlspecialchars($ticket) ?>
        </h5>
        <button type="button" class="close <?= $headerTextClass ?>" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <?php
        // IKS lama: Pemohon → Atasan → HRD. IKS baru: tambah Kadept sebelum HRD.
        $isIKSScan = stripos($ticket, 'IKS-') === 0;
        if ($isIKSScan) {
          $rolesOrder = $iksNeedsKadeptIT
            ? ['Pemohon', 'Atasan Pemohon', 'Kadept', 'HRD']
            : ['Pemohon', 'Atasan Pemohon', 'HRD'];
        } else {
          $rolesOrder = ['Pemohon', 'Atasan Pemohon', 'HRD'];
          $hasDanRu = false;
          foreach ($ttdData as $r) {
            if (strcasecmp($r['GroupRole'] ?? '', 'DanRu SATPAM') === 0) {
              $hasDanRu = true;
              break;
            }
          }
          if ($hasDanRu) {
            $rolesOrder[] = 'DanRu SATPAM';
          }
        }
        $colWidth = round(100 / count($rolesOrder), 2) . '%';
        ?>
        <table class="signature-table-modal">
          <thead>
            <tr>
              <?php if ($iksNeedsKadeptIT): ?>
                <td style="width:<?= $colWidth ?>;">Pemohon</td>
                <td colspan="2" style="width:<?= $colWidth ?>;">Mengetahui,</td>
                <td style="width:<?= $colWidth ?>;">Menyetujui,</td>
              <?php else: ?>
                <?php foreach ($rolesOrder as $index => $roleKey): ?>
                  <td style="width:<?= $colWidth ?>;">
                    <?= $index === 0 ? 'Pemohon' : ($index === 1 ? 'Mengetahui,' : ($roleKey === 'DanRu SATPAM' ? 'DanRu SATPAM' : 'Menyetujui,')) ?>
                  </td>
                <?php endforeach; ?>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <!-- Baris gambar TTD -->
            <tr>
              <?php foreach ($rolesOrder as $roleKey):
                $cell = null;
                foreach ($ttdData as $r) {
                  if (strcasecmp($r['GroupRole'] ?? '', $roleKey) === 0) {
                    $cell = $r;
                    break;
                  }
                }
                $ttdImg = $cell ? renderTtdImg($cell) : '';
                ?>
                <td class="ttd-img-cell">
                  <?php if (!empty($ttdImg)): ?>
                    <img src="<?= htmlspecialchars($ttdImg) ?>" alt="TTD <?= htmlspecialchars($roleKey) ?>"
                      class="ttd-cell-img">
                  <?php else: ?>
                    <span class="ttd-placeholder">( belum TTD )</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <!-- Baris nama & jabatan -->
            <tr>
              <?php foreach ($rolesOrder as $roleKey):
                $cell = null;
                foreach ($ttdData as $r) {
                  if (strcasecmp($r['GroupRole'] ?? '', $roleKey) === 0) {
                    $cell = $r;
                    break;
                  }
                }
                $name = $cell['SignedByUserName'] ?? '-';
                ?>
                <td class="ttd-name-cell">
                  <div><?= htmlspecialchars($name) ?></div>
                  <div style="font-weight:400; font-size:11px; color:#6c757d;"><?= htmlspecialchars($roleKey) ?></div>
                </td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
        <?php if (empty($ttdData)): ?>
          <div class="alert alert-info mt-3 mb-0" style="font-size:.9rem;">
            <i class="fas fa-info-circle"></i> Belum ada data tanda tangan yang terekam untuk tiket ini.
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Auto-dismiss any success/info messages after 4 seconds
  setTimeout(function () {
    $('.alert-success, .alert-info').not('.alert-permanent').fadeOut(400);
  }, 4000);
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
