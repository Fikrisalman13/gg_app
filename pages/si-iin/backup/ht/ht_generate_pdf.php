<?php
/**
 * Generate & simpan PDF Serah Terima (HT) lalu redirect ke:
 * http://localhost:81/gg_app/pages/si-iin/ht/pdf/HT_{id}.pdf
 *
 * MODIFIKASI:
 * - Menambahkan kop surat (logo + alamat) sesuai gambar.
 * - Menambahkan kode dokumen (SUM-BLP-IT-004) di kanan bawah.
 * - Set default "Yang Menyerahkan" ke "IT1" jika tidak ada ttd file.
 * - Penyesuaian jarak kop surat dan kode dokumen.
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../_shared/siin_lib.php'; // $pdo (DB SIIN)

// Autoload Composer (mPDF)
$autoload = realpath(__DIR__ . '/../../../vendor/autoload.php');
if (!$autoload || !is_file($autoload)) {
    http_response_code(500);
    die("Autoload Composer tidak ditemukan. Pastikan vendor/autoload.php ada.");
}
require_once $autoload;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

/* ===================== PARAMETER ===================== */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('Parameter id tidak valid.');
}

/* ===================== QUERY HEADER ===================== */

$sqlH = "
    SELECT id, no_grn, ir_no, status, emp_name, dept_name, ht_date, sign_name, sign_image_path
    FROM siin_ht
    WHERE id = ?
";
$stH = $pdo->prepare($sqlH);
$stH->execute([$id]);
$H = $stH->fetch(PDO::FETCH_ASSOC);

if (!$H) {
    http_response_code(404);
    die('Data HT tidak ditemukan.');
}

/* ===================== QUERY ITEMS ===================== */

$sqlI = "
    SELECT
      p.prod_code,
      p.prod_name,
      COALESCE(u.uomname, '-') AS uomname,
      i.qty
    FROM siin_ht_item i
    JOIN siin_product p ON p.id = i.product_id
    LEFT JOIN siin_uom u ON u.id = p.uom_id
    WHERE i.ht_id = ?
";
$stI = $pdo->prepare($sqlI);
$stI->execute([$id]);
$rows = $stI->fetchAll(PDO::FETCH_ASSOC);

/* ===================== HELPER FUNCTIONS ===================== */

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function indoDate(?string $dateStr): string {
    if (!$dateStr) return '-';
    $ts = strtotime($dateStr);
    if ($ts === false) return h($dateStr);

    static $bln = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    // Format diubah menjadi "14 November 2025" (Sesuai gambar)
    return date('d', $ts).' '.$bln[(int)date('m', $ts)].' '.date('Y', $ts);
}

/**
 * Kalau emp_name di tabel berisi ID (angka), ambil nama_lengkap dari m_emp (DB HR).
 * Kalau sudah berupa nama, langsung dikembalikan.
 */
function resolveEmpName($raw, $pdo_hr = null, $hr_res = null): string {
    $val = trim((string)$raw);
    if ($val === '') return '';
    if (!ctype_digit($val)) return $val; // sudah nama, bukan ID
    $id_emp = (int)$val;

    // PDO SQLSRV (mis: $pdo_mssql)
    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?");
        if ($st && $st->execute([$id_emp])) {
            $nm = $st->fetchColumn();
            if ($nm) return (string)$nm;
        }
    }

    // Resource sqlsrv (mis: $conn)
    if ($hr_res) {
        $sql  = "SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?";
        $stmt = sqlsrv_query($hr_res, $sql, [$id_emp]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['nama_lengkap'])) return (string)$row['nama_lengkap'];
            sqlsrv_free_stmt($stmt);
        }
    }

    // fallback: tetap tampilkan ID
    return $val;
}

/* ===================== TENTUKAN KONEKSI HR (optional, jika mau pakai resolveEmpName) ===================== */

$pdo_hr  = null;
$HRCONN  = null;

