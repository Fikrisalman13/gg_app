<?php
// pages/fabric_knowledge/master_kategori_tag.php
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ====== Permission Check ======
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $perm = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $perm = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $perm;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 124);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengakses halaman ini.";
    header('Location: problem_list.php');
    exit;
}

// ====== Fungsi Format Tanggal ======
function formatTanggal($date) {
    if (!$date) return '-';
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y H:i');
    }
    return date('d-m-Y H:i', strtotime($date));
}

// ====== Proses CRUD Kategori ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle kategori
        if (isset($_POST['action_kategori'])) {
            $action = $_POST['action_kategori'];
            $id_kategori = $_POST['id_kategori'] ?? '';
            $nama_kategori = trim($_POST['nama_kategori'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if ($action !== 'hapus') {
                if (empty($nama_kategori)) {
                    throw new Exception("Nama kategori wajib diisi!");
                }

                if (strlen($nama_kategori) > 100) {
                    throw new Exception("Nama kategori terlalu panjang. Maksimal 100 karakter.");
                }

                if (strlen($deskripsi) > 255) {
                    throw new Exception("Deskripsi terlalu panjang. Maksimal 255 karakter.");
                }
            }

            sqlsrv_begin_transaction($conn);

            if ($action === 'tambah') {
                $sql = "INSERT INTO dbo.fab_m_kategori (nama_kategori, deskripsi, created_by, updated_by) 
                        VALUES (?, ?, ?, ?)";
                $params = [$nama_kategori, $deskripsi, $_SESSION['UserName'], $_SESSION['UserName']];
                $success_msg = "Kategori berhasil ditambahkan!";
            } 
            elseif ($action === 'edit' && !empty($id_kategori)) {
                $sql = "UPDATE dbo.fab_m_kategori 
                        SET nama_kategori = ?, deskripsi = ?, updated_by = ?, updated_at = GETDATE() 
                        WHERE id_kategori = ?";
                $params = [$nama_kategori, $deskripsi, $_SESSION['UserName'], $id_kategori];
                $success_msg = "Kategori berhasil diperbarui!";
            }
            elseif ($action === 'hapus' && !empty($id_kategori)) {
                // Cek apakah kategori digunakan di masalah
                $sql_check = "SELECT COUNT(*) as jumlah FROM dbo.fab_m_problem WHERE id_kategori = ?";
                $stmt_check = sqlsrv_query($conn, $sql_check, [$id_kategori]);
                $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
                
                if ($row_check['jumlah'] > 0) {
                    throw new Exception("Kategori tidak dapat dihapus karena masih digunakan dalam " . $row_check['jumlah'] . " masalah.");
                }

                $sql = "DELETE FROM dbo.fab_m_kategori WHERE id_kategori = ?";
                $params = [$id_kategori];
                $success_msg = "Kategori berhasil dihapus!";
            } else {
                throw new Exception("Aksi tidak valid!");
            }

            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Gagal memproses data kategori: " . print_r(sqlsrv_errors(), true));
            }

            sqlsrv_commit($conn);
            $_SESSION['success'] = $success_msg;
        }

        // Handle tag
        if (isset($_POST['action_tag'])) {
            $action = $_POST['action_tag'];
            $id_tag = $_POST['id_tag'] ?? '';
            $nama_tag = trim($_POST['nama_tag'] ?? '');
            $id_kategori = $_POST['id_kategori'] ?? null;

            if ($action !== 'hapus') {
                if (empty($nama_tag)) {
                    throw new Exception("Nama tag wajib diisi!");
                }

                if (strlen($nama_tag) > 100) {
                    throw new Exception("Nama tag terlalu panjang. Maksimal 100 karakter.");
                }
            }

            sqlsrv_begin_transaction($conn);

            if ($action === 'tambah') {
                $sql = "INSERT INTO dbo.fab_m_tag (nama_tag, id_kategori, created_by, updated_by) 
                        VALUES (?, ?, ?, ?)";
                $params = [$nama_tag, $id_kategori, $_SESSION['UserName'], $_SESSION['UserName']];
                $success_msg = "Tag berhasil ditambahkan!";
            } 
            elseif ($action === 'edit' && !empty($id_tag)) {
                $sql = "UPDATE dbo.fab_m_tag 
                        SET nama_tag = ?, id_kategori = ?, updated_by = ?, updated_at = GETDATE() 
                        WHERE id_tag = ?";
                $params = [$nama_tag, $id_kategori, $_SESSION['UserName'], $id_tag];
                $success_msg = "Tag berhasil diperbarui!";
            }
            elseif ($action === 'hapus' && !empty($id_tag)) {
                // Cek apakah tag digunakan di masalah
                $sql_check = "SELECT COUNT(*) as jumlah FROM dbo.fab_m_problem WHERE id_tag = ?";
                $stmt_check = sqlsrv_query($conn, $sql_check, [$id_tag]);
                $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
                
                if ($row_check['jumlah'] > 0) {
                    throw new Exception("Tag tidak dapat dihapus karena masih digunakan dalam " . $row_check['jumlah'] . " masalah.");
                }

                $sql = "DELETE FROM dbo.fab_m_tag WHERE id_tag = ?";
                $params = [$id_tag];
                $success_msg = "Tag berhasil dihapus!";
            } else {
                throw new Exception("Aksi tidak valid!");
            }

            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                throw new Exception("Gagal memproses data tag: " . print_r(sqlsrv_errors(), true));
            }

            sqlsrv_commit($conn);
            $_SESSION['success'] = $success_msg;
        }

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: master_kategori_tag.php');
    exit;
}

