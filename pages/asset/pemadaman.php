<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['UserId'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Get logged in user data
$userId = $_SESSION['UserId'];
$userDept = '';
$userName = '';

// Query to get user department
$sqlUser = "SELECT
                SMUserMs.UserName,
                m_lokasi.nama_lokasi,
                m_emp.nama_lengkap 
            FROM
                dbo.SMUserMs
                LEFT JOIN dbo.m_asset ON SMUserMs.EmpId = m_asset.id_emp
                LEFT JOIN dbo.m_lokasi ON m_asset.id_lokasi = m_lokasi.id_lokasi
                LEFT JOIN dbo.m_emp ON m_asset.id_emp = m_emp.id_emp
            WHERE
                SMUserMs.UserId = ?";

$params = [$userId];
$stmt = sqlsrv_query($conn, $sqlUser, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (sqlsrv_has_rows($stmt)) {
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $userDept = $user['nama_lokasi'] ?? '';
    $userName = $user['nama_lengkap'] ?? $user['UserName'] ?? '';
    
    if (!empty($userDept)) {
        $_SESSION['UserDept'] = $userDept;
        $_SESSION['FullName'] = $userName;
    }
}

if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
}

if (empty($userDept)) {
    $_SESSION['error'] = "Lokasi tidak ditemukan untuk user ini!";
    header('Location: ../dashboard.php');
    exit;
}

// Function to get assets by department
function getAssetsByDepartment($conn, $department) {
    $sql = "SELECT
                a.id_asset,
                a.kode_asset_seq,
                kat.nama_kategori,
                t.nama_tipe,
                m.nama_merk,
                l.nama_lokasi,
                a.serial_number,
                a.keterangan,
                s.nama_status,
                e.nama_lengkap AS pengguna 
            FROM
                dbo.m_asset AS a
                LEFT JOIN dbo.m_kategori AS kat ON a.id_kategori = kat.id_kategori
                LEFT JOIN dbo.m_lokasi AS l ON a.id_lokasi = l.id_lokasi
                LEFT JOIN dbo.m_status AS s ON a.id_status = s.id_status
                LEFT JOIN dbo.m_tipe AS t ON a.id_tipe = t.id_tipe
                LEFT JOIN dbo.m_merk AS m ON t.id_merk = m.id_merk
                LEFT JOIN dbo.m_emp AS e ON a.id_emp = e.id_emp
            WHERE
                l.nama_lokasi = ?
                AND kat.nama_kategori IN ('Komputer', 'Laptop', 'Printer') 
                AND s.nama_status = 'Used' 
            ORDER BY
                kat.nama_kategori ASC,
                a.kode_asset_seq ASC";
    
    $params = [$department];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    $assets = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $assets[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $assets;
}

// Function to save shutdown report
function saveShutdownReport($conn, $data) {
    $sql = "INSERT INTO dbo.report_pemadaman 
            (waktu, bagian, user_id, jumlah_dimatikan, jumlah_aktif, foto, keterangan, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['waktu'],
        $data['bagian'],
        $data['user_id'],
        $data['jumlah_dimatikan'],
        $data['jumlah_aktif'],
        $data['foto'],
        $data['keterangan'],
        date('Y-m-d H:i:s')
    ];
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    return $stmt !== false;
}

// Function to get shutdown reports
function getShutdownReports($conn, $userId = null) {
    $sql = "SELECT r.id, r.waktu, r.bagian, r.jumlah_dimatikan, r.jumlah_aktif, 
                   r.foto, r.keterangan, u.UserName as pelapor
            FROM dbo.report_pemadaman r
            LEFT JOIN dbo.SMUserMs u ON r.user_id = u.UserId";
    
    $params = [];
    if ($userId) {
        $sql .= " WHERE r.user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY r.waktu DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $reports = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Check if foto exists on server
            $fotoName = $row['foto'] ?? '';
            if (!empty($fotoName)) {
                $photoPath = __DIR__ . '/../../uploads/pemadaman/' . $fotoName;
                $row['foto_exists'] = file_exists($photoPath);
                $row['full_photo_path'] = $photoPath;
            } else {
                $row['foto_exists'] = false;
                $row['full_photo_path'] = '';
            }
            
            $reports[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $reports;
}

// Function Compress Gambar
function compressImage($source, $destination, $quality) {
    $imgInfo = @getimagesize($source);
    if ($imgInfo === false || empty($imgInfo['mime'])) {
        return false;
    }

    switch ($imgInfo['mime']) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            if ($image !== false) {
                imagepalettetotruecolor($image);
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source);
            break;
        default:
            return false;
    }

    if ($image === false) {
        return false;
    }

    $result = $imgInfo['mime'] === 'image/png'
        ? @imagepng($image, $destination, 9)
        : @imagejpeg($image, $destination, $quality);

    imagedestroy($image);
    return $result;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $assets_dimatikan = $_POST['assets'] ?? [];
    $total_dimatikan = count($assets_dimatikan);
    
    $all_assets = getAssetsByDepartment($conn, $userDept);
    $total_aktif = count($all_assets) - $total_dimatikan;
    
    $foto_name = '';
    $target_file = '';
    $foto = $_FILES['foto'] ?? null;
    $fotoDipilih = is_array($foto) && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($fotoDipilih) {
        $uploadError = (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE);
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran foto melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran foto melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'Upload foto terputus. Silakan pilih dan kirim ulang foto.',
            UPLOAD_ERR_NO_TMP_DIR => 'Penyimpanan sementara server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan foto.',
            UPLOAD_ERR_EXTENSION => 'Upload foto dihentikan oleh konfigurasi server.'
        ];

        if ($uploadError !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = $uploadMessages[$uploadError] ?? 'Upload foto gagal.';
            error_log("Upload foto pemadaman gagal: code={$uploadError}, user_id={$userId}");
        } elseif (!is_uploaded_file($foto['tmp_name'])) {
            $_SESSION['error'] = 'File foto tidak diterima dengan benar. Silakan pilih ulang.';
            error_log("Upload foto pemadaman bukan uploaded file, user_id={$userId}");
        } elseif ((int) $foto['size'] > 4000000) {
            $_SESSION['error'] = 'Ukuran foto terlalu besar (maksimal 4MB).';
        } else {
            $imgInfo = @getimagesize($foto['tmp_name']);
            $extensionsByMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif'
            ];
            $mime = $imgInfo['mime'] ?? '';

            if (!isset($extensionsByMime[$mime])) {
                $_SESSION['error'] = 'Foto harus berformat JPG, PNG, atau GIF.';
            } else {
                $target_dir = __DIR__ . '/../../uploads/pemadaman/';
                if ((!is_dir($target_dir) && !@mkdir($target_dir, 0777, true)) || !is_writable($target_dir)) {
                    $_SESSION['error'] = 'Penyimpanan foto server tidak tersedia. Hubungi administrator.';
                    error_log("Direktori upload pemadaman tidak writable: {$target_dir}");
                } else {
                    try {
                        $foto_name = 'pemadaman_' . bin2hex(random_bytes(16)) . '.' . $extensionsByMime[$mime];
                    } catch (Throwable $e) {
                        $_SESSION['error'] = 'Gagal membuat identitas foto. Silakan coba lagi.';
                        error_log('Gagal membuat nama foto pemadaman: ' . $e->getMessage());
                    }

                    if ($foto_name !== '') {
                        $target_file = $target_dir . $foto_name;
                        if (!compressImage($foto['tmp_name'], $target_file, 75)
                            || !is_file($target_file)
                            || filesize($target_file) === 0) {
                            if (is_file($target_file)) {
                                @unlink($target_file);
                            }
                            $foto_name = '';
                            $target_file = '';
                            $_SESSION['error'] = 'Foto gagal diproses. Pilih foto lain lalu coba kembali.';
                            error_log("Kompresi foto pemadaman gagal: mime={$mime}, user_id={$userId}");
                        }
                    }
                }
            }
        }
    }
    
    // Validasi form
    if (empty($assets_dimatikan)) {
        $_SESSION['error'] = "Pilih minimal satu perangkat yang akan dimatikan!";
    } elseif (empty(trim($_POST['keterangan'] ?? ''))) {
        $_SESSION['error'] = "Keterangan wajib diisi!";
    }
    
    // Simpan data jika tidak ada error
    if (!isset($_SESSION['error'])) {
        $report_data = [
            'waktu' => date('Y-m-d H:i:s'),
            'bagian' => $userDept,
            'user_id' => $userId,
            'jumlah_dimatikan' => $total_dimatikan,
            'jumlah_aktif' => $total_aktif,
            'foto' => $foto_name,
            'keterangan' => trim($_POST['keterangan'] ?? '')
        ];
        
        if (saveShutdownReport($conn, $report_data)) {
            $_SESSION['success'] = "Laporan pemadaman berhasil disimpan!";
            header('Location: pemadaman.php');
            exit;
        } else {
            if ($target_file !== '' && is_file($target_file)) {
                @unlink($target_file);
            }
            $_SESSION['error'] = "Gagal menyimpan laporan pemadaman!";
            error_log("INSERT report_pemadaman gagal, user_id={$userId}: " . print_r(sqlsrv_errors(), true));
        }
    }
}

$assets = getAssetsByDepartment($conn, $userDept);
$reports = getShutdownReports($conn, $userId);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
    .asset-card {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s ease;
        cursor: pointer;
        background: #fff;
        position: relative;
        overflow: hidden;
    }
    
    .asset-card:hover {
        border-color: #007bff;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transform: translateY(-2px);
    }
    
    .asset-card.selected {
        background-color: #e8f4ff;
        border-color: #007bff;
    }
    
    .asset-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    .asset-code {
        font-weight: 600;
        color: #495057;
        font-size: 0.95rem;
    }
    
    .kategori-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 10rem;
    }
    
    .komputer { background-color: #28a745 !important; }
    .laptop { background-color: #ffc107 !important; color: #212529; }
    .printer { background-color: #17a2b8 !important; }
    
    .asset-details {
        padding: 1rem;
        font-size: 0.875rem;
    }
    
    .asset-detail-row {
        display: flex;
        margin-bottom: 0.375rem;
        align-items: flex-start;
    }
    
    .asset-detail-label {
        min-width: 80px;
        font-weight: 500;
        color: #6c757d;
        flex-shrink: 0;
    }
    
    .asset-detail-value {
        flex: 1;
        color: #495057;
        word-break: break-word;
    }
    
    .photo-preview {
        max-width: 100%;
        max-height: 200px;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        margin-top: 0.5rem;
        display: none;
    }
    
    .photo-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        cursor: pointer;
        transition: all 0.2s ease;
        background-color: #f8f9fa;
    }
    
    .photo-thumbnail:hover {
        transform: scale(1.05);
        border-color: #007bff;
    }
    
    .photo-placeholder {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
        color: #6c757d;
        font-size: 0.875rem;
        cursor: default;
    }
    
    .photo-placeholder:hover {
        background-color: #e9ecef;
    }
    
    .summary-badge {
        font-size: 1rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
    }
    
    .asset-checkbox-wrapper {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        z-index: 1;
    }
    
    .assets-scroll-container {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .asset-counter {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .form-section {
        background: #fff;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        margin-bottom: 1rem;
    }
    
    .form-section-header {
        background: #f8f9fa;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }
    
    .form-section-body {
        padding: 1rem;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Scrollbar styling */
    .assets-scroll-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .assets-scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .assets-scroll-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    
    .assets-scroll-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .asset-detail-label {
            min-width: 70px;
        }
        
        .photo-thumbnail, .photo-placeholder {
            width: 50px;
            height: 50px;
        }
        
        .asset-code {
            font-size: 0.85rem;
        }
    }
    
    @media (max-width: 576px) {
        .asset-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .kategori-badge {
            align-self: flex-start;
        }
    }
</style>

<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Laporan Pemadaman Perangkat IT</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Pemadaman</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Info Box -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="fas fa-building"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Departemen/Lokasi</span>
                            <span class="info-box-number"><?= htmlspecialchars($userDept) ?></span>
                            <div class="progress">
                                <div class="progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="progress-description">
                                Total Perangkat: <?= count($assets) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-user"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Operator</span>
                            <span class="info-box-number"><?= htmlspecialchars($userName) ?></span>
                            <div class="progress">
                                <div class="progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="progress-description">
                                <?= date('d F Y') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i>Form Laporan Pemadaman</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="shutdownForm">
                        <div class="row">
                            <!-- Assets List -->
                            <div class="col-lg-6 mb-4 mb-lg-0">
                                <div class="form-section">
                                    <div class="form-section-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-desktop mr-2"></i>Daftar Perangkat IT
                                        </div>
                                        <div class="asset-counter">
                                            <span class="badge badge-primary" id="selectedBadge">0</span> dipilih dari 
                                            <span class="badge badge-secondary"><?= count($assets) ?></span>
                                        </div>
                                    </div>
                                    <div class="form-section-body">
                                        <div class="assets-scroll-container">
                                            <?php if (empty($assets)): ?>
                                                <div class="alert alert-info mb-0">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    Tidak ada perangkat IT yang aktif di departemen Anda.
                                                </div>
                                            <?php else: ?>
                                                <div class="row">
                                                    <?php foreach ($assets as $asset): 
                                                        $kategori_class = strtolower($asset['nama_kategori']);
                                                        $is_checked = isset($_POST['assets']) && in_array($asset['id_asset'], $_POST['assets']);
                                                    ?>
                                                        <div class="col-12">
                                                            <div class="asset-card <?= $is_checked ? 'selected' : '' ?>">
                                                                <div class="asset-checkbox-wrapper">
                                                                    <div class="custom-control custom-checkbox">
                                                                        <input type="checkbox" 
                                                                               class="custom-control-input asset-checkbox" 
                                                                               name="assets[]" 
                                                                               value="<?= $asset['id_asset'] ?>" 
                                                                               id="asset_<?= $asset['id_asset'] ?>"
                                                                               <?= $is_checked ? 'checked' : '' ?>>
                                                                        <label class="custom-control-label" for="asset_<?= $asset['id_asset'] ?>"></label>
                                                                    </div>
                                                                </div>
                                                                <div class="asset-header">
                                                                    <span class="asset-code"><?= htmlspecialchars($asset['kode_asset_seq']) ?></span>
                                                                    <span class="badge kategori-badge <?= $kategori_class ?>">
                                                                        <?= htmlspecialchars($asset['nama_kategori']) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="asset-details">
                                                                    <div class="asset-detail-row">
                                                                        <span class="asset-detail-label">Merk/Tipe:</span>
                                                                        <span class="asset-detail-value">
                                                                            <?= htmlspecialchars($asset['nama_merk'] ?? '-') ?>
                                                                            <?= !empty($asset['nama_tipe']) ? '/ ' . htmlspecialchars($asset['nama_tipe']) : '' ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="asset-detail-row">
                                                                        <span class="asset-detail-label">Serial:</span>
                                                                        <span class="asset-detail-value"><?= htmlspecialchars($asset['serial_number'] ?? '-') ?></span>
                                                                    </div>
                                                                    <div class="asset-detail-row">
                                                                        <span class="asset-detail-label">Pengguna:</span>
                                                                        <span class="asset-detail-value"><?= htmlspecialchars($asset['pengguna'] ?? '-') ?></span>
                                                                    </div>
                                                                    <div class="asset-detail-row">
                                                                        <span class="asset-detail-label">Status:</span>
                                                                        <span class="asset-detail-value">
                                                                            <span class="badge badge-success"><?= htmlspecialchars($asset['nama_status']) ?></span>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Report Details -->
                            <div class="col-lg-6">
                                <div class="form-section">
                                    <div class="form-section-header">
                                        <i class="fas fa-edit mr-2"></i>Detail Laporan
                                    </div>
                                    <div class="form-section-body">
                                        <!-- Photo Upload -->
                                        <div class="form-group">
                                            <label for="foto" class="font-weight-bold">
                                                <i class="fas fa-camera mr-1"></i>Foto Pendukung
                                                <small class="text-muted ml-1">(Opsional)</small>
                                            </label>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/jpeg,image/png,image/gif">
                                                <label class="custom-file-label" for="foto">Pilih file gambar...</label>
                                            </div>
                                            <small class="form-text text-muted">
                                                Maksimal 4MB. Format: JPG, PNG, GIF
                                            </small>
                                            <div class="mt-2 text-center">
                                                <img id="photoPreview" class="photo-preview img-fluid rounded" alt="Preview Foto">
                                            </div>
                                        </div>
                                        
                                        <!-- Description -->
                                        <div class="form-group">
                                            <label for="keterangan" class="font-weight-bold">
                                                <i class="fas fa-comment-dots mr-1"></i>Keterangan
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="keterangan" name="keterangan" rows="5" 
                                                      placeholder="Masukkan keterangan pemadaman (alasan, waktu pemadaman, dll)..." 
                                                      required><?= isset($_POST['keterangan']) ? htmlspecialchars($_POST['keterangan']) : '' ?></textarea>
                                            <div class="invalid-feedback" id="keteranganError">
                                                Keterangan wajib diisi
                                            </div>
                                        </div>
                                        
                                        <!-- Summary -->
                                        <div class="alert alert-light border mt-4">
                                            <h5 class="alert-heading mb-3">
                                                <i class="fas fa-chart-pie mr-2"></i>Ringkasan
                                            </h5>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="mb-2">
                                                        <span class="badge badge-secondary p-2 w-100">Total</span>
                                                    </div>
                                                    <h4 class="text-secondary"><?= count($assets) ?></h4>
                                                </div>
                                                <div class="col-4">
                                                    <div class="mb-2">
                                                        <span class="badge badge-danger p-2 w-100">Dimatikan</span>
                                                    </div>
                                                    <h4 class="text-danger" id="summaryDimatikan">0</h4>
                                                </div>
                                                <div class="col-4">
                                                    <div class="mb-2">
                                                        <span class="badge badge-success p-2 w-100">Aktif</span>
                                                    </div>
                                                    <h4 class="text-success" id="summaryAktif"><?= count($assets) ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Submit Button -->
                                        <div class="form-group mt-4">
                                            <button type="submit" name="submit_report" class="btn btn-primary btn-lg btn-block">
                                                <i class="fas fa-save mr-2"></i> Simpan Laporan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- History Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-2"></i>Riwayat Laporan Pemadaman</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="reportsTable" class="table table-bordered table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Waktu</th>
                                    <th width="15%">Lokasi</th>
                                    <th width="8%" class="text-center">Dimatikan</th>
                                    <th width="8%" class="text-center">Aktif</th>
                                    <th width="10%" class="text-center">Foto</th>
                                    <th width="24%">Keterangan</th>
                                    <th width="10%">Pelapor</th>
                                    <th width="5%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p class="h5">Belum ada laporan pemadaman</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rowIndex = 1; ?>
                                    <?php foreach ($reports as $report): 
                                        if ($report['waktu'] instanceof DateTime) {
                                            $waktu = $report['waktu']->format('d/m/Y H:i');
                                            $full_waktu = $report['waktu']->format('d F Y H:i:s');
                                        } else {
                                            $waktu = date('d/m/Y H:i', strtotime($report['waktu']));
                                            $full_waktu = date('d F Y H:i:s', strtotime($report['waktu']));
                                        }
                                        
                                        $foto_exists = $report['foto_exists'] ?? false;
                                        $foto_name = $report['foto'] ?? '';
                                        $full_photo_path = $report['full_photo_path'] ?? '';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $rowIndex++ ?></td>
                                            <td>
                                                <span title="<?= $full_waktu ?>"><?= $waktu ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($report['bagian']) ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-danger p-2 d-inline-block" style="min-width: 40px;">
                                                    <?= $report['jumlah_dimatikan'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-success p-2 d-inline-block" style="min-width: 40px;">
                                                    <?= $report['jumlah_aktif'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($foto_name) && $foto_exists && file_exists($full_photo_path)): 
                                                    $photo_path = '/gg_app/uploads/pemadaman/' . $foto_name;
                                                    // Add timestamp to prevent caching issues
                                                    $photo_url = $photo_path . '?t=' . time();
                                                ?>
                                                    <img src="<?= $photo_url ?>" 
                                                         class="photo-thumbnail" 
                                                         data-toggle="modal" 
                                                         data-target="#photoModal"
                                                         data-photo="<?= $photo_url ?>"
                                                         alt="Foto Laporan"
                                                         title="Klik untuk melihat">
                                                <?php elseif (!empty($foto_name)): ?>
                                                    <div class="photo-placeholder" title="File foto tidak ditemukan di server">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="photo-placeholder" title="Tidak ada foto">
                                                        <i class="fas fa-camera-slash"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($report['keterangan'])): ?>
                                                    <div class="text-truncate" style="max-width: 300px;" 
                                                         data-toggle="tooltip" 
                                                         title="<?= htmlspecialchars($report['keterangan']) ?>">
                                                        <?= htmlspecialchars($report['keterangan']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($report['pelapor']) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-danger btn-delete" 
                                                        data-id="<?= $report['id'] ?>" 
                                                        data-foto="<?= $foto_name ?>"
                                                        title="Hapus Laporan">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
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
    </section>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="fas fa-camera mr-2"></i>Foto Laporan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <div id="modalLoading" class="p-5 text-center" style="display: none;">
                    <div class="loading-spinner" style="width: 40px; height: 40px; margin: 0 auto;"></div>
                    <p class="mt-3">Memuat foto...</p>
                </div>
                <img id="modalPhoto" class="img-fluid rounded" src="" alt="Foto Laporan" style="display: none;">
                <div id="modalError" class="p-5 text-center" style="display: none;">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5>Foto Tidak Ditemukan</h5>
                    <p class="text-muted">File foto tidak dapat diakses di server</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- CSS Libraries -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

<!-- JS Libraries -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize components
    bsCustomFileInput.init();
    
    // Initialize DataTable with LOCAL language configuration
    var reportsTable = $('#reportsTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 10,
        "language": {
            "emptyTable": "Tidak ada data laporan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ laporan",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 laporan",
            "infoFiltered": "(disaring dari _MAX_ total laporan)",
            "lengthMenu": "Tampilkan _MENU_ laporan",
            "loadingRecords": "Memuat...",
            "processing": "Memproses...",
            "search": "Cari:",
            "zeroRecords": "Tidak ditemukan laporan yang sesuai",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        },
        "drawCallback": function(settings) {
            // Re-initialize tooltips after table redraw
            $('[data-toggle="tooltip"]').tooltip();
        },
        "initComplete": function(settings, json) {
            console.log('DataTable initialized successfully');
        }
    });
    
    // Show notifications
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: '<?= addslashes($_SESSION['success']) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#d4edda',
            iconColor: '#155724'
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($_SESSION['error']) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#f8d7da',
            iconColor: '#721c24'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    // Asset selection functionality
    $('.asset-card').click(function(e) {
        if (!$(e.target).is('input') && !$(e.target).is('label') && !$(e.target).hasClass('custom-control-label')) {
            const checkbox = $(this).find('.asset-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });
    
    $('.asset-checkbox').change(function() {
        const card = $(this).closest('.asset-card');
        const isChecked = $(this).is(':checked');
        
        card.toggleClass('selected', isChecked);
        updateCounters();
    });
    
    // Update counters function
    function updateCounters() {
        const totalAssets = <?= count($assets) ?>;
        const selectedCount = $('.asset-checkbox:checked').length;
        const remainingCount = totalAssets - selectedCount;
        
        $('#selectedBadge').text(selectedCount);
        $('#summaryDimatikan').text(selectedCount);
        $('#summaryAktif').text(remainingCount);
        
        // Update button state
        const submitBtn = $('button[name="submit_report"]');
        if (selectedCount === 0) {
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="fas fa-exclamation-triangle mr-2"></i> Pilih perangkat terlebih dahulu');
        } else {
            submitBtn.prop('disabled', false);
            submitBtn.html('<i class="fas fa-save mr-2"></i> Simpan Laporan');
        }
    }
    
    // Photo preview
    $('#foto').change(function() {
        const file = this.files[0];
        const preview = $('#photoPreview');
        
        if (file) {
            if (file.size > 4000000) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File terlalu besar!',
                    text: 'Ukuran file maksimal 4MB',
                    confirmButtonColor: '#007bff'
                });
                $(this).val('');
                preview.hide();
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.attr('src', e.target.result).show();
            }
            reader.onerror = function() {
                preview.hide();
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Gagal membaca file gambar',
                    confirmButtonColor: '#007bff'
                });
            };
            reader.readAsDataURL(file);
        } else {
            preview.hide();
        }
    });
    
    // Form validation
    $('#shutdownForm').submit(function(e) {
        const selectedCount = $('.asset-checkbox:checked').length;
        const keterangan = $('#keterangan').val().trim();
        
        // Reset validation
        $('#keterangan').removeClass('is-invalid');
        $('#keteranganError').hide();
        
        let isValid = true;
        
        if (selectedCount === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian!',
                text: 'Pilih minimal satu perangkat yang akan dimatikan!',
                confirmButtonColor: '#007bff'
            });
            isValid = false;
        }
        
        if (keterangan === '') {
            $('#keterangan').addClass('is-invalid');
            $('#keteranganError').show();
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.content-wrapper').offset().top
            }, 500);
        }
        
        return isValid;
    });
    
    // Show photo in modal
    $('#photoModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const photoUrl = button.data('photo');
        const modal = $(this);
        const modalPhoto = modal.find('#modalPhoto');
        const modalLoading = modal.find('#modalLoading');
        const modalError = modal.find('#modalError');
        
        // Hide all, show loading
        modalPhoto.hide();
        modalError.hide();
        modalLoading.show();
        
        // Preload image
        const img = new Image();
        img.onload = function() {
            modalPhoto.attr('src', photoUrl);
            modalLoading.hide();
            modalPhoto.show();
            modalError.hide();
        };
        img.onerror = function() {
            modalLoading.hide();
            modalPhoto.hide();
            modalError.show();
            
            // Don't show alert if error, just show error state in modal
            console.log('Foto tidak ditemukan:', photoUrl);
        };
        img.src = photoUrl;
    });
    
    // Reset modal when closed
    $('#photoModal').on('hidden.bs.modal', function() {
        $(this).find('#modalPhoto').attr('src', '').hide();
        $(this).find('#modalLoading').hide();
        $(this).find('#modalError').hide();
    });
    
    // Handle broken images in table
    $(document).on('error', '.photo-thumbnail', function() {
        const $this = $(this);
        $this.replaceWith(
            '<div class="photo-placeholder" title="File foto tidak ditemukan">' +
            '<i class="fas fa-exclamation-triangle"></i>' +
            '</div>'
        );
    });
    
    // Delete report
    $(document).on('click', '.btn-delete', function() {
        const reportId = $(this).data('id');
        const fotoName = $(this).data('foto');
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Laporan ini akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_pemadaman.php',
                    type: 'POST',
                    data: {
                        id: reportId,
                        foto: fotoName,
                        action: 'delete'
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.fire({
                            title: 'Menghapus...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    },
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                confirmButtonColor: '#007bff'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: response.message,
                                confirmButtonColor: '#007bff'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Terjadi kesalahan saat menghapus laporan: ' + error,
                            confirmButtonColor: '#007bff'
                        });
                    }
                });
            }
        });
    });
    
    // Initialize counters
    updateCounters();
    
    // Handle form persistence after validation error
    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        // Scroll to form
        setTimeout(function() {
            $('html, body').animate({
                scrollTop: $('.content-wrapper').offset().top
            }, 500);
        }, 100);
    <?php endif; ?>
});
</script>