$KON = realpath(__DIR__ . '/../../../koneksi.php');
if ($KON) {
    require_once $KON;
    if (isset($pdo_mssql) && $pdo_mssql instanceof PDO) {
        $pdo_hr = $pdo_mssql;
    } elseif (isset($pdo) && $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $pdo_hr = $pdo;
    }
    if (isset($conn)) {
        $HRCONN = $conn;
    } elseif (isset($sqlsrvConn)) {
        $HRCONN = $sqlsrvConn;
    }
}

/* ===================== DATA UNTUK TAMPILAN ===================== */

$no_grn_raw = (string)($H['no_grn'] ?? '');
$no_grn     = h($no_grn_raw);
$ir_no      = h($H['ir_no']);
$status     = h($H['status']);
$emp_name   = h(resolveEmpName($H['emp_name'], $pdo_hr, $HRCONN));
$dept       = h($H['dept_name']);
$ht_date    = indoDate($H['ht_date']);
$sign_name  = trim((string)($H['sign_name'] ?? ''));
$logo_url   = 'http://192.168.7.184:8080/gg_app/dist/img/sumlogo.png';

/* ===================== TANDA TANGAN: Yang Menerima (existing) ===================== */

/**
 * Prioritas lokasi file:
 * 1) /pages/si-iin/ht/esign/ht_{id}.png
 * 2) path dari sign_image_path (kalau diawali /gg_app/pages/si-iin/ht/esign/)
 * 3) fallback /gg_app/esign/ht_{id}.png
 */
$recvSignFs = __DIR__ . '/esign/ht_' . $id . '.png';
if (!is_file($recvSignFs)) {
    $signUrl = $H['sign_image_path'] ?? '';
    if ($signUrl && strpos($signUrl, '/gg_app/pages/si-iin/ht/esign/') === 0) {
        $try = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '\\/') . $signUrl;
        if (is_file($try)) {
            $recvSignFs = $try;
        }
    } else {
        $fallback = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '\\/') . '/gg_app/esign/ht_' . $id . '.png';
        if (is_file($fallback)) {
            $recvSignFs = $fallback;
        }
    }
}
$recvHasSign = is_file($recvSignFs);

/* ===================== TANDA TANGAN: Yang Menyerahkan (baru) ===================== */

/**
 * Disimpan di:
 * /pages/si-iin/ht/esign/menyerahkan/SAFE_NO_GRN__SAFE_NAMA.png
 * Contoh nama file:
 * GRN_1125_3__SIDIQ_PERMANA.png
 * * PDF akan:
 * - cari file berdasarkan SAFE_NO_GRN__*.png
 * - nama yang menyerahkan diambil dari bagian setelah "__"
 * - JIKA TIDAK KETEMU, default nama "IT1" (sesuai gambar)
 */
$senderSignFs  = '';
$senderHasSign = false;
$senderName    = '';

$senderDir = __DIR__ . '/esign/menyerahkan';
$safeNoGrn = preg_replace('/[^A-Za-z0-9_\-]/', '_', $no_grn_raw);

if (is_dir($senderDir) && $safeNoGrn !== '') {
    $pattern = $senderDir . '/' . $safeNoGrn . '__*.png';
    $files   = glob($pattern);
    if ($files && count($files) > 0) {
        $senderSignFs  = $files[0];
        $senderHasSign = is_file($senderSignFs);

        // Ekstrak nama dari nama file (bagian setelah "__")
        $base  = basename($senderSignFs, '.png');
        $parts = explode('__', $base, 2);
        if (isset($parts[1])) {
            $senderName = str_replace('_', ' ', $parts[1]);
        }
    } else {
        // MODIFIKASI: Jika tidak ada file ttd, set nama default 'IT1'
        $senderName = 'IT1';
    }
} else {
    // MODIFIKASI: Jika direktori tidak ada, set nama default 'IT1'
    $senderName = 'IT1';
}

// Pastikan $senderName tidak kosong jika $sign_name ada
if ($senderName === '' && !$senderHasSign) {
    $senderName = 'IT1';
}


/* ===================== CSS PDF ===================== */

$css = <<<CSS
@page { margin: 18mm 16mm 18mm 16mm; }
body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10pt;
  color:#111;
}

