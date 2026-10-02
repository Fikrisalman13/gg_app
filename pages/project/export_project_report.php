<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function reportH($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reportDateFmt($value, $format = 'd-m-Y')
{
    if ($value === null || $value === '') {
        return '-';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format($format);
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return (string) $value;
    }
    return date($format, $ts);
}

function reportDateOnly($value)
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

function reportProjectCode($projectId, $dateValue = null)
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

function reportSplitPeople($value)
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

function reportIssueDone($status)
{
    return in_array(strtoupper(trim((string) $status)), array('DONE', 'CLOSED'), true);
}

function reportTimelineStatus($project, $progress)
{
    $progress = (int) $progress;
    $deadline = reportDateOnly($project['deadline'] ?? null);
    $completedAt = reportDateOnly($project['completed_at'] ?? null);
    if ($completedAt === '' && $progress >= 100) {
        $completedAt = reportDateOnly($project['updateat'] ?? null);
    }
    $today = date('Y-m-d');

    if ($progress >= 100) {
        if ($deadline !== '' && $completedAt !== '') {
            if ($completedAt <= $deadline) {
                return array('label' => 'ON TIME', 'class' => 'on-time');
            }
            return array('label' => 'LATE', 'class' => 'late');
        }
        return array('label' => 'ON TIME', 'class' => 'on-time');
    }

    if ($deadline !== '' && $today > $deadline) {
        return array('label' => 'OVERDUE', 'class' => 'overdue');
    }

    return array('label' => 'ON TRACK', 'class' => 'on-track');
}

function reportFileName($project)
{
    $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) ($project['projectname'] ?? 'Project'));
    $name = trim($name, '_');
    if ($name === '') {
        $name = 'Project';
    }
    return 'Project_Report_' . $name . '_' . date('Ymd_His') . '.pdf';
}

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($projectId <= 0) {
    http_response_code(400);
    echo 'Project ID tidak valid.';
    exit;
}

$project = null;
$issues = array();

$sqlProject = "SELECT projectid, projectname, assignee, client, startdate, deadline, progress, ISNULL(progress_auto, 1) AS progress_auto,
                      [desc], creatby, creatat, updateby, updateat
               FROM dbo.project
               WHERE projectid = ?";
$stmtProject = sqlsrv_query($conn, $sqlProject, array($projectId));
if ($stmtProject) {
    $project = sqlsrv_fetch_array($stmtProject, SQLSRV_FETCH_ASSOC);
}

if (!$project) {
    http_response_code(404);
    echo 'Project tidak ditemukan.';
    exit;
}

$sqlIssues = "SELECT l.issue_id, l.issue_status_snapshot, l.created_at AS linked_at,
                     i.issue_name, i.issue_type, i.status AS live_status, i.priority, i.due_date,
                     i.description, i.created_by, i.created_at, i.update_at, i.tanggal_selesai
              FROM dbo.project_issue_links l
              LEFT JOIN dbo.issues i ON l.issue_id = i.issue_id
              WHERE l.projectid = ?
              ORDER BY l.link_id ASC";
$stmtIssues = sqlsrv_query($conn, $sqlIssues, array($projectId));
if ($stmtIssues) {
    while ($row = sqlsrv_fetch_array($stmtIssues, SQLSRV_FETCH_ASSOC)) {
        $issues[] = $row;
    }
}

$totalIssues = count($issues);
$doneIssues = 0;
$openIssues = 0;
$overdueIssues = 0;
$lastDoneDate = null;
$todayDate = date('Y-m-d');
$picSummary = array();

