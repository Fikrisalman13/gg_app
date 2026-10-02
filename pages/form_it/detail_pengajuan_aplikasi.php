<?php

session_start();
require_once '../../koneksi.php';

$ticket = trim((string) ($_GET['ticket'] ?? ''));
if ($ticket === '') {
    http_response_code(400);
    exit('Ticket tidak ditemukan');
}

$detailStatement = sqlsrv_query(
    $conn,
    "SELECT * FROM Form_Pengajuan_Aplikasi WHERE ticket = ?",
    [$ticket]
);
if ($detailStatement === false) {
    http_response_code(500);
    exit('Data gagal diproses');
}

$data = sqlsrv_fetch_array($detailStatement, SQLSRV_FETCH_ASSOC);
if (!$data) {
    http_response_code(404);
    exit('Data tidak ditemukan');
}

$isRejected = strtolower(trim((string) ($data['status_ticket'] ?? ''))) === 'ditolak';
$roleCodes = [
    'Pemohon' => 'pemohon',
    'Atasan Pemohon' => 'atasan_pemohon',
    'Petugas IT' => 'petugas_it',
    'Kabag IT' => 'kabag_it',
    'Kadept IT' => 'kadept_it',
];
$permissions = array_fill_keys(array_keys($roleCodes), false);

if (isset($_SESSION['UserId'])) {
    $permissionSql = "SELECT DISTINCT GroupRole
                      FROM User_TTD_Template
                      WHERE UserId = ? AND IsActive = 1";
    $permissionStatement = sqlsrv_query($conn, $permissionSql, [$_SESSION['UserId']]);

    while (
        $permissionStatement
        && ($permissionRow = sqlsrv_fetch_array($permissionStatement, SQLSRV_FETCH_ASSOC))
    ) {
        $groupRole = trim((string) $permissionRow['GroupRole']);
        if (array_key_exists($groupRole, $permissions)) {
            $permissions[$groupRole] = true;
        }
    }
}

$signatures = [];
$signatureSql = "SELECT GroupRole,
                        SignaturePath,
                        SignedByUserName,
                        SignedByUserId
                 FROM Form_Pengajuan_Barang_TTD
                 WHERE Ticket = ?";
$signatureStatement = sqlsrv_query($conn, $signatureSql, [$ticket]);

while (
    $signatureStatement
    && ($signatureRow = sqlsrv_fetch_array($signatureStatement, SQLSRV_FETCH_ASSOC))
) {
    $signatures[$signatureRow['GroupRole']] = $signatureRow;
}

$requestFields = [
    'nama_aplikasi' => 'Nama Aplikasi',
    'latar_belakang_kendala' => 'Latar Belakang dan Kendala Saat Ini',
    'tujuan_pembuatan' => 'Tujuan Pembuatan Aplikasi',
    'gambaran_proses' => 'Gambaran Proses yang Diharapkan',
    'fitur_utama' => 'Kebutuhan atau Fitur Utama',
    'manfaat_diharapkan' => 'Manfaat yang Diharapkan',
];

/** Escape output detail untuk HTML. */
function appEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format tanggal detail menjadi DD-MM-YYYY. */
function appDate($value)
{
    return $value instanceof DateTime ? $value->format('d-m-Y') : appEsc($value ?: '-');
}

/** Cek apakah tanda tangan dibuat user aktif. */
function appSignedByMe($signature)
{
    return !empty($signature['SignaturePath'])
        && (string) ($signature['SignedByUserId'] ?? '') === (string) ($_SESSION['UserId'] ?? '');
}

