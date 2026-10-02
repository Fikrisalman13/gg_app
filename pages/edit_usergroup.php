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
requireEdit($conn, MENU_USER_GROUP);

// ===================================================
// 6. MENDAPATKAN DATA
// ===================================================
// Mendapatkan data Group ID dari URL
$groupId = $_GET['id'] ?? null;

if (!$groupId) {
    $_SESSION['error'] = "Invalid Group ID!";
    header("Location: usergroup.php");
    exit();
}
// Mendapatkan data User Group yang akan diedit
$groupSql = "
    SELECT 
        GroupId,
        GroupName,
        GroupDesc
    FROM dbo.SMUserGroup
    WHERE GroupId = ?
";

$groupStmt = sqlsrv_query($conn, $groupSql, array($groupId));

if ($groupStmt === false) {
    error_log("Failed to fetch group data: " . print_r(sqlsrv_errors(), true));
    $_SESSION['error'] = "Failed to retrieve user group data.";
    header("Location: usergroup.php");
    exit;
}

$groupData = sqlsrv_fetch_array($groupStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($groupStmt);

if (!$groupData) {
    $_SESSION['error'] = "User group not found!";
    header("Location: usergroup.php");
    exit;
}
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ===================================================
// 7. PROSES UPDATE DATA USER GROUP
// ===================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $groupName = trim($_POST['groupName']);
    $groupDesc = trim($_POST['groupDesc']);
    $updUser = $_SESSION['UserName'] ?? '';

    // --- PERSIAPAN QUERY UPDATE ---
    if (!empty($groupName)) {
        $updateSql = "UPDATE dbo.SMUserGroup SET GroupName = ?, GroupDesc = ?, UpdDate = GETDATE(), UpdUser = ? WHERE GroupId = ?";
        $params = array($groupName, $groupDesc, $updUser, $groupId);

         // --- EKSEKUSI UPDATE ---
        $updateStmt = sqlsrv_query($conn, $updateSql, $params);

        if ($updateStmt) {
            $_SESSION['success'] = "User Group successfully updated.";
            header("Location: usergroup.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update User Group: " . print_r(sqlsrv_errors(), true);
        }
        } else {
            $_SESSION['error'] = "Please fill in all required fields!";
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
                    <h1 class="m-0">Edit User Group</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="usergroup.php">User Group Manager</a></li>
                        <li class="breadcrumb-item active">Edit User Group</li>
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
                        Form Edit User Group
                    </h3>
            </div>
               
            <div class="card-body">
                <form action="" method="POST">

                        <!-- GROUP NAME FIELD -->
                        <div class="form-group">
                            <label for="groupName">Group Name</label>
                            <input type="text" name="groupName" id="groupName" class="form-control" value="<?= htmlspecialchars($groupData['GroupName']) ?>" required>
                        </div>

                        <!-- GROUP DESCRIPTION -->
                        <div class="form-group">
                            <label for="groupDesc">Group Description</label>
                            <textarea name="groupDesc" id="groupDesc" class="form-control"><?= htmlspecialchars($groupData['GroupDesc']) ?></textarea>
                        </div>
                        
                        <!-- ACTION BUTTONS -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>">
                                <i class="fas fa-save mr-2"></i> Save
                            </button>
                            <a href="usergroup.php" class="btn btn-secondary ml-2">
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
<!-- JAVASCRIPT DEPENDENCIES -->


<!-- CUSTOM SCRIPT -->