// ====== Ambil Data Kategori ======
$kategori = [];
$sql_kategori = "SELECT * FROM dbo.fab_m_kategori ORDER BY nama_kategori";
$stmt_kategori = sqlsrv_query($conn, $sql_kategori);
if ($stmt_kategori) {
    while ($row = sqlsrv_fetch_array($stmt_kategori, SQLSRV_FETCH_ASSOC)) {
        $kategori[] = $row;
    }
}

// ====== Ambil Data Tag ======
$tag = [];
$sql_tag = "SELECT t.*, k.nama_kategori 
             FROM dbo.fab_m_tag t
             LEFT JOIN dbo.fab_m_kategori k ON t.id_kategori = k.id_kategori
             ORDER BY k.nama_kategori, t.nama_tag";
$stmt_tag = sqlsrv_query($conn, $sql_tag);
if ($stmt_tag) {
    while ($row = sqlsrv_fetch_array($stmt_tag, SQLSRV_FETCH_ASSOC)) {
        $tag[] = $row;
    }
}

// ====== Hitung Penggunaan ======
// Hitung penggunaan kategori
$usage_kategori = [];
$sql_usage_kategori = "SELECT id_kategori, COUNT(*) as jumlah FROM dbo.fab_m_problem GROUP BY id_kategori";
$stmt_usage_kategori = sqlsrv_query($conn, $sql_usage_kategori);
if ($stmt_usage_kategori) {
    while ($row = sqlsrv_fetch_array($stmt_usage_kategori, SQLSRV_FETCH_ASSOC)) {
        $usage_kategori[$row['id_kategori']] = $row['jumlah'];
    }
}

