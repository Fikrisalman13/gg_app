<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil filter dari query string
$filterDept   = $_GET['filterDept']   ?? '';
$filterBag    = $_GET['filterBag']    ?? '';
$filterSubbag = $_GET['filterSubbag'] ?? '';
$filterJab    = $_GET['filterJab']    ?? '';
$filterStatus = $_GET['filterStatus'] ?? '';
$filterGol    = $_GET['filterGol']    ?? '';

// Buat kondisi WHERE
$where = " WHERE 1=1 ";
$params = [];

if ($filterDept !== '') {
    $where .= " AND e.id_dept = ? ";
    $params[] = $filterDept;
}
if ($filterBag !== '') {
    $where .= " AND e.id_bag = ? ";
    $params[] = $filterBag;
}
if ($filterSubbag !== '') {
    $where .= " AND e.id_subbag = ? ";
    $params[] = $filterSubbag;
}
if ($filterJab !== '') {
    $where .= " AND e.id_jab = ? ";
    $params[] = $filterJab;
}
if ($filterStatus !== '') {
    $where .= " AND e.aktif = ? ";
    $params[] = $filterStatus;
}
if ($filterGol !== '') {
    $where .= " AND e.id_gol = ? ";
    $params[] = $filterGol;
}

// Query data
$sql = "SELECT e.nik, e.nama_lengkap, 
               CASE WHEN e.aktif=1 THEN 'Aktif' ELSE 'Nonaktif' END AS status,
               d.dept, b.bagian, s.subbag, j.jabatan, g.golongan
        FROM m_emp e
        LEFT JOIN m_dept d ON e.id_dept = d.id_dept
        LEFT JOIN m_bag b ON e.id_bag = b.id_bag
        LEFT JOIN m_subbag s ON e.id_subbag = s.id_subbag
        LEFT JOIN m_jab j ON e.id_jab = j.id_jab
        LEFT JOIN m_gol g ON e.id_gol = g.id_gol
        $where
        ORDER BY e.nama_lengkap ASC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
}

// === KONFIGURASI PDF ===
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GG App');
$pdf->SetAuthor('IT Dept');
$pdf->SetTitle('Laporan Data Karyawan');
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 20);

// Header dan Footer
$pdf->setHeaderFont(Array('helvetica', '', 9));
$pdf->setFooterFont(Array('helvetica', '', 9));

// Menambahkan halaman
$pdf->AddPage();

// Set font default
$pdf->SetFont('helvetica', '', 9);

// === HEADER ===
// Nama Perusahaan
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(30, 30, 120);
$pdf->Cell(0, 15, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');

// Judul Laporan
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 15, 'LAPORAN DATA KARYAWAN', 0, 1, 'C');
$pdf->Ln(5);

// Informasi filter
$pdf->SetFont('helvetica', '', 10);
$filterInfo = [];
if ($filterDept !== '') {
    $deptName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT dept FROM m_dept WHERE id_dept = ?", [$filterDept]));
    $filterInfo[] = "Departemen: " . $deptName['dept'];
}
if ($filterBag !== '') {
    $bagName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT bagian FROM m_bag WHERE id_bag = ?", [$filterBag]));
    $filterInfo[] = "Bagian: " . $bagName['bagian'];
}
if ($filterSubbag !== '') {
    $subbagName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT subbag FROM m_subbag WHERE id_subbag = ?", [$filterSubbag]));
    $filterInfo[] = "Subbag: " . $subbagName['subbag'];
}
if ($filterJab !== '') {
    $jabName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT jabatan FROM m_jab WHERE id_jab = ?", [$filterJab]));
    $filterInfo[] = "Jabatan: " . $jabName['jabatan'];
}
if ($filterStatus !== '') {
    $statusText = ($filterStatus == 1) ? "Aktif" : "Nonaktif";
    $filterInfo[] = "Status: " . $statusText;
}
if ($filterGol !== '') {
    $golName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT golongan FROM m_gol WHERE id_gol = ?", [$filterGol]));
    $filterInfo[] = "Golongan: " . $golName['golongan'];
}

if (!empty($filterInfo)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 7, 'Filter Terpasang:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 7, implode("; ", $filterInfo), 0, 'L', false);
    $pdf->Ln(2);
} else {
    $pdf->Cell(0, 7, "Filter: Semua Data", 0, 1, 'L');
}

// Tanggal cetak
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . $_SESSION['UserName'], 0, 1, 'L');
$pdf->Ln(3);

// === LEBAR KOLOM ===
$maxWidths = [
    'no'      => 12,
    'nik'     => 25,
    'nama'    => 40,
    'status'  => 20,
    'dept'    => 40,
    'bagian'  => 40,
    'subbag'  => 50,
    'jabatan' => 20,
    'gol'     => 20
];

// Ambil data ke array
$dataRows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = $row;
}

// === FUNGSI CETAK TABEL ===
function printTableHeader($pdf, $maxWidths) {
    $pdf->SetFillColor(60, 100, 150);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(80, 80, 80);

    $headers = [
        'no'      => 'NO',
        'nik'     => 'NIK',
        'nama'    => 'NAMA LENGKAP',
        'status'  => 'STATUS',
        'dept'    => 'DEPARTEMEN',
        'bagian'  => 'BAGIAN',
        'subbag'  => 'SUBBAGIAN',
        'jabatan' => 'JABATAN',
        'gol'     => 'GOL'
    ];

    foreach ($headers as $key => $label) {
        $pdf->MultiCell($maxWidths[$key], 8, $label, 1, 'C', true, 0, '', '', true);
    }
    $pdf->Ln();

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
}

function printTableRow($pdf, $row, $no, $maxWidths, $fill) {
    if ($fill) {
        $pdf->SetFillColor(240, 245, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $cells = [
        'no'      => $no,
        'nik'     => $row['nik'],
        'nama'    => $row['nama_lengkap'],
        'status'  => $row['status'],
        'dept'    => $row['dept'],
        'bagian'  => $row['bagian'],
        'subbag'  => $row['subbag'],
        'jabatan' => $row['jabatan'],
        'gol'     => $row['golongan']
    ];

    $rowHeights = [];
    foreach ($cells as $key => $val) {
        $rowHeights[] = $pdf->getStringHeight($maxWidths[$key], $val, false, true, '', 1);
    }
    $rowHeight = max($rowHeights);

    foreach ($cells as $key => $val) {
        $align = in_array($key, ['no','nik','status']) ? 'C' : 'L';
        $pdf->MultiCell($maxWidths[$key], $rowHeight, $val, 1, $align, true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
    }
    $pdf->Ln();

    return !$fill;
}

// Cetak header tabel pertama
printTableHeader($pdf, $maxWidths);

// Isi tabel
$fill = false;
$no = 1;
foreach ($dataRows as $row) {
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        printTableHeader($pdf, $maxWidths);
        $fill = false;
    }
    $fill = printTableRow($pdf, $row, $no++, $maxWidths, $fill);
}

// Jumlah data
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 10, 'Total Data: ' . count($dataRows), 0, 1, 'R');

// Footer
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Dokumen ini dicetak secara otomatis dari Sistem HRD', 0, 1, 'C');
$pdf->Cell(0, 5, 'Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages(), 0, 0, 'C');

// Output PDF
$pdf->Output('Laporan_Karyawan_' . date('Ymd_His') . '.pdf', 'I');
?>
