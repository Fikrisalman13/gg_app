<?php
session_start();
require_once '../../koneksi.php';
$ticket = $_GET['ticket'] ?? '';
if (!$ticket) {
  die('Ticket tidak ditemukan');
}

$sql = "SELECT * FROM Form_Pengajuan_CCTV WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
  die('Data CCTV tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Flag rejected
$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

// Permission check via User_TTD_Template (similar to perangkat IT)
$canSignPemohon = $canSignAtasanPemohon = $canSignPetugasCCTV = $canSignKabagIT = $canSignKadeptIT = false;
if (isset($_SESSION['UserId'])) {
  $sqlRole = "SELECT DISTINCT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
  $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
  if ($stmtRole) {
    while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
      $role = trim($rowR['GroupRole'] ?? '');
      if (strcasecmp($role, 'Pemohon') === 0)
        $canSignPemohon = true;
      elseif (strcasecmp($role, 'Atasan Pemohon') === 0)
        $canSignAtasanPemohon = true;
      elseif (strcasecmp($role, 'Petugas CCTV') === 0)
        $canSignPetugasCCTV = true;
      elseif (strcasecmp($role, 'Kabag IT') === 0)
        $canSignKabagIT = true;
      elseif (strcasecmp($role, 'Kadept IT') === 0)
        $canSignKadeptIT = true;
    }
  }
}

// Ambil TTD yang sudah ada
$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
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

function isTtdByCurrentUser($ttdArray, $currentUserId)
{
  if (!isset($ttdArray['SignaturePath']) || empty($ttdArray['SignaturePath']))
    return false;
  if (!isset($ttdArray['SignedByUserId']))
    return false;
  return $ttdArray['SignedByUserId'] == $currentUserId;
}
function isTtdExists($ttdArray)
{
  return isset($ttdArray['SignaturePath']) && !empty($ttdArray['SignaturePath']);
}
function fmtDate($d)
{
  if ($d instanceof DateTime)
    return $d->format('d-m-Y');
  if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
    $p = explode('-', $d);
    return $p[2] . '-' . $p[1] . '-' . $p[0];
  }
  return '-';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Detail Pengajuan CCTV - <?= htmlspecialchars($ticket) ?></title>
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

    .ttd-scroll {
      width: 100%;
    }

    .ttd-scroll table {
      width: 100%;
      text-align: center;
    }

    @media(max-width:768px) {
      .ttd-scroll {
        overflow-x: auto;
      }

      .ttd-scroll table {
        min-width: 600px;
      }
    }
  </style>
  <script>
    window.addEventListener('load', function () {
      var canReject = <?php echo ($canSignKabagIT || $canSignKadeptIT) && !$isRejected ? 'true' : 'false'; ?>;
      var ticket = '<?= htmlspecialchars($ticket) ?>';
      if (window.parent && window.parent.updateRejectButtonVisibility) {
        window.parent.updateRejectButtonVisibility(canReject, ticket);
      }
    });
  </script>
</head>

