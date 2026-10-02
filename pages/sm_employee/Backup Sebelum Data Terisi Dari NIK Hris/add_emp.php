<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Authentication check
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn) {
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission check
$groupId = $_SESSION['GroupId'];
$menuId = 44;

$sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canAdd = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$canAdd = $row && $row['CanAdd'] == 1;
sqlsrv_free_stmt($stmt);

if (!$canAdd) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data.";
    header('Location: emp.php');
    exit;
}

// Optimized function to get options
function getOptions($conn, $table, $idCol, $nameCol, $where = '') {
    $options = "";
    $query = "SELECT $idCol, $nameCol FROM $table";
    if ($where) {
        $query .= " WHERE $where";
    }
    $query .= " ORDER BY $nameCol";
    
    $stmt = sqlsrv_query($conn, $query);
    
    if ($stmt === false) {
        error_log("Error fetching options from $table: " . print_r(sqlsrv_errors(), true));
        return "<option value=''>Error loading data</option>";
    }
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $value = htmlspecialchars($row[$idCol]);
        $name = htmlspecialchars($row[$nameCol]);
        $options .= "<option value='{$value}'>{$name}</option>";
    }
    
    sqlsrv_free_stmt($stmt);
    return $options;
}

// Get initial options
$deptOptions = getOptions($conn, "m_dept", "id_dept", "dept");
$golonganOptions = getOptions($conn, "m_gol", "id_gol", "golongan");
$shiftOptions = getOptions($conn, "m_shift", "id_shift", "shift");

