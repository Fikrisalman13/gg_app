<?php
session_start();
require_once '../../koneksi.php';
$ticket = $_GET['ticket'] ?? '';
if (!$ticket) { die('Ticket tidak ditemukan'); }

$sql = "SELECT * FROM Form_Buka_Tanggal_Closingan WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) { die('Data tidak ditemukan'); }
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

// Permission check
$canSignPemohon = false; $canSignAtasan = false; $canSignKabag = false; $canSignDireksi = false;
if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0 || strcasecmp($role, 'Kadept IT') === 0) $canSignKabag = true;
            elseif (strcasecmp($role, 'Direksi') === 0) $canSignDireksi = true;
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
function fmtDate($dt) { return ($dt instanceof DateTime) ? $dt->format('d-m-Y') : (is_string($dt) && !empty($dt) ? date('d-m-Y', strtotime($dt)) : '-'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Buka Tanggal Closingan - <?= htmlspecialchars($ticket) ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<style>
 body{background:#ffffff;font-size:15px;color:#000;} table{border-collapse:collapse;width:100%;} td{padding:5px 8px;vertical-align:top;} .b{font-weight:bold;} .center{text-align:center;} .border{border:1px solid #000;}
</style>
<script>
window.addEventListener('load', function(){
  var canReject = <?php echo ($canSignKabag || $canSignDireksi) && !$isRejected ? 'true':'false'; ?>;
  var isRejected = <?php echo $isRejected ? 'true' : 'false'; ?>;
  var ticket = '<?= htmlspecialchars($ticket) ?>';
  if (window.parent && window.parent.updateRejectButtonVisibility) {
    window.parent.updateRejectButtonVisibility(canReject, ticket);
  }
});
</script>
</head>
<body>

<style>
  @media (max-width: 768px) {
      .form-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; padding-bottom: 10px; margin: 5px auto !important; }
      .outer-form { min-width: 800px; }
      img[alt="logo"] { width: 50px !important; }
      body { font-size: 14px; }
  }
</style>

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
            <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;font-size:16px;">FORM KOMUNIKASI BUKA TANGGAL CLOSINGAN</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0;">
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="width:160px;padding:4px 6px;">Nama Pemohon</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['nama_pemohon']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Jabatan</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['jabatan']) ?></td>
            <td style="width:140px;padding:4px 6px;">Tgl Pengajuan</td>
            <td style="padding:4px 6px;">: <?= fmtDate($data['tgl_pengajuan']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Departemen</td>
            <td style="padding:4px 6px;">: <?= htmlspecialchars($data['departemen']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Mengajukan permintaan untuk</td>
            <td colspan="3" style="padding:4px 6px;">: 
                <?php 
                    $checkGudang = ($data['request_gudang'] == 1) ? '&#9745;' : '&#9744;';
                    $checkTransaksi = ($data['request_transaksi'] == 1) ? '&#9745;' : '&#9744;';
                ?>
                <span style="display:inline-block;margin-right:20px;"><?= $checkGudang ?> Closingan Gudang</span>
                <span style="display:inline-block;"><?= $checkTransaksi ?> Closingan Transaksi</span>
            </td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Buka Tgl</td>
            <td colspan="3" style="padding:4px 6px;">: <?= fmtDate($data['buka_tgl']) ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;">Gudang/Transaksi</td>
            <td colspan="3" style="padding:4px 6px;">: <?= htmlspecialchars($data['gudang_transaksi'] ?? '-') ?></td>
          </tr>
          <tr>
            <td style="padding:4px 6px;vertical-align:top;">Keterangan/Alasan</td>
            <td colspan="3" style="padding:4px 6px;">: <?= nl2br(htmlspecialchars($data['keterangan'])) ?></td>
          </tr>
          <tr><td colspan="4" style="height:20px;"></td></tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="padding:6px;">
        <table class="signature-table" style="width:100%;border-collapse:collapse;text-align:center;table-layout:fixed;" border="1">
          <tr style="font-weight:bold;font-size:14px;">
            <td style="width:25%;padding:4px;">Diajukan oleh,</td>
            <td style="width:25%;padding:4px;">Diketahui oleh,</td>
            <td style="width:25%;padding:4px;">Mengetahui,</td>
            <td style="width:25%;padding:4px;">Disetujui oleh,</td>
          </tr>
          <tr style="height:120px;vertical-align:middle;">
            <!-- Pemohon -->
            <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-pemohon">
                  <?php if (isset($ttd['Pemohon'])): ?>
                      <img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Pemohon">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-pemohon" class="mt-2">
                  <?php if ($canSignPemohon && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="pemohon">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="pemohon">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
            </td>
            <!-- Atasan -->
            <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-atasan">
                  <?php if (isset($ttd['Atasan Pemohon'])): ?>
                      <img src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Atasan">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-atasan" class="mt-2">
                  <?php if ($canSignAtasan && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="atasan_pemohon">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="atasan_pemohon">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
            </td>
            <!-- Kabag IT -->
            <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-kabag">
                  <?php if (isset($ttd['Kabag IT'])): ?>
                      <img src="<?= htmlspecialchars($ttd['Kabag IT']['SignaturePath']) ?>" height="60" alt="TTD Kabag IT">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-kabag" class="mt-2">
                  <?php if ($canSignKabag && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Kabag IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="kabag_it">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Kabag IT'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="kabag_it">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Kabag IT'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
            </td>
            <!-- Direksi -->
            <td style="padding:6px; vertical-align:middle;">
                <div id="ttd-area-direksi">
                  <?php if (isset($ttd['Direksi'])): ?>
                      <img src="<?= htmlspecialchars($ttd['Direksi']['SignaturePath']) ?>" height="60" alt="TTD Direksi">
                  <?php endif; ?>
                </div>
                <div id="ttd-btn-direksi" class="mt-2">
                  <?php if ($canSignDireksi && !$isRejected): ?>
                    <?php if (isTtdByCurrentUser($ttd['Direksi'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="direksi">Hapus Tanda Tangan</button>
                    <?php elseif (isTtdExists($ttd['Direksi'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="direksi">Tanda Tangan</button>
                    <?php endif; ?>
                  <?php elseif (isTtdExists($ttd['Direksi'] ?? [])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php endif; ?>
                </div>
            </td>
          </tr>
          <tr style="text-align:center;font-weight:bold;font-size:14px;">
            <td id="ttd-label-pemohon"><?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?></td>
            <td id="ttd-label-atasan"><?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Pemohon' ?></td>
            <td id="ttd-label-kabag"><?= isset($ttd['Kabag IT']) ? htmlspecialchars($ttd['Kabag IT']['SignedByUserName']) : 'Kabag IT' ?></td>
            <td id="ttd-label-direksi"><?= isset($ttd['Direksi']) ? htmlspecialchars($ttd['Direksi']['SignedByUserName']) : 'Direksi' ?></td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="4" style="padding:0;">
        <table style="width:100%; border-collapse:collapse; border:none; border-top:1px solid #000; font-size:10px; text-align:center;">
          <tr>
            <td style="width:50%;padding:4px;border-right:1px solid #000;">SUM-FM-IT-019</td>
            <td style="width:50%;padding:4px;">ERP</td>
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
              <div style="border:1px solid #ccc;display:inline-block;width:100%;">
                <canvas id="ttdCanvas" width="600" height="200" style="background:#fff;cursor:crosshair;width:100%;height:200px;"></canvas>
              </div>
              <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvas">Bersihkan Canvas</button>
            </div>
            <div class="col-md-4 mb-3">
              <label><b>Atau Upload File PNG/JPG</b></label>
              <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
              <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1" checked>
                <label class="form-check-label" for="chkSaveTemplate">Simpan tanda tangan ini sebagai template</label>
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
$(function(){
    var currentRoleCode = null;
    var isRejected = <?= $isRejected ? 'true' : 'false' ?>;
    var ticket = '<?= htmlspecialchars($ticket) ?>';
    var roleToDelete = null;

    // --- Canvas Logic ---
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
        function draw(e) { if (!drawing) return; if(e.touches) e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
        function endDraw() { drawing = false; }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('touchstart', startDraw, {passive:false});
        canvas.addEventListener('touchmove', draw, {passive:false});
        canvas.addEventListener('touchend', endDraw);
        
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
    }

    $('#btnClearCanvas').on('click', function() { ctx.clearRect(0, 0, canvas.width, canvas.height); });
    $('#modalTtd').on('shown.bs.modal', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); $('#ttdFile').val(''); });

    // --- Signature Actions ---
    $(document).on('click', '.btn-ttd', function(){
        var roleCode = $(this).data('role');
        currentRoleCode = roleCode;
        $.post('ttd_sign.php', { ticket: ticket, role_code: roleCode }, function(resp){
            if (resp.success && resp.signature_url) {
                updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
            } else if (resp.need_template) {
                $('#ttdRoleCode').val(roleCode);
                $('#modalTtd').modal('show');
            } else {
                Swal.fire('Gagal', resp.message || 'Gagal memproses tanda tangan.', 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-delete-ttd', function(){
        roleToDelete = $(this).data('role');
        $('#modalKonfirmasiHapusTtd').modal('show');
    });

    $('#btnKonfirmasiHapusTtd').on('click', function(){
        if (!roleToDelete) return;
        $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function(resp){
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

    $('#btnSimpanTtd').on('click', function(){
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
            success: function(resp) {
                try { resp = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e){}
                if (resp.success && resp.signature_url) {
                    $('#modalTtd').modal('hide');
                    updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
                } else {
                    Swal.fire('Gagal', resp.message || 'Gagal menyimpan tanda tangan.', 'error');
                }
            }
        });
    });

    function updateTtdArea(roleCode, imgUrl, name, userId) {
        var areaId = '', btnAreaId = '', labelId = '';
        if (roleCode === 'pemohon') { areaId = '#ttd-area-pemohon'; btnAreaId = '#ttd-btn-pemohon'; labelId = '#ttd-label-pemohon'; }
        else if (roleCode === 'atasan_pemohon') { areaId = '#ttd-area-atasan'; btnAreaId = '#ttd-btn-atasan'; labelId = '#ttd-label-atasan'; }
        else if (roleCode === 'kabag_it') { areaId = '#ttd-area-kabag'; btnAreaId = '#ttd-btn-kabag'; labelId = '#ttd-label-kabag'; }
        else if (roleCode === 'direksi') { areaId = '#ttd-area-direksi'; btnAreaId = '#ttd-btn-direksi'; labelId = '#ttd-label-direksi'; }

        if (areaId) $(areaId).html('<img src="' + imgUrl + '" height="60" alt="TTD">');
        if (labelId && name) $(labelId).text(name);
        if (btnAreaId) {
            var btnDelete = $('<button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd">Hapus Tanda Tangan</button>');
            btnDelete.attr('data-role', roleCode);
            $(btnAreaId).html(btnDelete);
        }
    }

    function removeTtdArea(roleCode) {
        var areaId = '', btnAreaId = '', labelId = '', defaultLabel = '';
        if (roleCode === 'pemohon') { areaId = '#ttd-area-pemohon'; btnAreaId = '#ttd-btn-pemohon'; labelId = '#ttd-label-pemohon'; defaultLabel = 'Pemohon'; }
        else if (roleCode === 'atasan_pemohon') { areaId = '#ttd-area-atasan'; btnAreaId = '#ttd-btn-atasan'; labelId = '#ttd-label-atasan'; defaultLabel = 'Atasan Pemohon'; }
        else if (roleCode === 'kabag_it') { areaId = '#ttd-area-kabag'; btnAreaId = '#ttd-btn-kabag'; labelId = '#ttd-label-kabag'; defaultLabel = 'Kabag IT'; }
        else if (roleCode === 'direksi') { areaId = '#ttd-area-direksi'; btnAreaId = '#ttd-btn-direksi'; labelId = '#ttd-label-direksi'; defaultLabel = 'Direksi'; }

        if (areaId) $(areaId).empty();
        if (labelId) $(labelId).text(defaultLabel);
        if (btnAreaId) {
            var btnSign = $('<button type="button" class="btn btn-sm btn-outline-primary btn-ttd">Tanda Tangan</button>');
            btnSign.attr('data-role', roleCode);
            $(btnAreaId).html(btnSign);
        }
    }
});
</script>
</body>
</html>
