<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil filter dari query string
$kategori = $_GET['kategori'] ?? '';
$dept     = $_GET['dept']     ?? '';
$bagian   = $_GET['bagian']   ?? '';
$subbag   = $_GET['subbag']   ?? '';

// Buat kondisi WHERE
$where = " WHERE 1=1 ";
$params = [];

if ($kategori !== '') {
    $where .= " AND d.id_kategori = ? ";
    $params[] = $kategori;
}
if ($dept !== '') {
    $where .= " AND d.id_dept = ? ";
    $params[] = $dept;
}
if ($bagian !== '') {
    $where .= " AND d.id_bag = ? ";
    $params[] = $bagian;
}
if ($subbag !== '') {
    $where .= " AND d.id_subbag = ? ";
    $params[] = $subbag;
}

// Query data dokumen
$sql = "SELECT 
    d.id_dok,
    d.kode_dok_seq,
    d.revisi,
    d.tanggal_upload,
    d.tanggal_revisi,
    d.tanggal_terbit,
    d.nama_dokumen,
    d.file_pdf,
    d.deskripsi,
    d.upduser,
    kd.kode_dok,
    k.nama_kategori,
    m_dept.dept,
    m_bag.bagian,
    m_subbag.subbag,
    CONCAT(kd.kode_dok, '-', d.kode_dok_seq) as no_dokumen
FROM dbo.dokumen AS d
LEFT JOIN dbo.m_kode_dok AS kd ON d.id_kode_dok = kd.id_kode_dok
LEFT JOIN dbo.m_kategori_dok AS k ON d.id_kategori = k.id_kategori
LEFT JOIN dbo.m_dept ON d.id_dept = m_dept.id_dept
LEFT JOIN dbo.m_bag ON d.id_bag = m_bag.id_bag
LEFT JOIN dbo.m_subbag ON d.id_subbag = m_subbag.id_subbag
$where
ORDER BY d.tanggal_upload DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
}

// === KONFIGURASI PDF ===
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GG App');
$pdf->SetAuthor('Sistem Dokumen');
$pdf->SetTitle('Laporan Data Dokumen ISO');
$pdf->SetMargins(10, 25, 10);
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
$pdf->Cell(0, 15, 'LAPORAN DATA DOKUMEN ISO', 0, 1, 'C');
$pdf->Ln(5);

// Informasi filter
$pdf->SetFont('helvetica', '', 10);
$filterInfo = [];

if ($kategori !== '') {
    $kategoriName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT nama_kategori FROM m_kategori_dok WHERE id_kategori = ?", [$kategori]));
    $filterInfo[] = "Kategori: " . ($kategoriName['nama_kategori'] ?? '');
}

if ($dept !== '') {
    $deptName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT dept FROM m_dept WHERE id_dept = ?", [$dept]));
    $filterInfo[] = "Departemen: " . ($deptName['dept'] ?? '');
}

if ($bagian !== '') {
    $bagName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT bagian FROM m_bag WHERE id_bag = ?", [$bagian]));
    $filterInfo[] = "Bagian: " . ($bagName['bagian'] ?? '');
}

if ($subbag !== '') {
    $subbagName = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT subbag FROM m_subbag WHERE id_subbag = ?", [$subbag]));
    $filterInfo[] = "Subbag: " . ($subbagName['subbag'] ?? '');
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
    'no'        => 12,
    'no_dokumen' => 35,
    'nama_dokumen' => 50,
    'kategori'  => 30,
    'revisi'    => 20,
    'dept_bagian' => 45,
    'tgl_upload' => 25,
    'deskripsi' => 45,
    'uploader'  => 18
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
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(80, 80, 80);

    $headers = [
        'no'          => 'NO',
        'no_dokumen'  => 'NO DOKUMEN',
        'nama_dokumen' => 'NAMA DOKUMEN',
        'kategori'    => 'KATEGORI',
        'revisi'      => 'REVISI',
        'dept_bagian' => 'DEPT/BAGIAN',
        'tgl_upload'  => 'TGL UPLOAD',
        'deskripsi'   => 'DESKRIPSI',
        'uploader'    => 'UPLOADER'
    ];

    foreach ($headers as $key => $label) {
        $pdf->MultiCell($maxWidths[$key], 8, $label, 1, 'C', true, 0, '', '', true);
    }
    $pdf->Ln();

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
}