/* ===== KOP SURAT ===== */
.header-table {
  width: 100%;
  border-bottom: 1px solid #000;
  padding-bottom: 2mm;
}
.header-table td {
  vertical-align: top;
}
.logo-img {
  width: 18mm;
  height: auto;
}
.kop-surat {
  text-align: left;
  padding-left: 0.5mm; /* Mengurangi padding agar lebih dekat ke logo */
}
.kop-surat h2 {
  font-size: 14pt;
  font-weight: bold;
  margin: 0;
  padding: 0;
}
.kop-surat p {
  font-size: 10pt;
  margin: 1mm 0 0 0;
  padding: 0;
  line-height: 1.4;
}

h3.title {
  font-size: 12pt;
  font-weight: bold;
  margin: 4mm 0 8pt 0; /* Beri jarak dari kop surat */
  text-align: center;
}
table { border-collapse: collapse; }

.meta {
  width: 100%;
  margin-bottom: 8pt;
}
.meta td {
  padding: 3pt 4pt;
  vertical-align: top;
}
.meta .lbl {
  font-weight: bold;
  white-space: nowrap;
}

.items {
  width: 100%;
  border: 0.6pt solid #888;
}
.items th {
  background: #f2f2f2;
  border: 0.6pt solid #888;
  padding: 5pt;
  font-weight: bold;
}
.items td {
  border: 0.6pt solid #888;
  padding: 5pt;
}
.t-right  { text-align: right; }
.t-center { text-align: center; }

/* ===== Signature block DI LUAR tabel items ===== */
.sig-table { width:100%; table-layout:fixed; margin-top:6mm; border:none; }
.sig-table td { border:none; padding:0; vertical-align: top; }
.sig-sender { width:20%; text-align:center; padding:0; } /* sejajar kolom Kode (20%) */
.sig-space  { width:60%; }                               /* ruang tengah */
.sig-recv   { width:20%; text-align:center; padding:0; } /* sejajar kolom Qty (20%) */

.sig-label { font-size:10pt; font-weight:normal; display:block; margin-bottom:2mm; }
.sig-img   { height:12mm; display:block; margin:1mm auto 0 auto; }
.sig-name  { font-size:10pt; font-weight:normal; margin-top:12mm; } /* Beri jarak untuk ttd */

/* ===== KODE DOKUMEN ===== */
.doc-code-container {
  position: absolute;
  top: 12mm; /* adjust to sit below page margin/header */
  right: 16mm; /* align to page right margin */
  font-size: 9pt;
  font-weight: bold;
  color: #000;
  background: transparent;
  padding: 0;
  border: none; /* removed border per request */
}
.doc-code {
  border: none;
  padding: 0;
  display: block;
}
CSS;

/* ===================== BUILD HTML ===================== */

// KOP SURAT
$html = '
<table class="header-table">
  <tr>
    <td style="width: 20%;">
      <img src="'.h($logo_url).'" class="logo-img" alt="Logo" />
    </td>
    <td style="width: 80%;" class="kop-surat">
      <h2>PT. SURYA USAHA MANDIRI</h2>
      <p>
        Jl. Tarajusari No. 9 Kp. Cipeundeuy RT 001 RW 007<br>
        Banjaran - Kab. Bandung<br>
        40377 Telp. (022) 594-0813
      </p>
    </td>
  </tr>
</table>';

// Kode dokumen: letakkan di pojok kanan atas, tanpa border
$html .= '<div class="doc-code-container">SUM-FM-IT-036</div>';

$html .= '<h3 class="title">Serah Terima Barang</h3>';

$html .= '<table class="meta">
  <tr>
    <td class="lbl" width="20%">No GRN</td><td width="30%">: ' . $no_grn   . '</td>
    <td class="lbl" width="20%">Tanggal</td><td width="30%">: ' . $ht_date  . '</td>
  </tr>
  <tr>
    <td class="lbl">Nomor IR</td><td>: ' . $ir_no    . '</td>
    <td class="lbl">Status</td><td>: ' . $status   . '</td>
  </tr>
  <tr>
    <td class="lbl">Karyawan</td><td>: ' . $emp_name . '</td>
    <td class="lbl">Departemen</td><td>: ' . $dept     . '</td>
  </tr>