foreach ($issues as $issue) {
    $statusLabel = trim((string) ($issue['live_status'] ?? ''));
    if ($statusLabel === '') {
        $statusLabel = trim((string) ($issue['issue_status_snapshot'] ?? ''));
    }

    $isDone = reportIssueDone($statusLabel);
    $dueDate = reportDateOnly($issue['due_date'] ?? null);

    if ($isDone) {
        $doneIssues++;
        $doneDate = $issue['tanggal_selesai'] ?? ($issue['update_at'] ?? null);
        if ($doneDate !== null && ($lastDoneDate === null || reportDateOnly($doneDate) > reportDateOnly($lastDoneDate))) {
            $lastDoneDate = $doneDate;
        }
    } else {
        $openIssues++;
        if ($dueDate !== '' && $dueDate < $todayDate) {
            $overdueIssues++;
        }
    }

    $pic = trim((string) ($issue['created_by'] ?? ''));
    if ($pic === '') {
        $pic = 'Unassigned';
    }
    $type = trim((string) ($issue['issue_type'] ?? ''));
    if ($type === '') {
        $type = 'Task';
    }

    if (!isset($picSummary[$pic])) {
        $picSummary[$pic] = array(
            'name' => $pic,
            'types' => array(),
            'total' => 0
        );
    }
    $picSummary[$pic]['total']++;
    if (!in_array($type, $picSummary[$pic]['types'], true)) {
        $picSummary[$pic]['types'][] = $type;
    }
}

$progress = (int) ($project['progress'] ?? 0);
if ((int) ($project['progress_auto'] ?? 1) === 1) {
    $progress = $totalIssues > 0 ? (int) round(($doneIssues / $totalIssues) * 100) : 0;
}
$progress = max(0, min(100, $progress));

if ($lastDoneDate !== null) {
    $project['completed_at'] = $lastDoneDate;
}

if (empty($picSummary)) {
    $assignedPeople = reportSplitPeople($project['assignee'] ?? '');
    foreach ($assignedPeople as $person) {
        $picSummary[$person] = array(
            'name' => $person,
            'types' => array('-'),
            'total' => 0
        );
    }
}

foreach ($picSummary as $k => $v) {
    $picSummary[$k]['types_text'] = !empty($v['types']) ? implode(' & ', $v['types']) : '-';
}

$timelineStatus = reportTimelineStatus($project, $progress);
$code = reportProjectCode($project['projectid'] ?? 0, $project['creatat'] ?? ($project['startdate'] ?? null));

$completedAtText = '-';
if ($progress >= 100) {
    $completedAtDate = $project['completed_at'] ?? ($project['updateat'] ?? null);
    $completedAtText = $completedAtDate ? reportDateFmt($completedAtDate, 'd-m-Y H:i') : '-';
}

$timelineRangeText = reportDateFmt($project['startdate'] ?? null, 'd-m-Y') . ' s/d ' . reportDateFmt($project['deadline'] ?? null, 'd-m-Y');

$logoPath = realpath(__DIR__ . '/../../dist/img/sumlogo.png');
$logoHtml = '';
if ($logoPath && file_exists($logoPath)) {
    $logoBase64 = base64_encode(file_get_contents($logoPath));
    $logoHtml = '<img src="data:image/png;base64,' . $logoBase64 . '" style="width:44px; height:auto;">';
}

$statusBadgeHtml = '';
if ($progress >= 100) {
    $statusBadgeHtml = '<span class="badge-status-completed">COMPLETED (' . (int) $progress . '%)</span>';
} else {
    $statusBadgeHtml = '<span class="badge-status-progress">IN PROGRESS (' . (int) $progress . '%)</span>';
}