function printTableRow($pdf, $row, $no, $maxWidths, $fill) {
    if ($fill) {
        $pdf->SetFillColor(240, 245, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    // Format tanggal
    $tgl_upload = '-';
    if ($row['tanggal_upload'] instanceof DateTime) {
        $tgl_upload = $row['tanggal_upload']->format('d/m/Y H:i');
    } elseif (!empty($row['tanggal_upload'])) {
        $tgl_upload = $row['tanggal_upload'];
    }

    $tgl_revisi = '-';
    if ($row['tanggal_revisi'] instanceof DateTime) {
        $tgl_revisi = $row['tanggal_revisi']->format('d/m/Y');
    } elseif (!empty($row['tanggal_revisi'])) {
        $tgl_revisi = $row['tanggal_revisi'];
    }

    $tgl_terbit = '-';
    if ($row['tanggal_terbit'] instanceof DateTime) {
        $tgl_terbit = $row['tanggal_terbit']->format('d/m/Y');
    } elseif (!empty($row['tanggal_terbit'])) {
        $tgl_terbit = $row['tanggal_terbit'];
    }

    // Format no dokumen dengan tanggal terbit
    $no_dokumen_display = $row['no_dokumen'];
    if ($tgl_terbit !== '-') {
        $no_dokumen_display = $tgl_terbit . "\n" . $row['no_dokumen'];
    }

    // Format revisi dengan tanggal revisi
    $revisi_display = $row['revisi'] ?? '1';
    if ($tgl_revisi !== '-') {
        $revisi_display = $tgl_revisi . "\nRev. " . $revisi_display;
    } else {
        $revisi_display = "Rev. " . $revisi_display;
    }

    // Format departemen/bagian
    $dept_bagian = $row['dept'] ?? '-';
    if (!empty($row['bagian'])) {
        $dept_bagian .= "\n" . $row['bagian'];
    }

    $cells = [
        'no'          => $no,
        'no_dokumen'  => $no_dokumen_display,
        'nama_dokumen' => ($row['nama_kategori'] ?? '-') . "\n" . ($row['nama_dokumen'] ?? '-'),
        'kategori'    => $row['nama_kategori'] ?? '-',
        'revisi'      => $revisi_display,
        'dept_bagian' => $dept_bagian,
        'tgl_upload'  => $tgl_upload,
        'deskripsi'   => $row['deskripsi'] ?? '-',
        'uploader'    => $row['upduser'] ?? '-'
    ];

    $rowHeights = [];
    foreach ($cells as $key => $val) {
        $rowHeights[] = $pdf->getStringHeight($maxWidths[$key], $val, false, true, '', 1);
    }
    $rowHeight = max($rowHeights);

    foreach ($cells as $key => $val) {
        $align = in_array($key, ['no','revisi']) ? 'C' : 'L';
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
    // Cek jika perlu halaman baru
    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
        printTableHeader($pdf, $maxWidths);
        $fill = false;
    }
    $fill = printTableRow($pdf, $row, $no++, $maxWidths, $fill);
}

// Jumlah data
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 10, 'Total Dokumen: ' . count($dataRows), 0, 1, 'R');

// Footer
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Dokumen ini dicetak secara otomatis dari Sistem E-Dokumen ISO', 0, 1, 'C');
$pdf->Cell(0, 5, 'Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages(), 0, 0, 'C');

// Output PDF
$pdf->Output('Laporan_Dokumen_ISO_' . date('Ymd_His') . '.pdf', 'I');

// Clean up
sqlsrv_free_stmt($stmt);
?>