// Hitung penggunaan tag
$usage_tag = [];
$sql_usage_tag = "SELECT id_tag, COUNT(*) as jumlah FROM dbo.fab_m_problem WHERE id_tag IS NOT NULL GROUP BY id_tag";
$stmt_usage_tag = sqlsrv_query($conn, $sql_usage_tag);
if ($stmt_usage_tag) {
    while ($row = sqlsrv_fetch_array($stmt_usage_tag, SQLSRV_FETCH_ASSOC)) {
        $usage_tag[$row['id_tag']] = $row['jumlah'];
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

    <style>
        .master-card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        
        .master-card .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .badge-usage {
            font-size: 0.75rem;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.25rem;
        }
        
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        
        .form-delete {
            display: inline;
        }
        
        .btn-delete {
            background: none;
            border: none;
            padding: 0;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Master Data</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="problem_list.php">Knowledge Base</a></li>
                            <li class="breadcrumb-item active">Master Kategori & Tag</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <!-- Notifikasi -->
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> Berhasil!</h5>
                        <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> Error!</h5>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="row">
                    <!-- Master Kategori -->
                    <div class="col-md-6">
                        <div class="card master-card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">
                                    <i class="fas fa-list mr-1"></i> Master Kategori
                                    <span class="badge badge-light ml-1"><?= count($kategori) ?> Data</span>
                                </h3>
                                <?php if ($permissions['CanAdd'] == 1): ?>
                                    <button type="button" class="btn btn-light btn-sm float-right" data-toggle="modal" data-target="#modalKategori" onclick="setKategoriAction('tambah')">
                                        <i class="fas fa-plus"></i> Tambah Kategori
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($kategori)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-list-alt"></i>
                                        <p>Belum ada data kategori</p>
                                        <?php if ($permissions['CanAdd'] == 1): ?>
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalKategori" onclick="setKategoriAction('tambah')">
                                                <i class="fas fa-plus"></i> Tambah Kategori Pertama
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th>Nama Kategori</th>
                                                    <th>Deskripsi</th>
                                                    <th width="15%">Digunakan</th>
                                                    <th width="20%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($kategori as $index => $kat): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($kat['nama_kategori']) ?></strong>
                                                            <?php if ($kat['updated_at']): ?>
                                                                <br><small class="text-muted">Update: <?= formatTanggal($kat['updated_at']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($kat['deskripsi'] ?: '-') ?></td>
                                                        <td>
                                                            <span class="badge badge-info badge-usage">
                                                                <?= $usage_kategori[$kat['id_kategori']] ?? 0 ?> masalah
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($permissions['CanEdit'] == 1): ?>
                                                                <button type="button" class="btn btn-warning btn-action" 
                                                                        data-toggle="modal" data-target="#modalKategori"
                                                                        onclick="editKategori(<?= htmlspecialchars(json_encode($kat)) ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($permissions['CanDelete'] == 1 && !isset($usage_kategori[$kat['id_kategori']])): ?>
                                                                <button type="button" class="btn btn-danger btn-action" 
                                                                        onclick="confirmDeleteKategori(<?= $kat['id_kategori'] ?>, '<?= htmlspecialchars($kat['nama_kategori']) ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Master Tag -->
                    <div class="col-md-6">
                        <div class="card master-card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">
                                    <i class="fas fa-tags mr-1"></i> Master Tag
                                    <span class="badge badge-light ml-1"><?= count($tag) ?> Data</span>
                                </h3>
                                <?php if ($permissions['CanAdd'] == 1): ?>
                                    <button type="button" class="btn btn-light btn-sm float-right" data-toggle="modal" data-target="#modalTag" onclick="setTagAction('tambah')">
                                        <i class="fas fa-plus"></i> Tambah Tag
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($tag)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-tags"></i>
                                        <p>Belum ada data tag</p>
                                        <?php if ($permissions['CanAdd'] == 1): ?>
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalTag" onclick="setTagAction('tambah')">
                                                <i class="fas fa-plus"></i> Tambah Tag Pertama
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th>Nama Tag</th>
                                                    <th>Kategori</th>
                                                    <th width="15%">Digunakan</th>
                                                    <th width="20%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tag as $index => $t): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($t['nama_tag']) ?></strong>
                                                            <?php if ($t['updated_at']): ?>
                                                                <br><small class="text-muted">Update: <?= formatTanggal($t['updated_at']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($t['nama_kategori'] ?? '-') ?></td>
                                                        <td>
                                                            <span class="badge badge-info badge-usage">
                                                                <?= $usage_tag[$t['id_tag']] ?? 0 ?> masalah
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($permissions['CanEdit'] == 1): ?>
                                                                <button type="button" class="btn btn-warning btn-action" 
                                                                        data-toggle="modal" data-target="#modalTag"
                                                                        onclick="editTag(<?= htmlspecialchars(json_encode($t)) ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($permissions['CanDelete'] == 1 && !isset($usage_tag[$t['id_tag']])): ?>
                                                                <button type="button" class="btn btn-danger btn-action" 
                                                                        onclick="confirmDeleteTag(<?= $t['id_tag'] ?>, '<?= htmlspecialchars($t['nama_tag']) ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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

<!-- Modal Kategori -->
<div class="modal fade" id="modalKategori" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formKategori">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKategoriTitle">Tambah Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action_kategori" id="action_kategori" value="tambah">
                    <input type="hidden" name="id_kategori" id="id_kategori">
                    
                    <div class="form-group">
                        <label for="nama_kategori">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" 
                               required maxlength="100" placeholder="Masukkan nama kategori">
                    </div>
                    
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                  rows="3" placeholder="Masukkan deskripsi kategori (opsional)"
                                  maxlength="255"></textarea>
                        <small class="form-text text-muted">Maksimal 255 karakter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tag -->
<div class="modal fade" id="modalTag" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formTag">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTagTitle">Tambah Tag</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action_tag" id="action_tag" value="tambah">
                    <input type="hidden" name="id_tag" id="id_tag">
                    
                    <div class="form-group">
                        <label for="nama_tag">Nama Tag <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_tag" name="nama_tag" 
                               required maxlength="100" placeholder="Masukkan nama tag">
                        <small class="form-text text-muted">Tag digunakan untuk mengelompokkan masalah yang serupa</small>
                    </div>
                    <div class="form-group">
                        <label for="id_kategori_tag">Kategori</label>
                        <select class="form-control" id="id_kategori_tag" name="id_kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori as $kat): ?>
                                <option value="<?= $kat['id_kategori'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Hapus Tersembunyi -->
<form method="POST" id="formDeleteKategori" style="display: none;">
    <input type="hidden" name="action_kategori" value="hapus">
    <input type="hidden" name="id_kategori" id="delete_id_kategori">
</form>

<form method="POST" id="formDeleteTag" style="display: none;">
    <input type="hidden" name="action_tag" value="hapus">
    <input type="hidden" name="id_tag" id="delete_id_tag">
</form>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
// Fungsi untuk Kategori
function setKategoriAction(action) {
    document.getElementById('action_kategori').value = action;
    document.getElementById('modalKategoriTitle').textContent = 
        action === 'tambah' ? 'Tambah Kategori' : 'Edit Kategori';
        
    if (action === 'tambah') {
        document.getElementById('formKategori').reset();
        document.getElementById('id_kategori').value = '';
    }
}

function editKategori(kategori) {
    setKategoriAction('edit');
    document.getElementById('id_kategori').value = kategori.id_kategori;
    document.getElementById('nama_kategori').value = kategori.nama_kategori;
    document.getElementById('deskripsi').value = kategori.deskripsi || '';
}

function confirmDeleteKategori(id, nama) {
    Swal.fire({
        title: 'Hapus Kategori?',
        html: `Anda akan menghapus kategori: <strong>${nama}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_id_kategori').value = id;
            document.getElementById('formDeleteKategori').submit();
        }
    });
}

// Fungsi untuk Tag
function setTagAction(action) {
    document.getElementById('action_tag').value = action;
    document.getElementById('modalTagTitle').textContent = 
        action === 'tambah' ? 'Tambah Tag' : 'Edit Tag';
        
    if (action === 'tambah') {
        document.getElementById('formTag').reset();
        document.getElementById('id_tag').value = '';
        document.getElementById('id_kategori_tag').value = '';
    }
}

function editTag(tag) {
    setTagAction('edit');
    document.getElementById('id_tag').value = tag.id_tag;
    document.getElementById('nama_tag').value = tag.nama_tag;
    document.getElementById('id_kategori_tag').value = tag.id_kategori || '';
}

function confirmDeleteTag(id, nama) {
    Swal.fire({
        title: 'Hapus Tag?',
        html: `Anda akan menghapus tag: <strong>${nama}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_id_tag').value = id;
            document.getElementById('formDeleteTag').submit();
        }
    });
}

// Validasi form (hanya untuk tambah dan edit, bukan hapus)
document.getElementById('formKategori').addEventListener('submit', function(e) {
    const action = document.getElementById('action_kategori').value;
    if (action !== 'hapus') {
        const nama = document.getElementById('nama_kategori').value.trim();
        if (!nama) {
            e.preventDefault();
            Swal.fire('Error', 'Nama kategori wajib diisi!', 'error');
            return false;
        }
    }
    return true;
});

document.getElementById('formTag').addEventListener('submit', function(e) {
    const action = document.getElementById('action_tag').value;
    if (action !== 'hapus') {
        const nama = document.getElementById('nama_tag').value.trim();
        if (!nama) {
            e.preventDefault();
            Swal.fire('Error', 'Nama tag wajib diisi!', 'error');
            return false;
        }
    }
    return true;
});

// Auto close modal setelah submit berhasil
$(document).ready(function() {
    <?php if (!empty($_SESSION['success'])): ?>
        $('#modalKategori').modal('hide');
        $('#modalTag').modal('hide');
    <?php endif; ?>
});
</script>

