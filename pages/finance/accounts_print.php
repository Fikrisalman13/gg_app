<?php
// accounts_print.php - Export Account Ledger to PDF
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

$accountId = $_GET['id'] ?? '';
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

if (empty($accountId)) {
    die("ID Akun tidak ditemukan.");
}

// 2. Fetch Account Details
$accSql = "SELECT * FROM fin_cash_accounts WHERE AccountId = ?";
$accStmt = sqlsrv_query($conn, $accSql, [$accountId]);
$account = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC);

if (!$account) die("Akun tidak ditemukan.");

// 3. Fetch Ledger Data
// Get Opening Balance before Start Date
$openingSql = "SELECT SUM(CASE WHEN Type = 'IN' THEN Amount ELSE -Amount END) as OpeningBalance 
               FROM fin_ledger 
               WHERE AccountId = ? AND TransTimestamp < ?";
$opStmt = sqlsrv_query($conn, $openingSql, [$accountId, $startDate]);
$opRow = sqlsrv_fetch_array($opStmt, SQLSRV_FETCH_ASSOC);
$openingBalance = $opRow['OpeningBalance'] ?? 0;

$sql = "SELECT * FROM fin_ledger 
        WHERE AccountId = ? 
        AND CAST(TransTimestamp AS DATE) BETWEEN ? AND ?
        ORDER BY TransTimestamp ASC";
$stmt = sqlsrv_query($conn, $sql, [$accountId, $startDate, $endDate]);

$ledger = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $ledger[] = $row;
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
        
        .info-box { margin-bottom: 15px; }
        .info-box span { display: block; }
        
        .ledger-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ledger-table th, .ledger-table td { border: 1px solid #ddd; padding: 6px; }
        .ledger-table th { background-color: #f2f2f2; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .bg-light { background-color: #f9f9f9; }
        
        .text-in { color: green; }
        .text-out { color: red; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8pt; text-align: center; border-top: 1px solid #ccc; padding-top: 5px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="60"><img src="' . $logoSrc . '" width="50"></td>
            <td align="center">
                <div class="title">LAPORAN MUTASI KAS</div>
                <div class="subtitle">PT. SURYA USAHA MANDIRI</div>
            </td>
            <td width="60" align="right"></td>
        </tr>
    </table>

    <div class="info-box">
        <b>Nama Akun:</b> ' . htmlspecialchars($account['AccountName']) . '<br>
        <b>Periode:</b> ' . date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)) . '
    </div>

    <table class="ledger-table">
        <thead>
            <tr>
                <th width="80">Waktu</th>
                <th>Keterangan</th>
                <th width="30">Tipe</th>
                <th width="80">Masuk</th>
                <th width="80">Keluar</th>
                <th width="90">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr class="bg-light">
                <td colspan="5" class="text-bold">Saldo Awal</td>
                <td class="text-right text-bold">' . number_format($openingBalance, 0, ',', '.') . '</td>
            </tr>';

$runningBalance = $openingBalance;
        $totalIn = 0;
        $totalOut = 0;
foreach ($ledger as $l) {
    if ($l['Type'] == 'IN') {
        $runningBalance += $l['Amount'];
                $totalIn += $l['Amount'];
        $inAmount = number_format($l['Amount'], 0, ',', '.');
        $outAmount = '-';
        $typeClass = 'text-in';
    } else {
        $runningBalance -= $l['Amount'];
                $totalOut += $l['Amount'];
        $inAmount = '-';
        $outAmount = number_format($l['Amount'], 0, ',', '.');
        $typeClass = 'text-out';
    }
    
    $html .= '<tr>
                <td class="text-center">' . date_format($l['TransTimestamp'], 'd/m/y H:i') . '</td>
                <td>' . htmlspecialchars($l['Description']) . '</td>
                <td class="text-center ' . $typeClass . '">' . $l['Type'] . '</td>
                <td class="text-right">' . $inAmount . '</td>
                <td class="text-right">' . $outAmount . '</td>
                <td class="text-right text-bold">' . number_format($runningBalance, 0, ',', '.') . '</td>
              </tr>';
}
// add totals row for the period

$html .= '<tr class="bg-light">'
    . '<td colspan="3" class="text-bold">Total</td>'
    . '<td class="text-right text-bold">' . number_format($totalIn, 0, ',', '.') . '</td>'
    . '<td class="text-right text-bold">' . number_format($totalOut, 0, ',', '.') . '</td>'
    . '<td class="text-right text-bold">' . number_format($runningBalance, 0, ',', '.') . '</td>'
    . '</tr>';

$html .= '</tbody>
    </table>

    <div class="footer">
        Dicetak oleh: ' . $_SESSION['UserName'] . ' pada ' . date('d/m/Y H:i') . '<br>
        Halaman ini digenerate secara otomatis oleh sistem.
    </div>
</body>
</html>';

// 5. Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Ledger_" . str_replace(' ', '_', $account['AccountName']) . ".pdf", array("Attachment" => false));
?>
