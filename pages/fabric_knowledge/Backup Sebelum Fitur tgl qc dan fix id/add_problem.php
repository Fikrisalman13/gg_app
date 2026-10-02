<?php
// pages/fabric_knowledge/add_problem.php
session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Ambil hak akses user untuk menu Knowledge Base (MenuId = 123)
function checkPermissions($conn, $groupId, $menuId)
{
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

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data masalah.";
    header('Location: problem_list.php');
    exit;
}

// ====== Dropdown Kategori & Tag ======
function getDropdownOptions($conn)
{
    $options = ['kategori' => [], 'tag' => []];

    $sql = "SELECT id_kategori, nama_kategori FROM dbo.fab_m_kategori ORDER BY nama_kategori";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['kategori'][] = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    $sql = "SELECT id_tag, nama_tag, id_kategori FROM dbo.fab_m_tag ORDER BY nama_tag";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['tag'][] = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $options;
}
$dropdownOptions = getDropdownOptions($conn);

// ====== Fungsi Upload File (Simplified tanpa PhpSpreadsheet) ======
function processFileUpload($filePath, $conn, $userName)
{
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'total' => 0
    ];

    try {
        // Baca file CSV
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $row = 0;
            
            // Mulai transaction
            sqlsrv_begin_transaction($conn);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                
                // Skip header row
                if ($row == 1) continue;
                
                $results['total']++;

                // Ambil data dari setiap kolom
                $nocp = trim($data[0] ?? '');
                $color = trim($data[1] ?? '');
                $routing = trim($data[2] ?? '');
                $status_qc = trim($data[3] ?? '');
                $nama_tag = trim($data[4] ?? '');
                $deskripsi = trim($data[5] ?? '');
                $solusi = trim($data[6] ?? '');
                $pdr = !empty($data[7]) ? intval($data[7]) : null;
                $resep = trim($data[8] ?? '');
                $analis = trim($data[9] ?? '');
                $review = trim($data[10] ?? '');

                // Skip baris kosong
                if (empty($nocp) && empty($deskripsi)) {
                    continue;
                }

                // CARI ID_KATEGORI OTOMATIS BERDASARKAN NAMA_TAG
                $id_kategori = 1; // Default kategori jika tidak ditemukan
                $id_tag = null;

                if (!empty($nama_tag)) {
                    // Cari tag dan kategori berdasarkan nama_tag
                    $sql_tag = "SELECT t.id_tag, t.id_kategori, k.nama_kategori 
                               FROM dbo.fab_m_tag t 
                               LEFT JOIN dbo.fab_m_kategori k ON t.id_kategori = k.id_kategori 
                               WHERE t.nama_tag = ?";
                    $stmt_tag = sqlsrv_query($conn, $sql_tag, [$nama_tag]);
                    
                    if ($stmt_tag && $row_tag = sqlsrv_fetch_array($stmt_tag, SQLSRV_FETCH_ASSOC)) {
                        $id_tag = $row_tag['id_tag'];
                        $id_kategori = $row_tag['id_kategori'];
                    }
                    if ($stmt_tag) sqlsrv_free_stmt($stmt_tag);
                }

                try {
                    // Insert data masalah
                    $sql = "INSERT INTO dbo.fab_m_problem (
                                deskripsi, id_kategori, id_tag, nocp, color, routing, 
                                status_qc, pdr, resep, analis, review, solusi,
                                created_by, updated_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $params = [
                        $deskripsi,
                        $id_kategori, // Menggunakan id_kategori yang sudah ditemukan
                        !empty($id_tag) ? $id_tag : null,
                        !empty($nocp) ? $nocp : null,
                        !empty($color) ? $color : null,
                        !empty($routing) ? $routing : null,
                        !empty($status_qc) ? $status_qc : null,
                        !empty($pdr) ? $pdr : null,
                        !empty($resep) ? $resep : null,
                        !empty($analis) ? $analis : null,
                        !empty($review) ? $review : null,
                        !empty($solusi) ? $solusi : null,
                        $userName,
                        $userName
                    ];

                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt === false) {
                        throw new Exception("Gagal menyimpan baris $row");
                    }

                    // Dapatkan ID problem yang baru dibuat
                    $sql_id = "SELECT IDENT_CURRENT('dbo.fab_m_problem') as new_id";
                    $stmt_id = sqlsrv_query($conn, $sql_id);
                    $row_id = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
                    $new_problem_id = $row_id['new_id'];

                    // Buat record awal di tabel solution
                    $sql_solution = "INSERT INTO dbo.fab_m_solution (id_problem, status, created_by) VALUES (?, 'Open', ?)";
                    $stmt_solution = sqlsrv_query($conn, $sql_solution, [$new_problem_id, $userName]);
                    
                    if ($stmt_solution === false) {
                        throw new Exception("Gagal membuat record solusi untuk baris $row");
                    }

                    // Simpan history
                    $sql_history = "INSERT INTO dbo.fab_t_history (id_problem, aksi, catatan, created_by) 
                                    VALUES (?, 'Tambah', 'Masalah berhasil ditambahkan via File Upload', ?)";
                    $stmt_history = sqlsrv_query($conn, $sql_history, [$new_problem_id, $userName]);

                    $results['success']++;
                    
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Baris $row: " . $e->getMessage();
                }
            }
            fclose($handle);

            // Commit transaction
            sqlsrv_commit($conn);
        }

    } catch (Exception $e) {
        // Rollback transaction jika error
        sqlsrv_rollback($conn);
        throw new Exception("Error processing file: " . $e->getMessage());
    }

    return $results;
}

