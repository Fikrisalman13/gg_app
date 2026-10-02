<?php
session_start();
require_once '../../koneksi.php';
$ticket = $_GET['ticket'] ?? '';
if (!$ticket) { die('Ticket tidak ditemukan'); }

$sql = "SELECT * FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) { die('Data tidak ditemukan'); }
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Flag rejected
$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';

// Check permissions via User_TTD_Template
// ... (Permission logic starts here)
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
// Cek role user untuk hak tanda tangan
$canSignPemohon = $canSignPetugasIT = $canSignKabagIT = $canSignKadeptIT = false;
if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole FROM User_TTD_Template WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            if (strcasecmp($role, 'Pemohon') === 0) $canSignPemohon = true;
            elseif (strcasecmp($role, 'Petugas IT') === 0) $canSignPetugasIT = true;
            elseif (strcasecmp($role, 'Kabag IT') === 0) $canSignKabagIT = true;
            elseif (strcasecmp($role, 'Kadept IT') === 0) $canSignKadeptIT = true;
        }
    }
}
function isTtdByCurrentUser($ttdArray, $currentUserId) {
    if (!isset($ttdArray['SignaturePath']) || empty($ttdArray['SignaturePath'])) return false;
    if (!isset($ttdArray['SignedByUserId'])) return false;
    return $ttdArray['SignedByUserId'] == $currentUserId;
}
function isTtdExists($ttdArray) {
    return isset($ttdArray['SignaturePath']) && !empty($ttdArray['SignaturePath']);
}
function fmtDate($dt) {
    if ($dt instanceof DateTime) return $dt->format('d-m-Y');
    if (is_string($dt) && preg_match('/\d{4}-\d{2}-\d{2}/', $dt)) {
        $p = explode('-', $dt); return "$p[2]-$p[1]-$p[0]";
    }
    return $dt ?: '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pengajuan : <?= htmlspecialchars($ticket) ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        body { background: #fff; font-size: 15px; }
        .border { border: 1px solid #000; }
        .b { font-weight: bold; }
        .center { text-align: center; }
        .purple-header { background: #6c2eb7; color: #fff; padding: 8px 18px; font-size: 1.1rem; border-radius: 6px 6px 0 0; margin-bottom: -1px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 6px 10px; vertical-align: top; }
        .ttd-box { height: 60px; text-align: center; }
        .catatan { font-size: 13px; margin-top: 8px; }
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
 <div class="border" style="max-width:900px;margin:18px auto 0 auto; padding: 0 0 10px 0; border-radius:6px; box-sizing:border-box; border: 2px solid #000;">
        <?php include 'form_contents/components/rejection_status.php'; ?>
        <table class="border" style="margin-bottom:10px;width:100%;">
            <tr>
                <td style="width:90px;border-right:1px solid #000;padding:8px 6px;vertical-align:top;text-align:center;">
                    <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
                </td>
                <td style="padding:6px 8px;vertical-align:top;">
                    <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
                    Jl. Tarajusari No. 8 Kp. Cipenudey RT 001 RW 007<br>
                    Banjaran – Kab. Bandung<br>
                    40377 Telp. (022) 594-0313
                </td>
            </tr>
            <tr>
                <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;">PENGAJUAN EMAIL ACCOUNT</td>
            </tr>
        </table>
        <table class="border" style="margin-bottom:10px;width:100%;">
            <tr>
                <td style="width:180px;">Nama Pemohon</td><td>: <?= htmlspecialchars($data['nama_pemohon'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Jabatan</td><td>: <?= htmlspecialchars($data['jabatan'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Departemen</td><td>: <?= htmlspecialchars($data['departemen'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Area</td><td>: <?= htmlspecialchars($data['area'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Tgl Pengajuan</td><td>: <?= fmtDate($data['tgl_pengajuan'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Permintaan</td>
                <td>:
                    <?php
                    $reqs = [];
                    if (!empty($data['request_email_account'])) $reqs[] = 'Email Account';
                    if (!empty($data['request_change_access'])) $reqs[] = 'Perubahan Hak Akses Email Account';
                    if (!empty($data['request_setting_device'])) $reqs[] = 'Setting Email Account pada device';
                    echo implode(', ', $reqs);
                    ?>
                </td>
            </tr>
            <?php
            $devs = [];
            if (!empty($data['device_android'])) $devs[] = 'Android';
            if (!empty($data['device_ios'])) $devs[] = 'Apple iOS';
            if (!empty($data['device_lainnya'])) $devs[] = 'Lainnya: ' . htmlspecialchars($data['device_lainnya_text'] ?? '');
            if (count($devs) > 0) {
            ?>
            <tr>
                <td>Perangkat</td>
                <td>: <?= implode(', ', $devs) ?></td>
            </tr>
            <?php } ?>
            <tr>
                <td style="vertical-align:top;">Status Akses Email</td>
                <td style="vertical-align:top;">
                    <div style="display:inline-block; vertical-align:top; min-width:10px;">:</div>
                    <div style="display:inline-block; vertical-align:top; padding-left:10px;">
                        <b>Akses Email:</b>
                        <ul style="list-style:none;padding-left:0;margin:0;">
                        <?php
                        // Local
                        if (!empty($data['akses_email_local'])) {
                            echo '<li><b>☑ Local</b> - Hanya bisa kirim & terima e-mail internal <i>(Sesama @sumitex.co.id)</i> – <b>Default</b></li>';
                        } else {
                            echo '<li>☐ Local - Hanya bisa kirim & terima e-mail internal <i>(Sesama @sumitex.co.id)</i> – Default</li>';
                        }
                        // Global
                        $isGlobal = !empty($data['akses_email_global']);
                        if ($isGlobal) {
                            echo '<li style="margin-top:2px;"><b>☑ Global</b>';
                            echo '<ul style="list-style:none;padding-left:18px;">';
                            // Sub global
                            if (!empty($data['global_kirim'])) {
                                echo '<li><b>☑</b> Hanya bisa kirim e-mail keluar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            } else {
                                echo '<li>☐ Hanya bisa kirim e-mail keluar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            }
                            if (!empty($data['global_terima'])) {
                                echo '<li><b>☑</b> Hanya bisa terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            } else {
                                echo '<li>☐ Hanya bisa terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            }
                            if (!empty($data['global_full'])) {
                                echo '<li><b>☑</b> Bisa kirim & terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i> – <b>Full Akses</b></li>';
                            } else {
                                echo '<li>☐ Bisa kirim & terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i> – Full Akses</li>';
                            }
                            echo '</ul></li>';
                        } else {
                            echo '<li>☐ Global';
                            echo '<ul style="list-style:none;padding-left:18px;">';
                            echo '<li>☐ Hanya bisa kirim e-mail keluar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            echo '<li>☐ Hanya bisa terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i></li>';
                            echo '<li>☐ Bisa kirim & terima e-mail dari luar <i>(Diluar domain @sumitex.co.id)</i> – Full Akses</li>';
                            echo '</ul></li>';
                        }
                        ?>
                        </ul>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Alamat E-Mail</td><td>: <?= htmlspecialchars($data['email_account'] ?? '') ? htmlspecialchars($data['email_account']) . '@sumtex.co.id' : '' ?></td>
            </tr>
            <tr>
                <td style="vertical-align:top;">Keterangan</td>
                <td style="vertical-align:top;">
                    <div style="display:flex; align-items:flex-start;">
                        <div style="margin-right:5px;">:</div>
                        <div style="word-break:break-word; overflow-wrap:anywhere;"><?= htmlspecialchars($data['keterangan'] ?? '') ?></div>
                    </div>
                </td>
            </tr>
        </table>
        <table class="border" style="width:100%;margin-bottom:10px;" border="1">
            <tr class="center b">
                <td style="width:25%;padding:4px;">Diajukan Oleh</td>
                <td style="width:25%;padding:4px;">Petugas IT</td>
                <td style="width:25%;padding:4px;">Kabag IT</td>
                <td style="width:25%;padding:4px;">Kadept IT</td>
            </tr>
            <tr style="height:120px;text-align:center;vertical-align:middle;">
                <td style="padding:6px;vertical-align:middle;">
                    <div id="ttd-area-pemohon">
                        <?php if (isset($ttd['Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" alt="TTD Pemohon"><?php endif; ?>
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
                    <div id="ttd-area-petugas-it">
                        <?php if (isset($ttd['Petugas IT'])): ?><img src="<?= htmlspecialchars($ttd['Petugas IT']['SignaturePath']) ?>" height="60" alt="TTD Petugas IT"><?php endif; ?>
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
                    <div id="ttd-area-kabag">
                        <?php if (isset($ttd['Kabag IT'])): ?><img src="<?= htmlspecialchars($ttd['Kabag IT']['SignaturePath']) ?>" height="60" alt="TTD Kabag IT"><?php endif; ?>
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
                    <div id="ttd-area-kadept">
                        <?php if (isset($ttd['Kadept IT'])): ?><img src="<?= htmlspecialchars($ttd['Kadept IT']['SignaturePath']) ?>" height="60" alt="TTD Kadept IT"><?php endif; ?>
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
            <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
            <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
            <script>
            $(function(){
                var currentRoleCode = null;
                var isRejected = <?= $isRejected ? 'true':'false' ?>;
                var roleToDelete = null;
                function updateTtdArea(roleCode, imgUrl, name, signedByUserId){
                    var areaId,labelId;
                    if (roleCode==='pemohon'){ areaId='#ttd-area-pemohon'; }
                    else if (roleCode==='petugas_it'){ areaId='#ttd-area-petugas-it'; }
                    else if (roleCode==='kabag_it'){ areaId='#ttd-area-kabag'; }
                    else if (roleCode==='kadept_it'){ areaId='#ttd-area-kadept'; }
                    if (areaId) $(areaId).html('<img src="'+imgUrl+'" height="60">');
                    var tdContainer = $('button[data-role="'+roleCode+'"]:visible').closest('td');
                    if (tdContainer.length){ tdContainer.find('button[data-role="'+roleCode+'"]').remove();
                        var currentUserId = <?= $_SESSION['UserId'] ?? 0 ?>;
                        if (isRejected) return;
                        if (signedByUserId && parseInt(signedByUserId) === parseInt(currentUserId)) {
                            tdContainer.append('<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="'+roleCode+'">Hapus Tanda Tangan</button>');
                            attachDeleteButtonListener();
                        } else {
                            tdContainer.append('<button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>');
                        }
                    }
                }
                function removeTtdArea(roleCode){
                    var areaId,def;
                    if (roleCode==='pemohon'){ areaId='#ttd-area-pemohon'; def='Pemohon'; }
                    else if (roleCode==='petugas_it'){ areaId='#ttd-area-petugas-it'; def='Petugas IT'; }
                    else if (roleCode==='kabag_it'){ areaId='#ttd-area-kabag'; def='Kabag IT'; }
                    else if (roleCode==='kadept_it'){ areaId='#ttd-area-kadept'; def='Kadept IT'; }
                    if (areaId) $(areaId).html('');
                    var tdContainer = $('button[data-role="'+roleCode+'"]:visible').closest('td');
                    if (tdContainer.length){ tdContainer.find('button[data-role="'+roleCode+'"]').remove(); if(!isRejected){ tdContainer.append('<button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="'+roleCode+'">Tanda Tangan</button>'); attachSignButtonListener(); } }
                }
                function attachSignButtonListener(){ $(document).off('click','.btn-ttd').on('click','.btn-ttd', function(){ var roleCode=$(this).data('role'); currentRoleCode=roleCode; $.post('ttd_sign.php',{ ticket:'<?= htmlspecialchars($data['ticket']) ?>', role_code:roleCode }, function(resp){ try{ resp= typeof resp==='string'?JSON.parse(resp):resp; }catch(e){ resp={}; } if(resp && resp.success && resp.signature_url){ updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id); } else if(resp && resp.need_template){ $('#ttdRoleCode').val(roleCode); $('#modalTtd').modal('show'); } else { alert(resp.message || 'Gagal memproses tanda tangan.'); } }); }); }
                function attachDeleteButtonListener(){ $(document).off('click','.btn-delete-ttd').on('click','.btn-delete-ttd', function(){ roleToDelete=$(this).data('role'); $.post('delete_ttd.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleToDelete }, function(resp){ try{ resp= typeof resp==='string'?JSON.parse(resp):resp; }catch(e){ resp={}; } if(resp && resp.success){ removeTtdArea(roleToDelete); Swal.fire('Berhasil','Tanda tangan berhasil dihapus.','success'); } else { alert(resp.message || 'Gagal menghapus tanda tangan.'); } }); }); }
                attachSignButtonListener();
                attachDeleteButtonListener();
            });
            </script>
            <tr style="text-align:center;font-weight:bold;font-size:14px;">
                <td><?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?></td>
                <td><?= isset($ttd['Petugas IT']) ? htmlspecialchars($ttd['Petugas IT']['SignedByUserName']) : 'Petugas IT' ?></td>
                <td><?= isset($ttd['Kabag IT']) ? htmlspecialchars($ttd['Kabag IT']['SignedByUserName']) : 'Kabag IT' ?></td>
                <td><?= isset($ttd['Kadept IT']) ? htmlspecialchars($ttd['Kadept IT']['SignedByUserName']) : 'Kadept IT' ?></td>
            </tr>
        </table>
        <div class="catatan">
            <b>Ketentuan:</b><br>
            1. E-mail user adalah e-mail perusahaan, bukan e-mail pribadi. Tidak diperkenankan User melakukan pendaftaran mailing list atau website tertentu menggunakan alamat email kantor dikarenakan dapat mengundang Spam terhadap e-mail kantor yang dapat mengganggu aktifitas e-mail kantor.<br> Apabila User ditemukan melakukan hal tersebut maka akan dikenakan sanksi sesuai dengan peraturan yang berlaku.<br>
            2. User tidak diperbolehkan untuk melakukan setting e-mail sendiri di komputer user.<br>
            3. User harus memelihara kerahasiaan semua hasil kerja (Data Perusahaan) dan tidak dibenarkan untuk memberikan atau menyampaikan kepada pihak manapun.<br>
            4. E-mail kantor hanya di setting pada perangkat milik kantor dan tidak diperbolehkan di setting pada komputer atau perangkat pribadi lainnya.<br>
            5. Bila tidak dipilih e-mail setting Local atau Global maka IT akan melakukan settingan secara default secara local only.<br>
        </div>
    </div>
</body>
</html>
