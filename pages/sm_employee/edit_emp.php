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

// Get employee data first
$nik = isset($_GET['nik']) ? trim($_GET['nik']) : null;
$employee = null;

if (!$nik) {
    $_SESSION['error'] = "NIK tidak valid";
    header('Location: emp.php');
    exit;
}

$sql = "SELECT * FROM dbo.m_emp WHERE nik = ?";
$params = [$nik];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Error fetching employee data: " . print_r(sqlsrv_errors(), true));
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$employee) {
    $_SESSION['error'] = "Data karyawan dengan NIK $nik tidak ditemukan";
    header('Location: emp.php');
    exit;
}

// Permission check
$groupId = $_SESSION['GroupId'];
$menuId = 44;

$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canEdit = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$canEdit = $row && $row['CanEdit'] == 1;
sqlsrv_free_stmt($stmt);

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: emp.php');
    exit;
}

// Optimized function to get options
function getOptions($conn, $table, $idCol, $nameCol, $selectedValue = null, $where = '') {
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
        $selected = ($selectedValue !== null && $row[$idCol] == $selectedValue) ? 'selected' : '';
        $options .= "<option value='{$value}' $selected>{$name}</option>";
    }
    
    sqlsrv_free_stmt($stmt);
    return $options;
}

// Format dates for input fields
function formatDateForInput($date) {
    return $date ? $date->format('Y-m-d') : '';
}

$tgl_masuk = formatDateForInput($employee['tgl_masuk']);
$tgl_keluar = formatDateForInput($employee['tgl_keluar']);
$tgl_lahir = formatDateForInput($employee['tgl_lahir']);

// Get options with current selections
$deptOptions = getOptions($conn, "m_dept", "id_dept", "dept", $employee['id_dept']);
$golonganOptions = getOptions($conn, "m_gol", "id_gol", "golongan", $employee['id_gol']);
$shiftOptions = getOptions($conn, "m_shift", "id_shift", "shift", $employee['id_shift']);

// Get dependent dropdown options
$bagianOptions = getOptions($conn, "m_bag", "id_bag", "bagian", $employee['id_bag'], 
                           $employee['id_dept'] ? "id_dept=" . $employee['id_dept'] : "");
$subbagOptions = getOptions($conn, "m_subbag", "id_subbag", "subbag", $employee['id_subbag'], 
                           $employee['id_bag'] ? "id_bag=" . $employee['id_bag'] : "");
