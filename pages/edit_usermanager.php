<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 3. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 4. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../includes/header.php';
include '../includes/sidebar.php';
// Import konstanta dan permission
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 5. VALIDASI IZIN AKSES
// ===================================================
requireEdit($conn, MENU_USER_MANAGER);

// ===================================================
// 6. MENDAPATKAN DATA
// ===================================================
// Mendapatkan data ID User dari URL
$userId = $_GET['id'] ?? null;

if (!$userId) {
    $_SESSION['error'] = "ID User tidak valid!";
    header("Location: usermanager.php");
    exit();
}
// Mendapatkan data User yang akan diedit
$userSql = "
    SELECT 
        a.UserId, a.UserName, a.EmpId, a.GroupId, c.nama_lengkap, b.GroupName
    FROM dbo.SMUserMs AS a
    LEFT JOIN dbo.SMUserGroup AS b ON a.GroupId = b.GroupId
    LEFT JOIN dbo.m_emp AS c ON a.EmpId = c.id_emp
    WHERE a.UserId = ?
";
$userStmt = sqlsrv_query($conn, $userSql, array($userId));
if ($userStmt === false) {
    error_log("Failed to fetch user: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Gagal mengambil data user.";
    header("Location: usermanager.php");
    exit;
}
$userData = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($userStmt);

if (!$userData) {
    $_SESSION['error'] = "User tidak ditemukan!";
    header("Location: usermanager.php");
    exit;
}
// Mendapatkan Data Dropdown (employee & groups)
$empList = [];
$empSql = "SELECT id_emp, nama_lengkap FROM dbo.m_emp ORDER BY nama_lengkap";
$empStmt = sqlsrv_query($conn, $empSql);
if ($empStmt !== false) {
    while ($row = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
        $empList[] = $row;
    }
    sqlsrv_free_stmt($empStmt);
} else {
    error_log("Failed to fetch employees: " . print_r(sqlsrv_errors(), true));
}

$groupList = [];
$groupSql = "SELECT GroupId, GroupName FROM dbo.SMUserGroup ORDER BY GroupName";
$groupStmt = sqlsrv_query($conn, $groupSql);
if ($groupStmt !== false) {
    while ($row = sqlsrv_fetch_array($groupStmt, SQLSRV_FETCH_ASSOC)) {
        $groupList[] = $row;
    }
    sqlsrv_free_stmt($groupStmt);
} else {
    error_log("Failed to fetch groups: " . print_r(sqlsrv_errors(), true));
}

// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA USER
// ===================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {    
    // Ambil data dari form
    $userName = trim($_POST['userName']);
    $password = trim($_POST['password']);
    $empId = $_POST['empId'];
    $groupId = $_POST['groupId'];
    $updUser = $_SESSION['UserName'];

    // Validasi input wajib
    if (empty($userName) || empty($empId) || empty($groupId)) {
        $_SESSION['error'] = "Username, Employee, and User Group are required.!";
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }

    // --- CEK DUPLIKASI USERNAME (kecuali user yang sedang diedit) ---
    $checkUserSql = "SELECT COUNT(*) AS total FROM dbo.SMUserMs WHERE UserName = ? AND UserId != ?";
    $checkUserStmt = sqlsrv_query($conn, $checkUserSql, array($userName, $userId));

    if ($checkUserStmt === false) {
        $_SESSION['error'] = "Error while checking username: " . print_r(sqlsrv_errors(), true);
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }

    $userRow = sqlsrv_fetch_array($checkUserStmt, SQLSRV_FETCH_ASSOC);
    if ($userRow && $userRow['total'] > 0) {
        $_SESSION['error'] = "Username already in use!";
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }

    // --- CEK DUPLIKASI EMPLOYEE ID (kecuali user yang sedang diedit) ---
    $checkEmpSql = "SELECT COUNT(*) AS total FROM dbo.SMUserMs WHERE EmpId = ? AND UserId != ?";
    $checkEmpStmt = sqlsrv_query($conn, $checkEmpSql, array($empId, $userId));

    if ($checkEmpStmt === false) {
        $_SESSION['error'] = "Error while checking Employee ID: " . print_r(sqlsrv_errors(), true);
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }

    $empRow = sqlsrv_fetch_array($checkEmpStmt, SQLSRV_FETCH_ASSOC);
    if ($empRow && $empRow['total'] > 0) {
        $_SESSION['error'] = "Employee ID already has another account!";
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }

    // --- PERSIAPAN QUERY UPDATE ---
    // Jika password diisi, update termasuk password
    if (!empty($password)) {
        // Validasi panjang password
        if (strlen($password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters!";
            header("Location: edit_usermanager.php?id=" . $userId);
            exit();
        }
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $updateSql = "UPDATE dbo.SMUserMs 
                     SET UserName = ?, UserPassword = ?, EmpId = ?, GroupId = ?, 
                         UpdDate = GETDATE(), UpdUser = ?
                     WHERE UserId = ?";
        $updateParams = array($userName, $hashedPassword, $empId, $groupId, $updUser, $userId);
    } else {
        // Jika password tidak diisi, jangan update password
        $updateSql = "UPDATE dbo.SMUserMs 
                     SET UserName = ?, EmpId = ?, GroupId = ?, 
                         UpdDate = GETDATE(), UpdUser = ?
                     WHERE UserId = ?";
        $updateParams = array($userName, $empId, $groupId, $updUser, $userId);
    }

    // --- EKSEKUSI UPDATE ---
    $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

    if ($updateStmt) {
        $_SESSION['success'] = "User successfully updated!";
        header("Location: usermanager.php");
        exit();
    } else {
        $errors = sqlsrv_errors();
        $_SESSION['error'] = "Failed to update user: " . print_r($errors, true);
        
        // Log error untuk debugging
        error_log("SQL Error: " . print_r($errors, true));
        error_log("SQL Query: " . $updateSql);
        error_log("Parameters: " . print_r($updateParams, true));
        
        header("Location: edit_usermanager.php?id=" . $userId);
        exit();
    }
}

?>
<!-- ===================================================
    8. HTML: STRUKTUR HALAMAN
======================================================= -->
<!-- CONTENT WRAPPER -->
<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit User</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="usermanager.php">User Manager</a></li>
                        <li class="breadcrumb-item active">Edit User</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- ALERT MESSAGES -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-check"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <i class="icon fas fa-ban"></i>
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- EDIT USER FORM -->
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit mr-2"></i>
                        Form Edit User - <?= htmlspecialchars($userData['UserName']) ?>
                    </h3>
                </div>

                <div class="card-body">
                    <form action="" method="POST" id="editUserForm" autocomplete="off">
                        <input type="hidden" name="userId" value="<?= $userId ?>">
                        
                        <!-- USERNAME FIELD -->
                        <div class="form-group">
                            <label for="userName" class="font-weight-bold">Username *</label>
                            <input type="text" 
                                   name="userName" 
                                   id="userName" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($userData['UserName']) ?>" 
                                   required
                                   autocomplete="username">
                            <small class="form-text text-muted">
                                Username must be unique and must not contain spaces.
                            </small>
                        </div>

                        <!-- PASSWORD FIELD -->
                        <div class="form-group">
                            <label for="password" class="font-weight-bold">Password</label>
                            <div class="input-group">
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       class="form-control" 
                                       placeholder="Leave blank if you don't want to change the password."
                                       autocomplete="new-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="password-note">
                                <i class="fas fa-info-circle mr-1"></i>
                                Minimum 6 characters. Leave blank if you don't want to change the password.
                            </small>
                        </div>

                        <!-- EMPLOYEE SELECTION -->
                        <div class="form-group">
                            <label for="empId" class="font-weight-bold">Employee *</label>
                            <select name="empId" id="empId" class="form-control select2bs4" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($empList as $emp): 
                                    $selected = ($emp['id_emp'] == $userData['EmpId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($emp['id_emp']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($emp['nama_lengkap']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- USER GROUP SELECTION -->
                        <div class="form-group">
                            <label for="groupId" class="font-weight-bold">User Group *</label>
                            <select name="groupId" id="groupId" class="form-control select2bs4" required>
                                <option value="">-- Select User Group --</option>
                                <?php foreach ($groupList as $g): 
                                    $selected = ($g['GroupId'] == $userData['GroupId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($g['GroupId']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($g['GroupName']) ?>
                                    </option>
                                <?php endforeach; ?>                                
                            </select>
                        </div>

                        <!-- ACTION BUTTONS -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Save
                            </button>
                            <a href="usermanager.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>

</div>
<!-- ===================================================
    9. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>

<!-- ===================================================
    10. JAVASCRIPT CUSTOM
======================================================= -->
<script>
$(document).ready(function() {
    
    // --- INITIALIZE SELECT2 WITH BOOTSTRAP 4 THEME ---
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: "Select an option",
        allowClear: true
    });

    // --- TOGGLE PASSWORD VISIBILITY ---
    $('#togglePassword').click(function() {
        const passwordField = $('#password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // --- FORM VALIDATION ---
    $('#editUserForm').on('submit', function(event) {
        const password = $('#password').val();
        const userName = $('#userName').val().trim();
        
        // Validasi username tidak kosong
        if (!userName) {
            alert('Username cannot be empty!');
            event.preventDefault();
            return false;
        }
        
        // Validasi panjang password jika diisi
        if (password.length > 0 && password.length < 6) {
            alert('Password must be at least 6 characters!');
            event.preventDefault();
            return false;
        }
        
        // Validasi pilihan employee dan group
        if ($('#empId').val() === '' || $('#groupId').val() === '') {
            alert('Employee and User Group must be selected!');
            event.preventDefault();
            return false;
        }
        
        return true;
    });

    // --- AUTO-HIDE ALERTS AFTER 5 SECONDS ---
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});
</script>