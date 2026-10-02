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

function splitProjectPeople($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return array();
    }

    $parts = array_map('trim', explode(',', $raw));
    $people = array();
    foreach ($parts as $part) {
        if ($part !== '') {
            $people[$part] = $part;
        }
    }
    return array_values($people);
}

function dateOnlyValue($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime((string) $value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function getProjectTimelineStatus($project, $progress)
{
    $progress = (int) $progress;
    $projectStatusRaw = strtoupper(trim((string) ($project['status'] ?? $project['project_status'] ?? '')));
    if (in_array($projectStatusRaw, array('CANCELLED', 'CANCELED', 'BATAL'), true)) {
        return array('key' => 'cancelled', 'label' => 'Cancelled');
    }

    $deadline = dateOnlyValue($project['deadline'] ?? null);
    $completedAt = dateOnlyValue($project['completed_at'] ?? null);
    if ($completedAt === '' && $progress >= 100) {
        $completedAt = dateOnlyValue($project['updateat'] ?? null);
    }
    $today = date('Y-m-d');

    if ($progress >= 100) {
        if ($deadline !== '' && $completedAt !== '') {
            if ($completedAt < $deadline) {
                return array('key' => 'completed-early', 'label' => 'Completed Early');
            }
            if ($completedAt === $deadline) {
                return array('key' => 'completed-on-time', 'label' => 'Completed On Time');
            }
            return array('key' => 'completed-late', 'label' => 'Completed Late');
        }
        return array('key' => 'completed-on-time', 'label' => 'Completed On Time');
    }

    if ($deadline !== '' && $today > $deadline) {
        return array('key' => 'overdue', 'label' => 'Overdue');
    }

    return array('key' => 'on-track', 'label' => 'On Track');
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

$status = '';
$statusType = 'success';
$themeColor = $_SESSION['Theme'] ?? 'primary';
$themePalette = getThemePalette($themeColor);

$sqlEnsureProjectIssueLinks = "
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
@sqlsrv_query($conn, $sqlEnsureProjectIssueLinks);

$sqlEnsureProgressAuto = "
IF COL_LENGTH('dbo.project', 'progress_auto') IS NULL
BEGIN
    ALTER TABLE dbo.project
    ADD progress_auto BIT NOT NULL CONSTRAINT DF_project_progress_auto DEFAULT(1) WITH VALUES;
END";
@sqlsrv_query($conn, $sqlEnsureProgressAuto);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project') {
    $projectId = isset($_POST['projectid']) ? (int) $_POST['projectid'] : 0;
    if ($projectId > 0) {
        $stmtDelete = sqlsrv_query($conn, "DELETE FROM dbo.project WHERE projectid = ?", array($projectId));
        if ($stmtDelete) {
            header('Location: project.php?status=deleted');
            exit;
        }
        $statusType = 'danger';
        $status = 'Gagal menghapus project.';
    } else {
        $statusType = 'danger';
        $status = 'Project ID tidak valid.';
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'added') {
    $statusType = 'success';
    $status = 'Project baru berhasil ditambahkan.';
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $statusType = 'success';
    $status = 'Project berhasil dihapus.';
}

if (isset($_GET['status']) && $_GET['status'] === 'updated') {
    $statusType = 'success';
    $status = 'Project berhasil diperbarui.';
}

$projects = array();
$sqlList = "SELECT p.projectid,
                   p.projectname,
                   COALESCE(NULLIF(pic.assignees, ''), p.assignee) AS assignee,
                   p.client,
                   p.startdate,
                   p.deadline,
                   CAST(CASE
                       WHEN ISNULL(p.progress_auto, 1) = 1 THEN
                           CASE WHEN ISNULL(issue_calc.total_count, 0) > 0
                                THEN ROUND((CAST(ISNULL(issue_calc.done_count, 0) AS FLOAT) / issue_calc.total_count) * 100, 0)
                                ELSE 0
                           END
                       ELSE ISNULL(p.progress, 0)
                   END AS INT) AS progress,
                   ISNULL(p.progress_auto, 1) AS progress_auto,
                   issue_calc.completed_at,
                   p.creatat,
                   p.updateat
            FROM dbo.project p
            OUTER APPLY (
                SELECT COUNT(*) AS total_count,
                       SUM(CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(i.status, '')))) IN ('DONE', 'CLOSED') THEN 1 ELSE 0 END) AS done_count,
                       MAX(CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(i.status, '')))) IN ('DONE', 'CLOSED') THEN COALESCE(i.tanggal_selesai, i.update_at, i.created_at) ELSE NULL END) AS completed_at
                FROM dbo.project_issue_links l
                LEFT JOIN dbo.issues i ON l.issue_id = i.issue_id
                WHERE l.projectid = p.projectid
            ) issue_calc
            OUTER APPLY (
                SELECT STUFF((
                    SELECT DISTINCT ', ' + LTRIM(RTRIM(ISNULL(i2.created_by, '')))
                    FROM dbo.project_issue_links l2
                    LEFT JOIN dbo.issues i2 ON l2.issue_id = i2.issue_id
                    WHERE l2.projectid = p.projectid
                      AND LTRIM(RTRIM(ISNULL(i2.created_by, ''))) <> ''
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS assignees
            ) pic
            ORDER BY p.projectid DESC";
$stmtList = sqlsrv_query($conn, $sqlList);
if ($stmtList) {
    while ($row = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $projects[] = $row;
    }
} else {
    $statusType = 'danger';
    $status = 'Gagal mengambil data project.';
}
?>

<style>
    .project-page {
        --accent-1: <?php echo h($themePalette['start']); ?>;
        --accent-2: <?php echo h($themePalette['end']); ?>;
        --accent-rgb: <?php echo h($themePalette['rgb']); ?>;
        --accent-text: <?php echo h($themePalette['text']); ?>;
    }
    .project-page .project-progress {
        min-width: 48px;
        display: inline-block;
        border-radius: 999px;
        border: 1px solid #28a745;
        color: #fff;
        background-color: #28a745;
        font-size: 12px;
        font-weight: 700;
        text-align: center;
        line-height: 22px;
        padding: 0 8px;
    }
    .project-page .project-progress.zero {
        border-color: #adb5bd;
        background-color: #adb5bd;
        color: #fff;
    }
    .project-page .cell-text {
        display: inline-block;
        border: 1px solid #17a2b8;
        color: #17a2b8;
        background: #fff;
        border-radius: 3px;
        font-size: 12px;
        padding: 2px 8px;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .project-page .action-btns .btn {
        padding: 2px 8px;
        margin-right: 3px;
    }
    .project-page .action-btns .btn:last-child {
        margin-right: 0;
    }
    .project-page table.dataTable thead th {
        white-space: nowrap;
    }
    .project-page .table td,
    .project-page .table th {
        vertical-align: middle;
    }
    .project-page .swal-delete-popup {
        border-radius: 14px !important;
    }
    .project-page .swal-delete-title {
        font-weight: 700 !important;
        color: #1f2937 !important;
    }
    .project-page .swal-delete-html {
        color: #4b5563 !important;
        font-size: .95rem !important;
        line-height: 1.45 !important;
    }
    .project-page .swal-delete-confirm {
        border-radius: 8px !important;
        font-weight: 600 !important;
    }
    .project-page .swal-delete-cancel {
        border-radius: 8px !important;
        font-weight: 600 !important;
    }
    .project-page .project-notif {
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
    .project-page .project-notif::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--accent-1) 0%, var(--accent-2) 100%);
    }
    .project-page .project-notif .n-icon {
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
    .project-page .project-notif .n-text {
        font-weight: 600;
        line-height: 1.45;
        padding-right: 18px;
    }
    .project-page .project-notif .n-close {
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
    .project-page .project-notif.is-danger {
        border-color: rgba(220, 53, 69, .35);
        background: linear-gradient(120deg, rgba(220, 53, 69, .16) 0%, rgba(220, 53, 69, .08) 100%);
        color: #5e1b21;
    }
    .project-page .project-notif.is-danger::before {
        background: linear-gradient(180deg, #dc3545 0%, #b92332 100%);
    }
    .project-page .project-notif.is-danger .n-icon {
        background: rgba(220, 53, 69, .18);
        color: #b92332;
    }
    .project-page .project-notif.is-warning {
        border-color: rgba(229, 157, 0, .35);
        background: linear-gradient(120deg, rgba(229, 157, 0, .16) 0%, rgba(229, 157, 0, .08) 100%);
        color: #644300;
    }
    .project-page .project-notif.is-warning::before {
        background: linear-gradient(180deg, #f3b41c 0%, #e59d00 100%);
    }
    .project-page .project-notif.is-warning .n-icon {
        background: rgba(229, 157, 0, .16);
        color: #ad7400;
    }
    @keyframes notifIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .project-page .project-list-card {
        border: none;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 14px 38px rgba(15, 23, 42, .08);
    }
    .project-page .project-list-head {
        background: linear-gradient(100deg, var(--accent-1) 0%, var(--accent-2) 100%);
        color: <?php echo h($themePalette['text']); ?>;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .project-page .project-list-head h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
    }
    .project-page .project-list-head .btn {
        border-radius: 6px;
        font-weight: 700;
        color: var(--accent-2);
        padding: 7px 13px;
    }
    .project-page .project-list-body {
        background: #f8fafc;
        padding: 16px 18px 18px;
    }
    .project-page .project-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
        color: #334155;
        font-size: 13px;
    }
    .project-page .project-length-control,
    .project-page .project-search-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .project-page .project-length-control select {
        width: 64px;
        height: 34px;
        border-radius: 6px;
        border: 1px solid #d8e0ee;
        font-size: 13px;
    }
    .project-page .project-search-box {
        position: relative;
        width: 260px;
    }
    .project-page .project-search-box input {
        height: 36px;
        border: 1px solid #d8e0ee;
        border-radius: 7px;
        padding: 8px 34px 8px 13px;
        font-size: 13px;
        width: 100%;
    }
    .project-page .project-search-box i {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .project-page .project-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .project-page .project-row-card {
        position: relative;
        display: grid;
        grid-template-columns: 74px minmax(0, 1fr) auto;
        gap: 20px;
        align-items: start;
        background: #fff;
        border: 1px solid #eef2f8;
        border-radius: 8px;
        padding: 24px 24px 22px 24px;
        box-shadow: 0 16px 34px rgba(15, 23, 42, .10);
        overflow: hidden;
    }
    .project-page .project-row-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        background: linear-gradient(180deg, var(--accent-1) 0%, var(--accent-2) 100%);
    }
    .project-page .project-side {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        gap: 16px;
        padding-top: 6px;
    }
    .project-page .project-icon-box {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--accent-rgb), .12);
        color: var(--accent-2);
        font-size: 22px;
    }
    .project-page .meta-icon {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--accent-rgb), .12);
        color: var(--accent-2);
        font-size: 15px;
    }
    .project-page .project-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #f59e0b;
    }
    .project-page .project-status-dot.on-track,
    .project-page .project-status-dot.completed-early,
    .project-page .project-status-dot.completed-on-time {
        background: #22c55e;
    }
    .project-page .project-status-dot.completed-late {
        background: #f97316;
    }
    .project-page .project-status-dot.overdue {
        background: #ef4444;
    }
    .project-page .project-status-dot.cancelled {
        background: #52525b;
    }
    .project-page .project-main {
        min-width: 0;
        display: grid;
        gap: 12px;
    }
    .project-page .project-title-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 0;
    }
    .project-page .project-code-chip {
        background: rgba(var(--accent-rgb), .12);
        color: var(--accent-2);
        border-radius: 7px;
        padding: 7px 12px;
        font-size: 13px;
        font-weight: 800;
        line-height: 1;
    }
    .project-page .project-name {
        font-size: 20px;
        font-weight: 800;
        color: #1f2937;
        line-height: 1.25;
    }
    .project-page .timeline-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #334155;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 11px;
        line-height: 1;
    }
    .project-page .timeline-status-chip::before {
        content: "";
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #22c55e;
        flex: 0 0 auto;
    }
    .project-page .timeline-status-chip.on-track::before,
    .project-page .timeline-status-chip.completed-early::before,
    .project-page .timeline-status-chip.completed-on-time::before {
        background: #22c55e;
    }
    .project-page .timeline-status-chip.completed-late::before {
        background: #f97316;
    }
    .project-page .timeline-status-chip.overdue::before {
        background: #ef4444;
    }
    .project-page .timeline-status-chip.cancelled::before {
        background: #52525b;
    }
    .project-page .assignee-line {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        margin-bottom: 0;
    }
    .project-page .assignee-label {
        color: #334155;
        font-size: 12px;
        font-weight: 800;
        min-width: 82px;
        padding-top: 6px;
    }
    .project-page .assignee-chip-list {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px;
    }
    .project-page .person-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: rgba(var(--accent-rgb), .11);
        color: var(--accent-2);
        padding: 7px 13px;
        font-size: 12px;
        font-weight: 700;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .project-page .project-meta-grid {
        display: grid;
        grid-template-columns: minmax(132px, 1fr) minmax(132px, 1fr) minmax(240px, 1.45fr) minmax(150px, 1fr);
        gap: 0;
        align-items: center;
        border-top: 1px solid #e5eaf2;
        margin-top: 10px;
        padding-top: 20px;
    }
    .project-page .meta-item {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        min-width: 0;
        padding: 0 22px;
        border-left: 1px solid #e5eaf2;
    }
    .project-page .meta-item:first-child {
        padding-left: 0;
        border-left: none;
    }
    .project-page .meta-label {
        font-size: 12px;
        font-weight: 800;
        color: #64748b;
        margin-bottom: 2px;
    }
    .project-page .meta-value {
        font-size: 13px;
        font-weight: 800;
        color: #1f2937;
        min-width: 0;
    }
    .project-page .client-chip {
        display: inline-block;
        max-width: 100%;
        background: rgba(var(--accent-rgb), .11);
        color: var(--accent-2);
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .project-page .progress-wrap {
        display: grid;
        grid-template-columns: minmax(90px, 1fr) auto;
        gap: 10px;
        align-items: center;
    }
    .project-page .progress-track {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .project-page .progress-fill {
        height: 100%;
        border-radius: inherit;
        background: #22c55e;
    }
    .project-page .progress-pill {
        min-width: 38px;
        border-radius: 999px;
        background: #22c55e;
        color: #fff;
        text-align: center;
        font-size: 11px;
        font-weight: 800;
        line-height: 20px;
        padding: 0 8px;
    }
    .project-page .progress-pill.zero {
        background: #94a3b8;
    }
    .project-page .project-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        white-space: nowrap;
        padding-left: 12px;
        padding-top: 3px;
    }
    .project-page .project-action-btn {
        height: 38px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        padding: 9px 14px;
        background: #fff;
        border-color: #dbe3ef;
    }
    .project-page .project-action-btn i {
        margin-right: 5px;
    }
    .project-page .project-action-btn.report {
        color: #dc2626;
    }
    .project-page .project-action-btn.delete {
        color: #ef4444;
    }
    .project-page .project-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 14px;
        font-size: 12px;
        color: #475569;
    }
    .project-page .project-pagination {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .project-page .project-pagination button {
        border: 1px solid #d8e0ee;
        background: #fff;
        border-radius: 5px;
        min-width: 36px;
        height: 34px;
        padding: 0 11px;
        color: #475569;
        font-size: 12px;
    }
    .project-page .project-pagination button.active {
        background: var(--accent-2);
        border-color: var(--accent-2);
        color: var(--accent-text, #fff);
        font-weight: 800;
    }
    .project-page .project-pagination button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }
    .project-page .project-empty {
        display: none;
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        color: #64748b;
        padding: 24px;
        text-align: center;
    }
    @media (max-width: 991.98px) {
        .project-page .project-row-card {
            grid-template-columns: 56px minmax(0, 1fr);
            padding: 15px;
        }
        .project-page .project-meta-grid {
            grid-template-columns: 1fr 1fr;
            gap: 12px 0;
        }
        .project-page .meta-item:nth-child(odd) {
            padding-left: 0;
            border-left: none;
        }
        .project-page .project-actions {
            justify-content: flex-start;
            grid-column: 1 / -1;
            flex-wrap: wrap;
            padding-left: 0;
        }
    }
    @media (max-width: 575.98px) {
        .project-page .project-list-head,
        .project-page .project-toolbar,
        .project-page .project-footer {
            flex-direction: column;
            align-items: stretch;
        }
        .project-page .project-search-box {
            width: 100%;
        }
        .project-page .project-row-card {
            grid-template-columns: 1fr;
        }
        .project-page .project-side {
            flex-direction: row;
            justify-content: flex-start;
        }
        .project-page .project-meta-grid {
            grid-template-columns: 1fr;
        }
        .project-page .meta-item,
        .project-page .meta-item:nth-child(odd) {
            padding: 0;
            border-left: none;
        }
    }
</style>

<div class="content-wrapper project-page">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Project</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Project</li>
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

            <div class="project-list-card">
                <div class="project-list-head">
                    <h3><i class="fas fa-list mr-2"></i>List Project</h3>
                    <a href="creat_project.php" class="btn btn-light btn-sm">
                        <i class="fas fa-plus mr-1"></i> New Project
                    </a>
                </div>
                <div class="project-list-body">
                    <div class="project-toolbar">
                        <div class="project-length-control">
                            <span>Tampilkan</span>
                            <select id="projectPageLength" class="form-control form-control-sm">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <span>data per halaman</span>
                        </div>
                        <div class="project-search-control">
                            <label for="projectSearch" class="mb-0">Cari:</label>
                            <div class="project-search-box">
                                <input type="search" id="projectSearch" placeholder="Cari project...">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <div class="project-list" id="projectList">
                        <?php foreach ($projects as $item) { ?>
                            <?php
                            $p = (int) $item['progress'];
                            if ($p < 0) {
                                $p = 0;
                            }
                            if ($p > 100) {
                                $p = 100;
                            }
                            $projectCode = formatProjectCode($item['projectid'] ?? 0, $item['creatat'] ?? ($item['startdate'] ?? null));
                            $projectName = trim((string) ($item['projectname'] ?? '-'));
                            $assigneeList = splitProjectPeople($item['assignee'] ?? '');
                            $clientText = trim((string) ($item['client'] ?? ''));
                            $timelineStatus = getProjectTimelineStatus($item, $p);
                            $searchText = strtolower($projectCode . ' ' . $projectName . ' ' . implode(' ', $assigneeList) . ' ' . $clientText . ' ' . fmtDateValue($item['startdate'] ?? null) . ' ' . fmtDateValue($item['deadline'] ?? null) . ' ' . $p . ' ' . $timelineStatus['label']);
                            ?>
                            <div class="project-row-card"
                                 data-search="<?php echo h($searchText); ?>"
                                 data-project-name="<?php echo h($projectName); ?>">
                                <div class="project-side">
                                    <div class="project-icon-box">
                                        <i class="far fa-folder-open"></i>
                                    </div>
                                    <span class="project-status-dot <?php echo h($timelineStatus['key']); ?>" title="<?php echo h($timelineStatus['label']); ?>"></span>
                                </div>
                                <div class="project-main">
                                    <div class="project-title-row">
                                        <span class="project-code-chip"><?php echo h($projectCode); ?></span>
                                        <div class="project-name"><?php echo h($projectName); ?></div>
                                        <span class="timeline-status-chip <?php echo h($timelineStatus['key']); ?>"><?php echo h($timelineStatus['label']); ?></span>
                                    </div>

                                    <div class="assignee-line">
                                        <div class="assignee-label"><i class="fas fa-user-friends mr-1"></i>Assignee</div>
                                        <div class="assignee-chip-list">
                                            <?php if (empty($assigneeList)) { ?>
                                                <span class="person-chip">-</span>
                                            <?php } else { ?>
                                                <?php foreach ($assigneeList as $personName) { ?>
                                                    <span class="person-chip" title="<?php echo h($personName); ?>"><?php echo h($personName); ?></span>
                                                <?php } ?>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="project-meta-grid">
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="far fa-calendar-alt"></i></span>
                                            <div>
                                                <div class="meta-label">Tanggal Mulai</div>
                                                <div class="meta-value"><?php echo h(fmtDateValue($item['startdate'] ?? null)); ?></div>
                                            </div>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="far fa-calendar-check"></i></span>
                                            <div>
                                                <div class="meta-label">Deadline</div>
                                                <div class="meta-value"><?php echo h(fmtDateValue($item['deadline'] ?? null)); ?></div>
                                            </div>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="far fa-clock"></i></span>
                                            <div>
                                                <div class="meta-label">Progress</div>
                                                <div class="progress-wrap">
                                                    <div class="progress-track">
                                                        <div class="progress-fill" style="width: <?php echo (int) $p; ?>%;"></div>
                                                    </div>
                                                    <span class="progress-pill <?php echo $p === 0 ? 'zero' : ''; ?>"><?php echo (int) $p; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon"><i class="far fa-user"></i></span>
                                            <div>
                                                <div class="meta-label">Klien</div>
                                                <div class="meta-value">
                                                    <span class="client-chip" title="<?php echo h($clientText !== '' ? $clientText : '-'); ?>">
                                                        <?php echo h($clientText !== '' ? $clientText : '-'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <a href="view_project.php?id=<?php echo (int) $item['projectid']; ?>" class="btn btn-outline-secondary project-action-btn" title="Lihat">
                                        <i class="fas fa-eye"></i>Lihat
                                    </a>
                                    <a href="project_report_preview.php?id=<?php echo (int) $item['projectid']; ?>" class="btn btn-outline-secondary project-action-btn report" title="Report Project" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf"></i>Report
                                    </a>
                                    <a href="edit_project.php?id=<?php echo (int) $item['projectid']; ?>" class="btn btn-outline-secondary project-action-btn" title="Edit">
                                        <i class="fas fa-pen"></i>Edit
                                    </a>
                                    <form method="post" class="d-inline delete-project-form">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="projectid" value="<?php echo (int) $item['projectid']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary project-action-btn delete" title="Hapus">
                                            <i class="fas fa-trash"></i>Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="project-empty" id="projectEmpty">
                        <i class="fas fa-search mr-1"></i>Data project tidak ditemukan.
                    </div>

                    <div class="project-footer">
                        <div id="projectInfo">Menampilkan 0 data</div>
                        <div class="project-pagination" id="projectPagination"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    $(function () {
        var notifEl = document.getElementById('projectNotif');
        if (notifEl) {
            setTimeout(function () {
                if (!notifEl || notifEl.style.display === 'none') return;
                notifEl.style.transition = 'opacity .25s ease';
                notifEl.style.opacity = '0';
                setTimeout(function () {
                    if (notifEl) notifEl.style.display = 'none';
                }, 250);
            }, 5000);
        }

        var $cards = $('#projectList .project-row-card');
        var $search = $('#projectSearch');
        var $length = $('#projectPageLength');
        var $info = $('#projectInfo');
        var $pagination = $('#projectPagination');
        var $empty = $('#projectEmpty');
        var currentPage = 1;

        function getFilteredCards() {
            var term = ($search.val() || '').toLowerCase().trim();
            if (term === '') {
                return $cards;
            }
            return $cards.filter(function () {
                return ($(this).data('search') || '').toString().indexOf(term) !== -1;
            });
        }

        function renderProjectList() {
            var pageLength = parseInt($length.val(), 10) || 10;
            var $filtered = getFilteredCards();
            var total = $filtered.length;
            var totalPages = Math.max(1, Math.ceil(total / pageLength));

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }

            var start = total === 0 ? 0 : ((currentPage - 1) * pageLength) + 1;
            var end = Math.min(currentPage * pageLength, total);

            $cards.hide();
            $filtered.slice(start - 1, end).show();
            $empty.toggle(total === 0);

            if (total === 0) {
                $info.text('Menampilkan 0 data');
            } else {
                $info.text('Menampilkan ' + start + ' - ' + end + ' dari ' + total + ' data');
            }

            $pagination.empty();
            var $prev = $('<button type="button">Sebelumnya</button>').prop('disabled', currentPage === 1 || total === 0);
            $pagination.append($prev);
            $prev.on('click', function () {
                if (currentPage > 1) {
                    currentPage--;
                    renderProjectList();
                }
            });

            for (var i = 1; i <= totalPages; i++) {
                var $page = $('<button type="button"></button>').text(i).toggleClass('active', i === currentPage);
                (function (pageNo) {
                    $page.on('click', function () {
                        currentPage = pageNo;
                        renderProjectList();
                    });
                })(i);
                $pagination.append($page);
            }

            var $next = $('<button type="button">Selanjutnya</button>').prop('disabled', currentPage === totalPages || total === 0);
            $pagination.append($next);
            $next.on('click', function () {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderProjectList();
                }
            });
        }

        $search.on('input', function () {
            currentPage = 1;
            renderProjectList();
        });

        $length.on('change', function () {
            currentPage = 1;
            renderProjectList();
        });

        renderProjectList();

        $(document).on('submit', '.delete-project-form', function (e) {
            e.preventDefault();

            var form = this;
            var projectName = $(form).closest('.project-row-card').data('project-name') || '';
            var safeProjectName = $('<div>').text(projectName !== '' ? projectName : '-').html();

            if (typeof Swal === 'undefined') {
                if (confirm('Hapus data project ini?')) {
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title: 'Hapus Project?',
                html: 'Project <b>' + safeProjectName + '</b> akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Batal',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: true,
                customClass: {
                    popup: 'swal-delete-popup',
                    title: 'swal-delete-title',
                    htmlContainer: 'swal-delete-html',
                    confirmButton: 'swal-delete-confirm btn btn-danger',
                    cancelButton: 'swal-delete-cancel btn btn-secondary'
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
