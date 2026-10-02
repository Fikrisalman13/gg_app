<?php
// Start session and output buffering at the very beginning
session_start();

// Include dependencies (use __DIR__ to locate files reliably)
$rootDir = realpath(__DIR__ . '/..');
if ($rootDir === false) $rootDir = __DIR__ . '/..';
// koneksi.php is in project root
require_once $rootDir . '/../koneksi.php';
// includes are in project root/includes
require_once $rootDir . '/../includes/header.php';
require_once $rootDir . '/../includes/sidebar.php';
?>
<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<?php

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

// Untuk simpel, gunakan menu yang sama dengan User Manager (hanya admin yang sama yang bisa akses)
define('TTD_USER_MANAGER_MENU_ID', 1294);

function checkPermissionsTtd($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    return $permissions;
}

function getAllUsersSimple($conn) {
    // Return active employees (m_emp) and include linked UserId from SMUserMs if any
    $sql = "SELECT m.id_emp AS EmpId,
                   ISNULL(m.nama_lengkap, m.nik) AS FullName,
                   (SELECT TOP 1 u.UserId FROM dbo.SMUserMs u WHERE u.EmpId = m.id_emp) AS LinkedUserId
            FROM dbo.m_emp m
            WHERE ISNULL(m.aktif,0) = 1
            ORDER BY FullName";

    $stmt = sqlsrv_query($conn, $sql);
    $users = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $users[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $users;
}

function getAllTtdTemplates($conn) {
    $sql = "SELECT t.Id, t.UserId, t.UserName, t.GroupRole, t.SignaturePath, t.IsActive, t.CreatedAt, t.UpdatedAt,
                   u.UserName AS LoginName,
                   ISNULL(e.nama_lengkap, ISNULL(u.UserName, t.UserName)) AS FullName,
                   e.id_emp AS EmpId
            FROM dbo.User_TTD_Template_Umum t
            LEFT JOIN dbo.SMUserMs u ON t.UserId = u.UserId AND t.UserId > 0
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            ORDER BY FullName, t.GroupRole";

    $stmt = sqlsrv_query($conn, $sql);
    $rows = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $rows;
}

// Check permissions
$permissions = checkPermissionsTtd($conn, $_SESSION['GroupId'], TTD_USER_MANAGER_MENU_ID);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Handle form actions (add/delete/toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $permissions['CanAdd'] == 1) {
        // user_id contains EmpId from m_emp for employee mode.
        // Manual mode allows storing a template without m_emp/SMUserMs account.
        $userSource  = $_POST['user_source'] ?? 'employee';
        $empId       = intval($_POST['user_id'] ?? 0);
        $manualName  = trim($_POST['manual_user_name'] ?? '');
        $groupRole   = trim($_POST['group_role'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $canvasData  = $_POST['canvas_data'] ?? '';

        if ($groupRole === '') {
            $_SESSION['error'] = 'Role wajib diisi.';
            header('Location: ttd_user.php');
            exit;
        }
        if ($userSource === 'manual') {
            if ($manualName === '') {
                $_SESSION['error'] = 'Nama karyawan manual wajib diisi.';
                header('Location: ttd_user.php');
                exit;
            }
        } elseif ($empId <= 0) {
            $_SESSION['error'] = 'User wajib dipilih.';
            header('Location: ttd_user.php');
            exit;
        }

        // Resolve identity.
        // Manual templates intentionally use UserId=0 and typed UserName.
        $userId = 0;
        $userName = 'User';
        if ($userSource === 'manual') {
            $userName = $manualName;
        } else {
            $sqlEmp = "SELECT id_emp, nama_lengkap FROM dbo.m_emp WHERE id_emp = ?";
            $stmtEmp = sqlsrv_query($conn, $sqlEmp, [(int)$empId]);
            if ($stmtEmp && $empRow = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
                // Always prefer and save the employee full name
                $userName = $empRow['nama_lengkap'] ?: $empRow['id_emp'];
            }
            if ($stmtEmp) sqlsrv_free_stmt($stmtEmp);

            // Check if there's a linked user account and preserve its UserId, but do NOT overwrite full name
            // Primary: lookup by EmpId (cast to int explicitly to avoid type mismatch)
            $sqlUser = "SELECT TOP 1 UserId, UserName FROM dbo.SMUserMs WHERE EmpId = ?";
            $stmtUser = sqlsrv_query($conn, $sqlUser, [(int)$empId]);
            if ($stmtUser && $rowU = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
                $userId = (int)$rowU['UserId'];
            }
            if ($stmtUser) sqlsrv_free_stmt($stmtUser);

            // Fallback: jika EmpId lookup gagal (UserId masih 0), coba cocokkan via nama_lengkap
            if ($userId === 0 && $userName !== 'User' && $userName !== '') {
                $sqlUserFallback = "SELECT TOP 1 u.UserId FROM dbo.SMUserMs u
                                    JOIN dbo.m_emp e ON u.EmpId = e.id_emp
                                    WHERE e.nama_lengkap = ?";
                $stmtFallback = sqlsrv_query($conn, $sqlUserFallback, [$userName]);
                if ($stmtFallback && $rowFb = sqlsrv_fetch_array($stmtFallback, SQLSRV_FETCH_ASSOC)) {
                    $userId = (int)$rowFb['UserId'];
                }
                if ($stmtFallback) sqlsrv_free_stmt($stmtFallback);
            }
        }

        // Folder upload (project root '/gg_app/uploads')
        $uploadBase = realpath($rootDir . '/../uploads');
        if ($uploadBase === false) {
            $tryDir = $rootDir . '/../uploads';
            if (!is_dir($tryDir)) {
                @mkdir($tryDir, 0777, true);
            }
            $uploadBase = realpath($tryDir);
        }

        if ($uploadBase === false) {
            $_SESSION['error'] = 'Folder upload tidak tersedia.';
            header('Location: ttd_user.php');
            exit;
        }

        $ttdDir = $uploadBase . DIRECTORY_SEPARATOR . 'ttd';
        if (!is_dir($ttdDir)) {
            @mkdir($ttdDir, 0777, true);
        }

        if (!is_dir($ttdDir) || !is_writable($ttdDir)) {
            $_SESSION['error'] = 'Folder ttd tidak bisa diakses/tulis.';
            header('Location: ttd_user.php');
            exit;
        }

        $imgPathRel = '';

        // Jika user memilih upload file, simpan file original (preserve quality) terlebih dulu.
        if (isset($_FILES['ttd_file']) && $_FILES['ttd_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['ttd_file']['tmp_name'];
            $name    = $_FILES['ttd_file']['name'];
            $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $_SESSION['error'] = 'Format file harus PNG/JPG.';
                header('Location: ttd_user.php');
                exit;
            }

            // Build human-friendly sanitized filename: "FullName - Role - DD-MM-YY.ext"
            $labelName = trim($userName);
            // Remove problematic filesystem characters and compress spaces
            $labelName = preg_replace('/[\/\\:\*\?"<>\|]+/', '', $labelName);
            $labelName = preg_replace('/\s+/', ' ', $labelName);
            $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
            $labelName = str_replace(' ', '_', $labelName);
            $safeRole = preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole);
            $safeRole = str_replace(' ', '_', trim($safeRole));
            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $ext;
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

            // Try to resize to 1294x643 (exact) using GD. If GD not available or fails, fallback to moving original file.
            $targetW = 1294; $targetH = 643;
            $resized = false;
            if (function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
                $info = @getimagesize($tmpName);
                if ($info !== false) {
                    $mime = $info['mime'] ?? '';
                    try {
                        if ($mime === 'image/png') {
                            $src = @imagecreatefrompng($tmpName);
                        } else {
                            $src = @imagecreatefromjpeg($tmpName);
                        }
                        if ($src !== false) {
                            $dst = imagecreatetruecolor($targetW, $targetH);
                            // fill white background
                            $white = imagecolorallocate($dst, 255,255,255);
                            imagefill($dst, 0, 0, $white);

                            // compute scale to cover while preserving aspect
                            $scale = max($targetW / imagesx($src), $targetH / imagesy($src));
                            $dw = (int)(imagesx($src) * $scale);
                            $dh = (int)(imagesy($src) * $scale);
                            $dx = (int)(($targetW - $dw) / 2);
                            $dy = (int)(($targetH - $dh) / 2);

                            imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $dw, $dh, imagesx($src), imagesy($src));

                            if ($ext === 'png') {
                                imagepng($dst, $fullPath, 0);
                            } else {
                                imagejpeg($dst, $fullPath, 95);
                            }
                            $resized = file_exists($fullPath);
                        }
                    } catch (Exception $e) {
                        // ignore and fallback
                        @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - gd resize exception: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }

            if (!$resized) {
                if (!move_uploaded_file($tmpName, $fullPath)) {
                    @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - gagal move_uploaded_file for empId={$empId} userId={$userId} tmp={$tmpName} dst={$fullPath}\n", FILE_APPEND);
                    $_SESSION['error'] = 'Gagal menyimpan file upload.';
                    header('Location: ttd_user.php');
                    exit;
                }
            }

            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
            @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - berhasil menyimpan upload (resized={$resized}): $fullPath -> $imgPathRel (empId={$empId}, userId={$userId})\n", FILE_APPEND);
        } else {
            // Prioritas kedua: canvas_data (lebih robust) — digunakan jika user menggambar ATAU hasil crop upload
            if (!empty($canvasData) && strpos($canvasData, 'data:image') === 0) {
                $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $canvasData);
                $base64 = str_replace(' ', '+', $base64);
                $data = base64_decode($base64);

                if ($data !== false) {
                    $src = @imagecreatefromstring($data);
                    if ($src !== false) {
                        // Force PNG
                        $saveExt = 'png';
                        // Build human-friendly sanitized filename for canvas-derived image
                        $labelName = trim($userName);
                        $labelName = preg_replace('/[\/\\:\*\?"<>\|]+/', '', $labelName);
                        $labelName = preg_replace('/\s+/', ' ', $labelName);
                        $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', $labelName);
                        $labelName = str_replace(' ', '_', $labelName);
                        $safeRole = preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole);
                        $safeRole = str_replace(' ', '_', trim($safeRole));
                        $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . '.' . $saveExt;
                        $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;

                        // Process Transparency (Remove White Background)
                        $w = imagesx($src);
                        $h = imagesy($src);
                        
                        $dst = imagecreatetruecolor($w, $h);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                        imagefill($dst, 0, 0, $transparent);
                        
                        // Copy source to dest
                        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                        
                        // Scan and make white/light pixels transparent
                        for ($y = 0; $y < $h; $y++) {
                            for ($x = 0; $x < $w; $x++) {
                                $rgb = imagecolorat($dst, $x, $y);
                                $r = ($rgb >> 16) & 0xFF;
                                $g = ($rgb >> 8) & 0xFF;
                                $b = $rgb & 0xFF;
                                
                                // Threshold: if pixel is light (white paper), make it transparent
                                if ($r > 210 && $g > 210 && $b > 210) {
                                    imagesetpixel($dst, $x, $y, $transparent);
                                }
                            }
                        }
                        
                        imagepng($dst, $fullPath, 0);
                        
                        if (file_exists($fullPath)) {
                            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
                            @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - berhasil menulis canvas file (transparency applied): $fullPath -> $imgPathRel\n", FILE_APPEND);
                        }
                    }
                } else {
                    @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - base64_decode returned false for empId={$empId}\n", FILE_APPEND);
                }
            }
        }

        if ($imgPathRel === '') {
            $_SESSION['error'] = 'Gambar tanda tangan (canvas atau upload) wajib diisi.';
            header('Location: ttd_user.php');
            exit;
        }

        $sqlIns = "INSERT INTO User_TTD_Template_Umum (UserId, UserName, GroupRole, SignaturePath, IsActive, CreatedAt)
                VALUES (?, ?, ?, ?, ?, GETDATE())";
        // Ensure we pass an integer (0 if no linked UserId) to avoid NULL insertion
        $userIdToInsert = (int)$userId;
        $stmtIns = sqlsrv_query($conn, $sqlIns, [$userIdToInsert, $userName, $groupRole, $imgPathRel, $isActive]);

        if ($stmtIns === false) {
            $_SESSION['error'] = 'Gagal menyimpan data TTD.';
            // Log SQL error for debugging
            $errs = print_r(sqlsrv_errors(), true);
            @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - DB INSERT gagal for empId={$empId} userId={$userIdToInsert} path={$imgPathRel} errors={$errs}\n", FILE_APPEND);
        } else {
            $_SESSION['success'] = 'Data TTD berhasil ditambahkan.';
            @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - DB INSERT sukses for empId={$empId} userId={$userIdToInsert} path={$imgPathRel}\n", FILE_APPEND);
        }

        header('Location: ttd_user.php');
        exit;
    }

    if ($action === 'delete' && $permissions['CanDelete'] == 1) {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get template info
            $sqlGet = "SELECT Id, SignaturePath, UserName, GroupRole FROM User_TTD_Template_Umum WHERE Id = ?";
            $stmtGet = sqlsrv_query($conn, $sqlGet, [$id]);
            if ($stmtGet === false) {
                $_SESSION['error'] = 'Gagal mengambil data TTD: ' . print_r(sqlsrv_errors(), true);
                header('Location: ttd_user.php'); exit;
            }
            $row = sqlsrv_fetch_array($stmtGet, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtGet);

            $sigPath = trim($row['SignaturePath'] ?? '');
            $sigBase = $sigPath !== '' ? basename($sigPath) : '';
            $deletedBy = isset($_SESSION['UserId']) ? intval($_SESSION['UserId']) : null;
            $deletedAt = date('Y-m-d H:i:s');

            // Prepare log dir
            $uploadBase = realpath($rootDir . '/../uploads');
            if ($uploadBase === false) {
                $tryDir = $rootDir . '/../uploads';
                if (!is_dir($tryDir)) @mkdir($tryDir, 0777, true);
                $uploadBase = realpath($tryDir);
            }
            $logRelDir = '/gg_app/uploads/ttd/delete_logs/';
            $logDir = $uploadBase . DIRECTORY_SEPARATOR . 'ttd' . DIRECTORY_SEPARATOR . 'delete_logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

            $logFilename = 'delete_umum_' . $id . '_' . date('Ymd_His') . '.txt';
            $logFullPath = $logDir . DIRECTORY_SEPARATOR . $logFilename;
            $logRelativePath = $logRelDir . $logFilename;

            $logLines = [];
            $logLines[] = "Delete log for Umum TTD Template Id: {$id}";
            $logLines[] = "UserName: " . ($row['UserName'] ?? '');
            $logLines[] = "GroupRole: " . ($row['GroupRole'] ?? '');
            $logLines[] = "SignaturePath: {$sigPath}";
            $logLines[] = "SignatureFile: {$sigBase}";
            $logLines[] = "DeletedByUserId: " . ($deletedBy !== null ? $deletedBy : 'unknown');
            $logLines[] = "DeletedAt: {$deletedAt}";
            $logLines[] = "----- Affected tables (search results) -----";

            // initialize found flag (will be set true if any references discovered)
            $foundAny = false;

            // If we have a signature path, attempt to discover which tables/columns reference it
            if ($sigPath !== '') {
                // Find candidate columns that are textual and likely hold signatures/paths
                $colSql = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME 
                           FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE DATA_TYPE IN ('varchar','nvarchar','text','char','nchar') 
                             AND (COLUMN_NAME LIKE '%ttd%' OR COLUMN_NAME LIKE '%sign%' OR COLUMN_NAME LIKE '%signature%' OR COLUMN_NAME LIKE '%path%')";
                $stmtCols = @sqlsrv_query($conn, $colSql);
                $foundAny = false;
                if ($stmtCols !== false) {
                    while ($col = sqlsrv_fetch_array($stmtCols, SQLSRV_FETCH_ASSOC)) {
                        $schema = $col['TABLE_SCHEMA'];
                        $table  = $col['TABLE_NAME'];
                        $column = $col['COLUMN_NAME'];

                        // Build safe fully-qualified table name
                        $fqTable = "[" . $schema . "].[" . $table . "]";
                        // Skip the template table itself to avoid counting the row we're about to delete
                        if (strtolower($table) === 'user_ttd_template_umum') {
                            continue;
                        }
                        // Count exact matches or contains filename
                        $param1 = $sigPath;
                        $param2 = '%' . $sigBase . '%';
                        $countSql = "SELECT COUNT(1) AS cnt FROM {$fqTable} WHERE {$column} = ? OR {$column} LIKE ?";
                        $stmtCount = @sqlsrv_query($conn, $countSql, [$param1, $param2]);
                        if ($stmtCount !== false) {
                            $cRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
                            $cnt = intval($cRow['cnt'] ?? 0);
                            sqlsrv_free_stmt($stmtCount);
                            if ($cnt > 0) {
                                $foundAny = true;
                                $logLines[] = "{$fqTable}.{$column} => {$cnt} row(s)";
                            }
                        }
                    }
                    sqlsrv_free_stmt($stmtCols);
                }
                if (!$foundAny) {
                    $logLines[] = "No matching references found in columns named like '%ttd%/sign%/signature%/path%'.";
                    $logLines[] = "You may need to search other tables or add a usage tracking table for more robust checks.";
                }
            } else {
                $logLines[] = "No SignaturePath found for this template.";
            }

            $logLines[] = "----- End of affected list -----";
            $logContent = implode(PHP_EOL, $logLines) . PHP_EOL;

            // write log file (append in case of concurrent)
            @file_put_contents($logFullPath, $logContent, FILE_APPEND | LOCK_EX);

            // If any references were found, abort deletion to avoid breaking documents
            if ($foundAny) {
                $_SESSION['error'] = 'Gagal menghapus template: file ini masih digunakan oleh satu atau beberapa dokumen. Detail tercatat di: ' . $logRelativePath;
                header('Location: ttd_user.php');
                exit;
            }

            // Now attempt to delete the file (original behavior). If you prefer to keep files, remove this block.
            if ($sigPath !== '') {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . $sigPath;
                if (file_exists($filePath)) {
                    @unlink($filePath); // ignore failure, log already saved
                }
            }

            // Delete DB record (original behavior)
            $sqlDel = "DELETE FROM User_TTD_Template_Umum WHERE Id = ?";
            $stmtDel = sqlsrv_query($conn, $sqlDel, [$id]);
            if ($stmtDel === false) {
                // if DB delete fails, append error to log
                $err = print_r(sqlsrv_errors(), true);
                @file_put_contents($logFullPath, "DB delete failed: {$err}" . PHP_EOL, FILE_APPEND | LOCK_EX);
                $_SESSION['error'] = 'Gagal menghapus data TTD: ' . $err;
            } else {
                $_SESSION['success'] = 'Data TTD berhasil dihapus Beserta Filenya.';
            }
        }
        header('Location: ttd_user.php');
        exit;
    }

    if ($action === 'toggle' && $permissions['CanEdit'] == 1) {
        $id      = intval($_POST['id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            $sqlUp = "UPDATE User_TTD_Template_Umum SET IsActive = ?, UpdatedAt = GETDATE() WHERE Id = ?";
            $stmtUp = sqlsrv_query($conn, $sqlUp, [$isActive, $id]);

            if ($stmtUp === false) {
                $_SESSION['error'] = 'Gagal mengubah status aktif.';
            } else {
                $_SESSION['success'] = 'Status aktif berhasil diubah.';
            }
        }
        header('Location: ttd_user.php');
        exit;
    }

    if ($action === 'edit' && $permissions['CanEdit'] == 1) {
        $id = intval($_POST['edit_id'] ?? 0);
        if ($id <= 0) { $_SESSION['error'] = 'ID template tidak valid.'; header('Location: ttd_user.php'); exit; }

        $sqlGet = "SELECT Id, UserId, UserName, GroupRole, SignaturePath FROM User_TTD_Template_Umum WHERE Id = ?";
        $stmtGet = sqlsrv_query($conn, $sqlGet, [$id]);
        if ($stmtGet === false) { $_SESSION['error'] = 'Gagal mengambil data TTD.'; header('Location: ttd_user.php'); exit; }
        $existing = sqlsrv_fetch_array($stmtGet, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtGet);
        if (!$existing) { $_SESSION['error'] = 'Data TTD tidak ditemukan.'; header('Location: ttd_user.php'); exit; }

        $oldSigPath = trim($existing['SignaturePath'] ?? '');
        // user fields disabled during edit, use existing data
        $userName = $existing['UserName'] ?? 'User';
        $userId = (int)($existing['UserId'] ?? 0);
        $groupRole = trim($_POST['group_role'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $canvasData = $_POST['canvas_data'] ?? '';

        if ($groupRole === '') { $_SESSION['error'] = 'Role wajib diisi.'; header('Location: ttd_user.php'); exit; }

        $uploadBase = realpath($rootDir . '/../uploads');
        if ($uploadBase === false) { $tryDir = $rootDir . '/../uploads'; if (!is_dir($tryDir)) @mkdir($tryDir, 0777, true); $uploadBase = realpath($tryDir); }
        if ($uploadBase === false) { $_SESSION['error'] = 'Folder upload tidak tersedia.'; header('Location: ttd_user.php'); exit; }
        $ttdDir = $uploadBase . DIRECTORY_SEPARATOR . 'ttd';
        if (!is_dir($ttdDir)) @mkdir($ttdDir, 0777, true);
        if (!is_dir($ttdDir) || !is_writable($ttdDir)) { $_SESSION['error'] = 'Folder ttd tidak bisa diakses/tulis.'; header('Location: ttd_user.php'); exit; }

        $imgPathRel = '';

        if (isset($_FILES['ttd_file']) && $_FILES['ttd_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['ttd_file']['tmp_name'];
            $name = $_FILES['ttd_file']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) { $_SESSION['error'] = 'Format file harus PNG/JPG.'; header('Location: ttd_user.php'); exit; }
            $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', str_replace(' ', '_', preg_replace('/\s+/', ' ', preg_replace('/[\/\\:\*\?"<>\|]+/', '', trim($userName)))));
            $safeRole = str_replace(' ', '_', trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole)));
            $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . ' (EDIT).' . $ext;
            $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
            $targetW = 1294; $targetH = 643; $resized = false;
            if (function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
                $info = @getimagesize($tmpName);
                if ($info !== false) {
                    $mime = $info['mime'] ?? '';
                    try {
                        $src = ($mime === 'image/png') ? @imagecreatefrompng($tmpName) : @imagecreatefromjpeg($tmpName);
                        if ($src !== false) {
                            $dst = imagecreatetruecolor($targetW, $targetH);
                            $white = imagecolorallocate($dst, 255, 255, 255);
                            imagefill($dst, 0, 0, $white);
                            $scale = max($targetW / imagesx($src), $targetH / imagesy($src));
                            imagecopyresampled($dst, $src, (int)(($targetW - imagesx($src) * $scale) / 2), (int)(($targetH - imagesy($src) * $scale) / 2), 0, 0, (int)(imagesx($src) * $scale), (int)(imagesy($src) * $scale), imagesx($src), imagesy($src));
                            if ($ext === 'png') imagepng($dst, $fullPath, 0);
                            else imagejpeg($dst, $fullPath, 95);
                            $resized = file_exists($fullPath);
                        }
                    } catch (Exception $e) {
                        @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - edit gd resize: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
            if (!$resized && !move_uploaded_file($tmpName, $fullPath)) {
                @file_put_contents($ttdDir . DIRECTORY_SEPARATOR . 'debug_ttd.log', date('Y-m-d H:i:s') . " - edit gagal move_uploaded_file\n", FILE_APPEND);
                $_SESSION['error'] = 'Gagal menyimpan file upload.';
                header('Location: ttd_user.php'); exit;
            }
            $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
        } elseif (!empty($canvasData) && strpos($canvasData, 'data:image') === 0) {
            $base64 = str_replace(' ', '+', preg_replace('#^data:image/[^;]+;base64,#', '', $canvasData));
            $data = base64_decode($base64);
            if ($data !== false) {
                $src = @imagecreatefromstring($data);
                if ($src !== false) {
                    $labelName = preg_replace('/[^A-Za-z0-9 _-]/', '', str_replace(' ', '_', preg_replace('/\s+/', ' ', preg_replace('/[\/\\:\*\?"<>\|]+/', '', trim($userName)))));
                    $safeRole = str_replace(' ', '_', trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $groupRole)));
                    $fileName = $labelName . ' - ' . $safeRole . ' - ' . date('d-m-y_Hi') . ' (EDIT).png';
                    $fullPath = $ttdDir . DIRECTORY_SEPARATOR . $fileName;
                    $w = imagesx($src); $h = imagesy($src);
                    $dst = imagecreatetruecolor($w, $h);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                    imagefill($dst, 0, 0, $trans);
                    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                    for ($y = 0; $y < $h; $y++) {
                        for ($x = 0; $x < $w; $x++) {
                            $rgb = imagecolorat($dst, $x, $y);
                            $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                            if ($r > 210 && $g > 210 && $b > 210) imagesetpixel($dst, $x, $y, $trans);
                        }
                    }
                    imagepng($dst, $fullPath, 0);
                    if (file_exists($fullPath)) $imgPathRel = '/gg_app/uploads/ttd/' . $fileName;
                }
            }
        }

        if ($imgPathRel === '') {
            $_SESSION['error'] = 'Gambar tanda tangan (canvas atau upload) wajib diisi.';
            header('Location: ttd_user.php'); exit;
        }

        // Handle old file - check if still referenced by documents
        if ($oldSigPath !== '') {
            $inUse = false;
            $sigBase = basename($oldSigPath);
            $stmtCols = @sqlsrv_query($conn, "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE DATA_TYPE IN ('varchar','nvarchar','text','char','nchar') AND (COLUMN_NAME LIKE '%ttd%' OR COLUMN_NAME LIKE '%sign%' OR COLUMN_NAME LIKE '%signature%' OR COLUMN_NAME LIKE '%path%')");
            if ($stmtCols !== false) {
                while ($col = sqlsrv_fetch_array($stmtCols, SQLSRV_FETCH_ASSOC)) {
                    if (strtolower($col['TABLE_NAME']) === 'user_ttd_template_umum') continue;
                    $fqTable = '[' . $col['TABLE_SCHEMA'] . '].[' . $col['TABLE_NAME'] . ']';
                    $stmtCount = @sqlsrv_query($conn, "SELECT COUNT(1) AS cnt FROM {$fqTable} WHERE [{$col['COLUMN_NAME']}] = ? OR [{$col['COLUMN_NAME']}] LIKE ?", [$oldSigPath, '%' . $sigBase . '%']);
                    if ($stmtCount !== false) {
                        $cRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
                        if (intval($cRow['cnt'] ?? 0) > 0) { $inUse = true; sqlsrv_free_stmt($stmtCount); break; }
                        sqlsrv_free_stmt($stmtCount);
                    }
                }
                sqlsrv_free_stmt($stmtCols);
            }
            if (!$inUse) { $oldFile = $_SERVER['DOCUMENT_ROOT'] . $oldSigPath; if (file_exists($oldFile)) @unlink($oldFile); }
        }

        $stmtUp = sqlsrv_query($conn, "UPDATE User_TTD_Template_Umum SET UserId = ?, UserName = ?, GroupRole = ?, SignaturePath = ?, IsActive = ?, UpdatedAt = GETDATE() WHERE Id = ?", [(int)$userId, $userName, $groupRole, $imgPathRel, $isActive, $id]);
        $_SESSION[$stmtUp === false ? 'error' : 'success'] = $stmtUp === false ? 'Gagal mengupdate data TTD.' : 'Data TTD berhasil diupdate.';

        header('Location: ttd_user.php');
        exit;
    }
}

// Data untuk tampilan
$users       = getAllUsersSimple($conn);
$ttdTemplates = getAllTtdTemplates($conn);

// Build mapping of EmpId => [roles] for client-side filtering (hide existing roles)
$existingRolesByEmp = [];
if (!empty($ttdTemplates)) {
    foreach ($ttdTemplates as $t) {
        $emp = isset($t['EmpId']) && $t['EmpId'] ? (int)$t['EmpId'] : 0;
        // If EmpId not present, try to infer from UserName matching users list
        if ($emp === 0) {
            $uname = strtolower(trim($t['UserName'] ?? ''));
            foreach ($users as $u) {
                if (strtolower(trim($u['FullName'])) === $uname) { $emp = (int)$u['EmpId']; break; }
            }
        }
        if ($emp > 0) {
            $role = trim($t['GroupRole'] ?? '');
            if ($role !== '') {
                if (!isset($existingRolesByEmp[$emp])) $existingRolesByEmp[$emp] = [];
                if (!in_array($role, $existingRolesByEmp[$emp], true)) $existingRolesByEmp[$emp][] = $role;
            }
        }
    }
}

?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tandatangan Pengguna (Umum)</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Tandatangan Pengguna Umum</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                        Daftar Tandatangan Pengguna Umum</h3>
                    <?php if ($permissions['CanAdd'] == 1): ?>
                        <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#modalAddTtd">
                            <i class="fas fa-plus"></i> Tambah TTD User
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ttdTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Preview TTD</th>
                                    <th>Aktif</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ttdTemplates)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada data TTD</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ttdTemplates as $index => $row): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <?= htmlspecialchars($row['FullName'] ?? $row['UserName'] ?? '-') ?>
                                                <br>
                                                <?php if ((int)($row['UserId'] ?? 0) === 0): ?>
                                                    <small class="text-info"><i class="fas fa-user-edit"></i> Manual / tanpa akun</small>
                                                <?php else: ?>
                                                    <small class="text-muted">(Login: <?= htmlspecialchars($row['LoginName'] ?? '#'.$row['UserId']) ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['GroupRole']) ?></td>
                                            <td>
                                                <?php if (!empty($row['SignaturePath'])): ?>
                                                    <img src="<?= htmlspecialchars($row['SignaturePath']) ?>" class="ttd-preview" alt="TTD" style="max-height:50px;">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($permissions['CanEdit'] == 1): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= (int)$row['Id'] ?>">
                                                        <input type="hidden" name="is_active" value="<?= $row['IsActive'] ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-sm <?= $row['IsActive'] ? 'btn-success' : 'btn-secondary' ?>">
                                                            <?= $row['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge <?= $row['IsActive'] ? 'badge-success' : 'badge-secondary' ?>">
                                                        <?= $row['IsActive'] ? 'Aktif' : 'Non-Aktif' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $row['CreatedAt'] ? $row['CreatedAt']->format('Y-m-d H:i:s') : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($permissions['CanEdit'] == 1): ?>
                                                    <button type="button" class="btn btn-warning btn-sm btn-edit-ttd mr-1"
                                                        data-id="<?= (int)$row['Id'] ?>"
                                                        data-user-source="<?= ((int)($row['EmpId'] ?? 0) > 0) ? 'employee' : 'manual' ?>"
                                                        data-emp-id="<?= (int)($row['EmpId'] ?? 0) ?>"
                                                        data-user-name="<?= htmlspecialchars($row['UserName'] ?? '') ?>"
                                                        data-group-role="<?= htmlspecialchars($row['GroupRole'] ?? '') ?>"
                                                        data-signature-path="<?= htmlspecialchars($row['SignaturePath'] ?? '') ?>"
                                                        data-is-active="<?= $row['IsActive'] ? 1 : 0 ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($permissions['CanDelete'] == 1): ?>
                                                    <form method="post" class="d-inline form-delete-ttd">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$row['Id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
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
    </div>
</div>

<!-- Modal Tambah TTD -->
<div class="modal fade" id="modalAddTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="formAddTtd">
        <div class="modal-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
          <h5 class="modal-title">Tambah Template TTD User Umum</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="edit_id" id="edit_id" value="">
          <input type="hidden" name="canvas_data" id="canvasDataManager" value="">
                    <div class="form-group">
                        <label>Sumber User</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="userSourceEmployee" name="user_source" class="custom-control-input" value="employee" checked>
                            <label class="custom-control-label" for="userSourceEmployee">Pilih dari Karyawan Aktif</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="userSourceManual" name="user_source" class="custom-control-input" value="manual">
                            <label class="custom-control-label" for="userSourceManual">Input Manual (tanpa data employee)</label>
                        </div>
                    </div>
                    <div class="form-group" id="employeeUserGroup">
                        <label>User (Pilih Karyawan Aktif)</label>
                        <select name="user_id" class="form-control select2" data-placeholder="-- Pilih Karyawan --" style="width:100%;" required>
                            <option value="">-- Pilih Karyawan --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['EmpId'] ?>"><?= htmlspecialchars($u['FullName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="manualUserGroup" style="display:none;">
                        <label>Nama Karyawan Manual</label>
                        <input type="text" name="manual_user_name" class="form-control" placeholder="Ketik nama karyawan manual...">
                        <small class="form-text text-muted">Digunakan untuk karyawan yang tidak ada di data employee.</small>
                    </div>
          <div class="form-group">
            <label>Role TTD</label>
                        <select name="group_role" class="form-control" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="Pemohon">Pemohon</option>
                            <option value="Atasan Pemohon">Atasan Pemohon</option>
                            <option value="Kabag IT">Kabag IT</option>
                            <option value="Kadept IT">Kadept IT</option>
                            <option value="Kabag ICS">Kabag ICS</option>
                            <option value="Kadept ACC">Kadept ACC</option>
                            <option value="Direksi">Direksi</option>
                            <option value="Personalia">Personalia</option>
                            <option value="HRD">HRD</option>
                            <option value="DanRu SATPAM">DanRu SATPAM</option>
                        </select>
          </div>
           <div class="form-group">
            <label><b>Gambar di Canvas</b></label>
            <div style="border:1px solid #ccc; display:inline-block;">
              <canvas id="ttdCanvasManager" width="400" height="150" style="background:#fff;cursor:crosshair;"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvasManager">Bersihkan Canvas</button>
          </div>
          <div class="form-group">
            <label>Atau Upload File TTD (PNG/JPG)</label>
            <input type="file" name="ttd_file" class="form-control" accept="image/png,image/jpeg">
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="chkIsActive" name="is_active" value="1" checked>
            <label class="form-check-label" for="chkIsActive">Aktif</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success" id="btnSimpanTtdManager">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Crop -->
<div class="modal fade" id="modalCrop" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Sesuaikan Ukuran / Crop Gambar</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-8">
                <label>1. Atur Area Potong (Crop):</label>
                <div class="img-container" style="height: 400px; background: #f7f7f7; border: 1px solid #ddd;">
                    <img id="image-cropper-preview" src="" style="max-width: 100%; display: block;">
                </div>
            </div>
            <div class="col-md-4">
                <label>2. Preview Hasil (Live):</label>
                <div style="border: 1px solid #999; background: #fff; padding: 2px;">
                    <canvas id="cropPreviewCanvas" style="width: 100%; height: auto; display: block;"></canvas>
                </div>
                <small class="text-muted d-block mt-2">* Gambar di atas adalah hasil yang akan disimpan.</small>
            </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="d-flex align-items-center" style="flex-grow: 1; max-width: 50%;">
            <label class="mr-2 mb-0 font-weight-bold">Ketebalan Tinta:</label>
            <input type="range" class="custom-range" id="inkThickness" min="0" max="5" step="1" value="0" style="width: 100px;">
            <span id="inkThicknessVal" class="ml-2 font-weight-bold badge badge-info">0</span>
        </div>
        <div>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary" id="btnCropSave">Crop & Simpan ke Canvas</button>
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

<!-- Cropper.js JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
$(function() {
    $('#ttdTable').DataTable({
        responsive: true,
        autoWidth: false,
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
    });

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

    // Use delegated handler so it works even when DataTables redraws the table rows
    $(document).on('submit', '.form-delete-ttd', function(e) {
        var $form = $(this);

        // If submit has already been confirmed, allow it to proceed (guard for re-trigger)
        if ($form.data('delete-confirmed')) {
            $form.removeData('delete-confirmed');
            return true; // allow native submit
        }

        e.preventDefault();

        Swal.fire({
            title: 'Hapus data TTD?',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                // mark as confirmed then perform native submit to avoid recursion
                $form.data('delete-confirmed', true);
                $form[0].submit();
            }
        });
    });

    // Canvas untuk TTD User Manajer (menggunakan logika dari sign_document.php)
    var canvas = document.getElementById('ttdCanvasManager');
    if (canvas) {
        var ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Penyesuaian resolusi canvas untuk tampilan yang tajam
        function resizeCanvas() {
            var container = canvas.parentElement;
            var width = 400;
            var height = 150;

            // dukungan devicePixelRatio untuk ketajaman di layar high-DPI
            var dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.scale(dpr, dpr);

            // Fill background putih
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        resizeCanvas();

        // Drawing state
        var drawing = false;
        var lastX = 0, lastY = 0;
        var lastTime = 0;
        var lastPressure = 0.5;

        function getCoords(e) {
            var rect = canvas.getBoundingClientRect();
            if (e.touches && e.touches.length) {
                return [e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top];
            } else if (e.clientX !== undefined) {
                return [e.clientX - rect.left, e.clientY - rect.top];
            } else if (e.changedTouches && e.changedTouches.length) {
                return [e.changedTouches[0].clientX - rect.left, e.changedTouches[0].clientY - rect.top];
            }
            return [0,0];
        }

        function start(e) {
            e.preventDefault();
            drawing = true;
            var coords = getCoords(e);
            lastX = coords[0];
            lastY = coords[1];
            lastTime = Date.now();
            lastPressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : 0.5;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }

        function draw(e) {
            if (!drawing) return;
            e.preventDefault();

            var coords = getCoords(e);
            var x = coords[0];
            var y = coords[1];
            var now = Date.now();
            var elapsed = Math.max(1, now - lastTime);
            lastTime = now;

            var pressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : lastPressure;
            var dist = Math.hypot(x - lastX, y - lastY);
            var speed = dist / elapsed;
            var dynamicWidth = Math.max(1, 5 - speed * 2) * pressure;

            ctx.lineWidth = dynamicWidth;
            ctx.strokeStyle = "#000";
            ctx.lineTo(x, y);
            ctx.stroke();

            lastX = x;
            lastY = y;
            lastPressure = pressure;
        }

        function stop(e) {
            if (!drawing) return;
            e.preventDefault();
            drawing = false;
            ctx.closePath();
        }

        // Event listeners: gunakan pointer events (kompatibel mouse/touch/stylus)
        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
        canvas.addEventListener('pointerleave', stop);

        // Prevent scrolling when touching canvas (mobile)
        canvas.addEventListener('touchstart', function(ev){ ev.preventDefault(); }, { passive:false });

        // Debounce helper to prevent lag during sliding/cropping
        function debounce(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }

        // Function to update Live Preview Canvas
        function updateLivePreview() {
            if (!cropper) return;
            
            var previewCanvas = document.getElementById('cropPreviewCanvas');
            var pCtx = previewCanvas.getContext('2d');
            
            // Set internal resolution (High Res)
            previewCanvas.width = 1294;
            previewCanvas.height = 643;
            
            // Get cropped data from Cropper
            var croppedCanvas = cropper.getCroppedCanvas({
                maxWidth: 4096, maxHeight: 4096,
                imageSmoothingEnabled: true, imageSmoothingQuality: 'high'
            });
            
            if (!croppedCanvas) return;

            // Clear Preview Canvas
            pCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
            pCtx.fillStyle = '#ffffff';
            pCtx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);

            // Scale & Center (Contain)
            var scale = Math.min(previewCanvas.width / croppedCanvas.width, previewCanvas.height / croppedCanvas.height);
            var dw = croppedCanvas.width * scale;
            var dh = croppedCanvas.height * scale;
            var dx = (previewCanvas.width - dw) / 2;
            var dy = (previewCanvas.height - dh) / 2;
            
            // Draw original cropped image
            pCtx.drawImage(croppedCanvas, dx, dy, dw, dh);
            
            // Apply Thickening (Dilation)
            var thickness = parseInt($('#inkThickness').val()) || 0;
            if (thickness > 0) {
                var imageData = pCtx.getImageData(0, 0, previewCanvas.width, previewCanvas.height);
                var data = imageData.data;
                var w = previewCanvas.width;
                var h = previewCanvas.height;
                
                // Helper to set pixel black
                function setBlack(d, idx) {
                    d[idx] = 0; d[idx+1] = 0; d[idx+2] = 0; // RGB Black
                }

                for (var iter = 0; iter < thickness; iter++) {
                    var srcData = new Uint8ClampedArray(data);
                    for (var y = 0; y < h; y++) {
                        for (var x = 0; x < w; x++) {
                            var idx = (y * w + x) * 4;
                            var r = srcData[idx];
                            var g = srcData[idx+1];
                            var b = srcData[idx+2];
                            
                            // Threshold for ink
                            if (r < 150 && g < 150 && b < 150) {
                                if (x < w-1) setBlack(data, idx + 4);
                                if (x > 0)   setBlack(data, idx - 4);
                                if (y < h-1) setBlack(data, idx + w * 4);
                                if (y > 0)   setBlack(data, idx - w * 4);
                                setBlack(data, idx);
                            }
                        }
                    }
                }
                pCtx.putImageData(imageData, 0, 0);
            }
        }

        // Load uploaded image into Cropper Modal
        var cropper;
        var image = document.getElementById('image-cropper-preview');

        $('input[name="ttd_file"]').on('change', function(e){
            var file = this.files && this.files[0];
            if (!file) return;
            var allowed = ['image/png','image/jpeg'];
            if (allowed.indexOf(file.type) === -1) {
                Swal.fire({icon:'error', title:'Format tidak didukung', text:'Silakan pilih file PNG atau JPG.'});
                $(this).val('');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(evt){
                $('#modalCrop').modal('show');
                $('#modalCrop').one('shown.bs.modal', function () {
                    image.src = evt.target.result;
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(image, {
                        viewMode: 1,
                        autoCropArea: 1,
                        responsive: true,
                        // Update preview when cropping changes (debounced)
                        crop: debounce(updateLivePreview, 100) 
                    });
                    // Initial preview
                    setTimeout(updateLivePreview, 100);
                });
            };
            reader.readAsDataURL(file);
        });

        // Update slider value display & Live Preview
        $('#inkThickness').on('input', function() {
            $('#inkThicknessVal').text($(this).val());
            updateLivePreview();
        });

        // Handle Crop & Save (Final)
        $('#btnCropSave').on('click', function() {
            // Copy from Preview Canvas to Main Canvas
            var previewCanvas = document.getElementById('cropPreviewCanvas');
            
            // Pastikan main canvas bersih dan putih
            ctx.setTransform(1, 0, 0, 1, 0, 0); // Reset transform just in case
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw preview image to fill main canvas
            ctx.drawImage(previewCanvas, 0, 0, canvas.width, canvas.height);
            
            // Close modal
            $('#modalCrop').modal('hide');
            
            // Clear file input so server uses canvas data
            $('input[name="ttd_file"]').val('');
        });

        // FIX: Kembalikan scroll pada modal utama setelah modal crop ditutup
        // Masalah umum Bootstrap pada stacked modal: class modal-open hilang dari body saat modal kedua tutup
        $('#modalCrop').on('hidden.bs.modal', function () {
            if ($('#modalAddTtd').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        // Clear canvas and also clear file input
        $('#btnClearCanvasManager').on('click', function(){
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0,0,canvas.width,canvas.height);
            $('#canvasDataManager').val('');
            $('input[name="ttd_file"]').val('');
        });

        // On submit, only set canvas_data when there is NO uploaded file.
        $('#formAddTtd').on('submit', function(){
            var hasFile = ($('input[name="ttd_file"]')[0] && $('input[name="ttd_file"]')[0].files && $('input[name="ttd_file"]')[0].files.length > 0);
            if (!hasFile) {
                $('#canvasDataManager').val(canvas.toDataURL('image/png'));
            } else {
                // leave canvas_data empty so server will use uploaded file (original quality)
                $('#canvasDataManager').val('');
            }
        });

        // Edit button handler - populate modal with existing data
        $(document).on('click', '.btn-edit-ttd', function() {
            var btn = $(this);
            $('#modalAddTtd .modal-title').text('Edit Template TTD User Umum');
            $('#btnSimpanTtdManager').text('Update');
            $('input[name="action"]').val('edit');
            $('#edit_id').val(btn.data('id'));

            var userSource = btn.data('user-source');
            if (userSource === 'manual') {
                $('#userSourceManual').prop('checked', true);
                $('input[name="user_source"]').trigger('change');
                $('input[name="manual_user_name"]').val(btn.data('user-name'));
            } else {
                $('#userSourceEmployee').prop('checked', true);
                $('input[name="user_source"]').trigger('change');
                var empSelect = $('select[name="user_id"]');
                empSelect.val(btn.data('emp-id'));
                empSelect.trigger('change');
                try { if (empSelect.data('select2')) empSelect.trigger('change.select2'); } catch(e){}
            }

            $('select[name="group_role"]').val(btn.data('group-role'));
            $('#chkIsActive').prop('checked', btn.data('is-active') == 1);

            // Disable user source fields during edit
            $('input[name="user_source"]').prop('disabled', true);
            $('select[name="user_id"]').prop('disabled', true);
            $('input[name="manual_user_name"]').prop('disabled', true);

            var sigPath = btn.data('signature-path');
            if (sigPath) {
                var img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function() {
                    var dpr = window.devicePixelRatio || 1;
                    ctx.save();
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.restore();
                    ctx.drawImage(img, 0, 0, 400, 150);
                };
                img.onerror = function() { /* keep canvas blank if image fails */ };
                img.src = sigPath + '?t=' + Date.now();
            }

            $('#modalAddTtd').modal('show');
        });

        // Reset modal state when hidden
        $('#modalAddTtd').on('hidden.bs.modal', function() {
            $('input[name="action"]').val('add');
            $('#edit_id').val('');
            $('#modalAddTtd .modal-title').text('Tambah Template TTD User Umum');
            $('#btnSimpanTtdManager').text('Simpan');

            // Re-enable user fields
            $('input[name="user_source"]').prop('disabled', false);
            $('select[name="user_id"]').prop('disabled', false);
            $('input[name="manual_user_name"]').prop('disabled', false);
            var dpr = window.devicePixelRatio || 1;
            ctx.save();
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        });
    }
});

// Initialize Select2 for employee selector and ensure modal focus/click works
$(function(){
    function updateUserSourceMode() {
        var mode = $('input[name="user_source"]:checked').val() || 'employee';
        var $employeeSelect = $('select[name="user_id"]');
        var $manualInput = $('input[name="manual_user_name"]');

        if (mode === 'manual') {
            $('#employeeUserGroup').hide();
            $('#manualUserGroup').show();
            $employeeSelect.prop('required', false).val('').trigger('change');
            $manualInput.prop('required', true).focus();
        } else {
            $('#employeeUserGroup').show();
            $('#manualUserGroup').hide();
            $employeeSelect.prop('required', true);
            $manualInput.prop('required', false).val('');
        }
    }

    $(document).on('change', 'input[name="user_source"]', updateUserSourceMode);
    updateUserSourceMode();

    // existingRolesByEmp mapping injected from server - roles per EmpId
    var existingRolesByEmp = <?php echo json_encode($existingRolesByEmp, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || {};

    // Cache original role options so we can restore later
    var $roleSelect = $('select[name="group_role"]');
    var originalRoleOptions = $roleSelect.find('option').map(function(){ return { value: $(this).attr('value'), text: $(this).text() }; }).get();

    function refreshRoleOptionsForEmp(empId) {
        var isEdit = $('#edit_id').val() !== '';
        $roleSelect.empty();
        var existing = [];
        if (!isEdit && empId && existingRolesByEmp.hasOwnProperty(empId)) existing = existingRolesByEmp[empId];
        // restore placeholder + options not present in existing
        originalRoleOptions.forEach(function(opt){
            if (opt.value === '') { $roleSelect.append($('<option>').attr('value','').text(opt.text)); return; }
            if (isEdit || existing.indexOf(opt.text.trim()) === -1) {
                $roleSelect.append($('<option>').attr('value', opt.value).text(opt.text));
            }
        });
        // If select2 is active, update UI
        try { if ($roleSelect.data('select2')) $roleSelect.trigger('change.select2'); } catch(e){}
    }

    // When user selection changes in modal, filter role options
    $(document).on('change', 'select[name="user_id"]', function(){
        var empId = $(this).val() || '';
        refreshRoleOptionsForEmp(empId);
    });

    if ($.fn.select2) {
        $('.select2').each(function(){
            var $el = $(this);
            var placeholderText = $el.data('placeholder') || '-- Pilih --';
            var $modalParent = $el.closest('.modal');
            var parent = $modalParent.length ? $modalParent : $(document.body);

            $el.select2({
                theme: 'bootstrap4',
                width: '100%',
                allowClear: true,
                placeholder: placeholderText,
                dropdownParent: parent
            });
        });

        // When modal opens, open select2 after a short delay so modal animation/backdrop finished
        $('#modalAddTtd').on('shown.bs.modal', function () {
            var sel = $(this).find('.select2');
            if (sel.length) {
                setTimeout(function(){
                    try { sel.select2('open'); } catch(e) { /* ignore */ }
                    var s = document.querySelector('.select2-container--open .select2-search__field');
                    if (s) s.focus();
                }, 200);
            }
        });
    }
});
</script>
