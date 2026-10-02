<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

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

function toInputDate($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    $ts = strtotime((string) $value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function formatProjectCode($projectId, $dateValue = null)
{
    $id = (int) $projectId;
    if ($id <= 0) {
        return '-';
    }

    $datePart = date('dmy');
    if ($dateValue instanceof DateTimeInterface) {
        $datePart = $dateValue->format('dmy');
    } elseif (!empty($dateValue)) {
        $ts = strtotime((string) $dateValue);
        if ($ts !== false) {
            $datePart = date('dmy', $ts);
        }
    }

    return 'P' . $id . '/' . $datePart;
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

function ensureProjectUploadDir()
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function createProjectUploadFileName($originalName, $prefix)
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

function handleProjectEditorImageUpload()
{
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        jsonResponse(array('success' => false, 'message' => 'File gambar tidak ditemukan.'));
    }

    $file = $_FILES['file'];
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(array('success' => false, 'message' => 'Upload gambar gagal.'));
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
        jsonResponse(array('success' => false, 'message' => 'Format gambar tidak diizinkan.'));
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > (5 * 1024 * 1024)) {
        jsonResponse(array('success' => false, 'message' => 'Ukuran gambar maksimal 5MB.'));
    }

    $uploadDir = ensureProjectUploadDir();
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        jsonResponse(array('success' => false, 'message' => 'Folder upload project tidak bisa ditulis.'));
    }

    $newFileName = createProjectUploadFileName($originalName, 'img_');
    $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
    if (!move_uploaded_file((string) $file['tmp_name'], $targetAbs)) {
        jsonResponse(array('success' => false, 'message' => 'Gagal menyimpan file gambar.'));
    }

    jsonResponse(array(
        'success' => true,
        'url' => '/gg_app/pages/project/uploads/' . rawurlencode($newFileName),
        'name' => $originalName !== '' ? $originalName : $newFileName
    ));
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themePalette = getThemePalette($themeColor);
$status = '';
$statusType = 'success';
$assigneeColumnMaxLen = getProjectColumnMaxLength($conn, 'assignee');
$projectId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['projectid']) ? (int) $_POST['projectid'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && trim((string) $_POST['action']) === 'upload_editor_image') {
    if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
        jsonResponse(array('success' => false, 'message' => 'Sesi login tidak valid.'));
    }
    handleProjectEditorImageUpload();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && trim((string) $_POST['action']) === 'fetch_issue_options') {
    if (!isset($_SESSION['UserId']) || empty($_SESSION['UserId'])) {
        jsonResponse(array('success' => false, 'message' => 'Sesi login tidak valid.'));
    }

    $scope = strtolower(trim((string) ($_POST['issue_scope'] ?? 'all')));
    if (!in_array($scope, array('all', 'active', 'done'), true)) {
        $scope = 'all';
    }
    $term = trim((string) ($_POST['q'] ?? ''));

    $sql = "SELECT TOP 80 i.issue_id, i.issue_name, i.issue_type, i.status, i.created_by
            FROM dbo.issues i
            WHERE 1=1";
    $params = array();

    if ($scope === 'active') {
        $sql .= " AND (i.status IS NULL OR UPPER(LTRIM(RTRIM(i.status))) NOT IN ('DONE', 'CLOSED'))";
    } elseif ($scope === 'done') {
        $sql .= " AND UPPER(LTRIM(RTRIM(i.status))) IN ('DONE', 'CLOSED')";
    }

    if ($term !== '') {
        $like = '%' . $term . '%';
        $sql .= " AND (i.issue_name LIKE ? OR CAST(i.issue_id AS VARCHAR(20)) LIKE ? OR i.created_by LIKE ? OR i.issue_type LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY i.issue_id DESC";
    $stmtIssue = sqlsrv_query($conn, $sql, $params);
    if ($stmtIssue === false) {
        jsonResponse(array('success' => false, 'message' => 'Gagal mengambil data issues.'));
    }

    $results = array();
    while ($row = sqlsrv_fetch_array($stmtIssue, SQLSRV_FETCH_ASSOC)) {
        $results[] = array(
            'id' => (int) ($row['issue_id'] ?? 0),
            'text' => buildIssueOptionLabel($row),
            'group' => isDoneIssueStatus($row['status'] ?? '') ? 'Selesai' : 'Aktif'
        );
    }
    jsonResponse(array('success' => true, 'results' => $results));
}

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

$selectedIssueIds = array();
$selectedIssueItems = array();

$form = array(
    'projectid' => $projectId,
    'projectname' => '',
    'project_code_date' => '',
    'assignee' => array(),
    'client' => '',
    'startdate' => '',
    'deadline' => '',
    'progress' => '0',
    'progress_auto' => '1',
    'description' => '',
    'issue_scope' => 'all'
);

if ($projectId > 0) {
    $sqlProject = "SELECT projectid, projectname, assignee, client, startdate, deadline, progress, ISNULL(progress_auto, 1) AS progress_auto, [desc], creatat
                   FROM dbo.project
                   WHERE projectid = ?";
    $stmtProject = sqlsrv_query($conn, $sqlProject, array($projectId));
    $projectRow = $stmtProject ? sqlsrv_fetch_array($stmtProject, SQLSRV_FETCH_ASSOC) : null;
    if ($projectRow) {
        $form['projectid'] = (int) ($projectRow['projectid'] ?? 0);
        $form['projectname'] = trim((string) ($projectRow['projectname'] ?? ''));
        $form['project_code_date'] = $projectRow['creatat'] ?? ($projectRow['startdate'] ?? '');
        $form['assignee'] = normalizeAssigneeSelection((string) ($projectRow['assignee'] ?? ''));
        $form['client'] = trim((string) ($projectRow['client'] ?? ''));
        $form['startdate'] = toInputDate($projectRow['startdate'] ?? '');
        $form['deadline'] = toInputDate($projectRow['deadline'] ?? '');
        $form['progress'] = (string) ((int) ($projectRow['progress'] ?? 0));
        $form['progress_auto'] = ((int) ($projectRow['progress_auto'] ?? 1)) === 1 ? '1' : '0';
        $form['description'] = (string) ($projectRow['desc'] ?? '');

        if ($issueLinkReady) {
            $sqlLinkedIssues = "SELECT l.issue_id, i.issue_name, i.issue_type, i.status, i.created_by
                                FROM dbo.project_issue_links l
                                LEFT JOIN dbo.issues i ON l.issue_id = i.issue_id
                                WHERE l.projectid = ?
                                ORDER BY l.link_id DESC";
            $stmtLinkedIssues = sqlsrv_query($conn, $sqlLinkedIssues, array($form['projectid']));
            if ($stmtLinkedIssues) {
                while ($rowIssue = sqlsrv_fetch_array($stmtLinkedIssues, SQLSRV_FETCH_ASSOC)) {
                    $issueId = (int) ($rowIssue['issue_id'] ?? 0);
                    if ($issueId <= 0) {
                        continue;
                    }
                    $selectedIssueIds[] = $issueId;
                    $selectedIssueItems[] = array(
                        'id' => $issueId,
                        'text' => buildIssueOptionLabel($rowIssue),
                        'group' => isDoneIssueStatus($rowIssue['status'] ?? '') ? 'Selesai' : 'Aktif'
                    );
                }
            }
        }
    } else {
        $statusType = 'danger';
        $status = 'Project tidak ditemukan.';
    }
} else {
    $statusType = 'danger';
    $status = 'Project ID tidak valid.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form['projectid'] > 0) {
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
    $updateBy = trim((string) ($_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? 'system'));

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
        $sqlUpdate = "UPDATE dbo.project
                      SET projectname = ?,
                          assignee = ?,
                          client = ?,
                          startdate = ?,
                          deadline = ?,
                          progress = ?,
                          progress_auto = ?,
                          [desc] = ?,
                          updateby = ?,
                          updateat = GETDATE()
                      WHERE projectid = ?";
        $params = array(
            $form['projectname'],
            $assigneeText,
            $form['client'],
            $form['startdate'],
            $form['deadline'],
            $progress,
            (int) $form['progress_auto'],
            $form['description'] !== '' ? $form['description'] : null,
            $updateBy !== '' ? $updateBy : null,
            $form['projectid']
        );

        if (sqlsrv_begin_transaction($conn)) {
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $params);
            if (!$stmtUpdate) {
                sqlsrv_rollback($conn);
                $statusType = 'danger';
                $status = 'Gagal memperbarui project.';
                $errors = sqlsrv_errors();
                if (!empty($errors[0]['message'])) {
                    $status .= ' ' . trim($errors[0]['message']);
                }
            } else {
                $stmtDeleteLinks = sqlsrv_query($conn, "DELETE FROM dbo.project_issue_links WHERE projectid = ?", array($form['projectid']));
                if (!$stmtDeleteLinks) {
                    sqlsrv_rollback($conn);
                    $statusType = 'danger';
                    $status = 'Gagal memperbarui list kegiatan issues.';
                    $errors = sqlsrv_errors();
                    if (!empty($errors[0]['message'])) {
                        $status .= ' ' . trim($errors[0]['message']);
                    }
                } else {
                    $issueStatusMap = array();
                    if (!empty($selectedIssueIds)) {
                        $placeholders = implode(',', array_fill(0, count($selectedIssueIds), '?'));
                        $sqlIssueStatus = "SELECT issue_id, status FROM dbo.issues WHERE issue_id IN ($placeholders)";
                        $stmtIssueStatus = sqlsrv_query($conn, $sqlIssueStatus, $selectedIssueIds);
                        if ($stmtIssueStatus) {
                            while ($rowIssueStatus = sqlsrv_fetch_array($stmtIssueStatus, SQLSRV_FETCH_ASSOC)) {
                                $issueStatusMap[(int) ($rowIssueStatus['issue_id'] ?? 0)] = trim((string) ($rowIssueStatus['status'] ?? ''));
                            }
                        }
                    }

                    $insertOk = true;
                    if (!empty($selectedIssueIds)) {
                        $sqlInsertLink = "INSERT INTO dbo.project_issue_links
                                          (projectid, issue_id, issue_status_snapshot, created_by, created_at)
                                          VALUES (?, ?, ?, ?, GETDATE())";
                        foreach ($selectedIssueIds as $issueId) {
                            $snapshot = $issueStatusMap[$issueId] ?? null;
                            $stmtInsertLink = sqlsrv_query($conn, $sqlInsertLink, array(
                                $form['projectid'],
                                $issueId,
                                $snapshot !== '' ? $snapshot : null,
                                $updateBy !== '' ? $updateBy : null
                            ));
                            if (!$stmtInsertLink) {
                                $insertOk = false;
                                break;
                            }
                        }
                    }

                    if (!$insertOk) {
                        sqlsrv_rollback($conn);
                        $statusType = 'danger';
                        $status = 'Gagal menyimpan perubahan list kegiatan issues.';
                        $errors = sqlsrv_errors();
                        if (!empty($errors[0]['message'])) {
                            $status .= ' ' . trim($errors[0]['message']);
                        }
                    } else {
                        sqlsrv_commit($conn);
                        header('Location: project.php?status=updated');
                        exit;
                    }
                }
            }
        } else {
            $statusType = 'danger';
            $status = 'Gagal memulai transaksi update project.';
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
        $selectedIssueItems = array();
        while ($rowSelected = sqlsrv_fetch_array($stmtSelectedIssues, SQLSRV_FETCH_ASSOC)) {
            $selectedIssueItems[] = array(
                'id' => (int) ($rowSelected['issue_id'] ?? 0),
                'text' => buildIssueOptionLabel($rowSelected),
                'group' => isDoneIssueStatus($rowSelected['status'] ?? '') ? 'Selesai' : 'Aktif'
            );
        }
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
?>

<style>
    .edit-project-page {
        --accent-1: <?php echo h($themePalette['start']); ?>;
        --accent-2: <?php echo h($themePalette['end']); ?>;
        --accent-rgb: <?php echo h($themePalette['rgb']); ?>;
        --accent-text: <?php echo h($themePalette['text']); ?>;
    }
    .edit-project-page .card-wrap {
        border: 1px solid #dfe6ee;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 14px 34px rgba(16, 24, 40, .08);
    }
    .edit-project-page .head-wrap {
        background: linear-gradient(120deg, var(--accent-1) 0%, var(--accent-2) 100%);
        color: var(--accent-text);
        padding: 20px 24px;
    }
    .edit-project-page .head-wrap h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }
    .edit-project-page .head-wrap p {
        margin: 6px 0 0;
        opacity: .94;
        font-size: .92rem;
    }
    .edit-project-page .form-shell {
        border: 1px solid #e0e7ef;
        border-radius: 14px;
        padding: 18px 18px 14px;
        margin-bottom: 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .03);
    }
    .edit-project-page .section-label {
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: var(--accent-1);
        margin-bottom: 14px;
        padding-bottom: 8px;
        border-bottom: 1px dashed rgba(var(--accent-rgb), .24);
    }
    .edit-project-page .form-group label {
        margin-bottom: 7px;
        font-size: .9rem;
        font-weight: 600;
        color: #27364a;
    }
    .edit-project-page .input-group-text {
        min-width: 42px;
        height: 42px;
        justify-content: center;
        color: #495a6b;
        background: #f8fafc;
        border-color: #cfd8e3;
    }
    .edit-project-page .form-control,
    .edit-project-page .custom-select,
    .edit-project-page .select2-container--bootstrap4 .select2-selection--single {
        border-radius: 9px;
        border-color: #cfd8e3;
        min-height: 42px;
    }
    .edit-project-page .form-control:focus,
    .edit-project-page .custom-select:focus,
    .edit-project-page .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border-color: var(--accent-2);
        box-shadow: 0 0 0 .18rem rgba(var(--accent-rgb), .14);
    }
    .edit-project-page .select2-container--bootstrap4 .select2-selection--single {
        padding-top: 5px;
    }
    .edit-project-page .select2-container--bootstrap4 .select2-selection__rendered {
        color: #27364a;
        line-height: 1.4;
    }
    .edit-project-page .client-select-wrap {
        position: relative;
    }
    .edit-project-page .assignee-select-wrap {
        position: relative;
    }
    .edit-project-page .assignee-select-wrap .assignee-icon {
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
    .edit-project-page .assignee-select-wrap > select.form-control {
        padding-left: 50px;
        min-height: 42px;
        border-radius: 9px;
    }
    .edit-project-page .assignee-select-wrap .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 42px;
        border-radius: 9px;
        border-color: #cfd8e3;
        padding-left: 40px;
    }
    .edit-project-page .assignee-select-wrap .select2-container--bootstrap4 .select2-selection__choice {
        border-radius: 999px !important;
        font-size: .8rem !important;
        padding: 3px 8px !important;
    }
    .edit-project-page .client-icon {
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
    .edit-project-page .client-select-wrap .select2-container--bootstrap4 .select2-selection--single {
        padding-left: 42px;
        min-height: 42px;
        height: 42px;
    }
    .edit-project-page .client-select-wrap .select2-container--bootstrap4 .select2-selection__arrow {
        height: 40px;
    }
    .edit-project-page .issue-picker-wrap {
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        background: #f8fbff;
        padding: 12px;
    }
    .edit-project-page .issue-filter-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .edit-project-page .issue-filter-row .custom-select {
        max-width: 220px;
    }
    .edit-project-page .issue-picker-wrap .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 44px;
        border-radius: 9px;
        border-color: #cfd8e3;
        padding: 3px 6px;
    }
    .edit-project-page .issue-picker-wrap .select2-selection__choice {
        border-radius: 999px !important;
        font-size: .8rem !important;
        padding: 3px 8px !important;
    }
    .edit-project-page .issue-help {
        margin-top: 7px;
        color: #6f7f90;
        font-size: .82rem;
    }
    .edit-project-page .note-editor.note-frame {
        border-radius: 11px;
        border-color: #cfd8e3;
        overflow: hidden;
    }
    .edit-project-page .note-toolbar {
        background: #f7fafc;
        border-bottom-color: #d8e0e8;
    }
    .edit-project-page .action-area {
        border-top: 1px solid #dfe6ee;
        padding-top: 16px;
    }
    .edit-project-page .action-area .btn {
        min-width: 120px;
        border-radius: 9px;
        font-weight: 600;
    }
    .edit-project-page .project-notif {
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
    .edit-project-page .project-notif::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--accent-1) 0%, var(--accent-2) 100%);
    }
    .edit-project-page .project-notif .n-icon {
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
    .edit-project-page .project-notif .n-text {
        font-weight: 600;
        line-height: 1.45;
        padding-right: 18px;
    }
    .edit-project-page .project-notif .n-close {
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
    .edit-project-page .project-notif.is-danger {
        border-color: rgba(220, 53, 69, .35);
        background: linear-gradient(120deg, rgba(220, 53, 69, .16) 0%, rgba(220, 53, 69, .08) 100%);
        color: #5e1b21;
    }
    .edit-project-page .project-notif.is-danger::before {
        background: linear-gradient(180deg, #dc3545 0%, #b92332 100%);
    }
    .edit-project-page .project-notif.is-danger .n-icon {
        background: rgba(220, 53, 69, .18);
        color: #b92332;
    }
    .edit-project-page .project-notif.is-warning {
        border-color: rgba(229, 157, 0, .35);
        background: linear-gradient(120deg, rgba(229, 157, 0, .16) 0%, rgba(229, 157, 0, .08) 100%);
        color: #644300;
    }
    .edit-project-page .project-notif.is-warning::before {
        background: linear-gradient(180deg, #f3b41c 0%, #e59d00 100%);
    }
    .edit-project-page .project-notif.is-warning .n-icon {
        background: rgba(229, 157, 0, .16);
        color: #ad7400;
    }
    @keyframes notifIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="content-wrapper edit-project-page">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Edit Project</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="project.php">Project</a></li>
                        <li class="breadcrumb-item active">Edit</li>
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

            <?php if ($form['projectid'] > 0) { ?>
                <div class="card card-wrap">
                    <div class="head-wrap">
                        <h3><i class="fas fa-edit mr-1"></i>Edit Project</h3>
                        <p>Project ID: <?php echo h(formatProjectCode($form['projectid'] ?? 0, $form['project_code_date'] ?? ($form['startdate'] ?? ''))); ?>. Perbarui data project dan simpan perubahan terbaru.</p>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="projectid" value="<?php echo (int) $form['projectid']; ?>">

                            <div class="form-shell">
                                <div class="section-label">Project Identity</div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Project Name <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-folder-open"></i></span>
                                                </div>
                                                <input type="text" name="projectname" class="form-control" value="<?php echo h($form['projectname']); ?>" required>
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
                                <div class="section-label">List Kegiatan dari Issues</div>
                                <div class="issue-picker-wrap">
                                    <div class="issue-filter-row">
                                        <label for="issueScope" class="mb-0">Sumber data issues:</label>
                                        <select name="issue_scope" id="issueScope" class="custom-select custom-select-sm">
                                            <option value="all" <?php echo $form['issue_scope'] === 'all' ? 'selected' : ''; ?>>Semua (Aktif + Selesai)</option>
                                            <option value="active" <?php echo $form['issue_scope'] === 'active' ? 'selected' : ''; ?>>Hanya Aktif</option>
                                            <option value="done" <?php echo $form['issue_scope'] === 'done' ? 'selected' : ''; ?>>Hanya Selesai</option>
                                        </select>
                                    </div>
                                    <select name="issue_ids[]" id="issueSelect" class="form-control" multiple="multiple">
                                        <?php foreach ($selectedIssueItems as $issueItem) { ?>
                                            <option value="<?php echo (int) $issueItem['id']; ?>" data-status="<?php echo h($issueItem['group'] === 'Selesai' ? 'Done' : 'In Progress'); ?>" selected>
                                                <?php echo h($issueItem['text']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <div class="issue-help">
                                        Pilih issue untuk ditautkan ke project ini. Anda bisa menambah atau mengurangi list kapan saja.
                                    </div>
                                </div>
                            </div>

                            <div class="form-shell">
                                <div class="section-label">Timeline & Progress</div>
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
                                        <div class="form-group">
                                            <label>Progress (0-100)</label>
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="progressAuto" name="progress_auto" value="1" <?php echo $form['progress_auto'] === '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="progressAuto">Calculate progress through tasks</label>
                                            </div>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-tasks"></i></span>
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

                            <div class="form-shell mb-0">
                                <div class="section-label">Description</div>
                                <div class="form-group mb-0">
                                    <label>Project Description</label>
                                    <textarea id="projectDescription" name="description" rows="8" class="form-control"><?php echo h($form['description']); ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end action-area mt-3">
                                <a href="project.php" class="btn btn-secondary mr-2">
                                    <i class="fas fa-arrow-left mr-1"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-<?php echo h($themeColor); ?>">
                                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
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
        var issueEndpoint = 'edit_project.php';

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
                    url: issueEndpoint,
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
            $('#progressInput').val(normalizeProgress(value));
        }

        function calculateSelectedIssueProgress() {
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
            return total > 0 ? Math.round((done / total) * 100) : 0;
        }

        function syncProgressMode() {
            var isAuto = $('#progressAuto').is(':checked');
            $('#progressInput').prop('readonly', isAuto).prop('disabled', isAuto);
            if (isAuto) {
                renderProgress(calculateSelectedIssueProgress());
            }
        }

        $('#progressAuto').on('change', syncProgressMode);
        $('#progressInput').on('input change blur', function () {
            renderProgress($(this).val());
        });
        $('#issueSelect').on('change select2:select select2:unselect', function () {
            if ($('#progressAuto').is(':checked')) {
                renderProgress(calculateSelectedIssueProgress());
            }
        });

        function uploadEditorImage(file) {
            var fd = new FormData();
            fd.append('action', 'upload_editor_image');
            fd.append('file', file);

            $.ajax({
                url: issueEndpoint,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (resp) {
                    if (!resp || resp.success !== true || !resp.url) {
                        alert((resp && resp.message) ? resp.message : 'Upload gambar gagal.');
                        return;
                    }
                    $('#projectDescription').summernote('insertImage', resp.url, function ($img) {
                        $img.attr('alt', resp.name || 'Project image');
                        $img.addClass('img-fluid');
                    });
                },
                error: function () {
                    alert('Upload gambar gagal. Coba lagi.');
                }
            });
        }

        $('#projectDescription').summernote({
            height: 280,
            minHeight: 220,
            lang: 'id-ID',
            placeholder: 'Tulis deskripsi project',
            callbacks: {
                onImageUpload: function (files) {
                    if (!files || !files.length) {
                        return;
                    }
                    Array.prototype.forEach.call(files, function (file) {
                        uploadEditorImage(file);
                    });
                }
            }
        });

        syncProgressMode();
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
