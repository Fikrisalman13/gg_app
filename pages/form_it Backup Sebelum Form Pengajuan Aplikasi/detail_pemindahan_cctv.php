<?php
session_start();
require_once '../../koneksi.php';

$ticket = $_GET['ticket'] ?? '';
if ($ticket === '') {
    die('Ticket tidak ditemukan');
}

if (stripos($ticket, 'CCTV-P-') !== 0) {
    header('Location: detail_cctv.php?ticket=' . urlencode($ticket));
    exit;
}

if (!isset($conn) || $conn === false) {
    die('Koneksi database gagal');
}

$stmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM dbo.Form_Pemindahan_CCTV WHERE ticket = ?", [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die('Data Pemindahan CCTV tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$roles = [];
if (isset($_SESSION['UserId'])) {
    $stmtRole = sqlsrv_query($conn, "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND IsActive = 1", [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowRole = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $roles[] = trim((string)($rowRole['GroupRole'] ?? ''));
        }
        sqlsrv_free_stmt($stmtRole);
    }
}

$canSignPemohon = in_array('Pemohon', $roles, true);
$canSignKabagIT = in_array('Kabag IT', $roles, true);
$canSignPetugasCCTV = in_array('Petugas CCTV', $roles, true);


$ttd = [];
$stmtTtd = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ? AND GroupRole IN ('Pemohon', 'Kabag IT', 'Petugas CCTV')", [$ticket]);
if ($stmtTtd) {
    while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $ttd[trim((string)$rowTtd['GroupRole'])] = $rowTtd;
    }
    sqlsrv_free_stmt($stmtTtd);
}

$hasPemohon = !empty($ttd['Pemohon']['SignaturePath'] ?? '');
$hasKabag = !empty($ttd['Kabag IT']['SignaturePath'] ?? '');
$hasPetugas = !empty($ttd['Petugas CCTV']['SignaturePath'] ?? '');

$canSignPemohonNow = $canSignPemohon && !$hasPemohon;
$canSignKabagNow = $canSignKabagIT && $hasPemohon && !$hasKabag;
$canSignPetugasNow = $canSignPetugasCCTV && $hasPemohon;

$isRejected = strtolower(trim((string)($data['status_ticket'] ?? ''))) === 'ditolak';
$signatureCount = 0;
foreach (['Pemohon', 'Kabag IT', 'Petugas CCTV'] as $roleName) {
    if (!empty($ttd[$roleName]['SignaturePath'] ?? '')) {
        $signatureCount++;
    }
}
$hasAllAttachments = !empty(trim((string)($data['lampiran_sebelum'] ?? ''))) && !empty(trim((string)($data['lampiran_setelah'] ?? '')));
$isCompleted = ($signatureCount >= 3 && $hasAllAttachments);
if ($isCompleted && strtolower(trim((string)($data['status_ticket'] ?? ''))) !== 'selesai') {
    $updatedBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'System';
    sqlsrv_query($conn, "UPDATE dbo.Form_Pemindahan_CCTV SET status_ticket = 'Selesai', updated_at = GETDATE(), updated_by = ? WHERE ticket = ?", [$updatedBy, $ticket]);
    $data['status_ticket'] = 'Selesai';
}
$canEditPetugas = $canSignPetugasCCTV && !$isRejected && !$isCompleted;
$today = date('Y-m-d');

function calcShortestDeliveryDateValue($tanggalPengerjaan, $manDays)
{
    $tanggalPengerjaan = trim((string)($tanggalPengerjaan ?? ''));
    $manDays = (int) trim((string)($manDays ?? ''));

    if ($tanggalPengerjaan === '' || $manDays <= 0) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $tanggalPengerjaan);
    if (!$date) {
        return '';
    }

    $offsetDays = max(0, $manDays - 1);
    if ($offsetDays > 0) {
        $date->modify('+' . $offsetDays . ' day');
    }

    return $date->format('Y-m-d');
}

