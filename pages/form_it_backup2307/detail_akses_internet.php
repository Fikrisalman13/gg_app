<?php
session_start();
require_once '../../koneksi.php';
$ticket = $_GET['ticket'] ?? '';
if (!$ticket) { die('Ticket tidak ditemukan'); }

$sql = "SELECT * FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) { die('Data tidak ditemukan'); }
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

// Permission check
$canSignPemohon = false; $canSignAtasanPemohon = false; $canSignPetugasIT = false; $canSignKabagIT = false; $canSignKadeptIT = false;
if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasanPemohon = true;
            elseif (strcasecmp($role, 'Petugas IT') === 0) $canSignPetugasIT = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0) $canSignKabagIT = true;
            elseif (strcasecmp($role, 'Kadept IT') === 0) $canSignKadeptIT = true;
        }
    }
}

// Get signatures
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

function isTtdExists($ttdData) { return !empty($ttdData['SignaturePath']); }
function isTtdByCurrentUser($ttdData, $userId) { return isTtdExists($ttdData) && ($ttdData['SignedByUserId'] == $userId); }
function fmtDate($dt) { return ($dt instanceof DateTime) ? $dt->format('d-m-Y') : (is_string($dt) && !empty($dt) ? $dt : '-'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pengajuan Akses Internet - <?= htmlspecialchars($ticket) ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<style>
 body{background:#ffffff;font-size:15px;} table{border-collapse:collapse;width:100%;} td{padding:5px 8px;vertical-align:top;} .b{font-weight:bold;} .center{text-align:center;} .border{border:1px solid #000;}
 table[border="1"] { border: 1px solid #000; }
 table[border="1"] td { border: 1px solid #000; }
 /* Request display */
 .req-item{margin-bottom:6px; display:inline-block; vertical-align:top; width:auto; max-width:calc(100% - 20px);}
 .req-title{font-weight:bold; display:inline-block; margin-right:6px;}
 .req-type{font-style:normal; color:#333;}
.req-detail{margin:4px 0 0 0; font-size:13px; color:#333;}
</style>
<script>
window.addEventListener('load', function(){
  var canReject = <?php echo ($canSignKabagIT || $canSignKadeptIT) && !$isRejected ? 'true':'false'; ?>;
  var ticket = '<?= htmlspecialchars($ticket) ?>';
  if (window.parent && window.parent.updateRejectButtonVisibility) {
    window.parent.updateRejectButtonVisibility(canReject, ticket);
  }
});
</script>
</head>
<body>

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
      img[alt="logo"] { width: 50px !important; }
      
      /* Adjust font sizes slightly */
      body { font-size: 14px; }
  }
</style>

   <!-- STATUS PENOLAKAN (jika ada) tetap ditampilkan di atas form wrapper -->
   <?php include 'form_contents/components/rejection_status.php'; ?>
<div class="form-wrapper" style="max-width:900px;margin:10px auto;">
  <table class="outer-form" style="width:100%;border:1px solid #000;border-collapse:collapse;font-size:14px;line-height:1.4;">
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
            <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;">PENGAJUAN AKSES INTERNET</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0;">
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="width:150px;padding:4px 6px;">Nama Pemohon</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['nama_pemohon']) ?></td>
          </tr>
          <tr>
            <td style="width:150px;padding:4px 6px;">Jabatan</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['jabatan']) ?></td>
            <td style="width:140px;padding:4px 6px;">Tgl Pengajuan</td>
            <td style="padding:4px 6px;">: <?= fmtDate($data['tgl_pengajuan']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Departemen</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['departemen']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Area</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['area']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Mengajukan permintaan untuk</td>
            <td colspan="3" style="padding:4px 6px;">:
              <?php
                $parts = [];
                if (!empty($data['request_akses_internet'])) {
                  $akses = '<span class="req-title">Akses Internet</span><span class="req-type">— ' . htmlspecialchars($data['akses_type'] ?? '-') . '</span>';
                  if (!empty($data['akses_temporary_from']) || !empty($data['akses_temporary_to'])) {
                    $akses .= '<div class="req-detail">Durasi: ' . fmtDate($data['akses_temporary_from']) . ' s/d ' . fmtDate($data['akses_temporary_to']) . '</div>';
                  } elseif (!empty($data['durasi_temporary'])) {
                    $akses .= '<div class="req-detail">' . htmlspecialchars($data['durasi_temporary']) . '</div>';
                  }
                  $akses = '<div class="req-entry">' . $akses . '</div>'; $parts[] = $akses;
                }

                if (!empty($data['request_tambah_bandwidth'])) {
                  $bw = '<span class="req-title">Penambahan Bandwidth</span><span class="req-type">— ' . htmlspecialchars($data['bandwidth_type'] ?? '-') . '</span>';
                  if (!empty($data['bandwidth_temporary_from']) || !empty($data['bandwidth_temporary_to'])) {
                    $bw .= '<div class="req-detail">Durasi: ' . fmtDate($data['bandwidth_temporary_from']) . ' s/d ' . fmtDate($data['bandwidth_temporary_to']) . '</div>';
                  }
                  $bw = '<div class="req-entry">' . $bw . '<div class="req-detail">Penambahan: ' . htmlspecialchars($data['tambah_bandwidth'] ?? '-') . ' ' . htmlspecialchars($data['tambah_bandwidth_unit'] ?? '') . '</div></div>'; $parts[] = $bw;
                }

                if (empty($parts)) {
                  echo '<small>- (Tidak ada permintaan)</small>';
                } else {
                  // Wrap each part in a consistent container for alignment
                  $out = '';
                  foreach ($parts as $p) {
                    $out .= '<div class="req-item">' . $p . '</div>';
                  }
                  echo $out;
                }
              ?>
            </td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Keterangan</td>
            <td colspan="3" style="padding:4px 6px;">: <?= nl2br(htmlspecialchars($data['keterangan'])) ?></td>
          </tr>
        </table>
      </td>
    </tr>
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
            <td style="padding:4px;">Di Ajukan Oleh</td>
            <td style="padding:4px;">Mengetahui</td>
            <td style="padding:4px;">Di Ketahui Oleh</td>
            <td style="padding:4px;" colspan="2">Di Setujui Oleh</td>
          </tr>
          <tr style="height:120px;text-align:center;vertical-align:middle;">
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-pemohon">
                <?php if (isset($ttd['Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Pemohon"><?php endif; ?>
              </div>
              <?php if ($canSignPemohon && !$isRejected): ?>
                <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="pemohon">Hapus Tanda Tangan</button>
                <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda Tangan</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-atasan-pemohon">
                <?php if (isset($ttd['Atasan Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Atasan Pemohon"><?php endif; ?>
              </div>
              <?php if ($canSignAtasanPemohon && !$isRejected): ?>
                <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="atasan_pemohon">Hapus Tanda Tangan</button>
                <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="atasan_pemohon">Tanda Tangan</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-petugas-it">
                <?php if (isset($ttd['Petugas IT'])): ?><img src="<?= htmlspecialchars($ttd['Petugas IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Petugas IT"><?php endif; ?>
              </div>
              <?php if ($canSignPetugasIT && !$isRejected): ?>
                <?php if (isTtdByCurrentUser($ttd['Petugas IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="petugas_it">Hapus Tanda Tangan</button>
                <?php elseif (isTtdExists($ttd['Petugas IT'] ?? [])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="petugas_it">Tanda Tangan</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-kabag-it">
                <?php if (isset($ttd['Kabag IT'])): ?><img src="<?= htmlspecialchars($ttd['Kabag IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Kabag IT"><?php endif; ?>
              </div>
              <?php if ($canSignKabagIT && !$isRejected): ?>
                <?php if (isTtdByCurrentUser($ttd['Kabag IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="kabag_it">Hapus Tanda Tangan</button>
                <?php elseif (isTtdExists($ttd['Kabag IT'] ?? [])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kabag_it">Tanda Tangan</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-kadept-it">
                <?php if (isset($ttd['Kadept IT'])): ?><img src="<?= htmlspecialchars($ttd['Kadept IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Kadept IT"><?php endif; ?>
              </div>
              <?php if ($canSignKadeptIT && !$isRejected): ?>
                <?php if (isTtdByCurrentUser($ttd['Kadept IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="kadept_it">Hapus Tanda Tangan</button>
                <?php elseif (isTtdExists($ttd['Kadept IT'] ?? [])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kadept_it">Tanda Tangan</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <tr style="text-align:center;font-size:14px;font-weight:bold;">
            <td style="padding:4px;" id="ttd-label-pemohon"><?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?></td>
            <td style="padding:4px;" id="ttd-label-atasan"><?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Pemohon' ?></td>
            <td style="padding:4px;" id="ttd-label-petugas"><?= isset($ttd['Petugas IT']) ? htmlspecialchars($ttd['Petugas IT']['SignedByUserName']) : 'Petugas IT' ?></td>
            <td style="padding:4px;" id="ttd-label-kabag"><?= isset($ttd['Kabag IT']) ? htmlspecialchars($ttd['Kabag IT']['SignedByUserName']) : 'Kabag IT' ?></td>
            <td style="padding:4px;" id="ttd-label-kadept"><?= isset($ttd['Kadept IT']) ? htmlspecialchars($ttd['Kadept IT']['SignedByUserName']) : 'Kadept IT' ?></td>
          </tr>
        </table>
        </div>
      </td>
    </tr>
    <tr>
      <td style="padding:6px;font-size:14px;">
        <b>Keterangan :</b><br>
        <ol style="margin:4px 0 0 18px;padding-left:0;">
          <li>Tidak diperkenankan user memakai browsing ke alamat situs-situs Pornografi, Games Online , sosial networking (streaming video/audio), atau situs-situs yang tidak ada hubungannya dengan pekerjaan sesuai dengan peraturan yang berlaku.</li>
          <li>Semua transaksi log internet tercatat di firewall kami sebagai bahan pengecekan jawaban ke management bila di perlukan suatu saat.</li>
          <li>IT berhak menutup akses bila di pergunakan diluar kepentingan kantor / pekerjaan.</li>
          <li>Bila ingin melakukan aktivitas download file yang besar koordinasikan ke IT Departemen untuk penggunaan internet tetap dengan baik.</li>
        </ol>
      </td>
    </tr>
    <tr>
      <td style="padding:0;">
        <table style="width:100%;border-collapse:collapse;border-top:1px solid #000;">
          <tr style="text-align:center;font-weight:bold;">
            <td style="width:70%;padding:6px;border-right:1px solid #000;">SUM-FM-IT-004</td>
            <td style="width:30%;padding:6px;">NT</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(document).ready(function(){
    var ticket = '<?= htmlspecialchars($ticket) ?>';
    
    // Handle Tanda Tangan button - langsung sign tanpa konfirmasi (seperti detail_tiket.php)
    $('.btn-ttd').on('click', function(){
        var role = $(this).data('role');
        
        // Langsung POST ke ttd_sign.php dengan parameter role_code
        $.ajax({
            url: '/gg_app/pages/form_it/ttd_sign.php',
            method: 'POST',
            data: { ticket: ticket, role_code: role },
            dataType: 'json',
            success: function(response){
                if (response && response.success) {
                    // Reload page untuk update tampilan
                    location.reload();
                } else {
                    Swal.fire('Gagal!', response.message || 'Gagal menyimpan tanda tangan', 'error');
                }
            },
            error: function(xhr){
                var errorMsg = 'Terjadi kesalahan saat menyimpan tanda tangan';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.message) errorMsg = resp.message;
                } catch(e) {}
                Swal.fire('Error!', errorMsg, 'error');
            }
        });
    });
    
    // Handle Hapus Tanda Tangan button
    $('.btn-delete-ttd').on('click', function(){
        var role = $(this).data('role');
        
        Swal.fire({
            title: 'Hapus Tanda Tangan?',
            text: 'Apakah Anda yakin ingin menghapus tanda tangan Anda?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                $.ajax({
                    url: '/gg_app/pages/form_it/delete_ttd.php',
                    method: 'POST',
                    data: { ticket: ticket, role_code: role },
                    dataType: 'json',
                    success: function(response){
                        if (response && response.success) {
                            Swal.fire('Berhasil!', 'Tanda tangan berhasil dihapus', 'success').then(function(){
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', response.message || 'Gagal menghapus tanda tangan', 'error');
                        }
                    },
                    error: function(){
                        Swal.fire('Error!', 'Terjadi kesalahan saat menghapus tanda tangan', 'error');
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>
