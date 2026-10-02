<?php
// pages/fabric_knowledge/generate_pdf_single.php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';

// ====== Set Timezone Indonesia ======
date_default_timezone_set('Asia/Jakarta');

// ====== Auth ======
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// ====== Permission Check ======
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $perm = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $perm = $row;
    }
    return $perm;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);
if ($permissions['CanView'] != 1) {
    die("Anda tidak memiliki hak untuk mengakses laporan ini.");
}

// ====== Ambil ID Problem ======
$id = $_GET['id'] ?? '';
if (empty($id)) {
    die("ID masalah tidak ditemukan.");
}

// ====== Fungsi Format Tanggal ======
function formatTanggal($date)
{
    if (!$date)
        return '-';
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y H:i');
    }
    return date('d-m-Y H:i', strtotime($date));
}

// ====== Ambil Data Problem dan Solution ======
$sql = "SELECT 
            p.*, 
            k.nama_kategori, 
            t.nama_tag,
            s.*,
            COALESCE(s.status, 'Open') as problem_status,
            s.created_by as solution_created_by,
            s.created_at as solution_created_at,
            s.updated_by as solution_updated_by,
            s.updated_at as solution_updated_at
        FROM dbo.fab_m_problem p
        LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori
        LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
        LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
        WHERE p.id_problem = ?";

$stmt = sqlsrv_query($conn, $sql, [$id]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("Data masalah tidak ditemukan.");
}

// ====== Create PDF Document ======
$pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Knowledge Base System');
$pdf->SetAuthor('PT. Surya Usaha Mandiri');
$pdf->SetTitle('Laporan Analisa & Solusi Masalah');
$pdf->SetSubject('Analisa & Solusi Masalah');
$pdf->SetKeywords('Kain, Masalah, Analisa, Solusi');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 20);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// === HEADER ===
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(30, 30, 120);
$pdf->Cell(0, 8, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'LAPORAN ANALISA & SOLUSI MASALAH', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'No. Dokumen: SUM-LP-RD-DF-002', 0, 1, 'C');

// Garis pemisah
$pdf->SetLineWidth(0.5);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY() + 3, 195, $pdf->GetY() + 3);
$pdf->Ln(8);

// === INFORMASI DOKUMEN ===
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 6, 'INFORMASI DOKUMEN', 0, 1, 'L');
$pdf->Ln(2);

// Tabel informasi dokumen
$infoWidth = 40;
$pdf->SetFont('helvetica', '', 9);

$pdf->Cell($infoWidth, 5, 'Nomor Masalah:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'KB-' . str_pad($data['id_problem'], 4, '0', STR_PAD_LEFT), 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($infoWidth, 5, 'Tanggal Cetak:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, date('d-m-Y H:i') . ' WIB', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($infoWidth, 5, 'Dicetak Oleh:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, $_SESSION['UserName'], 0, 1, 'L');

$pdf->Ln(5);

// === INFORMASI PRODUKSI ===
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(60, 100, 150);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'INFORMASI PRODUKSI', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetTextColor(0, 0, 0);

// Data informasi produksi
$productionData = array(
    array('No Kartu Produksi', !empty($data['nocp']) ? $data['nocp'] : '-'),
    array('Kategori', $data['nama_kategori']),
    array('Tag', $data['nama_tag'] ?: '-'),
    array('Status', $data['problem_status']),
    array('Dilaporkan Oleh', $data['created_by']),
    array('Tanggal Laporan', formatTanggal($data['created_at'])),
    array('Update Terakhir', formatTanggal($data['updated_at'] ?? $data['created_at']))
);

foreach ($productionData as $row) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 5, $row[0] . ':', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);

    if ($row[0] === 'Status') {
        // Status dengan styling
        $status = $row[1];
        if ($status === 'Solved') {
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(40, 167, 69);
        } elseif ($status === 'Reopen') {
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(220, 53, 69);
        } else {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 193, 7);
        }
        $pdf->Cell(0, 5, ' ' . $status . ' ', 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->Cell(0, 5, $row[1], 0, 1, 'L');
    }
}

