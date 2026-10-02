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
    $sql = "SELECT * FROM Form_Pengajuan_Barang WHERE ticket = ?";
    $params = [$ticket];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        throw new Exception("Data pengajuan dengan ticket '$ticket' tidak ditemukan!");
    }

    $tgl_pengajuan = $row['tgl_pengajuan'] instanceof DateTime 
        ? $row['tgl_pengajuan']->format('d-m-Y') 
        : '-';

    $pengajuan_list = [];
    $peripheral_list = [];
    
    if (!empty($row['pengajuan'])) {
        $pengajuan_decoded = json_decode($row['pengajuan'], true);
        if (is_array($pengajuan_decoded)) {
            $pengajuan_list = $pengajuan_decoded;
        }
    }
    
    if (!empty($row['peripheral'])) {
        $peripheral_decoded = json_decode($row['peripheral'], true);
        if (is_array($peripheral_decoded)) {
            $peripheral_list = $peripheral_decoded;
        }
    }

    function tampilkan($text, $panjang = 35) {
        $text = trim($text ?? '');
        if ($text === '') {
            return "<span style='display:inline-block; border-bottom:0.5px solid #000; width:{$panjang}ch;'>&nbsp;</span>";
        } else {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    function drawCheckbox($label, $selected) {
        $checked = is_array($selected) && in_array($label, $selected);
        if ($checked) {
            $box = "<span style='display:inline-block;width:10px;height:10px;border:1px solid #000;background:#000;position:relative;margin-right:4px;'><span style='position:absolute;left:2px;top:2px;width:2px;height:4px;border-right:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(45deg);'></span></span>";
        } else {
            $box = "<span style='display:inline-block;width:10px;height:10px;border:1px solid #000;margin-right:4px;'></span>";
        }
        return $box . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }

    function renderCheckboxLine($items, $selected) {
        $result = array();
        foreach ($items as $item) {
            $result[] = drawCheckbox($item, $selected);
        }
        return implode('&nbsp;&nbsp;', $result);
    }

    $logoPath = "../../dist/img/sumlogo.png";
    $logoBase64 = "";
    if (file_exists($logoPath)) {
        $logoBase64 = base64_encode(file_get_contents($logoPath));
    }
    $logoSrc = "data:image/png;base64," . $logoBase64;

    function renderCheckboxGroupWithQty($items, $selected, $data, $prefix) {
        $result = '';
        if (!is_array($selected)) {
            $selected = [];
        }
        // normalize selected for case-insensitive matching
        $selectedNorm = array_map(function($v){ return strtolower(trim($v)); }, $selected);

        // prepare a small SVG check image (base64) to avoid charset issues
        $svgChecked = base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" width="12" height="12"><polyline points="2,7 5,10 10,3" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
        foreach ($items as $item) {
            $key = $prefix . strtolower(str_replace(' ', '_', $item));
            $qty = intval($data[$key] ?? 0);
            $isChecked = ($qty > 0) || in_array(strtolower($item), $selectedNorm);

            // Render a single bordered box element; if checked, embed base64 SVG checkmark
            if ($isChecked) {
                $box = "<span style='display:inline-block;width:14px;height:14px;border:1px solid #000;background:#fff;margin-right:6px;vertical-align:middle;'>";
                $box .= "<img src='data:image/svg+xml;base64,$svgChecked' style='display:block;width:12px;height:12px;margin:0 auto;' alt=''/>";
                $box .= "</span>";
            } else {
                $box = "<span style='display:inline-block;width:14px;height:14px;border:1px solid #000;background:#fff;margin-right:6px;vertical-align:middle;'></span>";
            }

            $ket = '';
            if (strtolower($item) === 'lainnya') {
                $ketKey = ($prefix === 'qty_perip_') ? 'ket_perip_lainnya' : 'ket_lainnya';
                if (!empty($data[$ketKey])) {
                    $ket = $data[$ketKey] . ' ';
                }
            }
            $qtyText = ($isChecked && $qty > 0) ? " ( {$ket}{$qty} Unit )" : '';
            $result .= $box . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . $qtyText . "&nbsp;&nbsp;&nbsp; ";
        }
        return $result;
    }
    $itemsPengajuan = ['Komputer','Laptop','Tablet','Handphone','Lainnya'];
    $itemsPeripheral = ['Keyboard','Mouse','Monitor','Printer','Scanner','Lainnya'];
    $pengajuan_html = renderCheckboxGroupWithQty($itemsPengajuan, $pengajuan_list, $row, 'qty_');
    $peripheral_html = renderCheckboxGroupWithQty($itemsPeripheral, $peripheral_list, $row, 'qty_perip_');
    $total_pengajuan = 0; foreach ($itemsPengajuan as $it){ $k='qty_'.strtolower(str_replace(' ','_',$it)); $total_pengajuan += intval($row[$k] ?? 0); }
    $total_peripheral = 0; foreach ($itemsPeripheral as $it){ $k='qty_perip_'.strtolower(str_replace(' ','_',$it)); $total_peripheral += intval($row[$k] ?? 0); }
    $qty_total = $total_pengajuan + $total_peripheral;

    // Ambil tanda tangan dari tabel Form_Pengajuan_Barang_TTD
    $ttd = [];
    $sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
    $stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
    if ($stmtTtd) {
        while ($rT = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
            $roleName = $rT['GroupRole'];
            $ttd[$roleName] = $rT;
        }
    }

    // Bangun tabel tanda tangan dinamis
  $roles = ['Pemohon','Atasan Pemohon','Petugas IT','Kabag IT','Kadept IT'];

    $signTable = "<!-- ====== TANDA TANGAN + PERHATIAN + FOOTER (FINAL) ====== -->\n";
    $signTable .= "<table width='100%' style='border-collapse: collapse; border:1px solid #000;'>\n";
    // Header: first three separate labels, then a combined 'Disetujui oleh,' spanning two columns
    $signTable .= "<tr class='center bold' style='height:25px;'>";
    $signTable .= "<td style='border:1px solid #000;'>Di Ajukan Oleh</td>";
    $signTable .= "<td style='border:1px solid #000;'>Mengetahui</td>";
    $signTable .= "<td style='border:1px solid #000;'>Di Ketahui Oleh</td>";
    $signTable .= "<td colspan='2' width='40%' style='border:1px solid #000;'>Di Setujui Oleh</td>";
    $signTable .= "</tr>\n";
    // Signature row
    $signTable .= "<tr style='height:60px;' class='sign-row'>";
    foreach ($roles as $r) {
        $sigHtml = '&nbsp;';
        if (isset($ttd[$r]) && !empty($ttd[$r]['SignaturePath'])) {
            $sigPath = $ttd[$r]['SignaturePath'];
            $found = false;
            // Try absolute path as stored
            if (file_exists($sigPath)) { 
                $absolute = $sigPath;
                $found = true;
            }
            // Try document root + path
            if (!$found && isset($_SERVER['DOCUMENT_ROOT'])) {
                $docPath = rtrim($_SERVER['DOCUMENT_ROOT'], '\\/') . $sigPath;
                if (file_exists($docPath)) {
                    $absolute = $docPath;
                    $found = true;
                }
            }
            // Try relative to project root
            if (!$found) {
                $rel = __DIR__ . '/../../' . ltrim($sigPath, '/\\');
                if (file_exists($rel)) {
                    $absolute = $rel;
                    $found = true;
                }
            }

            if (!empty($found) && file_exists($absolute)) {
                $img = base64_encode(file_get_contents($absolute));
                $signedBy = htmlspecialchars($ttd[$r]['SignedByUserName'] ?? '');
                $sigHtml = "<img src='data:image/png;base64,$img' style='max-height:50px; display:block;margin:0 auto;'><br><small>$signedBy</small>";
            } else {
                // Fallback: use the path as URL (as detail_tiket.php does)
                $signedBy = htmlspecialchars($ttd[$r]['SignedByUserName'] ?? '');
                $sigUrl = htmlspecialchars($sigPath);
                $sigHtml = "<img src='{$sigUrl}' style='max-height:50px; display:block;margin:0 auto; object-fit:contain;'><br><small>$signedBy</small>";
            }
        }
        $signTable .= "<td style='border:1px solid #000;text-align:center;'>" . $sigHtml . "</td>";
    }
    $signTable .= "</tr>\n";

    // Label row
    $signTable .= "<tr class='center bold' style='font-size:9pt; height:22px;'>";
    foreach ($roles as $r) {
        $signTable .= "<td style='border:1px solid #000;'>" . htmlspecialchars($r) . "</td>";
    }
    $signTable .= "</tr>\n";

    // Perhatian and footer
    $signTable .= "<tr>\n<td colspan='5' style='border:1px solid #000; font-size:9pt; padding:6px 8px;'>\n<b>Perhatian :</b><br>\n<ol style='margin:4px 0 0 18px; padding:0;'>\n<li>Untuk spesifikasi ditentukan IT mengikuti standar.</li>\n<li>Untuk jenis perangkat dengan spesifikasi di luar standar, harus mendapatkan approval dari Direksi.</li>\n</ol>\n</td>\n</tr>\n";
    $signTable .= "<tr>\n<td colspan='4' style='border:1px solid #000; text-align:center; font-weight:bold;'>SUM-FM-IT-014</td>\n<td style='border:1px solid #000; text-align:center; font-weight:bold;'>HW</td>\n</tr>\n";
    $signTable .= "</table>\n";

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
        .sign-row td { height: 50px; vertical-align: bottom; text-align: center; }
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
            <td colspan='2' class='center bold' style='border-top:1px solid #000; border-bottom:1px solid #000;'>PENGAJUAN PERANGKAT IT</td>
        </tr>
    </table>

    <!-- Form utama -->
    <table class='border' style='border-top:none;'>

        <!-- Nama Pemohon -->
        <tr class='form-row'>
            <td class='label'>Nama Pemohon</td>
            <td class='value' colspan='3'>: ".tampilkan($row['nama_pemohon'])."</td>
        </tr>

        <!-- Jabatan dan Tgl Pengajuan dalam satu baris -->
        <tr class='form-row'>
            <td class='label' style='width: 35%;'>Jabatan</td>
            <td class='value' style='width: 35%;'>: ".tampilkan($row['jabatan'])."</td>
            <td class='label' style='text-align:right; width: 25%;'>Tgl Pengajuan</td>
            <td class='value' style='text-align:right; width: 25%;'>: ".tampilkan($tgl_pengajuan, 15)."</td>
        </tr>

        <!-- Departemen -->
        <tr class='form-row'>
            <td class='label'>Departemen</td>
            <td class='value' colspan='3'>: ".tampilkan($row['departemen'])."</td>
        </tr>

        <!-- Bagian -->
        <tr class='form-row'>
            <td class='label'>Bagian</td>
            <td colspan='3' class='value'>: ".tampilkan($row['bagian'])."</td>
        </tr>

        <!-- Pengajuan -->
        <tr class='form-row'>
            <td class='label'>Pengajuan</td>
            <td colspan='3'>: ".$pengajuan_html."</td>
        </tr>
      
        <!-- Spesifikasi -->
        <tr class='form-row'>
            <td class='label'>Spesifikasi Khusus</td>
            <td class='value' colspan='3'>: ".tampilkan($row['spesifikasi'], 70)."</td>
        </tr>

        <!-- Peripheral -->
        <tr class='form-row'>
            <td class='label'>Peripheral</td>
            <td colspan='3'>: ".$peripheral_html."</td>
        </tr>
       
        <!-- Total -->
        <tr class='form-row'>
            <td class='label'>Total Qty</td>
            <td class='value' colspan='3'>: <b>".$qty_total." Unit</b></td>
        </tr>

        <!-- Keterangan -->
        <tr class='form-row'>
            <td class='label'>Keterangan</td>
            <td colspan='3' class='value'>: ".tampilkan($row['keterangan'], 70)."<br><br><br></td>
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
        $filename = "PENGAJUAN PERANGKAT IT - " . $ticket . ".pdf";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $pdfContent;
        exit;
    }

    ob_end_clean();
    $pdfBase64 = base64_encode($pdfContent);
    $ticketEsc = htmlspecialchars($ticket);
    $namaEsc = htmlspecialchars($row['nama_pemohon']);
    $deptEsc = htmlspecialchars($row['departemen']);

    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Preview PDF - Pengajuan IT</title>
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
                var ticket = '$ticketEsc';
                var link = document.createElement('a');
                link.href = '?ticket=' + ticket + '&download=1';
                link.download = 'PENGAJUAN PERANGKAT IT - ' + ticket + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        </script>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2><i class='fas fa-file-pdf'></i> Preview Pengajuan Perangkat IT</h2>
                <div class='ticket-info'>
                    <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
                    <p><strong>Pemohon:</strong> $namaEsc</p>
                    <p><strong>Departemen:</strong> $deptEsc</p>
                </div>
            </div>

            <div class='pdf-viewer'>
                <iframe src='data:application/pdf;base64,$pdfBase64' width='100%' height='800' title='PENGAJUAN PERANGKAT IT - $ticketEsc.pdf'></iframe>
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
