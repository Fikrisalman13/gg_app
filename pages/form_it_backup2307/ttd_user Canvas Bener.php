<?php
// Start session and output buffering at the very beginning
session_start();
ob_start();

// Include dependencies (use __DIR__ to locate files reliably)
$rootDir = realpath(__DIR__ . '/..');
if ($rootDir === false) $rootDir = __DIR__ . '/..';
// koneksi.php is in project root
require_once $rootDir . '/../koneksi.php';
// includes are in project root/includes
require_once $rootDir . '/../includes/header.php';
require_once $rootDir . '/../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Untuk simpel, gunakan menu yang sama dengan User Manager (hanya admin yang sama yang bisa akses)
define('TTD_USER_MANAGER_MENU_ID', 2);

function checkPermissionsTtd($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    return $permissions;
}

function getAllUsersSimple($conn) {
    // Return active employees (m_emp) and include linked UserId from SMUserMs if any
    $sql = "SELECT m.id_emp AS EmpId,
                   ISNULL(m.nama_lengkap, m.nik) AS FullName,
                   (SELECT TOP 1 u.UserId FROM dbo.SMUserMs u WHERE u.EmpId = m.id_emp) AS LinkedUserId
            FROM dbo.m_emp m
            WHERE ISNULL(m.aktif,0) = 1
            ORDER BY FullName";

    $stmt = sqlsrv_query($conn, $sql);
    $users = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $users[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $users;
}

function getAllTtdTemplates($conn) {
    $sql = "SELECT t.Id, t.UserId, t.UserName, t.GroupRole, t.SignaturePath, t.IsActive, t.CreatedAt, t.UpdatedAt,
                   u.UserName AS LoginName,
                   ISNULL(e.nama_lengkap, u.UserName) AS FullName
            FROM dbo.User_TTD_Template t
            LEFT JOIN dbo.SMUserMs u ON t.UserId = u.UserId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            ORDER BY FullName, t.GroupRole";

    $stmt = sqlsrv_query($conn, $sql);
    $rows = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $rows;
}

// Check permissions
$permissions = checkPermissionsTtd($conn, $_SESSION['GroupId'], TTD_USER_MANAGER_MENU_ID);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Handle form actions (add/delete/toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $permissions['CanAdd'] == 1) {
        // user_id now contains EmpId from m_emp
        $empId       = intval($_POST['user_id'] ?? 0);
        $groupRole   = trim($_POST['group_role'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $canvasData  = $_POST['canvas_data'] ?? '';

        if ($empId <= 0 || $groupRole === '') {
            $_SESSION['error'] = 'User dan role wajib diisi.';
            header('Location: ttd_user.php');
            exit;
        }

        // Resolve linked UserId from SMUserMs if exists, otherwise use Emp data
        $userId = 0;
        $userName = 'User';
        $sqlEmp = "SELECT id_emp, nama_lengkap FROM dbo.m_emp WHERE id_emp = ?";
        $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$empId]);
        if ($stmtEmp && $empRow = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
            // Always prefer and save the employee full name
            $userName = $empRow['nama_lengkap'] ?: $empRow['id_emp'];
        }
        if ($stmtEmp) sqlsrv_free_stmt($stmtEmp);

        // Check if there's a linked user account and preserve its UserId, but do NOT overwrite full name
        $sqlUser = "SELECT TOP 1 UserId, UserName FROM dbo.SMUserMs WHERE EmpId = ?";
        $stmtUser = sqlsrv_query($conn, $sqlUser, [$empId]);
        if ($stmtUser && $rowU = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
            $userId = (int)$rowU['UserId'];
            // linked login name is available in $rowU['UserName'] if needed, but we keep $userName as full name
        }
        if ($stmtUser) sqlsrv_free_stmt($stmtUser);

        // Folder upload (project root '/gg_app/uploads')
        $uploadBase = realpath($rootDir . '/../uploads');
        if ($uploadBase === false) {
            $tryDir = $rootDir . '/../uploads';
            if (!is_dir($tryDir)) {
                @mkdir($tryDir, 0777, true);
            }
            $uploadBase = realpath($tryDir);
        }

        if ($uploadBase === false) {
            $_SESSION['error'] = 'Folder upload tidak tersedia.';
            header('Location: ttd_user.php');
            exit;
        }

        $ttdDir = $uploadBase . DIRECTORY_SEPARATOR . 'ttd';
        if (!is_dir($ttdDir)) {
            @mkdir($ttdDir, 0777, true);
        }

        if (!is_dir($ttdDir) || !is_writable($ttdDir)) {
            $_SESSION['error'] = 'Folder ttd tidak bisa diakses/tulis.';
            header('Location: ttd_user.php');
            exit;
        }

        $imgPathRel = '';

        // Prioritas pertama: canvas_data
        if (!empty($canvasData) && strpos($canvasData, 'data:image') === 0) {
            if (preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $canvasData, $m)) {
                $ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                $data = base64_decode($m[2]);
                if ($data === false) {
                    $_SESSION['error'] = 'Data canvas tidak valid.';
                    header('Location: ttd_user.php');
                    exit;
                }

                $fileName = 'ttd_tpl_' . $userId . '_' . preg_replace('/[^a-z0-9_]+/i', '_', $groupRole) . '_' . date('YmdHis') . '.' . $ext;
                $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

                if (file_put_contents($fullPath, $data) === false) {
                    $_SESSION['error'] = 'Gagal menyimpan file canvas.';
                    header('Location: ttd_user.php');
                    exit;
                }

                $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
            }
        }

        // Jika tidak ada canvas, pakai upload file
        if ($imgPathRel === '' && isset($_FILES['ttd_file']) && $_FILES['ttd_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['ttd_file']['tmp_name'];
            $name    = $_FILES['ttd_file']['name'];
            $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $_SESSION['error'] = 'Format file harus PNG/JPG.';
                header('Location: ttd_user.php');
                exit;
            }

            $fileName = 'ttd_tpl_' . $userId . '_' . preg_replace('/[^a-z0-9_]+/i', '_', $groupRole) . '_' . date('YmdHis') . '.' . $ext;
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($tmpName, $fullPath)) {
                $_SESSION['error'] = 'Gagal menyimpan file upload.';
                header('Location: ttd_user.php');
                exit;
            }

            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
        } elseif ($imgPathRel === '') {
            $_SESSION['error'] = 'Gambar tanda tangan (canvas atau upload) wajib diisi.';
            header('Location: ttd_user.php');
            exit;
        }

        $sqlIns = "INSERT INTO User_TTD_Template (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt)
                VALUES (?, ?, ?, ?, ?, GETDATE())";
        $stmtIns = sqlsrv_query($conn, $sqlIns, [$userId ? $userId : null, $userName, $groupRole, $imgPathRel, $isActive]);

        if ($stmtIns === false) {
            $_SESSION['error'] = 'Gagal menyimpan data TTD.';
        } else {
            $_SESSION['success'] = 'Data TTD berhasil ditambahkan.';
        }

        header('Location: ttd_user.php');
        exit;
    }

  if ($action === 'delete' && $permissions['CanDelete'] == 1) {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        // First, get the signature path before deleting
        $sqlGet = "SELECT SignaturePath FROM User_TTD_Template WHERE Id = ?";
        $stmtGet = sqlsrv_query($conn, $sqlGet, [$id]);
        
        if ($stmtGet === false) {
            $_SESSION['error'] = 'Gagal mengambil data TTD: ' . print_r(sqlsrv_errors(), true);
            header('Location: ttd_user.php');
            exit;
        }

        $row = sqlsrv_fetch_array($stmtGet, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtGet);
        
        if ($row && !empty($row['SignaturePath'])) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $row['SignaturePath'];
            
            // Delete the file if it exists
            if (file_exists($filePath)) {
                if (!@unlink($filePath)) {
                    $_SESSION['error'] = 'Gagal menghapus file tanda tangan.';
                    header('Location: ttd_user.php');
                    exit;
                }
            }
        }
        
        // Then delete the database record
        $sqlDel = "DELETE FROM User_TTD_Template WHERE Id = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDel, [$id]);

        if ($stmtDel === false) {
            $_SESSION['error'] = 'Gagal menghapus data TTD: ' . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['success'] = 'Data TTD dan file tanda tangan berhasil dihapus.';
        }
    }
    header('Location: ttd_user.php');
    exit;
}

    if ($action === 'toggle' && $permissions['CanEdit'] == 1) {
        $id      = intval($_POST['id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            $sqlUp = "UPDATE User_TTD_Template SET IsActive = ?, UpdatedAt = GETDATE() WHERE Id = ?";
            $stmtUp = sqlsrv_query($conn, $sqlUp, [$isActive, $id]);

            if ($stmtUp === false) {
                $_SESSION['error'] = 'Gagal mengubah status aktif.';
            } else {
                $_SESSION['success'] = 'Status aktif berhasil diubah.';
            }
        }
        header('Location: ttd_user.php');
        exit;
    }
}

