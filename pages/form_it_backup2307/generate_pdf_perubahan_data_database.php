<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require '../../vendor/autoload.php';
use Dompdf\Dompdf;

require '../../koneksi.php';

session_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../../login.php');
    exit;
}

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : null;
if (!$ticket) {
    die("Error: Ticket tidak ditemukan!");
}

try {
    $sql = "SELECT * FROM Form_Perubahan_Data_Database WHERE ticket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt === false || !($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        throw new Exception("Data pengajuan dengan ticket '$ticket' tidak ditemukan!");
    }

    // --- Helpers ---
    function tampilkan($text, $panjang = 35) {
        $text = trim($text ?? '');
        if ($text === '') {
            return "<span style='display:inline-block; border-bottom:0.5px solid #000; width:{$panjang}ch;'>&nbsp;</span>";
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    function fmtDate($d) {
        if ($d instanceof DateTime) return $d->format('d-m-Y');
        if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $p = explode('-', $d); return $p[2].'-'.$p[1].'-'.$p[0];
        }
        return '-';
    }

    // Build comma-separated list of selected options (only show checked ones)
    function selectedList($map) {
        $selected = [];
        foreach ($map as $label => $val) {
            if ($val == 1) $selected[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }
        return empty($selected) ? '-' : implode(', ', $selected);
    }

    // Logo as base64
    $logoPath = "../../dist/img/sumlogo.png";
    $logoBase64 = "";
    if (file_exists($logoPath)) {
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        }
    $logoSrc = "data:image/png;base64," . $logoBase64;

    // TTD
    $ttd = [];
    $sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
    $stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
    if ($stmtTtd) {
        while ($rT = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
            $ttd[$rT['GroupRole']] = $rT;
        }
    }

    // Helper: render signature cell as base64 img
    function sigCell($ttd, $role) {
        if (!isset($ttd[$role]) || empty($ttd[$role]['SignaturePath'])) return '&nbsp;';
        $sigPath = $ttd[$role]['SignaturePath'];
        $signedBy = htmlspecialchars($ttd[$role]['SignedByUserName'] ?? '');
        $absolute = null;
        if (file_exists($sigPath)) { $absolute = $sigPath; }
        elseif (isset($_SERVER['DOCUMENT_ROOT']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'],'\\/') . $sigPath)) {
            $absolute = rtrim($_SERVER['DOCUMENT_ROOT'],'\\/') . $sigPath;
        }
        elseif (file_exists(__DIR__ . '/../../' . ltrim($sigPath, '/\\'))) {
            $absolute = __DIR__ . '/../../' . ltrim($sigPath, '/\\');
        }
        if ($absolute) {
            $img = base64_encode(file_get_contents($absolute));
            return "<img src='data:image/png;base64,$img' style='max-height:55px; display:block; margin:0 auto;'><br><small>$signedBy</small>";
        }
        return "<img src='" . htmlspecialchars($sigPath) . "' style='max-height:55px; display:block; margin:0 auto;'><br><small>$signedBy</small>";
    }

    // Signature table – 4 roles matching the form
    $roles = [
        'Pemohon'       => 'Diajukan oleh,',
        'Atasan Pemohon'=> 'Diketahui oleh,',
        'Kabag IT'      => 'Mengetahui,',   // will prefer Kadept if it exists
        'Direksi'       => 'Disetujui oleh,',
    ];

    // Prefer Kadept IT over Kabag IT for the "Mengetahui" slot
    $itRole = isset($ttd['Kadept IT']) ? 'Kadept IT' : 'Kabag IT';
    $ttdRoles = ['Pemohon', 'Atasan Pemohon', $itRole, 'Direksi'];
    $ttdHeaders = ['Diajukan oleh,', 'Diketahui oleh,', 'Mengetahui,', 'Disetujui oleh,'];

    $signTable = "<table class='sign-table' width='100%' style='border-collapse:collapse; border:none; table-layout:fixed;'>\n";
    $signTable .= "<colgroup><col style='width:25%;'/><col style='width:25%;'/><col style='width:25%;'/><col style='width:25%;'/></colgroup>\n";

    // Header row
    $signTable .= "<tr class='center bold' style='height:25px;'>";
    foreach ($ttdHeaders as $h) {
        $signTable .= "<td>$h</td>";
    }
    $signTable .= "</tr>\n";

    // Signature image row
    $signTable .= "<tr style='height:80px;' class='sign-row'>";
    foreach ($ttdRoles as $r) {
        $signTable .= "<td style='text-align:center;'>" . sigCell($ttd, $r) . "</td>";
    }
    $signTable .= "</tr>\n";

    // Name label row — always show ROLE name (signer name already shown in sigCell via <small>)
    $signTable .= "<tr class='center bold' style='font-size:9pt; height:22px;'>";
    $labelMap = ['Pemohon' => 'Pemohon', 'Atasan Pemohon' => 'Atasan Langsung', 'Kabag IT' => 'Kabag/Kadept IT', 'Kadept IT' => 'Kabag/Kadept IT', 'Direksi' => 'Direksi'];
    foreach ($ttdRoles as $r) {
        $roleLabel = $labelMap[$r] ?? $r;
        $signTable .= "<td>$roleLabel</td>";
    }
    $signTable .= "</tr>\n";

    // Ketentuan + footer
    $signTable .= "<tr>\n<td colspan='4' style='font-size:9pt; padding:6px 8px; text-align:left;'>\n";
    $signTable .= "<b>Ketentuan :</b><br>\n<ol style='margin:4px 0 0 18px; padding:0; text-align:left;'>\n";
    $signTable .= "<li>Database Aplikasi sepenuhnya menjadi tanggung jawab Departemen IT.</li>\n";
    $signTable .= "<li>Perubahan data via Database <u>tidak dianjurkan jika tidak urgent</u>, karna dapat beresiko terhadap integritas, keamanan dan konsistensi data.</li>\n";
    $signTable .= "</ol>\n</td>\n</tr>\n";
    $signTable .= "<tr>\n<td colspan='3' style='text-align:center; font-weight:bold;'>SUM-FM-IT-022</td>\n<td style='text-align:center; font-weight:bold;'>SW</td>\n</tr>\n";
    $signTable .= "</table>\n";

    // --- HTML Content for PDF ---
    $html = "
    <html>
    <head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }
        .border { border: 1px solid #000; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .small { font-size: 9pt; }
        .form-row td { padding: 2px 6px; }
        .label { width: 27%; white-space: nowrap; }
        .value { width: 73%; padding-left: 3px; }
        .sign-table td { padding: 6px 8px; vertical-align: bottom; text-align: center; border: 1px solid #000; }
        .sign-table img { max-height: 60px; display: block; margin: 0 auto; }
        .sign-row { height: 80px; }
    </style>
    </head>
    <body>

    <!-- Header -->
    <table class='border' style='border-bottom:none;'>
        <tr>
            <td width='15%' style='border-right:1px solid #000; text-align:center;'>
                " . ($logoBase64 ? "<img src='{$logoSrc}' width='60'>" : "") . "
            </td>
            <td style='padding-left:5px; line-height:1.3; font-size:9pt;'>
                <b>PT. SURYA USAHA MANDIRI</b><br>
                Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
                Banjaran – Kab. Bandung<br>
                40377 Telp. (022) 594-0313
            </td>
        </tr>
        <tr>
            <td colspan='2' class='center bold' style='border-top:1px solid #000; border-bottom:1px solid #000;'>PENGAJUAN PERUBAHAN DATA VIA DATABASE</td>
        </tr>
    </table>

    <!-- Form utama -->
    <table class='border' style='border-top:none; border-bottom:0;'>
        <tr class='form-row'>
            <td class='label'>Nama Pemohon</td>
            <td class='value' colspan='3'>: " . tampilkan($data['nama_pemohon']) . "</td>
        </tr>
        <tr class='form-row'>
            <td class='label' style='width:35%;'>Jabatan</td>
            <td class='value' style='width:35%;'>: " . tampilkan($data['jabatan']) . "</td>
            <td class='label' style='text-align:right; width:25%;'>Tgl Pengajuan</td>
            <td class='value' style='text-align:right; width:25%;'>: " . fmtDate($data['tgl_pengajuan']) . "</td>
        </tr>
        <tr class='form-row'>
            <td class='label'>Departemen</td>
            <td class='value' colspan='3'>: " . tampilkan($data['departemen']) . "</td>
        </tr>";

    if (!empty($data['bagian'])) {
        $html .= "
        <tr class='form-row'>
            <td class='label'>Bagian</td>
            <td class='value' colspan='3'>: " . tampilkan($data['bagian']) . "</td>
        </tr>";
    }

    // Build selected-only text for checkbox fields
    $req_text = selectedList([
        'Add'    => $data['req_add'],
        'Edit'   => $data['req_edit'],
        'Delete' => $data['req_delete'],
    ]);
    $app_items = ['Proint' => $data['app_proint'], 'HRIS' => $data['app_hris']];
    if ($data['app_lainnya'] == 1 && !empty($data['app_lainnya_text'])) {
        $app_items[$data['app_lainnya_text']] = 1;
    } elseif ($data['app_lainnya'] == 1) {
        $app_items['Lainnya'] = 1;
    }
    $app_text = selectedList($app_items);

    $html .= "
        <tr class='form-row'>
            <td class='label'>Mengajukan permintaan untuk</td>
            <td class='value' colspan='3'>: $req_text</td>
        </tr>
        <tr class='form-row'>
            <td class='label'>Aplikasi</td>
            <td class='value' colspan='3'>: $app_text</td>
        </tr>
        <tr class='form-row'>
            <td class='label' style='vertical-align:top;'>Perubahan</td>
            <td class='value' colspan='3'>: " . nl2br(tampilkan($data['perubahan'], 70)) . "<br><br></td>
        </tr>
        <tr class='form-row'>
            <td class='label' style='vertical-align:top;'>Keterangan/Alasan</td>
            <td class='value' colspan='3'>: " . nl2br(tampilkan($data['keterangan'] ?? '-', 70)) . "<br><br></td>
        </tr>
    </table>

    " . $signTable . "

    </body>
    </html>
    ";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfContent = $dompdf->output();

    if (isset($_GET['download']) && $_GET['download'] == '1') {
        $filename = "PERUBAHAN DATA DB - " . $ticket . ".pdf";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $pdfContent;
        exit;
    }

    ob_end_clean();
    $pdfBase64 = base64_encode($pdfContent);
    $ticketEsc = htmlspecialchars($ticket);
    $namaEsc   = htmlspecialchars($data['nama_pemohon']);
    $deptEsc   = htmlspecialchars($data['departemen']);

    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Preview PDF - Perubahan Data Database</title>
        <link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css'>
        <link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css'>
        <style>
            body { margin: 0; padding: 20px; background-color: #f4f4f4; }
            .container { max-width: 1200px; margin: 0 auto; background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .header { margin-bottom: 20px; }
            .header h2 { margin: 0 0 10px 0; color: #333; }
            .pdf-viewer { border: 2px solid #ddd; margin-bottom: 20px; }
            object { display: block; }
            .button-group { display: flex; gap: 10px; }
            .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
            .btn-download { background-color: #28a745; color: white; }
            .btn-download:hover { background-color: #218838; }
            .btn-back { background-color: #6c757d; color: white; }
            .btn-back:hover { background-color: #5a6268; }
            .ticket-info { background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px 15px; margin-bottom: 20px; }
            .ticket-info p { margin: 5px 0; }
        </style>
        <script>
            function downloadPDF() {
                var link = document.createElement('a');
                link.href = '?ticket=$ticketEsc&download=1';
                link.download = 'PERUBAHAN DATA DB - $ticketEsc.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        </script>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2><i class='fas fa-file-pdf'></i> Preview Pengajuan Perubahan Data Via Database</h2>
                <div class='ticket-info'>
                    <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
                    <p><strong>Pemohon:</strong> $namaEsc</p>
                    <p><strong>Departemen:</strong> $deptEsc</p>
                </div>
            </div>

            <div class='pdf-viewer'>
                <iframe src='data:application/pdf;base64,$pdfBase64' width='100%' height='800' title='PERUBAHAN DATA DB - $ticketEsc.pdf'></iframe>
            </div>

            <div class='button-group'>
                <a href='?ticket=$ticketEsc&download=1' class='btn btn-download'>
                    <i class='fas fa-download'></i> Download PDF
                </a>
                <a href='list_form.php' class='btn btn-back'>
                    <i class='fas fa-arrow-left'></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </body>
    </html>
    ";
    exit;

} catch (Exception $e) {
    ob_end_clean();
    $errMsg = htmlspecialchars($e->getMessage());
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8d7da; }
            .error { color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; max-width: 800px; margin: 0 auto; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Error Generating PDF</h2>
            <p><strong>$errMsg</strong></p>
            <p><a href='javascript:history.back()'>Kembali</a></p>
        </div>
    </body>
    </html>
    ";
    exit;
}
?>
