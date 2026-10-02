<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse($payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function ensureProjectUploadDir()
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function createUniqueFileName($originalName, $prefix = 'file_')
{
    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $stamp = date('Ymd_His');
    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
    }
    return $prefix . $stamp . '_' . $rand . ($ext !== '' ? '.' . $ext : '');
}

function cleanProjectPendingUploads()
{
    if (!isset($_SESSION['project_pending_uploads']) || !is_array($_SESSION['project_pending_uploads'])) {
        $_SESSION['project_pending_uploads'] = array();
        return;
    }

    $expireBefore = time() - (24 * 60 * 60);
    foreach ($_SESSION['project_pending_uploads'] as $token => $items) {
        if (!is_array($items)) {
            unset($_SESSION['project_pending_uploads'][$token]);
            continue;
        }

        $keep = array();
        foreach ($items as $item) {
            $uploadedAt = isset($item['uploaded_at']) ? (int) $item['uploaded_at'] : 0;
            if ($uploadedAt >= $expireBefore) {
                $keep[] = $item;
            }
        }

        if (empty($keep)) {
            unset($_SESSION['project_pending_uploads'][$token]);
        } else {
            $_SESSION['project_pending_uploads'][$token] = $keep;
        }
    }
}

function getOrCreateFormToken($postedToken = '')
{
    $token = trim((string) $postedToken);
    if ($token !== '' && preg_match('/^[a-zA-Z0-9_-]{16,80}$/', $token)) {
        return $token;
    }

    try {
        return bin2hex(random_bytes(16));
    } catch (Exception $e) {
        return md5(uniqid((string) mt_rand(), true));
    }
}

function detectUploadedMimeType($tmpName, $fallbackMime = '')
{
    if (function_exists('finfo_open') && is_string($tmpName) && is_file($tmpName)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }
    return trim((string) $fallbackMime);
}

function registerPendingUpload($formToken, $meta)
{
    if (!isset($_SESSION['project_pending_uploads']) || !is_array($_SESSION['project_pending_uploads'])) {
        $_SESSION['project_pending_uploads'] = array();
    }
    if (!isset($_SESSION['project_pending_uploads'][$formToken]) || !is_array($_SESSION['project_pending_uploads'][$formToken])) {
        $_SESSION['project_pending_uploads'][$formToken] = array();
    }
    $_SESSION['project_pending_uploads'][$formToken][] = $meta;
}

function handleEditorUpload($fileField, $allowedExtensions, $maxBytes, $prefix, $isInline)
{
    if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
        jsonResponse(array('success' => false, 'message' => 'File tidak ditemukan.'));
    }

    $formToken = getOrCreateFormToken(isset($_POST['form_token']) ? $_POST['form_token'] : '');

    $file = $_FILES[$fileField];
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(array('success' => false, 'message' => 'Upload gagal.'));
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        jsonResponse(array('success' => false, 'message' => 'Ekstensi file tidak diizinkan.'));
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > $maxBytes) {
        jsonResponse(array('success' => false, 'message' => 'Ukuran file tidak valid.'));
    }

    $uploadDir = ensureProjectUploadDir();
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        jsonResponse(array('success' => false, 'message' => 'Folder upload tidak bisa ditulis.'));
    }

    $newFileName = createUniqueFileName($originalName, $prefix);
    $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
    if (!move_uploaded_file((string) $file['tmp_name'], $targetAbs)) {
        jsonResponse(array('success' => false, 'message' => 'Gagal memindahkan file upload.'));
    }

    $fileUrl = '/gg_app/pages/project/uploads/' . rawurlencode($newFileName);
    $mimeType = detectUploadedMimeType((string) $file['tmp_name'], (string) ($file['type'] ?? ''));
    registerPendingUpload($formToken, array(
        'file_name' => $originalName !== '' ? $originalName : $newFileName,
        'file_path' => $fileUrl,
        'file_ext' => $extension,
        'file_size' => $size,
        'mime_type' => $mimeType,
        'is_inline' => $isInline ? 1 : 0,
        'uploaded_at' => time()
    ));

    jsonResponse(array(
        'success' => true,
        'url' => $fileUrl,
        'name' => $originalName,
        'form_token' => $formToken
    ));
}

function parseIssueIdList($raw)
{
    $items = array();
    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        $items = explode(',', $raw);
    }

    $result = array();
    foreach ($items as $item) {
        $id = (int) trim((string) $item);
        if ($id > 0) {
            $result[$id] = $id;
        }
    }
    return array_values($result);
}

function normalizeAssigneeSelection($raw)
{
    $items = array();
    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        $items = explode(',', $raw);
    }

    $result = array();
    foreach ($items as $item) {
        $name = trim((string) $item);
        if ($name !== '') {
            $result[$name] = $name;
        }
    }
    return array_values($result);
}

function getProjectColumnMaxLength($conn, $columnName)
{
    $sql = "SELECT CHARACTER_MAXIMUM_LENGTH AS max_len
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = 'dbo'
              AND TABLE_NAME = 'project'
              AND COLUMN_NAME = ?";
    $stmt = sqlsrv_query($conn, $sql, array($columnName));
    if (!$stmt) {
        return null;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row || !isset($row['max_len'])) {
        return null;
    }
    $val = $row['max_len'];
    if ($val === null) {
        return null;
    }
    $len = (int) $val;
    return $len > 0 ? $len : null;
}

function isDoneIssueStatus($status)
{
    $s = strtoupper(trim((string) $status));
    return in_array($s, array('DONE', 'CLOSED'), true);
}

function buildIssueOptionLabel($row)
{
    $issueId = (int) ($row['issue_id'] ?? 0);
    $issueName = trim((string) ($row['issue_name'] ?? ''));
    $issueType = trim((string) ($row['issue_type'] ?? ''));
    $statusRaw = trim((string) ($row['status'] ?? ''));
    $createdBy = trim((string) ($row['created_by'] ?? ''));

    $statusLabel = $statusRaw !== '' ? $statusRaw : 'To Do';
    $parts = array();
    $parts[] = '#' . $issueId;
    if ($issueName !== '') {
        $parts[] = $issueName;
    }
    if ($issueType !== '') {
        $parts[] = $issueType;
    }
    $parts[] = 'Status: ' . $statusLabel;
    if ($createdBy !== '') {
        $parts[] = 'PIC: ' . $createdBy;
    }

    return implode(' | ', $parts);
}

function normalizeIssueStatus($status)
{
    return strtoupper(trim((string) $status));
}

