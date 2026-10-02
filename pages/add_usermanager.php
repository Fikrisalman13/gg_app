<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
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
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../includes/header.php';
include '../includes/sidebar.php';
// Import konstanta dan permission
include '../includes/menu_constants.php';
include '../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireAdd($conn, MENU_USER_MANAGER);

// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $userName = trim($_POST['userName']);
    $password = trim($_POST['password']);
    $empId    = $_POST['empId'] ?? null;
    $groupId  = $_POST['groupId'] ?? null;
    $updUser  = $_SESSION['UserName'];

    if ($userName && $password && $empId && $groupId) {

        // Validasi Cek Username
        $sqlCheck = "SELECT COUNT(*) AS total FROM SMUserMs WHERE UserName = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$userName]);
        $exists = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

        if ($exists > 0) {
            $_SESSION['error'] = "Username sudah digunakan!";
            header("Location: usermanager.php");
            exit;
        }

        // Validasi Cek Employee ID
        $sqlEmp = "SELECT COUNT(*) AS total FROM SMUserMs WHERE EmpId = ?";
        $stmtEmp = sqlsrv_query($conn, $sqlEmp, [$empId]);
        $existsEmp = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

        if ($existsEmp > 0) {
            $_SESSION['error'] = "Employee ID sudah memiliki akun!";
            header("Location: usermanager.php");
            exit;
        }

        // ---- Hash Password ----
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // ---- Insert Aman ----
        $sqlInsert = "
            INSERT INTO SMUserMs (UserName, UserPassword, EmpId, GroupId, UpdDate, UpdUser)
            VALUES (?, ?, ?, ?, GETDATE(), ?)
        ";
        $params = [$userName, $hashedPassword, $empId, $groupId, $updUser];

        if (sqlsrv_query($conn, $sqlInsert, $params)) {
            $_SESSION['success'] = "User berhasil ditambahkan!";
            header("Location: usermanager.php");
            exit;
        } else {
            $_SESSION['error'] = "Gagal menambahkan user: " . print_r(sqlsrv_errors(), true);
        }

    } else {
        $_SESSION['error'] = "Semua field wajib diisi!";
    }
}
// ===================================================
// 8. MENDAPATKAN DATA
// ===================================================
// Mendapatkan Data Dropdown (employee & groups)
$empList = [];
$empSql = "
    SELECT id_emp, nama_lengkap 
    FROM dbo.m_emp 
    WHERE ISNULL(aktif, 0) = 1 
      AND id_emp NOT IN (SELECT EmpId FROM dbo.SMUserMs WHERE EmpId IS NOT NULL)
    ORDER BY nama_lengkap
";
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

?>
<!-- ===================================================
    9. HTML: STRUKTUR HALAMAN
======================================================= -->
<!-- CONTENT WRAPPER -->
<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Add User</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="usermanager.php">User</a></li>
                        <li class="breadcrumb-item active">Add User</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <section class="content">
        <div class="container-fluid">

           <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">Form Add User</h3>
                </div>

                <div class="card-body">

                    <form action="" method="POST" autocomplete="off">

                        <!-- USERNAME -->
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="userName" class="form-control" required>
                        </div>

                        <!-- PASSWORD -->
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" required>
                                <div class="input-group-append">
                                    <button type="button" id="togglePassword" class="btn btn-outline-secondary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- EMPLOYEE -->
                        <div class="form-group">
                            <label>Employee</label>
                            <select name="empId" class="form-control select2bs4" required>
                                <option value=""> Pilih Employee </option>
                                <?php foreach ($empList as $emp): 
                                    $selected = ($emp['id_emp'] == $userData['EmpId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($emp['id_emp']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($emp['nama_lengkap']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- USER GROUP -->
                        <div class="form-group">
                            <label>User Group</label>
                            <select name="groupId" class="form-control select2bs4" required>
                                <option value=""> Pilih User Group </option>
                                <?php foreach ($groupList as $g): 
                                    $selected = ($g['GroupId'] == $userData['GroupId']) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($g['GroupId']) ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($g['GroupName']) ?>
                                    </option>
                                <?php endforeach; ?>   
                            </select>
                        </div>

                        <!-- BUTTON -->
                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                            <i class="fas fa-save"></i> Save
                        </button>

                        <a href="usermanager.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>

                    </form>
                </div>
            </div>

        </div>
    </section>

</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../includes/footer.php'; ?>

<!-- ===================================================
    12. JAVASCRIPT CUSTOM
======================================================= -->
<script>
$(function () {

    // --- Select2 Bootstrap 4 ---
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // --- Toggle Password ---
    $('#togglePassword').on('click', function () {
        const field = $('#password');
        const icon = $(this).find('i');

        if (field.attr('type') === 'password') {
            field.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            field.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>