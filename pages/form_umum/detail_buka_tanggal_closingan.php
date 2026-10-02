<?php
session_start();
require_once '../../koneksi.php';
require_once __DIR__ . '/items_parser.php';
require_once __DIR__ . '/approval_helper.php';

$ticket = $_GET['ticket'] ?? '';
if (!$ticket) {
  die('Ticket tidak ditemukan');
}

$sql = "SELECT * FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
  die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$currentStatus = strtolower(trim($data['status_ticket'] ?? ''));
$isRejected = $currentStatus === 'ditolak';
$isProcessLocked = in_array($currentStatus, ['unclosing', 'closed'], true);

// ── Cek Permission TTD ──────────────────────────────────────────────
$canSignPemohon = false;
$canSignAtasan = false;

$canSignKabagIcs = false;
$canSignAccAudit = false;
$canSignDireksi = false;

if (isset($_SESSION['UserId'])) {
  // Cek role dari User_TTD_Template_Umum
  $sqlRole = "SELECT GroupRole FROM User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
  $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
  if ($stmtRole) {
    while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
      $role = trim($rowR['GroupRole'] ?? '');
      if (strcasecmp($role, 'Pemohon') === 0)
        $canSignPemohon = true;
      elseif (strcasecmp($role, 'Atasan Pemohon') === 0)
        $canSignAtasan = true;
      elseif (strcasecmp($role, 'Kabag ICS') === 0)
        $canSignKabagIcs = true;
      elseif (strcasecmp($role, 'Kadept ACC') === 0 || strcasecmp($role, 'Acc Audit') === 0)
        $canSignAccAudit = true;
      elseif (strcasecmp($role, 'Direksi') === 0)
        $canSignDireksi = true;
    }
  }
}

// ── Ambil Tanda Tangan dari Form_Umum_TTD ──────────────────────────
$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Umum_TTD WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd && sqlsrv_has_rows($stmtTtd)) {
  while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
    $role = $rowTtd['GroupRole'];
    $ttd[$role] = [
      'SignaturePath' => $rowTtd['SignaturePath'],
      'SignedByUserName' => $rowTtd['SignedByUserName'],
      'SignedByUserId' => $rowTtd['SignedByUserId']
    ];
  }
}