function getThemePalette($theme)
{
    $key = strtolower(trim((string) $theme));
    $map = array(
        'primary' => array('start' => '#1d7cf2', 'end' => '#0f6ad9', 'rgb' => '29,124,242', 'text' => '#ffffff'),
        'success' => array('start' => '#1f9f48', 'end' => '#2cb757', 'rgb' => '44,183,87', 'text' => '#ffffff'),
        'info' => array('start' => '#16a7c9', 'end' => '#1b9ad4', 'rgb' => '27,154,212', 'text' => '#ffffff'),
        'warning' => array('start' => '#f3b41c', 'end' => '#e59d00', 'rgb' => '229,157,0', 'text' => '#1f2a37'),
        'danger' => array('start' => '#dc3545', 'end' => '#c82333', 'rgb' => '200,35,51', 'text' => '#ffffff'),
        'secondary' => array('start' => '#6c757d', 'end' => '#5a6268', 'rgb' => '90,98,104', 'text' => '#ffffff'),
        'dark' => array('start' => '#343a40', 'end' => '#23272b', 'rgb' => '35,39,43', 'text' => '#ffffff'),
        'purple' => array('start' => '#7c3aed', 'end' => '#5b21b6', 'rgb' => '91,33,182', 'text' => '#ffffff'),
        'indigo' => array('start' => '#5b5ff0', 'end' => '#4338ca', 'rgb' => '67,56,202', 'text' => '#ffffff'),
        'teal' => array('start' => '#14b8a6', 'end' => '#0d9488', 'rgb' => '13,148,136', 'text' => '#ffffff')
    );

    return $map[$key] ?? $map['primary'];
}

function ensureProjectIssueLinkTable($conn)
{
    $sql = "
IF OBJECT_ID('dbo.project_issue_links', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.project_issue_links (
        link_id INT IDENTITY(1,1) PRIMARY KEY,
        projectid INT NOT NULL,
        issue_id INT NOT NULL,
        issue_status_snapshot VARCHAR(50) NULL,
        created_by VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE UNIQUE INDEX UX_project_issue_links_project_issue ON dbo.project_issue_links(projectid, issue_id);
    CREATE INDEX IX_project_issue_links_issue_id ON dbo.project_issue_links(issue_id);
END";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $err = !empty($errors[0]['message']) ? trim((string) $errors[0]['message']) : 'Gagal menyiapkan tabel project_issue_links.';
        return array(false, $err);
    }
    return array(true, '');
}

function ensureProjectProgressAutoColumn($conn)
{
    $sql = "
IF COL_LENGTH('dbo.project', 'progress_auto') IS NULL
BEGIN
    ALTER TABLE dbo.project
    ADD progress_auto BIT NOT NULL CONSTRAINT DF_project_progress_auto DEFAULT(1) WITH VALUES;
END";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $err = !empty($errors[0]['message']) ? trim((string) $errors[0]['message']) : 'Gagal menyiapkan kolom mode progress project.';
        return array(false, $err);
    }
    return array(true, '');
}

function calculateProgressFromIssueIds($conn, $issueIds)
{
    if (empty($issueIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($issueIds), '?'));
    $sql = "SELECT COUNT(*) AS total_count,
                   SUM(CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(status, '')))) IN ('DONE', 'CLOSED') THEN 1 ELSE 0 END) AS done_count
            FROM dbo.issues
            WHERE issue_id IN ($placeholders)";
    $stmt = sqlsrv_query($conn, $sql, $issueIds);
    if (!$stmt) {
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $total = (int) ($row['total_count'] ?? 0);
    $done = (int) ($row['done_count'] ?? 0);
    if ($total <= 0) {
        return 0;
    }
    return (int) round(($done / $total) * 100);
}

