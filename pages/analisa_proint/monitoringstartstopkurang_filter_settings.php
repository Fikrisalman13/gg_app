<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
$currentUser = $_SESSION['UserName'] ?? '';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 75;
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

$message = '';
$messageType = '';

// Process tambah filter (multiple select)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty($_POST['rtgmsid']) && is_array($_POST['rtgmsid'])) {
        $addedCount = 0;
        $skipCount = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($_POST['rtgmsid'] as $rtgmsid) {
            $rtgmsid = (int)$rtgmsid;
            if ($rtgmsid <= 0) continue;
            // Cek sudah ada
            $check = sqlsrv_query($conn, "SELECT id, is_active FROM monitoringstartstopkurang_filter WHERE rtgmsid = $rtgmsid");
            $checkRow = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($check);
            if (!$checkRow) {
                // Insert baru
                $insert = sqlsrv_query($conn, "INSERT INTO monitoringstartstopkurang_filter (rtgmsid, is_active, created_by, created_time) VALUES ($rtgmsid, 1, '$currentUser', '$now')");
                if ($insert) {
                    $addedCount++;
                    sqlsrv_free_stmt($insert);
                }
            } else {
                // Sudah ada — walaupun inactive, anggap sudah ada
                $skipCount++;
            }
        }
        if ($addedCount > 0) {
            $message = "$addedCount filter berhasil ditambahkan.";
            $messageType = 'success';
        } elseif ($skipCount > 0) {
            $message = 'Filter sudah ada, tidak ada data baru.';
            $messageType = 'warning';
        }
    } elseif ($_POST['action'] === 'toggle' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $now = date('Y-m-d H:i:s');
        // Ambil status skrg
        $cur = sqlsrv_query($conn, "SELECT is_active FROM monitoringstartstopkurang_filter WHERE id = $id");
        $curRow = sqlsrv_fetch_array($cur, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($cur);
        if ($curRow) {
            if ($curRow['is_active'] == 1) {
                // Nonaktifkan — catat inactive_by & inactive_time
                $toggle = sqlsrv_query($conn, "UPDATE monitoringstartstopkurang_filter SET is_active = 0, inactive_by = '$currentUser', inactive_time = '$now' WHERE id = $id");
                $msg = 'Filter dinonaktifkan.';
            } else {
                // Aktifkan — catat reactivate_by & reactivate_time
                $toggle = sqlsrv_query($conn, "UPDATE monitoringstartstopkurang_filter SET is_active = 1, reactivate_by = '$currentUser', reactivate_time = '$now' WHERE id = $id");
                $msg = 'Filter diaktifkan kembali.';
            }
            if ($toggle) {
                $message = $msg;
                $messageType = 'success';
                sqlsrv_free_stmt($toggle);
            } else {
                $message = 'Gagal mengubah status filter.';
                $messageType = 'danger';
            }
        }
    }
}

// Ambil semua rtgms dari PostgreSQL (koneksi3) untuk dipilih
$allRtgms = [];
$q = "SELECT rtgmsid, rtgcode, rtgname FROM pdrtgms ORDER BY rtgcode";
$s = $conn3->query($q);
if ($s) {
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        $allRtgms[] = $r;
    }
}

// Ambil filter yg sudah disimpan dari SQL Server (koneksi) — semua termasuk yg nonaktif
$savedFilters = [];
$sf = "SELECT f.id, f.rtgmsid, f.is_active, f.created_by, f.created_time, f.reactivate_by, f.reactivate_time, f.inactive_by, f.inactive_time FROM monitoringstartstopkurang_filter f ORDER BY f.id";
$ss = sqlsrv_query($conn, $sf);
if ($ss) {
    while ($r = sqlsrv_fetch_array($ss, SQLSRV_FETCH_ASSOC)) {
        $savedFilters[] = $r;
    }
    sqlsrv_free_stmt($ss);
}