function isTtdExists($ttdData)
{
  return !empty($ttdData['SignaturePath']);
}
function isTtdByCurrentUser($ttdData, $userId)
{
  if (!isTtdExists($ttdData))
    return false;
  $signedBy = (int) ($ttdData['SignedByUserId'] ?? 0);
  if ($signedBy === (int) $userId)
    return true;
  return ((int) ($_SESSION['GroupId'] ?? 0) === 1 && $signedBy === 0);
}
function fmtDate($dt)
{
  if ($dt instanceof DateTime)
    return $dt->format('d-m-Y');
  if (is_string($dt) && !empty($dt))
    return date('d-m-Y', strtotime($dt));
  return '-';
}
function formatFileSize($bytes)
{
  if ($bytes === 0 || $bytes === null)
    return '0 Bytes';
  $k = 1024;
  $sizes = ['Bytes', 'KB', 'MB', 'GB'];
  $i = floor(log($bytes) / log($k));
  return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
function parseGudangTransaksi($text)
{
  $result = ['gudang' => '-', 'transaksi' => '-', 'nomor_transaksi' => '-', 'jenis_transaksi' => '-'];
  $text = trim((string) $text);
  if ($text === '')
    return $result;

  $parts = array_map('trim', explode(',', $text));
  $gudang = [];
  $transaksi = [];
  foreach ($parts as $part) {
    if ($part === '')
      continue;
    if (preg_match('/^(Procurement|Sales|Cash Management)\s*[:\-]\s*(.*)$/i', $part, $mPart)) {
      $result['jenis_transaksi'] = trim($mPart[1]);
      $trxText = trim($mPart[2]);
      if (preg_match('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', $trxText, $mNo)) {
        $result['nomor_transaksi'] = trim($mNo[1]);
        $trxText = trim(preg_replace('/\(\s*No\.\s*Transaksi\s*[:\-]\s*(.*?)\)\s*$/i', '', $trxText));
      }
      $transaksi[] = $trxText;
    } else {
      $gudang[] = $part;
    }
  }

  if (!empty($gudang))
    $result['gudang'] = implode(', ', $gudang);
  if (!empty($transaksi))
    $result['transaksi'] = implode(', ', $transaksi);
  return $result;
}
$items = parseItemsFromGudangTransaksi($data['gudang_transaksi'] ?? '');
$parsedGT = !empty($items) ? $items[0] : ['gudang' => '-', 'transaksi' => '-', 'nomor_transaksi' => '-', 'jenis_transaksi' => '-'];
$ttdKadeptAcc = $ttd['Kadept ACC'] ?? ($ttd['Acc Audit'] ?? ($ttd['Kadept'] ?? []));

$approvalFlow = closinganApprovalFlow($data);
$roleMengetahui = $approvalFlow['mengetahui'];
$roleDisetujui = $approvalFlow['disetujui'];
$totalSignatureCols = 2 + count($roleMengetahui) + count($roleDisetujui);
$requestFlags = closinganRequestFlags($data);
$showGudang = $requestFlags['has_gudang'];
$showTransaksi = $requestFlags['has_transaksi'];
$itemInfoColspan = 1 + ($showGudang ? 1 : 0) + ($showTransaksi ? 4 : 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Detail Buka Tanggal Closingan - <?= htmlspecialchars($ticket) ?></title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
  <style>
    body {
      background: #ffffff;
      font-size: 15px;
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

    .b {
      font-weight: bold;
    }

    .center {
      text-align: center;
    }

    .border {
      border: 1px solid #000;
    }

    @media (max-width: 768px) {
      .form-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        padding-bottom: 10px;
        margin: 5px auto !important;
      }

      .outer-form {
        min-width: 800px;
      }

      img[alt="logo"] {
        width: 50px !important;
      }

      body {
        font-size: 14px;
      }
    }
  </style>
  <script>
    window.addEventListener('load', function () {
      var canReject = <?php echo ($canSignKabagIcs || $canSignAccAudit || $canSignDireksi) && !$isRejected && !$isProcessLocked ? 'true' : 'false'; ?>;
      var ticket = '<?= htmlspecialchars($ticket) ?>';
      if (window.parent && window.parent.updateRejectButtonVisibility) {
        window.parent.updateRejectButtonVisibility(canReject, ticket);
      }
    });
  </script>
</head>

<body>

  <?php include 'form_contents/components/rejection_status.php'; ?>

  <div class="form-wrapper" style="max-width:900px; margin:10px auto;">
    <table class="outer-form"
      style="width:100%; border:1px solid #000; border-collapse:collapse; font-size:14px; line-height:1.4;">
      <!-- Header -->
      <tr>
        <td style="width:100%; padding:0;">
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td
                style="width:90px; border-right:1px solid #000; padding:8px 6px; vertical-align:top; text-align:center;">
                <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block; margin:0 auto;">
              </td>
              <td style="padding:6px 8px; vertical-align:top;">
                <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
                Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                Banjaran – Kab. Bandung<br>
                40377 Telp. (022) 594-0313
              </td>
            </tr>
            <tr>
              <td colspan="2"
                style="border-top:1px solid #000; border-bottom:1px solid #000; text-align:center; font-weight:bold; padding:6px 4px; font-size:16px;">
                FORM KOMUNIKASI BUKA TANGGAL CLOSINGAN
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Data -->
      <tr>
        <td style="padding:0;">
          <table style="width:100%; border-collapse:collapse;">
            <tr>
              <td style="width:200px; padding:4px 6px;">Nama Pemohon</td>
              <td style="padding:4px 6px;">: <?= htmlspecialchars($data['nama_pemohon'] ?? '-') ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Jabatan</td>
              <td style="padding:4px 6px;">: <?= htmlspecialchars($data['jabatan'] ?? '-') ?></td>
              <td style="width:140px; padding:4px 6px;">Tgl Pengajuan</td>
              <td style="padding:4px 6px;">: <?= fmtDate($data['tgl_pengajuan'] ?? '') ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Departemen</td>
              <td style="padding:4px 6px;" colspan="3">: <?= htmlspecialchars($data['departemen'] ?? '-') ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Mengajukan permintaan</td>
              <td colspan="3" style="padding:4px 6px;">:
                <?php
                $checkGudang = $showGudang ? '&#9745;' : '&#9744;';
                $checkTransaksi = $showTransaksi ? '&#9745;' : '&#9744;';
                ?>
                <span style="display:inline-block; margin-right:20px;"><?= $checkGudang ?> Closingan Gudang</span>
                <span style="display:inline-block;"><?= $checkTransaksi ?> Closingan Transaksi</span>
              </td>
            </tr>
            <?php if (count($items) >= 2): ?>
              <tr>
                <td colspan="4" style="padding:4px 6px;">
                  <table style="width:100%; border-collapse:collapse; border:1px solid #ccc; font-size:13px;">
                    <thead>
                      <tr style="background:#f5f5f5; font-weight:bold;">
                        <td style="border:1px solid #ccc; padding:4px 6px; text-align:center; width:30px;">No</td>
                        <td style="border:1px solid #ccc; padding:4px 6px;">Buka Tgl</td>
                        <?php if ($showGudang): ?>
                          <td style="border:1px solid #ccc; padding:4px 6px;">Gudang</td>
                        <?php endif; ?>
                        <?php if ($showTransaksi): ?>
                          <td style="border:1px solid #ccc; padding:4px 6px;">Jenis Transaksi</td>
                          <td style="border:1px solid #ccc; padding:4px 6px;">Transaksi</td>
                          <td style="border:1px solid #ccc; padding:4px 6px;">No. Transaksi</td>
                          <td style="border:1px solid #ccc; padding:4px 6px;">Vendor/Cust</td>
                        <?php endif; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as $idx => $item): ?>
                        <tr style="background:#fff;">
                          <td style="border:1px solid #ccc; padding:4px 6px; text-align:center; vertical-align:middle;"
                            rowspan="2"><?= $idx + 1 ?></td>
                          <td style="border:1px solid #ccc; padding:4px 6px;">
                            <?= htmlspecialchars($item['buka_tgl'] ? fmtDate($item['buka_tgl']) : '-') ?>
                          </td>
                          <?php if ($showGudang): ?>
                            <td style="border:1px solid #ccc; padding:4px 6px;">
                              <?= htmlspecialchars($item['gudang'] ?: '-') ?>
                            </td>
                          <?php endif; ?>
                          <?php if ($showTransaksi): ?>
                            <td style="border:1px solid #ccc; padding:4px 6px;">
                              <?= htmlspecialchars($item['jenis_transaksi'] ?: '-') ?>
                            </td>
                            <td style="border:1px solid #ccc; padding:4px 6px;">
                              <?= htmlspecialchars($item['transaksi'] ?: '-') ?>
                            </td>
                            <td style="border:1px solid #ccc; padding:4px 6px;">
                              <?= htmlspecialchars($item['nomor_transaksi'] ?: '-') ?>
                            </td>
                            <td style="border:1px solid #ccc; padding:4px 6px;">
                              <?= htmlspecialchars($item['vendor_cust'] ?: '-') ?>
                            </td>
                          <?php endif; ?>
                        </tr>
                        <tr style="background:#fafafa;">
                          <td colspan="<?= $itemInfoColspan ?>"
                            style="border:1px solid #ccc; padding:4px 6px; font-size:12px; color:#555;">
                            <table style="width:100%; border-collapse:collapse;">
                              <tr>
                                <td style="vertical-align:top; white-space:nowrap; width:1%;">
                                  <strong>Keterangan/Alasan:</strong>
                                </td>
                                <td style="vertical-align:top; padding-left:5px;">
                                  <?= nl2br(htmlspecialchars($item['keterangan'] ?: '-')) ?>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </td>
              </tr>
            <?php else: ?>
              <tr>
                <td style="padding:4px 6px;">Buka Tgl</td>
                <td colspan="3" style="padding:4px 6px;">: <?= fmtDate($data['buka_tgl'] ?? '') ?></td>
              </tr>
              <?php if ($showGudang): ?>
                <tr>
                  <td style="padding:4px 6px;">Gudang</td>
                  <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($parsedGT['gudang']) ?></td>
                </tr>
              <?php endif; ?>
              <?php if ($showTransaksi): ?>
                <tr>
                  <td style="padding:4px 6px;">Jenis Transaksi</td>
                  <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($parsedGT['jenis_transaksi'] ?: '-') ?></td>
                </tr>
                <tr>
                  <td style="padding:4px 6px;">Transaksi</td>
                  <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($parsedGT['transaksi']) ?></td>
                </tr>
                <tr>
                  <td style="padding:4px 6px;">No. Transaksi</td>
                  <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($parsedGT['nomor_transaksi']) ?></td>
                </tr>
                <tr>
                  <td style="padding:4px 6px;">Vendor/Cust</td>
                  <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($parsedGT['vendor_cust'] ?? '-') ?></td>
                </tr>
              <?php endif; ?>
              <tr>
                <td style="padding:4px 6px; vertical-align:top;">Keterangan / Alasan</td>
                <td colspan="3" style="padding:4px 6px; vertical-align:top;">
                  <table style="width:100%; border-collapse:collapse; border:none;">
                    <tr>
                      <td style="padding:0; vertical-align:top; white-space:nowrap; width:1%; border:none;">: </td>
                      <td style="padding:0; vertical-align:top; border:none;">
                        <?= nl2br(htmlspecialchars($data['keterangan'] ?? '-')) ?>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            <?php endif; ?>

            <!-- Lampiran Section -->
            <?php if (!empty($data['attachment_filename'])): ?>
              <tr>
                <td style="padding:4px 6px; vertical-align:top; font-weight:600;">Lampiran</td>
                <td colspan="3" style="padding:4px 6px;">
                  :
                  <button type="button" class="btn btn-sm btn-info btn-preview-lampiran"
                    data-path="../../<?= htmlspecialchars($data['attachment_path']) ?>" style="margin-left:5px;">
                    <i class="fas fa-file-alt"></i> Lihat Dokumen
                  </button>
                </td>
              </tr>
            <?php endif; ?>

            <tr>
              <td colspan="4" style="height:20px;"></td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Spacer antara tabel item dan tabel tanda tangan -->
      <tr>
        <td style="height:15px; padding:0; border:none;"></td>

      </tr>

      <!-- Signature Table -->
      <tr>
        <td style="padding:6px;">
          <table class="signature-table"
            style="width:100%; border-collapse:collapse; text-align:center; table-layout:fixed;" border="1">
            <tr style="font-weight:bold; font-size:14px;">
              <td style="width:<?= (100 / $totalSignatureCols) ?>%; padding:4px;">Diajukan oleh,</td>
              <td style="width:<?= (100 / $totalSignatureCols) ?>%; padding:4px;">Diketahui oleh,</td>
              <?php if (!empty($roleMengetahui)): ?>
                <td style="width:<?= (100 * count($roleMengetahui) / $totalSignatureCols) ?>%; padding:4px;"
                  colspan="<?= count($roleMengetahui) ?>">Mengetahui,</td>
              <?php endif; ?>
              <?php if (!empty($roleDisetujui)): ?>
                <td style="width:<?= (100 * count($roleDisetujui) / $totalSignatureCols) ?>%; padding:4px;"
                  colspan="<?= count($roleDisetujui) ?>">Disetujui oleh,</td>
              <?php endif; ?>
            </tr>
            <tr style="height:120px; vertical-align:middle;">
              <!-- Pemohon -->
              <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-pemohon" class="ttd-area-slot" data-ttd-role="pemohon">
                  <?php if (isset($ttd['Pemohon'])): ?>
                    <img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Pemohon">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-pemohon" class="mt-2 ttd-btn-slot" data-ttd-role="pemohon">
                  <?php if ($canSignPemohon && !$isRejected && !$isProcessLocked): ?>
                    <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="pemohon">Hapus
                        Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="pemohon">Tanda
                        Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
              </td>
              <!-- Atasan Pemohon -->
              <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-atasan" class="ttd-area-slot" data-ttd-role="atasan_pemohon">
                  <?php if (isset($ttd['Atasan Pemohon'])): ?>
                    <img src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60"
                      alt="TTD Atasan">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-atasan" class="mt-2 ttd-btn-slot" data-ttd-role="atasan_pemohon">
                  <?php if ($canSignAtasan && !$isRejected && !$isProcessLocked): ?>
                    <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd"
                        data-role="atasan_pemohon">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="atasan_pemohon">Tanda
                        Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
              </td>
              <?php
              // Render "Mengetahui" columns
              foreach ($roleMengetahui as $rm):
                $rCode = '';
                $rCanSign = false;
                $rTtdData = [];
                if ($rm === 'Kabag ICS') {
                  $rCode = 'kabag_ics';
                  $rCanSign = $canSignKabagIcs;
                  $rTtdData = $ttd['Kabag ICS'] ?? [];
                } elseif ($rm === 'Kadept ACC') {
                  $rCode = 'acc_audit';
                  $rCanSign = $canSignAccAudit;
                  $rTtdData = $ttdKadeptAcc;
                }
                ?>
                <td style="padding:6px; vertical-align:middle;">
                  <div class="ttd-area-slot" data-ttd-role="<?= $rCode ?>">
                    <?php if (isTtdExists($rTtdData)): ?>
                      <img src="<?= htmlspecialchars($rTtdData['SignaturePath']) ?>" height="60"
                        alt="TTD <?= htmlspecialchars($rm) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="mt-2 ttd-btn-slot" data-ttd-role="<?= $rCode ?>">
                    <?php if ($rCanSign && !$isRejected && !$isProcessLocked): ?>
                      <?php if (isTtdByCurrentUser($rTtdData, $_SESSION['UserId'] ?? 0)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd"
                          data-role="<?= $rCode ?>">Hapus Tanda Tangan</button>
                      <?php elseif (isTtdExists($rTtdData)): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                      <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="<?= $rCode ?>">Tanda
                          Tangan</button>
                      <?php endif; ?>
                    <?php elseif (isTtdExists($rTtdData)): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php endif; ?>
                  </div>
                </td>
              <?php endforeach; ?>
              <?php
              // Render "Disetujui oleh" columns
              foreach ($roleDisetujui as $rd):
                $rCode = '';
                $rCanSign = false;
                $rTtdData = [];
                if ($rd === 'Kabag ICS') {
                  $rCode = 'kabag_ics';
                  $rCanSign = $canSignKabagIcs;
                  $rTtdData = $ttd['Kabag ICS'] ?? [];
                } elseif ($rd === 'Kadept ACC') {
                  $rCode = 'acc_audit';
                  $rCanSign = $canSignAccAudit;
                  $rTtdData = $ttdKadeptAcc;
                } elseif ($rd === 'Direksi') {
                  $rCode = 'direksi';
                  $rCanSign = $canSignDireksi;
                  $rTtdData = $ttd['Direksi'] ?? [];
                }
                ?>
                <td style="padding:6px; vertical-align:middle;">
                  <div class="ttd-area-slot" data-ttd-role="<?= $rCode ?>">
                    <?php if (isTtdExists($rTtdData)): ?>
                      <img src="<?= htmlspecialchars($rTtdData['SignaturePath']) ?>" height="60"
                        alt="TTD <?= htmlspecialchars($rd) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="mt-2 ttd-btn-slot" data-ttd-role="<?= $rCode ?>">
                    <?php if ($rCanSign && !$isRejected && !$isProcessLocked): ?>
                      <?php if (isTtdByCurrentUser($rTtdData, $_SESSION['UserId'] ?? 0)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd"
                          data-role="<?= $rCode ?>">Hapus Tanda Tangan</button>
                      <?php elseif (isTtdExists($rTtdData)): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                      <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="<?= $rCode ?>">Tanda
                          Tangan</button>
                      <?php endif; ?>
                    <?php elseif (isTtdExists($rTtdData)): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php endif; ?>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr style="text-align:center; font-weight:bold; font-size:14px;">
              <td id="ttd-label-pemohon" class="ttd-label-slot" data-ttd-role="pemohon" data-default-label="Pemohon">
                <?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?>
              </td>
              <td id="ttd-label-atasan" class="ttd-label-slot" data-ttd-role="atasan_pemohon"
                data-default-label="Atasan Pemohon">
                <?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Pemohon' ?>
              </td>
              <?php foreach ($roleMengetahui as $rm): ?>
                <?php $rmTtd = ($rm === 'Kabag ICS') ? ($ttd['Kabag ICS'] ?? []) : $ttdKadeptAcc; ?>
                <td class="ttd-label-slot" data-ttd-role="<?= ($rm === 'Kabag ICS') ? 'kabag_ics' : 'acc_audit' ?>"
                  data-default-label="<?= htmlspecialchars($rm) ?>">
                  <?= isTtdExists($rmTtd) ? htmlspecialchars($rmTtd['SignedByUserName']) : htmlspecialchars($rm) ?>
                </td>
              <?php endforeach; ?>
              <?php foreach ($roleDisetujui as $rd): ?>
                <?php
                if ($rd === 'Kabag ICS')
                  $rdTtd = $ttd['Kabag ICS'] ?? [];
                elseif ($rd === 'Kadept ACC')
                  $rdTtd = $ttdKadeptAcc;
                else
                  $rdTtd = $ttd['Direksi'] ?? [];
                ?>
                <td class="ttd-label-slot"
                  data-ttd-role="<?= ($rd === 'Kabag ICS') ? 'kabag_ics' : (($rd === 'Kadept ACC') ? 'acc_audit' : 'direksi') ?>"
                  data-default-label="<?= htmlspecialchars($rd) ?>">
                  <?= isTtdExists($rdTtd) ? htmlspecialchars($rdTtd['SignedByUserName']) : htmlspecialchars($rd) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td colspan="4" style="padding:0;">
          <table
            style="width:100%; border-collapse:collapse; border:none; border-top:1px solid #000; font-size:10px; text-align:center;">
            <tr>
              <td style="width:50%; padding:4px; border-right:1px solid #000;">SUM-FM-UMU-001</td>
              <td style="width:50%; padding:4px;">ERP</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>

  <!-- Modal Buat / Upload TTD -->
  <div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Buat / Upload Tanda Tangan</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="formTtd" enctype="multipart/form-data">
            <input type="hidden" name="ticket" value="<?= htmlspecialchars($ticket) ?>">
            <input type="hidden" name="role_code" id="ttdRoleCode" value="">
            <input type="hidden" name="canvas_data" id="canvasData" value="">
            <div class="row">
              <div class="col-md-8 mb-3">
                <label><b>Gambar di Canvas</b></label>
                <div style="border:1px solid #ccc; display:inline-block; width:100%;">
                  <canvas id="ttdCanvas" width="600" height="200"
                    style="background:#fff; cursor:crosshair; width:100%; height:200px;"></canvas>
                </div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvas">Bersihkan
                  Canvas</button>
              </div>
              <div class="col-md-4 mb-3">
                <label><b>Atau Upload File PNG/JPG</b></label>
                <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
                <div class="form-check mt-2">
                  <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1"
                    checked>
                  <label class="form-check-label" for="chkSaveTemplate">Simpan tanda tangan ini sebagai template</label>
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

  <!-- Modal Konfirmasi Hapus TTD -->
  <div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
          <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus Tanda Tangan</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Sukses Hapus TTD -->
  <div class="modal fade" id="modalSuksesHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Berhasil</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p id="pesanSuksesHapus">Tanda tangan berhasil dihapus.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
  <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
  <script>
    $(function () {
      var currentRoleCode = null;
      var isRejected = <?= $isRejected ? 'true' : 'false' ?>;
      var isProcessLocked = <?= $isProcessLocked ? 'true' : 'false' ?>;
      var isAdminUser = <?= ((int) ($_SESSION['GroupId'] ?? 0) === 1) ? 'true' : 'false' ?>;
      var ticket = '<?= htmlspecialchars($ticket) ?>';
      var roleToDelete = null;

      // ── Canvas ──────────────────────────────────────────────────────
      var canvas = document.getElementById('ttdCanvas');
      var ctx = canvas ? canvas.getContext('2d') : null;
      var drawing = false;

      if (canvas) {
        function getPos(e) {
          var r = canvas.getBoundingClientRect();
          var x = (e.clientX || (e.touches && e.touches[0].clientX)) - r.left;
          var y = (e.clientY || (e.touches && e.touches[0].clientY)) - r.top;
          return { x: x, y: y };
        }
        function startDraw(e) { drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function draw(e) { if (!drawing) return; if (e.touches) e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
        function endDraw() { drawing = false; }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', endDraw);

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
      }

      $('#btnClearCanvas').on('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); });
      $('#modalTtd').on('shown.bs.modal', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); $('#ttdFile').val(''); });

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
            if (resp.success && resp.signature_url) updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            else Swal.fire('Gagal', resp.message || 'Gagal menyimpan TTD manual.', 'error');
          },
          error: function () { Swal.fire('Error', 'Tidak dapat menghubungi server.', 'error'); }
        });
      }

      function showAdminTtdChoice(roleCode) {
        $.getJSON('get_manual_ttd_templates.php', { role_code: roleCode }, function (resp) {
          var manualTemplates = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
          if (!manualTemplates.length) { runAccountTtd(roleCode); return; }
          var options = '<option value="">-- Pilih TTD manual --</option>';
          manualTemplates.forEach(function (tpl) { options += '<option value="' + tpl.id + '">' + $('<div>').text(tpl.user_name).html() + '</option>'; });
          Swal.fire({
            title: 'Pilih Sumber TTD',
            html: '<p class="text-left mb-2">Anda login sebagai Administrator. Pilih TTD yang akan digunakan.</p><select id="swalManualTemplate" class="form-control" ' + (manualTemplates.length ? '' : 'disabled') + '>' + options + '</select>' + (!manualTemplates.length ? '<small class="text-muted d-block mt-2">Tidak ada template manual aktif untuk role ini.</small>' : ''),
            icon: 'question', showCancelButton: true, showDenyButton: true,
            confirmButtonText: 'Pakai TTD Manual', denyButtonText: 'Pakai TTD Akun Saya', cancelButtonText: 'Batal',
            preConfirm: function () { var val = $('#swalManualTemplate').val(); if (!val) { Swal.showValidationMessage('Pilih TTD manual terlebih dahulu.'); return false; } return val; }
          }).then(function (result) { if (result.isConfirmed) signWithManualTemplate(roleCode, result.value); else if (result.isDenied) runAccountTtd(roleCode); });
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
        }, 'json');
      }

      // ── Sign ────────────────────────────────────────────────────────
      $(document).on('click', '.btn-ttd', function () {
        var roleCode = $(this).data('role');
        currentRoleCode = roleCode;
        if (isAdminUser) { showAdminTtdChoice(roleCode); return; }
        runAccountTtd(roleCode);
      });

      // ── Delete confirm ──────────────────────────────────────────────
      $(document).on('click', '.btn-delete-ttd', function () {
        roleToDelete = $(this).data('role');
        $('#modalKonfirmasiHapusTtd').modal('show');
      });

      $('#btnKonfirmasiHapusTtd').on('click', function () {
        if (!roleToDelete) return;
        $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function (resp) {
          $('#modalKonfirmasiHapusTtd').modal('hide');
          if (resp.success) {
            removeTtdArea(roleToDelete);
            $('#pesanSuksesHapus').text(resp.message || 'Tanda tangan berhasil dihapus.');
            $('#modalSuksesHapusTtd').modal('show');
          } else {
            Swal.fire('Gagal', resp.message || 'Gagal menghapus tanda tangan.', 'error');
          }
        }, 'json');
      });

      // ── Simpan TTD dari modal ────────────────────────────────────────
      $('#btnSimpanTtd').on('click', function () {
        var roleCode = $('#ttdRoleCode').val() || currentRoleCode;
        if (!roleCode) { Swal.fire('Error', 'Role tidak dikenal.', 'error'); return; }

        $('#canvasData').val(canvas.toDataURL('image/png'));
        var fd = new FormData(document.getElementById('formTtd'));

        $.ajax({
          url: 'ttd_save_template.php',
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false,
          success: function (resp) {
            try { resp = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch (e) { }
            if (resp.success && resp.signature_url) {
              $('#modalTtd').modal('hide');
              updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            } else {
              Swal.fire('Gagal', resp.message || 'Gagal menyimpan tanda tangan.', 'error');
            }
          }
        });
      });

      // ── Helpers ──────────────────────────────────────────────────────
      function updateTtdArea(roleCode, imgUrl, name, userId) {
        if (!roleCode || !imgUrl) return;
        var selector = '[data-ttd-role="' + roleCode + '"]';
        var separator = imgUrl.indexOf('?') === -1 ? '?' : '&';
        var displayUrl = imgUrl + separator + 'v=' + Date.now();
        $('.ttd-area-slot' + selector).html('<img src="' + displayUrl + '" height="60" alt="TTD">');
        if (name) $('.ttd-label-slot' + selector).text(name);
        if (isProcessLocked) return;
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

      function refreshParentStatus() {
        try {
          if (window.parent && window.parent.refreshTableRow) {
            // Reload the DataTable row via ajax so serverside renders correct buttons
            window.parent.refreshTableRow(ticket);
          } else if (window.parent && window.parent.updateStatusBadge) {
            // Fallback
            var row = window.parent.$('.btn-detail[data-ticket="' + ticket + '"]').closest('tr');
            if (row.length) window.parent.updateStatusBadge(ticket, row);
          }
        } catch (e) { }
      }

      // ── Lampiran Modal Preview Handler ───────────────────────────────
      $(document).on('click', '.btn-preview-lampiran', function () {
        var filePath = $(this).data('path');
        $('#previewIframe').attr('src', filePath);
        $('#modalPreview').modal('show');
      });

      $('#modalPreview').on('hidden.bs.modal', function () {
        $('#previewIframe').attr('src', '');
      });
    });
  </script>

  <!-- Modal Preview Lampiran -->
  <div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" aria-labelledby="modalPreviewLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95%;">
      <div class="modal-content shadow-lg" style="border: none; border-radius: 8px; overflow: hidden;">
        <div
          class="modal-header bg-<?= $_SESSION['Theme'] ?? 'primary' ?> text-white d-flex align-items-center justify-content-between py-2 px-3">
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
</body>

</html>