$timelineBadgeHtml = '';
if ($timelineStatus['class'] === 'on-time' || $timelineStatus['class'] === 'on-track') {
    $timelineBadgeHtml = '<span class="badge-timeline-dark">' . reportH($timelineStatus['label']) . '</span>';
} else {
    $timelineBadgeHtml = '<span class="badge-timeline-danger">' . reportH($timelineStatus['label']) . '</span>';
}

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 22px 26px 30px 26px;
}
* {
    box-sizing: border-box;
}
body {
    font-family: DejaVu Sans, sans-serif;
    color: #111827;
    font-size: 8pt;
    line-height: 1.35;
    background: #ffffff;
    margin: 0;
    padding: 0;
}
.header-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
}
.header-table td {
    vertical-align: middle;
}
.company-name {
    font-size: 11pt;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.2px;
}
.company-desc {
    font-size: 7.5pt;
    color: #374151;
    line-height: 1.3;
    margin-top: 1px;
}
.doc-title {
    font-size: 12pt;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.4px;
    text-align: right;
}
.project-code-head {
    font-size: 9.5pt;
    font-weight: 800;
    color: #0f172a;
    text-align: right;
    margin-top: 2px;
}
.header-divider {
    width: 100%;
    height: 1.5px;
    background: #0f172a;
    margin-bottom: 8px;
}
.summary-card {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #9ca3af;
    background: #ffffff;
    margin-bottom: 12px;
}
.summary-card td {
    vertical-align: top;
    padding: 6px 9px;
    border-right: 1px solid #9ca3af;
}
.summary-card td:last-child {
    border-right: none;
}
.card-label {
    font-size: 7pt;
    font-weight: 700;
    color: #4b5563;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}
.card-title {
    font-size: 9pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
}
.card-sub {
    font-size: 7.5pt;
    color: #111827;
    margin-top: 3px;
}
.card-meta {
    font-size: 7.5pt;
    color: #111827;
    line-height: 1.35;
}
.badge-status-completed {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 3px;
    background: #dcfce7;
    border: 1px solid #86efac;
    color: #15803d;
    font-size: 7pt;
    font-weight: 800;
    text-transform: uppercase;
}
.badge-status-progress {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 3px;
    background: #dbeafe;
    border: 1px solid #93c5fd;
    color: #1d4ed8;
    font-size: 7pt;
    font-weight: 800;
    text-transform: uppercase;
}
.badge-timeline-dark {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 3px;
    background: #0f172a;
    color: #ffffff;
    font-size: 7pt;
    font-weight: 800;
    text-transform: uppercase;
}
.badge-timeline-danger {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 3px;
    background: #dc2626;
    color: #ffffff;
    font-size: 7pt;
    font-weight: 800;
    text-transform: uppercase;
}
.section-title {
    font-size: 8.5pt;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    margin-top: 10px;
    margin-bottom: 5px;
    border-left: 3.5px solid #0f172a;
    padding-left: 5px;
    line-height: 1.2;
}
table.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
table.report-table th {
    background: #000000;
    color: #ffffff;
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    padding: 5px 6px;
    border: none;
    letter-spacing: 0.2px;
}
table.report-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 4.5px 6px;
    font-size: 7.5pt;
    color: #111827;
    vertical-align: middle;
}
table.report-table tr:nth-child(even) td {
    background: #fafafa;
}
.pill-status {
    display: inline-block;
    background: #e5e7eb;
    color: #1f2937;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    padding: 1.5px 6px;
    font-size: 7pt;
    font-weight: 700;
    text-align: center;
}
.pill-open {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    border-radius: 3px;
    padding: 1.5px 6px;
    font-size: 7pt;
    font-weight: 700;
    text-align: center;
}
.pill-overdue {
    display: inline-block;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    border-radius: 3px;
    padding: 1.5px 6px;
    font-size: 7pt;
    font-weight: 700;
    text-align: center;
}
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 50px; text-align: left;">' . $logoHtml . '</td>
        <td style="padding-left: 6px;">
            <div class="company-name">PT. SURYA USAHA MANDIRI</div>
            <div class="company-desc">Jl. Tarajusari No. 8 Kp. Cipendeuy RT 001 RW 007, Banjaran - Kab. Bandung 40377</div>
            <div class="company-desc">Telp. (022) 594-0313</div>
        </td>
        <td style="text-align: right;">
            <div class="doc-title">PROJECT REPORT</div>
            <div class="project-code-head">' . reportH($code) . '</div>
        </td>
    </tr>
</table>
<div class="header-divider"></div>

<table class="summary-card">
    <tr>
        <td style="width: 32%;">
            <div class="card-label">NAMA PROYEK</div>
            <div class="card-title">' . reportH($project['projectname'] ?? '-') . '</div>
            <div class="card-sub"><span style="color:#4b5563; font-weight:700;">KLIEN:</span> ' . reportH($project['client'] ?? '-') . '</div>
        </td>
        <td style="width: 38%;">
            <div class="card-label">STATUS & PENCAPAIAN</div>
            <div style="margin: 3px 0 4px;">
                ' . $statusBadgeHtml . '
                ' . $timelineBadgeHtml . '
            </div>
            <div class="card-sub">Total: ' . (int) $totalIssues . ' Task | Selesai: ' . (int) $doneIssues . ' | Overdue: ' . (int) $overdueIssues . '</div>
        </td>
        <td style="width: 30%;">
            <div class="card-label">JADWAL & PEMBUAT</div>
            <div class="card-meta">Timeline: ' . reportH($timelineRangeText) . '</div>
            <div class="card-meta">Selesai: ' . reportH($completedAtText) . '</div>
            <div class="card-meta">Dibuat oleh: ' . reportH($project['creatby'] ?? '-') . '</div>
        </td>
    </tr>
