<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

include '../koneksi.php';

$userLoggedIn = $_SESSION['UserName'];

// --- Proses update tema ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Theme'])) {
    $newTheme = $_POST['Theme'];
    $updateSql = "UPDATE SMUserMs 
                  SET Theme = ?, UpdDate = GETDATE(), UpdUser = ? 
                  WHERE UserName = ?";
    $params = [$newTheme, $_SESSION['UserName'], $userLoggedIn];
    $stmtUpdate = sqlsrv_query($conn, $updateSql, $params);

    if ($stmtUpdate) {
        $_SESSION['Theme'] = $newTheme;
        $_SESSION['success'] = "Tema berhasil diperbarui!";
        header("Location: /gg_app/pages/profile.php");
        exit;
    } else {
        $_SESSION['error'] = "Gagal memperbarui tema: " . print_r(sqlsrv_errors(), true);
    }
}

// --- Proses update password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Semua field password harus diisi!";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Password baru dan konfirmasi password tidak cocok!";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = "Password baru harus minimal 6 karakter!";
    } else {
        // Verifikasi password lama
        $checkSql = "SELECT UserPassword FROM SMUserMs WHERE UserName = ?";
        $checkParams = [$userLoggedIn];
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            // Gunakan password_verify() untuk memverifikasi password
            if (password_verify($current_password, $row['UserPassword'])) {
                // Hash password baru sebelum disimpan
                $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
                
                // Update password baru
                $updatePasswordSql = "UPDATE SMUserMs 
                                     SET UserPassword = ?, UpdDate = GETDATE(), UpdUser = ?
                                     WHERE UserName = ?";
                $updateParams = [$hashed_new_password, $_SESSION['UserName'], $userLoggedIn];
                $updateStmt = sqlsrv_query($conn, $updatePasswordSql, $updateParams);
                
                if ($updateStmt) {
                    $_SESSION['success'] = "Password berhasil diperbarui!";
                    header("Location: /gg_app/pages/profile.php");
                    exit;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui password: " . print_r(sqlsrv_errors(), true);
                }
            } else {
                $_SESSION['error'] = "Password saat ini salah!";
            }
        } else {
            $_SESSION['error'] = "Terjadi kesalahan saat memverifikasi password!";
        }
    }
}

// --- Ambil data user ---
$sql = "SELECT a.UserId, a.UserName, c.nama_lengkap, b.GroupName, a.UpdDate, a.UpdUser, a.Theme
        FROM dbo.SMUserMs AS a
        LEFT JOIN dbo.SMUserGroup AS b ON a.GroupId = b.GroupId
        LEFT JOIN dbo.m_emp AS c ON a.EmpId = c.id_emp
        WHERE a.UserName = ?
        ORDER BY a.UserName";
$params = [$userLoggedIn];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
}

$userData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$userData) {
    $_SESSION['error'] = "Data pengguna tidak ditemukan!";
    header("Location: /gg_app/index.php");
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';

// Daftar tema AdminLTE (diperluas)
$themes = [
    'primary'   => 'Biru (Primary)',
    'secondary' => 'Abu-abu (Secondary)',
    'success'   => 'Hijau (Success)',
    'danger'    => 'Merah (Danger)',
    'warning'   => 'Kuning (Warning)',
    'info'      => 'Cyan (Info)',
    'dark'      => 'Hitam (Dark)',
    'light'     => 'Putih Abu (Light)',
    'indigo'    => 'Indigo',
    'navy'      => 'Biru Navy',
    'purple'    => 'Ungu',
    'pink'      => 'Pink',
    'teal'      => 'Hijau Tosca (Teal)',
    'orange'    => 'Oranye',
    'olive'     => 'Hijau Olive',
    'lime'      => 'Lime',
    'fuchsia'   => 'Fuchsia',
    'maroon'    => 'Maroon'
];
?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Profil Pengguna</h1>
            </div>
        </div>
        
        <div class="content">
            <div class="container-fluid">
                <!-- Informasi Pengguna -->
                <div class="card mb-4">
                    <div class="card-header bg-<?= htmlspecialchars($_SESSION['Theme'] ?? 'primary') ?> text-white">
                        <h3 class="card-title">Informasi Pengguna</h3>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered">
                            <tr><th>Username</th><td><?= htmlspecialchars($userData['UserName']) ?></td></tr>
                            <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($userData['nama_lengkap'] ?? '-') ?></td></tr>
                            <tr><th>Nama Grup</th><td><?= htmlspecialchars($userData['GroupName']) ?></td></tr>
                            <tr><th>Update Terakhir</th><td><?= ($userData['UpdDate']) ? $userData['UpdDate']->format('Y-m-d H:i:s') : 'N/A' ?></td></tr>
                            <tr><th>Diupdate Oleh</th><td><?= htmlspecialchars($userData['UpdUser']) ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Form Ubah Tema -->
                <div class="card mb-4">
                    <div class="card-header bg-<?= htmlspecialchars($_SESSION['Theme'] ?? 'primary') ?> text-white">
                        <h3 class="card-title">Pengaturan Tema</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group">
                                <label for="Theme">Pilih Tema</label>
                                <select name="Theme" id="Theme" class="form-control select2bs4" style="width:100%;">
                                    <?php foreach ($themes as $key => $label): ?>
                                        <option value="<?= $key ?>" 
                                                <?= ($userData['Theme'] == $key) ? 'selected' : '' ?>
                                                data-color="<?= $key ?>">
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-<?= htmlspecialchars($_SESSION['Theme'] ?? 'primary') ?>">
                                <i class="fas fa-save"></i> Simpan Tema
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Form Ubah Password -->
                <div class="card">
                    <div class="card-header bg-<?= htmlspecialchars($_SESSION['Theme'] ?? 'primary') ?> text-white">
                        <h3 class="card-title">Ubah Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" id="passwordForm">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Password Saat Ini</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Password minimal 6 karakter</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-<?= htmlspecialchars($_SESSION['Theme'] ?? 'primary') ?>">
                                <i class="fas fa-key"></i> Ubah Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>
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
$(function () {
    // Initialize Select2
    function formatTheme (state) {
        if (!state.id) return state.text;
        let color = state.element.getAttribute("data-color");
        let colorClass = "bg-" + color;
        return $('<span class="badge ' + colorClass + ' p-2 mr-2">&nbsp;</span><span>' + state.text + '</span>');
    }

    $('#Theme').select2({
        theme: 'bootstrap4',
        templateResult: formatTheme,
        templateSelection: formatTheme,
        escapeMarkup: function (m) { return m; }
    });

    // Toggle password visibility
    $('.toggle-password').click(function() {
        const target = $(this).data('target');
        const input = $('#' + target);
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Password form validation
    $('#passwordForm').submit(function(e) {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            Swal.fire("Error", "Password baru dan konfirmasi password tidak cocok!", "error");
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            Swal.fire("Error", "Password baru harus minimal 6 karakter!", "error");
            return false;
        }
        
        return true;
    });
});
</script>

<?php if (isset($_SESSION['success'])): ?>
<script>Swal.fire("Berhasil", "<?= $_SESSION['success'] ?>", "success");</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>Swal.fire("Error", "<?= $_SESSION['error'] ?>", "error");</script>
<?php unset($_SESSION['error']); endif; ?>