</table>';

/* --- Tabel items --- */
$html .= '<table class="items">
  <thead>
    <tr>
      <th style="width:20%">Product Code</th>
      <th style="width:50%">Product Name</th>
      <th style="width:10%">UOM</th>
      <th style="width:20%">Qty</th>
    </tr>
  </thead>
  <tbody>';

if (!$rows) {
    $html .= '<tr><td colspan="4" class="t-center">Tidak ada item.</td></tr>';
} else {
    foreach ($rows as $r) {
        $qty = number_format((float)$r['qty'], 2, ',', '.');
        $html .= '<tr>
          <td>'.h($r['prod_code']).'</td>
          <td>'.h($r['prod_name']).'</td>
          <td>'.h($r['uomname']).'</td>
          <td class="t-right">'.$qty.'</td>
        </tr>';
    }
}
$html .= '</tbody></table>';

/* --- Blok tanda tangan di bawah tabel, sejajar: Menyerahkan (kiri) & Menerima (kanan) --- */
$html .= '
<table class="sig-table">
  <tr>
    <!-- Yang Menyerahkan (sejajar Kode: width 20%) --><td class="sig-sender">
      <span class="sig-label">Yang Menyerahkan</span>';
if ($senderHasSign) {
    $html .= '<img class="sig-img" src="'.h($senderSignFs).'" />';
} else {
    // Kosongkan area ttd jika tidak ada gambar
    $html .= '<div style="height: 12mm;"></div>';
}
// Selalu tampilkan nama (Baik dari file atau default 'IT1')
if ($senderName !== '') {
    $html .= '<div class="sig-name">'.h($senderName).'</div>';
}
$html .= '    </td>

    <!-- Ruang kosong tengah (Nama + UOM: width 60%) --><td class="sig-space"></td>

    <!-- Yang Menerima (sejajar Qty: width 20%) --><td class="sig-recv">
      <span class="sig-label">Yang Menerima</span>';
if ($recvHasSign) {
    $html .= '<img class="sig-img" src="'.h($recvSignFs).'" />';
} else {
    // Kosongkan area ttd jika tidak ada gambar
    $html .= '<div style="height: 12mm;"></div>';
}
if ($sign_name !== '') {
    $html .= '<div class="sig-name">'.h($sign_name).'</div>';
}

// KODE DOKUMEN (BARU) -- already placed at top-right; nothing to add here

$html .= '    </td>
  </tr>
</table>';

/* ===================== GENERATE PDF (mPDF) ===================== */

try {
    $mpdf = new Mpdf([
      'format'            => 'A4',
      'orientation'       => 'P',
      'tempDir'           => sys_get_temp_dir(),
      'default_font'      => 'Arial',
      'default_font_size' => 10,
      'allow_remote_images' => true,
    ]);
} catch (\Mpdf\MpdfException $e) {
    // Kalau di sini error "mbstring extension is required", berarti mbstring memang belum aktif di PHP
    http_response_code(500);
    echo '<h2>Gagal inisialisasi mPDF</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p>Jika pesan error menyebut <strong>mbstring</strong>, aktifkan ekstensi mbstring di <code>php.ini</code> lalu restart Apache/XAMPP.</p>';
    exit;
}

$mpdf->WriteHTML('<style>'.$css.'</style>', HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

/* ===================== SIMPAN & REDIRECT ===================== */

$saveDirFs = __DIR__ . '/pdf';
if (!is_dir($saveDirFs)) {
    if (!mkdir($saveDirFs, 0775, true) && !is_dir($saveDirFs)) {
        http_response_code(500);
        die('Gagal membuat folder: ' . $saveDirFs);
    }
}

$filename = "HT_{$id}.pdf";
$fullPath = $saveDirFs . DIRECTORY_SEPARATOR . $filename;

// Simpan sebagai file di server
$mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

// Redirect ke URL file
header('Cache-Control: no-store');
header('Location: http://192.168.7.184:8080/gg_app/pages/si-iin/ht/pdf/' . rawurlencode($filename));
exit;