/** Cek apakah role sudah memiliki tanda tangan. */
function appHasSignature($signature)
{
    return !empty($signature['SignaturePath']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pengajuan Aplikasi - <?= appEsc($ticket) ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        body { background: #fff; color: #111; font-size: 14px; }
        .form-wrap { max-width: 900px; margin: 10px auto; }
        .outer-form { width: 100%; border: 1px solid #000; border-collapse: collapse; }
        .header-table, .identity-table, .signature-table { width: 100%; border-collapse: collapse; }
        .company-logo { width: 90px; border-right: 1px solid #000; padding: 8px 6px; text-align: center; }
        .company-info { padding: 6px 8px; line-height: 1.35; }
        .form-title {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px;
            text-align: center;
            font-weight: 700;
        }
        .form-body { padding: 10px 12px 14px; }
        .identity-table td { padding: 3px 5px; vertical-align: top; }
        .identity-label { width: 145px; white-space: nowrap; }
        .identity-separator { width: 12px; padding-left: 0 !important; padding-right: 0 !important; }
        .identity-value { width: 270px; }
        .request-section { margin-top: 12px; border-top: 1px solid #bbb; padding-top: 10px; }
        .request-name { font-weight: 700; margin-bottom: 7px; }
        .request-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 18px; }
        .request-item { min-width: 0; padding: 7px 0; border-bottom: 1px solid #e1e1e1; }
        .request-label { display: block; font-weight: 700; margin-bottom: 2px; }
        .request-value { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.45; }
        .attachment-row { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e1e1e1; }
        .signature-wrap { padding: 6px; border-top: 1px solid #000; }
        .signature-table { table-layout: fixed; text-align: center; }
        .signature-table td { border: 1px solid #000; padding: 5px; vertical-align: middle; }
        .signature-area { min-height: 78px; display: flex; align-items: center; justify-content: center; }
        .signature-area img { height: 60px; max-width: 100%; object-fit: contain; }
        .note { border-top: 1px solid #000; padding: 7px 10px; font-size: 13px; }
        @media (max-width: 768px) {
            .form-wrap { overflow-x: auto; margin: 5px auto; }
            .outer-form { min-width: 850px; }
        }
    </style>
    <script>
    window.addEventListener('load', function() {
        const canReject = <?= (
            ($permissions['Kabag IT'] || $permissions['Kadept IT']) && !$isRejected
        ) ? 'true' : 'false' ?>;
        if (window.parent && window.parent.updateRejectButtonVisibility) {
            window.parent.updateRejectButtonVisibility(canReject, '<?= appEsc($ticket) ?>');
        }
    });
    </script>
</head>
<body>
<?php include 'form_contents/components/rejection_status.php'; ?>
<div class="form-wrap">
    <table class="outer-form">
        <tr>
            <td style="padding: 0;">
                <table class="header-table">
                    <tr>
                        <td class="company-logo">
                            <img src="../../dist/img/sumlogo.png" width="70" alt="Logo PT Surya Usaha Mandiri">
                        </td>
                        <td class="company-info">
                            <strong style="font-size: 16px;">PT. SURYA USAHA MANDIRI</strong><br>
                            Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                            Banjaran â€“ Kab. Bandung<br>
                            40377 Telp. (022) 594-0313
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="form-title">
                            FORM PENGAJUAN PEMBUATAN APLIKASI
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="form-body">
                <table class="identity-table">
                    <tr>
                        <td class="identity-label">No. Pengajuan</td>
                        <td class="identity-separator">:</td>
                        <td class="identity-value"><?= appEsc($ticket) ?></td>
                        <td class="identity-label">Tanggal Pengajuan</td>
                        <td class="identity-separator">:</td>
                        <td><?= appDate($data['tgl_pengajuan']) ?></td>
                    </tr>
                    <tr>
                        <td class="identity-label">Nama Pemohon</td>
                        <td class="identity-separator">:</td>
                        <td class="identity-value">
                            <?= appEsc($data['nama_pemohon']) ?>
                        </td>
                        <td class="identity-label">Jabatan</td>
                        <td class="identity-separator">:</td>
                        <td><?= appEsc($data['jabatan']) ?></td>
                    </tr>
                    <tr>
                        <td class="identity-label">Departemen</td>
                        <td class="identity-separator">:</td>
                        <td class="identity-value">
                            <?= appEsc($data['departemen']) ?>
                        </td>
                        <td class="identity-label">Bagian</td>
                        <td class="identity-separator">:</td>
                        <td><?= appEsc($data['bagian']) ?></td>
                    </tr>
                </table>
                <section class="request-section">
                    <div class="request-name">Informasi Kebutuhan Aplikasi</div>
                    <div class="request-grid">
                        <?php foreach ($requestFields as $fieldName => $fieldLabel): ?>
                            <div class="request-item">
                                <span class="request-label"><?= appEsc($fieldLabel) ?></span>
                                <div class="request-value"><?= appEsc($data[$fieldName]) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="attachment-row">
                        <strong>Lampiran Pendukung:</strong>
                        <?php if (!empty($data['lampiran_nama_asli'])): ?>
                            <a
                                href="download_lampiran_aplikasi.php?ticket=<?= urlencode($ticket) ?>"
                                target="_blank"
                                rel="noopener"
                            ><?= appEsc($data['lampiran_nama_asli']) ?></a>
                        <?php else: ?>-<?php endif; ?>
                    </div>
                </section>
            </td>
        </tr>
        <tr>
            <td class="signature-wrap">
                <table class="signature-table">
                    <tr class="font-weight-bold">
                        <?php foreach ($roleCodes as $roleName => $roleCode): ?>
                            <td><?= appEsc($roleName) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($roleCodes as $roleName => $roleCode): ?>
                            <?php $signature = $signatures[$roleName] ?? []; ?>
                            <td>
                                <div class="signature-area" id="ttd-area-<?= appEsc($roleCode) ?>">
                                    <?php if (appHasSignature($signature)): ?>
                                        <img
                                            src="<?= appEsc($signature['SignaturePath']) ?>"
                                            alt="Tanda tangan <?= appEsc($roleName) ?>"
                                        >
                                    <?php endif; ?>
                                </div>
                                <?php if ($permissions[$roleName] && !$isRejected): ?>
                                    <?php if (appSignedByMe($signature)): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger btn-delete-ttd"
                                            data-role="<?= appEsc($roleCode) ?>"
                                        >Hapus Tanda Tangan</button>
                                    <?php elseif (appHasSignature($signature)): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            disabled
                                        >Sudah Ditandatangani</button>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary btn-ttd"
                                            data-role="<?= appEsc($roleCode) ?>"
                                        >Tanda Tangan</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="font-weight-bold">
                        <?php foreach ($roleCodes as $roleName => $roleCode): ?>
                            <td id="ttd-label-<?= appEsc($roleCode) ?>">
                                <?= appEsc($signatures[$roleName]['SignedByUserName'] ?? $roleName) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="note">
                <strong>Catatan:</strong> Persetujuan form ini hanya menentukan pengajuan diterima
                atau ditolak. Proses pengembangan dan serah terima dicatat pada form terpisah.
            </td>
        </tr>
    </table>
</div>
<?php include 'form_contents/components/signature_canvas.php'; ?>
<div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Hapus Tanda Tangan?</h5>
            </div>
            <div class="modal-body">Tanda tangan ini akan dihapus.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
$(function initializeSignatureActions() {
    const ticket = '<?= appEsc($ticket) ?>';
    const isRejected = <?= $isRejected ? 'true' : 'false' ?>;
    const currentUserId = <?= (int) ($_SESSION['UserId'] ?? 0) ?>;
    let roleToDelete = null;

    $('.btn-ttd').on('click', function handleSign() {
        const roleCode = $(this).data('role');
        $.post('ttd_sign.php', {ticket: ticket, role_code: roleCode}, function handleResponse(response) {
            if (response.success && response.signature_url) {
                updateSignatureArea(
                    roleCode,
                    response.signature_url,
                    response.signed_by,
                    response.signed_by_user_id
                );
                return;
            }
            if (response.need_template) {
                $('#ttdTicket').val(ticket);
                $('#ttdRoleCode').val(roleCode);
                $('#modalTtd').modal('show');
                return;
            }
            alert(response.message || 'Gagal memproses tanda tangan.');
        }, 'json').fail(function handleFailure() {
            alert('Gagal menghubungi server.');
        });
    });

    $(document).on('ttdSaved', function handleSaved(event, roleCode, url, name, userId) {
        updateSignatureArea(roleCode, url, name, userId);
    });

    $(document).on('click', '.btn-delete-ttd', function prepareDelete() {
        roleToDelete = $(this).data('role');
        $('#modalKonfirmasiHapusTtd').modal('show');
    });

    $('#btnKonfirmasiHapusTtd').on('click', function deleteSignature() {
        $.post(
            'delete_ttd.php',
            {ticket: ticket, role_code: roleToDelete},
            function handleDeleteResponse(response) {
                if (response.success) {
                    location.reload();
                    return;
                }
                alert(response.message || 'Gagal menghapus tanda tangan.');
            },
            'json'
        );
    });

    function updateSignatureArea(roleCode, imageUrl, signerName, signedByUserId) {
        const signatureImage = $('<img>', {
            src: imageUrl,
            alt: 'Tanda tangan',
        });
        const signatureCell = $('#ttd-area-' + roleCode).closest('td');

        $('#ttd-area-' + roleCode).empty().append(signatureImage);
        $('#ttd-label-' + roleCode).text(signerName || '');
        signatureCell.find('button').remove();

        if (isRejected) {
            return;
        }

        const signedByCurrentUser = parseInt(signedByUserId, 10) === currentUserId;
        const actionButton = signedByCurrentUser
            ? $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-outline-danger btn-delete-ttd',
                'data-role': roleCode,
                text: 'Hapus Tanda Tangan',
            })
            : $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-outline-secondary',
                disabled: true,
                text: 'Sudah Ditandatangani',
            });
        signatureCell.append(actionButton);
    }
});
</script>
</body>
</html>