<body>
  <style>

  </style>
  <style>
    /* Mobile Responsive Styles - Horizontal Scroll */
    @media (max-width: 768px) {
      .form-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        padding-bottom: 10px;
        margin: 5px auto !important;
      }

      /* Force table to keep its minimum width to preserve PC layout */
      .outer-form {
        min-width: 800px;
      }

      /* Header logo adjustment */
      img[alt="logo"] {
        width: 50px !important;
      }

      /* Adjust font sizes slightly */
      body {
        font-size: 14px;
      }
    }
  </style>

  <!-- STATUS PENOLAKAN (jika ada) tetap ditampilkan di atas form wrapper -->
  <?php include 'form_contents/components/rejection_status.php'; ?>
  </div>
  </div>
  <div class="form-wrapper" style="max-width:900px;margin:10px auto;">
    <table class="outer-form"
      style="width:100%;border:1px solid #000;border-collapse:collapse;font-size:14px;line-height:1.4;">
      <tr>
        <td style="width:100%;padding:0;">
          <table style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="width:90px;border-right:1px solid #000;padding:8px 6px;vertical-align:top;text-align:center;">
                <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
              </td>
              <td style="padding:6px 8px;vertical-align:top;">
                <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
                Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                Banjaran – Kab. Bandung<br>
                40377 Telp. (022) 594-0313
              </td>
            </tr>
            <tr>
              <td colspan="2"
                style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;">
                PENGAJUAN PERMINTAAN REKAMAN CCTV</td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="padding:0;">
          <table style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="width:150px;padding:4px 6px;">Nama Pemohon</td>
              <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($data['nama_pemohon']) ?></td>
            </tr>
            <tr>
              <td style="width:150px;padding:4px 6px;">Jabatan</td>
              <td style="padding:4px 6px;">: <?= htmlspecialchars($data['jabatan']) ?></td>
              <td style="width:1%; padding:4px 6px; text-align: right; white-space: nowrap;">Tgl Pengajuan</td>
              <td style="width:1%; padding:4px 6px; white-space: nowrap;">: <?= fmtDate($data['tgl_pengajuan']) ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Departemen</td>
              <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($data['departemen']) ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Area</td>
              <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($data['area']) ?></td>
            </tr>
            <tr>
              <td style="padding:4px 6px;">Mengajukan permintaan untuk</td>
              <td colspan="3" style="padding:4px 6px;">: Rekaman Cctv</td>
            </tr>
            <tr>
              <td style="padding:4px 6px; vertical-align: top;">Keterangan</td>
              <td colspan="3" style="padding:4px 6px; vertical-align: top;">
                <div style="display: flex;">
                  <span style="margin-right: 5px;">:</span>
                  <span style="flex: 1;"><?= nl2br(htmlspecialchars($data['keterangan'])) ?></span>
                  <br>
                  <br>
                  <br>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="padding:2px 6px; text-align:center;">

          <!-- WRAPPER yang membuat semua isi benar-benar berada di tengah -->
          <div style="display:inline-block; text-align:left;">

            <div style="font-weight:bold; font-size:18px; text-align:center; margin-bottom:10px;">
              REKAMAN CCTV
            </div>

            <table style="border-collapse:collapse; margin:auto; text-align:left;">
              <?php
              $tgl1 = fmtDate($data['tanggal_1']);
              $jm11 = htmlspecialchars($data['jam_mulai_1']);
              $jm12 = htmlspecialchars($data['jam_selesai_1']);
              $tgl2 = fmtDate($data['tanggal_2']);
              $jm21 = htmlspecialchars($data['jam_mulai_2']);
              $jm22 = htmlspecialchars($data['jam_selesai_2']);
              $checked1 = ($tgl1 !== '-' && $tgl1 !== '');
              $checked2 = ($tgl2 !== '-' && $tgl2 !== '');
              ?>

              <tr>
                <td style="padding:4px 6px; white-space:nowrap; font-size:15px;">
                  <span
                    style="display:inline-block;width:20px;height:20px;border:1px solid #000;margin-right:6px;text-align:center;line-height:20px;font-weight:bold;font-size:18px;">
                    <?= $checked1 ? '✔' : '&nbsp;' ?>
                  </span>

                  Tanggal
                  <span style="display:inline-block;width:160px;border-bottom:1px solid #000;text-align:center;">
                    <?= $tgl1 !== '-' ? $tgl1 : '&nbsp;' ?>
                  </span>

                  &nbsp; Jam
                  <span style="display:inline-block;width:90px;border-bottom:1px solid #000;text-align:center;">
                    <?= $jm11 ?: '&nbsp;' ?>
                  </span>

                  s/d
                  <span style="display:inline-block;width:90px;border-bottom:1px solid #000;text-align:center;">
                    <?= $jm12 ?: '&nbsp;' ?>
                  </span>
                </td>
              </tr>

              <tr>
                <td style="padding:4px 6px; white-space:nowrap; font-size:15px;">
                  <span
                    style="display:inline-block;width:20px;height:20px;border:1px solid #000;margin-right:6px;text-align:center;line-height:20px;font-weight:bold;font-size:18px;">
                    <?= $checked2 ? '✔' : '&nbsp;' ?>
                  </span>

                  Tanggal
                  <span style="display:inline-block;width:160px;border-bottom:1px solid #000;text-align:center;">
                    <?= $tgl2 !== '-' ? $tgl2 : '&nbsp;' ?>
                  </span>

                  &nbsp; Jam
                  <span style="display:inline-block;width:90px;border-bottom:1px solid #000;text-align:center;">
                    <?= $jm21 ?: '&nbsp;' ?>
                  </span>

                  s/d
                  <span style="display:inline-block;width:90px;border-bottom:1px solid #000;text-align:center;">
                    <?= $jm22 ?: '&nbsp;' ?>
                  </span>
                </td>
              </tr>

            </table>

          </div>
        </td>

      <tr>
        <td style="padding:6px;">
          <div class="signature-wrapper">
            <table class="signature-table" style="width:100%;border-collapse:collapse;table-layout:fixed;" border="1">
              <colgroup>
                <col style="width:20%;">
                <col style="width:20%;">
                <col style="width:20%;">
                <col style="width:20%;">
                <col style="width:20%;">
              </colgroup>
              <tr style="text-align:center;font-size:14px;font-weight:bold;">
                <td style="padding:4px;">Diajukan Oleh</td>
                <td style="padding:4px;" colspan="2">Diketahui Oleh</td>
                <td style="padding:4px;">Mengetahui</td>
                <td style="padding:4px;">Disetujui Oleh</td>
              </tr>
              <tr style="height:120px;text-align:center;vertical-align:middle;">
                <td style="padding:6px;vertical-align:middle;">
                  <div id="ttd-area-pemohon">
                    <?php if (isset($ttd['Pemohon'])): ?><img
                        src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60"
                        alt="TTD Pemohon"><?php endif; ?>
                  </div>
                  <?php if ($canSignPemohon && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd"
                        data-role="pemohon">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah
                        Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda
                        Tangan</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td style="padding:6px;vertical-align:middle;">
                  <div id="ttd-area-atasan-pemohon">
                    <?php if (isset($ttd['Atasan Pemohon'])): ?><img
                        src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60"
                        alt="TTD Atasan Pemohon"><?php endif; ?>
                  </div>
                  <?php if ($canSignAtasanPemohon && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd"
                        data-role="atasan_pemohon">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah
                        Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd"
                        data-role="atasan_pemohon">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td style="padding:6px;vertical-align:middle;">
                  <div id="ttd-area-petugas-cctv">
                    <?php if (isset($ttd['Petugas CCTV'])): ?><img
                        src="<?= htmlspecialchars($ttd['Petugas CCTV']['SignaturePath']) ?>" height="60"
                        alt="TTD Petugas CCTV"><?php endif; ?>
                  </div>
                  <?php if ($canSignPetugasCCTV && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Petugas CCTV'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd"
                        data-role="petugas_cctv">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Petugas CCTV'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah
                        Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd"
                        data-role="petugas_cctv">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td style="padding:6px;vertical-align:middle;">
                  <div id="ttd-area-kadept">
                    <?php if (isset($ttd['Kabag IT'])): ?><img
                        src="<?= htmlspecialchars($ttd['Kabag IT']['SignaturePath']) ?>" height="60"
                        alt="TTD Kabag IT"><?php endif; ?>
                  </div>
                  <?php if ($canSignKabagIT && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Kabag IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd"
                        data-role="kabag_it">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Kabag IT'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah
                        Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kabag_it">Tanda
                        Tangan</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td style="padding:6px;vertical-align:middle;">
                  <div id="ttd-area-kabag">
                    <?php if (isset($ttd['Kadept IT'])): ?><img
                        src="<?= htmlspecialchars($ttd['Kadept IT']['SignaturePath']) ?>" height="60"
                        alt="TTD Kadept IT"><?php endif; ?>
                  </div>
                  <?php if ($canSignKadeptIT && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Kadept IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd"
                        data-role="kadept_it">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Kadept IT'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah
                        Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kadept_it">Tanda
                        Tangan</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <tr style="text-align:center;font-weight:bold;font-size:14px;">
                <td id="ttd-label-pemohon">
                  <?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?>
                </td>
                <td id="ttd-label-atasan-pemohon">
                  <?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Pemohon' ?>
                </td>
                <td id="ttd-label-petugas-cctv">
                  <?= isset($ttd['Petugas CCTV']) ? htmlspecialchars($ttd['Petugas CCTV']['SignedByUserName']) : 'Petugas CCTV' ?>
                </td>
                <td id="ttd-label-kadept">
                  <?= isset($ttd['Kabag IT']) ? htmlspecialchars($ttd['Kabag IT']['SignedByUserName']) : 'Kabag IT' ?>
                </td>
                <td id="ttd-label-kabag">
                  <?= isset($ttd['Kadept IT']) ? htmlspecialchars($ttd['Kadept IT']['SignedByUserName']) : 'Kadept IT' ?>
                </td>
              </tr>
            </table>
          </div>
        </td>
      </tr>
      <tr>
        <td style="padding:6px 8px;font-size:14px;">
          <b>Perhatian :</b><br>
          <ol style="margin:4px 0 0 18px;padding-left:0;">
            <li>Hasil rekaman ini merupakan data rahasia perusahaan tidak bisa di pakai dengan sembarangan apabila di
              ketahui tidak sesuai prosedur akan dikenakan sangsi sesuai dengan peraturan yang berlaku.</li>
            <li>Sistem penarikan rekaman CCTV ini mempunyai standar penyimpanan selama 14 hari secara nasional.</li>
            <li>Semua harus bertanggungjawab atas penyimpanan hasil rekaman yang di berikan dan hanya di perbolehkan di
              berikan ke pihak berwenang bila di butuhkan.</li>
            <li>Note : Mengenai penarikan rekaman sebisa mungkin di berikan periode dan jam yang di perlukan utk
              memepercepat penarikan rekaman CCTV.</li>
          </ol>
        </td>
      </tr>
      <tr>
        <td style="padding:0;">
          <table style="width:100%;border-collapse:collapse;" border="1">
            <tr>
              <td style="width:70%;text-align:center;font-weight:bold;padding:4px;">SUM-FM-IT-010</td>
              <td style="width:30%;text-align:center;font-weight:bold;padding:4px;">CCTV</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>


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
              <input type="hidden" name="ticket" value="<?= htmlspecialchars($data['ticket']) ?>">
              <input type="hidden" name="role_code" id="ttdRoleCode" value="">
              <input type="hidden" name="canvas_data" id="canvasData" value="">
              <div class="row">
                <div class="col-md-8 mb-3">
                  <label><b>Gambar di Canvas</b></label>
                  <div style="border:1px solid #ccc;display:inline-block;">
                    <canvas id="ttdCanvas" width="600" height="200" style="background:#fff;cursor:crosshair;"></canvas>
                  </div>
                  <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvas">Bersihkan
                    Canvas</button>
                </div>
                <div class="col-md-4 mb-3">
                  <label><b>Atau Upload File PNG/JPG</b></label>
                  <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg"
                    class="form-control mb-2">
                  <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1"
                      checked>
                    <label class="form-check-label" for="chkSaveTemplate">Simpan tanda tangan ini sebagai
                      template</label>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success" id="btnSimpanTtd">Simpan & Gunakan</button>
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
        var roleToDelete = null;

        function resetCanvas() { ctx.clearRect(0, 0, canvas.width, canvas.height); }
        $('#modalTtd').on('shown.bs.modal', function () { resetCanvas(); $('#ttdFile').val(''); });

        // Sign button
        $('.btn-ttd').on('click', function () {
          var roleCode = $(this).data('role');
          currentRoleCode = roleCode;
          $.post('ttd_sign.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleCode }, function (resp) {
            try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { resp = {}; }
            if (resp && resp.success && resp.signature_url) {
              updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            } else if (resp && resp.need_template) {
              $('#ttdRoleCode').val(roleCode); $('#modalTtd').modal('show');
            } else {
              alert(resp.message || 'Gagal memproses tanda tangan.');
            }
          });
        });

        // Delete button
        $('.btn-delete-ttd').on('click', function () { roleToDelete = $(this).data('role'); console.log('Delete clicked role:', roleToDelete); $('#modalKonfirmasiHapusTtd').modal('show'); });
        $('#btnKonfirmasiHapusTtd').on('click', function () {
          if (!roleToDelete) { alert('Role tidak ditemukan.'); return; }
          $.post('delete_ttd.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleToDelete }, function (resp) {
            var rawResp = resp;
            try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { console.error('JSON parse error delete_ttd:', e, rawResp); resp = {}; }
            $('#modalKonfirmasiHapusTtd').modal('hide');
            if (resp && resp.success) {
              console.log('Delete success for role', roleToDelete, resp);
              removeTtdArea(roleToDelete);
              $('#pesanSuksesHapus').text(resp.message || 'Tanda tangan berhasil dihapus.');
              $('#modalSuksesHapusTtd').modal('show');
            } else {
              console.error('Delete failed response:', resp);
              alert(resp.message || 'Gagal menghapus tanda tangan.');
            }
          }).fail(function (xhr) {
            $('#modalKonfirmasiHapusTtd').modal('hide');
            console.error('Ajax fail delete_ttd', xhr.status, xhr.responseText);
            alert('Gagal menghapus tanda tangan (network/server error).');
          });
        });

        // Canvas drawing
        var canvas = document.getElementById('ttdCanvas');
        var ctx = canvas.getContext('2d');
        var drawing = false; ctx.strokeStyle = '#000'; ctx.lineWidth = 2;
        function getPos(e) { var r = canvas.getBoundingClientRect(); var x, y; if (e.touches && e.touches.length) { x = e.touches[0].clientX - r.left; y = e.touches[0].clientY - r.top; } else { x = e.clientX - r.left; y = e.clientY - r.top; } return { x: x, y: y }; }
        function startDraw(e) { drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function draw(e) { if (!drawing) return; e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
        function endDraw() { drawing = false; }
        canvas.addEventListener('mousedown', startDraw); canvas.addEventListener('mousemove', draw); canvas.addEventListener('mouseup', endDraw); canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', function (e) { startDraw(e); }, { passive: false });
        canvas.addEventListener('touchmove', function (e) { draw(e); }, { passive: false });
        canvas.addEventListener('touchend', function (e) { endDraw(e); }, { passive: false });
        $('#btnClearCanvas').on('click', function () { resetCanvas(); });

        // Save TTD
        $('#btnSimpanTtd').on('click', function () {
          var roleCode = $('#ttdRoleCode').val() || currentRoleCode;
          if (!roleCode) { alert('Role tanda tangan tidak dikenal.'); return; }
          $('#canvasData').val(canvas.toDataURL('image/png'));
          var form = document.getElementById('formTtd');
          var fd = new FormData(form);
          $.ajax({
            url: 'ttd_save_template.php', method: 'POST', data: fd, processData: false, contentType: false,
            success: function (resp) {
              try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { resp = {}; }
              if (resp && resp.success && resp.signature_url) { $('#modalTtd').modal('hide'); updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); }
              else { alert(resp.message || 'Gagal menyimpan tanda tangan.'); }
            }, error: function () { alert('Error saat menyimpan tanda tangan.'); }
          });
        });

        function updateTtdArea(roleCode, imgUrl, name, signedByUserId) {
          var areaId, labelId;
          if (roleCode === 'pemohon') { areaId = '#ttd-area-pemohon'; labelId = '#ttd-label-pemohon'; }
          else if (roleCode === 'atasan_pemohon') { areaId = '#ttd-area-atasan-pemohon'; labelId = '#ttd-label-atasan-pemohon'; }
          else if (roleCode === 'petugas_cctv') { areaId = '#ttd-area-petugas-cctv'; labelId = '#ttd-label-petugas-cctv'; }
          else if (roleCode === 'kabag_it') { areaId = '#ttd-area-kadept'; labelId = '#ttd-label-kadept'; }
          else if (roleCode === 'kadept_it') { areaId = '#ttd-area-kabag'; labelId = '#ttd-label-kabag'; }
          if (areaId) $(areaId).html('<img src="' + imgUrl + '" height="60">');
          if (labelId && name) $(labelId).text(name);
          var tdContainer = $('button[data-role="' + roleCode + '"]').closest('td');
          if (tdContainer.length) {
            tdContainer.find('button[data-role="' + roleCode + '"]').remove();
            var currentUserId = <?= $_SESSION['UserId'] ?? 0 ?>;
            if (isRejected) return;
            if (signedByUserId && parseInt(signedByUserId) === parseInt(currentUserId)) {
              tdContainer.append('<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="' + roleCode + '">Hapus Tanda Tangan</button>');
              attachDeleteButtonListener();
            } else {
              tdContainer.append('<button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>');
            }
          }
        }
        function removeTtdArea(roleCode) {
          var areaId, labelId, def;
          if (roleCode === 'pemohon') { areaId = '#ttd-area-pemohon'; labelId = '#ttd-label-pemohon'; def = 'Pemohon'; }
          else if (roleCode === 'atasan_pemohon') { areaId = '#ttd-area-atasan-pemohon'; labelId = '#ttd-label-atasan-pemohon'; def = 'Atasan Pemohon'; }
          else if (roleCode === 'petugas_cctv') { areaId = '#ttd-area-petugas-cctv'; labelId = '#ttd-label-petugas-cctv'; def = 'Petugas CCTV'; }
          else if (roleCode === 'kabag_it') { areaId = '#ttd-area-kadept'; labelId = '#ttd-label-kadept'; def = 'Kabag IT'; }
          else if (roleCode === 'kadept_it') { areaId = '#ttd-area-kabag'; labelId = '#ttd-label-kabag'; def = 'Kadept IT'; }
          if (areaId) $(areaId).html(''); if (labelId) $(labelId).text(def);
          var tdContainer = $('button[data-role="' + roleCode + '"]').closest('td');
          if (tdContainer.length) { tdContainer.find('button[data-role="' + roleCode + '"]').remove(); if (!isRejected) { tdContainer.append('<button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="' + roleCode + '">Tanda Tangan</button>'); attachSignButtonListener(); } }
        }
        function attachSignButtonListener() { $(document).off('click', '.btn-ttd').on('click', '.btn-ttd', function () { var roleCode = $(this).data('role'); currentRoleCode = roleCode; $.post('ttd_sign.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleCode }, function (resp) { try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { resp = {}; } if (resp && resp.success && resp.signature_url) { updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); } else if (resp && resp.need_template) { $('#ttdRoleCode').val(roleCode); $('#modalTtd').modal('show'); } else { alert(resp.message || 'Gagal memproses tanda tangan.'); } }); }); }
        function attachDeleteButtonListener() { $(document).off('click', '.btn-delete-ttd').on('click', '.btn-delete-ttd', function () { roleToDelete = $(this).data('role'); $('#modalKonfirmasiHapusTtd').modal('show'); }); }
      });
    </script>
</body>

</html>