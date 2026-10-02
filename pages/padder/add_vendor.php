<?php
// add_vendor.php - Tambah Data Vendor
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

// helper: ambil permission user untuk menu Vendor
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission (sesuaikan MenuId dengan menu Vendor di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 112);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data vendor.";
    header('Location: m_vendor.php');
    exit;
}

// ====== Process Form Submission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorName = $_POST['vendor_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $contactPerson = $_POST['contact_person'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($vendorName)) {
        $errors[] = "Nama Vendor harus diisi";
    }
    
    // Check if vendor name already exists
    if (empty($errors)) {
        $checkSql = "SELECT COUNT(*) as count FROM dbo.pad_m_vendor WHERE vendor_name = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$vendorName]);
        if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['count'] > 0) {
                $errors[] = "Vendor dengan nama '" . $vendorName . "' sudah ada";
            }
        }
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);
    }
    
    // Validate email format if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (empty($errors)) {
        // Insert data
        $sql = "INSERT INTO dbo.pad_m_vendor (
                    vendor_name, 
                    address, 
                    contact_person, 
                    phone, 
                    email
                ) VALUES (?, ?, ?, ?, ?)";
        
        $params = [$vendorName, $address, $contactPerson, $phone, $email];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $_SESSION['success'] = "Data vendor berhasil ditambahkan!";
            header('Location: m_vendor.php');
            exit;
        } else {
            $errors[] = "Gagal menambahkan data: " . print_r(sqlsrv_errors(), true);
        }
        
        if ($stmt) sqlsrv_free_stmt($stmt);
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Vendor</title>

    <!-- CSS (AdminLTE) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Vendor</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="m_vendor.php">Vendor</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h3 class="card-title">Form Tambah Vendor</h3>
                    <div class="card-tools">
                        <a href="m_vendor.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="form-container">
                        <form id="addVendorForm" method="post" action="">
                            <div class="form-group">
                                <label for="vendor_name" class="required">Nama Vendor</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="vendor_name" 
                                       name="vendor_name" 
                                       value="<?= isset($_POST['vendor_name']) ? htmlspecialchars($_POST['vendor_name']) : '' ?>"
                                       placeholder="Masukkan nama vendor"
                                       required
                                       maxlength="100">
                                <small class="form-text text-muted">
                                    Maksimal 100 karakter
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="contact_person" 
                                       name="contact_person" 
                                       value="<?= isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : '' ?>"
                                       placeholder="Masukkan nama contact person"
                                       maxlength="100">
                                <small class="form-text text-muted">
                                    Maksimal 100 karakter
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Telepon</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="phone" 
                                               name="phone" 
                                               value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>"
                                               placeholder="Contoh: 021-1234567"
                                               maxlength="50">
                                        <small class="form-text text-muted">
                                            Maksimal 50 karakter
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                               placeholder="Contoh: vendor@example.com"
                                               maxlength="100">
                                        <small class="form-text text-muted">
                                            Maksimal 100 karakter
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Alamat</label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          rows="3" 
                                          placeholder="Masukkan alamat lengkap vendor"
                                          maxlength="255"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                                <small class="form-text text-muted">
                                    Maksimal 255 karakter
                                </small>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                                <a href="m_vendor.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(document).ready(function() {
    // Form validation
    $('#addVendorForm').on('submit', function(e) {
        var vendorName = $('#vendor_name').val().trim();
        var email = $('#email').val().trim();
        
        if (!vendorName) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nama Vendor harus diisi!',
                confirmButtonColor: '#3085d6',
            });
            $('#vendor_name').focus();
            return false;
        }
        
        // Validate max length
        if (vendorName.length > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nama Vendor maksimal 100 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#vendor_name').focus();
            return false;
        }
        
        var contactPerson = $('#contact_person').val().trim();
        if (contactPerson.length > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Contact Person maksimal 100 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#contact_person').focus();
            return false;
        }
        
        var phone = $('#phone').val().trim();
        if (phone.length > 50) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Telepon maksimal 50 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#phone').focus();
            return false;
        }
        
        if (email.length > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Email maksimal 100 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#email').focus();
            return false;
        }
        
        // Validate email format
        if (email && !isValidEmail(email)) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Format email tidak valid!',
                confirmButtonColor: '#3085d6',
            });
            $('#email').focus();
            return false;
        }
        
        var address = $('#address').val().trim();
        if (address.length > 255) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Alamat maksimal 255 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#address').focus();
            return false;
        }
        
        // Show loading
        Swal.fire({
            title: 'Menyimpan...',
            text: 'Sedang menyimpan data vendor',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
    
    // Email validation function
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Real-time character counter for vendor_name
    $('#vendor_name').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 100;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 20) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Real-time character counter for contact_person
    $('#contact_person').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 100;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 20) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Real-time character counter for phone
    $('#phone').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 50;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 10) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Real-time character counter for email
    $('#email').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 100;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 20) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Real-time character counter for address
    $('#address').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 255;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 50) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Show notification if any
    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        html: <?= json_encode($_SESSION['error']) ?>,
        confirmButtonColor: '#3085d6',
    });
    <?php unset($_SESSION['error']); endif; ?>
});
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>