$pdf->Ln(5);

// === INFORMASI TEKNIS & PROSES ===
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(80, 120, 160);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'INFORMASI TEKNIS & PROSES', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetTextColor(0, 0, 0);

// Data dalam grid 2 kolom dengan layout yang lebih rapi
$techData = array(
    array('Kode Warna', !empty($data['color']) ? $data['color'] : '-'),
    array('Routing', !empty($data['routing']) ? $data['routing'] : '-'),
    array('Status QC', !empty($data['status_qc']) ? $data['status_qc'] : '-'),
    array('Tgl Lkp Qc', !empty($data['tgl_lkp_qc']) ? ($data['tgl_lkp_qc'] instanceof DateTime ? $data['tgl_lkp_qc']->format('d-m-Y') : date('d-m-Y', strtotime($data['tgl_lkp_qc']))) : '-'),
    array('PDR', !empty($data['pdr']) ? $data['pdr'] : '-'),
    array('Resep', !empty($data['resep']) ? $data['resep'] : '-'),
    array('Analis', !empty($data['analis']) ? $data['analis'] : '-')
);

$colWidth = 85;
$labelWidth = 30;
$valueWidth = 50;

for ($i = 0; $i < count($techData); $i += 2) {
    // Kolom kiri
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($labelWidth, 5, $techData[$i][0] . ':', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);

    if ($techData[$i][0] === 'Status QC') {
        // Format Status QC dengan box yang konsisten
        $status = $techData[$i][1];
        if ($status === 'Pass' || $status === 'Lulus') {
            $pdf->SetFillColor(40, 167, 69);
            $pdf->SetTextColor(255, 255, 255);
        } elseif ($status === 'Fail' || $status === 'Gagal' || $status === 'Reject') {
            $pdf->SetFillColor(220, 53, 69);
            $pdf->SetTextColor(255, 255, 255);
        } else {
            $pdf->SetFillColor(255, 193, 7);
            $pdf->SetTextColor(0, 0, 0);
        }
        $statusText = $status !== '-' ? $status : 'Pending';
        $pdf->Cell(20, 5, ' ' . $statusText . ' ', 0, 0, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        // Tambahkan space untuk alignment
        $pdf->Cell($colWidth - $labelWidth - 20, 5, '', 0, 0, 'L');
    } elseif ($techData[$i][0] === 'PDR') {
        // Format PDR dengan box yang konsisten
        $pdrValue = $techData[$i][1];
        if ($pdrValue !== '-') {
            // Jika PDR ada nilai, beri background
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(25, 5, ' ' . $pdrValue . ' ', 0, 0, 'C', true);
            $pdf->Cell($colWidth - $labelWidth - 25, 5, '', 0, 0, 'L');
        } else {
            $pdf->Cell($colWidth - $labelWidth, 5, $pdrValue, 0, 0, 'L');
        }
    } else {
        $pdf->Cell($colWidth - $labelWidth, 5, $techData[$i][1], 0, 0, 'L');
    }

    // Kolom kanan (jika ada)
    if (isset($techData[$i + 1])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($labelWidth, 5, $techData[$i + 1][0] . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);

        if ($techData[$i + 1][0] === 'Status QC') {
            // Format Status QC untuk kolom kanan
            $status = $techData[$i + 1][1];
            if ($status === 'Pass' || $status === 'Lulus') {
                $pdf->SetFillColor(40, 167, 69);
                $pdf->SetTextColor(255, 255, 255);
            } elseif ($status === 'Fail' || $status === 'Gagal' || $status === 'Reject') {
                $pdf->SetFillColor(220, 53, 69);
                $pdf->SetTextColor(255, 255, 255);
            } else {
                $pdf->SetFillColor(255, 193, 7);
                $pdf->SetTextColor(0, 0, 0);
            }
            $statusText = $status !== '-' ? $status : 'Pending';
            $pdf->Cell(20, 5, ' ' . $statusText . ' ', 0, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        } elseif ($techData[$i + 1][0] === 'PDR') {
            // Format PDR untuk kolom kanan
            $pdrValue = $techData[$i + 1][1];
            if ($pdrValue !== '-') {
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(25, 5, ' ' . $pdrValue . ' ', 0, 1, 'C', true);
            } else {
                $pdf->Cell(0, 5, $pdrValue, 0, 1, 'L');
            }
        } else {
            $pdf->Cell(0, 5, $techData[$i + 1][1], 0, 1, 'L');
        }
    } else {
        $pdf->Ln(5);
    }
}

$pdf->Ln(8);

// === DESKRIPSI MASALAH ===
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(60, 100, 150);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'DESKRIPSI MASALAH', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

if (!empty($data['deskripsi'])) {
    $pdf->MultiCell(0, 6, $data['deskripsi'], 0, 'L');
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(0, 6, 'Tidak ada deskripsi masalah yang tercatat.', 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
}

$pdf->Ln(8);

// === ANALISA & SOLUSI ===
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(60, 100, 150);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'ANALISA & SOLUSI', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetTextColor(0, 0, 0);

// Analisa Akar Masalah
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 30, 120);
$pdf->Cell(0, 6, 'Analisa Akar Masalah:', 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
if (!empty($data['analisa_akar_masalah'])) {
    $pdf->MultiCell(0, 5, $data['analisa_akar_masalah'], 0, 'L');
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(0, 5, 'Belum ada analisa akar masalah yang tercatat.', 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(5);

// Tindakan Perbaikan
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 30, 120);
$pdf->Cell(0, 6, 'Tindakan Perbaikan:', 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
if (!empty($data['tindakan_perbaikan'])) {
    $pdf->MultiCell(0, 5, $data['tindakan_perbaikan'], 0, 'L');
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(0, 5, 'Belum ada tindakan perbaikan yang tercatat.', 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(5);

// Tindakan Pencegahan
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 30, 120);
$pdf->Cell(0, 6, 'Tindakan Pencegahan:', 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
if (!empty($data['tindakan_pencegahan'])) {
    $pdf->MultiCell(0, 5, $data['tindakan_pencegahan'], 0, 'L');
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(0, 5, 'Belum ada tindakan pencegahan yang tercatat.', 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(8);

// === INFORMASI TIM ===
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 6, 'INFORMASI TIM ANALISA', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 9);
$analisaInfo = "Dianalisa oleh: " . ($data['solution_created_by'] ?? $data['created_by']) .
    " pada " . formatTanggal($data['solution_created_at'] ?? $data['created_at']);

if (!empty($data['solution_updated_by']) && $data['solution_updated_by'] != ($data['solution_created_by'] ?? $data['created_by'])) {
    $analisaInfo .= "\nDiupdate oleh: " . $data['solution_updated_by'] .
        " pada " . formatTanggal($data['solution_updated_at']);
}

$pdf->MultiCell(0, 4, $analisaInfo, 0, 'L');

// === FOOTER ===
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);

// Garis footer
$pdf->SetLineWidth(0.3);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY() - 2, 195, $pdf->GetY() - 2);

$pdf->Cell(0, 5, 'Dokumen ini dicetak secara otomatis dari Knowledge Base System - PT. Surya Usaha Mandiri', 0, 1, 'C');
$pdf->Cell(0, 5, 'No. Dokumen: SUM-LP-RD-DF-002 - Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages() . ' - Dicetak: ' . date('d-m-Y H:i') . ' WIB', 0, 0, 'C');

// === OUTPUT PDF ===
$filename = 'Laporan_Masalah_' . (!empty($data['nocp']) ? $data['nocp'] : 'KB-' . str_pad($data['id_problem'], 4, '0', STR_PAD_LEFT)) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');

// Tutup koneksi
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>