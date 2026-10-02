<?php
// Start session and output buffering at the very beginning
session_start();
ob_start();

// Include dependencies
include '../koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Define menu ID for Group Access Rights
define('GROUP_ACCESS_MENU_ID', 4);

// Get trustee data first
$trusteeId = $_GET['id'] ?? 0;
$trusteeData = getTrusteeData($conn, $trusteeId);
if (!$trusteeData) {
    $_SESSION['error'] = "Data trustee tidak ditemukan!";
    header('Location: hakaksesgroup.php');
    exit;
}

// Now check edit permissions after we have the trustee data
$groupId = $_SESSION['GroupId'];
$menuId = GROUP_ACCESS_MENU_ID; // Using the defined constant

// Check CanEdit permission
$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canEdit = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $canEdit = $row && $row['CanEdit'] == 1;
    sqlsrv_free_stmt($stmt);
}

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: hakaksesgroup.php');
    exit;
}

// Get all groups and menus
$groups = getAllGroups($conn);
$menus = getAllMenus($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $groupId = $_POST['groupId'];
    $menuId = $_POST['menuId'];
    $canView = isset($_POST['canView']) ? 1 : 0;
    $canAdd = isset($_POST['canAdd']) ? 1 : 0;
    $canEdit = isset($_POST['canEdit']) ? 1 : 0;
    $canDelete = isset($_POST['canDelete']) ? 1 : 0;

    // Update trustee data
    $updateSql = "UPDATE dbo.SMGroupTrustee 
                  SET GroupId = ?, MenuId = ?, 
                      CanView = ?, CanAdd = ?, CanEdit = ?, CanDelete = ?
                  WHERE TrusteeId = ?";
    $updateParams = [$groupId, $menuId, $canView, $canAdd, $canEdit, $canDelete, $trusteeId];
    $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

    if ($updateStmt) {
        $_SESSION['success'] = "Hak akses berhasil diperbarui!";
        header('Location: hakaksesgroup.php');
        exit;
    } else {
        $_SESSION['error'] = "Gagal memperbarui hak akses: " . print_r(sqlsrv_errors(), true);
    }
}

/**
 * Get trustee data by ID
 */
function getTrusteeData($conn, $trusteeId) {
    $sql = "SELECT a.GroupId, b.GroupName, b.GroupDesc, a.TrusteeId,
                   c.MenuId, c.MenuName, a.CanView, a.CanAdd, 
                   a.CanEdit, a.CanDelete
            FROM dbo.SMGroupTrustee AS a
            LEFT JOIN dbo.SMUserGroup AS b ON a.GroupId = b.GroupId
            LEFT JOIN dbo.SMMenu AS c ON a.MenuId = c.MenuId
            WHERE a.TrusteeId = ?";
    $params = [$trusteeId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    return false;
}

/**
 * Get all groups
 */
function getAllGroups($conn) {
    $sql = "SELECT GroupId, GroupName FROM dbo.SMUserGroup ORDER BY GroupName";
    $stmt = sqlsrv_query($conn, $sql);
    $groups = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $groups[] = $row;
        }
    }
    return $groups;
}

/**
 * Get all menus
 */
function getAllMenus($conn) {
    $sql = "SELECT MenuId, MenuName FROM dbo.SMMenu ORDER BY MenuName";
    $stmt = sqlsrv_query($conn, $sql);
    $menus = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $menus[] = $row;
        }
    }
    return $menus;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hak Akses Group</title>

    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <style>
        .card-header {
            padding: 1rem 1.25rem;
        }
        .card-title {
            margin-bottom: 0;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-check-label {
            padding-left: 0.5rem;
        }
        .permission-checkboxes {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .permission-title {
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Hak Akses Group</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="hakaksesgroup.php">Hak Akses Group</a></li>
                        <li class="breadcrumb-item active">Edit Hak Akses</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title">Form Edit Hak Akses</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit_hakaksesgroup.php?id=<?= $trusteeId ?>">
                        <div class="form-group">
                            <label for="groupId">Group</label>
                            <select class="form-control select2" id="groupId" name="groupId" required>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= $group['GroupId'] ?>" 
                                        <?= $group['GroupId'] == $trusteeData['GroupId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($group['GroupName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="menuId">Menu</label>
                            <select class="form-control select2" id="menuId" name="menuId" required>
                                <?php foreach ($menus as $menu): ?>
                                    <option value="<?= $menu['MenuId'] ?>" 
                                        <?= $menu['MenuId'] == $trusteeData['MenuId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($menu['MenuName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="permission-checkboxes">
                            <div class="permission-title">Hak Akses</div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="canView" name="canView" 
                                    <?= $trusteeData['CanView'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canView">Can View</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="canAdd" name="canAdd"
                                    <?= $trusteeData['CanAdd'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canAdd">Can Add</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="canEdit" name="canEdit"
                                    <?= $trusteeData['CanEdit'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canEdit">Can Edit</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="canDelete" name="canDelete"
                                    <?= $trusteeData['CanDelete'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canDelete">Can Delete</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>
">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="hakaksesgroup.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>


<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Show notifications
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
});
</script>
</body>
</html>

<?php include '../includes/footer.php'; ?>