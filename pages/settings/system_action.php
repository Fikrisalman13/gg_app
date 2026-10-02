<?php
session_start();
ini_set('max_execution_time', 0); // Disable timeout for backup/restore
set_time_limit(0);
require_once '../../koneksi.php';
require_once '../../includes/app_version.php';
date_default_timezone_set('Asia/Jakarta');
// Cek login
if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

$isAdmin = ($_SESSION['GroupId'] == 1);
$action = $_GET['action'] ?? '';

// Folder configuration
$backupDir = __DIR__ . '/../../backups';
if (!file_exists($backupDir)) mkdir($backupDir, 0777, true);

$releasesDir = __DIR__ . '/../../releases';
if (!file_exists($releasesDir)) mkdir($releasesDir, 0777, true);

/**
 * Redirect helper using Sessions for Flash Messages
 */
function redirect($status, $message) {
    $_SESSION['flash_status'] = $status;
    $_SESSION['flash_message'] = $message;
    header("Location: settings.php");
    exit();
}

/**
 * Internal Backup for Rollback
 */
function create_snapshot($backupDir, $version, $fileList = null) {
    if (!file_exists($backupDir)) mkdir($backupDir, 0777, true);
    
    $zipName = "rollback_snapshot_v{$version}.zip";
    $zipPath = "$backupDir/$zipName";
    $rootPath = realpath(__DIR__ . '/../../');

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return false;

    if ($fileList === null) {
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current, $key, $iterator) {
                    $exclude = ['backups', 'releases', '.git', 'node_modules', 'vendor'];
                    return !in_array($current->getFilename(), $exclude);
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
    } else {
        foreach ($fileList as $relativePath) {
            // Standardize path and prevent traversal
            $cleanRelative = ltrim(str_replace(['\\', '../'], ['/', ''], $relativePath), '/');
            $filePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRelative);
            
            if (file_exists($filePath) && !is_dir($filePath)) {
                $zip->addFile($filePath, $cleanRelative);
            }
        }
    }
    
    $zip->close();
    
    // Save metadata
    file_put_contents("$backupDir/rollback_info.json", json_encode([
        'version' => $version,
        'zip' => $zipName,
        'date' => date('Y-m-d H:i:s'),
        'target_files' => $fileList
    ]));
    
    return true;
}

