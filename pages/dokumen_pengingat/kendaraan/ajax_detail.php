<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../koneksi.php';

$id = $_GET['id'] ?? null;
if (!$id) die('ID tidak valid');

$sql = "
SELECT 
    sk.*, 
    b.bagian as nama_bagian
FROM dr_surat_kendaraan sk
LEFT JOIN dbo.m_bag b ON sk.bagian_id = b.id_bag
WHERE sk.id = ?";

$stmt = sqlsrv_query($conn, $sql, [$id]);
$r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$r) die('Data tidak ditemukan');
?>

<table class="table table-sm table-bordered">
    <tr><th width="30%">No Polisi</th><td><?= htmlspecialchars((string)$r['no_polisi']) ?></td></tr>
    <tr><th>Jenis Kendaraan</th><td><?= htmlspecialchars((string)$r['jenis_kendaraan']) ?></td></tr>
    <tr><th>Nama Kendaraan</th><td><?= htmlspecialchars((string)$r['nama_kendaraan']) ?></td></tr>
    <tr><th>Nama Pemilik</th><td><?= htmlspecialchars((string)$r['nama_pemilik']) ?></td></tr>
    <tr><th>Expire Date</th><td><?= $r['expire_date'] ? $r['expire_date']->format('d-m-Y') : '-' ?></td></tr>
    <tr><th>Bagian</th><td><?= htmlspecialchars((string)$r['nama_bagian']) ?></td></tr>
    <tr><th>Email Reminder</th><td><?= htmlspecialchars((string)$r['email_reminder']) ?></td></tr>
    <tr><th>No Whatsapp</th><td><?= htmlspecialchars((string)$r['no_whatsapp']) ?></td></tr>
    <tr><th>Keterangan</th><td><?= nl2br(htmlspecialchars((string)$r['keterangan'])) ?></td></tr>
    <tr><th>Dibuat Oleh</th><td><?= htmlspecialchars((string)$r['created_by']) ?></td></tr>
    <tr><th>Tanggal Buat</th><td><?= $r['createdate'] ? $r['createdate']->format('d-m-Y H:i') : '-' ?></td></tr>
    <tr>
        <th>File</th>
        <td>
            <?php 
            if ($r['file_kendaraan']) {
                $files = explode(',', $r['file_kendaraan']);
                foreach ($files as $f) {
                    $f = trim($f);
                    if ($f === '') continue;
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    $icon = ($ext === 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                    echo '<a href="/gg_app/pages/dokumen_pengingat/kendaraan/uploads/kendaraan/'.rawurlencode($f).'" target="_blank" class="btn btn-xs btn-info mb-1 mr-1" style="max-width: 100%; white-space: normal; word-break: break-all; text-align: left;">
                            <i class="fas '.$icon.'"></i> '.htmlspecialchars($f).'
                          </a>';
                }
            } else {
                echo '-';
            }
            ?>
        </td>
    </tr>
</table>

<?php
// Cek riwayat perpanjangan
$stmtHistory = sqlsrv_query($conn, "SELECT * FROM dr_document_renewals WHERE document_type = 'kendaraan' AND document_id = ? ORDER BY created_at DESC", [$id]);
$histories = [];
if ($stmtHistory) {
    while($rh = sqlsrv_fetch_array($stmtHistory, SQLSRV_FETCH_ASSOC)) {
        $histories[] = $rh;
    }
}

if (count($histories) > 0) {
    echo '<div class="mt-4">';
    echo '<h6 class="font-weight-bold text-success"><i class="fas fa-history mr-1"></i> Riwayat Perpanjangan</h6>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-sm mb-0 text-center" style="font-size: 13px;">';
    echo '<thead class="bg-light"><tr><th>Tgl Perpanjangan</th><th>Oleh</th><th>Expire Lama</th><th>Expire Baru</th><th>Dokumen Lama</th><th>Catatan</th></tr></thead>';
    echo '<tbody>';
    foreach ($histories as $h) {
        $tglRenew = $h['created_at'] ? $h['created_at']->format('d/m/Y H:i') : '-';
        $oldExp = $h['old_expire_date'] ? $h['old_expire_date']->format('d/m/Y') : '-';
        $newExp = $h['new_expire_date'] ? $h['new_expire_date']->format('d/m/Y') : '-';
        $remarks = $h['remarks'] ? htmlspecialchars($h['remarks']) : '-';
        $creator = $h['created_by'] ? htmlspecialchars($h['created_by']) : '-';
        
                $oldFileLink = '-';
        if (!empty($h['old_file_path'])) {
            $paths = explode(',', $h['old_file_path']);
            $links = [];
            $dir = '';
            foreach ($paths as $idx => $p) {
                $p = trim($p);
                if ($p === '') continue;
                
                if (strpos($p, '/') === false && $dir !== '') {
                    $p = $dir . $p;
                } else if ($idx === 0) {
                    $dir = dirname($p) . '/';
                }
                
                $url = rawurlencode($p);
                $url = str_replace('%2F', '/', $url);
                $links[] = '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\''.$url.'\')" class="text-primary text-nowrap" style="font-size:12px;"><i class="fas fa-file-alt"></i> File '.($idx+1).'</a></div>';
            }
            $oldFileLink = implode('', $links);
        }

        echo '<tr>';
        echo '<td>' . $tglRenew . '</td>';
        echo '<td>' . $creator . '</td>';
        echo '<td class="text-danger"><del>' . $oldExp . '</del></td>';
        echo '<td class="text-success font-weight-bold">' . $newExp . '</td>';
        echo '<td>' . $oldFileLink . '</td>';
        echo '<td>' . $remarks . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
}
?>