<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak. Silakan login.");
}

$path = isset($_GET['file']) ? $_GET['file'] : '';
if (!$path) {
    die("File tidak ditentukan.");
}

// Security check: ensure path is within allowed directories
$allowed_dirs = [
    'kendaraan/uploads/', 
    'sertifikat/uploads/', 
    'kontrak/uploads/',
    'uploads/',   // V2 Dynamic Documents and Renewals
];
$is_allowed = false;
foreach ($allowed_dirs as $dir) {
    if (strpos($path, $dir) === 0) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    die("Akses ke file ini tidak diizinkan.");
}

$fullPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path);
if (!$fullPath || !file_exists($fullPath)) {
    die("File tidak ditemukan di server: " . htmlspecialchars($path));
}

// Read file and convert to Base64
$fileData = file_get_contents($fullPath);
$base64 = base64_encode($fileData);
$mimeType = mime_content_type($fullPath);
$fileName = basename($path);
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= htmlspecialchars($fileName) ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; background-color: #525659; overflow: hidden; }
        .viewer-container { height: 100vh; display: flex; flex-direction: column; }
        .viewer-header { 
            background: #323639; color: white; padding: 10px 20px; 
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3); z-index: 10;
        }
        .bg-theme { 
            background-color: var(--<?= $themeColor ?>) !important; 
        }
        /* Fallback if CSS variables aren't defined the same way */
        .header-primary { background-color: #007bff !important; }
        .header-success { background-color: #28a745 !important; }
        .header-info { background-color: #17a2b8 !important; }
        .header-warning { background-color: #ffc107 !important; }
        .header-danger { background-color: #dc3545 !important; }
        .header-dark { background-color: #343a40 !important; }

        iframe { flex: 1; border: none; width: 100%; height: 100%; }
        .btn-download { background: #fff; color: #333; border: none; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; }
        .btn-download:hover { background: #eee; color: #000; }
    </style>
</head>
<body>
    <div class="viewer-container">
        <div class="viewer-header header-<?= $themeColor ?>">
            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60%;">
                <i class="fas fa-file-pdf mr-2" style="color: #fff;"></i>
                <span style="font-weight: bold;"><?= htmlspecialchars($fileName) ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="view_file.php?path=<?= urlencode($path) ?>&download=1" class="btn-download">
                    <i class="fas fa-download mr-1"></i> Unduh File
                </a>
                <button onclick="closeModal()" style="background: none; border: none; color: white; cursor: pointer; font-size: 1.5rem; line-height: 1; padding: 0 5px;" title="Tutup">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Iframe with Base64 data to bypass IDM -->
        <iframe src="data:<?= $mimeType ?>;base64,<?= $base64 ?>" title="<?= htmlspecialchars($fileName) ?>"></iframe>
    </div>

    <script>
        function closeModal() {
            // Cek apakah halaman ini dibuka di dalam iframe modal
            if (window.parent && window.parent.$) {
                window.parent.$('#modalPreview').modal('hide');
            } else {
                // Jika dibuka di tab baru, tutup tabnya
                window.close();
            }
        }
    </script>
</body>
</html>