switch ($action) {


    case 'web_backup':
        // Check if already in progress to prevent double execution (fix for browser retries)
        if (isset($_SESSION['backup_in_progress']) && $_SESSION['backup_in_progress'] > (time() - 300)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'error', 'message' => 'A backup process is already running. Please wait.']);
                exit();
            }
            redirect('error', "A backup process is already running.");
        }

        $_SESSION['backup_in_progress'] = time();
        session_write_close(); // Release session lock so other requests can proceed

        try {
            // Web Files ZIP Logic
            $zipName = 'WebBackup_' . date('Ymd_His') . '.zip';
            $zipPath = "$backupDir/$zipName";
            $rootPath = realpath(__DIR__ . '/../../');

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Could not create ZIP file.");
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    function ($current, $key, $iterator) {
                        $exclude = ['backups', 'releases', '.git', 'node_modules', 'vendor'];
                        return !in_array($current->getFilename(), $exclude);
                    }
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();

            session_start();
            unset($_SESSION['backup_in_progress']);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'success', 'file' => $zipName]);
                exit();
            }

            // Normal non-AJAX download
            if (file_exists($zipPath)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                exit();
            }
        } catch (Exception $e) {
            session_start();
            unset($_SESSION['backup_in_progress']);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit();
            }
            redirect('error', $e->getMessage());
        }
        break;

    case 'download_backup':
        $file = $_GET['file'] ?? '';
        $filePath = realpath("$backupDir/$file");

        // Safety check: ensure file is in backupDir
        if ($file && $filePath && strpos($filePath, realpath($backupDir)) === 0 && file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit();
        } else {
            redirect('error', "Backup file not found or invalid.");
        }
        break;




    case 'web_restore':
        if (!isset($_FILES['web_file'])) redirect('error', "No file uploaded.");
        
        $zipFile = $_FILES['web_file']['tmp_name'];
        $rootPath = realpath(__DIR__ . '/../../');
        
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($rootPath);
            $zip->close();
            redirect('success', "Web files restored successfully.");
        } else {
            redirect('error', "Failed to open ZIP file.");
        }

    case 'give_update':
        if (!$isAdmin) redirect('error', "Unauthorized.");
        if (!isset($_FILES['update_file']) || empty($_POST['new_version'])) redirect('error', "Missing data.");

        $version = trim($_POST['new_version']);
        $zipFile = $_FILES['update_file'];
        $destPath = "$releasesDir/update_v$version.zip";

        if (move_uploaded_file($zipFile['tmp_name'], $destPath)) {
            // Update the update.json for others to see
            $updateInfo = [
                'version' => $version,
                'release_date' => date('Y-m-d H:i:s'),
                'download_url' => "releases/update_v$version.zip",
                'changelog' => $_POST['changelog'] ?? "Version $version released by Administrator"
            ];
            
            // Update the public JSON
            file_put_contents("$releasesDir/update.json", json_encode($updateInfo));
            
            redirect('success', "Update released successfully. Users (including you) can now 'Check for Update' to apply it.");
        }
        redirect('error', "Failed to process update.");

    case 'check_update':
        $jsonPath = "$releasesDir/update.json";
        $response = ['update_available' => false];
        
        if (file_exists($jsonPath)) {
            $info = json_decode(file_get_contents($jsonPath), true);
            if (version_compare($info['version'], APP_VERSION, '>')) {
                $response = [
                    'update_available' => true,
                    'latest_version' => $info['version'],
                    'changelog' => $info['changelog']
                ];
            }
        }
        echo json_encode($response);
        exit();

    case 'install_update':
        $jsonPath = "$releasesDir/update.json";
        if (!file_exists($jsonPath)) {
            echo json_encode(['status' => 'error', 'message' => 'Update info not found.']);
            exit();
        }
        
        $info = json_decode(file_get_contents($jsonPath), true);
        $zipPath = __DIR__ . '/../../' . $info['download_url'];
        
        if (file_exists($zipPath)) {
            // 1. Get list of files in the update package
            $updateZip = new ZipArchive;
            $fileList = [];
            if ($updateZip->open($zipPath) === TRUE) {
                for ($i = 0; $i < $updateZip->numFiles; $i++) {
                    $filename = $updateZip->getNameIndex($i);
                    // Skip directories
                    if (substr($filename, -1) !== '/' && substr($filename, -1) !== '\\') {
                        $fileList[] = $filename;
                    }
                }
                $updateZip->close();
            }

            // Always include app_version.php to rollback numbering
            if (!in_array('includes/app_version.php', $fileList)) {
                $fileList[] = 'includes/app_version.php';
            }

            // --- CLEANUP OLD ROLLBACK FILES ---
            $oldSnapshots = glob($backupDir . '/rollback_snapshot_v*.zip');
            if ($oldSnapshots) {
                foreach ($oldSnapshots as $f) {
                    if (file_exists($f)) @unlink($f);
                }
            }
            if (file_exists("$backupDir/rollback_info.json")) @unlink("$backupDir/rollback_info.json");

            // 2. Create Snapshot of current version before update (only for impacted files)
            if (!create_snapshot($backupDir, APP_VERSION, $fileList)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create rollback snapshot. Update aborted.']);
                exit();
            }

            // 3. Extract Update
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo(__DIR__ . '/../../');
                $zip->close();
                
                // 4. Update app_version.php
                $ver = $info['version'];
                $versionContent = "<?php\nif (!defined('APP_VERSION')) {\n    define('APP_VERSION', '$ver');\n}\n";
                file_put_contents(__DIR__ . '/../../includes/app_version.php', $versionContent);

                // --- CLEANUP OLD RELEASE FILES ---
                $currentUpdateFile = basename($zipPath);
                $allUpdates = glob($releasesDir . '/*.zip');
                if ($allUpdates) {
                    foreach ($allUpdates as $f) {
                        if (basename($f) !== $currentUpdateFile) {
                            if (file_exists($f)) @unlink($f);
                        }
                    }
                }
                
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to extract update.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Update file missing.']);
        }
        exit();

    case 'rollback':
        $infoPath = "$backupDir/rollback_info.json";
        if (!file_exists($infoPath)) {
            $msg = "No rollback snapshot available.";
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit();
            }
            redirect('error', $msg);
        }

        $info = json_decode(file_get_contents($infoPath), true);
        $zipPath = "$backupDir/" . $info['zip'];
        $prevVersion = $info['version'];

        if (file_exists($zipPath)) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                // Restore files
                $zip->extractTo(__DIR__ . '/../../');
                $zip->close();

                // Restore version file
                $versionContent = "<?php\nif (!defined('APP_VERSION')) {\n    define('APP_VERSION', '$prevVersion');\n}\n";
                file_put_contents(__DIR__ . '/../../includes/app_version.php', $versionContent);

                unlink($infoPath); 

                $msg = "System rolled back to $prevVersion successfully.";
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    $_SESSION['flash_status'] = 'success';
                    $_SESSION['flash_message'] = $msg;
                    echo json_encode(['status' => 'success']);
                    exit();
                }
                redirect('success', $msg);
            } else {
                $err = "Failed to open rollback ZIP.";
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['status' => 'error', 'message' => $err]);
                    exit();
                }
                redirect('error', $err);
            }
        } else {
            $err = "Rollback file missing.";
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['status' => 'error', 'message' => $err]);
                exit();
            }
            redirect('error', $err);
        }
        exit();

    case 'download_template':
        if (!$isAdmin) redirect('error', "Unauthorized.");

        $zipName = 'Update_Package_Template.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            // Create folder structure placeholders
            $zip->addEmptyDir('pages');
            $zip->addEmptyDir('includes');
            $zip->addEmptyDir('assets');
            $zip->addEmptyDir('dist');
            $zip->addEmptyDir('plugins');

            // Add README guide
            $readme = "PANDUAN UPDATE SISTEM (GG_APP)\n";
            $readme .= "=============================\n\n";
            $readme .= "1. STRUKTUR FOLDER:\n";
            $readme .= "   - Masukkan file halaman baru/update ke folder `pages/` (sesuai sub-folder aslinya).\n";
            $readme .= "   - Masukkan file logika/koneksi baru ke folder `includes/`.\n";
            $readme .= "   - Masukkan file CSS/JS/Gambar ke folder `assets/` atau `dist/`.\n\n";
            $readme .= "2. CARA MEMBUAT PAKET UPDATE:\n";
            $readme .= "   - Pilih folder `pages`, `includes`, dll (JANGAN pilih folder utama gg_app).\n";
            $readme .= "   - Klik kanan > Send to > Compressed (zipped) folder.\n";
            $readme .= "   - Pastikan saat ZIP dibuka, langsung terlihat folder-folder tersebut.\n\n";
            $readme .= "3. CARA RELEASE:\n";
            $readme .= "   - Buka menu System Settings > Release Update.\n";
            $readme .= "   - Upload file ZIP, masukkan nomor versi baru, dan isi changelog.\n\n";
            $readme .= "4. PENTING:\n";
            $readme .= "   - JANGAN sertakan file `koneksi.php` kecuali ada perubahan database.\n";
            $readme .= "   - JANGAN sertakan folder `backups/` atau `releases/`.\n";
            $readme .= "   - Backup otomatis (rollback) akan tercipta sebelum update diterapkan.";
            
            $zip->addFromString('README_UPDATE_GUIDE.txt', $readme);
            $zip->close();

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            @unlink($zipPath);
            exit();
        } else {
            redirect('error', "Failed to create template ZIP.");
        }
        exit();

    default:
        redirect('error', "Invalid action.");
}