// Data untuk tampilan
$users       = getAllUsersSimple($conn);
$ttdTemplates = getAllTtdTemplates($conn);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTD User Manajer</title>

    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    <!-- Select2 (local fallback used elsewhere in project) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <style>
        .table-responsive { min-height: 300px; }
        
        .ttd-preview { max-height: 60px; }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">TTD User Manajer</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">TTD User Manajer</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Daftar Template TTD User</h3>
                    <?php if ($permissions['CanAdd'] == 1): ?>
                        <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#modalAddTtd">
                            <i class="fas fa-plus"></i> Tambah TTD User
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ttdTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Preview TTD</th>
                                    <th>Aktif</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ttdTemplates)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada data TTD</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ttdTemplates as $index => $row): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <?= htmlspecialchars($row['FullName'] ?? $row['UserName']) ?>
                                                <br><small class="text-muted">(Login: <?= htmlspecialchars($row['LoginName'] ?? '-') ?>)</small>
                                            </td>
                                            <td><?= htmlspecialchars($row['GroupRole']) ?></td>
                                            <td>
                                                <?php if (!empty($row['SignaturePath'])): ?>
                                                    <img src="<?= htmlspecialchars($row['SignaturePath']) ?>" class="ttd-preview" alt="TTD">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($permissions['CanEdit'] == 1): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= (int)$row['Id'] ?>">
                                                        <input type="hidden" name="is_active" value="<?= $row['IsActive'] ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-sm <?= $row['IsActive'] ? 'btn-success' : 'btn-secondary' ?>">
                                                            <?= $row['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge <?= $row['IsActive'] ? 'badge-success' : 'badge-secondary' ?>">
                                                        <?= $row['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $row['CreatedAt'] ? $row['CreatedAt']->format('Y-m-d H:i:s') : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($permissions['CanDelete'] == 1): ?>
                                                    <form method="post" class="d-inline form-delete-ttd">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$row['Id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah TTD -->
<div class="modal fade" id="modalAddTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="formAddTtd">
        <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
          <h5 class="modal-title">Tambah Template TTD User</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="canvas_data" id="canvasDataManager" value="">
                    <div class="form-group">
                        <label>User (Pilih Karyawan Aktif)</label>
                        <select name="user_id" class="form-control select2" data-placeholder="-- Pilih Karyawan --" style="width:100%;" required>
                            <option value="">-- Pilih Karyawan --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['EmpId'] ?>"><?= htmlspecialchars($u['FullName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
          <div class="form-group">
            <label>Role TTD</label>
                        <select name="group_role" class="form-control" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="Pemohon">Pemohon</option>
                            <option value="Atasan Pemohon">Atasan Pemohon</option>
                            <option value="Petugas IT">Petugas IT</option>
                            <option value="Petugas CCTV">Petugas CCTV</option>
                            <option value="Kabag IT">Kabag IT</option>
                            <option value="Kadept IT">Kadept IT</option>
                        </select>
          </div>
          <div class="form-group">
            <label><b>Gambar di Canvas</b></label>
            <div style="border:1px solid #ccc; display:inline-block;">
              <canvas id="ttdCanvasManager" width="400" height="150" style="background:#fff;cursor:crosshair;"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvasManager">Bersihkan Canvas</button>
          </div>
          <div class="form-group">
            <label>Atau Upload File TTD (PNG/JPG)</label>
            <input type="file" name="ttd_file" class="form-control" accept="image/png,image/jpeg">
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="chkIsActive" name="is_active" value="1" checked>
            <label class="form-check-label" for="chkIsActive">Aktif</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success" id="btnSimpanTtdManager">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<!-- Select2 JS (project includes Select2 assets) -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script>
$(function() {
    $('#ttdTable').DataTable({
        responsive: true,
        language: {
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data yang ditemukan",
            info: "Menampilkan halaman _PAGE_ dari _PAGES_",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sukses!',
        text: "<?= $_SESSION['success'] ?>",
        timer: 3000,
        showConfirmButton: false
    });
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: "<?= $_SESSION['error'] ?>",
        timer: 3000,
        showConfirmButton: false
    });
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    $('.form-delete-ttd').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus data TTD?',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Canvas untuk TTD User Manajer (menggunakan logika dari sign_document.php)
    var canvas = document.getElementById('ttdCanvasManager');
    if (canvas) {
        var ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Penyesuaian resolusi canvas untuk tampilan yang tajam
        function resizeCanvas() {
            var container = canvas.parentElement;
            var width = 400;
            var height = 150;

            // dukungan devicePixelRatio untuk ketajaman di layar high-DPI
            var dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.scale(dpr, dpr);

            // Fill background putih
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        resizeCanvas();

        // Drawing state
        var drawing = false;
        var lastX = 0, lastY = 0;
        var lastTime = 0;
        var lastPressure = 0.5;

        function getCoords(e) {
            var rect = canvas.getBoundingClientRect();
            if (e.touches && e.touches.length) {
                return [e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top];
            } else if (e.clientX !== undefined) {
                return [e.clientX - rect.left, e.clientY - rect.top];
            } else if (e.changedTouches && e.changedTouches.length) {
                return [e.changedTouches[0].clientX - rect.left, e.changedTouches[0].clientY - rect.top];
            }
            return [0,0];
        }

        function start(e) {
            e.preventDefault();
            drawing = true;
            var coords = getCoords(e);
            lastX = coords[0];
            lastY = coords[1];
            lastTime = Date.now();
            lastPressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : 0.5;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }

        function draw(e) {
            if (!drawing) return;
            e.preventDefault();

            var coords = getCoords(e);
            var x = coords[0];
            var y = coords[1];
            var now = Date.now();
            var elapsed = Math.max(1, now - lastTime);
            lastTime = now;

            var pressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : lastPressure;
            var dist = Math.hypot(x - lastX, y - lastY);
            var speed = dist / elapsed;
            var dynamicWidth = Math.max(1, 5 - speed * 2) * pressure;

            ctx.lineWidth = dynamicWidth;
            ctx.strokeStyle = "#000";
            ctx.lineTo(x, y);
            ctx.stroke();

            lastX = x;
            lastY = y;
            lastPressure = pressure;
        }

        function stop(e) {
            if (!drawing) return;
            e.preventDefault();
            drawing = false;
            ctx.closePath();
        }

        // Event listeners: gunakan pointer events (kompatibel mouse/touch/stylus)
        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
        canvas.addEventListener('pointerleave', stop);

        // Prevent scrolling when touching canvas (mobile)
        canvas.addEventListener('touchstart', function(ev){ ev.preventDefault(); }, { passive:false });

        $('#btnClearCanvasManager').on('click', function(){
            var dpr = window.devicePixelRatio || 1;
            ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);
            $('#canvasDataManager').val('');
        });

        $('#formAddTtd').on('submit', function(){
            var dataUrl = canvas.toDataURL('image/png');
            $('#canvasDataManager').val(dataUrl);
        });
    }
});

    // Initialize Select2 for employee selector and ensure modal focus/click works
    $(function(){
        if ($.fn.select2) {
            $('.select2').each(function(){
                var $el = $(this);
                var placeholderText = $el.data('placeholder') || '-- Pilih --';
                var $modalParent = $el.closest('.modal');
                var parent = $modalParent.length ? $modalParent : $(document.body);

                $el.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    allowClear: true,
                    placeholder: placeholderText,
                    dropdownParent: parent
                });
            });

            // When modal opens, open select2 after a short delay so modal animation/backdrop finished
            $('#modalAddTtd').on('shown.bs.modal', function () {
                var sel = $(this).find('.select2');
                if (sel.length) {
                    setTimeout(function(){
                        try { sel.select2('open'); } catch(e) { /* ignore */ }
                        var s = document.querySelector('.select2-container--open .select2-search__field');
                        if (s) s.focus();
                    }, 200);
                }
            });
        }
    });
</script>
</body>
</html>

<?php require_once $rootDir . '/../includes/footer.php'; ?>