// Ambil nama rtgms dari PostgreSQL berdasarkan id yg tersimpan
$savedRtgmsIds = array_map(function($f) { return (int)$f['rtgmsid']; }, $savedFilters);
$savedFiltersDetail = [];
if (!empty($savedRtgmsIds)) {
    $placeholders = implode(',', array_fill(0, count($savedRtgmsIds), '?'));
    $detailQuery = "SELECT rtgmsid, rtgcode, rtgname FROM pdrtgms WHERE rtgmsid IN ($placeholders) ORDER BY rtgcode";
    $detailStmt = $conn3->prepare($detailQuery);
    $detailStmt->execute($savedRtgmsIds);
    $detailLookup = [];
    while ($d = $detailStmt->fetch(PDO::FETCH_ASSOC)) {
        $detailLookup[(int)$d['rtgmsid']] = $d;
    }
    foreach ($savedFilters as $f) {
        $rtgmsid = (int)$f['rtgmsid'];
        $savedFiltersDetail[] = [
            'id' => (int)$f['id'],
            'rtgmsid' => $rtgmsid,
            'rtgcode' => $detailLookup[$rtgmsid]['rtgcode'] ?? '',
            'rtgname' => $detailLookup[$rtgmsid]['rtgname'] ?? '',
            'is_active' => (int)$f['is_active'],
            'created_by' => $f['created_by'] ?? '',
            'created_time' => $f['created_time'] ?? '',
            'reactivate_by' => $f['reactivate_by'] ?? '',
            'reactivate_time' => $f['reactivate_time'] ?? '',
            'inactive_by' => $f['inactive_by'] ?? '',
            'inactive_time' => $f['inactive_time'] ?? '',
        ];
    }
}

// Cari id yg aktif aja untuk disabled options
$activeRtgmsIds = array_map(function($f) { return $f['rtgmsid']; }, array_filter($savedFiltersDetail, function($f) { return $f['is_active']; }));
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Pengaturan Filter Routing</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="monitoringstartstopkurang.php">Monitoring Start Stop Kurang</a></li>
                            <li class="breadcrumb-item active">Pengaturan Filter</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form Tambah Filter -->
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                                <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Tambah Filter</h3>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="add">
                                    <div class="form-group">
                                        <label for="rtgmsid">Pilih Routing</label>
                                        <select id="rtgmsid" name="rtgmsid[]" class="form-control select2" style="width: 100%;" multiple>
                                            <?php foreach ($allRtgms as $rtg): ?>
                                                <?php $disabled = in_array((int)$rtg['rtgmsid'], $activeRtgmsIds) ? 'disabled style="color:#999;"' : ''; ?>
                                                <option value="<?= $rtg['rtgmsid'] ?>" <?= $disabled ?>>
                                                    [<?= htmlspecialchars($rtg['rtgmsid']) ?>] <?= htmlspecialchars($rtg['rtgcode'] . ' - ' . $rtg['rtgname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                    <a href="monitoringstartstopkurang.php" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Filter Tersimpan -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i> Filter Tersimpan</h3>
                                <div class="card-tools">
                                    <a href="export_excel_monitoringstartstopkurang_filter_settings.php" target="_blank" class="btn btn-sm btn-light">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($savedFiltersDetail) > 0): ?>
                                <div class="table-responsive">
                                    <table id="filterTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center">
                                                <th>No</th>
                                                <th>RTGMS ID</th>
                                                <th>Kode</th>
                                                <th>Nama Routing</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php $no = 1; foreach ($savedFiltersDetail as $f): ?>
                                            <?php $rowClass = $f['is_active'] ? '' : ' class="table-secondary" style="text-decoration: line-through;"'; ?>
                                            <tr<?= $rowClass ?>>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="text-center"><?= htmlspecialchars($f['rtgmsid']) ?></td>
                                                <td><?= htmlspecialchars($f['rtgcode']) ?></td>
                                                <td><?= htmlspecialchars($f['rtgname']) ?></td>
                                                <td class="text-center">
                                                    <?php if ($f['is_active']): ?>
                                                        <span class="badge badge-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Nonaktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                                        <?php if ($f['is_active']): ?>
                                                            <button type="submit" class="btn btn-warning btn-xs">
                                                                <i class="fas fa-ban"></i> Nonaktifkan
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="btn btn-success btn-xs">
                                                                <i class="fas fa-check"></i> Aktifkan
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle"></i> Belum ada filter routing. Silakan tambah filter baru.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: '-- Pilih Routing --',
            allowClear: true,
            width: '100%'
        });

        $('#filterTable').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            lengthChange: false,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            },
        });
    });
</script>