$defaultTanggalPengerjaan = '';
if (!empty($data['tanggal_pengerjaan'])) {
    $defaultTanggalPengerjaan = $data['tanggal_pengerjaan'] instanceof DateTime ? $data['tanggal_pengerjaan']->format('Y-m-d') : (string)$data['tanggal_pengerjaan'];
} else {
    $defaultTanggalPengerjaan = $today;
}
$defaultManDays = trim((string)($data['man_days'] ?? ''));
$defaultShortestDeliveryDate = '';
if (!empty($data['shortest_delivery_date'])) {
    $defaultShortestDeliveryDate = $data['shortest_delivery_date'] instanceof DateTime ? $data['shortest_delivery_date']->format('Y-m-d') : (string)$data['shortest_delivery_date'];
} else {
    $defaultShortestDeliveryDate = calcShortestDeliveryDateValue($defaultTanggalPengerjaan, $defaultManDays);
}
$defaultAssignedTo = 'Tim IT';

function fmtDateVal($value)
{
    if ($value instanceof DateTime) {
        return $value->format('d-m-Y');
    }
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $parts = explode('-', $value);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return '-';
}

function textVal($value)
{
    $value = trim((string)($value ?? ''));
    return $value === '' ? '-' : htmlspecialchars($value);
}

function sigImg($row)
{
    if (empty($row['SignaturePath'] ?? '')) {
        return '<span class="text-muted">Belum ada TTD</span>';
    }
    return '<img src="' . htmlspecialchars($row['SignaturePath']) . '" style="max-height:70px;max-width:220px;">';
}

function attachmentPublicPath($path)
{
    $path = trim((string)($path ?? ''));
    if ($path === '') {
        return '';
    }
    if (preg_match('~^(?:https?:)?//~i', $path) || stripos($path, 'data:') === 0) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (isset($path[0]) && $path[0] === '/') {
        return $path;
    }
    return '/gg_app/' . ltrim($path, '/');
}

