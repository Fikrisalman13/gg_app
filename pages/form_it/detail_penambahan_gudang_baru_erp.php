<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../koneksi.php';

$ticket = $_GET['ticket'] ?? '';
if ($ticket === '') {
    die('Ticket tidak ditemukan');
}

$sql = "SELECT * FROM dbo.Form_Penambahan_Gudang_Baru_ERP WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

$canSignPemohon = false;
$canSignAtasan = false;
$canSignIT = false;
$canSignDireksi = false;
$itRoleCode = 'kabag_it';
if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0) {
                $canSignIT = true;
                $itRoleCode = 'kabag_it';
            } elseif (strcasecmp($role, 'Kadept IT') === 0) {
                $canSignIT = true;
                $itRoleCode = 'kadept_it';
            }
            elseif (strcasecmp($role, 'Direksi') === 0) $canSignDireksi = true;
        }
        sqlsrv_free_stmt($stmtRole);
    }
}

$sessionName = trim($_SESSION['NamaLengkap'] ?? ($_SESSION['UserFullName'] ?? ($_SESSION['UserName'] ?? '')));
$createdBy = trim($data['created_by'] ?? '');
$namaPemohon = trim($data['nama_pemohon'] ?? '');
if (
    $sessionName !== ''
    && (
        strcasecmp($sessionName, $namaPemohon) === 0
        || strcasecmp($sessionName, $createdBy) === 0
    )
) {
    $canSignPemohon = true;
}

$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd) {
    while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $ttd[$rowTtd['GroupRole']] = [
            'SignaturePath' => $rowTtd['SignaturePath'],
            'SignedByUserName' => $rowTtd['SignedByUserName'],
            'SignedByUserId' => $rowTtd['SignedByUserId']
        ];
    }
    sqlsrv_free_stmt($stmtTtd);
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDateGudang($value) {
    if ($value instanceof DateTime) return $value->format('d-m-Y');
    if (is_string($value) && $value !== '') return date('d-m-Y', strtotime($value));
    return '-';
}

function ttdImage($ttd, $role) {
    if (empty($ttd[$role]['SignaturePath'])) return '';
    return '<img src="' . e($ttd[$role]['SignaturePath']) . '" style="max-width:100%;object-fit:contain;height:60px;" alt="TTD ' . e($role) . '">';
}

function ttdLabel($ttd, $role, $fallback) {
    return !empty($ttd[$role]['SignedByUserName']) ? e($ttd[$role]['SignedByUserName']) : e($fallback);
}

function isTtdExists($ttdData) {
    return !empty($ttdData['SignaturePath']);
}

