<?php
// requests_print.php - Export Financial Request to PDF
ob_start();
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Validation
if (!isset($_SESSION['UserName'])) {
    die("Akses ditolak. Silakan login.");
}

$requestId = $_GET['id'] ?? '';
if (empty($requestId)) {
    die("ID Pengajuan tidak ditemukan.");
}

// 2. Fetch Data
$sql = "SELECT r.*, u.UnitName, c.CategoryName, ar.RuleName,
               emp.nama_lengkap as RequesterName, curr_s.StepName as CurrentStepName,
               bp.Period as BudgetPeriod
        FROM fin_requests r
        LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
        LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
        LEFT JOIN fin_budget_plans bp ON r.BudgetId = bp.BudgetId
        LEFT JOIN fin_approval_rules ar ON r.RuleId = ar.RuleId
        LEFT JOIN fin_workflow_steps curr_s ON r.CurrentStepId = curr_s.StepId
        LEFT JOIN dbo.SMUserMs usr ON r.CreatedBy = usr.UserId
        LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
        WHERE r.RequestId = ?";
$stmt = sqlsrv_query($conn, $sql, [$requestId]);
$req = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$req) die("Data tidak ditemukan.");

// 3. Fetch History (Approvals)
$histSql = "SELECT h.*, s.StepName, emp.nama_lengkap as ActorName
            FROM fin_request_history h
            LEFT JOIN fin_workflow_steps s ON h.StepId = s.StepId
            LEFT JOIN dbo.SMUserMs usr ON h.ActorUserId = usr.UserId
            LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
            WHERE h.RequestId = ?
            ORDER BY h.ActionTimestamp ASC";
$histStmt = sqlsrv_query($conn, $histSql, [$requestId]);
$history = [];
while ($row = sqlsrv_fetch_array($histStmt, SQLSRV_FETCH_ASSOC)) {
    $history[] = $row;
}

// 4. HTML Template
$logoPath = "../../dist/img/sumlogo.png"; 
$logoBase64 = "";
if (file_exists($logoPath)) {
    $logoBase64 = base64_encode(file_get_contents($logoPath));
}
$logoSrc = "data:image/png;base64," . $logoBase64;

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
        .header-table { width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 10pt; text-align: center; margin-top: 5px; }
        
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 5px; vertical-align: top; }
        .label { font-weight: bold; width: 140px; }
        
        .amount-box { border: 2px solid #333; padding: 10px; font-size: 14pt; font-weight: bold; text-align: center; margin: 20px 0; background-color: #f9f9f9; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th, .history-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .history-table th { background-color: #f2f2f2; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8pt; text-align: center; border-top: 1px solid #ccc; padding-top: 5px; }
        
        .status-stamp {
            position: absolute; top: 150px; right: 50px; border: 3px solid red; 
            padding: 10px; color: red; font-size: 20pt; font-weight: bold; transform: rotate(-15deg); opacity: 0.5;
        }
        .status-approved { border-color: green; color: green; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="80"><img src="' . $logoSrc . '" width="60"></td>
            <td align="center">
                <div class="title">BUKTI PENGAJUAN DANA</div>
                <div class="subtitle">PT. SURYA USAHA MANDIRI - FINANCE DEPT</div>
            </td>
            <td width="80" align="right">NO: ' . $req['RequestId'] . '</td>
        </tr>
    </table>

    <table class="info-table">
        <tr><td class="label">Tanggal Request</td><td>: ' . date_format($req['CreatedAt'], 'd F Y') . '</td></tr>
        <tr><td class="label">Pemohon</td><td>: ' . htmlspecialchars($req['RequesterName']) . '</td></tr>
        <tr><td class="label">Unit / Divisi</td><td>: ' . htmlspecialchars($req['UnitName']) . '</td></tr>
        <tr><td class="label">Judul Keperluan</td><td>: ' . htmlspecialchars($req['Title']) . '</td></tr>
        <tr><td class="label">Kategori</td><td>: ' . htmlspecialchars($req['CategoryName']) . '</td></tr>
    </table>

    <div class="amount-box">
        Rp ' . number_format($req['Amount'], 0, ',', '.') . '
    </div>

    <div style="margin-bottom: 20px;">
        <b>Keterangan / Deskripsi:</b><br>
        ' . nl2br(htmlspecialchars($req['Description'] ?? '-')) . '
    </div>

    <h3>Riwayat Approval</h3>
    <table class="history-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Tahapan</th>
                <th>Oleh</th>
                <th>Status</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>';
        
foreach ($history as $h) {
    $html .= '<tr>
                <td>' . date_format($h['ActionTimestamp'], 'd/m/Y H:i') . '</td>
                <td>' . htmlspecialchars($h['StepName'] ?? '-') . '</td>
                <td>' . htmlspecialchars($h['ActorName']) . '</td>
                <td>' . $h['Action'] . '</td>
                <td>' . htmlspecialchars($h['Note'] ?? '-') . '</td>
              </tr>';
}

$html .= '</tbody>
    </table>

    <div class="footer">
        Dicetak oleh: ' . $_SESSION['UserName'] . ' pada ' . date('d/m/Y H:i') . '<br>
        Dokumen ini digenerate secara otomatis oleh sistem.
    </div>';

if ($req['Status'] == 'Approved') {
    $html .= '<div class="status-stamp status-approved">APPROVED</div>';
} elseif ($req['Status'] == 'Rejected') {
    $html .= '<div class="status-stamp">REJECTED</div>';
}

$html .= '</body></html>';

// 5. Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Voucher_" . $requestId . ".pdf", array("Attachment" => false));
?>