function attachmentPreview($path, $label)
{
    $path = trim((string)($path ?? ''));
    if ($path === '') {
        return '<div class="text-muted">-</div>';
    }
    $publicPath = attachmentPublicPath($path);
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
    $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $fileEsc = htmlspecialchars($publicPath, ENT_QUOTES, 'UTF-8');
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return '<a href="' . $fileEsc . '" target="_blank" class="d-block"><img src="' . $fileEsc . '" alt="' . $labelEsc . '" style="width:100%;max-height:220px;object-fit:contain;border:1px solid #dcdcdc;border-radius:4px;padding:4px;background:#fff;"></a>';
    }
    return '<a href="' . $fileEsc . '" target="_blank">Lihat file</a>';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pengajuan CCTV - <?= htmlspecialchars($ticket) ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        body{background:#fff;color:#000;font-size:14px;}
        .wrap{padding:16px;}
        .form-wrapper{max-width:980px;margin:10px auto;}
        .outer-form{width:100%;border:1px solid #000;border-collapse:collapse;}
        .outer-form td{vertical-align:top;}
        .company-name{font-size:16px;font-weight:700;}
        .form-name{padding:6px 4px;text-align:center;font-weight:700;border-top:1px solid #000;border-bottom:1px solid #000;}
        .section-title{margin:14px 0 8px;padding:6px 10px;background:#eef5ff;border:1px solid #000;font-weight:700;}
        .form-table td{border:1px solid #000;padding:6px 8px;vertical-align:top;}
        .label-col{width:235px;font-weight:600;}
        .attachment-grid{display:flex;gap:12px;flex-wrap:wrap;}
        .attachment-card{flex:1 1 300px;border:1px solid #000;padding:10px;}
        .attachment-title{font-weight:700;margin-bottom:8px;}
        .sig-box{min-height:140px;}
        .muted-note{color:#6c757d;font-size:12px;}
        @media(max-width:768px){.wrap{padding:10px;}.attachment-card{flex:1 1 100%;}}
    </style>
</head>
<body>
<div class="wrap">
  <div class="form-wrapper">
    <table class="outer-form" style="border-bottom:none;">
      <tr>
        <td style="width:90px;border-right:1px solid #000;padding:8px 6px;text-align:center;">
          <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
        </td>
        <td style="padding:6px 8px;line-height:1.35;">
          <div class="company-name">PT. SURYA USAHA MANDIRI</div>
          Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
          Banjaran &ndash; Kab. Bandung<br>
          40377 Telp. (022) 594-0313
        </td>
      </tr>
      <tr><td colspan="2" class="form-name">PEMINDAHAN KAMERA CCTV</td></tr>
    </table>

    <div class="section-title">Detail Pengajuan : <?= htmlspecialchars($ticket) ?></div>
    <table class="form-table" style="width:100%;border-collapse:collapse;">
      <tr><td class="label-col">Ticket</td><td><?= htmlspecialchars($data['ticket']) ?></td></tr>
      <tr><td class="label-col">Nama Pemohon</td><td><?= textVal($data['nama_pemohon']) ?></td></tr>
      <tr><td class="label-col">Jabatan</td><td><?= textVal($data['jabatan']) ?></td></tr>
      <tr><td class="label-col">Departemen</td><td><?= textVal($data['departemen']) ?></td></tr>
      <tr><td class="label-col">Area</td><td><?= textVal($data['area']) ?></td></tr>
      <tr><td class="label-col">Tanggal Pengajuan</td><td><?= fmtDateVal($data['tgl_pengajuan']) ?></td></tr>
      <tr><td class="label-col">Deskripsi Permintaan</td><td><?= nl2br(textVal($data['deskripsi_user'])) ?></td></tr>
    </table>


    <div class="section-title">Detail Pengerjaan</div>
    <table class="form-table" style="width:100%;border-collapse:collapse;">
      <tr>
        <td style="width:33%;"><label class="mb-1 font-weight-bold">Tanggal Pengerjaan</label><div class="form-control form-control-sm bg-light" style="min-height:auto;"><?= fmtDateVal($data['tanggal_pengerjaan'] ?? '') ?></div></td>
        <td style="width:33%;"><label class="mb-1 font-weight-bold">Man-days</label><div class="form-control form-control-sm bg-light" style="min-height:auto;"><?= textVal($data['man_days'] ?? '') ?></div></td>
        <td style="width:33%;"><label class="mb-1 font-weight-bold">Shortest Delivery Date</label><div class="form-control form-control-sm bg-light" style="min-height:auto;"><?= fmtDateVal($data['shortest_delivery_date'] ?? '') ?></div></td>
      </tr>
      <tr>
        <td><label class="mb-1 font-weight-bold">Deskripsi Area</label><div class="form-control form-control-sm bg-light" style="min-height:38px;white-space:pre-wrap;"><?= nl2br(textVal($data['deskripsi_area'] ?? '')) ?></div></td>
        <td><label class="mb-1 font-weight-bold">Assigned to</label><div class="form-control form-control-sm bg-light" style="min-height:auto;"><?= textVal($data['assigned_to'] ?? 'Tim IT') ?></div></td>
        <td><label class="mb-1 font-weight-bold">Tanggal Diterima</label><div class="form-control form-control-sm bg-light" style="min-height:auto;"><?= fmtDateVal($data['tgl_pengajuan']) ?></div></td>
      </tr>
      <tr>
        <td colspan="3"><label class="mb-1 font-weight-bold">Opsi Deskripsi Solusi</label><div class="form-control form-control-sm bg-light" style="min-height:60px;white-space:pre-wrap;"><?= nl2br(textVal($data['Opsi_deskripsi_solusi'] ?? '')) ?></div></td>
      </tr>
    </table>

    <div class="section-title">Dokumen 1 - Diisi oleh Staf IT</div>
    <?php if ($canEditPetugas): ?>
    <form id="formPemindahanCCTVDetail" enctype="multipart/form-data" method="post">
      <input type="hidden" name="form_type" value="pemindahan_kamera_cctv">
      <input type="hidden" name="ticket" value="<?= htmlspecialchars($data['ticket']) ?>">
      <input type="hidden" name="nama_pemohon" value="<?= htmlspecialchars($data['nama_pemohon']) ?>">
      <input type="hidden" name="jabatan" value="<?= htmlspecialchars($data['jabatan']) ?>">
      <input type="hidden" name="departemen" value="<?= htmlspecialchars($data['departemen']) ?>">
      <input type="hidden" name="area" value="<?= htmlspecialchars($data['area']) ?>">
      <input type="hidden" name="tgl_pengajuan" value="<?= fmtDateVal($data['tgl_pengajuan']) ?>">
      <input type="hidden" name="deskripsi_user" value="<?= htmlspecialchars($data['deskripsi_user']) ?>">
      <table class="form-table" style="width:100%;border-collapse:collapse;">
        <tr>
          <td style="width:33%;"><label class="mb-1 font-weight-bold">Tanggal Pengerjaan</label><input type="date" id="tanggalPengerjaan" name="tanggal_pengerjaan" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultTanggalPengerjaan) ?>" required></td>
          <td style="width:33%;"><label class="mb-1 font-weight-bold">Man-days</label><input type="text" id="manDays" name="man_days" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultManDays) ?>" placeholder="0"></td>
          <td style="width:33%;"><label class="mb-1 font-weight-bold">Shortest Delivery Date</label><input type="date" id="shortestDeliveryDate" name="shortest_delivery_date" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultShortestDeliveryDate) ?>" readonly></td>
        </tr>
        <tr>
          <td><label class="mb-1 font-weight-bold">Deskripsi Area</label><textarea name="deskripsi_area" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($data['deskripsi_area'] ?? '') ?></textarea></td>
          <td><label class="mb-1 font-weight-bold">Assigned to</label><input type="text" id="assignedTo" name="assigned_to" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultAssignedTo) ?>" readonly required></td>
          <td><label class="mb-1 font-weight-bold">Tanggal Diterima</label><input type="text" class="form-control form-control-sm" value="<?= fmtDateVal($data['tgl_pengajuan']) ?>" readonly></td>
        </tr>
        <tr>
          <td colspan="3"><label class="mb-1 font-weight-bold">Deskripsi Solusi</label><textarea name="Opsi_deskripsi_solusi" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($data['Opsi_deskripsi_solusi'] ?? '') ?></textarea></td>
        </tr>
        <tr>
          <td><label class="mb-1 font-weight-bold">Lampiran Sebelum</label><input type="file" name="lampiran_sebelum" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png"><?php if (!empty($data['lampiran_sebelum'])): ?><div class="mt-1 small">Lama: <a href="<?= htmlspecialchars($data['lampiran_sebelum']) ?>" target="_blank">Lihat file lama</a></div><?php endif; ?></td>
          <td><label class="mb-1 font-weight-bold">Lampiran Setelah</label><input type="file" name="lampiran_setelah" class="form-control form-control-sm" accept="image/*,.pdf,.jpg,.jpeg,.png"><?php if (!empty($data['lampiran_setelah'])): ?><div class="mt-1 small">Lama: <a href="<?= htmlspecialchars($data['lampiran_setelah']) ?>" target="_blank">Lihat file lama</a></div><?php endif; ?></td>
          <td><label class="mb-1 font-weight-bold">Status</label><input type="text" class="form-control form-control-sm" value="<?= textVal($data['status_ticket']) ?>" readonly></td>
        </tr>
      </table>
      <div class="text-right mt-3 mb-2"><button type="button" class="btn btn-primary" id="btnSavePemindahanCCTV"><i class="fas fa-save"></i> Simpan</button></div>
    </form>
    <?php endif; ?>
    <div class="section-title">Lampiran Pengajuan</div>
    <div class="attachment-grid">
      <div class="attachment-card">
        <div class="attachment-title">Lampiran Sebelum</div>
        <?= attachmentPreview($data['lampiran_sebelum'] ?? '', 'Lampiran Sebelum') ?>
      </div>
      <div class="attachment-card">
        <div class="attachment-title">Lampiran Setelah</div>
        <?= attachmentPreview($data['lampiran_setelah'] ?? '', 'Lampiran Setelah') ?>
      </div>
    </div>

    <div class="section-title">Tanda Tangan</div>
    <table class="table table-bordered table-sm">
      <tr class="text-center font-weight-bold"><td>Pemohon</td><td>Kabag IT</td><td>Petugas CCTV</td></tr>
      <tr class="text-center">
        <td class="sig-box"><div id="ttd-area-pemohon"><?= sigImg($ttd['Pemohon'] ?? []) ?></div><div class="mt-2" id="ttd-label-pemohon"><?= textVal($ttd['Pemohon']['SignedByUserName'] ?? 'Pemohon') ?></div><?php if ($canSignPemohon && !$isRejected): ?><?php if (!$hasPemohon): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda Tangan</button><?php else: ?><button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button><?php endif; ?><?php endif; ?></td>
        <td class="sig-box"><div id="ttd-area-kabag-it"><?= sigImg($ttd['Kabag IT'] ?? []) ?></div><div class="mt-2" id="ttd-label-kabag-it"><?= textVal($ttd['Kabag IT']['SignedByUserName'] ?? 'Kabag IT') ?></div><?php if ($canSignKabagIT && !$isRejected): ?><?php if (!$hasPemohon): ?><button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Menunggu Pemohon</button><?php elseif (!$hasKabag): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kabag_it">Tanda Tangan</button><?php else: ?><button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button><?php endif; ?><?php endif; ?></td>
        <td class="sig-box"><div id="ttd-area-petugas-cctv"><?= sigImg($ttd['Petugas CCTV'] ?? []) ?></div><div class="mt-2" id="ttd-label-petugas-cctv"><?= textVal($ttd['Petugas CCTV']['SignedByUserName'] ?? 'Petugas CCTV') ?></div><?php if ($canSignPetugasCCTV && !$isRejected): ?><?php if (!$hasPemohon): ?><button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Menunggu Pemohon</button><?php else: ?><button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="petugas_cctv"><?= $hasPetugas ? 'Perbarui Tanda Tangan' : 'Tanda Tangan' ?></button><?php endif; ?><?php endif; ?></td>
      </tr>
      <tr class="text-center"><td class="muted-note"><?= $hasPemohon ? 'TTD tersimpan' : 'Belum ada TTD' ?></td><td class="muted-note"><?= $hasKabag ? 'TTD tersimpan' : 'Belum ada TTD' ?></td><td class="muted-note"><?= $hasPetugas ? 'TTD tersimpan' : 'Belum ada TTD' ?></td></tr>
    </table>
  </div>
</div><div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Buat / Upload Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
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

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    function formatDateInput(dateObj){
        var year = dateObj.getFullYear();
        var month = String(dateObj.getMonth() + 1).padStart(2, '0');
        var day = String(dateObj.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function syncShortestDeliveryDate(forceClear){
        var dateValue = $('#tanggalPengerjaan').val();
        var manDaysValue = parseInt($('#manDays').val(), 10);
        if (!dateValue || isNaN(manDaysValue) || manDaysValue <= 0) {
            if (forceClear) {
                $('#shortestDeliveryDate').val('');
            }
            return;
        }

        var parts = dateValue.split('-');
        if (parts.length !== 3) {
            if (forceClear) {
                $('#shortestDeliveryDate').val('');
            }
            return;
        }

        var dateObj = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(dateObj.getTime())) {
            if (forceClear) {
                $('#shortestDeliveryDate').val('');
            }
            return;
        }

        dateObj.setDate(dateObj.getDate() + manDaysValue - 1);
        $('#shortestDeliveryDate').val(formatDateInput(dateObj));
    }

    $('#assignedTo').val('Tim IT');
    $('#tanggalPengerjaan, #manDays').on('input change', function(){
        syncShortestDeliveryDate(true);
    });
    syncShortestDeliveryDate(false);
    var currentRoleCode = null;
    var canvas = document.getElementById('ttdCanvas');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var isRejected = <?= $isRejected ? 'true' : 'false' ?>;

    function resetCanvas(){
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function getPos(e){
        var rect = canvas.getBoundingClientRect();
        var x, y;
        if (e.touches && e.touches.length) {
            x = e.touches[0].clientX - rect.left;
            y = e.touches[0].clientY - rect.top;
        } else {
            x = e.clientX - rect.left;
            y = e.clientY - rect.top;
        }
        return { x: x, y: y };
    }

    function startDraw(e){
        drawing = true;
        var p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function draw(e){
        if (!drawing) return;
        e.preventDefault();
        var p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }

    function endDraw(){
        drawing = false;
    }

    function signRole(roleCode) {
        $.post('ttd_sign.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleCode }, function(resp){
            try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { resp = {}; }
            if (resp && resp.success && resp.signature_url) {
                location.reload();
            } else if (resp && resp.need_template) {
                $('#ttdRoleCode').val(roleCode);
                $('#modalTtd').modal('show');
            } else {
                alert((resp && resp.message) ? resp.message : 'Gagal memproses tanda tangan.');
            }
        });
    }

    function savePetugasCctvThenSign(roleCode) {
        var form = document.getElementById('formPemindahanCCTVDetail');
        if (!form || !form.reportValidity()) {
            return;
        }
        var fd = new FormData(form);
        $.ajax({
            url: 'proses_simpan.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(resp){
                if (resp && resp.success) {
                    signRole(roleCode);
                } else {
                    alert((resp && resp.message) ? resp.message : 'Gagal menyimpan data sebelum tanda tangan.');
                }
            },
            error: function(){
                alert('Error saat menyimpan data sebelum tanda tangan.');
            }
        });
    }

    $('.btn-ttd').on('click', function(){
        if (isRejected) return;
        currentRoleCode = $(this).data('role');
        if (currentRoleCode === 'petugas_cctv') {
            savePetugasCctvThenSign(currentRoleCode);
            return;
        }
        signRole(currentRoleCode);
    });

    $('#modalTtd').on('shown.bs.modal', function(){
        resetCanvas();
        $('#ttdFile').val('');
    });

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', function(e){ startDraw(e); }, { passive:false });
    canvas.addEventListener('touchmove', function(e){ draw(e); }, { passive:false });
    canvas.addEventListener('touchend', function(e){ endDraw(e); }, { passive:false });

    $('#btnClearCanvas').on('click', function(){ resetCanvas(); });

    $('#btnSimpanTtd').on('click', function(){
        var roleCode = $('#ttdRoleCode').val() || currentRoleCode;
        if (!roleCode) {
            alert('Role tanda tangan tidak dikenal.');
            return;
        }
        $('#canvasData').val(canvas.toDataURL('image/png'));
        var fd = new FormData(document.getElementById('formTtd'));
        $.ajax({
            url: 'ttd_save_template.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(resp){
                try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch (e) { resp = {}; }
                if (resp && resp.success && resp.signature_url) {
                    $('#modalTtd').modal('hide');
                    location.reload();
                } else {
                    alert((resp && resp.message) ? resp.message : 'Gagal menyimpan tanda tangan.');
                }
            },
            error: function(){ alert('Error saat menyimpan tanda tangan.'); }
        });
    });

    $('#btnSavePemindahanCCTV').on('click', function(){
        var form = document.getElementById('formPemindahanCCTVDetail');
        if (!form.reportValidity()) {
            return;
        }
        var fd = new FormData(form);
        $.ajax({
            url: 'proses_simpan.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function(){
                $('#btnSavePemindahanCCTV').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
            },
            success: function(resp){
                if (resp && resp.success) {
                    alert(resp.message || 'Data berhasil disimpan.');
                    location.reload();
                } else {
                    alert((resp && resp.message) ? resp.message : 'Gagal menyimpan data.');
                }
            },
            error: function(xhr){
                var msg = 'Terjadi kesalahan saat menyimpan.';
                try {
                    var err = JSON.parse(xhr.responseText);
                    if (err && err.message) msg = err.message;
                } catch (e) {}
                alert(msg);
            },
            complete: function(){
                $('#btnSavePemindahanCCTV').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            }
        });
    });
});
</script>
</body>
</html>