function isTtdByCurrentUser($ttdData, $userId) {
    return isTtdExists($ttdData) && isset($ttdData['SignedByUserId']) && $ttdData['SignedByUserId'] == $userId;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Penambahan Gudang Baru ERP - <?= e($ticket) ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<style>
body{background:#fff;font-size:15px;color:#000;}
table{border-collapse:collapse;width:100%;}
td{padding:5px 8px;vertical-align:top;}
.form-wrapper{max-width:900px;margin:10px auto;}
.outer-form{width:100%;border:1px solid #000;border-collapse:collapse;font-size:14px;line-height:1.4;}
.signature-table{text-align:center;table-layout:fixed;}
@media(max-width:768px){.form-wrapper{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;padding-bottom:10px;margin:5px auto!important}.outer-form{min-width:800px}img[alt="logo"]{width:50px!important}body{font-size:14px}}
</style>
<script>
window.addEventListener('load', function(){
    var canReject = <?= ($canSignIT || $canSignDireksi) && !$isRejected ? 'true' : 'false' ?>;
    var ticket = '<?= e($ticket) ?>';
    if (window.parent && window.parent.updateRejectButtonVisibility) {
        window.parent.updateRejectButtonVisibility(canReject, ticket);
    }
});
</script>
</head>
<body>
<?php include 'form_contents/components/rejection_status.php'; ?>

<div class="form-wrapper">
  <table class="outer-form">
    <tr>
      <td style="padding:0;">
        <table>
          <tr>
            <td style="width:90px;border-right:1px solid #000;padding:8px 6px;text-align:center;">
              <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
            </td>
            <td style="padding:6px 8px;">
              <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
              Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
              Banjaran - Kab. Bandung<br>
              40377 Telp. (022) 594-0313
            </td>
          </tr>
          <tr>
            <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;font-size:16px;">
              FORM PENAMBAHAN GUDANG BARU DI SYSTEM ERP
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0;">
        <table>
          <tr>
            <td style="width:170px;">No. Pengajuan</td>
            <td>: <?= e($data['ticket']) ?></td>
            <td style="width:140px;">Tgl Pengajuan</td>
            <td>: <?= fmtDateGudang($data['tgl_pengajuan']) ?></td>
          </tr>
          <tr>
            <td>Nama Pemohon</td>
            <td>: <?= e($data['nama_pemohon']) ?></td>
            <td>Status</td>
            <td>: <?= e($data['status_ticket']) ?></td>
          </tr>
          <tr>
            <td>Jabatan</td>
            <td>: <?= e($data['jabatan']) ?></td>
            <td>Departemen</td>
            <td>: <?= e($data['departemen']) ?></td>
          </tr>
          <tr>
            <td>Bagian</td>
            <td colspan="3">: <?= e($data['bagian']) ?></td>
          </tr>
          <tr><td colspan="4" style="height:12px;"></td></tr>
          <tr>
            <td>Nama Gudang Baru</td>
            <td colspan="3">: <?= e($data['nama_gudang_baru']) ?></td>
          </tr>
          <tr>
            <td style="vertical-align:top;">User Akses Gudang</td>
            <td colspan="3">: <?= nl2br(e($data['user_akses_gudang'])) ?></td>
          </tr>
          <tr>
            <td style="vertical-align:top;">Keterangan/Alasan</td>
            <td colspan="3">: <?= nl2br(e($data['keterangan'])) ?></td>
          </tr>
          <tr><td colspan="4" style="height:20px;"></td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:6px;">
        <table class="signature-table" border="1">
          <tr style="font-weight:bold;font-size:14px;">
            <td style="width:25%;padding:4px;">Diajukan oleh,</td>
            <td style="width:25%;padding:4px;">Diketahui oleh,</td>
            <td style="width:25%;padding:4px;">Mengetahui,</td>
            <td style="width:25%;padding:4px;">Disetujui oleh,</td>
          </tr>
          <tr style="height:120px;vertical-align:middle;">
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-pemohon"><?= ttdImage($ttd, 'Pemohon') ?></div>
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
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-atasan"><?= ttdImage($ttd, 'Atasan Pemohon') ?></div>
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
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-it"><?= ttdImage($ttd, 'Kabag IT') ?: ttdImage($ttd, 'Kadept IT') ?></div>
              <div id="ttd-btn-it" class="mt-2">
                <?php $itSigned = isTtdExists($ttd['Kabag IT'] ?? []) || isTtdExists($ttd['Kadept IT'] ?? []); ?>
                <?php $itSignedByMe = isTtdByCurrentUser($ttd['Kabag IT'] ?? [], $_SESSION['UserId'] ?? 0) || isTtdByCurrentUser($ttd['Kadept IT'] ?? [], $_SESSION['UserId'] ?? 0); ?>
                <?php if ($canSignIT && !$isRejected): ?>
                  <?php if ($itSignedByMe): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="<?= e($itRoleCode) ?>">Hapus Tanda Tangan</button>
                  <?php elseif ($itSigned): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="<?= e($itRoleCode) ?>">Tanda Tangan</button>
                  <?php endif; ?>
                <?php elseif ($itSigned): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>
                <?php endif; ?>
              </div>
            </td>
            <td style="padding:6px;vertical-align:middle;">
              <div id="ttd-area-direksi"><?= ttdImage($ttd, 'Direksi') ?></div>
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
          <tr style="font-weight:bold;font-size:14px;">
            <td><?= ttdLabel($ttd, 'Pemohon', 'Pemohon') ?></td>
            <td><?= ttdLabel($ttd, 'Atasan Pemohon', 'Atasan Pemohon') ?></td>
            <td><?php
                if (!empty($ttd['Kabag IT']['SignedByUserName'])) echo e($ttd['Kabag IT']['SignedByUserName']);
                elseif (!empty($ttd['Kadept IT']['SignedByUserName'])) echo e($ttd['Kadept IT']['SignedByUserName']);
                else echo 'Kabag/Kadept IT';
            ?></td>
            <td><?= ttdLabel($ttd, 'Direksi', 'Direksi') ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<div class="modal fade" id="form-it-gudang-erp-modal-ttd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:var(--primary, #3c8dbc);">
        <h5 class="modal-title">Buat Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="form-it-gudang-erp-form-ttd" enctype="multipart/form-data">
          <input type="hidden" name="ticket" value="<?= e($ticket) ?>">
          <input type="hidden" name="role_code" id="form-it-gudang-erp-role-code" value="">
          <input type="hidden" name="canvas_data" id="form-it-gudang-erp-canvas-data" value="">
          <input type="hidden" name="save_template" value="1">
          <div style="border:1px solid #ccc;width:100%;">
            <canvas id="form-it-gudang-erp-canvas" width="600" height="200" style="background:#fff;cursor:crosshair;width:100%;height:150px;"></canvas>
          </div>
        </form>
      </div>
      <div class="modal-footer justify-content-start">
        <button type="button" class="btn btn-light btn-sm" id="form-it-gudang-erp-clear-canvas">Bersihkan</button>
        <button type="button" class="btn btn-success btn-sm" id="form-it-gudang-erp-save-ttd">Simpan TTD</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="form-it-gudang-erp-modal-delete-ttd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
        <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="form-it-gudang-erp-confirm-delete-ttd">Ya, Hapus Tanda Tangan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="form-it-gudang-erp-modal-delete-success" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Berhasil</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"><p id="form-it-gudang-erp-delete-message">Tanda tangan berhasil dihapus.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-success" data-dismiss="modal">OK</button></div>
    </div>
  </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
    var currentRoleCode = null;
    var ticket = '<?= e($ticket) ?>';
    var isRejected = <?= $isRejected ? 'true' : 'false' ?>;
    var roleToDelete = null;
    var canvas = document.getElementById('form-it-gudang-erp-canvas');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var drawing = false;

    if (canvas && ctx) {
        function getPos(e) {
            var r = canvas.getBoundingClientRect();
            var source = e.touches ? e.touches[0] : e;
            return {
                x: (source.clientX - r.left) * (canvas.width / r.width),
                y: (source.clientY - r.top) * (canvas.height / r.height)
            };
        }
        function startDraw(e) { if (e.touches) e.preventDefault(); drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function draw(e) { if (!drawing) return; if (e.touches) e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
        function endDraw() { drawing = false; }
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', startDraw, {passive:false});
        canvas.addEventListener('touchmove', draw, {passive:false});
        canvas.addEventListener('touchend', endDraw);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
    }

    $('#form-it-gudang-erp-clear-canvas').on('click', function(){ if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); });
    $('#form-it-gudang-erp-modal-ttd').on('shown.bs.modal', function(){ if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); });

    $(document).on('click', '.btn-ttd', function(){
        var roleCode = $(this).data('role');
        if (isRejected) {
            Swal.fire('Ditolak', 'Pengajuan ini telah ditolak dan tidak dapat ditandatangani.', 'error');
            return;
        }
        currentRoleCode = roleCode;
        $.post('ttd_sign.php', { ticket: ticket, role_code: roleCode }, function(resp){
            if (resp && resp.success && resp.signature_url) {
                updateTtdArea(roleCode, resp.signature_url, resp.signed_by);
            } else if (resp && resp.need_template) {
                $('#form-it-gudang-erp-role-code').val(roleCode);
                $('#form-it-gudang-erp-modal-ttd').modal('show');
            } else {
                Swal.fire('Gagal', (resp && resp.message) || 'Gagal memproses tanda tangan.', 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-delete-ttd', function(){
        roleToDelete = $(this).data('role');
        $('#form-it-gudang-erp-modal-delete-ttd').modal('show');
    });

    $('#form-it-gudang-erp-confirm-delete-ttd').on('click', function(){
        if (!roleToDelete) return;
        $.post('delete_ttd.php', { ticket: ticket, role_code: roleToDelete }, function(resp){
            $('#form-it-gudang-erp-modal-delete-ttd').modal('hide');
            if (resp && resp.success) {
                removeTtdArea(roleToDelete);
                $('#form-it-gudang-erp-delete-message').text(resp.message || 'Tanda tangan berhasil dihapus.');
                $('#form-it-gudang-erp-modal-delete-success').modal('show');
            } else {
                Swal.fire('Gagal', (resp && resp.message) || 'Gagal menghapus tanda tangan.', 'error');
            }
        }, 'json');
    });

    $('#form-it-gudang-erp-save-ttd').on('click', function(){
        var roleCode = $('#form-it-gudang-erp-role-code').val() || currentRoleCode;
        if (!roleCode) { Swal.fire('Error', 'Role tidak dikenal.', 'error'); return; }
        if (canvas && isCanvasBlank()) {
            Swal.fire('Peringatan', 'Silakan buat tanda tangan terlebih dahulu.', 'warning');
            return;
        }
        if (canvas) $('#form-it-gudang-erp-canvas-data').val(canvas.toDataURL('image/png'));
        var fd = new FormData(document.getElementById('form-it-gudang-erp-form-ttd'));
        $.ajax({
            url: 'ttd_save_template.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(resp) {
                try { resp = (typeof resp === 'string') ? JSON.parse(resp) : resp; } catch(e) {}
                if (resp && resp.success && resp.signature_url) {
                    $('#form-it-gudang-erp-modal-ttd').modal('hide');
                    updateTtdArea(roleCode, resp.signature_url, resp.signed_by);
                } else {
                    Swal.fire('Gagal', (resp && resp.message) || 'Gagal menyimpan tanda tangan.', 'error');
                }
            }
        });
    });

    function roleTargets(roleCode) {
        if (roleCode === 'pemohon') return ['#ttd-area-pemohon', '#ttd-btn-pemohon', '#ttd-label-pemohon', 'Pemohon'];
        if (roleCode === 'atasan_pemohon') return ['#ttd-area-atasan', '#ttd-btn-atasan', '#ttd-label-atasan', 'Atasan Pemohon'];
        if (roleCode === 'kabag_it' || roleCode === 'kadept_it') return ['#ttd-area-it', '#ttd-btn-it', '#ttd-label-it', 'Kabag/Kadept IT'];
        if (roleCode === 'direksi') return ['#ttd-area-direksi', '#ttd-btn-direksi', '#ttd-label-direksi', 'Direksi'];
        return ['', '', '', ''];
    }

    function updateTtdArea(roleCode, imgUrl, name) {
        var t = roleTargets(roleCode);
        if (t[0]) $(t[0]).html('<img src="' + imgUrl + '" style="max-width:100%;object-fit:contain;height:60px;" alt="TTD">');
        if (t[2] && name) $(t[2]).text(name);
        if (t[1]) $(t[1]).html($('<button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd">Hapus Tanda Tangan</button>').attr('data-role', roleCode));
    }

    function removeTtdArea(roleCode) {
        var t = roleTargets(roleCode);
        if (t[0]) $(t[0]).empty();
        if (t[2]) $(t[2]).text(t[3]);
        if (isRejected) {
            if (t[1]) $(t[1]).empty();
            return;
        }
        if (t[1]) $(t[1]).html($('<button type="button" class="btn btn-sm btn-outline-primary btn-ttd">Tanda Tangan</button>').attr('data-role', roleCode));
    }

    function isCanvasBlank() {
        if (!canvas || !ctx) return true;
        try {
            var pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            for (var i = 3; i < pixels.length; i += 4) {
                if (pixels[i] !== 0) return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }
});
</script>
</body>
</html>