function deriveAssigneeFromIssueIds($conn, $issueIds, $maxLen = null)
{
    if (empty($issueIds)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($issueIds), '?'));
    $sql = "SELECT DISTINCT LTRIM(RTRIM(ISNULL(created_by, ''))) AS created_by
            FROM dbo.issues
            WHERE issue_id IN ($placeholders)
              AND LTRIM(RTRIM(ISNULL(created_by, ''))) <> ''
            ORDER BY created_by ASC";
    $stmt = sqlsrv_query($conn, $sql, $issueIds);
    if (!$stmt) {
        return '';
    }

    $names = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $name = trim((string) ($row['created_by'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    $text = implode(', ', $names);
    if ($maxLen !== null && $maxLen > 0 && strlen($text) > $maxLen) {
        $text = substr($text, 0, $maxLen);
    }
    return $text;
}

cleanProjectPendingUploads();

include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string) $_POST['action']);
    if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
        jsonResponse(array('success' => false, 'message' => 'Sesi login tidak valid.'));
    }
    if ($action === 'upload_editor_image') {
        handleEditorUpload(
            'file',
            array('jpg', 'jpeg', 'png', 'gif', 'webp'),
            5 * 1024 * 1024,
            'img_',
            true
        );
    }
    if ($action === 'upload_editor_attachment') {
        handleEditorUpload(
            'file',
            array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png'),
            15 * 1024 * 1024,
            'att_',
            false
        );
    }
    if ($action === 'fetch_issue_options') {
        $scope = strtolower(trim((string) ($_POST['issue_scope'] ?? 'all')));
        if (!in_array($scope, array('all', 'active', 'done'), true)) {
            $scope = 'all';
        }

        $term = trim((string) ($_POST['q'] ?? ''));
        $baseSql = "SELECT TOP 80 i.issue_id, i.issue_name, i.issue_type, i.status, i.created_by
                    FROM dbo.issues i
                    WHERE 1=1";
        $params = array();

        if ($scope === 'active') {
            $baseSql .= " AND (i.status IS NULL OR UPPER(LTRIM(RTRIM(i.status))) NOT IN ('DONE', 'CLOSED'))";
        } elseif ($scope === 'done') {
            $baseSql .= " AND UPPER(LTRIM(RTRIM(i.status))) IN ('DONE', 'CLOSED')";
        }

        if ($term !== '') {
            $like = '%' . $term . '%';
            $baseSql .= " AND (
                            i.issue_name LIKE ?
                            OR CAST(i.issue_id AS VARCHAR(20)) LIKE ?
                            OR i.created_by LIKE ?
                            OR i.issue_type LIKE ?
                        )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $baseSql .= " ORDER BY i.issue_id DESC";
        $stmtIssue = sqlsrv_query($conn, $baseSql, $params);
        if ($stmtIssue === false) {
            jsonResponse(array('success' => false, 'message' => 'Gagal mengambil data issues.'));
        }

        $results = array();
        while ($row = sqlsrv_fetch_array($stmtIssue, SQLSRV_FETCH_ASSOC)) {
            $results[] = array(
                'id' => (int) ($row['issue_id'] ?? 0),
                'text' => buildIssueOptionLabel($row),
                'group' => isDoneIssueStatus($row['status'] ?? '') ? 'Selesai' : 'Aktif',
                'status' => trim((string) ($row['status'] ?? ''))
            );
        }
        jsonResponse(array('success' => true, 'results' => $results));
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themePalette = getThemePalette($themeColor);
$status = '';
$statusType = 'success';
$assigneeOptions = array(
    'Dade Syahrul Ramdani - Infra & Support',
    'Derri Muhammad Ramdani',
    'Dika Pratama - Infra & Support',
    'Fikri Salman Ramadhan - ERP & Software',
    'Komara Sidik Permana - ERP & Software',
    'Muhammad Zhafran Irfan - Infra & Support',
    'Mulyadi Adi Saputra - ERP & Software',
    'Panji Mahesya',
    'Rismayanti - ERP & Software',
    'Rivaldi Arya Setia - ERP & Software',
    'Tina Martina - Infra & Support',
    'Yusup Sam - Infra & Support'
);
$clientOptions = array();
$sqlClient = "SELECT 
                m.id_emp,
                ISNULL(m.nama_lengkap, '') AS nama_lengkap,
                ISNULL(NULLIF(d.dept, ''), ISNULL(NULLIF(d2.dept, ''), '-')) AS dept,
                ISNULL(NULLIF(b.bagian, ''), ISNULL(NULLIF(b2.bagian, ''), '-')) AS bagian
              FROM dbo.m_emp m
              LEFT JOIN dbo.m_dept d ON m.id_dept = d.id_dept
              LEFT JOIN dbo.m_bag b ON m.id_bag = b.id_bag
              LEFT JOIN dbo.m_subbag s ON m.id_subbag = s.id_subbag
              LEFT JOIN dbo.m_bag b2 ON s.id_bag = b2.id_bag
              LEFT JOIN dbo.m_dept d2 ON b2.id_dept = d2.id_dept
              WHERE ISNULL(aktif, 0) = 1
              ORDER BY m.nama_lengkap ASC";
$stmtClient = sqlsrv_query($conn, $sqlClient);
if ($stmtClient) {
    while ($row = sqlsrv_fetch_array($stmtClient, SQLSRV_FETCH_ASSOC)) {
        $name = trim((string) ($row['nama_lengkap'] ?? ''));
        if ($name === '') {
            continue;
        }
        $dept = trim((string) ($row['dept'] ?? '-'));
        if ($dept === '') {
            $dept = '-';
        }
        $bagian = trim((string) ($row['bagian'] ?? '-'));
        if ($bagian === '') {
            $bagian = '-';
        }
        $clientOptions[] = array(
            'id_emp' => (int) ($row['id_emp'] ?? 0),
            'nama_lengkap' => $name,
            'dept' => $dept,
            'bagian' => $bagian
        );
    }
}

list($issueLinkReady, $issueLinkError) = ensureProjectIssueLinkTable($conn);
if (!$issueLinkReady) {
    $statusType = 'danger';
    $status = $issueLinkError;
}
list($progressAutoReady, $progressAutoError) = ensureProjectProgressAutoColumn($conn);
if (!$progressAutoReady) {
    $statusType = 'danger';
    $status = $progressAutoError;
}

$projectIdIsIdentity = true;
$assigneeColumnMaxLen = getProjectColumnMaxLength($conn, 'assignee');
$stmtProjectIdMeta = sqlsrv_query(
    $conn,
    "SELECT COLUMNPROPERTY(OBJECT_ID('dbo.project'), 'projectid', 'IsIdentity') AS is_identity"
);
if ($stmtProjectIdMeta) {
    $rowProjectIdMeta = sqlsrv_fetch_array($stmtProjectIdMeta, SQLSRV_FETCH_ASSOC);
    if ($rowProjectIdMeta && isset($rowProjectIdMeta['is_identity'])) {
        $projectIdIsIdentity = ((int) $rowProjectIdMeta['is_identity'] === 1);
    }
}

$formToken = getOrCreateFormToken(isset($_POST['form_token']) ? $_POST['form_token'] : '');
$selectedIssueIds = array();
$selectedIssueItems = array();

$form = array(
    'form_token' => $formToken,
    'projectname' => '',
    'assignee' => array(),
    'client' => '',
    'startdate' => '',
    'deadline' => '',
    'progress' => '0',
    'progress_auto' => '1',
    'description' => '',
    'issue_scope' => 'all'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['form_token'] = getOrCreateFormToken(isset($_POST['form_token']) ? $_POST['form_token'] : $form['form_token']);
    $form['projectname'] = isset($_POST['projectname']) ? trim($_POST['projectname']) : '';
    $form['client'] = isset($_POST['client']) ? trim($_POST['client']) : '';
    $form['startdate'] = isset($_POST['startdate']) ? trim($_POST['startdate']) : '';
    $form['deadline'] = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
    $form['progress'] = isset($_POST['progress']) ? trim($_POST['progress']) : '0';
    $form['progress_auto'] = isset($_POST['progress_auto']) ? '1' : '0';
    $form['description'] = isset($_POST['description']) ? trim($_POST['description']) : '';
    $form['issue_scope'] = isset($_POST['issue_scope']) ? strtolower(trim((string) $_POST['issue_scope'])) : 'all';
    if (!in_array($form['issue_scope'], array('all', 'active', 'done'), true)) {
        $form['issue_scope'] = 'all';
    }
    $selectedIssueIds = parseIssueIdList($_POST['issue_ids'] ?? array());

    $progress = (int) $form['progress'];
    if ($form['progress_auto'] === '1') {
        $progress = calculateProgressFromIssueIds($conn, $selectedIssueIds);
        $form['progress'] = (string) $progress;
    }
    $assigneeText = deriveAssigneeFromIssueIds($conn, $selectedIssueIds, $assigneeColumnMaxLen);
    $createBy = trim((string) ($_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? 'system'));

    if ($form['projectname'] === '') {
        $statusType = 'danger';
        $status = 'Project Name wajib diisi.';
    } elseif ($form['startdate'] === '' || strtotime($form['startdate']) === false) {
        $statusType = 'danger';
        $status = 'Start date wajib valid.';
    } elseif ($form['deadline'] === '' || strtotime($form['deadline']) === false) {
        $statusType = 'danger';
        $status = 'Deadline wajib valid.';
    } elseif (strtotime($form['deadline']) < strtotime($form['startdate'])) {
        $statusType = 'danger';
        $status = 'Deadline tidak boleh lebih kecil dari start date.';
    } elseif ($progress < 0 || $progress > 100) {
        $statusType = 'danger';
        $status = 'Progress harus antara 0 sampai 100.';
    } elseif (!$issueLinkReady) {
        $statusType = 'danger';
        $status = $issueLinkError !== '' ? $issueLinkError : 'Tabel relasi issue project belum siap.';
    } elseif (!$progressAutoReady) {
        $statusType = 'danger';
        $status = $progressAutoError !== '' ? $progressAutoError : 'Kolom mode progress project belum siap.';
    } else {
        $sqlInsert = "INSERT INTO dbo.project
            (projectname, assignee, client, startdate, deadline, progress, progress_auto, [desc], creatby, creatat, updateby, updateat)
            OUTPUT INSERTED.projectid
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), NULL, NULL)";
        $params = array(
            $form['projectname'],
            $assigneeText,
            $form['client'],
            $form['startdate'],
            $form['deadline'],
            $progress,
            (int) $form['progress_auto'],
            $form['description'] !== '' ? $form['description'] : null,
            $createBy !== '' ? $createBy : null
        );

        $projectId = null;
        if (sqlsrv_begin_transaction($conn)) {
            $maxProjectIdBefore = 0;
            $stmtMaxBefore = sqlsrv_query($conn, "SELECT ISNULL(MAX(projectid), 0) AS max_projectid FROM dbo.project WITH (UPDLOCK, HOLDLOCK)");
            if ($stmtMaxBefore) {
                $rowMaxBefore = sqlsrv_fetch_array($stmtMaxBefore, SQLSRV_FETCH_ASSOC);
                if ($rowMaxBefore && isset($rowMaxBefore['max_projectid'])) {
                    $maxProjectIdBefore = (int) $rowMaxBefore['max_projectid'];
                }
            }

            if (!$projectIdIsIdentity) {
                $projectId = $maxProjectIdBefore + 1;
                $sqlInsert = "INSERT INTO dbo.project
                    (projectid, projectname, assignee, client, startdate, deadline, progress, progress_auto, [desc], creatby, creatat, updateby, updateat)
                    OUTPUT INSERTED.projectid
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), NULL, NULL)";
                $params = array(
                    $projectId,
                    $form['projectname'],
                    $assigneeText,
                    $form['client'],
                    $form['startdate'],
                    $form['deadline'],
                    $progress,
                    (int) $form['progress_auto'],
                    $form['description'] !== '' ? $form['description'] : null,
                    $createBy !== '' ? $createBy : null
                );
            }

            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);
            if ($stmtInsert) {
                $rowInserted = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC);
                if ($rowInserted && array_key_exists('projectid', $rowInserted)) {
                    $pidValue = $rowInserted['projectid'];
                    if ($pidValue !== null && $pidValue !== '') {
                        $projectId = $pidValue;
                    }
                }

                if ($projectId === null) {
                    $stmtLatest = sqlsrv_query(
                        $conn,
                        "SELECT TOP 1 projectid
                         FROM dbo.project
                         ORDER BY projectid DESC"
                    );
                    if ($stmtLatest) {
                        $rowLatest = sqlsrv_fetch_array($stmtLatest, SQLSRV_FETCH_ASSOC);
                        if ($rowLatest && isset($rowLatest['projectid'])) {
                            $pidValue = $rowLatest['projectid'];
                            if ($pidValue !== null && $pidValue !== '') {
                                $projectId = $pidValue;
                            }
                        }
                    }
                }

                if ($projectId === null) {
                    $sqlFindInserted = "SELECT TOP 1 projectid
                                        FROM dbo.project
                                        WHERE projectname = ?
                                          AND assignee = ?
                                          AND client = ?
                                        ORDER BY creatat DESC, projectid DESC";
                    $findParams = array(
                        $form['projectname'],
                        $assigneeText,
                        $form['client']
                    );
                    $stmtFindInserted = sqlsrv_query($conn, $sqlFindInserted, $findParams);
                    if ($stmtFindInserted) {
                        $foundRow = sqlsrv_fetch_array($stmtFindInserted, SQLSRV_FETCH_ASSOC);
                        if ($foundRow && isset($foundRow['projectid'])) {
                            $pidValue = $foundRow['projectid'];
                            if ($pidValue !== null && $pidValue !== '') {
                                $projectId = $pidValue;
                            }
                        }
                    }
                }
            }

            if ($projectId === null) {
                sqlsrv_rollback($conn);
                $statusType = 'danger';
                $status = 'Gagal menyimpan project. ID project tidak terbaca setelah proses insert.';
                $errors = sqlsrv_errors();
                if (!empty($errors[0]['message'])) {
                    $status .= ' ' . trim($errors[0]['message']);
                } else {
                    $dbgParts = array();

                    $stmtObj = sqlsrv_query(
                        $conn,
                        "SELECT o.type_desc AS object_type
                         FROM sys.objects o
                         WHERE o.object_id = OBJECT_ID('dbo.project')"
                    );
                    if ($stmtObj) {
                        $rowObj = sqlsrv_fetch_array($stmtObj, SQLSRV_FETCH_ASSOC);
                        if (!empty($rowObj['object_type'])) {
                            $dbgParts[] = 'Object: ' . trim((string) $rowObj['object_type']);
                        }
                    }

                    $stmtTrig = sqlsrv_query(
                        $conn,
                        "SELECT TOP 5 t.name
                         FROM sys.triggers t
                         WHERE t.parent_id = OBJECT_ID('dbo.project')
                         ORDER BY t.name ASC"
                    );
                    if ($stmtTrig) {
                        $triggerNames = array();
                        while ($rowTrig = sqlsrv_fetch_array($stmtTrig, SQLSRV_FETCH_ASSOC)) {
                            $tn = trim((string) ($rowTrig['name'] ?? ''));
                            if ($tn !== '') {
                                $triggerNames[] = $tn;
                            }
                        }
                        if (!empty($triggerNames)) {
                            $dbgParts[] = 'Trigger: ' . implode(', ', $triggerNames);
                        } else {
                            $dbgParts[] = 'Trigger: -';
                        }
                    }

                    $stmtMatch = sqlsrv_query(
                        $conn,
                        "SELECT COUNT(*) AS cnt, ISNULL(MAX(projectid), 0) AS max_projectid
                         FROM dbo.project
                         WHERE projectname = ?
                           AND assignee = ?
                           AND client = ?
                           AND startdate = ?
                           AND deadline = ?
                           AND progress = ?",
                        array(
                            $form['projectname'],
                            $assigneeText,
                            $form['client'],
                            $form['startdate'],
                            $form['deadline'],
                            $progress
                        )
                    );
                    if ($stmtMatch) {
                        $rowMatch = sqlsrv_fetch_array($stmtMatch, SQLSRV_FETCH_ASSOC);
                        if ($rowMatch) {
                            $dbgParts[] = 'MatchRow=' . (int) ($rowMatch['cnt'] ?? 0);
                            $dbgParts[] = 'MaxId=' . trim((string) ($rowMatch['max_projectid'] ?? ''));
                        }
                    }

                    if (!empty($dbgParts)) {
                        $status .= ' [' . implode(' | ', $dbgParts) . ']';
                    }
                }
            } else {
                $pendingUploads = array();
                if (isset($_SESSION['project_pending_uploads'][$form['form_token']]) && is_array($_SESSION['project_pending_uploads'][$form['form_token']])) {
                    $pendingUploads = $_SESSION['project_pending_uploads'][$form['form_token']];
                }

                $attachmentInsertOk = true;
                if (!empty($pendingUploads)) {
                    $sqlAttachment = "INSERT INTO dbo.project_attachments
                        (projectid, file_name, file_path, file_ext, file_size, mime_type, is_inline, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

                    foreach ($pendingUploads as $uploadMeta) {
                        $fileName = trim((string) ($uploadMeta['file_name'] ?? ''));
                        $filePath = trim((string) ($uploadMeta['file_path'] ?? ''));
                        $fileExt = strtolower(trim((string) ($uploadMeta['file_ext'] ?? '')));
                        $fileSize = (int) ($uploadMeta['file_size'] ?? 0);
                        $mimeType = trim((string) ($uploadMeta['mime_type'] ?? ''));
                        $isInline = (int) ($uploadMeta['is_inline'] ?? 0) === 1 ? 1 : 0;

                        if ($filePath === '' || $fileName === '') {
                            continue;
                        }

                        $attachParams = array(
                            $projectId,
                            $fileName,
                            $filePath,
                            $fileExt !== '' ? $fileExt : null,
                            $fileSize > 0 ? $fileSize : 0,
                            $mimeType !== '' ? $mimeType : null,
                            $isInline,
                            $createBy !== '' ? $createBy : null
                        );
                        $stmtAttachment = sqlsrv_query($conn, $sqlAttachment, $attachParams);
                        if (!$stmtAttachment) {
                            $attachmentInsertOk = false;
                            break;
                        }
                    }
                }

                $issueLinkInsertOk = true;
                if ($attachmentInsertOk && !empty($selectedIssueIds)) {
                    $placeholders = implode(',', array_fill(0, count($selectedIssueIds), '?'));
                    $sqlIssueStatus = "SELECT issue_id, status FROM dbo.issues WHERE issue_id IN ($placeholders)";
                    $stmtIssueStatus = sqlsrv_query($conn, $sqlIssueStatus, $selectedIssueIds);
                    $issueStatusMap = array();
                    if ($stmtIssueStatus) {
                        while ($rowIssueStatus = sqlsrv_fetch_array($stmtIssueStatus, SQLSRV_FETCH_ASSOC)) {
                            $issueStatusMap[(int) ($rowIssueStatus['issue_id'] ?? 0)] = trim((string) ($rowIssueStatus['status'] ?? ''));
                        }
                    }

                    $sqlIssueLink = "INSERT INTO dbo.project_issue_links
                        (projectid, issue_id, issue_status_snapshot, created_by, created_at)
                        VALUES (?, ?, ?, ?, GETDATE())";
                    foreach ($selectedIssueIds as $issueId) {
                        $statusSnapshot = $issueStatusMap[$issueId] ?? null;
                        $stmtIssueLink = sqlsrv_query($conn, $sqlIssueLink, array(
                            $projectId,
                            $issueId,
                            $statusSnapshot !== '' ? $statusSnapshot : null,
                            $createBy !== '' ? $createBy : null
                        ));
                        if (!$stmtIssueLink) {
                            $issueLinkInsertOk = false;
                            break;
                        }
                    }
                }

                if (!$attachmentInsertOk) {
                    sqlsrv_rollback($conn);
                    $statusType = 'danger';
                    $status = 'Project tersimpan gagal karena gagal menyimpan lampiran.';
                    $errors = sqlsrv_errors();
                    if (!empty($errors[0]['message'])) {
                        $status .= ' ' . trim($errors[0]['message']);
                    }
                } elseif (!$issueLinkInsertOk) {
                    sqlsrv_rollback($conn);
                    $statusType = 'danger';
                    $status = 'Project tersimpan gagal karena gagal menyimpan list kegiatan dari issues.';
                    $errors = sqlsrv_errors();
                    if (!empty($errors[0]['message'])) {
                        $status .= ' ' . trim($errors[0]['message']);
                    }
                } else {
                    sqlsrv_commit($conn);
                    unset($_SESSION['project_pending_uploads'][$form['form_token']]);
                    header('Location: project.php?status=added');
                    exit;
                }
            }
        } else {
            $statusType = 'danger';
            $status = 'Gagal memulai transaksi penyimpanan project.';
        }
    }
}

