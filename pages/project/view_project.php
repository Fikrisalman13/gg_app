<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmtDateValue($value, $withTime = false)
{
    if ($value === null || $value === '') {
        return '-';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return (string) $value;
    }
    return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', $ts);
}

function guessFileIcon($ext)
{
    $ext = strtolower(trim((string) $ext));
    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
        return 'fas fa-image text-success';
    }
    if (in_array($ext, array('pdf'), true)) {
        return 'fas fa-file-pdf text-danger';
    }
    if (in_array($ext, array('doc', 'docx'), true)) {
        return 'fas fa-file-word text-primary';
    }
    if (in_array($ext, array('xls', 'xlsx'), true)) {
        return 'fas fa-file-excel text-success';
    }
    if (in_array($ext, array('ppt', 'pptx'), true)) {
        return 'fas fa-file-powerpoint text-warning';
    }
    if (in_array($ext, array('zip', 'rar', '7z'), true)) {
        return 'fas fa-file-archive text-secondary';
    }
    return 'fas fa-paperclip text-info';
}

function splitAssignees($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return array();
    }
    $parts = array_map('trim', explode(',', $raw));
    $out = array();
    foreach ($parts as $p) {
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
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

function ensureProjectCommentTable($conn)
{
    $sql = "
IF OBJECT_ID('dbo.project_comments', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.project_comments (
        comment_id INT IDENTITY(1,1) PRIMARY KEY,
        projectid INT NOT NULL,
        comment_text NVARCHAR(MAX) NOT NULL,
        created_by VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_project_comments_projectid ON dbo.project_comments(projectid);
END";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        return !empty($errors[0]['message']) ? trim((string) $errors[0]['message']) : 'Gagal menyiapkan tabel komentar project.';
    }
    return '';
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themePalette = getThemePalette($themeColor);
$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$activeTab = trim((string) ($_GET['tab'] ?? 'summary'));
if (!in_array($activeTab, array('summary', 'issues', 'comments', 'files'), true)) {
    $activeTab = 'summary';
}
$status = '';
$statusType = 'danger';
$project = null;
$attachments = array();
$linkedIssues = array();
$comments = array();

$sqlEnsureLinkTable = "
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
@sqlsrv_query($conn, $sqlEnsureLinkTable);

$sqlEnsureProgressAuto = "
IF COL_LENGTH('dbo.project', 'progress_auto') IS NULL
BEGIN
    ALTER TABLE dbo.project
    ADD progress_auto BIT NOT NULL CONSTRAINT DF_project_progress_auto DEFAULT(1) WITH VALUES;
END";
@sqlsrv_query($conn, $sqlEnsureProgressAuto);

$commentTableError = ensureProjectCommentTable($conn);
if ($commentTableError !== '') {
    $status = $commentTableError;
}

if ($projectId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $activeTab = 'comments';
    $commentText = trim((string) ($_POST['comment_text'] ?? ''));
    if ($commentText === '') {
        $statusType = 'danger';
        $status = 'Komentar tidak boleh kosong.';
    } elseif ($commentTableError !== '') {
        $statusType = 'danger';
        $status = $commentTableError;
    } else {
        $userName = trim((string) ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'User'));
        $stmtAddComment = sqlsrv_query(
            $conn,
            "INSERT INTO dbo.project_comments (projectid, comment_text, created_by, created_at) VALUES (?, ?, ?, GETDATE())",
            array($projectId, $commentText, $userName !== '' ? $userName : null)
        );
        if ($stmtAddComment) {
            header('Location: view_project.php?id=' . (int) $projectId . '&tab=comments&comment=added');
            exit;
        }
        $statusType = 'danger';
        $status = 'Gagal menyimpan komentar.';
        $errors = sqlsrv_errors();
        if (!empty($errors[0]['message'])) {
            $status .= ' ' . trim((string) $errors[0]['message']);
        }
    }
}

if ($projectId > 0) {
    $sqlProject = "SELECT projectid, projectname, assignee, client, startdate, deadline, progress, ISNULL(progress_auto, 1) AS progress_auto, [desc], creatby, creatat, updateby, updateat
                   FROM dbo.project
                   WHERE projectid = ?";
    $stmtProject = sqlsrv_query($conn, $sqlProject, array($projectId));
    if ($stmtProject) {
        $project = sqlsrv_fetch_array($stmtProject, SQLSRV_FETCH_ASSOC);
    }

    if ($project) {
        $sqlIssueLinks = "SELECT l.issue_id, l.issue_status_snapshot, l.created_at,
                                 i.issue_name, i.issue_type, i.status AS live_status, i.created_by
                          FROM dbo.project_issue_links l
                          LEFT JOIN dbo.issues i ON l.issue_id = i.issue_id
                          WHERE l.projectid = ?
                          ORDER BY l.link_id DESC";
        $stmtIssueLinks = sqlsrv_query($conn, $sqlIssueLinks, array($projectId));
        if ($stmtIssueLinks) {
            while ($rowIssue = sqlsrv_fetch_array($stmtIssueLinks, SQLSRV_FETCH_ASSOC)) {
                $linkedIssues[] = $rowIssue;
            }
        }

        $sqlAttach = "SELECT attachment_id, file_name, file_path, file_ext, file_size, mime_type, is_inline, created_by, created_at
                      FROM dbo.project_attachments
                      WHERE projectid = ?
                      ORDER BY attachment_id DESC";
        $stmtAttach = sqlsrv_query($conn, $sqlAttach, array($projectId));
        if ($stmtAttach) {
            while ($rowAttach = sqlsrv_fetch_array($stmtAttach, SQLSRV_FETCH_ASSOC)) {
                $attachments[] = $rowAttach;
            }
        }

        if ($commentTableError === '') {
            $stmtComments = sqlsrv_query(
                $conn,
                "SELECT comment_id, comment_text, created_by, created_at
                 FROM dbo.project_comments
                 WHERE projectid = ?
                 ORDER BY comment_id DESC",
                array($projectId)
            );
            if ($stmtComments) {
                while ($rowComment = sqlsrv_fetch_array($stmtComments, SQLSRV_FETCH_ASSOC)) {
                    $comments[] = $rowComment;
                }
            }
        }
    }
}

if (!$project) {
    $status = 'Project tidak ditemukan atau ID tidak valid.';
} elseif (isset($_GET['comment']) && $_GET['comment'] === 'added') {
    $statusType = 'success';
    $status = 'Komentar berhasil ditambahkan.';
}
?>

<style>
    .view-project-page {
        --accent-1: <?php echo h($themePalette['start']); ?>;
        --accent-2: <?php echo h($themePalette['end']); ?>;
        --accent-rgb: <?php echo h($themePalette['rgb']); ?>;
        --accent-text: <?php echo h($themePalette['text']); ?>;
    }
    .view-project-page .project-shell {
        border: 1px solid #d9e4ef;
        border-radius: 16px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, .08);
        overflow: hidden;
        background: #fff;
    }
    .view-project-page .project-head {
        background: linear-gradient(120deg, var(--accent-1) 0%, var(--accent-2) 100%);
        color: var(--accent-text);
        padding: 18px 20px;
        display: flex;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }
    .view-project-page .project-head h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
    }
    .view-project-page .project-head .sub {
        margin-top: 4px;
        opacity: .93;
        font-size: .92rem;
    }
    .view-project-page .mini-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255, 255, 255, .38);
        padding: 4px 10px;
        border-radius: 999px;
        font-weight: 600;
        font-size: .78rem;
        margin-top: 8px;
        margin-right: 6px;
    }
    .view-project-page .head-actions .btn {
        border-radius: 9px;
        padding: 8px 12px;
        font-weight: 600;
    }
    .view-project-page .head-actions .btn-light {
        border: none;
    }
    .view-project-page .body-wrap {
        padding: 16px;
        background: #f7f9fc;
    }
    .view-project-page .nav-tabs {
        border-bottom: 1px solid #d7e2ed;
    }
    .view-project-page .nav-tabs .nav-link {
        border: none;
        color: #445669;
        font-weight: 700;
        letter-spacing: .2px;
        border-bottom: 3px solid transparent;
        padding: 10px 14px;
    }
    .view-project-page .nav-tabs .nav-link.active {
        color: var(--accent-2);
        background: transparent;
        border-bottom-color: var(--accent-2);
    }
    .view-project-page .tab-pane {
        padding-top: 14px;
    }
    .view-project-page .summary-grid {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr);
        gap: 14px;
    }
    .view-project-page .soft-card {
        border: 1px solid #dde7f2;
        border-radius: 12px;
        background: #fff;
        padding: 14px;
    }
    .view-project-page .progress-circle-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 260px;
    }
    .view-project-page .progress-circle {
        --p: 0;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        background: conic-gradient(var(--accent-2) calc(var(--p) * 1%), #e6edf5 0);
        display: grid;
        place-items: center;
        position: relative;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .04);
    }
    .view-project-page .progress-circle::before {
        content: "";
        width: 170px;
        height: 170px;
        border-radius: 50%;
        background: #fff;
        box-shadow: inset 0 0 0 1px #d8e4ef;
    }
    .view-project-page .progress-circle .num {
        position: absolute;
        font-size: 2.4rem;
        font-weight: 800;
        color: var(--accent-1);
        letter-spacing: .3px;
    }
    .view-project-page .progress-circle .label {
        position: absolute;
        bottom: 51px;
        font-size: .74rem;
        color: #6e8193;
        text-transform: uppercase;
        letter-spacing: .8px;
        font-weight: 700;
    }
    .view-project-page .kv-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e2ebf4;
        border-radius: 10px;
        overflow: hidden;
    }
    .view-project-page .kv-table th,
    .view-project-page .kv-table td {
        padding: 10px 11px;
        border-bottom: 1px solid #edf2f7;
    }
    .view-project-page .kv-table th {
        width: 160px;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .7px;
        color: #61758a;
        background: #fbfdff;
    }
    .view-project-page .kv-table td {
        color: #1e2d3d;
        font-weight: 600;
    }
    .view-project-page .kv-table tr:last-child th,
    .view-project-page .kv-table tr:last-child td {
        border-bottom: none;
    }
    .view-project-page .assignee-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
    }
    .view-project-page .assignee-chip {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(var(--accent-rgb), .34);
        color: var(--accent-1);
        background: rgba(var(--accent-rgb), .10);
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .83rem;
        font-weight: 600;
    }
    .view-project-page .section-title {
        margin: 0 0 8px;
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #637587;
        font-weight: 800;
    }
    .view-project-page .desc-box {
        border: 1px solid #e1eaf3;
        border-radius: 10px;
        padding: 12px;
        background: #fff;
        min-height: 130px;
    }
    .view-project-page .desc-box img {
        max-width: 100%;
        height: auto;
    }
    .view-project-page .issue-list .list-group-item,
    .view-project-page .attach-list .list-group-item {
        border-color: #e1ebf5;
        border-radius: 10px;
        margin-bottom: 8px;
    }
    .view-project-page .issue-list .list-group-item:last-child,
    .view-project-page .attach-list .list-group-item:last-child {
        margin-bottom: 0;
    }
    .view-project-page .issue-meta,
    .view-project-page .file-meta {
        color: #6f8295;
        font-size: .83rem;
        margin-top: 2px;
    }
    .view-project-page .status-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .2px;
        margin-left: 7px;
    }
    .view-project-page .status-done {
        color: #115f38;
        background: #d8f4e6;
    }
    .view-project-page .status-active {
        color: #8a5a00;
        background: #fff0c7;
    }
    .view-project-page .empty-note {
        border: 1px dashed #cfdbe8;
        border-radius: 10px;
        background: #fbfdff;
        padding: 18px;
        color: #708397;
    }
    .view-project-page .project-notif {
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
    .view-project-page .project-notif::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--accent-1) 0%, var(--accent-2) 100%);
    }
    .view-project-page .project-notif .n-icon {
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
    .view-project-page .project-notif .n-text {
        font-weight: 600;
        line-height: 1.45;
        padding-right: 18px;
    }
    .view-project-page .project-notif .n-close {
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
    .view-project-page .project-notif.is-danger {
        border-color: rgba(220, 53, 69, .35);
        background: linear-gradient(120deg, rgba(220, 53, 69, .16) 0%, rgba(220, 53, 69, .08) 100%);
        color: #5e1b21;
    }
    .view-project-page .project-notif.is-danger::before {
        background: linear-gradient(180deg, #dc3545 0%, #b92332 100%);
    }
    .view-project-page .project-notif.is-danger .n-icon {
        background: rgba(220, 53, 69, .18);
        color: #b92332;
    }
    .view-project-page .project-notif.is-warning {
        border-color: rgba(229, 157, 0, .35);
        background: linear-gradient(120deg, rgba(229, 157, 0, .16) 0%, rgba(229, 157, 0, .08) 100%);
        color: #644300;
    }
    .view-project-page .project-notif.is-warning::before {
        background: linear-gradient(180deg, #f3b41c 0%, #e59d00 100%);
    }
    .view-project-page .project-notif.is-warning .n-icon {
        background: rgba(229, 157, 0, .16);
        color: #ad7400;
    }
    @keyframes notifIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .view-project-page .comment-form-wrap {
        border: 1px solid #dce7f2;
        border-radius: 12px;
        background: #fbfdff;
        padding: 12px;
    }
    .view-project-page .comment-form-wrap textarea.form-control {
        min-height: 95px;
        resize: vertical;
        border-radius: 10px;
        border-color: #cfdeec;
    }
    .view-project-page .comment-form-wrap textarea.form-control:focus {
        border-color: var(--accent-2);
        box-shadow: 0 0 0 .18rem rgba(var(--accent-rgb), .14);
    }
    .view-project-page .comment-form-wrap .btn {
        border-radius: 9px;
        font-weight: 600;
    }
    .view-project-page .comment-item {
        border: 1px solid #e1ebf5;
        border-radius: 12px;
        background: #fff;
        padding: 12px;
        margin-bottom: 10px;
    }
    .view-project-page .comment-item:last-child {
        margin-bottom: 0;
    }
    .view-project-page .comment-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .view-project-page .comment-author {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-weight: 700;
        color: #274158;
    }
    .view-project-page .comment-time {
        font-size: .8rem;
        color: #6d8194;
        white-space: nowrap;
    }
    .view-project-page .comment-body {
        color: #24364a;
        line-height: 1.5;
        font-size: .94rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
    @media (max-width: 991.98px) {
        .view-project-page .summary-grid {
            grid-template-columns: 1fr;
        }
        .view-project-page .progress-circle-wrap {
            min-height: 0;
            padding: 8px 0;
        }
    }
    @media (max-width: 575.98px) {
        .view-project-page .project-head {
            padding: 14px;
        }
        .view-project-page .body-wrap {
            padding: 12px;
        }
        .view-project-page .head-actions .btn {
            width: 100%;
            margin-top: 6px;
        }
    }
</style>

<div class="content-wrapper view-project-page">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>View Project</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="project.php">Project</a></li>
                        <li class="breadcrumb-item active">View</li>
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

            <?php if ($project) { ?>
                <?php
                $p = (int) ($project['progress'] ?? 0);
                $linkedTotal = count($linkedIssues);
                $linkedDone = 0;
                $assigneeMap = array();
                foreach ($linkedIssues as $issueForCalc) {
                    $liveStatus = trim((string) ($issueForCalc['live_status'] ?? ''));
                    $snapStatus = trim((string) ($issueForCalc['issue_status_snapshot'] ?? ''));
                    $statusForCalc = $liveStatus !== '' ? $liveStatus : $snapStatus;
                    if (in_array(strtoupper(trim($statusForCalc)), array('DONE', 'CLOSED'), true)) {
                        $linkedDone++;
                    }
                    $creatorName = trim((string) ($issueForCalc['created_by'] ?? ''));
                    if ($creatorName !== '') {
                        $assigneeMap[$creatorName] = $creatorName;
                    }
                }
                if ((int) ($project['progress_auto'] ?? 1) === 1) {
                    $p = $linkedTotal > 0 ? (int) round(($linkedDone / $linkedTotal) * 100) : 0;
                }
                if ($p < 0) {
                    $p = 0;
                }
                if ($p > 100) {
                    $p = 100;
                }
                ?>
                <?php
                $assigneeList = array_values($assigneeMap);
                if (empty($assigneeList)) {
                    $assigneeList = splitAssignees($project['assignee'] ?? '');
                }
                $statusProject = $p >= 100 ? 'Done' : 'In Progress';
                ?>
                <div class="project-shell">
                    <div class="project-head">
                        <div>
                            <h3><i class="fas fa-folder-open mr-1"></i><?php echo h($project['projectname'] ?? '-'); ?></h3>
                            <div class="sub">Project ID: <?php echo h(formatProjectCode($project['projectid'] ?? 0, $project['creatat'] ?? ($project['startdate'] ?? null))); ?></div>
                            <span class="mini-chip"><i class="far fa-calendar-alt"></i> Start: <?php echo h(fmtDateValue($project['startdate'] ?? null)); ?></span>
                            <span class="mini-chip"><i class="far fa-flag"></i> Deadline: <?php echo h(fmtDateValue($project['deadline'] ?? null)); ?></span>
                        </div>
                        <div class="head-actions text-right">
                            <a href="project_report_preview.php?id=<?php echo (int) ($project['projectid'] ?? 0); ?>" class="btn btn-light" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf mr-1"></i> Report Project
                            </a>
                            <a href="edit_project.php?id=<?php echo (int) ($project['projectid'] ?? 0); ?>" class="btn btn-light">
                                <i class="fas fa-edit mr-1"></i> Edit Project
                            </a>
                            <a href="project.php" class="btn btn-outline-light ml-1">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                            </a>
                        </div>
                    </div>
                    <div class="body-wrap">
                        <ul class="nav nav-tabs" id="projectViewTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'summary' ? 'active' : ''; ?>" id="summary-tab" data-toggle="tab" href="#summaryPane" role="tab" aria-controls="summaryPane" aria-selected="<?php echo $activeTab === 'summary' ? 'true' : 'false'; ?>">Project Summary</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'issues' ? 'active' : ''; ?>" id="issues-tab" data-toggle="tab" href="#issuesPane" role="tab" aria-controls="issuesPane" aria-selected="<?php echo $activeTab === 'issues' ? 'true' : 'false'; ?>">Issues</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'comments' ? 'active' : ''; ?>" id="comments-tab" data-toggle="tab" href="#commentsPane" role="tab" aria-controls="commentsPane" aria-selected="<?php echo $activeTab === 'comments' ? 'true' : 'false'; ?>">Comments</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'files' ? 'active' : ''; ?>" id="files-tab" data-toggle="tab" href="#filesPane" role="tab" aria-controls="filesPane" aria-selected="<?php echo $activeTab === 'files' ? 'true' : 'false'; ?>">Files</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade <?php echo $activeTab === 'summary' ? 'show active' : ''; ?>" id="summaryPane" role="tabpanel" aria-labelledby="summary-tab">
                                <div class="summary-grid">
                                    <div class="soft-card">
                                        <div class="section-title">Progress Project</div>
                                        <div class="progress-circle-wrap">
                                            <div class="progress-circle" style="--p: <?php echo (int) $p; ?>;">
                                                <div class="num"><?php echo (int) $p; ?></div>
                                                <div class="label">Percent</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="soft-card">
                                        <table class="kv-table">
                                            <tr>
                                                <th>Client</th>
                                                <td><?php echo h($project['client'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td><?php echo h($statusProject); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Create By</th>
                                                <td><?php echo h($project['creatby'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Create At</th>
                                                <td><?php echo h(fmtDateValue($project['creatat'] ?? null, true)); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Update At</th>
                                                <td><?php echo h(fmtDateValue($project['updateat'] ?? null, true)); ?></td>
                                            </tr>
                                        </table>

                                        <div class="section-title mt-3">Assigned Staff</div>
                                        <?php if (empty($assigneeList)) { ?>
                                            <div class="empty-note">Belum ada assignee.</div>
                                        <?php } else { ?>
                                            <div class="assignee-chips">
                                                <?php foreach ($assigneeList as $assigneeName) { ?>
                                                    <span class="assignee-chip"><i class="fas fa-user mr-1"></i><?php echo h($assigneeName); ?></span>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="soft-card mt-3">
                                    <div class="section-title">Project Description</div>
                                    <div class="desc-box">
                                        <?php
                                        $htmlDesc = trim((string) ($project['desc'] ?? ''));
                                        if ($htmlDesc === '') {
                                            echo '<span class="text-muted">Tidak ada deskripsi.</span>';
                                        } else {
                                            echo $htmlDesc;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade <?php echo $activeTab === 'issues' ? 'show active' : ''; ?>" id="issuesPane" role="tabpanel" aria-labelledby="issues-tab">
                                <div class="soft-card">
                                    <div class="section-title">Kegiatan dari Issues</div>
                                    <?php if (empty($linkedIssues)) { ?>
                                        <div class="empty-note">Belum ada issue yang ditautkan ke project ini.</div>
                                    <?php } else { ?>
                                        <div class="issue-list list-group">
                                            <?php foreach ($linkedIssues as $issue) { ?>
                                                <?php
                                                $issueId = (int) ($issue['issue_id'] ?? 0);
                                                $issueName = trim((string) ($issue['issue_name'] ?? ''));
                                                $issueType = trim((string) ($issue['issue_type'] ?? ''));
                                                $statusLive = trim((string) ($issue['live_status'] ?? ''));
                                                $statusSnap = trim((string) ($issue['issue_status_snapshot'] ?? ''));
                                                $statusLabel = $statusLive !== '' ? $statusLive : ($statusSnap !== '' ? $statusSnap : '-');
                                                $creator = trim((string) ($issue['created_by'] ?? ''));
                                                $doneState = strtoupper($statusLabel) === 'DONE' || strtoupper($statusLabel) === 'CLOSED';
                                                ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong>#<?php echo h($issueId); ?></strong>
                                                            <?php if ($issueName !== '') { ?>
                                                                - <?php echo h($issueName); ?>
                                                            <?php } ?>
                                                            <span class="status-badge <?php echo $doneState ? 'status-done' : 'status-active'; ?>">
                                                                <?php echo h($statusLabel); ?>
                                                            </span>
                                                            <div class="issue-meta">
                                                                <?php echo h($issueType !== '' ? $issueType : '-'); ?> | PIC: <?php echo h($creator !== '' ? $creator : '-'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                    <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="tab-pane fade <?php echo $activeTab === 'comments' ? 'show active' : ''; ?>" id="commentsPane" role="tabpanel" aria-labelledby="comments-tab">
                                <div class="soft-card">
                                    <div class="section-title">Komentar Project</div>
                                    <div class="comment-form-wrap mb-3">
                                        <form method="post" action="view_project.php?id=<?php echo (int) ($project['projectid'] ?? 0); ?>&tab=comments">
                                            <input type="hidden" name="action" value="add_comment">
                                            <div class="form-group mb-2">
                                                <textarea name="comment_text" class="form-control" placeholder="Tulis komentar atau update progress terbaru..." maxlength="3000" required></textarea>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-<?php echo h($themeColor); ?>">
                                                    <i class="fas fa-paper-plane mr-1"></i> Kirim Komentar
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (empty($comments)) { ?>
                                        <div class="empty-note">Belum ada komentar. Mulai diskusi project dari sini.</div>
                                    <?php } else { ?>
                                        <div class="comment-list">
                                            <?php foreach ($comments as $cm) { ?>
                                                <?php
                                                $author = trim((string) ($cm['created_by'] ?? 'User'));
                                                $commentText = trim((string) ($cm['comment_text'] ?? ''));
                                                ?>
                                                <div class="comment-item">
                                                    <div class="comment-head">
                                                        <div class="comment-author">
                                                            <i class="fas fa-user-circle"></i>
                                                            <?php echo h($author !== '' ? $author : 'User'); ?>
                                                        </div>
                                                        <div class="comment-time">
                                                            <i class="far fa-clock mr-1"></i><?php echo h(fmtDateValue($cm['created_at'] ?? null, true)); ?>
                                                        </div>
                                                    </div>
                                                    <div class="comment-body"><?php echo nl2br(h($commentText)); ?></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="tab-pane fade <?php echo $activeTab === 'files' ? 'show active' : ''; ?>" id="filesPane" role="tabpanel" aria-labelledby="files-tab">
                                <div class="soft-card">
                                    <div class="section-title">Lampiran</div>
                                    <?php if (empty($attachments)) { ?>
                                        <div class="empty-note">Belum ada lampiran.</div>
                                    <?php } else { ?>
                                        <div class="attach-list list-group">
                                            <?php foreach ($attachments as $att) { ?>
                                                <?php
                                                $fileName = trim((string) ($att['file_name'] ?? 'Lampiran'));
                                                $filePath = trim((string) ($att['file_path'] ?? '#'));
                                                $fileExt = trim((string) ($att['file_ext'] ?? ''));
                                                $fileSize = (int) ($att['file_size'] ?? 0);
                                                $fileSizeKb = $fileSize > 0 ? number_format($fileSize / 1024, 1) . ' KB' : '-';
                                                $icon = guessFileIcon($fileExt);
                                                ?>
                                                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                                   href="<?php echo h($filePath); ?>" target="_blank" rel="noopener">
                                                    <div>
                                                        <i class="<?php echo h($icon); ?> mr-2"></i>
                                                        <strong><?php echo h($fileName); ?></strong>
                                                        <div class="file-meta">
                                                            <?php echo h(strtoupper($fileExt !== '' ? $fileExt : '-')); ?> | <?php echo h($fileSizeKb); ?>
                                                        </div>
                                                    </div>
                                                    <span class="badge badge-light"><i class="fas fa-external-link-alt"></i></span>
                                                </a>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <a href="project.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
            <?php } ?>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
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