// ====== Proses Form Submit ======
$error = '';
$success = '';
$uploadResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Cek apakah ini upload file
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            
            // Validasi file
            $allowedTypes = [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/octet-stream'
            ];
            
            $fileType = $_FILES['excel_file']['type'];
            $fileSize = $_FILES['excel_file']['size'];
            $fileName = $_FILES['excel_file']['name'];
            
            if (!in_array($fileType, $allowedTypes) && !preg_match('/\.(csv|xls|xlsx)$/i', $fileName)) {
                throw new Exception("File harus berupa CSV atau Excel (.csv, .xls, .xlsx)");
            }
            
            if ($fileSize > 5 * 1024 * 1024) { // 5MB
                throw new Exception("File terlalu besar. Maksimal 5MB.");
            }
            
            // Upload file
            $uploadDir = __DIR__ . '/../../uploads/excel/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = uniqid() . '_' . date('Ymd_His') . '.' . $fileExtension;
            $filePath = $uploadDir . $uniqueFileName;
            
            if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $filePath)) {
                throw new Exception("Gagal mengupload file.");
            }
            
            // Proses file
            $uploadResults = processFileUpload($filePath, $conn, $_SESSION['UserName']);
            
            // Hapus file temporary
            unlink($filePath);
            
            $success = "Upload file berhasil! " . 
                      "Berhasil: {$uploadResults['success']}, " .
                      "Gagal: {$uploadResults['failed']}, " .
                      "Total: {$uploadResults['total']}";
                      
        } else {
            // Proses form manual
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $id_kategori = $_POST['id_kategori'] ?? '';
            $id_tag = $_POST['id_tag'] ?? '';
            $nocp = trim($_POST['nocp'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $routing = trim($_POST['routing'] ?? '');
            $status_qc = trim($_POST['status_qc'] ?? '');
            $pdr = $_POST['pdr'] ?? null;
            $resep = trim($_POST['resep'] ?? '');
            $analis = trim($_POST['analis'] ?? '');
            $review = trim($_POST['review'] ?? '');
            $solusi = trim($_POST['solusi'] ?? '');
            
            if (empty($id_kategori)) {
                throw new Exception("Kategori wajib diisi!");
            }

            // Validasi format nocp jika diisi
            if (!empty($nocp)) {
                if (strlen($nocp) > 20) {
                    throw new Exception("No Kartu Produksi terlalu panjang. Maksimal 20 karakter.");
                }
            }

            // Mulai transaction
            sqlsrv_begin_transaction($conn);

            // Insert data masalah
            $sql = "INSERT INTO dbo.fab_m_problem (
                        deskripsi, id_kategori, id_tag, nocp, color, routing, 
                        status_qc, pdr, resep, analis, review, solusi,
                        created_by, updated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $deskripsi,
                $id_kategori,
                !empty($id_tag) ? $id_tag : null,
                !empty($nocp) ? $nocp : null,
                !empty($color) ? $color : null,
                !empty($routing) ? $routing : null,
                !empty($status_qc) ? $status_qc : null,
                !empty($pdr) ? $pdr : null,
                !empty($resep) ? $resep : null,
                !empty($analis) ? $analis : null,
                !empty($review) ? $review : null,
                !empty($solusi) ? $solusi : null,
                $_SESSION['UserName'],
                $_SESSION['UserName']
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Gagal menyimpan data masalah: " . print_r(sqlsrv_errors(), true));
            }

            // Dapatkan ID problem yang baru dibuat
            $sql = "SELECT IDENT_CURRENT('dbo.fab_m_problem') as new_id";
            $stmt = sqlsrv_query($conn, $sql);
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $new_problem_id = $row['new_id'];

            // Buat record awal di tabel solution dengan status 'Open'
            $sql_solution = "INSERT INTO dbo.fab_m_solution (
                                id_problem, status, created_by
                             ) VALUES (?, 'Open', ?)";
            $params_solution = [$new_problem_id, $_SESSION['UserName']];
            $stmt_solution = sqlsrv_query($conn, $sql_solution, $params_solution);
            
            if ($stmt_solution === false) {
                throw new Exception("Gagal membuat record solusi: " . print_r(sqlsrv_errors(), true));
            }

            // Simpan history
            $sql_history = "INSERT INTO dbo.fab_t_history (id_problem, aksi, catatan, created_by) 
                            VALUES (?, 'Tambah', 'Masalah berhasil ditambahkan', ?)";
            $params_history = [$new_problem_id, $_SESSION['UserName']];
            $stmt_history = sqlsrv_query($conn, $sql_history, $params_history);
            
            if ($stmt_history === false) {
                throw new Exception("Gagal menyimpan history: " . print_r(sqlsrv_errors(), true));
            }

            // Commit transaction
            sqlsrv_commit($conn);
            
            $_SESSION['success'] = "Data masalah berhasil ditambahkan!";
            header('Location: problem_list.php');
            exit;
        }

    } catch (Exception $e) {
        // Rollback transaction jika error
        if (isset($conn)) {
            sqlsrv_rollback($conn);
        }
        $error = $e->getMessage();
    }
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

   
    <style>
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .form-note {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        .tag-loading {
            display: none;
            color: #007bff;
            font-size: 0.8em;
        }
        .upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-container:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .upload-container.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 20px;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
        .template-download {
            margin-top: 10px;
        }
        .upload-results {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tambah Masalah</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="problem_list.php">Knowledge Base</a></li>
                        <li class="breadcrumb-item active">Tambah Masalah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container-fluid">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    <?php echo htmlspecialchars($success); ?>
                    
                    <?php if ($uploadResults && !empty($uploadResults['errors'])): ?>
                        <div class="mt-3">
                            <h6>Detail Error:</h6>
                            <div class="upload-results">
                                <?php foreach ($uploadResults['errors'] as $errorMsg): ?>
                                    <div class="text-danger small"><?php echo htmlspecialchars($errorMsg); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah Masalah</h3>
                    <div class="card-tools">
                        <span class="badge badge-light">Status: <span class="badge badge-warning">Open</span></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="manual-tab" data-toggle="tab" href="#manual" role="tab">
                                <i class="fas fa-edit"></i> Input Manual
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="file-tab" data-toggle="tab" href="#file" role="tab">
                                <i class="fas fa-file-excel"></i> Upload File
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <!-- Tab Input Manual -->
                        <div class="tab-pane fade show active" id="manual" role="tabpanel">
                            <form method="POST" id="problemForm">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nocp">No Kartu Produksi</label>
                                                <input type="text" class="form-control" id="nocp" name="nocp" 
                                                       value="<?php echo htmlspecialchars($_POST['nocp'] ?? ''); ?>" 
                                                       placeholder="Contoh: D25I0979.01.0101"
                                                       maxlength="20">
                                                <div class="form-note">Format: DXXXXXXX.XX.XXXX</div>
                                            </div>

                                            <div class="form-group">
                                                <label for="color">Kode Warna</label>
                                                <input type="text" class="form-control" id="color" name="color" 
                                                       value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>" 
                                                       placeholder="Contoh: 6.3.11.0.0782"
                                                       maxlength="50">
                                            </div>

                                            <div class="form-group">
                                                <label for="routing">Routing</label>
                                                <input type="text" class="form-control" id="routing" name="routing" 
                                                       value="<?php echo htmlspecialchars($_POST['routing'] ?? ''); ?>" 
                                                       placeholder="Contoh: INSPECT FINAL"
                                                       maxlength="100">
                                            </div>

                                            <div class="form-group">
                                                <label for="status_qc">Status QC</label>
                                                <select class="form-control" id="status_qc" name="status_qc">
                                                    <option value="">Pilih Status QC</option>
                                                    <option value="Pass" <?= (($_POST['status_qc'] ?? '') == 'Pass') ? 'selected' : '' ?>>Pass</option>
                                                    <option value="Fail" <?= (($_POST['status_qc'] ?? '') == 'Fail') ? 'selected' : '' ?>>Fail</option>
                                                    <option value="Hold" <?= (($_POST['status_qc'] ?? '') == 'Hold') ? 'selected' : '' ?>>Hold</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="pdr">PDR</label>
                                                <input type="number" class="form-control" id="pdr" name="pdr" 
                                                       value="<?php echo htmlspecialchars($_POST['pdr'] ?? ''); ?>" 
                                                       placeholder="Contoh: 4"
                                                       min="0" max="100">
                                            </div>

                                            <div class="form-group">
                                                <label for="resep">Resep</label>
                                                <input type="text" class="form-control" id="resep" name="resep" 
                                                       value="<?php echo htmlspecialchars($_POST['resep'] ?? ''); ?>" 
                                                       placeholder="Contoh: MB"
                                                       maxlength="50">
                                            </div>

                                            <div class="form-group">
                                                <label for="analis">Analis</label>
                                                <input type="text" class="form-control" id="analis" name="analis" 
                                                       value="<?php echo htmlspecialchars($_POST['analis'] ?? ''); ?>" 
                                                       placeholder="Contoh: Dikdik"
                                                       maxlength="100">
                                            </div>

                                            <div class="form-group">
                                                <label for="review">Review</label>
                                                <input type="text" class="form-control" id="review" name="review" 
                                                       value="<?php echo htmlspecialchars($_POST['review'] ?? ''); ?>" 
                                                       placeholder="Contoh: OVER WARNA"
                                                       maxlength="200">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_kategori" class="required-field">Kategori</label>
                                                <select class="form-control" id="id_kategori" name="id_kategori" required>
                                                    <option value="">Pilih Kategori</option>
                                                    <?php foreach ($dropdownOptions['kategori'] as $row): ?>
                                                        <option value="<?= htmlspecialchars($row['id_kategori']) ?>" 
                                                            <?= (($_POST['id_kategori'] ?? '') == $row['id_kategori']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($row['nama_kategori']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-note">Pilih kategori yang paling sesuai dengan area masalah.</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_tag">Tag</label>
                                                <select class="form-control" id="id_tag" name="id_tag">
                                                    <option value="">Pilih Tag (Opsional)</option>
                                                    <?php foreach ($dropdownOptions['tag'] as $row): ?>
                                                        <option value="<?= htmlspecialchars($row['id_tag']) ?>" 
                                                            data-kategori="<?= htmlspecialchars($row['id_kategori']) ?>"
                                                            <?= (($_POST['id_tag'] ?? '') == $row['id_tag']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($row['nama_tag']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="tag-loading" id="tagLoading">
                                                    <i class="fas fa-spinner fa-spin"></i> Memuat tag...
                                                </div>
                                                <div class="form-note">Tag akan otomatis difilter berdasarkan kategori yang dipilih.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="deskripsi">Deskripsi Masalah</label>
                                                <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                                          rows="4" placeholder="Jelaskan detail masalah yang terjadi"><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
                                                <div class="form-note">Deskripsi yang lengkap akan membantu tim analisa memahami masalah dengan baik.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="solusi">Solusi</label>
                                                <textarea class="form-control" id="solusi" name="solusi" 
                                                          rows="3" placeholder="Masukkan solusi yang diberikan"><?php echo htmlspecialchars($_POST['solusi'] ?? ''); ?></textarea>
                                                <div class="form-note">Solusi yang telah diberikan untuk masalah ini.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan Masalah
                                    </button>
                                    <a href="problem_list.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                    </a>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo"></i> Reset Form
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab Upload File -->
                        <div class="tab-pane fade" id="file" role="tabpanel">
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Format File yang Didukung</h6>
                                    <p class="mb-2">File harus mengikuti format CSV dengan kolom berikut:</p>
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Kolom A</th>
                                                <th>Kolom B</th>
                                                <th>Kolom C</th>
                                                <th>Kolom D</th>
                                                <th>Kolom E</th>
                                                <th>Kolom F</th>
                                                <th>Kolom G</th>
                                                <th>Kolom H</th>
                                                <th>Kolom I</th>
                                                <th>Kolom J</th>
                                                <th>Kolom K</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>nocp</td>
                                                <td>color</td>
                                                <td>routing</td>
                                                <td>status_qc</td>
                                                <td>nama_tag</td>
                                                <td>deskripsi</td>
                                                <td>solusi</td>
                                                <td>PDR</td>
                                                <td>Resep</td>
                                                <td>Analis</td>
                                                <td>Review</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p class="mb-0 mt-2">
                                        <strong>Note:</strong> Baris pertama adalah header. Data dimulai dari baris kedua.
                                        <strong>Kategori akan otomatis ditentukan berdasarkan nama_tag.</strong>
                                    </p>
                                </div>

                                <form method="POST" enctype="multipart/form-data" id="fileForm">
                                    <div class="upload-container" id="fileUploadContainer">
                                        <i class="fas fa-file-upload fa-3x text-primary mb-3"></i>
                                        <h5>Upload File</h5>
                                        <p class="text-muted mb-2">Klik atau drag & drop file di sini</p>
                                        <small class="text-muted d-block">Maksimal 5MB</small>
                                        <small class="text-muted">Format: .csv, .xls, .xlsx</small>
                                        <input type="file" class="d-none" id="excel_file" name="excel_file" accept=".csv,.xls,.xlsx">
                                    </div>
                                    <div id="filePreview" class="text-center"></div>

                                    <div class="template-download text-center">
                                        <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadTemplate">
                                            <i class="fas fa-download"></i> Download Template CSV
                                        </button>
                                        <div class="form-note">Gunakan template ini untuk memastikan format yang benar</div>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-success" id="btnUploadFile" disabled>
                                    <i class="fas fa-upload"></i> Upload File
                                </button>
                                <a href="problem_list.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
(function(){
    // ====== FUNGSI UNTUK TAB MANUAL ======
    // Fungsi untuk filter tag berdasarkan kategori
    function filterTagsByCategory(categoryId) {
        const tagSelect = document.getElementById('id_tag');
        const tagLoading = document.getElementById('tagLoading');
        const currentSelectedTag = tagSelect.value;
        
        // Show loading
        tagLoading.style.display = 'block';
        
        // Reset tag options, keep the first empty option
        const emptyOption = tagSelect.querySelector('option[value=""]');
        tagSelect.innerHTML = '';
        if (emptyOption) {
            tagSelect.appendChild(emptyOption);
        }
        
        // Get all tag options from original data
        const allTags = <?php echo json_encode($dropdownOptions['tag']); ?>;
        
        // Filter tags by selected category
        const filteredTags = allTags.filter(tag => {
            return !categoryId || tag.id_kategori == categoryId;
        });
        
        // Add filtered tags to select
        filteredTags.forEach(tag => {
            const option = document.createElement('option');
            option.value = tag.id_tag;
            option.textContent = tag.nama_tag;
            option.setAttribute('data-kategori', tag.id_kategori);
            tagSelect.appendChild(option);
        });
        
        // Try to restore previous selection if it exists in filtered tags
        if (currentSelectedTag && filteredTags.some(tag => tag.id_tag == currentSelectedTag)) {
            tagSelect.value = currentSelectedTag;
        } else {
            tagSelect.value = '';
        }
        
        // Hide loading
        tagLoading.style.display = 'none';
    }

    // Event listener untuk perubahan kategori
    document.getElementById('id_kategori').addEventListener('change', function() {
        const selectedCategoryId = this.value;
        filterTagsByCategory(selectedCategoryId);
    });

    // Initialize tags based on selected category (if any)
    const initialCategoryId = document.getElementById('id_kategori').value;
    if (initialCategoryId) {
        filterTagsByCategory(initialCategoryId);
    }

    // Form validation untuk manual
    document.getElementById('problemForm').addEventListener('submit', function(e) {
        const kategori = document.getElementById('id_kategori').value;
        
        if (!kategori) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Kategori wajib diisi!',
                confirmButtonText: 'Mengerti'
            });
            return false;
        }

        // Show loading
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    });



    // ====== FUNGSI UNTUK TAB FILE UPLOAD ======
    const fileUploadContainer = document.getElementById('fileUploadContainer');
    const fileInput = document.getElementById('excel_file');
    const filePreview = document.getElementById('filePreview');
    const btnUploadFile = document.getElementById('btnUploadFile');
    const btnDownloadTemplate = document.getElementById('btnDownloadTemplate');

    // Download Template CSV
    btnDownloadTemplate.addEventListener('click', function() {
        // Create CSV content
        const csvContent = "nocp,color,routing,status_qc,nama_tag,deskripsi,solusi,PDR,Resep,Analis,Review\n" +
                          "D25I0979.01.0101,6.3.11.0.0782,INSPECT FINAL,Pass,Listing,\"BWS 8,1% BULU LENNO ADA YANG TIDAK KEBAKAR SEBAGIAN JARAK 1 S/D 2 METER\",\"LST(ACC-MEET PROD)B3,BULU LENNO 188 M U/LOT 6A,BWS 3%(ACC-QC)B2\",4,MB,Dikdik,OVER WARNA";
        
        // Create blob and download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "template_masalah.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Click on upload container to trigger file input
    fileUploadContainer.addEventListener('click', function() {
        fileInput.click();
    });

    // File input change event
    fileInput.addEventListener('change', function(e) {
        handleFile(e.target.files[0]);
    });

    // Drag and drop functionality
    fileUploadContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadContainer.classList.add('dragover');
    });

    fileUploadContainer.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileUploadContainer.classList.remove('dragover');
    });

    fileUploadContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadContainer.classList.remove('dragover');
        
        if (e.dataTransfer.files.length > 0) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    // Function to handle file
    function handleFile(file) {
        if (!file) return;

        // Validasi file type
        const allowedExtensions = /(\.csv|\.xls|\.xlsx)$/i;
        if (!allowedExtensions.exec(file.name)) {
            Swal.fire({
                icon: 'error',
                title: 'Format File Tidak Didukung',
                text: 'File harus berupa CSV atau Excel (.csv, .xls, .xlsx)'
            });
            return;
        }

        // Validasi file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Terlalu Besar',
                text: 'File melebihi 5MB'
            });
            return;
        }

        // Update preview
        filePreview.innerHTML = `
            <div class="alert alert-success mt-3">
                <i class="fas fa-check-circle"></i> File siap diupload: 
                <strong>${file.name}</strong> (${(file.size / 1024 / 1024).toFixed(2)} MB)
            </div>
        `;

        // Enable upload button
        btnUploadFile.disabled = false;
    }

    // Upload File button click
    btnUploadFile.addEventListener('click', function() {
        if (!fileInput.files[0]) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilih File',
                text: 'Silakan pilih file terlebih dahulu'
            });
            return;
        }

        Swal.fire({
            title: 'Upload File?',
            text: 'Data akan diproses dan disimpan ke database. Kategori akan otomatis ditentukan berdasarkan nama_tag.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Upload!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                btnUploadFile.disabled = true;
                btnUploadFile.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';

                // Submit form
                document.getElementById('fileForm').submit();
            }
        });
    });

    // Reset file preview when switching tabs
    document.getElementById('file-tab').addEventListener('click', function() {
        // Reset preview when switching to file tab
        filePreview.innerHTML = '';
        btnUploadFile.disabled = true;
        fileInput.value = '';
    });

})();
</script>