if (!empty($selectedIssueIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIssueIds), '?'));
    $sqlSelectedIssues = "SELECT i.issue_id, i.issue_name, i.issue_type, i.status, i.created_by
                          FROM dbo.issues i
                          WHERE i.issue_id IN ($placeholders)
                          ORDER BY i.issue_id DESC";
    $stmtSelectedIssues = sqlsrv_query($conn, $sqlSelectedIssues, $selectedIssueIds);
    if ($stmtSelectedIssues) {
        while ($rowSelected = sqlsrv_fetch_array($stmtSelectedIssues, SQLSRV_FETCH_ASSOC)) {
            $selectedIssueItems[] = array(
                'id' => (int) ($rowSelected['issue_id'] ?? 0),
                'text' => buildIssueOptionLabel($rowSelected),
                'group' => isDoneIssueStatus($rowSelected['status'] ?? '') ? 'Selesai' : 'Aktif'
            );
        }
    }
}
?>

<style>
    .create-project-page {
        --accent-1: <?php echo h($themePalette['start']); ?>;
        --accent-2: <?php echo h($themePalette['end']); ?>;
        --accent-rgb: <?php echo h($themePalette['rgb']); ?>;
        --accent-text: <?php echo h($themePalette['text']); ?>;
    }
    .create-project-page .hero-card {
        border: 1px solid #dfe6ee;
        overflow: hidden;
        box-shadow: 0 14px 34px rgba(16, 24, 40, .08);
        border-radius: 16px;
    }
    .create-project-page .hero-head {
        background: linear-gradient(120deg, var(--accent-1) 0%, var(--accent-2) 100%);
        color: var(--accent-text);
        padding: 20px 24px;
    }
    .create-project-page .hero-head h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;
        letter-spacing: .1px;
    }
    .create-project-page .hero-head p {
        margin: 6px 0 0;
        opacity: .94;
        font-size: .95rem;
    }
    .create-project-page .form-shell {
        background: #fff;
        border: 1px solid #e0e7ef;
        border-radius: 14px;
        padding: 18px 18px 14px;
        margin-bottom: 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .03);
    }
    .create-project-page .form-shell.identity-shell {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-color: #d6e3f0;
    }
    .create-project-page .form-shell.issues-shell {
        background: linear-gradient(180deg, #fafdff 0%, #f3f8fd 100%);
        border-color: #d5e2ef;
    }
    .create-project-page .section-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #506175;
        margin-bottom: 14px;
        padding-bottom: 8px;
        border-bottom: 1px dashed #d7dee7;
    }
    .create-project-page .section-label i {
        color: var(--accent-2);
        font-size: .85rem;
    }
    .create-project-page .section-sub {
        margin: -4px 0 12px;
        font-size: .81rem;
        color: #6d7f92;
    }
    .create-project-page .identity-shell .row {
        margin-left: -8px;
        margin-right: -8px;
    }
    .create-project-page .identity-shell .row > [class*="col-"] {
        padding-left: 8px;
        padding-right: 8px;
    }
    .create-project-page .identity-shell .form-group {
        margin-bottom: 13px;
    }
    .create-project-page .input-group-text {
        min-width: 42px;
        height: 42px;
        justify-content: center;
        color: #495a6b;
        background: #f8fafc;
        border-color: #cfd8e3;
    }
    .create-project-page .form-group label {
        margin-bottom: 7px;
        font-size: .9rem;
        font-weight: 600;
        color: #27364a;
    }
    .create-project-page .form-control,
    .create-project-page .custom-select,
    .create-project-page .select2-container--bootstrap4 .select2-selection--single {
        border-radius: 9px;
        border-color: #cfd8e3;
        min-height: 42px;
    }
    .create-project-page .form-control:focus,
    .create-project-page .custom-select:focus,
    .create-project-page .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border-color: var(--accent-2);
        box-shadow: 0 0 0 .18rem rgba(var(--accent-rgb), .14);
    }
    .create-project-page .input-group > .form-control:not(:first-child),
    .create-project-page .input-group > .custom-select:not(:first-child) {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    .create-project-page .action-area {
        border-top: 1px solid #dfe6ee;
        padding-top: 16px;
    }
    .create-project-page .btn {
        border-radius: 9px;
        font-weight: 600;
    }
    .create-project-page .note-editor.note-frame {
        border-radius: 11px;
        border-color: #cfd8e3;
        overflow: hidden;
    }
    .create-project-page .note-toolbar {
        background: #f7fafc;
        border-bottom-color: #d8e0e8;
    }
    .create-project-page .note-editor .note-btn {
        border-radius: 6px;
    }
    .create-project-page .note-editor .note-editable {
        font-size: .94rem;
        line-height: 1.55;
    }
    .create-project-page .desc-tools {
        margin-top: 8px;
    }
    .create-project-page .desc-tools .btn {
        border-radius: 6px;
    }
    .create-project-page .upload-hint {
        margin-top: 6px;
        color: #6c757d;
        font-size: .82rem;
    }
    .create-project-page .select2-container--bootstrap4 .select2-selection--single {
        min-height: 42px;
        padding-top: 5px;
    }
    .create-project-page .select2-container--bootstrap4 .select2-selection__rendered {
        line-height: 1.4;
        color: #27364a;
    }
    .create-project-page .client-select-wrap {
        position: relative;
    }
    .create-project-page .assignee-select-wrap {
        position: relative;
    }
    .create-project-page .assignee-select-wrap .assignee-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 42px;
        min-height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #495a6b;
        background: #f8fafc;
        border: 1px solid #cfd8e3;
        border-right: 0;
        border-radius: 9px 0 0 9px;
        z-index: 3;
    }
    .create-project-page .assignee-select-wrap > select.form-control {
        padding-left: 50px;
        min-height: 42px;
        border-radius: 9px;
    }
    .create-project-page .assignee-select-wrap .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 42px;
        border-radius: 9px;
        border-color: #cfd8e3;
        padding-left: 40px;
    }
    .create-project-page .assignee-select-wrap .select2-container--bootstrap4 .select2-selection__choice {
        border-radius: 999px !important;
        font-size: .8rem !important;
        padding: 3px 8px !important;
    }
    .create-project-page .client-select-wrap .client-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #495a6b;
        background: #f8fafc;
        border: 1px solid #cfd8e3;
        border-right: 0;
        border-radius: 9px 0 0 9px;
        z-index: 3;
    }
    .create-project-page .client-select-wrap > select.form-control {
        padding-left: 50px;
        height: 42px;
        border-radius: 9px;
    }
    .create-project-page .client-select-wrap .select2-container--bootstrap4 .select2-selection--single {
        min-height: 42px;
        height: 42px;
        padding-left: 42px;
        border-radius: 9px;
    }
    .create-project-page .client-select-wrap .select2-container--bootstrap4 .select2-selection__arrow {
        height: 40px;
    }
    .create-project-page .issue-picker-wrap {
        border: 1px solid #cfdeec;
        border-radius: 12px;
        background: #f7fbff;
        padding: 12px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7);
    }
    .create-project-page .issue-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding-bottom: 8px;
        margin-bottom: 8px;
        border-bottom: 1px dashed #d8e5f2;
    }
    .create-project-page .issue-filter-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 0;
    }
    .create-project-page .issue-filter-row label {
        margin-bottom: 0;
        font-size: .82rem;
        color: #455f77;
        font-weight: 700;
    }
    .create-project-page .issue-filter-row .custom-select {
        max-width: 220px;
    }
    .create-project-page .issue-summary-inline {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .create-project-page .issue-summary-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .2px;
    }
    .create-project-page .issue-summary-pill.pill-primary {
        background: #e7f1fb;
        color: #1d5f97;
        border: 1px solid #c2d9ef;
    }
    .create-project-page .issue-summary-pill.pill-success {
        background: #e4f6ea;
        color: #0f6d39;
        border: 1px solid #bee6cb;
    }
    .create-project-page .issue-picker-wrap .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 46px;
        border-radius: 9px;
        border-color: #c7d8e8;
        padding: 3px 6px;
        background: #fff;
    }
    .create-project-page .issue-picker-wrap .select2-selection__choice {
        border-radius: 999px !important;
        font-size: .79rem !important;
        padding: 3px 9px !important;
        border: 1px solid #b7d0e7 !important;
        background: #ecf5ff !important;
        color: #224867 !important;
    }
    .create-project-page .issue-help {
        margin-top: 8px;
        color: #6f7f90;
        font-size: .82rem;
    }
    .create-project-page .progress-field .progress-meta {
        background: #f8fbff;
        border: 1px solid #dfe8f2;
        border-radius: 12px;
        padding: 10px 12px 12px;
    }
    .create-project-page .progress-field .progress-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .create-project-page .progress-field .progress-head label {
        margin: 0;
    }
    .create-project-page .progress-field .progress-tip {
        color: #6f7f90;
        font-size: .83rem;
        margin: 0 0 8px;
    }
    .create-project-page .progress-field .progress-badge {
        min-width: 62px;
        text-align: center;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(120deg, var(--accent-1) 0%, var(--accent-2) 100%);
        padding: 5px 12px;
    }
    .create-project-page .progress-slider-wrap {
        position: relative;
        padding-top: 22px;
        margin-bottom: 10px;
    }
    .create-project-page #progressRange {
        width: 100%;
        height: 9px;
        border-radius: 999px;
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        background: linear-gradient(90deg, var(--accent-2) 0%, #dae3ec 0%);
    }
    .create-project-page #progressRange::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 22px;
        height: 22px;
        border: 3px solid #fff;
        border-radius: 50%;
        background: #17a2b8;
        box-shadow: 0 4px 10px rgba(23, 162, 184, .34);
        cursor: pointer;
    }
    .create-project-page #progressRange::-moz-range-thumb {
        width: 22px;
        height: 22px;
        border: 3px solid #fff;
        border-radius: 50%;
        background: #17a2b8;
        box-shadow: 0 4px 10px rgba(23, 162, 184, .34);
        cursor: pointer;
    }
    .create-project-page .progress-bubble {
        position: absolute;
        top: -2px;
        left: 0;
        transform: translateX(-50%);
        min-width: 38px;
        text-align: center;
        border-radius: 9px;
        background: #212529;
        color: #fff;
        font-size: .76rem;
        font-weight: 700;
        padding: 3px 7px;
        line-height: 1.2;
        pointer-events: none;
    }
    .create-project-page .progress-bubble::after {
        content: '';
        position: absolute;
        left: 50%;
        bottom: -6px;
        transform: translateX(-50%);
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 6px solid #212529;
    }
    .create-project-page .progress-field .input-group-text,
    .create-project-page .progress-field .form-control {
        min-height: 38px;
        height: 38px;
    }
    .create-project-page .action-area .btn {
        min-width: 120px;
    }
    .create-project-page .action-area .btn i {
        font-size: .84rem;
    }
    .create-project-page .project-notif {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-radius: 12px;
        border: 1px solid rgba(var(--accent-rgb), .35);
        background: linear-gradient(120deg, rgba(var(--accent-rgb), .16) 0%, rgba(var(--accent-rgb), .08) 100%);
        color: #173552;
        padding: 11px 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        position: relative;
        overflow: hidden;
        animation: notifIn .25s ease-out;
    }
    .create-project-page .project-notif::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--accent-1) 0%, var(--accent-2) 100%);
    }
    .create-project-page .project-notif .n-icon {
        width: 28px;
        height: 28px;
        min-width: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--accent-rgb), .18);
        color: var(--accent-1);
        margin-top: 1px;
    }
    .create-project-page .project-notif .n-text {
        font-weight: 600;
        line-height: 1.45;
        padding-right: 18px;
    }
    .create-project-page .project-notif .n-close {
        position: absolute;
        right: 8px;
        top: 6px;
        border: none;
        background: transparent;
        color: #5d7388;
        cursor: pointer;
        font-size: 1rem;
        padding: 0 4px;
    }
    .create-project-page .project-notif.is-danger {
        border-color: rgba(220, 53, 69, .35);
        background: linear-gradient(120deg, rgba(220, 53, 69, .16) 0%, rgba(220, 53, 69, .08) 100%);
        color: #5e1b21;
    }
    .create-project-page .project-notif.is-danger::before {
        background: linear-gradient(180deg, #dc3545 0%, #b92332 100%);
    }
    .create-project-page .project-notif.is-danger .n-icon {
        background: rgba(220, 53, 69, .18);
        color: #b92332;
    }
    .create-project-page .project-notif.is-warning {
        border-color: rgba(229, 157, 0, .35);
        background: linear-gradient(120deg, rgba(229, 157, 0, .16) 0%, rgba(229, 157, 0, .08) 100%);
        color: #644300;
    }
    .create-project-page .project-notif.is-warning::before {
        background: linear-gradient(180deg, #f3b41c 0%, #e59d00 100%);
    }
    .create-project-page .project-notif.is-warning .n-icon {
        background: rgba(229, 157, 0, .16);
        color: #ad7400;
    }
    @keyframes notifIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 767.98px) {
        .create-project-page .hero-head {
            padding: 16px 16px;
        }
        .create-project-page .hero-head h3 {
            font-size: 1.15rem;
        }
        .create-project-page .hero-head p {
            font-size: .88rem;
        }
        .create-project-page .card-body.p-4 {
            padding: 14px !important;
        }
        .create-project-page .form-shell {
            padding: 14px 12px 10px;
        }
        .create-project-page .progress-field .progress-head {
            gap: 6px;
            flex-wrap: wrap;
        }
        .create-project-page .action-area {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .create-project-page .action-area .btn {
            width: 100%;
            margin: 0 !important;
        }
        .create-project-page .issue-toolbar {
            align-items: flex-start;
        }
        .create-project-page .issue-summary-inline {
            width: 100%;
        }
    }
</style>

<div class="content-wrapper create-project-page">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Create Project</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="project.php">Project</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($status !== '') { ?>
                <?php
                $notifClass = 'is-warning';
                $notifIcon = 'fas fa-info-circle';
                if ($statusType === 'success') {
                    $notifClass = 'is-success';
                    $notifIcon = 'fas fa-check-circle';
                } elseif ($statusType === 'danger') {
                    $notifClass = 'is-danger';
                    $notifIcon = 'fas fa-exclamation-circle';
                }
                ?>
                <div class="project-notif <?php echo h($notifClass); ?>" role="alert" id="projectNotif">
                    <div class="n-icon"><i class="<?php echo h($notifIcon); ?>"></i></div>
                    <div class="n-text"><?php echo h($status); ?></div>
                    <button type="button" class="n-close" aria-label="Close" onclick="document.getElementById('projectNotif').style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>

            <div class="card hero-card">
                <div class="hero-head">
                    <h3><i class="fas fa-folder-plus mr-1"></i>New Project</h3>
                    <p>Lengkapi detail project dengan rapi untuk memudahkan tracking progress tim.</p>
                </div>
                <div class="card-body p-4">
                    <form method="post" autocomplete="off" enctype="multipart/form-data">
                        <input type="hidden" name="form_token" id="formToken" value="<?php echo h($form['form_token']); ?>">
                        <div class="form-shell identity-shell">
                            <div class="section-label"><i class="fas fa-id-card"></i> Project Identity</div>
                            <div class="section-sub">Isi informasi dasar project dengan jelas agar tim mudah follow up.</div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Project Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-folder-open"></i></span>
                                            </div>
                                            <input type="text" name="projectname" class="form-control" placeholder="Nama project" value="<?php echo h($form['projectname']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Client</label>
                                        <div class="client-select-wrap">
                                            <span class="client-icon"><i class="fas fa-building"></i></span>
                                            <select name="client" id="clientSelect" class="form-control">
                                                <option value="">Pilih client...</option>
                                                <?php
                                                if ($form['client'] !== '') {
                                                    $existsInList = false;
                                                    foreach ($clientOptions as $opt) {
                                                        if ($opt['nama_lengkap'] === $form['client']) {
                                                            $existsInList = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$existsInList) {
                                                        echo '<option value="' . h($form['client']) . '" selected>' . h($form['client']) . '</option>';
                                                    }
                                                }
                                                foreach ($clientOptions as $opt) {
                                                    $label = $opt['nama_lengkap'] . ' - ' . $opt['dept'] . ' (' . $opt['bagian'] . ')';
                                                    $selected = ($form['client'] === $opt['nama_lengkap']) ? 'selected' : '';
                                                    echo '<option value="' . h($opt['nama_lengkap']) . '" ' . $selected . '>' . h($label) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-shell">
                            <div class="section-label"><i class="fas fa-stream"></i> Timeline & Progress</div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Start Date <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                            </div>
                                            <input type="date" name="startdate" class="form-control" value="<?php echo h($form['startdate']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Deadline <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="far fa-calendar-check"></i></span>
                                            </div>
                                            <input type="date" name="deadline" class="form-control" value="<?php echo h($form['deadline']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group progress-field">
                                        <div class="progress-meta">
                                            <div class="progress-head">
                                                <label>Progress (0-100)</label>
                                                <span class="progress-badge" id="progressPercentLabel">0%</span>
                                            </div>
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="progressAuto" name="progress_auto" value="1" <?php echo $form['progress_auto'] === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="progressAuto">Calculate progress through tasks</label>
                                            </div>
                                            <p class="progress-tip">Jika aktif, progress dihitung dari jumlah issues tertaut yang sudah selesai.</p>
                                            <div class="progress-slider-wrap">
                                                <div class="progress-bubble" id="progressBubble">0</div>
                                                <input type="range" id="progressRange" min="0" max="100" step="1" value="<?php echo h($form['progress']); ?>">
                                            </div>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-list-ul"></i></span>
                                                </div>
                                                <input type="number" id="progressInput" name="progress" min="0" max="100" class="form-control" value="<?php echo h($form['progress']); ?>">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-shell mb-0">
                            <div class="section-label"><i class="fas fa-align-left"></i> Description</div>
                            <div class="form-group mb-0">
                                <label>Project Description</label>
                                <textarea id="projectDescription" name="description" rows="8" class="form-control" placeholder="Tulis pesan, bisa gambar/video"><?php echo h($form['description']); ?></textarea>
                                <div class="desc-tools">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAttachDescFiles">
                                        <i class="fas fa-paperclip mr-1"></i> Attach Files
                                    </button>
                                    <input type="file" id="descriptionAttachInput" class="d-none" multiple>
                                </div>
                                <div class="upload-hint">
                                    Gambar bisa langsung drag/drop ke editor. File lampiran akan disisipkan sebagai link di deskripsi.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end action-area mt-3">
                            <a href="project.php" class="btn btn-secondary mr-2">
                                <i class="fas fa-arrow-left mr-1"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-<?php echo h($themeColor); ?>">
                                <i class="fas fa-save mr-1"></i> Simpan Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/summernote/summernote-bs4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/summernote/summernote-bs4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/summernote/lang/summernote-id-ID.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<script>
    $(function () {
        var uploadEndpoint = 'creat_project.php';
        var formToken = $('#formToken').val() || '';
        var $issueScope = $('#issueScope');

        if ($.fn.select2) {
            $('#clientSelect').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Pilih client...',
                allowClear: true
            });

            function formatIssueResult(item) {
                if (!item.id) {
                    return item.text;
                }
                var group = item.group ? item.group : '';
                var safeText = $('<div>').text(item.text || '').html();
                var badgeClass = group === 'Selesai' ? 'badge-success' : 'badge-info';
                var badge = group !== '' ? '<span class="badge ' + badgeClass + ' mr-1">' + group + '</span>' : '';
                return $('<span>' + badge + safeText + '</span>');
            }

            $('#issueSelect').select2({
                theme: 'bootstrap4',
                width: '100%',
                multiple: true,
                placeholder: 'Cari dan pilih kegiatan dari issues...',
                closeOnSelect: false,
                ajax: {
                    url: uploadEndpoint,
                    method: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'fetch_issue_options',
                            q: params.term || '',
                            issue_scope: $('#issueScope').val() || 'all'
                        };
                    },
                    processResults: function (data) {
                        if (!data || data.success !== true || !Array.isArray(data.results)) {
                            return { results: [] };
                        }
                        return { results: data.results };
                    },
                    cache: true
                },
                escapeMarkup: function (markup) {
                    return markup;
                },
                templateResult: formatIssueResult,
                templateSelection: function (item) {
                    return item.text || '';
                }
            });
        }

        function updateIssueSelectionSummary() {
            var selected = $('#issueSelect option:selected');
            var total = selected.length;
            var done = 0;

            selected.each(function () {
                var status = ($(this).attr('data-status') || '').toUpperCase();
                if (!status) {
                    var text = $(this).text() || '';
                    var m = text.match(/status\s*:\s*([^|]+)/i);
                    if (m && m[1]) {
                        status = $.trim(m[1]).toUpperCase();
                    }
                }
                if (status === 'DONE' || status === 'CLOSED') {
                    done++;
                }
            });

            $('#issueSelectedCount').text(total + ' dipilih');
            $('#issueDoneCount').text(done + ' selesai');
            if ($('#progressAuto').is(':checked')) {
                renderProgress(total > 0 ? Math.round((done / total) * 100) : 0);
            }
        }

        $('#issueScope').on('change', function () {
            if ($.fn.select2 && $('#issueSelect').data('select2')) {
                $('#issueSelect').select2('open');
            }
        });

        function normalizeProgress(value) {
            var num = parseInt(value, 10);
            if (isNaN(num)) {
                num = 0;
            }
            if (num < 0) {
                num = 0;
            }
            if (num > 100) {
                num = 100;
            }
            return num;
        }

        function renderProgress(value) {
            var safe = normalizeProgress(value);
            var $range = $('#progressRange');
            var $input = $('#progressInput');
            var $bubble = $('#progressBubble');
            var $badge = $('#progressPercentLabel');
            var ratio = safe / 100;
            var percent = ratio * 100;
            var pageEl = document.querySelector('.create-project-page');
            var accent = pageEl ? getComputedStyle(pageEl).getPropertyValue('--accent-2').trim() : '';
            if (!accent) {
                accent = '#28a745';
            }

            $range.val(safe);
            $input.val(safe);
            $badge.text(safe + '%');
            $bubble.text(safe);
            $bubble.css('left', percent + '%');
            $range.css('background', 'linear-gradient(90deg, ' + accent + ' ' + percent + '%, #dce3ea ' + percent + '%)');
        }

        $('#progressRange').on('input change', function () {
            if ($('#progressAuto').is(':checked')) {
                return;
            }
            renderProgress($(this).val());
        });

        $('#progressInput').on('input change blur', function () {
            if ($('#progressAuto').is(':checked')) {
                return;
            }
            renderProgress($(this).val());
        });

        function syncProgressMode() {
            var isAuto = $('#progressAuto').is(':checked');
            $('#progressRange, #progressInput').prop('readonly', isAuto).prop('disabled', isAuto);
            if (isAuto) {
                updateIssueSelectionSummary();
            }
        }

        $('#progressAuto').on('change', syncProgressMode);

        $('#issueSelect').on('change select2:select select2:unselect', function () {
            updateIssueSelectionSummary();
        });

        renderProgress($('#progressInput').val());
        updateIssueSelectionSummary();
        syncProgressMode();

        function uploadEditorFile(file, actionType, onSuccess) {
            var fd = new FormData();
            fd.append('action', actionType);
            fd.append('file', file);
            fd.append('form_token', formToken);

            $.ajax({
                url: uploadEndpoint,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function (resp) {
                    var data = resp;
                    if (typeof resp === 'string') {
                        try {
                            data = JSON.parse(resp);
                        } catch (e) {
                            alert('Respons upload tidak valid.');
                            return;
                        }
                    }

                    if (!data || data.success !== true || !data.url) {
                        alert((data && data.message) ? data.message : 'Upload gagal.');
                        return;
                    }

                    if (data.form_token) {
                        formToken = data.form_token;
                        $('#formToken').val(formToken);
                    }

                    if (typeof onSuccess === 'function') {
                        onSuccess(data);
                    }
                },
                error: function () {
                    alert('Gagal upload file. Coba lagi.');
                }
            });
        }

        $('#projectDescription').summernote({
            height: 280,
            minHeight: 220,
            lang: 'id-ID',
            placeholder: 'Tulis pesan, bisa gambar/video',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'italic', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['codeview', 'help']]
            ],
            callbacks: {
                onImageUpload: function (files) {
                    for (var i = 0; i < files.length; i++) {
                        (function (f) {
                            uploadEditorFile(f, 'upload_editor_image', function (data) {
                                $('#projectDescription').summernote('insertImage', data.url, function ($img) {
                                    $img.attr('alt', data.name || 'project-image');
                                });
                            });
                        })(files[i]);
                    }
                }
            }
        });

        $('#btnAttachDescFiles').on('click', function () {
            $('#descriptionAttachInput').trigger('click');
        });

        $('#descriptionAttachInput').on('change', function () {
            var files = this.files || [];
            if (!files.length) {
                return;
            }

            for (var i = 0; i < files.length; i++) {
                (function (f) {
                    uploadEditorFile(f, 'upload_editor_attachment', function (data) {
                        var safeName = $('<div>').text(data.name || 'Lampiran').html();
                        var safeUrl = $('<div>').text(data.url).html();
                        var linkHtml = '<p><a href="' + safeUrl + '" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ' + safeName + '</a></p>';
                        $('#projectDescription').summernote('pasteHTML', linkHtml);
                    });
                })(files[i]);
            }

            this.value = '';
        });
    });
</script>
<script>
    (function () {
        var el = document.getElementById('projectNotif');
        if (!el) return;
        setTimeout(function () {
            if (!el || el.style.display === 'none') return;
            el.style.transition = 'opacity .25s ease';
            el.style.opacity = '0';
            setTimeout(function () {
                if (el) el.style.display = 'none';
            }, 250);
        }, 5000);
    })();
</script>
