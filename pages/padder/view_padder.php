<?php
// view_padder.php - Detail Padder dengan Tab
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ===== Auth =====
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$padderId = $_GET['id'] ?? '';
if (empty($padderId)) {
    $_SESSION['error'] = "Padder ID tidak valid!";
    header('Location: master_padder.php');
    exit;
}

// ===== Query Data =====
function fetchAll($conn, $sql, $params=[]) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    $rows = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// Data utama padder
$sqlPadder = "SELECT * FROM pad_m_padder WHERE padder_id = ?";
$stmtPadder = sqlsrv_query($conn, $sqlPadder, [$padderId]);
$padder = sqlsrv_fetch_array($stmtPadder, SQLSRV_FETCH_ASSOC);
if (!$padder) {
    $_SESSION['error'] = "Padder tidak ditemukan!";
    header('Location: master_padder.php');
    exit;
}

// Spesifikasi
$specs = fetchAll($conn, "SELECT * FROM pad_m_padder_spec WHERE padder_id = ?", [$padderId]);

// Purchase
$purchases = fetchAll($conn, "
    SELECT p.*, v.vendor_name 
    FROM pad_t_purchase p 
    LEFT JOIN pad_m_vendor v ON p.vendor_id = v.vendor_id 
    WHERE p.padder_id = ?", [$padderId]);

// Receive dengan foto
$receives = fetchAll($conn, "
    SELECT r.*, 
           (SELECT COUNT(*) FROM pad_t_receive_files rf WHERE rf.receive_id = r.id) as photo_count
    FROM pad_t_receive r 
    WHERE r.padder_id = ? 
    ORDER BY r.receive_date DESC", [$padderId]);

// Get photos untuk setiap receive
foreach ($receives as &$receive) {
    $receive['photos'] = fetchAll($conn, 
        "SELECT * FROM pad_t_receive_files WHERE receive_id = ?", 
        [$receive['id']]
    );
}

// Usage
$usages = fetchAll($conn, "SELECT * FROM pad_t_usage WHERE padder_id = ? ORDER BY used_date DESC", [$padderId]);

// Maintenance
$maintenances = fetchAll($conn, "SELECT * FROM pad_t_maintenance WHERE padder_id = ? ORDER BY maintenance_date DESC", [$padderId]);

// Repair
$repairs = fetchAll($conn, "
    SELECT r.*, v.vendor_name 
    FROM pad_t_repair r 
    LEFT JOIN pad_m_vendor v ON r.vendor_id = v.vendor_id 
    WHERE r.padder_id = ? ORDER BY send_date DESC", [$padderId]);

// History
$histories = fetchAll($conn, "SELECT * FROM pad_status_log WHERE padder_id = ? ORDER BY changed_at DESC", [$padderId]);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Detail Padder</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="master_padder.php">Master Padder</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'info'); ?> text-white">
                    <h3 class="card-title">
                        <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                    </h3>
                    <span class="badge badge-<?= 
                        $padder['status'] == 'READY' ? 'success' : 
                        ($padder['status'] == 'IN_USE' ? 'primary' : 
                        ($padder['status'] == 'MAINTENANCE' ? 'warning' : 
                        ($padder['status'] == 'REPAIR_VENDOR' ? 'secondary' : 
                        ($padder['status'] == 'SCRAP' ? 'danger' : 'info')))) ?>">
                        <?= htmlspecialchars($padder['status']) ?>
                    </span>
                </div>

                <div class="card-body">

                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="padderTabs" role="tablist">
                        <?php 
                        $tabs = [
                            'info'=>'Info', 
                            'spec'=>'Spesifikasi', 
                            'purchase'=>'Purchase', 
                            'receive'=>'Receive',
                            'usage'=>'Usage', 
                            'maintenance'=>'Maintenance', 
                            'repair'=>'Repair', 
                            'history'=>'History'
                        ];
                        $first = true;
                        foreach($tabs as $id=>$name): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $first ? 'active':'' ?>" id="<?= $id ?>-tab" data-toggle="tab" href="#<?= $id ?>" role="tab">
                                <i class="fas fa-<?= 
                                    $id=='info'?'info-circle':
                                    ($id=='spec'?'list':
                                    ($id=='receive'?'truck-loading':'history')) 
                                ?>"></i> <?= $name ?>
                            </a>
                        </li>
                        <?php $first=false; endforeach; ?>
                    </ul>

                    <div class="tab-content mt-3">

                        <!-- Info -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 table-responsive">
                                    <table class="table table-bordered">
                                        <tr><th>Padder ID</th><td><?= htmlspecialchars($padder['padder_id']) ?></td></tr>
                                        <tr><th>Nama Padder</th><td><?= htmlspecialchars($padder['padder_name']) ?></td></tr>
                                        <tr><th>Status</th><td>
                                            <span class="badge badge-<?= 
                                                $padder['status'] == 'READY' ? 'success' : 
                                                ($padder['status'] == 'IN_USE' ? 'primary' : 
                                                ($padder['status'] == 'MAINTENANCE' ? 'warning' : 
                                                ($padder['status'] == 'REPAIR_VENDOR' ? 'secondary' : 
                                                ($padder['status'] == 'SCRAP' ? 'danger' : 'info')))) ?>">
                                                <?= htmlspecialchars($padder['status']) ?>
                                            </span>
                                        </td></tr>
                                        <?php if (!empty($padder['qr_code'])): ?>
                                        <tr><th>QR Code</th>
                                            <td>
                                                <img src="<?= htmlspecialchars($padder['qr_code']) ?>" 
                                                     alt="QR Code" 
                                                     style="width: 100px; height: 100px;"
                                                     class="border rounded">
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6 table-responsive">
                                    <table class="table table-bordered">
                                        <tr><th>Dibuat</th><td><?= $padder['created_at'] ? $padder['created_at']->format('d/m/Y H:i') : '-' ?></td></tr>
                                        <tr><th>Oleh</th><td><?= htmlspecialchars($padder['created_by']) ?></td></tr>
                                        <tr><th>Diupdate</th><td><?= $padder['updated_at'] ? $padder['updated_at']->format('d/m/Y H:i') : '-' ?></td></tr>
                                        <tr><th>Oleh</th><td><?= $padder['updated_by'] ?: '-' ?></td></tr>
                                        <tr><th>Keterangan</th><td><?= $padder['remarks'] ?: '-' ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Spesifikasi -->
                        <div class="tab-pane fade" id="spec" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Nama Spesifikasi</th><th>Nilai</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($specs as $spec): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($spec['spec_name']) ?></td>
                                            <td><?= htmlspecialchars($spec['spec_value']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($specs)): ?>
                                        <tr><td colspan="2" class="text-center text-muted">Tidak ada spesifikasi</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Purchase -->
                        <div class="tab-pane fade" id="purchase" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Tanggal</th><th>PO Number</th><th>Vendor</th><th>Harga</th><th>Keterangan</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($purchases as $p): ?>
                                        <tr>
                                            <td><?= $p['purchase_date'] ? $p['purchase_date']->format('d/m/Y') : '-' ?></td>
                                            <td><?= htmlspecialchars($p['po_number']) ?></td>
                                            <td><?= htmlspecialchars($p['vendor_name']) ?></td>
                                            <td>Rp <?= number_format($p['price']??0,0,',','.') ?></td>
                                            <td><?= $p['remarks'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($purchases)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">Tidak ada data purchase</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Receive -->
                        <div class="tab-pane fade" id="receive" role="tabpanel">
                            <?php if (empty($receives)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Belum ada data penerimaan untuk padder ini.
                                </div>
                            <?php else: ?>
                                <?php foreach($receives as $receive): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-truck-loading text-primary"></i>
                                            Penerimaan - GRN: <?= htmlspecialchars($receive['grn_number']) ?>
                                            <span class="badge badge-info float-right">
                                                <?= $receive['receive_date']->format('d/m/Y') ?>
                                            </span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm table-borderless">
                                                    <tr>
                                                        <th width="40%">Tanggal Terima</th>
                                                        <td><?= $receive['receive_date']->format('d/m/Y') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Nomor GRN</th>
                                                        <td><strong><?= htmlspecialchars($receive['grn_number']) ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Keterangan</th>
                                                        <td><?= htmlspecialchars($receive['remarks'] ?: '-') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Jumlah Foto</th>
                                                        <td>
                                                            <span class="badge badge-<?= $receive['photo_count'] > 0 ? 'success' : 'secondary' ?>">
                                                                <?= $receive['photo_count'] ?> foto
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if (!empty($receive['photos'])): ?>
                                                    <h6><i class="fas fa-camera text-success"></i> Dokumentasi Foto:</h6>
                                                    <div class="row">
                                                        <?php foreach($receive['photos'] as $photo): ?>
                                                        <div class="col-6 col-md-4 mb-2">
                                                            <div class="text-center">
                                                                <a href="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                                   target="_blank" 
                                                                   class="d-block mb-1">
                                                                    <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                                         alt="Foto Penerimaan" 
                                                                         class="img-thumbnail" 
                                                                         style="height: 80px; width: 100%; object-fit: cover;"
                                                                         onerror="this.src='/gg_app/images/no-image.jpg'">
                                                                </a>
                                                                <small class="text-muted">Foto <?= $photo['id'] ?></small>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-warning text-center py-3">
                                                        <i class="fas fa-camera fa-2x mb-2"></i><br>
                                                        <small>Tidak ada foto dokumentasi</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Usage -->
                        <div class="tab-pane fade" id="usage" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Tanggal</th><th>Lokasi</th><th>Mesin</th><th>Keterangan</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($usages as $u): ?>
                                        <tr>
                                            <td><?= $u['used_date'] ? $u['used_date']->format('d/m/Y') : '-' ?></td>
                                            <td><?= htmlspecialchars($u['location']) ?></td>
                                            <td><?= htmlspecialchars($u['machine_name']) ?></td>
                                            <td><?= $u['remarks'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($usages)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Tidak ada data pemakaian</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Maintenance -->
                        <div class="tab-pane fade" id="maintenance" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Tanggal</th><th>Pekerjaan</th><th>Hardness Check</th><th>Catatan</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($maintenances as $m): ?>
                                        <tr>
                                            <td><?= $m['maintenance_date'] ? $m['maintenance_date']->format('d/m/Y') : '-' ?></td>
                                            <td><?= htmlspecialchars($m['work_done']) ?></td>
                                            <td><?= $m['hardness_check'] ?: '-' ?></td>
                                            <td><?= $m['notes'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($maintenances)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Tidak ada data maintenance</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Repair -->
                        <div class="tab-pane fade" id="repair" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Tanggal Kirim</th><th>No SJ</th><th>Vendor</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($repairs as $r): ?>
                                        <tr>
                                            <td><?= $r['send_date'] ? $r['send_date']->format('d/m/Y') : '-' ?></td>
                                            <td><?= htmlspecialchars($r['sj_number']) ?></td>
                                            <td><?= htmlspecialchars($r['vendor_name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $r['status'] == 'ON REPAIR' ? 'warning' : 
                                                    ($r['status'] == 'COMPLETED' ? 'success' : 'secondary')
                                                ?>">
                                                    <?= htmlspecialchars($r['status']) ?>
                                                </span>
                                            </td>
                                            
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($repairs)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">Tidak ada data perbaikan</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- History -->
                        <div class="tab-pane fade" id="history" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>Waktu</th><th>Status</th><th>User</th><th>Keterangan</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($histories as $h): ?>
                                        <tr>
                                            <td><?= $h['changed_at'] ? $h['changed_at']->format('d/m/Y H:i') : '-' ?></td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $h['status'] == 'READY' ? 'success' : 
                                                    ($h['status'] == 'IN_USE' ? 'primary' : 
                                                    ($h['status'] == 'MAINTENANCE' ? 'warning' : 
                                                    ($h['status'] == 'REPAIR_VENDOR' ? 'secondary' : 
                                                    ($h['status'] == 'SCRAP' ? 'danger' : 'info')))) ?>">
                                                    <?= htmlspecialchars($h['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($h['changed_by']) ?></td>
                                            <td><?= $h['remarks'] ?: '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($histories)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">Tidak ada riwayat status</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(function(){
    // Aktifkan tab dari hash URL
    var hash = window.location.hash;
    if(hash) $('.nav-tabs a[href="'+hash+'"]').tab('show');

    // Update URL hash saat pindah tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
        window.location.hash = e.target.hash;
    });

    // Handle error image
    $('img').on('error', function(){
        $(this).attr('src', '/gg_app/images/no-image.jpg');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>