// Default empty options for dependent dropdowns
$bagianOptions = '<option value="">-- Pilih Bagian --</option>';
$subbagOptions = '<option value="">-- Pilih Subbagian --</option>';
$jabatanOptions = '<option value="">-- Pilih Jabatan --</option>';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik']);
    
    // Validate required fields
    if (empty($nik) || empty($_POST['nama_lengkap']) || empty($_POST['id_dept']) || 
        empty($_POST['id_bag']) || empty($_POST['id_jab']) || empty($_POST['id_gol']) || 
        empty($_POST['id_shift'])) {
        $_SESSION['error'] = "Field wajib harus diisi! (NIK, Nama Lengkap, Departemen, Bagian, Jabatan, Golongan, Shift)";
        header('Location: add_emp.php');
        exit;
    }

    // Check if NIK already exists
    $checkSql = "SELECT COUNT(*) as count FROM dbo.m_emp WHERE nik = ?";
    $checkParams = [$nik];
    $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
    
    if ($checkStmt === false) {
        die("Error checking NIK: " . print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if ($row['count'] > 0) {
        $_SESSION['error'] = "NIK '{$nik}' sudah terdaftar. Silakan gunakan NIK yang berbeda.";
        header('Location: add_emp.php');
        exit;
    }
    sqlsrv_free_stmt($checkStmt);

    // Handle file upload
    $foto_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = $_FILES['photo']['type'];
        $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $_SESSION['error'] = "Format file tidak didukung. Hanya JPEG/JPG/PNG!";
            header('Location: add_emp.php');
            exit;
        }

        // Validate file size (max 2MB)
        if ($_FILES['photo']['size'] > 2097152) {
            $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 2MB.";
            header('Location: add_emp.php');
            exit;
        }

        // Create upload directory
        $uploadDir = "../../uploads/foto_karyawan/";
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $_SESSION['error'] = "Gagal membuat folder upload.";
            header('Location: add_emp.php');
            exit;
        }

        // Generate safe filename
        $safeNik = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nik);
        $newFilename = $safeNik . "_" . time() . "." . $fileExtension;
        $destination = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
            $foto_path = "uploads/foto_karyawan/" . $newFilename;
        } else {
            $_SESSION['error'] = "Gagal upload foto. Pastikan folder upload memiliki permission yang tepat.";
            header('Location: add_emp.php');
            exit;
        }
    }

    // Collect form data with proper sanitization
    $data = [
        'nik' => $nik,
        'nama_lengkap' => trim($_POST['nama_lengkap']),
        'id_jab' => (int)$_POST['id_jab'],
        'id_subbag' => !empty($_POST['id_subbag']) ? (int)$_POST['id_subbag'] : null,
        'id_bag' => (int)$_POST['id_bag'],
        'id_dept' => (int)$_POST['id_dept'],
        'id_gol' => (int)$_POST['id_gol'],
        'id_shift' => (int)$_POST['id_shift'],
        'alamat' => trim($_POST['alamat'] ?? ''),
        'kd_pos' => trim($_POST['kd_pos'] ?? ''),
        'tgl_masuk' => !empty($_POST['tgl_masuk']) ? $_POST['tgl_masuk'] : null,
        'tgl_keluar' => !empty($_POST['tgl_keluar']) ? $_POST['tgl_keluar'] : null,
        'agama' => $_POST['agama'] ?? '',
        'gol_darah' => $_POST['gol_darah'] ?? '',
        'kelamin' => $_POST['kelamin'] ?? 'L',
        'kebangsaan' => $_POST['kebangsaan'] ?? 'Indonesia',
        'telp' => trim($_POST['telp'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'tmp_lahir' => trim($_POST['tmp_lahir'] ?? ''),
        'tgl_lahir' => !empty($_POST['tgl_lahir']) ? $_POST['tgl_lahir'] : null,
        'status_kawin' => $_POST['status_kawin'] ?? 'Belum Kawin',
        'jml_anak' => !empty($_POST['jml_anak']) ? (int)$_POST['jml_anak'] : 0,
        'pend_akhir' => $_POST['pend_akhir'] ?? '',
        'no_rek' => trim($_POST['no_rek'] ?? ''),
        'bank' => trim($_POST['bank'] ?? ''),
        'aktif' => isset($_POST['aktif']) ? 1 : 0,
        'no_jamsostek' => trim($_POST['no_jamsostek'] ?? ''),
        'no_bpjs' => trim($_POST['no_bpjs'] ?? ''),
        'no_ktp' => trim($_POST['no_ktp'] ?? ''),
        'finger_key' => trim($_POST['finger_key'] ?? ''),
        'foto_path' => $foto_path,
        'upduser' => $_SESSION['UserName']
    ];

    // Validate foreign key relationships for REQUIRED fields
    $requiredFkChecks = [
        'id_jab' => ['table' => 'm_jab', 'col' => 'id_jab'],
        'id_bag' => ['table' => 'm_bag', 'col' => 'id_bag'],
        'id_dept' => ['table' => 'm_dept', 'col' => 'id_dept'],
        'id_gol' => ['table' => 'm_gol', 'col' => 'id_gol'],
        'id_shift' => ['table' => 'm_shift', 'col' => 'id_shift']
    ];

    foreach ($requiredFkChecks as $field => $config) {
        $checkSql = "SELECT COUNT(*) as count FROM dbo.{$config['table']} WHERE {$config['col']} = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$data[$field]]);
        
        if ($checkStmt === false) {
            die("Error checking {$config['table']}: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        if ($row['count'] == 0) {
            $_SESSION['error'] = "Data {$config['table']} yang dipilih tidak valid. Silakan pilih data yang tersedia.";
            header('Location: add_emp.php');
            exit;
        }
        sqlsrv_free_stmt($checkStmt);
    }

    // Validate optional foreign key
    if (!empty($data['id_subbag'])) {
        $checkSql = "SELECT COUNT(*) as count FROM dbo.m_subbag WHERE id_subbag = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$data['id_subbag']]);
        
        if ($checkStmt === false) {
            die("Error checking m_subbag: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        if ($row['count'] == 0) {
            $_SESSION['error'] = "Data subbagian yang dipilih tidak valid.";
            header('Location: add_emp.php');
            exit;
        }
        sqlsrv_free_stmt($checkStmt);
    }

    // Prepare INSERT query
    $sql = "INSERT INTO dbo.m_emp (
                nik, id_shift, id_gol, id_jab, id_subbag, id_bag, id_dept, 
                tgl_masuk, tgl_keluar, agama, gol_darah, nama_lengkap, 
                alamat, kd_pos, kebangsaan, telp, email, kelamin, 
                tmp_lahir, tgl_lahir, status_kawin, jml_anak, pend_akhir, 
                no_rek, bank, aktif, no_jamsostek, no_bpjs, no_ktp, 
                finger_key, foto_path, upddate, upduser
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, GETDATE(), ?
            )";
    
    $params = [
        $data['nik'], $data['id_shift'], $data['id_gol'], $data['id_jab'], 
        $data['id_subbag'], $data['id_bag'], $data['id_dept'],
        $data['tgl_masuk'], $data['tgl_keluar'], $data['agama'], 
        $data['gol_darah'], $data['nama_lengkap'],
        $data['alamat'], $data['kd_pos'], $data['kebangsaan'], 
        $data['telp'], $data['email'], $data['kelamin'],
        $data['tmp_lahir'], $data['tgl_lahir'], $data['status_kawin'], 
        $data['jml_anak'], $data['pend_akhir'],
        $data['no_rek'], $data['bank'], $data['aktif'], 
        $data['no_jamsostek'], $data['no_bpjs'], $data['no_ktp'],
        $data['finger_key'], $data['foto_path'], $data['upduser']
    ];
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        // Cleanup uploaded file if insert fails
        if ($foto_path && file_exists("../../" . $foto_path)) {
            unlink("../../" . $foto_path);
        }
        $errors = sqlsrv_errors();
        error_log("Failed to insert employee: " . print_r($errors, true));
        $_SESSION['error'] = "Gagal menambahkan data: " . $errors[0]['message'];
    } else {
        $_SESSION['success'] = "Data karyawan berhasil ditambahkan.";
        header('Location: emp.php');
        exit;
    }
}

ob_end_flush();
?>


    <style>
        .form-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .form-section h5 {
            margin-bottom: 20px;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .img-preview-container {
            text-align: center;
            margin-top: 10px;
        }
        #photoPreview {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .required-label {
            font-weight: bold;
            color: #d9534f;
        }
        .form-note {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .field-required {
            border-left: 3px solid #d9534f !important;
        }
    </style>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Tambah Karyawan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="emp.php">Karyawan</a></li>
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-ban"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="employeeForm">
                    <div class="card">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title">Form Tambah Karyawan</h3>
                           
                        </div>
                        <div class="card-body">
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Informasi Pribadi</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- ✅ WAJIB: NIK -->
                                        <div class="form-group">
                                            <label for="nik" class="required-label">NIK</label>
                                            <input type="text" id="nik" name="nik" class="form-control field-required" required 
                                                   maxlength="20" placeholder="Masukkan NIK">
                                            <small class="form-text text-danger">Wajib diisi</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Nama Lengkap -->
                                        <div class="form-group">
                                            <label for="nama_lengkap" class="required-label">Nama Lengkap</label>
                                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control field-required" 
                                                   required maxlength="150" placeholder="Masukkan nama lengkap">
                                            <small class="form-text text-danger">Wajib diisi</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="tmp_lahir">Tempat Lahir</label>
                                            <input type="text" id="tmp_lahir" name="tmp_lahir" class="form-control" 
                                                   maxlength="100" placeholder="Masukkan tempat lahir">
                                        </div>
                                        <div class="form-group">
                                            <label for="tgl_lahir">Tanggal Lahir</label>
                                            <input type="date" name="tgl_lahir" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kelamin">Jenis Kelamin</label>
                                            <select name="kelamin" class="form-control">
                                                <option value="L">Laki-laki</option>
                                                <option value="P">Perempuan</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="agama">Agama</label>
                                            <select name="agama" class="form-control">
                                                <option value="Islam">Islam</option>
                                                <option value="Kristen">Kristen</option>
                                                <option value="Katolik">Katolik</option>
                                                <option value="Hindu">Hindu</option>
                                                <option value="Budha">Budha</option>
                                                <option value="Konghucu">Konghucu</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="gol_darah">Golongan Darah</label>
                                            <select name="gol_darah" class="form-control">
                                                <option value="">- Pilih -</option>
                                                <option value="A">A</option>
                                                <option value="B">B</option>
                                                <option value="AB">AB</option>
                                                <option value="O">O</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status_kawin">Status Perkawinan</label>
                                            <select name="status_kawin" class="form-control">
                                                <option value="Belum Kawin">Belum Kawin</option>
                                                <option value="Kawin">Kawin</option>
                                                <option value="Cerai Hidup">Cerai Hidup</option>
                                                <option value="Cerai Mati">Cerai Mati</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="jml_anak">Jumlah Anak</label>
                                            <input type="number" name="jml_anak" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-id-card"></i> Dokumen & Identitas</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="no_ktp">No. KTP</label>
                                            <input type="text" name="no_ktp" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan nomor KTP">
                                        </div>
                                        <div class="form-group">
                                            <label for="no_jamsostek">No. Jamsostek</label>
                                            <input type="text" name="no_jamsostek" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan nomor Jamsostek">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="no_bpjs">No. BPJS</label>
                                            <input type="text" name="no_bpjs" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan nomor BPJS">
                                        </div>
                                        <div class="form-group">
                                            <label for="finger_key">Finger Key</label>
                                            <input type="text" name="finger_key" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan finger key">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-camera"></i> Foto Karyawan</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="photo">Upload Foto</label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="photo" name="photo" 
                                                       accept="image/jpeg, image/jpg, image/png">
                                                <label class="custom-file-label" for="photo">Pilih file foto</label>
                                            </div>
                                            <small class="form-text text-muted">
                                                Format: JPEG, JPG, PNG | Maksimal: 2MB
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Pratinjau Foto</label>
                                            <div class="img-preview-container">
                                                <img id="photoPreview" src="data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20200%20200%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_18945b7b7e7%20text%20%7B%20fill%3A%23AAAAAA%3Bfont-weight%3Abold%3Bfont-family%3AArial%2C%20Helvetica%2C%20Open%20Sans%2C%20sans-serif%2C%20monospace%3Bfont-size%3A10pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_18945b7b7e7%22%3E%3Crect%20width%3D%22200%22%20height%3D%22200%22%20fill%3D%22%23EEEEEE%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%2274.421875%22%20y%3D%22104.5%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E" 
                                                    alt="Pratinjau foto" class="img-thumbnail">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-address-book"></i> Informasi Kontak</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="alamat">Alamat</label>
                                            <textarea name="alamat" class="form-control" rows="3" maxlength="255" 
                                                      placeholder="Masukkan alamat lengkap"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="kd_pos">Kode Pos</label>
                                            <input type="text" name="kd_pos" class="form-control" maxlength="10" 
                                                   placeholder="Masukkan kode pos">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="telp">Telepon</label>
                                            <input type="text" name="telp" class="form-control" maxlength="20" 
                                                   placeholder="Masukkan nomor telepon">
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" name="email" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan alamat email">
                                        </div>
                                        <div class="form-group">
                                            <label for="kebangsaan">Kebangsaan</label>
                                            <input type="text" name="kebangsaan" class="form-control" value="Indonesia" 
                                                   maxlength="50" placeholder="Masukkan kebangsaan">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-briefcase"></i> Informasi Pekerjaan</h5>
                                <small class="text-danger mb-3 d-block"><i class="fas fa-exclamation-triangle"></i> Semua field di bawah ini WAJIB diisi untuk menghindari error database</small>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- ✅ WAJIB: Departemen -->
                                        <div class="form-group">
                                            <label for="id_dept" class="required-label">Departemen</label>
                                            <select name="id_dept" id="id_dept" class="form-control field-required" required>
                                                <option value="">-- Pilih Departemen --</option>
                                                <?= $deptOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Bagian -->
                                        <div class="form-group">
                                            <label for="id_bag" class="required-label">Bagian</label>
                                            <select name="id_bag" id="id_bag" class="form-control field-required" required>
                                                <option value="">-- Pilih Bagian --</option>
                                                <?= $bagianOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Subbagian -->
                                        <div class="form-group">
                                            <label for="id_subbag">Subbagian</label>
                                            <select name="id_subbag" id="id_subbag" class="form-control field-required" required>
                                                <?= $subbagOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <!-- ✅ WAJIB: Jabatan -->
                                        <div class="form-group">
                                            <label for="id_jab" class="required-label">Jabatan</label>
                                            <select name="id_jab" id="id_jab" class="form-control field-required" required>
                                                <?= $jabatanOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Golongan -->
                                        <div class="form-group">
                                            <label for="id_gol" class="required-label">Golongan</label>
                                            <select name="id_gol" id="id_gol" class="form-control field-required" required>
                                                <option value="">-- Pilih Golongan --</option>
                                                <?= $golonganOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih - Pastikan ID golongan valid</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Shift -->
                                        <div class="form-group">
                                            <label for="id_shift" class="required-label">Shift</label>
                                            <select name="id_shift" id="id_shift" class="form-control field-required" required>
                                                <option value="">-- Pilih Shift --</option>
                                                <?= $shiftOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih - Pastikan ID shift valid</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="tgl_masuk">Tanggal Masuk</label>
                                            <input type="date" name="tgl_masuk" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tgl_keluar">Tanggal Keluar</label>
                                            <input type="date" name="tgl_keluar" class="form-control">
                                            <small class="form-note">Isi hanya jika karyawan sudah keluar</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group form-check mt-4">
                                            <input type="checkbox" name="aktif" class="form-check-input" id="aktif" checked>
                                            <label class="form-check-label" for="aktif">Status Aktif</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-graduation-cap"></i> Pendidikan & Keuangan</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="pend_akhir">Pendidikan Terakhir</label>
                                            <select name="pend_akhir" class="form-control">
                                                <option value="SD">SD</option>
                                                <option value="SMP">SMP</option>
                                                <option value="SMA">SMA</option>
                                                <option value="D1">D1</option>
                                                <option value="D2">D2</option>
                                                <option value="D3">D3</option>
                                                <option value="S1">S1</option>
                                                <option value="S2">S2</option>
                                                <option value="S3">S3</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="no_rek">No. Rekening</label>
                                            <input type="text" name="no_rek" class="form-control" maxlength="50" 
                                                   placeholder="Masukkan nomor rekening">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="bank">Bank</label>
                                            <input type="text" name="bank" class="form-control" maxlength="100" 
                                                   placeholder="Masukkan nama bank">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor);?>"> 
                                <i class="fas fa-save"></i> Simpan Data
                            </button>
                            <a href="emp.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Photo preview functionality
    $('#photo').on('change', function() {
        const file = this.files[0];
        const preview = $('#photoPreview');
        const label = $(this).next('.custom-file-label');
        
        if (file) {
            label.text(file.name);
            
            if (file.type.match('image.*')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.attr('src', e.target.result);
                }
                
                reader.readAsDataURL(file);
            }
        } else {
            label.text('Pilih file foto');
        }
    });

    // Dependent dropdown functionality
    function loadDependentOptions(targetElement, table, idCol, nameCol, where) {
        if (where) {
            $.get('get_options.php', {
                table: table,
                id_col: idCol,
                name_col: nameCol,
                where: where
            }, function(data) {
                $(targetElement).html(data);
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Error fetching ' + table + ':', textStatus, errorThrown);
                $(targetElement).html('<option value="">Error loading data</option>');
            });
        }
    }

    $('#id_dept').change(function() {
        var deptId = $(this).val();
        
        // Reset dependent dropdowns
        $('#id_bag').html('<option value="">-- Pilih Bagian --</option>');
        $('#id_subbag').html('<option value="">-- Pilih Subbagian --</option>');
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(deptId) {
            loadDependentOptions('#id_bag', 'm_bag', 'id_bag', 'bagian', 'id_dept=' + deptId);
        }
    });
    
    $('#id_bag').change(function() {
        var bagId = $(this).val();
        
        // Reset dependent dropdowns
        $('#id_subbag').html('<option value="">-- Pilih Subbagian --</option>');
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(bagId) {
            loadDependentOptions('#id_subbag', 'm_subbag', 'id_subbag', 'subbag', 'id_bag=' + bagId);
        }
    });
    
    $('#id_subbag').change(function() {
        var subbagId = $(this).val();
        
        // Reset position dropdown
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(subbagId) {
            loadDependentOptions('#id_jab', 'm_jab', 'id_jab', 'jabatan', 'id_subbag=' + subbagId);
        }
    });

    // Enhanced form validation
    $('#employeeForm').on('submit', function(e) {
        let isValid = true;
        const requiredFields = [
            '#nik', '#nama_lengkap', '#id_dept', '#id_bag', '#id_jab', '#id_gol', '#id_shift'
        ];

        // Check all required fields
        requiredFields.forEach(function(field) {
            const value = $(field).val().trim();
            if (!value) {
                isValid = false;
                $(field).addClass('is-invalid');
                $(field).closest('.form-group').find('.form-text').addClass('text-danger');
            } else {
                $(field).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Harap lengkapi semua field wajib yang ditandai dengan warna merah!');
            return false;
        }
        
        // Show loading state
        $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    });

    // Remove invalid class when user starts typing
    $('.field-required').on('input change', function() {
        if ($(this).val().trim()) {
            $(this).removeClass('is-invalid');
        }
    });
});
</script>
