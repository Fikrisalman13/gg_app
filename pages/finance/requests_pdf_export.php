<?php
// requests_pdf_export.php - Export Financial Request List to PDF
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

$userId = $_SESSION['UserId'];
$groupId = $_SESSION['GroupId'];
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

// 2. Base Query
$sql = "SELECT r.*, u.UnitName, c.CategoryName, emp.nama_lengkap as RequesterName
        FROM fin_requests r
        LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
        LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
        LEFT JOIN dbo.SMUserMs usr ON r.CreatedBy = usr.UserId
        LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp";

$where = [];
$params = [];

// Privacy Filter
if ($groupId != 1) {
    $where[] = "r.CreatedBy = ?";
    $params[] = $userId;
}

// Date Filter
if (!empty($startDate)) {
    $where[] = "CAST(r.CreatedAt AS DATE) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "CAST(r.CreatedAt AS DATE) <= ?";
    $params[] = $endDate;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY r.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
$requests = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $requests[] = $row;
    }
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
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #333; }
        .header-table { width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 9pt; text-align: center; margin-top: 5px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .data-table th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8pt; text-align: center; border-top: 1px solid #ccc; padding-top: 5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="80"><img src="' . $logoSrc . '" width="50"></td>
            <td align="center">
                <div class="title">LAPORAN PENGAJUAN DANA</div>
                <div class="subtitle">PT. SURYA USAHA MANDIRI - FINANCE DEPT</div>
                <div style="font-size: 8pt; margin-top: 5px;">
                    Periode: ' . ($startDate ? date('d/m/Y', strtotime($startDate)) : 'Awal') . ' s/d ' . ($endDate ? date('d/m/Y', strtotime($endDate)) : 'Sekarang') . '
                </div>
            </td>
            <td width="80" align="right"></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="30">NO</th>
                <th width="70">TANGGAL</th>
                <th>PEMOHON</th>
                <th>UNIT</th>
                <th>JUDUL KEPERLUAN</th>
                <th width="90">NOMINAL</th>
                <th width="70">STATUS</th>
            </tr>
        </thead>
        <tbody>';

$totalAmount = 0;
foreach ($requests as $idx => $r) {
    $totalAmount += $r['Amount'];
    $html .= '<tr>
                <td class="text-center">' . ($idx + 1) . '</td>
                <td>' . date_format($r['CreatedAt'], 'd/m/Y') . '</td>
                <td>' . htmlspecialchars($r['RequesterName']) . '</td>
                <td>' . htmlspecialchars($r['UnitName']) . '</td>
                <td>' . htmlspecialchars($r['Title']) . '</td>
                <td class="text-right font-bold">Rp ' . number_format($r['Amount'], 0, ',', '.') . '</td>
                <td class="text-center">' . $r['Status'] . '</td>
              </tr>';
}

$html .= '</tbody>
        <tfoot>
            <tr style="background-color: #f9f9f9;">
                <td colspan="5" class="text-right font-bold">TOTAL PENGAJUAN:</td>
                <td class="text-right font-bold" style="background-color: #eee; font-size: 10pt;">Rp ' . number_format($totalAmount, 0, ',', '.') . '</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dicetak oleh: ' . $_SESSION['UserName'] . ' pada ' . date('d/m/Y H:i') . ' | Halaman {PAGENO}
    </div>
</body></html>';

// 5. Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Laporan_Pengajuan_" . date('Ymd_His') . ".pdf", array("Attachment" => false));
?>