</table>

<div class="section-title">RINCIAN PEKERJAAN / TASK BREAKDOWN</div>
<table class="report-table">
    <thead>
        <tr>
            <th style="width: 4%; text-align: center;">NO</th>
            <th style="width: 44%; text-align: left;">TASK / URAIAN PEKERJAAN</th>
            <th style="width: 17%; text-align: left;">TIPE</th>
            <th style="width: 17%; text-align: left;">PIC</th>
            <th style="width: 11%; text-align: center;">DUE DATE</th>
            <th style="width: 7%; text-align: center;">STATUS</th>
        </tr>
    </thead>
    <tbody>';

if (empty($issues)) {
    $html .= '<tr><td colspan="6" style="text-align:center; color:#6b7280; padding:10px;">Belum ada task / issue yang terhubung.</td></tr>';
} else {
    $no = 1;
    foreach ($issues as $issue) {
        $statusLabel = trim((string) ($issue['live_status'] ?? ''));
        if ($statusLabel === '') {
            $statusLabel = trim((string) ($issue['issue_status_snapshot'] ?? ''));
        }
        $isDone = reportIssueDone($statusLabel);
        $dueDate = reportDateOnly($issue['due_date'] ?? null);
        $isOverdue = $dueDate !== '' && $dueDate < $todayDate && !$isDone;

        $pillClass = 'pill-status';
        $displayStatus = $statusLabel !== '' ? $statusLabel : 'Open';
        if ($isDone) {
            $pillClass = 'pill-status';
            $displayStatus = 'Done';
        } elseif ($isOverdue) {
            $pillClass = 'pill-overdue';
            $displayStatus = 'Overdue';
        } else {
            $pillClass = 'pill-open';
        }

        $html .= '<tr>
            <td style="text-align: center; font-weight: 700;">' . $no . '</td>
            <td><strong>#' . (int) ($issue['issue_id'] ?? 0) . '</strong> ' . reportH($issue['issue_name'] ?? '-') . '</td>
            <td>' . reportH($issue['issue_type'] ?? '-') . '</td>
            <td>' . reportH($issue['created_by'] ?? '-') . '</td>
            <td style="text-align: center;">' . reportH(reportDateOnly($issue['due_date'] ?? null) ?: '-') . '</td>
            <td style="text-align: center;"><span class="' . $pillClass . '">' . reportH($displayStatus) . '</span></td>
        </tr>';
        $no++;
    }
}

$html .= '</tbody>
</table>

<div class="section-title">DAFTAR PIC / ASSIGNED STAFF</div>
<table class="report-table">
    <thead>
        <tr>
            <th style="width: 5%; text-align: center;">NO</th>
            <th style="width: 45%; text-align: left;">NAMA STAF / PIC</th>
            <th style="width: 35%; text-align: left;">TIPE PEKERJAAN</th>
            <th style="width: 15%; text-align: center;">TOTAL TASK</th>
        </tr>
    </thead>
    <tbody>';

if (empty($picSummary)) {
    $html .= '<tr><td colspan="4" style="text-align:center; color:#6b7280; padding:10px;">Belum ada PIC / staf yang ditugaskan.</td></tr>';
} else {
    $picNo = 1;
    foreach ($picSummary as $picData) {
        $html .= '<tr>
            <td style="text-align: center; font-weight: 700;">' . $picNo . '</td>
            <td>' . reportH($picData['name']) . '</td>
            <td>' . reportH($picData['types_text']) . '</td>
            <td style="text-align: center; font-weight: 700;">' . (int) $picData['total'] . ' Task</td>
        </tr>';
        $picNo++;
    }
}

$html .= '</tbody>
</table>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->getFont('DejaVu Sans', 'normal');
$canvas->page_text(26, 816, 'PT. SURYA USAHA MANDIRI | Project Code: ' . $code, $font, 7.5, array(0.35, 0.4, 0.45));
$canvas->page_text(495, 816, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7.5, array(0.35, 0.4, 0.45));

$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$dompdf->stream(reportFileName($project), array('Attachment' => $download));
exit;

