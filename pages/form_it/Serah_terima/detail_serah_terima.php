<?php
session_start();
require_once '../../../koneksi.php';

$ticket = $_GET['ticket'] ?? '';
if ($ticket === '') {
    die('Ticket tidak ditemukan');
}

$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.Form_Serah_Terima_Aplikasi WHERE ticket = ?", [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId
           FROM dbo.Form_Pengajuan_Barang_TTD
           WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd) {
    while ($row = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $ttd[$row['GroupRole']] = $row;
    }
}

$roles = [];
$canSignPemohon = false;
$canSignAtasan = false;
$canSignKabag = false;
if (isset($_SESSION['UserId'])) {
    $stmtRole = sqlsrv_query($conn, "SELECT GroupRole FROM dbo.User_TTD_Template WHERE UserId = ? AND IsActive = 1", [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($row = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($row['GroupRole'] ?? '');
            $roles[] = $role;
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            if (strcasecmp($role, 'Atasan Pemohon') === 0) $canSignAtasan = true;
            if (strcasecmp($role, 'Kabag IT') === 0 || strcasecmp($role, 'Kadept IT') === 0 || strcasecmp($role, 'Direksi') === 0) $canSignKabag = true;
        }
    }
}

function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function fmtDate($value)
{
    if ($value instanceof DateTime) return $value->format('d-m-Y');
    return $value ? date('d-m-Y', strtotime($value)) : '-';
}
function checkedBox($checked) { return $checked ? '&#9745;' : '&#9744;'; }
function signatureCell($ttd, $role, $roleCode, $canSign, $defaultLabel)
{
    $row = $ttd[$role] ?? null;
    $hasTtd = $row && trim((string) ($row['SignaturePath'] ?? '')) !== '';
    $signedByCurrentUser = $hasTtd && isset($_SESSION['UserId']) && (int) ($row['SignedByUserId'] ?? 0) === (int) $_SESSION['UserId'];
    $areaId = str_replace('_', '-', $roleCode);
    $html = '<td style="padding:8px;text-align:center;vertical-align:middle;">';
    $html .= '<div id="ttd-area-' . h($areaId) . '" style="height:70px;">';
    if ($hasTtd) {
        $html .= '<img src="' . h($row['SignaturePath']) . '" style="max-width:100%;height:60px;object-fit:contain;" alt="TTD">';
    }
    $html .= '</div>';
    $html .= '<div id="ttd-btn-' . h($areaId) . '" class="mt-1">';
    if ($canSign) {
        if ($signedByCurrentUser) {
            $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-ttd" data-role="' . h($roleCode) . '">Hapus Tanda Tangan</button>';
        } elseif ($hasTtd) {
            $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>';
        } else {
            $html .= '<button type="button" class="btn btn-sm btn-outline-primary btn-ttd" data-role="' . h($roleCode) . '">Tanda Tangan</button>';
        }
    } elseif ($hasTtd) {
        $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sudah Ditandatangani</button>';
    }
    $html .= '</div>';
    $html .= '<div id="ttd-label-' . h($areaId) . '" style="margin-top:8px;font-weight:bold;">' . h($hasTtd ? ($row['SignedByUserName'] ?? $defaultLabel) : $defaultLabel) . '</div>';
    $html .= '</td>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Serah Terima - <?= h($ticket) ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<style>
body { background:#fff; color:#000; font-size:14px; }
table { border-collapse: collapse; width: 100%; }
.sheet { max-width: 920px; margin: 12px auto; border: 1px solid #000; }
.line { border-bottom: 1px dotted #333; min-height: 22px; display: inline-block; min-width: 360px; }
.section-title { font-weight: bold; margin: 10px 0 6px; }
.option-line { margin: 4px 0; }
@media (max-width: 768px) { .sheet { min-width: 860px; } .wrap { overflow-x:auto; } }
</style>
</head>
<body>
<div class="wrap">
<div class="sheet">
    <table>
        <tr>
            <td style="width:95px;border-right:1px solid #000;padding:8px;text-align:center;vertical-align:top;">
                <img src="/gg_app/dist/img/sumlogo.png" width="72" alt="logo">
            </td>
            <td style="padding:8px;line-height:1.35;">
                <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
                Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                Banjaran - Kab. Bandung<br>
                40377 Telp. (022) 594-0313
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top:1px solid #000;text-align:center;font-weight:bold;padding:8px;font-size:16px;">
                SERAH TERIMA HASIL PEMBUATAN/PENGEMBANGAN APLIKASI
            </td>
        </tr>
    </table>

    <div style="padding:14px 18px;">
        <div style="text-align:right;margin-bottom:12px;"><?= fmtDate($data['tanggal_serah_terima']) ?></div>

        <div class="section-title">1. Informasi Umum</div>
        <table>
            <tr><td style="width:210px;">Nama Aplikasi</td><td>: <?= h($data['nama_aplikasi']) ?></td></tr>
            <tr><td>Nama Modul</td><td>: <?= nl2br(h($data['nama_modul'])) ?></td></tr>
            <tr><td>Tanggal Selesai</td><td>: <?= fmtDate($data['tanggal_selesai']) ?></td></tr>
            <tr><td>Diminta Oleh (User dan Departemen)</td><td>: <?= h($data['diminta_oleh']) ?></td></tr>
        </table>

        <div class="section-title">2. Deskripsi Aplikasi</div>
        <div style="white-space:pre-line;line-height:1.45;"><?= h($data['deskripsi_aplikasi']) ?></div>

        <div class="section-title">3. Hasil Pengujian</div>
        <div style="margin-left:18px;">
            <b>a. Status Testing :</b>
            <div class="option-line"><?= checkedBox((int)$data['status_testing_it'] === 1) ?> Sudah diuji oleh IT</div>
            <div class="option-line"><?= checkedBox((int)$data['status_testing_user'] === 1) ?> Sudah diuji oleh User</div>

            <b>b. Hasil Testing :</b>
            <div class="option-line"><?= checkedBox($data['hasil_testing'] === 'sesuai') ?> Sesuai dengan permintaan</div>
            <div class="option-line"><?= checkedBox($data['hasil_testing'] === 'revisi') ?> Perlu revisi (jelaskan) :</div>
            <div style="min-height:46px;border-bottom:1px dotted #777;white-space:pre-line;"><?= h($data['catatan_revisi'] ?? '') ?></div>
        </div>

        <p style="margin-top:16px;">Dengan ini pihak IT menyerahkan aplikasi yang telah dibuat kepada pihak user, dan user menyatakan bahwa:</p>
        <table>
            <tr>
                <td style="width:55%;vertical-align:top;">
                    <div><?= checkedBox($data['status_penerimaan'] === 'sesuai') ?> Aplikasi telah sesuai dengan kebutuhan</div>
                    <div><?= checkedBox($data['status_penerimaan'] === 'catatan') ?> Aplikasi diterima dengan catatan (terlampir)</div>
                    <div><?= checkedBox($data['status_penerimaan'] === 'perbaikan') ?> Aplikasi masih membutuhkan perbaikan</div>
                    <?php if (!empty($data['catatan_penerimaan'])): ?>
                        <div style="margin-top:8px;white-space:pre-line;"><b>Catatan:</b><br><?= h($data['catatan_penerimaan']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="width:45%;vertical-align:top;">
                    <table border="1" style="table-layout:fixed;text-align:center;">
                        <tr style="font-weight:bold;">
                            <td>Dibuat oleh,</td>
                            <td>Diterima oleh,</td>
                            <td>Disetujui oleh,</td>
                        </tr>
                        <tr style="height:125px;">
                            <?= signatureCell($ttd, 'Pemohon', 'pemohon', $canSignPemohon, 'IT') ?>
                            <?= signatureCell($ttd, 'Atasan Pemohon', 'atasan_pemohon', $canSignAtasan, 'User') ?>
                            <?= signatureCell($ttd, 'Kabag IT', 'kabag_it', $canSignKabag, 'Kabag/Kadept IT') ?>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="text-align:center;margin-top:16px;font-size:12px;">
            SUM-FM-IT-037
        </div>
    </div>
</div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
    var ticket = '<?= h($ticket) ?>';

    $(document).on('click', '.btn-ttd', function(){
        var roleCode = $(this).data('role');
        $.post('../ttd_sign.php', { ticket: ticket, role_code: roleCode }, function(resp){
            if (resp && resp.success && resp.signature_url) {
                location.reload();
            } else if (resp && resp.need_template) {
                Swal.fire('Belum Ada Template TTD', 'Buat tanda tangan terlebih dahulu di menu Tandatangan.', 'warning');
            } else {
                Swal.fire('Gagal', (resp && resp.message) || 'Gagal memproses tanda tangan.', 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.btn-delete-ttd', function(){
        var roleCode = $(this).data('role');
        Swal.fire({
            title: 'Hapus Tanda Tangan?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (!result.isConfirmed) return;
            $.post('../delete_ttd.php', { ticket: ticket, role_code: roleCode }, function(resp){
                if (resp && resp.success) {
                    location.reload();
                } else {
                    Swal.fire('Gagal', (resp && resp.message) || 'Gagal menghapus tanda tangan.', 'error');
                }
            }, 'json');
        });
    });
});
</script>
</body>
</html>