$jabatanOptions = getOptions($conn, "m_jab", "id_jab", "jabatan", $employee['id_jab'], 
                            $employee['id_subbag'] ? "id_subbag=" . $employee['id_subbag'] : "");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_nik = trim($_POST['old_nik']);
    $new_nik = trim($_POST['nik']);

    // Validate NIK not empty
    if (empty($new_nik)) {
        $_SESSION['error'] = "NIK tidak boleh kosong!";
        header("Location: edit_emp.php?nik=$old_nik");
        exit;
    }

    // Check for duplicate NIK if NIK was changed
    if ($new_nik !== $old_nik) {
        $dupSql = "SELECT COUNT(*) as cnt FROM dbo.m_emp WHERE nik = ?";
        $dupStmt = sqlsrv_query($conn, $dupSql, [$new_nik]);
        if ($dupStmt === false) {
            die("Error checking NIK duplikat: " . print_r(sqlsrv_errors(), true));
        }
        $dupRow = sqlsrv_fetch_array($dupStmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($dupStmt);
        if ($dupRow['cnt'] > 0) {
            $_SESSION['error'] = "NIK \"$new_nik\" sudah digunakan oleh karyawan lain. Silakan gunakan NIK yang berbeda.";
            header("Location: edit_emp.php?nik=$old_nik");
            exit;
        }
    }

    $nik = $new_nik; // Use new NIK going forward
    
    // Validate required fields
    if (empty($_POST['nama_lengkap']) || empty($_POST['id_dept']) || 
        empty($_POST['id_bag']) || empty($_POST['id_subbag']) || empty($_POST['id_jab']) || 
        empty($_POST['id_gol']) || empty($_POST['id_shift'])) {
        $_SESSION['error'] = "Field wajib harus diisi! (Nama Lengkap, Departemen, Bagian, Subbagian, Jabatan, Golongan, Shift)";
        header("Location: edit_emp.php?nik=$nik");
        exit;
    }

    // Collect form data with proper sanitization
    $data = [
        'nik' => $new_nik,
        'old_nik' => $old_nik,
        'nama_lengkap' => trim($_POST['nama_lengkap']),
        'id_jab' => (int)$_POST['id_jab'],
        'id_subbag' => (int)$_POST['id_subbag'],
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
        'jurusan' => !in_array($_POST['pend_akhir'] ?? '', ['SD', 'SMP'], true) ? substr(trim($_POST['jurusan'] ?? ''), 0, 100) : '',
        'no_rek' => trim($_POST['no_rek'] ?? ''),
        'bank' => trim($_POST['bank'] ?? ''),
        'aktif' => isset($_POST['aktif']) ? 1 : 0,
        'no_jamsostek' => trim($_POST['no_jamsostek'] ?? ''),
        'no_bpjs' => trim($_POST['no_bpjs'] ?? ''),
        'no_ktp' => trim($_POST['no_ktp'] ?? ''),
        'finger_key' => trim($_POST['finger_key'] ?? ''),
        'upduser' => $_SESSION['UserName']
    ];

    // Validate required fields when status is inactive
    if ($data['aktif'] == 0 && empty($data['tgl_keluar'])) {
        $_SESSION['error'] = "Tanggal keluar wajib diisi karena status karyawan tidak aktif!";
        header("Location: edit_emp.php?nik=$nik");
        exit;
    }

    // Validate foreign key relationships
    $requiredFkChecks = [
        'id_jab' => ['table' => 'm_jab', 'col' => 'id_jab'],
        'id_subbag' => ['table' => 'm_subbag', 'col' => 'id_subbag'],
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
            header("Location: edit_emp.php?nik=$nik");
            exit;
        }
        sqlsrv_free_stmt($checkStmt);
    }

    // Handle file upload
    $foto_path = $employee['foto_path'];
    $oldPhotoPath = $employee['foto_path'];
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = $_FILES['photo']['type'];
        $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $_SESSION['error'] = "Format file tidak didukung. Hanya JPEG/JPG/PNG!";
            header("Location: edit_emp.php?nik=$nik");
            exit;
        }

        // Validate file size (max 2MB)
        if ($_FILES['photo']['size'] > 2097152) {
            $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 2MB.";
            header("Location: edit_emp.php?nik=$nik");
            exit;
        }

        // Create upload directory
        $uploadDir = "../../uploads/foto_karyawan/";
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $_SESSION['error'] = "Gagal membuat folder upload.";
            header("Location: edit_emp.php?nik=$nik");
            exit;
        }

        // Generate safe filename
        $safeNik = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nik);
        $newFilename = $safeNik . "_" . time() . "." . $fileExtension;
        $destination = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
            $foto_path = "uploads/foto_karyawan/" . $newFilename;
            
            // Delete old photo file if exists and different from new one
            if ($oldPhotoPath && $oldPhotoPath != $foto_path && file_exists("../../" . $oldPhotoPath)) {
                unlink("../../" . $oldPhotoPath);
            }
        } else {
            $_SESSION['error'] = "Gagal upload foto. Pastikan folder upload memiliki permission yang tepat.";
            header("Location: edit_emp.php?nik=$nik");
            exit;
        }
    }

    $data['foto_path'] = $foto_path;

    // Helper untuk mendapatkan text/nama dari Master Data alih-alih ID
    function getHistoryDisplayValue($conn, $key, $value) {
        if ($value === '' || $value === null) return '-';
        
        $map = [
            'id_shift' => ['table' => 'm_shift', 'idCol' => 'id_shift', 'nameCol' => 'shift'],
            'id_gol' => ['table' => 'm_gol', 'idCol' => 'id_gol', 'nameCol' => 'golongan'],
            'id_jab' => ['table' => 'm_jab', 'idCol' => 'id_jab', 'nameCol' => 'jabatan'],
            'id_subbag' => ['table' => 'm_subbag', 'idCol' => 'id_subbag', 'nameCol' => 'subbag'],
            'id_bag' => ['table' => 'm_bag', 'idCol' => 'id_bag', 'nameCol' => 'bagian'],
            'id_dept' => ['table' => 'm_dept', 'idCol' => 'id_dept', 'nameCol' => 'dept']
        ];
        
        if (isset($map[$key])) {
            $m = $map[$key];
            $q = "SELECT " . $m['nameCol'] . " as name FROM " . $m['table'] . " WHERE " . $m['idCol'] . " = ?";
            $stmt = sqlsrv_query($conn, $q, [$value]);
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $name = $row['name'];
                sqlsrv_free_stmt($stmt);
                return $name;
            }
            if ($stmt) sqlsrv_free_stmt($stmt);
            return $value;
        }
        
        if ($key === 'kelamin') return $value == 'L' ? 'Laki-laki' : ($value == 'P' ? 'Perempuan' : $value);
        if ($key === 'aktif') return $value == '1' ? 'Aktif' : 'Nonaktif';
        
        return $value;
    }

    // Detect Changes
    $changes = [];

    // NIK change detection (special case - not in employee array comparison)
    if ($new_nik !== $old_nik) {
        $changes[] = [
            'field' => 'NIK',
            'old' => $old_nik,
            'new' => $new_nik
        ];
    }

    $fieldsToCompare = [
        'id_shift' => 'Shift',
        'id_gol' => 'Golongan',
        'id_jab' => 'Jabatan',
        'id_subbag' => 'Subbagian',
        'id_bag' => 'Bagian',
        'id_dept' => 'Departemen',
        'tgl_masuk' => 'Tanggal Masuk',
        'tgl_keluar' => 'Tanggal Keluar',
        'agama' => 'Agama',
        'gol_darah' => 'Gol. Darah',
        'nama_lengkap' => 'Nama Lengkap',
        'alamat' => 'Alamat',
        'kd_pos' => 'Kode Pos',
        'kebangsaan' => 'Kebangsaan',
        'telp' => 'Telepon',
        'email' => 'Email',
        'kelamin' => 'Jenis Kelamin',
        'tmp_lahir' => 'Tempat Lahir',
        'tgl_lahir' => 'Tanggal Lahir',
        'status_kawin' => 'Status Perkawinan',
        'jml_anak' => 'Jumlah Anak',
        'pend_akhir' => 'Pendidikan Akhir',
        'jurusan' => 'Jurusan',
        'no_rek' => 'No Rekening',
        'bank' => 'Bank',
        'aktif' => 'Status Aktif',
        'no_jamsostek' => 'No Jamsostek',
        'no_bpjs' => 'No BPJS',
        'no_ktp' => 'No KTP',
        'finger_key' => 'Finger Key'
    ];

    foreach ($fieldsToCompare as $key => $label) {
        // SQL Server might return case-sensitive keys (e.g., 'Bank' instead of 'bank')
        $empValue = null;
        if (array_key_exists($key, $employee)) {
            $empValue = $employee[$key];
        } elseif (array_key_exists(ucfirst($key), $employee)) {
            $empValue = $employee[ucfirst($key)];
        } elseif (array_key_exists(strtolower($key), $employee)) {
            $empValue = $employee[strtolower($key)];
        }

        $oldVal = is_object($empValue) ? $empValue->format('Y-m-d') : (string)$empValue;
        $newVal = (string)$data[$key];
        
        // Normalisasi CRLF dan spasi agar tidak terjadi false positive (seperti pd Alamat)
        $oldComp = trim(str_replace("\r", "", $oldVal));
        $newComp = trim(str_replace("\r", "", $newVal));
        
        if ($oldComp !== $newComp) {
            $changes[] = [
                'field' => $label,
                'old' => getHistoryDisplayValue($conn, $key, $oldVal),
                'new' => getHistoryDisplayValue($conn, $key, $newVal)
            ];
        }
    }

    if ($foto_path && $foto_path !== $oldPhotoPath) {
        $changes[] = [
            'field' => 'Foto',
            'old' => $oldPhotoPath,
            'new' => $foto_path
        ];
    }

    sqlsrv_begin_transaction($conn);
    try {
        // Prepare UPDATE query (also update nik if changed)
        $sql = "UPDATE dbo.m_emp SET
                    nik = ?,
                    id_shift = ?, id_gol = ?, id_jab = ?, id_subbag = ?, id_bag = ?, id_dept = ?, 
                    tgl_masuk = ?, tgl_keluar = ?, agama = ?, gol_darah = ?, nama_lengkap = ?, 
                    alamat = ?, kd_pos = ?, kebangsaan = ?, telp = ?, email = ?, kelamin = ?, 
                    tmp_lahir = ?, tgl_lahir = ?, status_kawin = ?, jml_anak = ?, pend_akhir = ?, jurusan = ?,
                    no_rek = ?, bank = ?, aktif = ?, no_jamsostek = ?, no_bpjs = ?, no_ktp = ?, 
                    finger_key = ?, foto_path = ?, upddate = GETDATE(), upduser = ?
                WHERE nik = ?";
        
        $params = [
            $data['nik'],
            $data['id_shift'], $data['id_gol'], $data['id_jab'], $data['id_subbag'], 
            $data['id_bag'], $data['id_dept'], $data['tgl_masuk'], $data['tgl_keluar'], 
            $data['agama'], $data['gol_darah'], $data['nama_lengkap'], $data['alamat'], 
            $data['kd_pos'], $data['kebangsaan'], $data['telp'], $data['email'], 
            $data['kelamin'], $data['tmp_lahir'], $data['tgl_lahir'], $data['status_kawin'], 
            $data['jml_anak'], $data['pend_akhir'], $data['jurusan'], $data['no_rek'], $data['bank'], 
            $data['aktif'], $data['no_jamsostek'], $data['no_bpjs'], $data['no_ktp'], 
            $data['finger_key'], $data['foto_path'], $data['upduser'], $data['old_nik']
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Gagal mengupdate data: " . sqlsrv_errors()[0]['message']);
        }

        // Insert history
        $id_emp = $employee['id_emp'];
        if (!empty($changes)) {
            $sqlHistory = "INSERT INTO dbo.m_emp_history (id_emp, field_changed, old_value, new_value, diubah_oleh) VALUES (?, ?, ?, ?, ?)";
            $stmtHistory = sqlsrv_prepare($conn, $sqlHistory, [&$id_emp, &$h_field, &$h_old, &$h_new, &$data['upduser']]);
            
            if (!$stmtHistory) {
                throw new Exception("Gagal preparing history statement.");
            }

            foreach ($changes as $ch) {
                $h_field = $ch['field'];
                $h_old = $ch['old'];
                $h_new = $ch['new'];
                if (!sqlsrv_execute($stmtHistory)) {
                    throw new Exception("Gagal insert history: " . sqlsrv_errors()[0]['message']);
                }
            }
        }
        
        sqlsrv_commit($conn);
        $_SESSION['success'] = "Data karyawan berhasil diupdate.";
        header('Location: emp.php');
        exit;
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        error_log("Transaction failed: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_emp.php?nik=$nik");
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
        #photoPreview, .current-photo {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 5px;
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
        .dependency-note {
            font-size: 0.8rem;
            color: #e67e22;
            font-style: italic;
        }
        .current-photo-container {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>

<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit Karyawan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="emp.php">Karyawan</a></li>
                            <li class="breadcrumb-item active">Edit - <?= htmlspecialchars($employee['nama_lengkap']) ?></li>
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
                    <input type="hidden" name="old_nik" id="old_nik" value="<?= htmlspecialchars($employee['nik']) ?>">
                    
                    <div class="card">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title">Form Edit Karyawan - <?= htmlspecialchars($employee['nama_lengkap']) ?></h3>
                            
                        </div>
                        <div class="card-body">
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Informasi Pribadi</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nik">NIK</label>
                                            <input type="text" name="nik" id="nik" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['nik']) ?>" 
                                                   maxlength="50" autocomplete="off">
                                            <small class="form-text text-muted" id="nik_feedback">Ubah NIK jika diperlukan</small>
                                        </div>
                                        
                                        <!-- ✅ WAJIB: Nama Lengkap -->
                                        <div class="form-group">
                                            <label for="nama_lengkap" class="required-label">Nama Lengkap</label>
                                            <input type="text" name="nama_lengkap" class="form-control field-required" 
                                                   value="<?= htmlspecialchars($employee['nama_lengkap']) ?>" required maxlength="150">
                                            <small class="form-text text-danger">Wajib diisi</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="tmp_lahir">Tempat Lahir</label>
                                            <input type="text" name="tmp_lahir" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['tmp_lahir']) ?>" maxlength="100">
                                        </div>
                                        <div class="form-group">
                                            <label for="tgl_lahir">Tanggal Lahir</label>
                                            <input type="date" name="tgl_lahir" class="form-control" value="<?= $tgl_lahir ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="kelamin">Jenis Kelamin</label>
                                            <select name="kelamin" class="form-control">
                                                <option value="L" <?= $employee['kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                                <option value="P" <?= $employee['kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="agama">Agama</label>
                                            <select name="agama" class="form-control">
                                                <option value="Islam" <?= $employee['agama'] == 'Islam' ? 'selected' : '' ?>>Islam</option>
                                                <option value="Kristen" <?= $employee['agama'] == 'Kristen' ? 'selected' : '' ?>>Kristen</option>
                                                <option value="Katolik" <?= $employee['agama'] == 'Katolik' ? 'selected' : '' ?>>Katolik</option>
                                                <option value="Hindu" <?= $employee['agama'] == 'Hindu' ? 'selected' : '' ?>>Hindu</option>
                                                <option value="Budha" <?= $employee['agama'] == 'Budha' ? 'selected' : '' ?>>Budha</option>
                                                <option value="Konghucu" <?= $employee['agama'] == 'Konghucu' ? 'selected' : '' ?>>Konghucu</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="gol_darah">Golongan Darah</label>
                                            <select name="gol_darah" class="form-control">
                                                <option value="">- Pilih -</option>
                                                <option value="A" <?= $employee['gol_darah'] == 'A' ? 'selected' : '' ?>>A</option>
                                                <option value="B" <?= $employee['gol_darah'] == 'B' ? 'selected' : '' ?>>B</option>
                                                <option value="AB" <?= $employee['gol_darah'] == 'AB' ? 'selected' : '' ?>>AB</option>
                                                <option value="O" <?= $employee['gol_darah'] == 'O' ? 'selected' : '' ?>>O</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status_kawin">Status Perkawinan</label>
                                            <select name="status_kawin" class="form-control">
                                                <option value="Belum Kawin" <?= $employee['status_kawin'] == 'Belum Kawin' ? 'selected' : '' ?>>Belum Kawin</option>
                                                <option value="Kawin" <?= $employee['status_kawin'] == 'Kawin' ? 'selected' : '' ?>>Kawin</option>
                                                <option value="Cerai Hidup" <?= $employee['status_kawin'] == 'Cerai Hidup' ? 'selected' : '' ?>>Cerai Hidup</option>
                                                <option value="Cerai Mati" <?= $employee['status_kawin'] == 'Cerai Mati' ? 'selected' : '' ?>>Cerai Mati</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="jml_anak">Jumlah Anak</label>
                                            <input type="number" name="jml_anak" class="form-control" min="0" value="<?= $employee['jml_anak'] ?>">
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
                                            <input type="text" name="no_ktp" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['no_ktp']) ?>" maxlength="50">
                                        </div>
                                        <div class="form-group">
                                            <label for="no_jamsostek">No. Jamsostek</label>
                                            <input type="text" name="no_jamsostek" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['no_jamsostek']) ?>" maxlength="50">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="no_bpjs">No. BPJS</label>
                                            <input type="text" name="no_bpjs" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['no_bpjs']) ?>" maxlength="50">
                                        </div>
                                        <div class="form-group">
                                            <label for="finger_key">Finger Key</label>
                                            <input type="text" name="finger_key" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['finger_key']) ?>" maxlength="50">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5><i class="fas fa-camera"></i> Foto Karyawan</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="photo">Upload Foto Baru</label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="photo" name="photo" 
                                                       accept="image/jpeg, image/jpg, image/png">
                                                <label class="custom-file-label" for="photo">Pilih file foto baru</label>
                                            </div>
                                            <small class="form-text text-muted">
                                                Biarkan kosong jika tidak ingin mengubah foto. Format: JPEG, JPG, PNG | Maksimal: 2MB
                                            </small>
                                        </div>
                                        
                                        <?php if ($employee['foto_path']): ?>
                                        <div class="current-photo-container">
                                            <label>Foto Saat Ini</label>
                                            <div>
                                                <img src="../../<?= htmlspecialchars($employee['foto_path']) ?>" 
                                                     alt="Foto Karyawan" class="current-photo"
                                                     onerror="this.style.display='none'">
                                                <div class="mt-2">
                                                    <small class="text-muted"><?= htmlspecialchars(basename($employee['foto_path'])) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Pratinjau Foto Baru</label>
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
                                            <textarea name="alamat" class="form-control" rows="3" maxlength="255"><?= htmlspecialchars($employee['alamat']) ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="kd_pos">Kode Pos</label>
                                            <input type="text" name="kd_pos" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['kd_pos']) ?>" maxlength="10">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="telp">Telepon</label>
                                            <input type="text" name="telp" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['telp']) ?>" maxlength="20">
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['email']) ?>" maxlength="50">
                                        </div>
                                        <div class="form-group">
                                            <label for="kebangsaan">Kebangsaan</label>
                                            <input type="text" name="kebangsaan" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['kebangsaan'] ?? 'Indonesia') ?>" maxlength="50">
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
                                            <label for="id_subbag" class="required-label">Subbagian</label>
                                            <select name="id_subbag" id="id_subbag" class="form-control field-required" required>
                                                <option value="">-- Pilih Subbagian --</option>
                                                <?= $subbagOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih - diperlukan untuk memilih jabatan</small>
                                            <small class="form-text dependency-note">⚠️ Harus dipilih sebelum memilih Jabatan</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <!-- ✅ WAJIB: Jabatan -->
                                        <div class="form-group">
                                            <label for="id_jab" class="required-label">Jabatan</label>
                                            <select name="id_jab" id="id_jab" class="form-control field-required" required>
                                                <option value="">-- Pilih Jabatan --</option>
                                                <?= $jabatanOptions ?>
                                            </select>
                                            <small class="form-text text-danger">Wajib dipilih</small>
                                            <small class="form-text dependency-note">⚠️ Pilih Subbagian terlebih dahulu</small>
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
                                            <input type="date" name="tgl_masuk" class="form-control" value="<?= $tgl_masuk ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tgl_keluar" id="tgl_keluar_label">Tanggal Keluar</label>
                                            <input type="date" name="tgl_keluar" id="tgl_keluar" class="form-control" value="<?= $tgl_keluar ?>">
                                            <small class="form-text text-danger" id="tgl_keluar_note" style="display: none;">Wajib diisi jika status tidak aktif</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group form-check mt-4">
                                            <input type="checkbox" name="aktif" class="form-check-input" id="aktif" <?= $employee['aktif'] == 1 ? 'checked' : '' ?>>
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
                                            <select id="sm_employee_edit_pend_akhir" name="pend_akhir" class="form-control">
                                                <option value="SD" <?= $employee['pend_akhir'] == 'SD' ? 'selected' : '' ?>>SD</option>
                                                <option value="SMP" <?= $employee['pend_akhir'] == 'SMP' ? 'selected' : '' ?>>SMP</option>
                                                <option value="SMA" <?= $employee['pend_akhir'] == 'SMA' ? 'selected' : '' ?>>SMA</option>
                                                <option value="D1" <?= $employee['pend_akhir'] == 'D1' ? 'selected' : '' ?>>D1</option>
                                                <option value="D2" <?= $employee['pend_akhir'] == 'D2' ? 'selected' : '' ?>>D2</option>
                                                <option value="D3" <?= $employee['pend_akhir'] == 'D3' ? 'selected' : '' ?>>D3</option>
                                                <option value="S1" <?= $employee['pend_akhir'] == 'S1' ? 'selected' : '' ?>>S1</option>
                                                <option value="S2" <?= $employee['pend_akhir'] == 'S2' ? 'selected' : '' ?>>S2</option>
                                                <option value="S3" <?= $employee['pend_akhir'] == 'S3' ? 'selected' : '' ?>>S3</option>
                                            </select>
                                        </div>
                                        <div class="form-group" id="sm_employee_edit_jurusan_group" <?= in_array($employee['pend_akhir'], ['SD', 'SMP'], true) ? 'hidden' : '' ?>>
                                            <label for="sm_employee_edit_jurusan">Jurusan <small class="text-muted">(Opsional)</small></label>
                                            <input type="text" id="sm_employee_edit_jurusan" name="jurusan" class="form-control" maxlength="100"
                                                   value="<?= htmlspecialchars($employee['jurusan'] ?? '') ?>" placeholder="Contoh: Teknik Informatika"
                                                   <?= in_array($employee['pend_akhir'], ['SD', 'SMP'], true) ? 'disabled' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="bank">Bank</label>
                                            <input type="text" name="bank" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['Bank']) ?>" maxlength="100">
                                        </div>
                                        <div class="form-group">
                                            <label for="no_rek">No. Rekening</label>
                                            <input type="text" name="no_rek" class="form-control" 
                                                   value="<?= htmlspecialchars($employee['no_rek']) ?>" maxlength="50">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor);?>"> 
                                <i class="fas fa-save"></i> Simpan Perubahan
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
            label.text('Pilih file foto baru');
        }
    });

    // Handle status aktif change
    function toggleTglKeluarRequired() {
        const isActive = $('#aktif').is(':checked');
        const tglKeluar = $('#tgl_keluar');
        const tglKeluarNote = $('#tgl_keluar_note');
        
        if (!isActive) {
            tglKeluar.prop('required', true);
            tglKeluarNote.show();
        } else {
            tglKeluar.prop('required', false);
            tglKeluarNote.hide();
        }
    }

    $('#aktif').change(toggleTglKeluarRequired);
    toggleTglKeluarRequired(); // Initial call

    function syncJurusanField() {
        const education = $('#sm_employee_edit_pend_akhir').val();
        const shouldShow = education && education !== 'SD' && education !== 'SMP';
        const group = $('#sm_employee_edit_jurusan_group');
        const input = $('#sm_employee_edit_jurusan');

        group.prop('hidden', !shouldShow);
        input.prop('disabled', !shouldShow);
        if (!shouldShow) input.val('');
    }

    $('#sm_employee_edit_pend_akhir').on('change', syncJurusanField);
    syncJurusanField();

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
        const deptId = $(this).val();
        
        // Reset dependent dropdowns
        $('#id_bag').html('<option value="">-- Pilih Bagian --</option>');
        $('#id_subbag').html('<option value="">-- Pilih Subbagian --</option>');
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(deptId) {
            loadDependentOptions('#id_bag', 'm_bag', 'id_bag', 'bagian', 'id_dept=' + deptId);
        }
    });
    
    $('#id_bag').change(function() {
        const bagId = $(this).val();
        
        // Reset dependent dropdowns
        $('#id_subbag').html('<option value="">-- Pilih Subbagian --</option>');
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(bagId) {
            loadDependentOptions('#id_subbag', 'm_subbag', 'id_subbag', 'subbag', 'id_bag=' + bagId);
        }
    });
    
    $('#id_subbag').change(function() {
        const subbagId = $(this).val();
        
        // Reset position dropdown
        $('#id_jab').html('<option value="">-- Pilih Jabatan --</option>');
        
        if(subbagId) {
            loadDependentOptions('#id_jab', 'm_jab', 'id_jab', 'jabatan', 'id_subbag=' + subbagId);
        }
    });

    // Real-time NIK duplicate check
    let nikDuplicateOk = true;
    $('#nik').on('blur', function() {
        const newNik = $(this).val().trim();
        const oldNik = $('#old_nik').val();
        const feedback = $('#nik_feedback');

        if (!newNik) {
            $(this).addClass('is-invalid');
            feedback.removeClass('text-muted text-success text-danger').addClass('text-danger').text('NIK tidak boleh kosong!');
            nikDuplicateOk = false;
            return;
        }

        if (newNik === oldNik) {
            $(this).removeClass('is-invalid').addClass('is-valid');
            feedback.removeClass('text-danger text-success').addClass('text-muted').text('Ubah NIK jika diperlukan');
            nikDuplicateOk = true;
            return;
        }

        // Check for duplicate via AJAX
        $.ajax({
            url: 'check_nik.php',
            method: 'GET',
            data: { nik: newNik, exclude: oldNik },
            dataType: 'json',
            success: function(res) {
                if (res.duplicate) {
                    $('#nik').addClass('is-invalid').removeClass('is-valid');
                    feedback.removeClass('text-muted text-success').addClass('text-danger').text('NIK sudah digunakan oleh karyawan lain!');
                    nikDuplicateOk = false;
                } else {
                    $('#nik').removeClass('is-invalid').addClass('is-valid');
                    feedback.removeClass('text-muted text-danger').addClass('text-success').text('NIK tersedia ✓');
                    nikDuplicateOk = true;
                }
            },
            error: function() {
                feedback.removeClass('text-success').addClass('text-danger').text('Gagal memverifikasi NIK, coba lagi.');
                nikDuplicateOk = false;
            }
        });
    });

    // Enhanced form validation
    $('#employeeForm').on('submit', function(e) {
        let isValid = true;
        const requiredFields = [
            '#nama_lengkap', '#id_dept', '#id_bag', '#id_subbag', '#id_jab', '#id_gol', '#id_shift'
        ];

        // Check NIK
        if (!$('#nik').val().trim()) {
            isValid = false;
            $('#nik').addClass('is-invalid');
            $('#nik_feedback').addClass('text-danger').text('NIK tidak boleh kosong!');
        }
        if (!nikDuplicateOk) {
            isValid = false;
            $('#nik').addClass('is-invalid');
        }

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

        // Check tanggal keluar if status is inactive
        if (!$('#aktif').is(':checked') && !$('#tgl_keluar').val()) {
            isValid = false;
            $('#tgl_keluar').addClass('is-invalid');
            $('#tgl_keluar_note').addClass('text-danger');
        }

        if (!isValid) {
            e.preventDefault();
            alert('Harap lengkapi semua field wajib yang ditandai dengan warna merah!\n\nPastikan:\n1. NIK valid dan tidak duplikat\n2. Semua field informasi pekerjaan terisi\n3. Tanggal keluar diisi jika status tidak aktif');
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

    // Real-time validation for tanggal keluar
    $('#tgl_keluar').on('input', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
            $('#tgl_keluar_note').removeClass('text-danger');
        }
    });
});
</script>
